/**
 * assets/js/desafios.js
 * -----------------------------------------------------------------------------
 * Tela de desafio: executa o código do aluno e envia o resultado ao servidor.
 *
 * Fluxo dos exercícios de código:
 *   1. o aluno clica em "Rodar código"
 *   2. o código vai para o Web Worker, que o executa contra as ENTRADAS dos
 *      casos de teste (as saídas esperadas dos casos ocultos nunca chegam ao
 *      navegador)
 *   3. o worker devolve o que o código produziu em cada caso
 *   4. esse resultado é enviado ao servidor, que compara com o gabarito e
 *      decide se passou
 *
 * O julgamento fica no servidor de propósito: qualquer coisa decidida aqui
 * poderia ser forjada pelo aluno no DevTools.
 */

/** Token CSRF da página (meta no <head>, includes/header.php). As APIs recusam POST sem ele. */
function tokenCsrf() {
    var meta = document.querySelector('meta[name="csrf"]');
    return meta ? meta.getAttribute('content') : '';
}

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('formDesafio');
    if (!form) {
        return;
    }

    var feedback   = document.getElementById('feedbackMsg');
    var painel     = document.getElementById('painelResultados');
    var botaoRodar = document.getElementById('btnRodar');
    var botaoEnviar = form.querySelector('button[type="submit"]');
    var campoCodigo = document.getElementById('codigo');

    var tipo  = form.dataset.tipo;
    var ehQuiz = (tipo === 'quiz');

    // Trilhas de HTML e CSS usam outro executor (assets/js/executor-html.js):
    // o código vira uma página num iframe isolado, e os casos são verificações
    // sobre essa página. O resto da tela — envio, veredito, XP — é o mesmo.
    var ehHtml = (form.dataset.linguagem === 'html') && !!window.DevFoxHtml;
    var ehPython = form.dataset.linguagem === 'python';
    var previa = document.getElementById('previaHtml');

    /* ---------------------------------------------------------------------
     * Editor de código
     * ------------------------------------------------------------------ */

    /**
     * O CodeMirror substitui o <textarea>, mas o <textarea> continua existindo
     * por baixo — é ele que o formulário envia. Por isso todo acesso ao código
     * passa por lerCodigo(): assim o resto do arquivo não precisa saber se o
     * editor carregou ou não.
     *
     * Se os arquivos do editor não carregarem (rede, caminho errado, navegador
     * antigo), `window.CodeMirror` fica indefinido e a tela cai de volta para o
     * <textarea> simples. O aluno perde o destaque de sintaxe, não o exercício.
     */
    var editor = null;

    if (!ehQuiz && campoCodigo && window.CodeMirror) {
        editor = CodeMirror.fromTextArea(campoCodigo, {
            mode: ehHtml ? 'htmlmixed' : (ehPython ? 'python' : 'javascript'),
            theme: 'devfox',
            lineNumbers: true,
            // Python exige indentação consistente nos blocos (if/elif/else).
            // Mantém 2 espaços nas outras linguagens e usa 4 no Python.
            indentUnit: ehPython ? 4 : 2,
            tabSize: ehPython ? 4 : 2,
            // Tab insere a indentação configurada; com seleção, indenta o bloco.
            // Shift+Tab reduz a indentação do bloco/linha selecionada.
            extraKeys: {
                Tab: function (cm) {
                    if (cm.somethingSelected()) {
                        cm.indentSelection('add');
                    } else {
                        cm.replaceSelection(Array(cm.getOption('indentUnit') + 1).join(' '), 'end');
                    }
                },
                'Shift-Tab': 'indentLess',
                // Backspace remove um nível de indentação de uma só vez,
                // quando o cursor está no espaço em branco no início da linha.
                Backspace: function (cm) {
                    var cursor = cm.getCursor();
                    var linha = cm.getLine(cursor.line);
                    var antes = linha.slice(0, cursor.ch);
                    var unidade = cm.getOption('indentUnit');

                    if (!cm.somethingSelected() && /^\s+$/.test(antes) && antes.length > 0) {
                        var remover = Math.min(unidade, antes.length);
                        cm.replaceRange('', { line: cursor.line, ch: cursor.ch - remover }, cursor);
                    } else {
                        return CodeMirror.Pass;
                    }
                }
            },
            lineWrapping: true,        // sem rolagem horizontal no celular
            matchBrackets: true,
            autoCloseBrackets: true,
            viewportMargin: Infinity,
            // A altura do editor acompanha o conteúdo e as linhas quebram, então
            // as barras de rolagem do CodeMirror nunca são necessárias. Sem esta
            // opção, o Windows desenha uma barra clara atravessando o editor.
            scrollbarStyle: 'null',
            // Coluna à esquerda dos números, onde entra a marca de erro.
            gutters: ['gutter-erro', 'CodeMirror-linenumbers']
        });

        // Mantém o <textarea> em dia a cada tecla, para que um envio sem
        // JavaScript (ou um submit nativo) leve o código certo.
        // A marca de erro sai assim que o aluno mexe no código: ela vale para a
        // execução anterior, e deixá-la na tela apontaria para um código que
        // não existe mais.
        editor.on('change', function () { editor.save(); limparErro(); });

        // HTML: a prévia acompanha a digitação (com uma pausa, para não
        // redesenhar a cada tecla).
        if (ehHtml && previa) {
            var espera = null;
            editor.on('change', function () {
                clearTimeout(espera);
                espera = setTimeout(function () { DevFoxHtml.mostrarPrevia(previa, lerCodigo()); }, 400);
            });
        }

        // Exposto para os testes automatizados escreverem no editor.
        window.editorDevFox = editor;
    }

    /** O código que está na tela agora, venha do editor ou do textarea. */
    function lerCodigo() {
        return editor ? editor.getValue() : (campoCodigo ? campoCodigo.value : '');
    }

    /* ---------------------------------------------------------------------
     * Marca da linha com erro
     *
     * Quem descobre a linha é o executor (assets/js/executor.worker.js), que é
     * onde o código roda de verdade. Aqui só pintamos: fundo vermelho na linha,
     * número em destaque e um aviso na coluna da esquerda, como num editor.
     * ------------------------------------------------------------------ */

    var linhaMarcada = null;

    function limparErro() {
        if (!editor || linhaMarcada === null) {
            return;
        }
        editor.removeLineClass(linhaMarcada, 'background', 'linha-com-erro');
        editor.removeLineClass(linhaMarcada, 'wrap', 'linha-erro');
        editor.setGutterMarker(linhaMarcada, 'gutter-erro', null);
        linhaMarcada = null;
    }

    /** `numero` é a linha como o aluno a vê: a primeira é 1. */
    function marcarErro(numero) {
        limparErro();

        if (!editor || !numero || numero < 1 || numero > editor.lineCount()) {
            return;
        }

        var indice = numero - 1;
        var marca = document.createElement('span');
        marca.className = 'marca-erro';
        marca.textContent = '●';
        marca.title = 'Erro nesta linha';

        editor.addLineClass(indice, 'background', 'linha-com-erro');
        editor.addLineClass(indice, 'wrap', 'linha-erro');
        editor.setGutterMarker(indice, 'gutter-erro', marca);
        linhaMarcada = indice;
    }

    /* ---------------------------------------------------------------------
     * Dica sob demanda
     * ------------------------------------------------------------------ */

    /**
     * O texto da dica não vem no HTML: é buscado quando o aluno abre o bloco.
     * É nessa ida ao servidor que o uso fica registrado, e é o que sustenta a
     * conquista "Por Conta Própria" — ver api/dica.php.
     */
    var blocoDica = document.getElementById('blocoDica');

    if (blocoDica) {
        var dicaCarregada = false;

        blocoDica.addEventListener('toggle', function () {
            if (!blocoDica.open || dicaCarregada) {
                return;
            }
            dicaCarregada = true;       // marcado antes do fetch: evita pedido duplo

            var alvo = document.getElementById('textoDica');
            var dados = new FormData();
            dados.append('atividade_id', blocoDica.dataset.atividade);
            dados.append('csrf', tokenCsrf());

            fetch('api/dica.php', { method: 'POST', body: dados })
                .then(function (r) { return r.json(); })
                .then(function (retorno) {
                    if (retorno && retorno.sucesso) {
                        alvo.textContent = retorno.dica;
                    } else {
                        alvo.textContent = (retorno && retorno.mensagem) || 'Não foi possível carregar a dica.';
                        dicaCarregada = false;
                    }
                })
                .catch(function () {
                    alvo.textContent = 'Não foi possível carregar a dica. Verifique sua conexão.';
                    dicaCarregada = false;
                });
        });
    }

    /** Casos de teste: só id e entrada. Nenhum gabarito vem para cá. */
    var casos = [];
    var dadosCasos = document.getElementById('dadosCasos');
    if (dadosCasos) {
        try {
            casos = JSON.parse(dadosCasos.textContent) || [];
        } catch (e) {
            casos = [];
        }
    }

    /** Tempo máximo de execução antes de considerar que travou. */
    var LIMITE_MS = ehPython ? 30000 : 3000;

    /** Guarda o resultado da última execução, para enviar ao servidor. */
    var ultimaExecucao = null;

    function mostrar(texto, classe) {
        feedback.className = 'aviso aviso-' + classe;
        feedback.textContent = texto;
    }

    function limparFeedback() {
        feedback.className = 'aviso';
        feedback.textContent = '';
    }

    /* ---------------------------------------------------------------------
     * Execução do código no Web Worker
     * ------------------------------------------------------------------ */

    /**
     * Roda o código do aluno e devolve uma Promise com os resultados.
     *
     * O worker roda numa thread separada justamente para que um laço infinito
     * possa ser interrompido: passado o limite de tempo, a thread principal
     * encerra o worker e a página continua respondendo.
     */
    function executarCodigo(codigo) {
        if (ehHtml) {
            if (previa) { DevFoxHtml.mostrarPrevia(previa, codigo); }
            return DevFoxHtml.executar(codigo, casos);
        }

        return new Promise(function (resolve, reject) {
            var worker;

            try {
                // Usa a URL versionada pelo PHP para evitar que o navegador
                // continue carregando uma cópia antiga do worker Python em cache.
                var urlWorkerPython = form.dataset.workerPython || 'assets/js/executor-python.worker.js';
                worker = new Worker(ehPython ? urlWorkerPython : 'assets/js/executor.worker.js');
            } catch (e) {
                reject(new Error('Seu navegador não conseguiu iniciar o executor de código.'));
                return;
            }

            var cronometro = setTimeout(function () {
                worker.terminate();
                reject(new Error(
                    'Seu código demorou demais para responder (mais de ' +
                    (LIMITE_MS / 1000) + ' segundos). Verifique se há um laço que nunca termina.'
                ));
            }, LIMITE_MS);

            worker.onmessage = function (evento) {
                clearTimeout(cronometro);
                worker.terminate();
                if (evento.data && evento.data.erro) { reject(new Error(evento.data.erro)); return; }
                resolve((evento.data && evento.data.resultados) || []);
            };

            worker.onerror = function (evento) {
                clearTimeout(cronometro);
                worker.terminate();
                reject(new Error(evento.message || 'Erro ao executar o código.'));
            };

            worker.postMessage({ codigo: codigo, casos: casos });
        });
    }

    /* ---------------------------------------------------------------------
     * Painel de resultados
     * ------------------------------------------------------------------ */

    /**
     * Mostra o que o código produziu em cada caso.
     *
     * Enquanto o servidor não respondeu, nenhum caso é marcado como certo ou
     * errado — o navegador não sabe o gabarito, e fingir que sabe seria mentir
     * para o aluno.
     */
    function mostrarExecucao(resultados) {
        if (!painel) {
            return;
        }

        var html = '<strong class="painel-titulo">Resultado da execução</strong>';
        var comLinha = resultados.filter(function (r) { return r.erro && r.linha; })[0];

        // Todos os casos rodam o mesmo código, então o erro é o mesmo: basta a
        // primeira linha encontrada.
        if (comLinha) {
            marcarErro(comLinha.linha);
        } else {
            limparErro();
        }

        resultados.forEach(function (resultado) {
            var caso = casos.filter(function (c) { return c.id === resultado.caso_id; })[0] || {};
            var rotulo = caso.oculto ? 'Caso oculto' : (caso.entrada || 'Saída do console');

            html += '<div class="linha-resultado">';
            html += '<code class="resultado-entrada">' + escapar(rotulo) + '</code>';

            if (resultado.erro) {
                html += '<span class="resultado-erro">⚠ '
                     + (resultado.linha ? '<strong>Linha ' + parseInt(resultado.linha, 10) + ':</strong> ' : '')
                     + escapar(resultado.erro) + '</span>';
            } else if (caso.oculto) {
                html += '<span class="resultado-neutro">executado</span>';
            } else {
                html += '<span class="resultado-seta">→</span>';
                html += '<code class="resultado-saida">' + escapar(resultado.saida) + '</code>';
            }

            html += '</div>';
        });

        painel.innerHTML = html;
        painel.hidden = false;
    }

    /** Marca cada caso com o veredito que veio do servidor. */
    function aplicarVeredito(detalhes) {
        if (!painel || !detalhes) {
            return;
        }

        var linhas = painel.querySelectorAll('.linha-resultado');

        detalhes.forEach(function (detalhe, indice) {
            var linha = linhas[indice];
            if (!linha) {
                return;
            }
            linha.classList.add(detalhe.passou ? 'caso-passou' : 'caso-falhou');

            var marca = document.createElement('span');
            marca.className = 'resultado-marca';
            marca.textContent = detalhe.passou ? '✓' : '✕';
            linha.insertBefore(marca, linha.firstChild);
        });
    }

    function escapar(texto) {
        var div = document.createElement('div');
        div.textContent = (texto === null || texto === undefined) ? '' : String(texto);
        return div.innerHTML;
    }

    /* ---------------------------------------------------------------------
     * Botão "Rodar código"
     * ------------------------------------------------------------------ */

    if (botaoRodar && !ehQuiz) {
        // Guarda o conteúdo original (ícone de play + texto). Trocar só o
        // textContent apagava o <svg> do ícone depois da primeira execução.
        var conteudoRodar = botaoRodar.innerHTML;

        botaoRodar.addEventListener('click', function () {
            limparFeedback();
            botaoRodar.disabled = true;
            botaoRodar.textContent = 'Rodando…';

            executarCodigo(lerCodigo())
                .then(function (resultados) {
                    ultimaExecucao = resultados;
                    mostrarExecucao(resultados);

                    var comErro = resultados.filter(function (r) { return r.erro; });
                    if (comErro.length === resultados.length && resultados.length > 0) {
                        var linhaErro = comErro[0].linha;
                        mostrar('Seu código não executou'
                            + (linhaErro ? ': o erro está na linha ' + linhaErro + '.' : '. Veja o erro abaixo.'), 'erro');
                    } else {
                        mostrar('Código executado. Confira o resultado e envie sua resposta.', 'info');
                    }
                })
                .catch(function (erro) {
                    ultimaExecucao = null;
                    mostrar(erro.message, 'erro');
                })
                .finally(function () {
                    botaoRodar.disabled = false;
                    botaoRodar.innerHTML = conteudoRodar;
                });
        });
    }

    /* ---------------------------------------------------------------------
     * Envio da resposta
     * ------------------------------------------------------------------ */

    form.addEventListener('submit', function (evento) {
        evento.preventDefault();

        if (ehQuiz) {
            enviar(new FormData(form));
            return;
        }

        // Exercício de código: executa agora, para garantir que o que vai ao
        // servidor corresponde ao código que está na tela neste momento.
        botaoEnviar.disabled = true;
        mostrar('Executando seu código…', 'info');

        executarCodigo(lerCodigo())
            .then(function (resultados) {
                ultimaExecucao = resultados;
                mostrarExecucao(resultados);

                var dados = new FormData(form);
                dados.append('resultados', JSON.stringify(resultados));
                enviar(dados);
            })
            .catch(function (erro) {
                mostrar(erro.message, 'erro');
                botaoEnviar.disabled = false;
            });
    });

    function enviar(dados) {
        dados.set('csrf', tokenCsrf());
        botaoEnviar.disabled = true;
        // Troca a mensagem anterior na hora: sem isso, quem responde de novo
        // continua lendo o "incorreta" da tentativa passada até o servidor
        // responder — parece que a resposta nova também foi recusada.
        mostrar('Conferindo sua resposta…', 'info');

        fetch('api/processar_desafio.php', { method: 'POST', body: dados })
            .then(function (resposta) {
                if (resposta.status === 401) {
                    window.location.href = 'login.php';
                    return null;
                }
                return resposta.json();
            })
            .then(function (retorno) {
                if (!retorno) {
                    return;
                }

                aplicarVeredito(retorno.detalhes);

                if (retorno.sucesso) {
                    // Depois de concluir, a única ação principal deve ser avançar.
                    botaoEnviar.hidden = true;
                    mostrar(retorno.mensagem, 'sucesso');

                    // A API devolve as conquistas desbloqueadas por esta conclusão.
                    // Na tela de desafio elas aparecem na hora, sem recarregar.
                    if (window.DevFoxConquistas && retorno.conquistas) {
                        window.DevFoxConquistas.mostrar(retorno.conquistas);
                    }

                    oferecerProximaAtividade();
                } else {
                    mostrar(retorno.mensagem, 'erro');
                }
            })
            .catch(function () {
                mostrar('Não foi possível falar com o servidor. Verifique sua conexão e tente de novo.', 'erro');
            })
            .finally(function () {
                botaoEnviar.disabled = false;
            });
    }

    /** Depois de acertar, mostra o botão para seguir para a próxima atividade. */
    function oferecerProximaAtividade() {
        if (document.getElementById('btnProxima')) {
            return;
        }

        var proxima = form.dataset.proximaUrl;
        var acoes   = form.querySelector('.acoes-exercicio');
        var link    = document.createElement('a');
        var voltar  = document.createElement('a');

        link.id          = 'btnProxima';
        link.className   = 'botao botao-primario';
        link.href        = proxima || 'trilhas.php';
        link.textContent = form.dataset.proximaRotulo || (!proxima ? 'Voltar para as trilhas →' : 'Próxima questão →');
        acoes.appendChild(link);

        // Só oferece voltar às trilhas quando a última questão do módulo
        // foi concluída; nas demais questões, mantém apenas a próxima etapa.
        if (form.dataset.moduloConcluido === '1') {
            voltar.className = 'botao botao-contorno';
            voltar.href = 'trilhas.php';
            voltar.textContent = 'Voltar para as trilhas';
            voltar.style.marginLeft = '8px';
            acoes.appendChild(voltar);
        }
    }

    // Prévia inicial: o aluno já vê como o código de partida aparece na tela.
    if (ehHtml && previa) {
        DevFoxHtml.mostrarPrevia(previa, lerCodigo());
    }
});
