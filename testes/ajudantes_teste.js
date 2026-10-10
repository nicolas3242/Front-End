/**
 * tools/ajudantes_teste.js
 * -----------------------------------------------------------------------------
 * Funções que as duas suítes compartilham para RESOLVER atividades sem
 * depender de id nem de uma cópia das soluções.
 *
 * Por que isso existe: a primeira versão das suítes referenciava atividades por
 * id fixo (desafio.php?id=3) e guardava as soluções numa constante. Quando o
 * conteúdo das trilhas cresceu, os ids mudaram e todos os testes quebraram de
 * uma vez — sem que nada do SISTEMA estivesse errado. Teste que quebra quando
 * o conteúdo muda não testa o sistema, testa o conteúdo.
 *
 * A estratégia agora:
 *   - exercício de código → lê do BANCO a solução de referência da atividade
 *     aberta (acompanha o conteúdo). Até 22/09 ela era copiada do "Mostrar
 *     exemplo" da página, mas esse bloco agora só aparece DEPOIS de concluir:
 *     antes disso, a solução pronta dava o XP sem esforço nenhum;
 *   - quiz → o gabarito NÃO está na página, e é assim que tem que ser. Então o
 *     teste tenta as alternativas até acertar, o que de quebra comprova que as
 *     erradas são recusadas.
 */

const { execSync } = require('child_process');

const APROVADO = 'passaram';
const MYSQL = process.env.DEVFOX_MYSQL || 'mysql -u root devfox';

/** Solução de referência da atividade, direto do banco (sem escapar quebras de linha). */
function solucaoDoBanco(atividadeId) {
    return execSync(MYSQL + ' --default-character-set=utf8mb4 -N -B --raw -e "SELECT solucao_exemplo FROM atividades WHERE id = ' +
                    parseInt(atividadeId, 10) + '"').toString().replace(/\n$/, '');
}

/** Espera o feedback parar de dizer "Executando..." e devolve o texto final. */
async function esperarFeedback(pg) {
    await pg.waitForFunction(() => {
        const f = document.getElementById('feedbackMsg');
        return f && f.textContent.trim() !== '' && !f.textContent.includes('Executando')
                 && !f.textContent.includes('Conferindo');
    }, { timeout: 15000 });
    return pg.textContent('#feedbackMsg');
}

/**
 * Escreve código na tela de desafio.
 *
 * Com o CodeMirror ativo o <textarea id="codigo"> fica escondido, e `fill()`
 * falha em elemento invisível. Então o texto vai pelo próprio editor, que a
 * página expõe em `window.editorDevFox`. Sem o editor (ou se ele não carregar),
 * cai no textarea — a mesma degradação que o aluno teria.
 */
async function escreverCodigo(pg, codigo) {
    await pg.evaluate((texto) => {
        if (window.editorDevFox) {
            window.editorDevFox.setValue(texto);
            window.editorDevFox.save();          // espelha no <textarea>
        } else {
            document.getElementById('codigo').value = texto;
        }
    }, codigo);
}

/** Escreve código e clica em Rodar, esperando o worker terminar. */
async function rodar(pg, codigo) {
    await escreverCodigo(pg, codigo);
    await pg.click('#btnRodar');
    await pg.waitForFunction(() => {
        const b = document.getElementById('btnRodar');
        return b && !b.disabled;
    }, { timeout: 15000 });
}

/**
 * Sai da conta pelo botão Sair (POST com token). Desde 22/09 o logout.php
 * não desloga por GET — por GET, qualquer <img src=logout.php> em outro site
 * deslogava o aluno. Se não houver ninguém logado, não faz nada.
 */
async function sair(pg, base) {
    await pg.goto(base + '/inicio.php');
    const botao = await pg.$('form.form-sair button');
    if (botao) {
        await Promise.all([pg.waitForNavigation(), botao.click()]);
    }
}

/**
 * Responde a prova aberta. As questões E as alternativas vêm embaralhadas a
 * cada abertura (desde 22/09), então um gabarito por posição não serve: a
 * alternativa certa de cada questão é buscada no banco pelo id.
 * `errar` = quantas questões (as primeiras da tela) errar de propósito.
 */
async function responderProva(pg, errar = 0) {
    const questoes = await pg.$$('.questao-prova');
    for (let q = 0; q < questoes.length; q++) {
        const valores = await questoes[q].$$eval('.alternativa input', ns => ns.map(n => n.value));
        const certa = execSync(MYSQL + ' -N -B -e "SELECT id FROM alternativas WHERE correta = 1 AND id IN (' +
                               valores.map(v => parseInt(v, 10)).join(',') + ')"').toString().trim();
        const escolha = q < errar ? valores.find(v => v !== certa) : certa;
        await pg.check(`.questao-prova >> nth=${q} >> .alternativa input[value="${escolha}"]`);
    }
    await pg.click('button[type=submit]');
    await pg.waitForLoadState();
}

/** Token CSRF da página aberta (o <meta name="csrf"> do cabeçalho). */
async function tokenDaPagina(pg) {
    return pg.getAttribute('meta[name="csrf"]', 'content');
}

/** Resolve a atividade que está aberta na tela, seja quiz ou exercício de código. */
async function resolverAtual(pg) {
    if (await pg.$('#codigo')) {
        const id = await pg.getAttribute('input[name=atividade_id]', 'value');
        const solucao = solucaoDoBanco(id).trim();
        if (!solucao) {
            throw new Error('a atividade aberta não tem solução de referência na página');
        }
        await rodar(pg, solucao);
        await pg.click('button[type=submit]');
        return esperarFeedback(pg);
    }

    const total = await pg.$$eval('.alternativa input[type=radio]', ns => ns.length);
    let fb = '';
    for (let i = 0; i < total; i++) {
        await pg.check('.alternativa input[type=radio] >> nth=' + i);
        await pg.click('button[type=submit]');
        fb = await esperarFeedback(pg);
        if (fb.includes(APROVADO)) return fb;
    }
    return fb;
}

/** A atividade aberta já foi concluída antes? */
async function jaConcluida(pg) {
    return (await pg.$('.selo-concluida')) !== null;
}

/**
 * Conclui todas as atividades pendentes de um módulo, na ordem em que o
 * próprio sistema as entrega (desafio.php?modulo=N abre sempre a primeira
 * pendente). Devolve quantas atividades resolveu.
 */
async function concluirModulo(pg, base, moduloId, limite = 20) {
    let feitas = 0;

    for (let n = 0; n < limite; n++) {
        await pg.goto(base + '/desafio.php?modulo=' + moduloId);

        if (!(await pg.$('#formDesafio'))) return feitas;   // módulo sem atividade
        if (await jaConcluida(pg)) return feitas;           // não há mais pendente

        const fb = await resolverAtual(pg);
        if (!fb.includes(APROVADO)) {
            throw new Error('não consegui resolver uma atividade do módulo ' +
                            moduloId + ': ' + fb);
        }
        feitas++;
    }

    throw new Error('módulo ' + moduloId + ' não terminou em ' + limite + ' atividades');
}

module.exports = { APROVADO, solucaoDoBanco, sair, tokenDaPagina, responderProva, esperarFeedback, escreverCodigo, rodar, resolverAtual,
                   jaConcluida, concluirModulo };
