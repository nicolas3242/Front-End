/**
 * teste-admin.js — a Administração cadastra conteúdo que funciona de verdade?
 * -----------------------------------------------------------------------------
 * Ferramenta de verificação; não é entregue ao aluno.
 *
 * Não basta "salvou". O que precisa ser provado:
 *   - o que o admin cadastra APARECE para o aluno e funciona;
 *   - gabarito errado é RECUSADO antes de virar exercício quebrado;
 *   - exercício sem caso oculto é recusado (senão dá para chumbar a resposta);
 *   - aluno não entra, e POST sem token CSRF não passa;
 *   - conteúdo com progresso de aluno não é apagado por engano.
 *
 * ⚠ MEXE NO BANCO. Rode num banco de desenvolvimento.
 */

const { chromium } = require('playwright');
const { concluirModulo, resolverAtual, escreverCodigo, esperarFeedback, sair } = require('./ajudantes_teste');

const BASE = process.env.DEVFOX_URL || 'http://127.0.0.1:8080';

let falhas = 0;
const ok    = t => console.log('  ✔ ' + t);
const falha = t => { console.log('  ✘ FALHOU: ' + t); falhas++; };
const secao = t => console.log('\n' + t);

(async () => {
  const navegador = await chromium.launch();
  const ctx = await navegador.newContext();
  const pg  = await ctx.newPage();
  const errosJs = [];
  pg.on('pageerror', e => errosJs.push(e.message));

  async function entrar(email, senha) {
    await sair(pg, BASE);
    await pg.goto(BASE + '/login.php');
    await pg.fill('#email', email);
    await pg.fill('#senha', senha);
    await pg.click('button[type=submit]');
    await pg.waitForURL(/(inicio|administracao)\.php/)   // o admin cai na Administração;
  }

  const marca = Date.now();

  // =========================================================
  secao('ACESSO');
  await entrar('aluno@devfox.local', 'aluno123');
  for (const pagina of ['administracao.php', 'admin_trilha.php?id=1',
                        'admin_modulo.php?id=1', 'admin_atividade.php?modulo=1']) {
    await pg.goto(BASE + '/' + pagina);
    !pg.url().includes(pagina.split('?')[0])
      ? ok('aluno barrado em ' + pagina)
      : falha('aluno entrou em ' + pagina);
  }

  await entrar('admin@devfox.local', 'devfox123');
  await pg.goto(BASE + '/administracao.php');
  pg.url().includes('administracao.php') ? ok('admin entra na Administração')
                                         : falha('admin não entrou');

  // =========================================================
  secao('CSRF — POST sem token é recusado');
  const semToken = await pg.evaluate(async (base) => {
    const d = new FormData();
    d.append('acao', 'criar');
    d.append('titulo', 'Trilha forjada');
    const r = await fetch(base + '/administracao.php', { method: 'POST', body: d });
    return { status: r.status, texto: (await r.text()).slice(0, 80) };
  }, BASE);
  semToken.status === 403 ? ok('POST sem token CSRF recusado com 403')
                          : falha('POST sem token passou: ' + JSON.stringify(semToken));

  const tokenErrado = await pg.evaluate(async (base) => {
    const d = new FormData();
    d.append('csrf', 'token-inventado');
    d.append('acao', 'criar');
    d.append('titulo', 'Trilha forjada 2');
    const r = await fetch(base + '/administracao.php', { method: 'POST', body: d });
    return r.status;
  }, BASE);
  tokenErrado === 403 ? ok('POST com token inválido recusado')
                      : falha('token inventado passou: ' + tokenErrado);

  const trilhasForjadas = await pg.evaluate(async (base) => {
    const r = await fetch(base + '/administracao.php');
    return (await r.text()).includes('Trilha forjada');
  }, BASE);
  !trilhasForjadas ? ok('nenhuma trilha forjada foi criada')
                   : falha('a trilha forjada entrou no banco');

  // =========================================================
  secao('CRIAR TRILHA');
  await pg.goto(BASE + '/administracao.php');
  await pg.fill('#titulo', 'Trilha de Teste ' + marca);
  await pg.fill('#descricao', 'Criada pelo teste automatizado.');
  await pg.selectOption('#nivel_dificuldade', 'iniciante');
  // a tela tem vários submits (um por trilha, para publicar): mira no certo
  await pg.click('form:has(#titulo) button[type=submit]');
  await pg.waitForURL('**/admin_trilha.php**');
  ok('trilha criada, redirecionou para a tela dela');

  const urlTrilha = pg.url();
  const trilhaId = new URL(urlTrilha).searchParams.get('id');

  let corpo = await pg.textContent('body');
  corpo.includes('rascunho')
    ? ok('trilha nova nasce como rascunho (não aparece para o aluno)')
    : falha('trilha nova já nasceu publicada');

  // o aluno NÃO deve ver ainda
  await entrar('aluno@devfox.local', 'aluno123');
  await pg.goto(BASE + '/trilhas.php');
  corpo = await pg.textContent('body');
  !corpo.includes('Trilha de Teste ' + marca)
    ? ok('aluno não vê a trilha em rascunho')
    : falha('trilha em rascunho apareceu para o aluno');

  await entrar('admin@devfox.local', 'devfox123');

  // =========================================================
  secao('CRIAR MÓDULO');
  await pg.goto(urlTrilha);
  await pg.fill('#modulo_titulo', 'Módulo de Teste');
  await pg.selectOption('#modulo_tipo', 'aula');
  await pg.fill('#modulo_descricao', 'Um módulo criado pela tela.');
  await pg.click('#formModulo button[type=submit]');
  await pg.waitForURL('**/admin_modulo.php**');
  ok('módulo criado, redirecionou para a tela dele');

  const urlModulo = pg.url();
  const moduloId = new URL(urlModulo).searchParams.get('id');

  corpo = await pg.textContent('body');
  corpo.includes('Nenhuma atividade ainda')
    ? ok('módulo novo avisa que está vazio')
    : falha('módulo vazio não avisou');

  // escreve a explicação
  await pg.fill('#teoria_texto', 'Esta é a explicação do módulo de teste.');
  await pg.click('form:has(#teoria_texto) button[type=submit]');
  await pg.waitForLoadState();
  corpo = await pg.textContent('body');
  corpo.includes('Módulo atualizado') ? ok('explicação salva') : falha('explicação não salvou');

  // =========================================================
  secao('GABARITO ERRADO É RECUSADO');
  await pg.goto(BASE + '/admin_atividade.php?modulo=' + moduloId);
  await pg.fill('#titulo', 'Dobro de um número');
  await pg.fill('#enunciado', 'Escreva dobro(n) que devolva o dobro de n.');
  await pg.selectOption('#tipo', 'codigo_livre');
  await pg.fill('#solucao_exemplo', 'function dobro(n) { return n * 2; }');

  // saída esperada ERRADA de propósito
  const linhas = await pg.$$('.caso-admin');
  await linhas[0].$eval('input[name="caso_entrada[]"]', (n, v) => n.value = v, 'dobro(5)');
  await linhas[0].$eval('input[name="caso_saida[]"]',   (n, v) => n.value = v, '11');
  await linhas[1].$eval('input[name="caso_entrada[]"]', (n, v) => n.value = v, 'dobro(0)');
  await linhas[1].$eval('input[name="caso_saida[]"]',   (n, v) => n.value = v, '0');

  await pg.click('#btnConferir');
  await pg.waitForFunction(() => {
    const a = document.getElementById('resultadoConferencia');
    return a && a.textContent.trim() !== '' && !a.textContent.includes('Executando');
  }, { timeout: 15000 });

  let conferencia = await pg.textContent('#resultadoConferencia');
  conferencia.includes('NÃO passa')
    ? ok('gabarito errado detectado: "' + conferencia.slice(0, 70) + '..."')
    : falha('gabarito errado passou na conferência: ' + conferencia);

  // tentar salvar assim mesmo
  await pg.click('#formAtividade button[type=submit]');
  await pg.waitForTimeout(600);
  pg.url().includes('admin_atividade.php')
    ? ok('salvar foi bloqueado com o gabarito errado')
    : falha('salvou mesmo com gabarito errado');

  // =========================================================
  secao('GABARITO CERTO — a atividade é criada');
  await linhas[0].$eval('input[name="caso_saida[]"]', (n, v) => n.value = v, '10');
  await linhas[2].$eval('input[name="caso_entrada[]"]', (n, v) => n.value = v, 'dobro(-3)');
  await linhas[2].$eval('input[name="caso_saida[]"]',   (n, v) => n.value = v, '-6');
  await linhas[2].$eval('input[type="checkbox"]', n => n.checked = true);   // oculto

  await pg.click('#btnConferir');
  await pg.waitForFunction(() => {
    const a = document.getElementById('resultadoConferencia');
    return a && a.textContent.includes('conferido');
  }, { timeout: 15000 });

  conferencia = await pg.textContent('#resultadoConferencia');
  ok('gabarito certo aprovado: "' + conferencia.trim().slice(0, 60) + '..."');

  await pg.click('#formAtividade button[type=submit]');
  await pg.waitForURL('**/admin_modulo.php**');
  corpo = await pg.textContent('body');
  corpo.includes('Dobro de um número') ? ok('atividade salva e listada no módulo')
                                       : falha('atividade não apareceu na lista');

  // Guarda o id. `?modulo=N` abre sempre a PRÓXIMA pendente — depois que o aluno
  // resolve esta, aquela URL passaria a abrir o quiz, e a checagem de chumbo
  // logo abaixo cairia no vazio sem ninguém perceber.
  const idDobro = await pg.$$eval('.linha-admin', ns => {
    const alvo = ns.find(n => n.textContent.includes('Dobro de um número'));
    const link = alvo && alvo.querySelector('a[href*="admin_atividade.php?id="]');
    return link ? new URL(link.href).searchParams.get('id') : null;
  });
  idDobro ? ok('id da atividade capturado: #' + idDobro)
          : falha('não consegui capturar o id da atividade criada');
  corpo.includes('1 oculto') ? ok('módulo mostra que há 1 caso oculto')
                             : falha('contagem de ocultos errada');

  // =========================================================
  secao('SEM CASO OCULTO É RECUSADO');
  await pg.goto(BASE + '/admin_atividade.php?modulo=' + moduloId);
  await pg.fill('#titulo', 'Triplo sem oculto');
  await pg.fill('#enunciado', 'Escreva triplo(n).');
  await pg.selectOption('#tipo', 'codigo_livre');
  await pg.fill('#solucao_exemplo', 'function triplo(n) { return n * 3; }');

  const l2 = await pg.$$('.caso-admin');
  await l2[0].$eval('input[name="caso_entrada[]"]', (n, v) => n.value = v, 'triplo(2)');
  await l2[0].$eval('input[name="caso_saida[]"]',   (n, v) => n.value = v, '6');
  await l2[1].$eval('input[name="caso_entrada[]"]', (n, v) => n.value = v, 'triplo(4)');
  await l2[1].$eval('input[name="caso_saida[]"]',   (n, v) => n.value = v, '12');
  await l2[2].$eval('input[type="checkbox"]', n => n.checked = false);   // nenhum oculto

  await pg.click('#btnConferir');
  await pg.waitForFunction(() => {
    const a = document.getElementById('resultadoConferencia');
    return a && a.textContent.includes('conferido');
  }, { timeout: 15000 });
  await pg.click('#formAtividade button[type=submit]');
  await pg.waitForLoadState();

  corpo = await pg.textContent('body');
  corpo.includes('oculto')
    ? ok('exercício sem caso oculto recusado pelo servidor')
    : falha('exercício sem caso oculto foi aceito');

  // =========================================================
  secao('QUIZ PELA TELA');
  await pg.goto(BASE + '/admin_atividade.php?modulo=' + moduloId);
  await pg.fill('#titulo', 'Quiz de Teste');
  await pg.fill('#enunciado', 'Quanto é 2 + 2?');
  await pg.selectOption('#tipo', 'quiz');
  const alts = await pg.$$('.linha-alternativa');
  await alts[0].$eval('input[type="text"]', (n, v) => n.value = v, '3');
  await alts[1].$eval('input[type="text"]', (n, v) => n.value = v, '4');
  await alts[2].$eval('input[type="text"]', (n, v) => n.value = v, '5');
  await alts[1].$eval('input[type="radio"]', n => n.checked = true);
  await pg.click('#formAtividade button[type=submit]');
  await pg.waitForURL('**/admin_modulo.php**');
  corpo = await pg.textContent('body');
  corpo.includes('Quiz de Teste') ? ok('quiz criado pela tela') : falha('quiz não foi criado');

  // duas alternativas corretas -> recusado
  await pg.goto(BASE + '/admin_atividade.php?modulo=' + moduloId);
  await pg.fill('#titulo', 'Quiz sem resposta');
  await pg.fill('#enunciado', 'Sem marcar a correta.');
  await pg.selectOption('#tipo', 'quiz');
  const alts2 = await pg.$$('.linha-alternativa');
  await alts2[0].$eval('input[type="text"]', (n, v) => n.value = v, 'a');
  await alts2[1].$eval('input[type="text"]', (n, v) => n.value = v, 'b');
  await pg.$$eval('.linha-alternativa input[type="radio"]', ns => ns.forEach(n => n.checked = false));
  await pg.click('#formAtividade button[type=submit]');
  await pg.waitForLoadState();
  corpo = await pg.textContent('body');
  corpo.includes('exatamente uma alternativa')
    ? ok('quiz sem alternativa correta recusado')
    : falha('quiz sem resposta correta foi aceito');

  // =========================================================
  secao('O ALUNO USA O QUE FOI CADASTRADO');
  // publica a trilha
  await pg.goto(BASE + '/administracao.php');
  // (desde a v10 cada linha tem vários formulários: ordem, publicar, arquivar)
  await pg.click(`form:has(input[name=acao][value=publicar]):has(input[name=trilha_id][value="${trilhaId}"]) button[type=submit]`);
  await pg.waitForLoadState();
  corpo = await pg.textContent('body');
  corpo.includes('Trilha publicada') ? ok('trilha publicada') : falha('não publicou');

  await entrar('aluno@devfox.local', 'aluno123');
  await pg.goto(BASE + '/trilhas.php');          // o login agora cai no Início
  corpo = await pg.textContent('body');
  corpo.includes('Trilha de Teste ' + marca)
    ? ok('a trilha publicada aparece para o aluno')
    : falha('trilha publicada não apareceu para o aluno');

  await pg.goto(BASE + '/desafio.php?modulo=' + moduloId);
  corpo = await pg.textContent('body');
  corpo.includes('Esta é a explicação do módulo de teste')
    ? ok('a explicação cadastrada aparece na tela do aluno')
    : falha('a explicação não apareceu');

  // resolve o exercício criado pela tela
  await pg.goto(BASE + '/desafio.php?id=' + idDobro);
  const temEditor = await pg.$('#codigo');
  if (temEditor) {
    await escreverCodigo(pg, 'function dobro(n) { return n * 2; }');
    await pg.click('#btnRodar');
    await pg.waitForFunction(() => !document.getElementById('btnRodar').disabled, { timeout: 15000 });
    await pg.click('#formDesafio button[type=submit]');
    const fb = await esperarFeedback(pg);
    fb.includes('passaram')
      ? ok('o aluno resolve o exercício criado pela tela: ' + fb)
      : falha('exercício criado pela tela não aceitou a solução: ' + fb);
  } else {
    falha('a tela do aluno não abriu o exercício');
  }

  // chumbar a resposta visível não passa (o caso oculto faz seu trabalho)
  await pg.goto(BASE + '/desafio.php?id=' + idDobro);
  if (await pg.$('#codigo')) {
    await escreverCodigo(pg, 'function dobro(n) { if (n === 5) return 10; return 0; }');
    await pg.click('#btnRodar');
    await pg.waitForFunction(() => !document.getElementById('btnRodar').disabled, { timeout: 15000 });
    await pg.click('#formDesafio button[type=submit]');
    const fb = await esperarFeedback(pg);
    !fb.includes('Todos os casos passaram')
      ? ok('chumbar a resposta visível não passa no exercício criado pela tela')
      : falha('o caso oculto cadastrado pela tela não protegeu');
  } else {
    falha('a tela do exercício não abriu para a checagem de chumbo');
  }

  // =========================================================
  secao('CONTEÚDO COM PROGRESSO NÃO É APAGADO');
  await entrar('admin@devfox.local', 'devfox123');
  await pg.goto(urlTrilha);
  await pg.click(`form:has(input[name="acao"][value="apagar_modulo"]) button[type=submit]`)
    .catch(() => { /* o botão de apagar módulo não está nesta tela; segue */ });

  await pg.goto(BASE + '/admin_modulo.php?id=' + moduloId);
  corpo = await pg.textContent('body');
  corpo.includes('conclus')
    ? ok('o módulo mostra quantos alunos concluíram cada atividade')
    : falha('não mostrou contagem de conclusões');

  // tentar apagar a atividade já concluída
  const atividadeConcluida = await pg.$$eval('.linha-admin', ns => {
    const alvo = ns.find(n => n.textContent.includes('Dobro de um número'));
    const link = alvo && alvo.querySelector('a[href*="admin_atividade.php"]');
    return link ? link.getAttribute('href') : null;
  });

  if (atividadeConcluida) {
    await pg.goto(BASE + '/' + atividadeConcluida);
    corpo = await pg.textContent('body');
    corpo.includes('não pode ser apagada')
      ? ok('atividade já concluída por aluno não pode ser apagada')
      : falha('atividade com progresso oferece apagar');
  } else {
    falha('não achei a atividade concluída na lista');
  }

  // =========================================================
  secao('ERROS DE JAVASCRIPT');
  errosJs.length === 0 ? ok('nenhum erro de JS') : falha('erros: ' + JSON.stringify(errosJs));

  await navegador.close();
  console.log('\n' + (falhas === 0 ? '>>> TODOS OS TESTES PASSARAM' : '>>> ' + falhas + ' TESTE(S) FALHARAM'));
  process.exit(falhas === 0 ? 0 : 1);
})();
