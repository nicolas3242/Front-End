/**
 * testes/teste-admin-acesso.js
 * -----------------------------------------------------------------------------
 * Três coisas que entraram juntas e se cruzam:
 *
 *   1. ADMIN NAVEGANDO COMO ALUNO vê tudo aberto — trilha com pré-requisito,
 *      módulo fora de ordem, trilha em rascunho, prova sem espera — e ganha os
 *      atalhos de edição. O ALUNO continua barrado em todos esses pontos: é o
 *      que estes testes protegem, porque "liberar para o admin" é exatamente o
 *      tipo de mudança que abre buraco para todo mundo se for mal feita.
 *   2. LOJA: o admin cria moldura pela tela, escolhendo uma aparência do
 *      catálogo fechado. Aparência inventada no POST é recusada.
 *   3. ORDEM SORTEADA das atividades: sorteada entre alunos, estável para o
 *      mesmo aluno, e igual em todas as telas (senão "Lição 3 de 16" apontaria
 *      uma atividade e o botão "Próxima" levaria a outra).
 */

const { chromium } = require('playwright');
const { execSync } = require('child_process');
const crypto = require('crypto');
const { sair } = require('./ajudantes_teste');

const BASE  = process.env.DEVFOX_URL   || 'http://127.0.0.1:8080';
const MYSQL = process.env.DEVFOX_MYSQL || 'mysql -u root devfox';

let falhas = 0;
function conferir(nome, condicao, detalhe) {
    if (condicao) {
        console.log('  ok    ' + nome);
    } else {
        falhas++;
        console.log('  FALHA ' + nome + (detalhe ? ' → ' + detalhe : ''));
    }
}

const consultar = (sql) =>
    execSync(`${MYSQL} --default-character-set=utf8mb4 -N -B -e "${sql}"`).toString().trim();

/** Mesma chave de ordenação do PHP (chaveDeOrdem em includes/auth.php). */
const chaveDeOrdem = (alunoId, itemId) =>
    crypto.createHash('md5').update(`${alunoId}:${itemId}`).digest('hex');

async function entrar(pg, email, senha) {
    await sair(pg, BASE);            // "Sair" é POST: abrir logout.php pela URL não desloga
    await pg.goto(BASE + '/login.php');
    await pg.fill('#email', email);
    await pg.fill('#senha', senha);
    await pg.click('button[type=submit]');
    await pg.waitForLoadState();
}

/** Id da atividade que a tela de desafio está mostrando. */
const atividadeNaTela = (pg) => pg.getAttribute('input[name=atividade_id]', 'value');

