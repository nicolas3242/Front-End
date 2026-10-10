/**
 * teste-conta.js — "Lembrar-me", "Esqueci minha senha" e "Fale com o suporte".
 * -----------------------------------------------------------------------------
 * Ferramenta de verificação; não é entregue ao aluno.
 *
 * O que este teste prova:
 *   - Lembrar-me: só cria cookie com a caixa marcada; o cookie (HttpOnly) entra
 *     sozinho num navegador sem sessão; é trocado a cada uso (o antigo deixa de
 *     valer); cookie forjado é recusado e derruba os tokens do usuário; sair
 *     apaga o token.
 *   - Esqueci minha senha: mesma resposta para e-mail que existe e que não
 *     existe; o link funciona uma vez só, expira, e um pedido novo invalida o
 *     anterior; a senha antiga para de valer.
 *   - Suporte: mensagem chega na Administração; campo isca, limite de tamanho e
 *     espera entre envios funcionam; POST sem CSRF é recusado.
 *
 * ⚠ MEXE NO BANCO e lê logs/emails.log (EMAIL_MODO = 'log').
 */

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const { sair } = require('./ajudantes_teste');
const { execSync } = require('child_process');

const BASE  = process.env.DEVFOX_URL   || 'http://127.0.0.1:8080';
const MYSQL = process.env.DEVFOX_MYSQL || 'mysql -u root devfox';
const LOG   = process.env.DEVFOX_EMAILS || [
  path.join(__dirname, 'app', 'logs', 'emails.log'),
  path.join(__dirname, '..', 'logs', 'emails.log'),
].find(p => fs.existsSync(path.dirname(p)));

