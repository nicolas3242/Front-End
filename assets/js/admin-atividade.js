/**
 * assets/js/admin-atividade.js — formulário de atividade da Administração.
 * -----------------------------------------------------------------------------
 * Faz duas coisas:
 *
 * 1. Mostra só os campos que importam para o tipo escolhido. Quiz não tem caso
 *    de teste; exercício de código não tem alternativa. Deixar os dois na tela
 *    ao mesmo tempo convida a preencher o campo errado.
 *
 * 2. CONFERE O GABARITO antes de deixar salvar — a parte que justifica esta
 *    tela existir.
 *
 *    A conferência roda a solução de referência no MESMO Web Worker que executa
 *    o código do aluno (assets/js/executor.worker.js) e compara o resultado com
 *    os casos de teste usando a MESMA normalização do servidor. Se a solução não
 *    passar nos próprios casos, o exercício está quebrado — e é melhor descobrir
 *    agora do que quando um aluno travar nele.
 *
 *    Vale ser honesto sobre o limite: isto roda no navegador, então um admin
 *    determinado contornaria. Mas o adversário aqui não é o admin — é o erro
 *    humano de quem digitou uma saída esperada errada às onze da noite.
 */

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('formAtividade');
    if (!form) {
        return;
    }

    var campoTipo   = document.getElementById('tipo');
    var blocoQuiz   = document.getElementById('blocoQuiz');
    var blocoCodigo = document.getElementById('blocoCodigo');
    var blocoCasos  = document.getElementById('blocoCasos');
    var campoDica   = document.getElementById('campoDica');
    var ehProva     = form.dataset.ehprova === '1';

    var conferido   = document.getElementById('gabaritoConferido');
    var aviso       = document.getElementById('resultadoConferencia');
    var detalhe     = document.getElementById('detalheConferencia');
    var btnConferir = document.getElementById('btnConferir');

    var LIMITE_MS = 3000;

    /* ---------------------------------------------------------------------
     * Campos conforme o tipo
     * ------------------------------------------------------------------ */

    function tipoAtual() {
        return ehProva ? 'quiz' : (campoTipo ? campoTipo.value : 'quiz');
    }

    function ajustarCampos() {
        var quiz = (tipoAtual() === 'quiz');

        if (blocoQuiz)   { blocoQuiz.hidden   = !quiz; }
        if (blocoCodigo) { blocoCodigo.hidden = quiz; }
        if (blocoCasos)  { blocoCasos.hidden  = quiz; }
        if (campoDica)   { campoDica.hidden   = false; }   // dica serve aos dois

        // Trocar de tipo invalida a conferência anterior: os casos mudaram.
        if (quiz) {
            marcarConferido(false, '');
        }
    }

    if (campoTipo) {
        campoTipo.addEventListener('change', ajustarCampos);
    }
    ajustarCampos();

    /* ---------------------------------------------------------------------
     * Linhas de caso de teste
     * ------------------------------------------------------------------ */

    var listaCasos = document.getElementById('listaCasos');
    var btnAddCaso = document.getElementById('btnAddCaso');

    /**
     * Os checkboxes usam índice no nome (caso_oculto[2]) porque o PHP precisa
     * saber a QUAL linha cada marcação pertence — um checkbox desmarcado não é
     * enviado, então uma lista simples sairia desalinhada das outras.
     */
    function renumerarCasos() {
        if (!listaCasos) {
            return;
        }
        var linhas = listaCasos.querySelectorAll('.caso-admin');
        for (var i = 0; i < linhas.length; i++) {
            var caixa = linhas[i].querySelector('input[type="checkbox"]');
            if (caixa) {
                caixa.name = 'caso_oculto[' + i + ']';
            }
        }
    }

    if (btnAddCaso && listaCasos) {
        btnAddCaso.addEventListener('click', function () {
            var linha = document.createElement('div');
            linha.className = 'caso-admin';
            linha.innerHTML =
                '<input type="text" name="caso_descricao[]" placeholder="Descrição (opcional)" class="caso-admin-descricao">' +
                '<input type="text" name="caso_entrada[]" placeholder="' +
                    (form.dataset.linguagem === 'html' ? 'Verificação — texto(&quot;h1&quot;)' : 'Entrada — somar(10, 5)') +
                    '" spellcheck="false">' +
                '<input type="text" name="caso_saida[]" placeholder="Saída esperada — 15" spellcheck="false">' +
                '<label class="caso-admin-oculto" title="Não aparece para o aluno">' +
                    '<input type="checkbox" value="1"><span>oculto</span>' +
                '</label>' +
                '<button type="button" class="botao botao-secundario botao-icone remover-caso" title="Remover este caso">&times;</button>';

            listaCasos.appendChild(linha);
            renumerarCasos();
            marcarConferido(false, 'Os casos mudaram. Confira o gabarito de novo antes de salvar.');
        });
    }

    if (listaCasos) {
        listaCasos.addEventListener('click', function (evento) {
            if (!evento.target.classList.contains('remover-caso')) {
                return;
            }
            var linha = evento.target.closest('.caso-admin');
            if (linha) {
                linha.remove();
                renumerarCasos();
                marcarConferido(false, 'Os casos mudaram. Confira o gabarito de novo antes de salvar.');
            }
        });

        // Qualquer edição invalida a conferência: o que passou não é mais o que
        // está na tela.
        listaCasos.addEventListener('input', function () {
            marcarConferido(false, '');
        });
    }

    var campoSolucao = document.getElementById('solucao_exemplo');
    if (campoSolucao) {
        campoSolucao.addEventListener('input', function () {
            marcarConferido(false, '');
        });
    }

    /* ---------------------------------------------------------------------
     * Conferência do gabarito
     * ------------------------------------------------------------------ */

    /** Mesma normalização de api/processar_desafio.php. */
    function normalizar(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
            .trim().replace(/\s+/g, ' ').toLowerCase();
    }

    /** Lê os casos preenchidos no formulário. */
    function casosDoFormulario() {
        var casos = [];
        if (!listaCasos) {
            return casos;
        }

        var linhas = listaCasos.querySelectorAll('.caso-admin');
        for (var i = 0; i < linhas.length; i++) {
            var entrada = linhas[i].querySelector('input[name="caso_entrada[]"]').value;
            var saida   = linhas[i].querySelector('input[name="caso_saida[]"]').value;
            var caixa   = linhas[i].querySelector('input[type="checkbox"]');

            if (saida.trim() === '') {
                continue;                         // linha em branco é ignorada
            }
            casos.push({
                id: i,
                entrada: entrada.trim(),
                saida_esperada: saida,
                oculto: !!(caixa && caixa.checked)
            });
        }
        return casos;
    }

    function marcarConferido(ok, mensagem) {
        conferido.value = ok ? 'ok' : '';

        if (!ok && mensagem === '') {
            aviso.className = 'aviso';
            aviso.textContent = '';
            if (detalhe) { detalhe.hidden = true; }
            return;
        }

        aviso.className = 'aviso aviso-' + (ok ? 'sucesso' : 'erro');
        aviso.textContent = mensagem;
    }

    function executarNoWorker(codigo, casos) {
        // Trilha de HTML e CSS: mesmo executor que o aluno usa (iframe isolado).
        if (form.dataset.linguagem === 'html' && window.DevFoxHtml) {
            return DevFoxHtml.executar(codigo, casos);
        }

        return new Promise(function (resolve, reject) {
            var worker;
            try {
                worker = new Worker('assets/js/executor.worker.js');
            } catch (e) {
                reject(new Error('Não foi possível iniciar o executor de código.'));
                return;
            }

            var cronometro = setTimeout(function () {
                worker.terminate();
                reject(new Error('A solução demorou mais de ' + (LIMITE_MS / 1000) +
                                 ' segundos. Há algum laço que nunca termina?'));
            }, LIMITE_MS);

            worker.onmessage = function (evento) {
                clearTimeout(cronometro);
                worker.terminate();
                resolve((evento.data && evento.data.resultados) || []);
            };

            worker.onerror = function (evento) {
                clearTimeout(cronometro);
                worker.terminate();
                reject(new Error(evento.message || 'Erro ao executar a solução.'));
            };

            worker.postMessage({ codigo: codigo, casos: casos });
        });
    }

    function escapar(texto) {
        var d = document.createElement('div');
        d.textContent = (texto === null || texto === undefined) ? '' : String(texto);
        return d.innerHTML;
    }

    function mostrarDetalhe(casos, resultados) {
        if (!detalhe) {
            return;
        }

        var html = '<strong class="painel-titulo-menor">Resultado da conferência</strong>';

        casos.forEach(function (caso, i) {
            var r = resultados[i] || {};
            var obtido = r.erro ? null : r.saida;
            var passou = !r.erro && normalizar(obtido) === normalizar(caso.saida_esperada);

            html += '<div class="linha-resultado ' + (passou ? 'caso-passou' : 'caso-falhou') + '">';
            html += '<span class="resultado-marca">' + (passou ? '✓' : '✕') + '</span>';
            html += '<code class="resultado-entrada">' +
                    escapar(caso.entrada || 'console.log') + '</code>';

            if (r.erro) {
                html += '<span class="resultado-erro">⚠ ' + escapar(r.erro) + '</span>';
            } else if (passou) {
                html += '<span class="resultado-seta">→</span>';
                html += '<code class="resultado-saida">' + escapar(obtido) + '</code>';
            } else {
                html += '<span class="resultado-erro">esperava <code>' +
                        escapar(caso.saida_esperada) + '</code>, veio <code>' +
                        escapar(obtido) + '</code></span>';
            }

            html += '</div>';
        });

        detalhe.innerHTML = html;
        detalhe.hidden = false;
    }

    if (btnConferir) {
        btnConferir.addEventListener('click', function () {
            var solucao = (campoSolucao ? campoSolucao.value : '').trim();
            var casos   = casosDoFormulario();

            if (solucao === '') {
                marcarConferido(false, 'Escreva a solução de referência antes de conferir.');
                return;
            }
            if (casos.length === 0) {
                marcarConferido(false, 'Preencha pelo menos um caso de teste (com saída esperada).');
                return;
            }

            btnConferir.disabled = true;
            marcarConferido(false, 'Executando a solução…');
            aviso.className = 'aviso aviso-info';

            executarNoWorker(solucao, casos)
                .then(function (resultados) {
                    mostrarDetalhe(casos, resultados);

                    var falhou = [];
                    casos.forEach(function (caso, i) {
                        var r = resultados[i] || {};
                        var passou = !r.erro &&
                            normalizar(r.saida) === normalizar(caso.saida_esperada);
                        if (!passou) {
                            falhou.push(caso.entrada || 'console.log');
                        }
                    });

                    if (falhou.length === 0) {
                        marcarConferido(true,
                            'Gabarito conferido: a solução passa nos ' + casos.length +
                            ' caso(s). Pode salvar.');
                    } else {
                        marcarConferido(false,
                            'A solução NÃO passa em ' + falhou.length + ' caso(s): ' +
                            falhou.join(', ') + '. Veja o detalhe abaixo — ou a saída esperada ' +
                            'está errada, ou a solução está.');
                    }
                })
                .catch(function (erro) {
                    marcarConferido(false, erro.message);
                    if (detalhe) { detalhe.hidden = true; }
                })
                .finally(function () {
                    btnConferir.disabled = false;
                });
        });
    }

    /* ---------------------------------------------------------------------
     * Trava no envio
     * ------------------------------------------------------------------ */

    form.addEventListener('submit', function (evento) {
        if (tipoAtual() === 'quiz') {
            return;                               // quiz não tem gabarito a executar
        }

        if (conferido.value !== 'ok') {
            evento.preventDefault();
            marcarConferido(false,
                'Confira o gabarito antes de salvar. A solução de referência precisa passar ' +
                'em todos os casos de teste.');
            btnConferir.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
});
