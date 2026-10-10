/**
 * testes/teste-editor.js
 * -----------------------------------------------------------------------------
 * Editor de código da tela de desafio.
 *
 * Duas coisas são verificadas aqui:
 *
 *   1. as barras de rolagem não voltam a ser claras — nem a da página, nem a
 *      que o CodeMirror desenhava atravessando o editor;
 *   2. a linha do erro é apontada, como num editor de verdade: erro de
 *      execução e erro de sintaxe, a marca saindo quando o aluno digita de
 *      novo, e nada sendo apontado quando o código roda sem erro.
 *
 * O ponto 2 é o que mais pode envelhecer mal: a linha vem da pilha do
 * navegador, cujo formato não é padronizado. Por isso o executor MEDE o
 * deslocamento em vez de chutar — e por isso estes testes existem.
 */

const { chromium } = require('playwright');

const BASE = process.env.DEVFOX_URL || 'http://127.0.0.1:8080';
const ATIVIDADE = process.env.DEVFOX_ATIVIDADE || '2';   // "Guardando um nome" (JavaScript)

let falhas = 0;
function conferir(nome, condicao, detalhe) {
    if (condicao) {
        console.log('  ok   ' + nome);
    } else {
        falhas++;
        console.log('  FALHA ' + nome + (detalhe ? ' → ' + detalhe : ''));
    }
}

async function entrar(pg, email, senha) {
    await pg.goto(BASE + '/login.php');
    await pg.fill('#email', email);
    await pg.fill('#senha', senha);
    await pg.click('button[type=submit]');
    await pg.waitForLoadState();
}

/** Escreve no editor e clica em Rodar, esperando o worker terminar. */
async function rodar(pg, codigo) {
    await pg.evaluate((texto) => {
        window.editorDevFox.setValue(texto);
        window.editorDevFox.save();
    }, codigo);

    await pg.click('#btnRodar');
    await pg.waitForFunction(() => {
        const f = document.getElementById('feedbackMsg');
        return f && f.textContent.trim() !== '' && !f.textContent.includes('Rodando');
    }, { timeout: 15000 });
}

/** Número da linha marcada em vermelho (1 = primeira), ou null. */
async function linhaMarcada(pg) {
    return pg.evaluate(() => {
        const ed = window.editorDevFox;
        for (let i = 0; i < ed.lineCount(); i++) {
            const info = ed.lineInfo(i);
            if (info.bgClass && info.bgClass.indexOf('linha-com-erro') !== -1) {
                return i + 1;
            }
        }
        return null;
    });
}

(async () => {
    const nav = await chromium.launch();
    const pg  = await (await nav.newContext()).newPage();
    const errosJs = [];
    pg.on('pageerror', e => errosJs.push(e.message));

    await entrar(pg, 'aluno@devfox.local', 'aluno123');
    await pg.goto(BASE + '/desafio.php?id=' + ATIVIDADE);
    await pg.waitForSelector('.CodeMirror');

    console.log('\n— Barras de rolagem —');

    const barras = await pg.evaluate(() => ({
        // CodeMirror com scrollbarStyle:'null' não cria os elementos de barra.
        hscroll: document.querySelectorAll('.CodeMirror-hscrollbar').length,
        vscroll: document.querySelectorAll('.CodeMirror-vscrollbar').length,
        raiz: getComputedStyle(document.documentElement).scrollbarColor
    }));

    conferir('editor não desenha barra horizontal', barras.hscroll === 0, 'encontradas: ' + barras.hscroll);
    conferir('editor não desenha barra vertical', barras.vscroll === 0, 'encontradas: ' + barras.vscroll);
    conferir('página define cor própria de barra de rolagem',
             barras.raiz && barras.raiz !== 'auto', 'valor: ' + barras.raiz);

    console.log('\n— Linha com erro —');

    // Erro em tempo de execução na 3ª linha.
    await rodar(pg, 'let nome = "DevFox";\nconsole.log(nome);\nnaoExiste();');
    conferir('erro de execução aponta a linha certa', (await linhaMarcada(pg)) === 3,
             'marcou: ' + await linhaMarcada(pg));
    conferir('o painel mostra o número da linha',
             (await pg.textContent('#painelResultados')).includes('Linha 3'));
    conferir('a coluna da esquerda ganha a marca',
             (await pg.locator('.marca-erro').count()) === 1);

    // Erro de sintaxe na 2ª linha (o navegador não informa a posição; quem
    // descobre é a recompilação linha a linha do executor).
    await rodar(pg, 'let nome = "DevFox";\nlet erro = ;\nconsole.log(nome);');
    conferir('erro de sintaxe aponta a linha certa', (await linhaMarcada(pg)) === 2,
             'marcou: ' + await linhaMarcada(pg));

    // Digitar limpa a marca: ela vale para o código anterior.
    await pg.evaluate(() => { window.editorDevFox.setValue('let nome = "DevFox";'); });
    conferir('digitar limpa a marca', (await linhaMarcada(pg)) === null);

    // Código correto: nada marcado, e o painel não fala em linha.
    await rodar(pg, 'let nome = "DevFox";\nconsole.log(nome);');
    conferir('código sem erro não marca nada', (await linhaMarcada(pg)) === null);
    conferir('código sem erro não fala em linha',
             !(await pg.textContent('#painelResultados')).includes('Linha '));

    conferir('nenhum erro de JavaScript na página', errosJs.length === 0, errosJs.join(' | '));

    await nav.close();
    console.log(falhas === 0 ? '\nTodas as verificações passaram.' : '\n' + falhas + ' verificação(ões) falharam.');
    process.exit(falhas === 0 ? 0 : 1);
})();
