/**
 * tools/validar_html.js
 * -----------------------------------------------------------------------------
 * Validador dos exercícios de HTML e CSS. Faz o mesmo que validar_conteudo.js
 * faz com JavaScript, mas num navegador DE VERDADE (Chromium, via Playwright):
 * os casos conferem estilo CALCULADO ("rgb(255, 0, 0)", "24px"), e isso só um
 * navegador calcula do jeito que o aluno vai ver.
 *
 * Usa o MESMO arquivo que a tela do aluno usa (app/assets/js/executor-html.js,
 * ou assets/js/... na raiz), então validar aqui é validar o que roda lá.
 *
 * Confere, para cada exercício:
 *   - a solução de referência passa em TODOS os casos;
 *   - o código inicial NÃO passa em todos (senão o exercício não exercita).
 *
 * Uso:
 *   node tools/validar_html.js                 (lê tools/dados.json, só linguagem html)
 *   node tools/validar_html.js arquivo.json    (lista de exercícios)
 */

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const candidatos = [
    path.join(__dirname, '..', 'assets', 'js', 'executor-html.js'),
    path.join(__dirname, '..', 'app', 'assets', 'js', 'executor-html.js'),
];
const executor = candidatos.find(fs.existsSync);
if (!executor) { console.error('executor-html.js nao encontrado'); process.exit(2); }

let exercicios;
if (process.argv[2]) {
    exercicios = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
} else {
    const dados = JSON.parse(fs.readFileSync(path.join(__dirname, 'dados.json'), 'utf8'));
    exercicios = dados.codigo.filter(a => a.linguagem === 'html');
}

const normalizar = v => String(v === null || v === undefined ? '' : v).trim().replace(/\s+/g, ' ').toLowerCase();

(async () => {
    const navegador = await chromium.launch();
    const pg = await navegador.newPage();
    await pg.setContent('<!DOCTYPE html><html><body></body></html>');
    await pg.addScriptTag({ content: fs.readFileSync(executor, 'utf8') });

    let falhas = 0;
    const erro = m => { falhas++; console.log('  ✘ ' + m); };
    console.log('\nHTML/CSS: SOLUCAO x CASOS e CODIGO INICIAL');

    for (const a of exercicios) {
        const casos = a.casos.map((c, i) => ({ id: i + 1, entrada: c.entrada }));
        const rotulo = '#' + (a.id || '?') + ' "' + a.titulo + '"';

        if (casos.some(c => !c.entrada)) { erro(rotulo + ': caso sem verificacao (entrada vazia)'); continue; }

        const res = await pg.evaluate(([cod, cs]) => window.DevFoxHtml.executar(cod, cs), [a.solucao_exemplo, casos]);
        res.forEach((r, i) => {
            const c = a.casos[i];
            if (r.erro) { erro(rotulo + ' caso ' + (i + 1) + ' [' + c.entrada + ']: ' + r.erro); }
            else if (normalizar(r.saida) !== normalizar(c.saida_esperada)) {
                erro(rotulo + ' caso ' + (i + 1) + ' [' + c.entrada + ']: esperado ' +
                     JSON.stringify(c.saida_esperada) + ', obtido ' + JSON.stringify(r.saida));
            }
        });

        if (a.codigo_inicial && a.codigo_inicial.trim() !== '') {
            const ini = await pg.evaluate(([cod, cs]) => window.DevFoxHtml.executar(cod, cs), [a.codigo_inicial, casos]);
            const todos = ini.every((r, i) => !r.erro && normalizar(r.saida) === normalizar(a.casos[i].saida_esperada));
            if (todos) { erro(rotulo + ': o codigo inicial JA passa em todos os casos'); }
        }
    }

    await navegador.close();
    console.log(falhas === 0 ? '>>> HTML/CSS OK: ' + exercicios.length + ' exercicios' : '>>> ' + falhas + ' PROBLEMA(S) EM HTML/CSS');
    process.exit(falhas === 0 ? 0 : 1);
})();
