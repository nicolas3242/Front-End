/**
 * teste-graves.js — os 5 furos graves da auditoria de 21/09/2026.
 * -----------------------------------------------------------------------------
 * Ferramenta de verificação; não é entregue ao aluno.
 *
 * Cada seção reproduz um ataque ou um travamento que EXISTIA e confere que
 * agora não acontece mais:
 *   1. API de respostas: questão de prova, módulo bloqueado e trilha em
 *      rascunho são recusados (antes davam XP e revelavam o gabarito da prova);
 *   2. "Mostrar exemplo de solução" só aparece depois de concluir;
 *   3. módulo vazio (aula sem atividade / prova sem questão) não trava a trilha;
 *   4. prova aprovada com nota mínima (não 100%) = trilha concluída: 100%,
 *      conquista e liberação da trilha dependente;
 *   5. paginação do histórico com número gigante não derruba a página.
 * (A proteção das pastas pelo .htaccess é do Apache; o servidor embutido do
 *  PHP usado aqui ignora .htaccess — ver testes/README.md.)
 *
 * ⚠ MEXE NO BANCO. Rode num banco de desenvolvimento recém-importado.
 */

const { chromium } = require('playwright');
const { execSync } = require('child_process');
const { concluirModulo, resolverAtual, APROVADO } = require('./ajudantes_teste');

const BASE  = process.env.DEVFOX_URL   || 'http://127.0.0.1:8080';
const MYSQL = process.env.DEVFOX_MYSQL || 'mysql -u root devfox';