(async () => {
    const nav = await chromium.launch();
    const pg  = await (await nav.newContext()).newPage();
    const errosJs = [];
    pg.on('pageerror', e => errosJs.push(e.message));

    // Dados de apoio, direto do banco (sem depender de id fixo no teste).
    const alunoId    = parseInt(consultar("SELECT id FROM usuarios WHERE email='aluno@devfox.local'"), 10);
    const trilhaBloq = parseInt(consultar(
        "SELECT r.trilha_id FROM trilha_requisitos r JOIN trilhas t ON t.id=r.trilha_id WHERE t.publicada=1 LIMIT 1"), 10);
    const moduloTardio = parseInt(consultar(
        `SELECT m.id FROM modulos m WHERE m.tipo='aula' AND m.trilha_id=(SELECT id FROM trilhas WHERE publicada=1 ORDER BY ordem LIMIT 1)
         ORDER BY m.ordem DESC LIMIT 1`), 10);
    const moduloInicial = parseInt(consultar(
        `SELECT m.id FROM modulos m WHERE m.tipo='aula' AND m.trilha_id=(SELECT id FROM trilhas WHERE publicada=1 ORDER BY ordem LIMIT 1)
         ORDER BY m.ordem LIMIT 1`), 10);

    // Trilha em rascunho só para este teste: o admin tem que vê-la, o aluno não.
    execSync(`${MYSQL} -e "INSERT INTO trilhas (titulo, descricao, nivel_dificuldade, icone, ordem, publicada)
              VALUES ('Trilha Rascunho de Teste', 'Só o admin deve ver.', 'iniciante', 'codigo', 99, 0)"`);

    console.log('\n— O aluno continua barrado —');

    await entrar(pg, 'aluno@devfox.local', 'aluno123');

    await pg.goto(BASE + '/desafio.php?modulo=' + moduloTardio);
    conferir('módulo fora de ordem não abre para o aluno',
             (await pg.content()).includes('Módulo ainda bloqueado'));

    await pg.goto(BASE + '/trilhas.php');
    const htmlAluno = await pg.content();
    // O cartão da trilha bloqueada leva a classe no <article>; "Em breve"
    // (trilha sem módulos) usa outra classe e não conta aqui.
    const cartaoBloqueado = /class="linha-trilha\s+trilha-bloqueada/;
    conferir('trilha com pré-requisito aparece bloqueada para o aluno',
             cartaoBloqueado.test(htmlAluno));
    conferir('aluno não vê trilha em rascunho', !htmlAluno.includes('>rascunho<'));

    await pg.goto(BASE + '/desafio.php?modulo=' + moduloInicial);
    conferir('aluno não vê a barra de edição do admin',
             (await pg.locator('.barra-admin').count()) === 0);

    console.log('\n— Ordem sorteada, e estável —');

    const idsDoModulo = consultar(
        `SELECT id FROM atividades WHERE modulo_id=${moduloInicial} ORDER BY ordem, id`
    ).split('\n').map(Number);

    const esperada = [...idsDoModulo].sort((a, b) =>
        chaveDeOrdem(alunoId, a).localeCompare(chaveDeOrdem(alunoId, b)));

    await pg.goto(BASE + '/desafio.php?modulo=' + moduloInicial);
    const primeira = Number(await atividadeNaTela(pg));
    conferir('a 1ª atividade é a 1ª da ordem sorteada deste aluno',
             primeira === esperada[0], `tela: ${primeira}, esperado: ${esperada[0]}`);

    await pg.reload();
    conferir('recarregar não muda a atividade (ordem estável)',
             Number(await atividadeNaTela(pg)) === primeira);

    // A contagem "Lição N de M" tem que concordar com a mesma ordem.
    const textoLicao = await pg.textContent('.faixa-progresso');
    const posicaoTela = parseInt((textoLicao.match(/Lição\s+(\d+)/) || [])[1], 10);
    conferir('"Lição N" bate com a ordem sorteada',
             posicaoTela === esperada.indexOf(primeira) + 1,
             `tela: ${posicaoTela}, esperado: ${esperada.indexOf(primeira) + 1}`);

    // A ordem é diferente conforme o aluno: comparada com a do admin, tem que
    // haver pelo menos uma troca (mesmo módulo, chave diferente).
    const adminId = parseInt(consultar("SELECT id FROM usuarios WHERE email='admin@devfox.local'"), 10);
    const ordemAdmin = [...idsDoModulo].sort((a, b) =>
        chaveDeOrdem(adminId, a).localeCompare(chaveDeOrdem(adminId, b)));
    conferir('a ordem muda de pessoa para pessoa',
             JSON.stringify(ordemAdmin) !== JSON.stringify(esperada),
             'as duas deram a mesma ordem (pode acontecer por acaso com poucas atividades)');

    console.log('\n— O admin vê tudo aberto —');

    await entrar(pg, 'admin@devfox.local', 'devfox123');

    await pg.goto(BASE + '/desafio.php?modulo=' + moduloTardio);
    conferir('admin abre módulo fora de ordem',
             !(await pg.content()).includes('Módulo ainda bloqueado'));
    conferir('admin vê a barra com "Editar atividade"',
             (await pg.locator('.barra-admin a[href^="admin_atividade.php"]').count()) === 1);

    await pg.goto(BASE + '/trilhas.php');
    const htmlAdmin = await pg.content();
    conferir('nenhuma trilha fica bloqueada para o admin', !cartaoBloqueado.test(htmlAdmin));

    const moduloDaBloqueada = parseInt(consultar(
        `SELECT id FROM modulos WHERE trilha_id=${trilhaBloq} AND tipo='aula' ORDER BY ordem LIMIT 1`), 10);
    await pg.goto(BASE + '/desafio.php?modulo=' + moduloDaBloqueada);
    conferir('trilha com pré-requisito abre para o admin',
             !(await pg.content()).includes('Módulo ainda bloqueado'));
    conferir('admin vê a trilha em rascunho, marcada como tal',
             htmlAdmin.includes('Trilha Rascunho de Teste') && htmlAdmin.includes('>rascunho<'));

    console.log('\n— Conquistas: prévia do admin —');

    await pg.goto(BASE + '/conquistas.php');
    const htmlConqAdmin = await pg.content();
    conferir('nenhuma conquista aparece bloqueada para o admin',
             !htmlConqAdmin.includes('conquista-bloqueada'));
    conferir('as conquistas em prévia são marcadas como tal',
             htmlConqAdmin.includes('prévia de admin'));
    // O número continua sendo o real: prévia não é conquista ganha, senão o
    // Perfil, o ranking e os relatórios passariam a contar o que não houve.
    conferir('a contagem continua real (0 de N)',
             /0 de \d+/.test(await pg.textContent('.painel-inicio-cabecalho')),
             await pg.textContent('.painel-inicio-cabecalho'));
    conferir('nada foi gravado no banco para o admin',
             consultar(`SELECT COUNT(*) FROM conquistas_usuarios WHERE usuario_id=${adminId}`) === '0');

    await entrar(pg, 'aluno@devfox.local', 'aluno123');
    await pg.goto(BASE + '/conquistas.php');
    conferir('para o aluno as conquistas continuam bloqueadas',
             (await pg.content()).includes('conquista-bloqueada'));
    await entrar(pg, 'admin@devfox.local', 'devfox123');

    console.log('\n— Painel inicial do admin —');

    await pg.goto(BASE + '/administracao.php');
    const painel = await pg.content();
    for (const bloco of ['Evolução dos alunos', 'Distribuição por nível', 'Ações rápidas',
                         'Últimas atividades', 'Desafios em destaque', 'Ranking geral']) {
        conferir('o painel traz o bloco "' + bloco + '"', painel.includes(bloco));
    }
    conferir('o gráfico é desenhado em SVG', (await pg.locator('svg.grafico polyline').count()) === 2);
    conferir('a rosca é desenhada', (await pg.locator('svg.rosca circle').count()) >= 1);
    conferir('o gráfico tem a tabela equivalente (acessibilidade)',
             (await pg.locator('.tabela-do-grafico table').count()) === 1);
    conferir('não há atalho para criar usuário', !painel.includes('Adicionar usuário'));
    // O número do cartão tem que bater com o banco, não ser enfeite.
    const alunosNoBanco = consultar("SELECT COUNT(*) FROM usuarios WHERE tipo='aluno'");
    conferir('o cartão de alunos mostra o número real do banco',
             (await pg.textContent('.grade-kpi')).includes(alunosNoBanco),
             'banco: ' + alunosNoBanco);

    // Busca única do painel.
    const nomeAluno = consultar("SELECT nome FROM usuarios WHERE email='aluno@devfox.local'");
    await pg.goto(BASE + '/admin_busca.php?q=' + encodeURIComponent(nomeAluno.split(' ')[0]));
    conferir('a busca encontra o aluno', (await pg.textContent('body')).includes(nomeAluno));
    // "_" é curinga no LIKE. Se o escape falhar, "Guard_ndo" acha "Guardando";
    // com o escape certo, ele é texto comum e não acha nada.
    const tituloReal = consultar("SELECT titulo FROM atividades WHERE titulo LIKE 'Guardando%' LIMIT 1");
    const comCuringa = tituloReal.replace(/^(.{5})./, '$1_');
    await pg.goto(BASE + '/admin_busca.php?q=' + encodeURIComponent(comCuringa));
    conferir('curinga digitado na busca não vira curinga de SQL',
             (await pg.textContent('body')).includes('Nada encontrado'),
             'buscou: ' + comCuringa);

    await entrar(pg, 'aluno@devfox.local', 'aluno123');
    await pg.goto(BASE + '/admin_busca.php?q=teste');
    conferir('aluno não abre a busca do admin', !pg.url().includes('admin_busca'));
    await entrar(pg, 'admin@devfox.local', 'devfox123');

    console.log('\n— Loja: moldura criada pela tela —');

    await pg.goto(BASE + '/admin_loja.php');
    const livres = await pg.locator('#estilo option').count();
    conferir('o formulário oferece aparências de moldura prontas', livres > 0, 'nenhuma livre');

    const estilo = await pg.locator('#estilo option').first().getAttribute('value');
    await pg.selectOption('#categoria', 'moldura');
    await pg.selectOption('#estilo', estilo);
    await pg.fill('#nome', 'Moldura de Teste');
    await pg.fill('#descricao', 'Criada pelo teste automatizado.');
    await pg.fill('#preco', '250');
    await pg.click('#formItem button[type=submit]');
    await pg.waitForLoadState();

    conferir('a moldura é criada e volta com aviso',
             (await pg.textContent('body')).includes('Moldura criada'));
    conferir('a moldura nasce na categoria certa',
             consultar(`SELECT categoria FROM itens_loja WHERE slug='${estilo}'`) === 'moldura');
    conferir('a ação fica no registro do admin',
             consultar("SELECT COUNT(*) FROM admin_registro WHERE acao='loja.criar'") !== '0');

    // Aparência inventada (POST forjado) é recusada pelo servidor.
    const recusa = await pg.evaluate(async (base) => {
        const csrf = document.querySelector('meta[name=csrf]').content;
        const corpo = new URLSearchParams({ acao: 'salvar', id: '0', categoria: 'moldura',
            estilo: 'nao-existe-no-css', nome: 'Invasora', descricao: '', preco: '10', csrf });
        const r = await fetch(base + '/admin_loja.php', { method: 'POST', body: corpo });
        return r.text();
    }, BASE);
    conferir('aparência fora do catálogo é recusada',
             recusa.includes('Escolha uma aparência') || consultar(
                 "SELECT COUNT(*) FROM itens_loja WHERE slug='nao-existe-no-css'") === '0');

    // O aluno vê a moldura nova à venda.
    await entrar(pg, 'aluno@devfox.local', 'aluno123');
    await pg.goto(BASE + '/loja.php');
    conferir('a moldura nova aparece na Loja do aluno',
             (await pg.textContent('body')).includes('Moldura de Teste'));

    conferir('nenhum erro de JavaScript nas páginas visitadas', errosJs.length === 0, errosJs.join(' | '));

    await nav.close();
    console.log(falhas === 0 ? '\n>>> TODOS OS TESTES PASSARAM' : `\n>>> ${falhas} FALHA(S)`);
    process.exit(falhas === 0 ? 0 : 1);
})();
