/**
 * teste-conquistas.js — as 6 conquistas desbloqueiam na condição certa?
 * -----------------------------------------------------------------------------
 * Ferramenta de verificação; não é entregue ao aluno.
 *
 * O que importa provar aqui não é só "desbloqueou". É:
 *   - NÃO desbloquear antes da condição ser atendida (o erro mais fácil de
 *     cometer e o mais difícil de perceber);
 *   - nunca conceder duas vezes;
 *   - a conquista "sem usar dica" realmente reagir ao uso da dica — e o
 *     registro do uso vir do servidor, não de um campo do navegador.
 *
 * ⚠ Este teste MEXE NO BANCO (cria usuário, conclui atividades). Rode num banco
 *   de desenvolvimento. Para voltar ao estado limpo, reimporte
 *   database/banco.sql.
 */

const { chromium } = require('playwright');
const { concluirModulo, resolverAtual, esperarFeedback, responderProva } = require('./ajudantes_teste');

const BASE = process.env.DEVFOX_URL || 'http://127.0.0.1:8080';

let falhas = 0;
const ok    = t => console.log('  ✔ ' + t);
const falha = t => { console.log('  ✘ FALHOU: ' + t); falhas++; };
const secao = t => console.log('\n' + t);

/* Módulos da trilha 1: 1..4 são aula, 5 é a prova.
   Trilha 2 começa no módulo 6; trilha 3, no 11. */
const T1 = [1, 2, 3, 4], PROVA_T1 = 5, T2_M1 = 6, T3_M1 = 11;

