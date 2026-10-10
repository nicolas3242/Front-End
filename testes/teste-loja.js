/**
 * teste-loja.js — a Loja resiste a quem tenta tirar vantagem dela?
 * -----------------------------------------------------------------------------
 * Ferramenta de verificação; não é entregue ao aluno.
 *
 * Mostrar a tela é o fácil. O que este teste prova:
 *   - moeda só vem de XP, e XP repetido não gera moeda repetida;
 *   - o preço vem do banco (um "preco=0" forjado no POST é ignorado);
 *   - não dá para comprar sem saldo, nem comprar duas vezes;
 *   - não dá para equipar item que não comprou;
 *   - DUAS SESSÕES comprando ao mesmo tempo não gastam mais do que o saldo
 *     (a trava FOR UPDATE de comprarItem()). Para isso o servidor precisa de
 *     mais de um processo: rode com PHP_CLI_SERVER_WORKERS=4 php -S ...;
 *   - POST sem token CSRF é recusado.
 *
 * ⚠ Este teste MEXE NO BANCO (cria usuário e ajusta o XP dele pelo cliente
 *   mysql, para não depender de resolver 30 atividades). Rode num banco de
 *   desenvolvimento.
 */

const { chromium } = require('playwright');
const { execSync } = require('child_process');
const { resolverAtual } = require('./ajudantes_teste');

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
  const ctx = await navegador.newContext();
  const pg  = await ctx.newPage();
  const errosJs = [];
  pg.on('pageerror', e => errosJs.push(e.message));

  const email = 'loja' + Date.now() + '@teste.local';

  async function entrar(pagina) {
    await pagina.goto(BASE + '/login.php');
    await pagina.fill('#email', email);
    await pagina.fill('#senha', 'senha123');
    await pagina.click('button[type=submit]');
    await pagina.waitForURL(/(inicio|administracao)\.php/)   // o admin cai na Administração;
  }

  const numero = t => parseInt(String(t).replace(/\D/g, ''), 10);

  async function saldoNaTela(pagina = pg) {
    await pagina.goto(BASE + '/loja.php');
    return numero(await pagina.textContent('#saldoMoedas'));
  }

  async function xpNaTela() {
    return numero(await pg.textContent('.pilula-xp'));
  }

  /** POST forjado, fora dos botões da tela. Devolve o HTML final (após o redirect). */
  async function postar(pagina, campos, comToken = true) {
    await pagina.goto(BASE + '/loja.php');
    const token = await pagina.getAttribute('input[name=csrf]', 'value');
    const form = Object.assign(comToken ? { csrf: token } : {}, campos);
    const r = await pagina.request.post(BASE + '/loja.php', { form });
    return { status: r.status(), html: await r.text() };
  }

  const idDo = slug => parseInt(sql("SELECT id FROM itens_loja WHERE slug = '" + slug + "'"), 10);
  const idUsuario = () => parseInt(sql("SELECT id FROM usuarios WHERE email = '" + email + "'"), 10);
  const definirXp = xp => sql('UPDATE usuarios SET xp = ' + xp + ' WHERE id = ' + idUsuario());

  // =========================================================
  secao('ALUNO NOVO — Loja aberta, saldo zero');
  await pg.goto(BASE + '/cadastro.php');
  await pg.fill('#nome', 'Testador Loja');
  await pg.fill('#email', email);
  await pg.fill('#senha', 'senha123');
  await pg.click('button[type=submit]');
  await pg.waitForTimeout(2500);
  await entrar(pg);

  confere(await pg.$('a.menu-item[href="loja.php"]') !== null,
          'menu "Loja" virou link de verdade', 'menu da Loja ainda inativo');
  confere(await pg.$('.selo-embreve') === null,
          'selo "em breve" saiu do menu', 'selo "em breve" continua no menu');

  let saldo = await saldoNaTela();
  confere(saldo === 0, 'saldo inicial 0', 'saldo inicial ' + saldo);

  const itensNaTela = await pg.$$eval('.item-loja', n => n.length);
  confere(itensNaTela === 9, 'catálogo com 9 itens', 'catálogo com ' + itensNaTela);

  const compraveis = await pg.$$eval('.item-loja-acao button:not([disabled])', n => n.length);
  confere(compraveis === 0, 'sem saldo, todos os botões de compra desabilitados',
          compraveis + ' botão(ões) de compra habilitado(s) com saldo 0');

  // =========================================================
  secao('MOEDA VEM DO XP — e só uma vez');
  await pg.goto(BASE + '/desafio.php?modulo=1');
  await resolverAtual(pg);
  saldo = await saldoNaTela();                           // recarrega: a pílula de XP só
  const xp1 = await xpNaTela();                          // atualiza em página nova
  confere(xp1 > 0 && saldo === Math.floor(xp1 / 10),
          'resolveu 1 atividade: ' + xp1 + ' XP → ' + saldo + ' moedas',
          'XP ' + xp1 + ' deveria dar ' + Math.floor(xp1 / 10) + ' moedas, deu ' + saldo);

  // Reenviar a mesma atividade não pode render moeda de novo.
  const primeira = sql('SELECT MIN(atividade_id) FROM atividades_concluidas WHERE aluno_id = ' + idUsuario());
  await pg.goto(BASE + '/desafio.php?id=' + primeira);
  await resolverAtual(pg).catch(() => {});
  const saldoRepetido = await saldoNaTela();
  confere(saldoRepetido === saldo, 'refazer a mesma atividade não gera moeda',
          'saldo foi de ' + saldo + ' para ' + saldoRepetido + ' refazendo a atividade');

  // =========================================================
  secao('SEM SALDO — o servidor recusa, mesmo com POST forjado');
  definirXp(500);                                         // 50 moedas
  let r = await postar(pg, { acao: 'comprar', item_id: idDo('ouro') });
  confere(r.html.includes('Moedas insuficientes'), 'Moldura Ouro (250) recusada com 50 moedas',
          'compra sem saldo não foi recusada');
  confere(await saldoNaTela() === 50, 'saldo intacto após a recusa', 'saldo mudou após recusa');

  r = await postar(pg, { acao: 'comprar', item_id: idDo('ouro'), preco: 0, preco_pago: 0 });
  confere(r.html.includes('Moedas insuficientes'), '"preco=0" enviado no POST é ignorado',
          'o preço enviado pelo navegador foi aceito');

  r = await postar(pg, { acao: 'comprar', item_id: 999999 });
  confere(r.html.includes('não está à venda'), 'item inexistente recusado', 'item inexistente aceito');

  // =========================================================
  secao('COMPRA — desconta, equipa e aparece na tela');
  definirXp(1000);                                        // 100 moedas
  await pg.goto(BASE + '/loja.php');
  await pg.click('.item-loja[data-item="brasa"] button');
  await pg.waitForURL('**/loja.php');
  saldo = numero(await pg.textContent('#saldoMoedas'));
  confere(saldo === 40, 'Moldura Brasa (60): 100 → 40 moedas', 'saldo depois da compra: ' + saldo);
  confere(await pg.$('.barra-lateral .avatar.moldura-brasa') !== null,
          'moldura aparece no avatar da barra lateral', 'moldura não aplicada na barra lateral');
  confere(await pg.$('.item-loja[data-item="brasa"].item-loja-equipado') !== null,
          'item marcado como equipado na Loja', 'item não marcado como equipado');

  await pg.goto(BASE + '/ranking.php');
  confere(await pg.$('.linha-destacada .avatar.moldura-brasa') !== null,
          'moldura aparece no ranking', 'moldura não aparece no ranking');
  await pg.goto(BASE + '/perfil.php');
  confere(await pg.$('.cartao-perfil .avatar.moldura-brasa') !== null,
          'moldura aparece no perfil', 'moldura não aparece no perfil');

  r = await postar(pg, { acao: 'comprar', item_id: idDo('brasa') });
  confere(r.html.includes('já tem'), 'comprar o mesmo item de novo é recusado', 'compra repetida aceita');
  confere(await saldoNaTela() === 40, 'saldo não muda na compra repetida', 'compra repetida cobrou');
  const linhas = sql('SELECT COUNT(*) FROM compras WHERE usuario_id = ' + idUsuario());
  confere(linhas === '1', 'uma única linha em compras', linhas + ' linhas em compras');

  // =========================================================
  secao('EQUIPAR — só o que é seu');
  r = await postar(pg, { acao: 'equipar', item_id: idDo('neon') });
  confere(r.html.includes('só pode equipar'), 'equipar item não comprado é recusado',
          'equipou item que não comprou');
  confere(await pg.$('.barra-lateral .avatar.moldura-neon') === null,
          'avatar continua sem a moldura neon', 'moldura neon aplicada sem compra');

  r = await postar(pg, { acao: 'desequipar', categoria: 'moldura' });
  await pg.goto(BASE + '/loja.php');
  confere(await pg.$('.barra-lateral .avatar.moldura') === null, '"Tirar" remove a moldura',
          'moldura continua depois de tirar');

  r = await postar(pg, { acao: 'desequipar', categoria: 'xp = 99999, tipo' });
  confere(r.html.includes('Categoria inválida'), 'categoria forjada recusada (coluna vem de lista fixa)',
          'categoria forjada aceita');

  await pg.click('.item-loja[data-item="brasa"] button');
  await pg.waitForURL('**/loja.php');
  confere(await pg.$('.barra-lateral .avatar.moldura-brasa') !== null, 'reequipar pelo botão funciona',
          'reequipar falhou');

  // =========================================================
  secao('TÍTULO — substitui o do nível');
  definirXp(1000 + 800);                                  // 40 + 80 = 120 moedas
  await pg.goto(BASE + '/loja.php');
  await pg.click('.item-loja[data-item="cacador-de-bugs"] button');
  await pg.waitForURL('**/loja.php');
  const titulo = (await pg.textContent('.cartao-usuario-nome small')).trim();
  confere(titulo === 'Caçador de Bugs', 'título comprado aparece sob o nome',
          'título exibido: ' + titulo);

  // =========================================================
  secao('CORRIDA — duas sessões comprando ao mesmo tempo');
  // Gasto até aqui: 60 + 80 = 140. Com XP 2400 o saldo é 100: dá para UM dos
  // dois itens abaixo (60 e 80), nunca para os dois (140).
  definirXp(2400);
  const ctx2 = await navegador.newContext();
  const pg2  = await ctx2.newPage();
  await entrar(pg2);
  confere(await saldoNaTela(pg2) === 100, 'segunda sessão enxerga saldo 100', 'saldo inesperado na 2ª sessão');

  await pg.goto(BASE + '/loja.php');
  const t1 = await pg.getAttribute('input[name=csrf]', 'value');
  const t2 = await pg2.getAttribute('input[name=csrf]', 'value');
  await Promise.all([
    pg.request.post(BASE + '/loja.php',  { form: { csrf: t1, acao: 'comprar', item_id: idDo('oceano') } }),
    pg2.request.post(BASE + '/loja.php', { form: { csrf: t2, acao: 'comprar', item_id: idDo('floresta') } }),
  ]);
  const compradas = parseInt(sql("SELECT COUNT(*) FROM compras c JOIN itens_loja i ON i.id = c.item_id " +
    "WHERE c.usuario_id = " + idUsuario() + " AND i.slug IN ('oceano','floresta')"), 10);
  saldo = await saldoNaTela();
  confere(compradas === 1 && saldo >= 0,
          'só uma das duas compras passou (saldo final ' + saldo + ')',
          compradas + ' compras passaram; saldo final ' + saldo);
  await ctx2.close();

  // =========================================================
  secao('CSRF');
  r = await postar(pg, { acao: 'comprar', item_id: idDo('oceano') }, false);
  confere(r.status === 403, 'POST sem token → 403', 'POST sem token devolveu ' + r.status);

  // =========================================================
  secao('JAVASCRIPT');
  confere(errosJs.length === 0, 'nenhum erro de JavaScript', 'erros: ' + errosJs.join(' | '));

  await navegador.close();
  console.log('\n' + (falhas === 0 ? '>>> TODOS OS TESTES PASSARAM' : '>>> ' + falhas + ' TESTE(S) FALHARAM'));
  process.exit(falhas === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(1); });
