/**
 * teste-trilhas.js — tela de Trilhas, exercícios de HTML/CSS e trilha bloqueada.
 * -----------------------------------------------------------------------------
 * Ferramenta de verificação; não é entregue ao aluno.
 *
 * O que este teste prova:
 *   - as 6 trilhas aparecem na ordem do wireframe, com destaque e recomendadas;
 *   - o Projeto Final nasce BLOQUEADO: sem link na lista e barrado pela URL;
 *     libera sozinho quando as outras 5 trilhas estão concluídas;
 *   - um exercício de HTML é corrigido de verdade: a solução passa, o código
 *     inicial não passa, e o servidor concede XP uma vez só;
 *   - <script> escrito pelo aluno NÃO executa (iframe sem allow-scripts);
 *   - exercício de CSS confere o estilo calculado pelo navegador.
 *
 * ⚠ MEXE NO BANCO (cria usuário, marca trilhas como concluídas via mysql).
 */

const { chromium } = require('playwright');
const { execSync } = require('child_process');
const { resolverAtual, rodar, esperarFeedback, APROVADO } = require('./ajudantes_teste');

const BASE  = process.env.DEVFOX_URL   || 'http://127.0.0.1:8080';
const MYSQL = process.env.DEVFOX_MYSQL || 'mysql -u root devfox';

let falhas = 0;
const ok    = t => console.log('  ✔ ' + t);
const falha = t => { console.log('  ✘ FALHOU: ' + t); falhas++; };
const secao = t => console.log('\n' + t);
const confere = (cond, bom, ruim) => cond ? ok(bom) : falha(ruim);