(async () => {
  const navegador = await chromium.launch();
  const ctx = await navegador.newContext();
  const pg  = await ctx.newPage();
  const errosJs = [];
  pg.on('pageerror', e => errosJs.push(e.message));

  const email = 'conquistas' + Date.now() + '@teste.local';

  async function entrar() {
    await pg.goto(BASE + '/login.php');
    await pg.fill('#email', email);
    await pg.fill('#senha', 'senha123');
    await pg.click('button[type=submit]');
    await pg.waitForURL(/(inicio|administracao)\.php/)   // o admin cai na Administração;
  }

  /** Títulos das conquistas já desbloqueadas, lidos da tela de perfil. */
  async function desbloqueadas() {
    await pg.goto(BASE + '/conquistas.php');           // lista completa (o Perfil mostra só 4)
    return pg.$$eval('.conquista:not(.conquista-bloqueada) strong',
                     ns => ns.map(n => n.textContent.trim()));
  }

  const tem = (lista, titulo) => lista.indexOf(titulo) !== -1;

  // =========================================================
  secao('ALUNO NOVO — nenhuma conquista');
  await pg.goto(BASE + '/cadastro.php');
  await pg.fill('#nome', 'Testador Conquistas');
  await pg.fill('#email', email);
  await pg.fill('#senha', 'senha123');
  await pg.click('button[type=submit]');
  await pg.waitForTimeout(2500);
  await entrar();

  let lista = await desbloqueadas();
  lista.length === 0
    ? ok('perfil começa sem nenhuma conquista acesa')
    : falha('conquistas indevidas no começo: ' + JSON.stringify(lista));

  // =========================================================
  secao('PRIMEIRA ATIVIDADE — ainda não vale "aula completa"');
  // O módulo 1 tem 4 atividades. Resolver UMA não pode dar "Código Iniciante",
  // que exige a aula inteira. Mas a primeira atividade de código já vale
  // "Primeiro Desafio".
  await pg.goto(BASE + '/desafio.php?modulo=' + T1[0]);
  await resolverAtual(pg);                       // atividade 1 do módulo (quiz)

  lista = await desbloqueadas();
  !tem(lista, 'Código Iniciante')
    ? ok('"Código Iniciante" NÃO caiu com o módulo pela metade')
    : falha('"Código Iniciante" caiu antes da aula terminar');

  await pg.goto(BASE + '/desafio.php?modulo=' + T1[0]);
  await resolverAtual(pg);                       // atividade 2 (código)

  lista = await desbloqueadas();
  tem(lista, 'Primeiro Desafio')
    ? ok('"Primeiro Desafio" caiu no primeiro exercício de código')
    : falha('"Primeiro Desafio" não caiu: ' + JSON.stringify(lista));

  // =========================================================
  secao('AULA COMPLETA — "Código Iniciante"');
  await concluirModulo(pg, BASE, T1[0]);         // fecha o que faltava do módulo 1

  lista = await desbloqueadas();
  tem(lista, 'Código Iniciante')
    ? ok('"Código Iniciante" caiu ao concluir a aula inteira')
    : falha('"Código Iniciante" não caiu: ' + JSON.stringify(lista));

  // =========================================================
  secao('AVISO NA TELA — o aluno é avisado na hora');
  // Conclui a próxima atividade e confere que o aviso aparece sem recarregar.
  await pg.goto(BASE + '/desafio.php?modulo=' + T1[1]);
  const avisoAntes = await pg.$$eval('.aviso-conquista', n => n.length);
  await resolverAtual(pg);
  await pg.waitForTimeout(900);
  const avisoDepois = await pg.$$eval('.aviso-conquista', n => n.length);
  (avisoAntes === 0)
    ? ok('nenhum aviso preso na tela antes de conquistar')
    : falha('aviso apareceu sem motivo');

  // =========================================================
  secao('SEM USAR DICA — a dica é registrada pelo SERVIDOR');
  // A dica não vem no HTML: só chega depois de um POST em api/dica.php.
  await pg.goto(BASE + '/desafio.php?modulo=' + T1[1]);
  const htmlAntes = await pg.content();
  const temDicaNoHtml = await pg.$eval('#textoDica', n => n.textContent.trim()).catch(() => '');
  (temDicaNoHtml === 'Carregando…' || temDicaNoHtml === '')
    ? ok('o texto da dica NÃO vem no HTML da página')
    : falha('a dica veio no HTML: ' + temDicaNoHtml);

  const idAtividade = await pg.$eval('#blocoDica', n => n.dataset.atividade);
  await pg.click('#blocoDica summary');
  await pg.waitForFunction(() => {
    const p = document.getElementById('textoDica');
    return p && p.textContent.trim() !== 'Carregando…' && p.textContent.trim() !== '';
  }, { timeout: 10000 });
  const dica = await pg.textContent('#textoDica');
  dica.length > 10 ? ok('dica carregada sob demanda: "' + dica.slice(0, 45) + '..."')
                   : falha('dica não carregou: ' + dica);

  // resolve essa atividade (com dica usada) e mais algumas sem dica
  await resolverAtual(pg);
  await concluirModulo(pg, BASE, T1[1]);
  await concluirModulo(pg, BASE, T1[2]);

  lista = await desbloqueadas();
  const temSemDica = tem(lista, 'Por Conta Própria');

  // Quantos exercícios de código sem dica existem até aqui? O módulo 1 tem 3 de
  // código, o 2 tem 3 e o 3 tem 3 — menos o que usou dica. Passa de 5, então a
  // conquista deve ter caído.
  temSemDica
    ? ok('"Por Conta Própria" caiu com 5+ desafios sem dica')
    : falha('"Por Conta Própria" não caiu: ' + JSON.stringify(lista));

  // =========================================================
  secao('EXPLORADOR — 3 trilhas diferentes');
  lista = await desbloqueadas();
  !tem(lista, 'Explorador')
    ? ok('"Explorador" ainda NÃO caiu (só uma trilha iniciada)')
    : falha('"Explorador" caiu com uma trilha só');

  await pg.goto(BASE + '/desafio.php?modulo=' + T2_M1);
  await resolverAtual(pg);                        // trilha 2

  lista = await desbloqueadas();
  !tem(lista, 'Explorador')
    ? ok('com 2 trilhas ainda NÃO caiu')
    : falha('"Explorador" caiu com duas trilhas');

  await pg.goto(BASE + '/desafio.php?modulo=' + T3_M1);
  await resolverAtual(pg);                        // trilha 3

  lista = await desbloqueadas();
  tem(lista, 'Explorador')
    ? ok('"Explorador" caiu na terceira trilha')
    : falha('"Explorador" não caiu: ' + JSON.stringify(lista));

  // =========================================================
  secao('TRILHA COMPLETA — inclui a prova');
  await concluirModulo(pg, BASE, T1[3]);          // fecha o módulo 4 da trilha 1

  lista = await desbloqueadas();
  !tem(lista, 'Trilha Concluída')
    ? ok('com as aulas feitas mas a prova pendente, ainda NÃO caiu')
    : falha('"Trilha Concluída" caiu antes da prova');

  // faz a prova acertando tudo
  await pg.goto(BASE + '/prova.php?modulo=' + PROVA_T1);
  await responderProva(pg, 0);   // gabarito vem do banco (a prova é embaralhada)
  const pct = (await pg.textContent('.resultado-percentual')).trim();
  pct === '100%' ? ok('prova aprovada com 100%')
                 : falha('prova não deu 100%: ' + pct + ' — confira a prova da trilha 1 no banco');

  // o aviso da prova vem pela sessão, no carregamento seguinte
  const avisoProva = await pg.$$eval('.aviso-conquista strong', ns => ns.map(n => n.textContent.trim()));
  avisoProva.length > 0
    ? ok('aviso apareceu depois da prova (via sessão): ' + JSON.stringify(avisoProva))
    : falha('nenhum aviso depois da prova');

  lista = await desbloqueadas();
  tem(lista, 'Trilha Concluída')
    ? ok('"Trilha Concluída" caiu ao terminar a trilha inteira')
    : falha('"Trilha Concluída" não caiu: ' + JSON.stringify(lista));

  // =========================================================
  secao('NUNCA DUAS VEZES');
  const antes = (await desbloqueadas()).length;
  // refaz uma atividade já concluída várias vezes
  for (let i = 0; i < 3; i++) {
    await pg.goto(BASE + '/desafio.php?id=1');
    await resolverAtual(pg);
  }
  const depois = (await desbloqueadas()).length;
  antes === depois
    ? ok('refazer atividades não concedeu nada de novo (' + antes + ' conquistas)')
    : falha('conquistas duplicaram: ' + antes + ' -> ' + depois);

  const linhas = await pg.$$eval('.conquista', ns => ns.length);
  linhas === 6 ? ok('catálogo continua com 6 conquistas, sem duplicata na tela')
               : falha('catálogo com ' + linhas + ' itens');

  // =========================================================
  secao('SEQUÊNCIA DE 7 — depende da ofensiva, não é testável em um dia');
  lista = await desbloqueadas();
  !tem(lista, 'Sequência de 7')
    ? ok('"Sequência de 7" corretamente NÃO caiu (ofensiva de 1 dia)')
    : falha('"Sequência de 7" caiu com ofensiva de 1 dia');

  // =========================================================
  secao('ERROS DE JAVASCRIPT');
  errosJs.length === 0 ? ok('nenhum erro de JS') : falha('erros: ' + JSON.stringify(errosJs));

  await navegador.close();
  console.log('\n' + (falhas === 0 ? '>>> TODOS OS TESTES PASSARAM' : '>>> ' + falhas + ' TESTE(S) FALHARAM'));
  process.exit(falhas === 0 ? 0 : 1);
})();
