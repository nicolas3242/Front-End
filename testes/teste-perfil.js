/**
 * teste-perfil.js — tela de Perfil, conquistas, histórico e troca de avatar.
 * -----------------------------------------------------------------------------
 * Ferramenta de verificação; não é entregue ao aluno.
 *
 * O que este teste prova:
 *   - os blocos do wireframe estão na tela e os números batem com o banco
 *     (desafios, conquistas, "+N esta semana", melhor sequência);
 *   - trocar o avatar funciona, aparece na barra lateral, e um código fora do
 *     catálogo (ex.: um caminho de arquivo) é recusado; sem CSRF, 403;
 *   - "Ver todas" das conquistas lista o catálogo inteiro;
 *   - o histórico completo pagina.
 *
 * ⚠ MEXE NO BANCO. Rode num banco de desenvolvimento.
 */

const { chromium } = require('playwright');
const { execSync } = require('child_process');

const BASE  = process.env.DEVFOX_URL   || 'http://127.0.0.1:8080';
const MYSQL = process.env.DEVFOX_MYSQL || 'mysql -u root devfox';

let falhas = 0;
const ok    = t => console.log('  ✔ ' + t);
const falha = t => { console.log('  ✘ FALHOU: ' + t); falhas++; };
const secao = t => console.log('\n' + t);
const confere = (cond, bom, ruim) => cond ? ok(bom) : falha(ruim);
const sql = q => execSync(MYSQL + ' -N -B -e "' + q.replace(/"/g, '\\"') + '"').toString().trim();
const numero = t => parseInt(String(t).replace(/\D/g, ''), 10);

(async () => {
  const nav = await chromium.launch();
  const pg = await (await nav.newContext()).newPage();
  const errosJs = [];
  pg.on('pageerror', e => errosJs.push(e.message));

  const email = 'perfil' + Date.now() + '@teste.local';
  await pg.goto(BASE + '/cadastro.php');
  await pg.fill('#nome', 'Testador Perfil');
  await pg.fill('#email', email);
  await pg.fill('#senha', 'senha123');
  await pg.click('button[type=submit]');
  await pg.waitForTimeout(1500);
  const uid = sql("SELECT id FROM usuarios WHERE email = '" + email + "'");

  // Progresso de exemplo: 25 atividades de código hoje (dá > 20 eventos, para paginar),
  // 2 conquistas, recorde de sequência 9.
  sql('INSERT INTO atividades_concluidas (aluno_id, atividade_id, xp_concedido) ' +
      'SELECT ' + uid + ", a.id, a.xp_recompensa FROM atividades a JOIN modulos m ON m.id = a.modulo_id " +
      "WHERE a.tipo <> 'quiz' AND m.tipo = 'aula' ORDER BY a.id LIMIT 25");
  sql('INSERT INTO atividades_concluidas (aluno_id, atividade_id, xp_concedido, concluido_em) ' +
      'SELECT ' + uid + ", a.id, 50, NOW() - INTERVAL 20 DAY FROM atividades a " +
      "WHERE a.tipo = 'quiz' ORDER BY a.id LIMIT 2");
  sql('UPDATE usuarios SET xp = (SELECT SUM(xp_concedido) FROM atividades_concluidas WHERE aluno_id = ' + uid +
      '), melhor_sequencia = 9 WHERE id = ' + uid);
  sql('INSERT INTO conquistas_usuarios (usuario_id, conquista_id) VALUES (' + uid + ', 1), (' + uid + ', 2)');

  await pg.goto(BASE + '/login.php');
  await pg.fill('#email', email);
  await pg.fill('#senha', 'senha123');
  await pg.click('button[type=submit]');
  await pg.waitForURL(/(inicio|administracao)\.php/)   // o admin cai na Administração;
  await pg.goto(BASE + '/perfil.php');

  // =========================================================
  secao('BLOCOS DO WIREFRAME');
  for (const [sel, nome] of [['.cartao-perfil', 'cartão do perfil'], ['.perfil-numeros', 'linha de números'],
                             ['.grade-conquistas-perfil', 'conquistas'], ['.grade-estatisticas', 'estatísticas'],
                             ['.frase-perfil', 'frase'], ['.painel-mascote', 'mascote'], ['.lista-eventos', 'atividade recente'],
                             ['a[href="configuracoes.php"].botao', '"Editar perfil"'], ['#btnAvatar', '"Alterar avatar"'],
                             ['.painel-mascote a[href="loja.php"]', '"Personalizar mascote"'],
                             ['a[href="conquistas.php"]', '"Ver todas" (conquistas)'], ['a[href="atividades.php"]', '"Ver todas" (atividades)']]) {
    confere(await pg.$(sel) !== null, nome, 'faltando: ' + nome);
  }

  // =========================================================
  secao('NÚMEROS = BANCO');
  const nums = await pg.$$eval('.perfil-numeros strong', n => n.map(x => parseInt(x.textContent, 10)));
  // O login já confere as regras e pode desbloquear mais do que as 2 inseridas.
  const noBanco = parseInt(sql('SELECT COUNT(*) FROM conquistas_usuarios WHERE usuario_id = ' + uid), 10);
  confere(nums[1] === noBanco && noBanco >= 2, 'conquistas = banco (' + noBanco + ')', 'conquistas: ' + nums[1] + ' x ' + noBanco);
  confere(nums[3] === 25, 'desafios concluídos: 25 (quiz não conta)', 'desafios: ' + nums[3]);

  const est = await pg.$$eval('.cartao-estatistica', n => n.map(x => x.textContent.replace(/\s+/g, ' ')));
  const xpSemana = parseInt(sql('SELECT SUM(xp_concedido) FROM atividades_concluidas WHERE aluno_id = ' + uid +
                               ' AND concluido_em >= NOW() - INTERVAL 7 DAY'), 10);
  confere(numero(est[0].split('+')[1]) === xpSemana, 'XP da semana = ' + xpSemana + ' (o de 20 dias atrás não conta)',
          'XP da semana errado: ' + est[0]);
  confere(est[1].includes('+25 esta semana'), '+25 desafios esta semana', 'desafios da semana: ' + est[1]);
  confere(est[3].includes('Melhor: 9 dias'), 'melhor sequência vem do banco (9 dias)', 'melhor sequência: ' + est[3]);

  const cards = await pg.$$('.grade-conquistas-perfil .conquista');
  confere(cards.length === 4, 'perfil mostra 4 conquistas', cards.length + ' conquistas no perfil');
  const primeira = (await pg.textContent('.grade-conquistas-perfil .conquista strong')).trim();
  confere(await pg.$('.grade-conquistas-perfil .conquista:first-child.conquista-bloqueada') === null,
          'desbloqueadas aparecem primeiro (' + primeira + ')', 'bloqueada aparece antes');

  // =========================================================
  secao('ALTERAR AVATAR');
  await pg.click('#btnAvatar');
  confere(await pg.isVisible('#dialogoAvatar'), 'janela de avatar abre', 'janela não abriu');
  await pg.check('input[name=avatar][value=retrato]', { force: true });
  await pg.click('#dialogoAvatar button[type=submit]');
  await pg.waitForLoadState();
  confere((await pg.getAttribute('.cartao-usuario .avatar', 'src')).includes('raposa-retrato'),
          'avatar novo na barra lateral', 'barra lateral com avatar antigo');
  confere(sql('SELECT foto_avatar FROM usuarios WHERE id = ' + uid) === 'retrato', 'gravado como código "retrato"', 'não gravou');

  const token = await pg.getAttribute('input[name=csrf]', 'value');
  let r = await pg.request.post(BASE + '/perfil.php', { form: { csrf: token, avatar: '../../config/conexao.php' } });
  confere((await r.text()).includes('Avatar inválido') && sql('SELECT foto_avatar FROM usuarios WHERE id = ' + uid) === 'retrato',
          'caminho de arquivo no lugar do código é recusado', 'aceitou avatar fora do catálogo');
  r = await pg.request.post(BASE + '/perfil.php', { form: { avatar: 'padrao' } });
  confere(r.status() === 403, 'sem CSRF → 403', 'sem CSRF devolveu ' + r.status());

  // =========================================================
  secao('VER TODAS');
  await pg.goto(BASE + '/conquistas.php');
  const todas = await pg.$$('.conquista');
  const catalogo = parseInt(sql('SELECT COUNT(*) FROM conquistas'), 10);
  confere(todas.length === catalogo, 'conquistas.php lista as ' + catalogo, todas.length + ' de ' + catalogo);

  await pg.goto(BASE + '/atividades.php');
  const pag1 = await pg.$$('.lista-eventos li');
  confere(pag1.length === 20 && await pg.$('a[href="atividades.php?pagina=2"]') !== null,
          'histórico: 20 por página, com link para a próxima', pag1.length + ' eventos na página 1');
  await pg.goto(BASE + '/atividades.php?pagina=2');
  const pag2 = await pg.$$('.lista-eventos li');
  confere(pag2.length > 0, 'página 2 tem os mais antigos (' + pag2.length + ')', 'página 2 vazia');
  confere((await pg.textContent('.lista-eventos')).includes('Iniciou nova trilha'),
          'histórico inclui "Iniciou nova trilha"', 'sem evento de trilha iniciada');

  // =========================================================
  secao('JAVASCRIPT');
  confere(errosJs.length === 0, 'nenhum erro de JavaScript', 'erros: ' + errosJs.join(' | '));

  await nav.close();
  console.log('\n' + (falhas === 0 ? '>>> TODOS OS TESTES PASSARAM' : '>>> ' + falhas + ' TESTE(S) FALHARAM'));
  process.exit(falhas === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(1); });
