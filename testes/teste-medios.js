/**
 * teste-medios.js — os problemas médios da auditoria de 21/09/2026 (itens 6 a 11).
 * -----------------------------------------------------------------------------
 * Ferramenta de verificação; não é entregue ao aluno.
 *
 *    6. Sequência de dias: conta ESTUDO (não login), zera na tela de quem
 *       parou, e o "hoje" é o de Brasília (PHP e MySQL no mesmo fuso).
 *    7. Bloqueio olha TODOS os módulos anteriores.
 *    8. Pré-requisito circular é recusado; pré-requisito em rascunho não prende.
 *    9. CSRF em login, cadastro, APIs e logout (POST); limite de 5 senhas
 *       erradas por e-mail; cookie de sessão HttpOnly + SameSite.
 *   10. Prova embaralhada a cada abertura; aprovação sem arredondar.
 *       (A espera depois de reprovar está em teste-prova.js.)
 *   11. Cadastro não quebra com nome gigante ou campo em lista, e não confirma
 *       quem tem conta.
 *
 * ⚠ MEXE NO BANCO. Rode num banco de desenvolvimento recém-importado.
 */

const { chromium } = require('playwright');
const { execSync } = require('child_process');
const { concluirModulo, sair, responderProva } = require('./ajudantes_teste');

const BASE  = process.env.DEVFOX_URL   || 'http://127.0.0.1:8080';
const MYSQL = process.env.DEVFOX_MYSQL || 'mysql -u root devfox';

