/**
 * assets/js/executor.worker.js
 * -----------------------------------------------------------------------------
 * Caixa de execução do código do aluno.
 *
 * Roda dentro de um Web Worker, ou seja: numa thread separada, sem acesso ao
 * DOM, aos cookies nem à sessão do usuário. É isso que permite executar código
 * de terceiros com segurança e, principalmente, interromper um laço infinito —
 * a thread principal simplesmente encerra o worker, o que seria impossível se
 * o código rodasse na própria página (ela travaria junto).
 *
 * ⚠ Este worker NÃO decide se a resposta está certa. Ele só executa e devolve
 *   o que o código produziu. Quem compara com o gabarito é o servidor, em
 *   api/processar_desafio.php. O motivo é simples: tudo que roda no navegador
 *   pode ser adulterado pelo aluno, então a decisão não pode morar aqui.
 */

/**
 * Converte o valor produzido pelo código em texto, para comparar com o
 * gabarito guardado no banco (que também é texto).
 */
function formatarValor(valor) {
    if (valor === undefined) {
        return 'undefined';
    }
    if (valor === null) {
        return 'null';
    }
    if (typeof valor === 'string') {
        return valor;                       // sem aspas: o gabarito é DevFox, não "DevFox"
    }
    if (typeof valor === 'number' || typeof valor === 'boolean') {
        return String(valor);
    }
    try {
        return JSON.stringify(valor);       // arrays e objetos
    } catch (e) {
        return String(valor);
    }
}

/**
 * Executa o código do aluno uma vez para cada caso de teste.
 *
 * Cada caso roda isolado dos outros: uma variável que o aluno tenha deixado
 * suja num caso não contamina o seguinte.
 *
 * Dois formatos de caso:
 *   - com `entrada`  → a entrada é avaliada como expressão (ex: "somar(10, 5)")
 *                      e o valor devolvido é o resultado.
 *   - sem `entrada`  → o código roda direto e o resultado é o que ele imprimiu
 *                      com console.log.
 */
function executarCaso(codigo, entrada) {
    var impressos = [];

    // console.log do aluno é capturado, não vai para o console do navegador.
    var consoleFalso = {
        log: function () {
            impressos.push(Array.prototype.map.call(arguments, formatarValor).join(' '));
        }
    };
    consoleFalso.info = consoleFalso.log;
    consoleFalso.warn = consoleFalso.log;
    consoleFalso.error = consoleFalso.log;
    consoleFalso.debug = consoleFalso.log;

    var temEntrada = typeof entrada === 'string' && entrada.trim() !== '';

    // O código do aluno e a expressão do caso são montados numa função só, para
    // que a expressão enxergue as funções que ele declarou.
    var corpo = temEntrada
        ? codigo + '\n;return (' + entrada + ');'
        : codigo + '\n;return undefined;';

    var fabrica = new Function('console', corpo);
    var retorno = fabrica(consoleFalso);

    if (temEntrada) {
        return formatarValor(retorno);
    }

    return impressos.join('\n');
}

/* -----------------------------------------------------------------------------
 * Em que linha o erro aconteceu
 *
 * O objetivo é o mesmo do editor do VS Code: apontar a linha, em vez de só
 * dizer "deu erro". São dois casos bem diferentes:
 *
 *   - erro em tempo de execução (ReferenceError, TypeError…): o navegador põe a
 *     posição na pilha (`e.stack`), mas numerada a partir da função que o
 *     `new Function` montou, que tem um cabeçalho de tamanho variável conforme
 *     o navegador. Por isso o deslocamento é MEDIDO uma vez, com um erro de
 *     posição conhecida, em vez de chutado;
 *   - erro de SINTAXE: nem Chrome nem Edge põem a posição na pilha. Aí o jeito
 *     é recompilar o código linha a linha e ver em qual delas ele passa a não
 *     compilar.
 *
 * Quando nada disso dá certo, `linha` volta null e a tela mostra só a
 * mensagem — é melhor não apontar nada do que apontar a linha errada.
 * -------------------------------------------------------------------------- */

/** Primeira posição que aparecer na pilha, ou null. */
function linhaDaPilha(pilha) {
    if (typeof pilha !== 'string') {
        return null;
    }
    var achado = pilha.match(/<anonymous>:(\d+):\d+/)        // Chrome, Edge, Opera
              || pilha.match(/(?:Function|eval):(\d+):\d+/); // Firefox, Safari
    return achado ? parseInt(achado[1], 10) : null;
}

/**
 * Quantas linhas o navegador acrescenta antes do código.
 * Medido com um erro na linha 1: o que sobrar é o cabeçalho.
 */
var DESLOCAMENTO = (function () {
    try {
        new Function('throw new Error("sonda");')();
    } catch (e) {
        var linha = linhaDaPilha(e && e.stack);
        return linha === null ? null : linha - 1;
    }
    return null;
})();

/**
 * Linha do erro de sintaxe: a primeira em que o trecho de código deixa de
 * compilar. "Fim inesperado" não conta — significa só que o trecho ainda está
 * incompleto (um `if` ou uma função que continua nas linhas de baixo).
 */
function linhaDeSintaxe(codigo) {
    var linhas = codigo.split('\n');

    for (var i = 1; i <= linhas.length; i++) {
        try {
            new Function(linhas.slice(0, i).join('\n'));
        } catch (e) {
            var mensagem = String((e && e.message) || '');
            if (/end of (input|script)|unexpected eof|got end of|expected expression/i.test(mensagem)) {
                continue;
            }
            return i;
        }
    }
    return null;
}

self.onmessage = function (evento) {
    var dados  = evento.data || {};
    var codigo = String(dados.codigo || '');
    var casos  = Array.isArray(dados.casos) ? dados.casos : [];
    var total  = codigo.split('\n').length;

    // A busca pela linha do erro de sintaxe é feita no máximo uma vez por
    // execução, mesmo que todos os casos falhem pelo mesmo motivo.
    var sintaxeProcurada = false;
    var linhaSintaxe     = null;

    function linhaDoErro(e) {
        if (e instanceof SyntaxError) {
            if (!sintaxeProcurada) {
                sintaxeProcurada = true;
                linhaSintaxe = linhaDeSintaxe(codigo);
            }
            return linhaSintaxe;
        }

        if (DESLOCAMENTO === null) {
            return null;
        }

        var linha = linhaDaPilha(e && e.stack);
        if (linha === null) {
            return null;
        }

        linha -= DESLOCAMENTO;
        // Fora do código do aluno (o `return` que o worker acrescenta, ou uma
        // função interna do navegador): melhor não apontar nada.
        return (linha >= 1 && linha <= total) ? linha : null;
    }

    var resultados = casos.map(function (caso) {
        try {
            return {
                caso_id: caso.id,
                saida: executarCaso(codigo, caso.entrada),
                erro: null,
                linha: null
            };
        } catch (e) {
            // Erro de sintaxe ou exceção em tempo de execução: o caso falha,
            // mas os outros continuam sendo avaliados.
            return {
                caso_id: caso.id,
                saida: null,
                erro: (e && e.message) ? String(e.message) : 'Erro ao executar o código.',
                linha: linhaDoErro(e)
            };
        }
    });

    self.postMessage({ resultados: resultados });
};