let falhas = 0;
const ok    = t => console.log('  ✔ ' + t);
const falha = t => { console.log('  ✘ FALHOU: ' + t); falhas++; };
const secao = t => console.log('\n' + t);
const confere = (cond, bom, ruim) => cond ? ok(bom) : falha(ruim);
const sql = q => execSync(MYSQL + ' -N -B -e "' + q.replace(/"/g, '\\"') + '"').toString().trim();

(async () => {
  const nav = await chromium.launch();
  const email = 'conta' + Date.now() + '@teste.local';
  let ctx = await nav.newContext();
  let pg = await ctx.newPage();

  await pg.goto(BASE + '/cadastro.php');
  await pg.fill('#nome', 'Testador Conta');
  await pg.fill('#email', email);
  await pg.fill('#senha', 'senha123');
  await pg.click('button[type=submit]');
  await pg.waitForTimeout(1500);
  const uid = sql("SELECT id FROM usuarios WHERE email = '" + email + "'");
  const tokens = () => parseInt(sql('SELECT COUNT(*) FROM lembrar_tokens WHERE usuario_id = ' + uid), 10);

  async function entrar(p, senha, lembrar) {
    await p.goto(BASE + '/login.php');
    await p.fill('#email', email);
    await p.fill('#senha', senha);
    if (lembrar) await p.check('input[name=lembrar]');
    await p.click('button[type=submit]');
    await p.waitForLoadState();
    return p.url();
  }
  // O PHP codifica o ":" do cookie como %3A; aqui se trabalha com o valor decodificado.
  const cookieLembrar = async c => {
    const k = (await c.cookies()).find(x => x.name === 'devfox_lembrar');
    return k ? Object.assign({}, k, { valor: decodeURIComponent(k.value) }) : undefined;
  };

  // =========================================================
  secao('LOGIN — os elementos do wireframe');
  await pg.goto(BASE + '/login.php');
  confere(await pg.$('input[name=lembrar]') !== null, 'caixa "Lembrar-me"', 'sem "Lembrar-me"');
  confere(await pg.$('a[href="esqueci_senha.php"]') !== null, 'link "Esqueci minha senha"', 'sem link de senha');
  confere(await pg.$('a[href="suporte.php"]') !== null, 'link "Fale com o suporte"', 'sem link de suporte');

  // =========================================================
  secao('LEMBRAR-ME');
  await entrar(pg, 'senha123', false);
  confere(!(await cookieLembrar(ctx)) && tokens() === 0, 'sem a caixa marcada, nenhum cookie é criado',
          'criou cookie sem a caixa marcada');
  await sair(pg, BASE);

  await entrar(pg, 'senha123', true);
  const c1 = await cookieLembrar(ctx);
  confere(c1 && c1.httpOnly && tokens() === 1, 'com a caixa: cookie HttpOnly e 1 token no banco',
          'cookie ausente ou legível por JavaScript');
  confere(!sql("SELECT validador_hash FROM lembrar_tokens WHERE usuario_id = " + uid).includes(c1.valor.split(':')[1]),
          'o banco guarda o hash, não o validador do cookie', 'validador em texto no banco!');

  // navegador novo, só com o cookie de lembrar (sem sessão)
  let ctx2 = await nav.newContext();
  await ctx2.addCookies([{ name: 'devfox_lembrar', value: c1.value, url: BASE }]);
  let pg2 = await ctx2.newPage();
  await pg2.goto(BASE + '/inicio.php');
  confere(pg2.url().endsWith('/inicio.php'), 'navegador sem sessão entra direto pelo cookie', 'cookie não logou: ' + pg2.url());
  const c2 = await cookieLembrar(ctx2);
  confere(c2 && c2.value !== c1.value && tokens() === 1, 'o token foi trocado no uso (rotação)', 'token não foi trocado');
  await ctx2.close();

  ctx2 = await nav.newContext();
  await ctx2.addCookies([{ name: 'devfox_lembrar', value: c1.value, url: BASE }]);
  pg2 = await ctx2.newPage();
  await pg2.goto(BASE + '/inicio.php');
  confere(pg2.url().endsWith('/login.php'), 'cookie antigo (já usado) não entra mais', 'cookie reutilizado entrou');
  await ctx2.close();

  const forjado = c2.valor.split(':')[0] + ':' + 'a'.repeat(64);
  ctx2 = await nav.newContext();
  await ctx2.addCookies([{ name: 'devfox_lembrar', value: forjado, url: BASE }]);
  pg2 = await ctx2.newPage();
  await pg2.goto(BASE + '/inicio.php');
  confere(pg2.url().endsWith('/login.php') && tokens() === 0,
          'validador forjado é recusado e derruba os tokens da conta', 'cookie forjado entrou ou tokens ficaram: ' + pg2.url() + ' tokens=' + tokens());
  await ctx2.close();

  await sair(pg, BASE);
  await entrar(pg, 'senha123', true);
  await sair(pg, BASE);
  confere(tokens() === 0 && !(await cookieLembrar(ctx)), 'sair apaga o token e o cookie', 'logout deixou token: tokens=' + tokens() + ' cookie=' + JSON.stringify(await cookieLembrar(ctx)));

  // =========================================================
  secao('ESQUECI MINHA SENHA');
  if (fs.existsSync(LOG)) fs.writeFileSync(LOG, '');
  await pg.goto(BASE + '/esqueci_senha.php');
  await pg.fill('#email', 'ninguem' + Date.now() + '@teste.local');
  await pg.click('button[type=submit]');
  const respostaInexistente = (await pg.textContent('.aviso-sucesso')).trim();

  await ctx.close(); ctx = await nav.newContext(); pg = await ctx.newPage();   // sessão nova (sem espera)
  await pg.goto(BASE + '/esqueci_senha.php');
  await pg.fill('#email', email);
  await pg.click('button[type=submit]');
  const respostaExistente = (await pg.textContent('.aviso-sucesso')).trim();
  confere(respostaExistente === respostaInexistente, 'mesma resposta para e-mail existente e inexistente',
          'as respostas diferem — dá para descobrir quem tem conta');

  await pg.goto(BASE + '/esqueci_senha.php');
  await pg.fill('#email', email);
  await pg.click('button[type=submit]');
  confere((await pg.textContent('body')).includes('Aguarde'), 'segundo pedido em seguida precisa esperar', 'sem espera entre pedidos');

  const log = fs.readFileSync(LOG, 'utf8');
  const links = [...log.matchAll(/redefinir_senha\.php\?token=([a-f0-9]{64})/g)].map(m => m[1]);
  confere(links.length === 1 && log.includes('Para: ' + email) && !log.includes('ninguem'),
          'só o e-mail cadastrado recebeu o link', 'links no log: ' + links.length);
  const token1 = links[0];
  confere(!sql("SELECT token_hash FROM redefinicoes_senha WHERE usuario_id = " + uid).includes(token1),
          'o banco guarda o hash do token, não o token', 'token em texto no banco!');

  await pg.goto(BASE + '/redefinir_senha.php?token=' + 'b'.repeat(64));
  confere((await pg.textContent('h1')).includes('Link inválido'), 'token inventado é recusado', 'token inventado aceito');

  // pedido novo invalida o anterior
  await ctx.close(); ctx = await nav.newContext(); pg = await ctx.newPage();
  await pg.goto(BASE + '/esqueci_senha.php');
  await pg.fill('#email', email);
  await pg.click('button[type=submit]');
  const token2 = [...fs.readFileSync(LOG, 'utf8').matchAll(/token=([a-f0-9]{64})/g)].map(m => m[1]).pop();
  await pg.goto(BASE + '/redefinir_senha.php?token=' + token1);
  confere((await pg.textContent('h1')).includes('Link inválido'), 'pedir de novo invalida o link anterior', 'link antigo ainda vale');

  await pg.goto(BASE + '/redefinir_senha.php?token=' + token2);
  await pg.fill('#senha', 'novaSenha9');
  await pg.fill('#confirmacao', 'outraCoisa');
  await pg.click('button[type=submit]');
  confere((await pg.textContent('body')).includes('não é igual'), 'confirmação diferente é recusada', 'aceitou confirmação diferente');
  await pg.fill('#senha', 'novaSenha9');
  await pg.fill('#confirmacao', 'novaSenha9');
  await pg.click('button[type=submit]');
  await pg.waitForURL('**/login.php');
  confere((await pg.textContent('body')).includes('Senha alterada'), 'senha nova salva, volta ao login com aviso', 'não salvou');

  confere(!(await entrar(pg, 'senha123', false)).endsWith('/inicio.php'), 'senha antiga não entra mais', 'senha antiga entrou');
  confere((await entrar(pg, 'novaSenha9', false)).endsWith('/inicio.php'), 'senha nova entra', 'senha nova não entra');
  await pg.goto(BASE + '/redefinir_senha.php?token=' + token2);
  confere((await pg.textContent('h1')).includes('Link inválido'), 'o link não funciona duas vezes', 'link reutilizado');
  await sair(pg, BASE);

  // expirado
  await ctx.close(); ctx = await nav.newContext(); pg = await ctx.newPage();
  await pg.goto(BASE + '/esqueci_senha.php');
  await pg.fill('#email', email);
  await pg.click('button[type=submit]');
  const token3 = [...fs.readFileSync(LOG, 'utf8').matchAll(/token=([a-f0-9]{64})/g)].map(m => m[1]).pop();
  sql('UPDATE redefinicoes_senha SET expira_em = NOW() - INTERVAL 1 DAY WHERE usuario_id = ' + uid + ' AND usado_em IS NULL');
  await pg.goto(BASE + '/redefinir_senha.php?token=' + token3);
  confere((await pg.textContent('h1')).includes('Link inválido'), 'link vencido é recusado', 'link vencido aceito');

  const semToken = await pg.request.post(BASE + '/esqueci_senha.php', { form: { email } });
  confere(semToken.status() === 403, 'POST sem CSRF → 403', 'sem CSRF devolveu ' + semToken.status());

  // =========================================================
  secao('FALE COM O SUPORTE');
  await ctx.close(); ctx = await nav.newContext(); pg = await ctx.newPage();
  const antes = parseInt(sql('SELECT COUNT(*) FROM mensagens_suporte'), 10);

  await pg.goto(BASE + '/suporte.php');
  await pg.fill('#nome', 'Visitante');
  await pg.fill('#email', 'visitante@teste.local');
  await pg.fill('#mensagem', 'curta');
  await pg.click('button[type=submit]');
  confere((await pg.textContent('body')).includes('entre 10 e 2.000'), 'mensagem curta demais é recusada', 'aceitou mensagem curta');

  await pg.evaluate(() => { document.getElementById('site').value = 'http://spam'; });
  await pg.fill('#mensagem', 'Sou um robô tentando enviar spam pelo formulário.');
  await pg.click('button[type=submit]');
  confere(parseInt(sql('SELECT COUNT(*) FROM mensagens_suporte'), 10) === antes,
          'campo isca preenchido: finge sucesso e não grava', 'mensagem de robô gravada');

  await ctx.close(); ctx = await nav.newContext(); pg = await ctx.newPage();
  await pg.goto(BASE + '/suporte.php');
  await pg.fill('#nome', 'Visitante');
  await pg.fill('#email', 'visitante@teste.local');
  await pg.selectOption('#assunto', 'conta');
  await pg.fill('#mensagem', 'Não consigo entrar na minha conta desde ontem. <b>teste</b>');
  await pg.click('button[type=submit]');
  confere((await pg.textContent('body')).includes('Mensagem enviada') &&
          parseInt(sql('SELECT COUNT(*) FROM mensagens_suporte'), 10) === antes + 1,
          'mensagem de visitante gravada', 'mensagem não gravada');

  await pg.goto(BASE + '/suporte.php');
  await pg.fill('#nome', 'Visitante');
  await pg.fill('#email', 'visitante@teste.local');
  await pg.fill('#mensagem', 'Segunda mensagem logo em seguida, para testar.');
  await pg.click('button[type=submit]');
  confere((await pg.textContent('body')).includes('Aguarde'), 'segunda mensagem em seguida precisa esperar', 'sem espera');

  // admin lê
  await ctx.close(); ctx = await nav.newContext(); pg = await ctx.newPage();
  await pg.goto(BASE + '/login.php');
  await pg.fill('#email', 'admin@devfox.local');
  await pg.fill('#senha', 'devfox123');
  await pg.click('button[type=submit]');
  await pg.waitForURL(/(inicio|administracao)\.php/)   // o admin cai na Administração;
  await pg.goto(BASE + '/administracao.php');
  confere(await pg.$('a[href="admin_suporte.php"]') !== null, 'Administração mostra o cartão de Suporte', 'sem acesso ao suporte');
  await pg.goto(BASE + '/admin_suporte.php');
  const html = await pg.content();
  confere(html.includes('Não consigo entrar na minha conta') && html.includes('&lt;b&gt;teste&lt;/b&gt;'),
          'admin vê a mensagem, com HTML escapado', 'mensagem ausente ou HTML não escapado');
  await pg.click('.mensagem-nao-lida button[type=submit]');
  await pg.waitForLoadState();
  confere((await pg.textContent('body')).includes('marcada como lida'), 'marcar como lida funciona', 'não marcou');

  // aluno não entra
  await ctx.close(); ctx = await nav.newContext(); pg = await ctx.newPage();
  await entrar(pg, 'novaSenha9', false);
  await pg.goto(BASE + '/admin_suporte.php');
  confere(!pg.url().includes('admin_suporte'), 'aluno não abre a caixa de suporte', 'aluno abriu o suporte da equipe');

  await nav.close();
  console.log('\n' + (falhas === 0 ? '>>> TODOS OS TESTES PASSARAM' : '>>> ' + falhas + ' TESTE(S) FALHARAM'));
  process.exit(falhas === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(1); });