let falhas = 0;
const ok      = t => console.log('  ✔ ' + t);
const falha   = t => { console.log('  ✘ FALHOU: ' + t); falhas++; };
const secao   = t => console.log('\n' + t);
const confere = (cond, bom, ruim) => cond ? ok(bom) : falha(ruim);
// Mesmo fuso do sistema (includes/ambiente.php): CURDATE() aqui = "hoje" de Brasília.
const sql = q => execSync(MYSQL + ' --default-character-set=utf8mb4 -N -B -e "SET time_zone = \'-03:00\'; ' +
                          q.replace(/"/g, '\\"') + '"').toString().trim();
const hojeBrasilia = () => new Date().toLocaleDateString('sv-SE', { timeZone: 'America/Sao_Paulo' });

(async () => {
  const nav = await chromium.launch();
  const ctx = await nav.newContext();
  const pg  = await ctx.newPage();
  const errosJs = [];
  pg.on('pageerror', e => errosJs.push(e.message));

  async function cadastrar(nome, email, senha = 'senha123') {
    await pg.goto(BASE + '/cadastro.php');
    await pg.fill('#nome', nome);
    await pg.fill('#email', email);
    await pg.fill('#senha', senha);
    await pg.click('button[type=submit]');
    await pg.waitForLoadState();
    return pg.textContent('body');
  }
  async function entrar(email, senha = 'senha123') {
    await sair(pg, BASE);
    await pg.goto(BASE + '/login.php');
    await pg.fill('#email', email);
    await pg.fill('#senha', senha);
    await pg.click('button[type=submit]');
    await pg.waitForLoadState();
    return pg.url();
  }

  const email = 'medios' + Date.now() + '@teste.local';
  await cadastrar('Testador Medios', email);
  const uid = sql("SELECT id FROM usuarios WHERE email = '" + email + "'");

  // =========================================================
  secao('6. SEQUÊNCIA DE DIAS');
  sql('UPDATE usuarios SET sequencia_dias = 5, ultimo_dia_ativo = CURDATE() - INTERVAL 3 DAY WHERE id = ' + uid);
  await entrar(email);
  confere(sql('SELECT CONCAT(sequencia_dias, \'|\', ultimo_dia_ativo) FROM usuarios WHERE id = ' + uid).startsWith('5|') &&
          sql('SELECT ultimo_dia_ativo = CURDATE() - INTERVAL 3 DAY FROM usuarios WHERE id = ' + uid) === '1',
          'entrar NÃO conta como dia de estudo (banco não mudou)', 'o login mexeu na sequência');
  const lateral = (await pg.textContent('.cartao-usuario-numeros li:first-child strong')).trim();
  confere(lateral === '0', 'parou há 3 dias → a barra lateral mostra 0, não o 5 antigo', 'barra lateral mostra ' + lateral);
  await pg.goto(BASE + '/ranking.php');
  const linha = await pg.$$eval('table tbody tr', (ls, nome) => {
    const l = ls.find(x => x.textContent.includes(nome));
    return l ? [...l.querySelectorAll('td.num')].map(td => td.textContent.trim()) : null;
  }, 'Testador Medios');
  confere(linha && linha[1] === '0', 'ranking também mostra 0 para quem parou', 'ranking: ' + JSON.stringify(linha));

  sql('UPDATE usuarios SET ultimo_dia_ativo = CURDATE() - INTERVAL 1 DAY WHERE id = ' + uid);
  await pg.goto(BASE + '/inicio.php');
  confere((await pg.textContent('.cartao-usuario-numeros li:first-child strong')).trim() === '5',
          'estudou ontem → a sequência de 5 ainda vale hoje', 'sequência de ontem não apareceu');

  // enviar uma resposta (mesmo errada) = estudou hoje
  await pg.goto(BASE + '/desafio.php?id=1');
  await pg.check('.alternativa input[type=radio] >> nth=0');
  await pg.click('button[type=submit]');
  await pg.waitForFunction(() => {
    const f = document.getElementById('feedbackMsg');
    return f && f.textContent.trim() !== '' && !f.textContent.includes('Conferindo');
  });
  const [seq, dia] = sql('SELECT CONCAT(sequencia_dias, \'|\', ultimo_dia_ativo) FROM usuarios WHERE id = ' + uid).split('|');
  confere(seq === '6', 'responder uma atividade conta o dia: 5 → 6', 'sequência ficou ' + seq);
  confere(dia === hojeBrasilia(), 'o "hoje" gravado é o de Brasília (' + dia + ')', 'dia gravado ' + dia + ' ≠ ' + hojeBrasilia());

  // =========================================================
  secao('7. BLOQUEIO OLHA TODOS OS MÓDULOS ANTERIORES');
  const mods = sql("SELECT id FROM modulos WHERE trilha_id = 1 AND tipo = 'aula' ORDER BY ordem, id").split('\n');
  await concluirModulo(pg, BASE, mods[0]);
  await concluirModulo(pg, BASE, mods[1]);
  // admin acrescenta uma atividade nova no módulo 1 de quem já tinha terminado
  sql("INSERT INTO atividades (modulo_id, titulo, enunciado, tipo, xp_recompensa, ordem) " +
      "VALUES (" + mods[0] + ", 'Nova', 'Questão nova', 'quiz', 10, 99)");
  const nova = sql('SELECT MAX(id) FROM atividades');
  sql('INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES (' + nova + ", 'Sim', 1, 1), (" + nova + ", 'Não', 0, 2)");
  await pg.goto(BASE + '/desafio.php?modulo=' + mods[2]);
  confere((await pg.textContent('body')).includes('Módulo ainda bloqueado'),
          'módulo 3 trava enquanto o 1 tem atividade pendente (antes ficava aberto)', 'módulo 3 continuou aberto');
  await pg.goto(BASE + '/desafio.php?id=' + sql('SELECT id FROM atividades WHERE modulo_id = ' + mods[1] + ' ORDER BY ordem, id LIMIT 1'));
  confere(!(await pg.textContent('body')).includes('Módulo ainda bloqueado'),
          'módulo 2, já concluído, continua aberto para revisão', 'módulo concluído ficou trancado');

  // =========================================================
  secao('8. PRÉ-REQUISITOS');
  const adm = await (await nav.newContext()).newPage();
  await adm.goto(BASE + '/login.php');
  await adm.fill('#email', 'admin@devfox.local');
  await adm.fill('#senha', 'devfox123');
  await adm.click('button[type=submit]');
  await adm.waitForLoadState();
  await adm.goto(BASE + '/admin_trilha.php?id=1');
  await adm.check('input[name="requisitos[]"][value="8"]');       // 8 (Projeto Final) já exige a 1
  await adm.click('form:has(input[value="salvar_trilha"]) button[type=submit]');
  await adm.waitForLoadState();
  confere((await adm.textContent('body')).includes('Requisito circular') &&
          sql('SELECT COUNT(*) FROM trilha_requisitos WHERE trilha_id = 1') === '0',
          'Lógica exigir o Projeto Final (que exige a Lógica) é recusado', 'requisito circular foi salvo');

  sql('UPDATE trilhas SET publicada = 0 WHERE id = 2');           // JavaScript Básico em rascunho
  await pg.goto(BASE + '/trilhas.php');
  const motivo = await pg.textContent('.linha-trilha[data-trilha="8"] .sr-only');
  confere(!motivo.includes('JavaScript Básico'), 'trilha exigida em rascunho não aparece como pendente',
          'rascunho ainda prende: ' + motivo);
  sql('UPDATE trilhas SET publicada = 1 WHERE id = 2');

  // =========================================================
  secao('9. CSRF, LOGOUT, LIMITE DE LOGIN E COOKIE');
  const anon = await nav.newContext();
  const ap = await anon.newPage();
  let r = await ap.request.post(BASE + '/login.php', { form: { email, senha: 'senha123' } });
  confere(r.status() === 403, 'login sem token → 403 (login CSRF)', 'login sem token devolveu ' + r.status());
  r = await ap.request.post(BASE + '/cadastro.php', { form: { nome: 'X Y', email: 'x' + Date.now() + '@t.local', senha: 'senha123' } });
  confere(r.status() === 403, 'cadastro sem token → 403', 'cadastro sem token devolveu ' + r.status());
  await anon.close();

  await pg.goto(BASE + '/inicio.php');
  r = await pg.request.post(BASE + '/api/processar_desafio.php', { form: { atividade_id: '1', alternativa_id: '1' } });
  confere(r.status() === 403 && (await r.json()).sucesso === false, 'API de respostas sem token → 403 (JSON)', 'API sem token: ' + r.status());
  r = await pg.request.post(BASE + '/api/dica.php', { form: { atividade_id: '2' } });
  confere(r.status() === 403, 'API de dica sem token → 403', 'dica sem token: ' + r.status());

  await pg.goto(BASE + '/logout.php');                             // GET: não desloga mais
  confere(pg.url().endsWith('/inicio.php'), 'abrir logout.php por GET não desloga', 'GET deslogou: ' + pg.url());
  r = await pg.request.post(BASE + '/logout.php', { form: {} });
  confere(r.status() === 403, 'logout por POST sem token → 403', 'logout sem token: ' + r.status());
  await pg.goto(BASE + '/inicio.php');
  confere(pg.url().endsWith('/inicio.php'), 'continua logado depois das tentativas forjadas', 'caiu da sessão');

  const cookies = await ctx.cookies();
  const sessao = cookies.find(c => c.name === 'PHPSESSID');
  confere(sessao && sessao.httpOnly && sessao.sameSite === 'Lax', 'cookie de sessão HttpOnly + SameSite=Lax',
          'cookie de sessão: ' + JSON.stringify(sessao));

  await sair(pg, BASE);
  confere(pg.url().endsWith('/login.php'), 'botão Sair (POST com token) desloga', 'Sair não deslogou');

  // limite de login
  for (let i = 0; i < 5; i++) await entrar(email, 'errada' + i);
  await entrar(email, 'senha123');
  confere(pg.url().endsWith('/login.php') && (await pg.textContent('body')).includes('Muitas tentativas'),
          'depois de 5 senhas erradas, nem a certa entra (bloqueio de 15 min)', 'entrou depois de 5 erros: ' + pg.url());
  const fantasma = 'naoexiste' + Date.now() + '@teste.local';
  for (let i = 0; i < 5; i++) await entrar(fantasma, 'x' + i);
  await entrar(fantasma, 'y');
  confere((await pg.textContent('body')).includes('Muitas tentativas'),
          'e-mail que não existe também bloqueia (o bloqueio não revela quem tem conta)', 'só conta existente bloqueia');
  sql("UPDATE tentativas_login SET criado_em = criado_em - INTERVAL 20 MINUTE WHERE email = '" + email + "'");
  confere((await entrar(email, 'senha123')).endsWith('/inicio.php') &&
          sql("SELECT COUNT(*) FROM tentativas_login WHERE email = '" + email + "'") === '0',
          'passados 15 minutos, a senha certa entra e zera a contagem', 'não liberou depois da janela');

  // =========================================================
  secao('10. PROVA EMBARALHADA E NOTA SEM ARREDONDAR');
  sql('INSERT IGNORE INTO atividades_concluidas (aluno_id, atividade_id, xp_concedido) ' +
      'SELECT ' + uid + ', a.id, 0 FROM atividades a JOIN modulos m ON m.id = a.modulo_id ' +
      "WHERE m.trilha_id = 1 AND m.tipo = 'aula'");
  const prova = sql("SELECT id FROM modulos WHERE trilha_id = 1 AND tipo = 'prova' LIMIT 1");
  const ordens = new Set();
  for (let i = 0; i < 6; i++) {
    await pg.goto(BASE + '/prova.php?modulo=' + prova);
    ordens.add(JSON.stringify(await pg.$$eval('.questao-prova input[type=radio]', ns => ns.map(n => n.name + '=' + n.value))));
  }
  confere(ordens.size > 1, 'questões/alternativas mudam de ordem entre aberturas (' + ordens.size + ' ordens em 6)',
          'a prova veio sempre na mesma ordem');

  // 3 questões, mínimo 67%: 2 de 3 = 66,67%. Com round() virava 67% e passava.
  const questoes = sql('SELECT id FROM atividades WHERE modulo_id = ' + prova + ' ORDER BY ordem, id').split('\n');
  sql('DELETE FROM atividades WHERE id IN (' + questoes.slice(3).join(',') + ')');   // banco de teste
  sql('UPDATE modulos SET nota_minima = 67 WHERE id = ' + prova);
  await pg.goto(BASE + '/prova.php?modulo=' + prova);
  await responderProva(pg, 1);
  const nota = (await pg.textContent('.resultado-percentual')).trim();
  confere(nota === '67%' && (await pg.textContent('body')).includes('Ainda não foi dessa vez'),
          '2 de 3 (66,67%, exibido como 67%) NÃO passa numa prova de mínimo 67%', 'nota ' + nota + ' aprovou');

  // =========================================================
  secao('11. CADASTRO');
  await sair(pg, BASE);
  // O campo tem maxlength=100 no HTML, mas o servidor não pode confiar nisso:
  // o POST vai direto, sem o navegador cortar o texto.
  await pg.goto(BASE + '/cadastro.php');
  const token = await pg.getAttribute('input[name=csrf]', 'value');
  r = await pg.request.post(BASE + '/cadastro.php', { form: { csrf: token, nome: 'A'.repeat(101), email: 'grande' + Date.now() + '@t.local', senha: 'senha123' } });
  let texto = await r.text();
  confere(r.status() === 200 && texto.includes('entre 2 e 100'), 'nome de 101 caracteres: mensagem, não erro 500',
          'nome gigante: ' + r.status());
  r = await pg.request.post(BASE + '/cadastro.php', { form: { csrf: token, 'nome[]': 'x', email: 'lista@t.local', senha: 'senha123' } });
  confere(r.status() === 200 && (await r.text()).includes('Preencha todos os campos'),
          'campo enviado como lista (nome[]=): mensagem, não erro 500', 'lista devolveu ' + r.status());
  texto = await cadastrar('Outra Pessoa', email);
  confere(!texto.includes('já está cadastrado') && texto.includes('Não foi possível criar a conta'),
          'e-mail repetido: mensagem neutra (não confirma que a conta existe)', 'mensagem: ' + texto.slice(0, 160));
  const maiusculo = 'Maiusculo' + Date.now() + '@Teste.Local';
  await cadastrar('Letra Grande', maiusculo);
  confere(sql("SELECT email FROM usuarios WHERE email = '" + maiusculo + "'") === maiusculo.toLowerCase(),
          'e-mail gravado em minúsculas', 'e-mail gravado como digitado');

  // =========================================================
  secao('JAVASCRIPT');
  confere(errosJs.length === 0, 'nenhum erro de JavaScript', 'erros: ' + errosJs.join(' | '));

  await nav.close();
  console.log('\n' + (falhas === 0 ? '>>> TODOS OS TESTES PASSARAM' : '>>> ' + falhas + ' TESTE(S) FALHARAM'));
  process.exit(falhas === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(1); });