let falhas = 0;
const ok      = t => console.log('  ✔ ' + t);
const falha   = t => { console.log('  ✘ FALHOU: ' + t); falhas++; };
const secao   = t => console.log('\n' + t);
const confere = (cond, bom, ruim) => cond ? ok(bom) : falha(ruim);
const sql     = q => execSync(MYSQL + ' -N -B -e "' + q.replace(/"/g, '\\"') + '"').toString().trim();

(async () => {
  const nav = await chromium.launch();
  const pg  = await (await nav.newContext()).newPage();
  const errosJs = [];
  pg.on('pageerror', e => errosJs.push(e.message));

  const email = 'graves' + Date.now() + '@teste.local';
  await pg.goto(BASE + '/cadastro.php');
  await pg.fill('#nome', 'Testador Graves');
  await pg.fill('#email', email);
  await pg.fill('#senha', 'senha123');
  await pg.click('button[type=submit]');
  await pg.waitForTimeout(1500);
  await pg.goto(BASE + '/login.php');
  await pg.fill('#email', email);
  await pg.fill('#senha', 'senha123');
  await pg.click('button[type=submit]');
  await pg.waitForURL(/(inicio|administracao)\.php/)   // o admin cai na Administração;
  const uid = sql("SELECT id FROM usuarios WHERE email = '" + email + "'");
  const xp  = () => parseInt(sql('SELECT xp FROM usuarios WHERE id = ' + uid), 10);

  // Ids tirados do banco, não fixos: o conteúdo pode mudar.
  const prova1   = sql("SELECT id FROM modulos WHERE trilha_id = 1 AND tipo = 'prova' LIMIT 1");
  const questao  = sql('SELECT id FROM atividades WHERE modulo_id = ' + prova1 + ' ORDER BY ordem, id LIMIT 1');
  const altsQ    = sql('SELECT id FROM alternativas WHERE atividade_id = ' + questao).split('\n');
  const mod2     = sql("SELECT id FROM modulos WHERE trilha_id = 1 AND tipo = 'aula' ORDER BY ordem, id LIMIT 1 OFFSET 1");
  const ativMod2 = sql('SELECT id FROM atividades WHERE modulo_id = ' + mod2 + ' ORDER BY ordem, id LIMIT 1');

  // Com o token CSRF certo (desde 22/09 as APIs exigem): o que se testa aqui
  // são as regras de progressão, não o CSRF (esse é o teste-medios.js).
  await pg.goto(BASE + '/inicio.php');
  const csrf = await pg.getAttribute('meta[name="csrf"]', 'content');
  const api = async (campos) => {
    const r = await pg.request.post(BASE + '/api/processar_desafio.php', { form: { csrf, ...campos } });
    return { status: r.status(), json: await r.json().catch(() => ({})) };
  };

  // =========================================================
  secao('1. API DE RESPOSTAS');
  const xp0 = xp();
  let certas = 0, status403 = 0;
  for (const alt of altsQ) {
    const r = await api({ atividade_id: questao, alternativa_id: alt });
    if (r.status === 403) status403++;
    if (r.json.sucesso) certas++;
  }
  confere(status403 === altsQ.length && certas === 0 && xp() === xp0,
          'questão da prova: todas as ' + altsQ.length + ' alternativas → 403, gabarito não vaza, XP igual',
          'API ainda corrige questão de prova (' + certas + ' certa(s), ' + status403 + ' × 403)');

  let r = await api({ atividade_id: ativMod2, alternativa_id: 0, codigo: 'x', resultados: '[]' });
  confere(r.status === 403 && xp() === xp0, 'módulo 2 bloqueado → 403, sem XP', 'módulo bloqueado devolveu ' + r.status);

  // trilha em rascunho: a 1ª atividade da trilha 3 está liberada... até despublicar
  const ativT3 = sql("SELECT a.id FROM atividades a JOIN modulos m ON m.id = a.modulo_id " +
                     "WHERE m.trilha_id = 3 ORDER BY m.ordem, a.ordem, a.id LIMIT 1");
  sql('UPDATE trilhas SET publicada = 0 WHERE id = 3');
  r = await api({ atividade_id: ativT3, alternativa_id: 0, codigo: 'x', resultados: '[]' });
  confere(r.status === 403, 'trilha em rascunho → 403 na API', 'rascunho devolveu ' + r.status);
  const rd = await pg.request.post(BASE + '/api/dica.php', { form: { csrf, atividade_id: ativT3 } });
  confere(rd.status() === 403, 'trilha em rascunho → 403 na dica', 'dica do rascunho devolveu ' + rd.status());
  await pg.goto(BASE + '/desafio.php?id=' + ativT3);
  confere((await pg.textContent('body')).includes('bloqueado'), 'rascunho não abre pela URL', 'rascunho abriu pela URL');
  sql('UPDATE trilhas SET publicada = 1 WHERE id = 3');

  // =========================================================
  secao('2. SOLUÇÃO DE EXEMPLO SÓ DEPOIS DE CONCLUIR');
  const codigo1 = sql("SELECT a.id FROM atividades a JOIN modulos m ON m.id = a.modulo_id " +
                      "WHERE m.trilha_id = 1 AND a.tipo <> 'quiz' AND a.solucao_exemplo IS NOT NULL " +
                      "AND a.solucao_exemplo <> '' ORDER BY m.ordem, a.ordem, a.id LIMIT 1");
  const modCod = sql('SELECT modulo_id FROM atividades WHERE id = ' + codigo1);
  // resolve o módulo até chegar nessa atividade (ela precisa estar liberada)
  await pg.goto(BASE + '/desafio.php?id=' + codigo1);
  confere(!(await pg.textContent('body')).includes('Mostrar exemplo de solução'),
          'antes de resolver: sem "Mostrar exemplo de solução"', 'solução aparece antes de resolver');
  const fb = await resolverAtual(pg);
  confere(fb.includes(APROVADO), 'atividade resolvida', 'não resolveu: ' + fb);
  await pg.goto(BASE + '/desafio.php?id=' + codigo1);
  confere((await pg.textContent('body')).includes('Mostrar exemplo de solução'),
          'depois de resolver: a solução aparece para comparar', 'solução não apareceu depois de resolver');

  // =========================================================
  secao('3. MÓDULO VAZIO NÃO TRAVA A TRILHA');
  // aula vazia logo depois do módulo 1, e prova vazia no fim da trilha 1
  const mod1 = sql("SELECT id FROM modulos WHERE trilha_id = 1 ORDER BY ordem, id LIMIT 1");
  const ordem1 = sql('SELECT ordem FROM modulos WHERE id = ' + mod1);
  sql("INSERT INTO modulos (trilha_id, titulo, tipo, ordem) VALUES (1, 'Aula vazia', 'aula', " + ordem1 + ")");
  sql("INSERT INTO modulos (trilha_id, titulo, tipo, ordem, nota_minima) VALUES (1, 'Prova vazia', 'prova', 99, 70)");
  await concluirModulo(pg, BASE, mod1);
  await concluirModulo(pg, BASE, modCod);
  await pg.goto(BASE + '/desafio.php?modulo=' + mod2);
  confere(!(await pg.textContent('body')).includes('Módulo ainda bloqueado'),
          'aula vazia no meio não bloqueia o módulo seguinte', 'aula vazia travou o módulo seguinte');

  // =========================================================
  secao('4. PROVA APROVADA COM A NOTA MÍNIMA = TRILHA CONCLUÍDA');
  const aulas = sql("SELECT m.id FROM modulos m WHERE m.trilha_id = 1 AND m.tipo = 'aula' " +
                    "AND EXISTS (SELECT 1 FROM atividades a WHERE a.modulo_id = m.id) ORDER BY m.ordem, m.id").split('\n');
  for (const m of aulas) await concluirModulo(pg, BASE, m);

  // responde a prova errando UMA questão (4 de 5 = 80%, mínimo 70%)
  await pg.goto(BASE + '/prova.php?modulo=' + prova1);
  const questoes = await pg.$$('.questao-prova');
  for (let q = 0; q < questoes.length; q++) {
    const valores = await questoes[q].$$eval('.alternativa input', ns => ns.map(n => n.value));
    const certa = sql('SELECT id FROM alternativas WHERE correta = 1 AND id IN (' + valores.join(',') + ')');
    const escolha = q === 0 ? valores.find(v => v !== certa) : certa;
    await pg.check(`.questao-prova >> nth=${q} >> .alternativa input[value="${escolha}"]`);
  }
  await pg.click('button[type=submit]');
  await pg.waitForLoadState();
  const nota = (await pg.textContent('.resultado-percentual')).trim();
  confere((await pg.textContent('body')).includes('Aprovado') && nota !== '100%',
          'aprovado com ' + nota + ' (não 100%)', 'não aprovou com 4/5: ' + nota);

  await pg.goto(BASE + '/trilhas.php');
  const pct = (await pg.textContent('.linha-trilha[data-trilha="1"] .linha-trilha-progresso strong')).trim();
  confere(pct === '100%', 'Lógica aparece com 100% (antes ficava presa em 95%)', 'Lógica ficou em ' + pct);
  const botao = (await pg.textContent('.linha-trilha[data-trilha="1"] .linha-trilha-acao')).trim();
  confere(botao === 'Revisar', 'botão vira "Revisar"', 'botão: ' + botao);
  const temConquista = sql("SELECT COUNT(*) FROM conquistas_usuarios cu JOIN conquistas c ON c.id = cu.conquista_id " +
                           "WHERE cu.usuario_id = " + uid + " AND c.codigo = 'trilha_completa'");
  confere(temConquista === '1', 'conquista "trilha completa" desbloqueada', 'conquista não saiu');
  const motivo = await pg.textContent('.linha-trilha[data-trilha="8"] .sr-only');
  confere(!motivo.includes('Lógica de Programação'),
          'Projeto Final não lista mais Lógica como pendente (a prova vazia não prende)',
          'Lógica ainda pendente para o Projeto Final: ' + motivo);
  await pg.goto(BASE + '/perfil.php');
  const nums = await pg.$$eval('.grade-estatisticas .cartao-estatistica', n => n.map(x => x.textContent.replace(/\s+/g, ' ')));
  confere(nums.some(t => /Trilhas concluídas 1 /.test(t)),
          'Perfil conta 1 trilha concluída', 'Perfil: ' + JSON.stringify(nums));

  // =========================================================
  secao('5. PAGINAÇÃO COM NÚMERO GIGANTE');
  const rp = await pg.goto(BASE + '/atividades.php?pagina=9223372036854775807');
  const html = await pg.content();
  confere(rp.status() === 200 && !/Fatal error|TypeError/.test(html),
          'página abre normalmente', 'erro: status ' + rp.status());

  // =========================================================
  secao('ADMIN AINDA ABRE RASCUNHO (para conferir antes de publicar)');
  sql('UPDATE trilhas SET publicada = 0 WHERE id = 3');
  const adm = await (await nav.newContext()).newPage();
  await adm.goto(BASE + '/login.php');
  await adm.fill('#email', 'admin@devfox.local');
  await adm.fill('#senha', 'devfox123');
  await adm.click('button[type=submit]');
  await adm.waitForLoadState();
  await adm.goto(BASE + '/desafio.php?id=' + ativT3);
  confere(await adm.$('#formDesafio') !== null, 'admin abre a atividade em rascunho', 'admin bloqueado no rascunho');
  sql('UPDATE trilhas SET publicada = 1 WHERE id = 3');

  // =========================================================
  secao('JAVASCRIPT');
  confere(errosJs.length === 0, 'nenhum erro de JavaScript', 'erros: ' + errosJs.join(' | '));

  await nav.close();
  console.log('\n' + (falhas === 0 ? '>>> TODOS OS TESTES PASSARAM' : '>>> ' + falhas + ' TESTE(S) FALHARAM'));
  process.exit(falhas === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(1); });
