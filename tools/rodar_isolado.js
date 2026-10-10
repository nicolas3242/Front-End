/**
 * tools/rodar_isolado.js
 *
 * Executa um código contra uma entrada e imprime o resultado em JSON.
 * Roda como processo separado para que o validador possa matá-lo se o código
 * travar — é o equivalente, em linha de comando, ao worker.terminate() que o
 * navegador usa em laços infinitos.
 *
 * Uso: node rodar_isolado.js '<json com {codigo, entrada}>'
 */

function formatarValor(valor) {
    if (valor === undefined) return 'undefined';
    if (valor === null) return 'null';
    if (typeof valor === 'string') return valor;
    if (typeof valor === 'number' || typeof valor === 'boolean') return String(valor);
    try { return JSON.stringify(valor); } catch (e) { return String(valor); }
}

const entradaJson = JSON.parse(process.argv[2]);
const impressos = [];
const consoleFalso = {
    log: function () {
        impressos.push(Array.prototype.map.call(arguments, formatarValor).join(' '));
    }
};
consoleFalso.info = consoleFalso.warn = consoleFalso.error = consoleFalso.debug = consoleFalso.log;

try {
    const entrada = entradaJson.entrada;
    const temEntrada = typeof entrada === 'string' && entrada.trim() !== '';
    const corpo = temEntrada
        ? entradaJson.codigo + '\n;return (' + entrada + ');'
        : entradaJson.codigo + '\n;return undefined;';

    const retorno = new Function('console', corpo)(consoleFalso);
    process.stdout.write(JSON.stringify({
        saida: temEntrada ? formatarValor(retorno) : impressos.join('\n'),
        erro: null
    }));
} catch (e) {
    process.stdout.write(JSON.stringify({ saida: null, erro: String(e && e.message) }));
}