function sql(consulta) {
  return execSync(MYSQL + ' -N -B -e "' + consulta.replace(/"/g, '\\"') + '"').toString().trim();
}

(async () => {
  const navegador = await chromium.launch();
  const pg = await (await navegador.newContext()).newPage();
  const errosJs = [];
  pg.on('pageerror', e => errosJs.push(e.message));

  const email = 'trilhas' + Date.now() + '@teste.local';
  await pg.goto(BASE + '/cadastro.php');
  await pg.fill('#nome', 'Testador Trilhas');
  await pg.fill('#email', email);
  await pg.fill('#senha', 'senha123');
  await pg.click('button[type=submit]');
  await pg.waitForTimeout(2000);
  await pg.goto(BASE + '/login.php');
  await pg.fill('#email', email);
  await pg.fill('#senha', 'senha123');
  await pg.click('button[type=submit]');
  await pg.waitForURL(/(inicio|administracao)\.php/)   // o admin cai na Administração;
  const alunoId = sql("SELECT id FROM usuarios WHERE email = '" + email + "'");

  // =========================================================
  secao('TELA DE TRILHAS');
  await pg.goto(BASE + '/trilhas.php');
  const titulos = await pg.$$eval('.linha-trilha h3', n => n.map(x => x.textContent.trim()));
  const esperado = ['Lógica de Programação', 'HTML', 'CSS', 'JavaScript Básico',
                    'Desafios Práticos', 'Projeto Final'];
  confere(JSON.stringify(titulos) === JSON.stringify(esperado), '6 trilhas na ordem do wireframe',
          'ordem: ' + titulos.join(', '));

  const destaque = await pg.$$eval('.cartao-destaque-marca strong', n => n.map(x => x.textContent.trim()));
  confere(destaque.join(',') === 'HTML,CSS', 'painel de destaque com HTML e CSS', 'destaque: ' + destaque);
  confere((await pg.textContent('.destaque-topo h2')).trim() === 'HTML e CSS',
          'título do painel montado pelas trilhas ("HTML e CSS")', 'título do painel errado');

  const recs = await pg.$$('.cartao-recomendado');
  confere(recs.length >= 2, recs.length + ' recomendações para aluno novo', 'sem recomendações');

  const primarios = await pg.$$('.linha-trilha .botao-primario');
  confere(primarios.length === 1, 'um único botão principal (a trilha a seguir)', primarios.length + ' botões principais');

  // =========================================================
  secao('PROJETO FINAL BLOQUEADO');
  const linhaProjeto = pg.locator('.linha-trilha', { hasText: 'Projeto Final' });
  confere(await linhaProjeto.locator('a.botao').count() === 0 &&
          (await linhaProjeto.textContent()).includes('Bloqueada'),
          'Projeto Final aparece bloqueado e sem link', 'Projeto Final com link');

  const moduloProjeto = sql("SELECT m.id FROM modulos m JOIN trilhas t ON t.id = m.trilha_id " +
                            "WHERE t.titulo = 'Projeto Final' AND m.tipo = 'aula' LIMIT 1");
  await pg.goto(BASE + '/desafio.php?modulo=' + moduloProjeto);
  confere((await pg.content()).includes('Esta trilha libera depois'),
          'abrir o Projeto Final pela URL é barrado, com o motivo', 'Projeto Final abriu pela URL');

  // =========================================================
  secao('EXERCÍCIO DE HTML');
  const idHtml = sql("SELECT a.id FROM atividades a JOIN modulos m ON m.id = a.modulo_id " +
                     "JOIN trilhas t ON t.id = m.trilha_id WHERE t.titulo = 'HTML' AND a.tipo <> 'quiz' " +
                     "ORDER BY m.ordem, a.ordem LIMIT 1");
  await pg.goto(BASE + '/desafio.php?id=' + idHtml);
  confere((await pg.textContent('.selo-linguagem')).includes('HTML'), 'selo "HTML e CSS" no editor', 'selo errado');
  confere(await pg.$('#previaHtml') !== null, 'prévia da página aparece', 'sem prévia');

  // código inicial: não pode passar
  await rodar(pg, await pg.evaluate(() => window.editorDevFox.getValue()));
  await pg.click('button[type=submit]');
  let fb = await esperarFeedback(pg);
  confere(!fb.includes(APROVADO), 'código inicial é recusado pelo servidor', 'código inicial passou');

  // <script> do aluno não executa
  await pg.goto(BASE + '/desafio.php?id=' + idHtml);
  await rodar(pg, '<h1>Oi</h1><script>parent.document.title = "INVADIDO"; parent.window.invadido = true;</script>');
  await pg.waitForTimeout(600);
  confere((await pg.title()) !== 'INVADIDO' && !(await pg.evaluate(() => window.invadido === true)),
          '<script> do aluno não roda (iframe sem allow-scripts)', 'o script do aluno executou!');

  await pg.goto(BASE + '/desafio.php?id=' + idHtml);
  fb = await resolverAtual(pg);
  confere(fb.includes(APROVADO) && /ganhou \d+ XP/.test(fb), 'solução de HTML aprovada e ganha XP: "' + fb.slice(0, 60) + '"',
          'solução de HTML recusada: ' + fb);
  await pg.goto(BASE + '/desafio.php?id=' + idHtml);
  fb = await resolverAtual(pg);
  confere(fb.includes('não ganha XP de novo'), 'refazer o exercício de HTML não dá XP de novo', 'XP repetido: ' + fb);

  // =========================================================
  secao('EXERCÍCIO DE CSS — estilo calculado');
  const idCss = sql("SELECT a.id FROM atividades a JOIN modulos m ON m.id = a.modulo_id " +
                    "JOIN trilhas t ON t.id = m.trilha_id WHERE t.titulo = 'CSS' AND a.tipo <> 'quiz' " +
                    "ORDER BY m.ordem, a.ordem LIMIT 1");
  await pg.goto(BASE + '/desafio.php?id=' + idCss);
  confere((await pg.textContent('.saida-esperada')).includes('estilo('), 'casos de CSS conferem estilo(...)',
          'casos de CSS sem verificação de estilo');
  fb = await resolverAtual(pg);
  confere(fb.includes(APROVADO), 'solução de CSS aprovada', 'solução de CSS recusada: ' + fb);

  // =========================================================
  secao('PROJETO FINAL LIBERA COM AS OUTRAS CONCLUÍDAS');
  // Marca como concluído tudo das outras 5 trilhas (atividades de aula + provas).
  sql("INSERT IGNORE INTO atividades_concluidas (aluno_id, atividade_id, xp_concedido) " +
      "SELECT " + alunoId + ", a.id, 0 FROM atividades a JOIN modulos m ON m.id = a.modulo_id " +
      "JOIN trilhas t ON t.id = m.trilha_id WHERE t.titulo <> 'Projeto Final' AND m.tipo = 'aula'");
  sql("INSERT IGNORE INTO modulos_concluidos (aluno_id, modulo_id) " +
      "SELECT " + alunoId + ", m.id FROM modulos m JOIN trilhas t ON t.id = m.trilha_id " +
      "WHERE t.titulo <> 'Projeto Final' AND m.tipo = 'prova'");
  await pg.goto(BASE + '/trilhas.php');
  confere(await linhaProjeto.locator('a.botao').count() === 1, 'Projeto Final liberou na lista', 'Projeto Final continua bloqueado');
  await pg.goto(BASE + '/desafio.php?modulo=' + moduloProjeto);
  confere(await pg.$('#formDesafio') !== null, 'Projeto Final abre pela URL agora', 'Projeto Final não abriu');

  // =========================================================
  secao('JAVASCRIPT');
  confere(errosJs.length === 0, 'nenhum erro de JavaScript', 'erros: ' + errosJs.join(' | '));

  await navegador.close();
  console.log('\n' + (falhas === 0 ? '>>> TODOS OS TESTES PASSARAM' : '>>> ' + falhas + ' TESTE(S) FALHARAM'));
  process.exit(falhas === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(1); });
