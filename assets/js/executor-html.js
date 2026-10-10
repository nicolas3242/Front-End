/**
 * assets/js/executor-html.js — executor dos exercícios de HTML e CSS.
 * -----------------------------------------------------------------------------
 * O executor de JavaScript (executor.worker.js) roda o código num Web Worker.
 * HTML e CSS não dá: Worker não tem DOM, e o que se quer conferir aqui é
 * justamente o DOM e o estilo calculado pelo navegador. Então o código do aluno
 * é renderizado num <iframe> isolado e as VERIFICAÇÕES são feitas nele.
 *
 * Isolamento do iframe:
 *   - sandbox="allow-same-origin" SEM "allow-scripts": nenhum <script> escrito
 *     pelo aluno executa (logo, nada de laço infinito nem de mexer na página);
 *     o allow-same-origin existe só para esta página conseguir LER o DOM dele.
 *   - Content-Security-Policy no documento: nenhum recurso externo é baixado
 *     (só CSS inline e imagens data:).
 *
 * Mesma regra do resto do sistema: este arquivo NÃO sabe o gabarito. Ele só
 * responde às verificações e devolve os resultados; quem compara com a saída
 * esperada é api/processar_desafio.php.
 *
 * AS VERIFICAÇÕES (campo `entrada` dos casos de teste):
 *
 *   existe("seletor")              → "sim" ou "não"
 *   contar("seletor")              → quantidade de elementos, ex.: "3"
 *   texto("seletor")               → texto do 1º elemento, espaços colapsados
 *   atributo("seletor", "nome")    → valor do atributo do 1º elemento
 *   estilo("seletor", "propriedade") → valor CALCULADO pelo navegador,
 *                                      ex.: "rgb(255, 107, 0)", "24px", "flex"
 *
 * A entrada é interpretada por um analisador próprio (argumentos em JSON),
 * nunca por eval: quem escreve o caso de teste não consegue injetar código.
 */

(function (global) {
    'use strict';

    var CSP = "default-src 'none'; style-src 'unsafe-inline'; img-src data:; font-src data:";
    var LIMITE_MS = 3000;

    var NENHUM = '(nenhum elemento)';
    var SEM_ATRIBUTO = '(sem atributo)';

    var FUNCOES = {
        existe:   1,
        contar:   1,
        texto:    1,
        atributo: 2,
        estilo:   2
    };

    /**
     * Monta o documento que vai no iframe: CSP primeiro, código do aluno depois.
     * O <!DOCTYPE> do aluno é retirado e recolocado no início — sem ele no topo
     * o navegador entraria em "quirks mode" e calcularia estilos diferente.
     */
    function montarDocumento(codigo) {
        var corpo = String(codigo || '').replace(/^\s*<!doctype[^>]*>/i, '');
        return '<!DOCTYPE html><meta http-equiv="Content-Security-Policy" content="' + CSP + '">' + corpo;
    }

    /** Transforma 'estilo("h1", "color")' em { nome: 'estilo', args: ['h1', 'color'] }. */
    function interpretar(entrada) {
        var m = /^\s*([a-z]+)\s*\(([\s\S]*)\)\s*$/.exec(String(entrada || ''));
        if (!m || !Object.prototype.hasOwnProperty.call(FUNCOES, m[1])) {
            throw new Error('Verificação desconhecida: ' + entrada);
        }

        var args;
        try {
            args = JSON.parse('[' + m[2] + ']');
        } catch (e) {
            throw new Error('Verificação mal escrita: ' + entrada);
        }

        if (args.length !== FUNCOES[m[1]] || args.some(function (a) { return typeof a !== 'string'; })) {
            throw new Error('Verificação com argumentos errados: ' + entrada);
        }

        return { nome: m[1], args: args };
    }

    function selecionarTodos(doc, seletor) {
        try {
            return doc.querySelectorAll(seletor);
        } catch (e) {
            throw new Error('Seletor inválido na verificação: ' + seletor);
        }
    }

    /** Responde UMA verificação sobre um documento já renderizado. */
    function avaliar(doc, entrada) {
        var v = interpretar(entrada);
        var lista = selecionarTodos(doc, v.args[0]);
        var el = lista[0] || null;

        switch (v.nome) {
            case 'existe':
                return lista.length > 0 ? 'sim' : 'não';

            case 'contar':
                return String(lista.length);

            case 'texto':
                return el ? el.textContent.replace(/\s+/g, ' ').trim() : NENHUM;

            case 'atributo':
                if (!el) { return NENHUM; }
                return el.hasAttribute(v.args[1]) ? el.getAttribute(v.args[1]) : SEM_ATRIBUTO;

            case 'estilo':
                if (!el) { return NENHUM; }
                var janela = doc.defaultView;
                return janela.getComputedStyle(el).getPropertyValue(v.args[1]).trim();
        }

        return '';
    }

    /** Cria um iframe isolado, fora da tela, e espera o documento carregar. */
    function renderizar(codigo) {
        return new Promise(function (resolve, reject) {
            var quadro = document.createElement('iframe');
            quadro.setAttribute('sandbox', 'allow-same-origin');
            quadro.setAttribute('aria-hidden', 'true');
            quadro.tabIndex = -1;
            // Fora da tela, mas com tamanho real: layout e estilo precisam ser
            // calculados como numa janela de verdade (display:none não serve).
            quadro.style.cssText = 'position:absolute;left:-10000px;top:0;width:800px;height:600px;border:0;';

            var cronometro = setTimeout(function () {
                quadro.remove();
                reject(new Error('O navegador demorou demais para mostrar o seu HTML.'));
            }, LIMITE_MS);

            quadro.onload = function () {
                clearTimeout(cronometro);
                resolve(quadro);
            };

            quadro.srcdoc = montarDocumento(codigo);
            document.body.appendChild(quadro);
        });
    }

    /**
     * Mesmo contrato do worker de JavaScript: recebe o código e os casos
     * ({id, entrada}) e devolve [{caso_id, saida, erro}].
     */
    function executar(codigo, casos) {
        return renderizar(codigo).then(function (quadro) {
            var doc = quadro.contentDocument;

            var resultados = (casos || []).map(function (caso) {
                try {
                    return { caso_id: caso.id, saida: avaliar(doc, caso.entrada), erro: null };
                } catch (e) {
                    return { caso_id: caso.id, saida: null, erro: e.message || 'Erro na verificação.' };
                }
            });

            quadro.remove();
            return resultados;
        });
    }

    /** Mostra o resultado visual do código num iframe de prévia já existente. */
    function mostrarPrevia(quadro, codigo) {
        if (!quadro) { return; }
        quadro.setAttribute('sandbox', 'allow-same-origin');
        quadro.srcdoc = montarDocumento(codigo);
    }

    global.DevFoxHtml = {
        executar: executar,
        mostrarPrevia: mostrarPrevia,
        avaliar: avaliar,
        interpretar: interpretar,
        montarDocumento: montarDocumento
    };
})(window);
