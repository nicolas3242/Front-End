/**
 * teste-menores.js — os detalhes menores da auditoria de 21/09/2026.
 * -----------------------------------------------------------------------------
 * Ferramenta de verificação; não é entregue ao aluno.
 *
 *   - plural certo ("Falta 1 moeda", "1 de 1 questão"...);
 *   - "Rodar código" mantém o ícone depois de rodar;
 *   - menu do celular: os links continuam com nome para leitor de tela;
 *   - contraste do botão principal e do texto apagado (WCAG AA, 4,5:1);
 *   - Perfil: um "Ver todas" só no painel de atividade; 8 avatares, nenhum
 *     repetido, todos carregam;
 *   - vídeo da aula: só YouTube (javascript: é recusado e não vira iframe).
 *
 * ⚠ MEXE NO BANCO. Rode num banco de desenvolvimento recém-importado.
 */

const { chromium } = require('playwright');
const { execSync } = require('child_process');

const BASE  = process.env.DEVFOX_URL   || 'http://127.0.0.1:8080';
const MYSQL = process.env.DEVFOX_MYSQL || 'mysql -u root devfox';

let falhas = 0;
const ok      = t => console.log('  ✔ ' + t);
const falha   = t => { console.log('  ✘ FALHOU: ' + t); falhas++; };
const secao   = t => console.log('\n' + t);
const confere = (cond, bom, ruim) => cond ? ok(bom) : falha(ruim);
const sql     = q => execSync(MYSQL + ' -N -B -e "' + q.replace(/"/g, '\\"') + '"').toString().trim();

/** Razão de contraste WCAG entre duas cores "rgb(r, g, b)". */
function contraste(a, b) {
  const lum = c => {
    const [r, g, bb] = c.match(/\d+(\.\d+)?/g).slice(0, 3).map(Number).map(v => {
      v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * bb;
  };
  const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p);
  return (x + 0.05) / (y + 0.05);
}

async function entrar(pg, email, senha) {
  await pg.goto(BASE + '/login.php');
  await pg.fill('#email', email);
  await pg.fill('#senha', senha);
  await pg.click('button[type=submit]');
  await pg.waitForLoadState();
}

(async () => {
  const nav = await chromium.launch();
  const pg  = await (await nav.newContext()).newPage();
  const errosJs = [];
  pg.on('pageerror', e => errosJs.push(e.message));
  await entrar(pg, 'aluno@devfox.local', 'aluno123');

  // =========================================================
  secao('PLURAL');
  const preco = parseInt(sql('SELECT MIN(preco) FROM itens_loja WHERE ativo = 1'), 10);
  sql('UPDATE usuarios SET xp = ' + ((preco - 1) * 10) + " WHERE email = 'aluno@devfox.local'");   // 1 moeda = 10 XP
  await pg.goto(BASE + '/loja.php');
  const loja = await pg.textContent('body');
  confere(loja.includes('Falta 1 moeda') && !loja.includes('Faltam 1 moedas'), '"Falta 1 moeda" (antes "Faltam 1 moedas")',
          'plural errado na Loja');

  // =========================================================
  secao('BOTÃO "RODAR CÓDIGO"');
  const codigo = sql("SELECT a.id FROM atividades a JOIN modulos m ON m.id = a.modulo_id " +
                     "WHERE m.trilha_id = 1 AND a.tipo <> 'quiz' ORDER BY m.ordem, a.ordem, a.id LIMIT 1");
  await pg.goto(BASE + '/desafio.php?id=' + codigo);
  for (let i = 0; i < 2; i++) {
    await pg.click('#btnRodar');
    await pg.waitForFunction(() => !document.getElementById('btnRodar').disabled);
  }
  confere(await pg.$eval('#btnRodar', b => !!b.querySelector('svg') && b.textContent.trim() === 'Rodar código'),
          'depois de rodar duas vezes o ícone continua lá', 'o ícone sumiu do botão');

  // =========================================================
  secao('CONTRASTE (WCAG AA ≥ 4,5:1)');
  await pg.goto(BASE + '/trilhas.php');
  const [cor, fundo] = await pg.$eval('.botao-primario', b => [getComputedStyle(b).color, getComputedStyle(b).backgroundColor]);
  const cBotao = contraste(cor, fundo);
  confere(cBotao >= 4.5, 'botão principal: ' + cBotao.toFixed(2) + ':1', 'botão principal só ' + cBotao.toFixed(2) + ':1');
  const apagado = await pg.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--texto-apagado').trim());
  const hex2rgb = h => 'rgb(' + [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16)).join(', ') + ')';
  const piores = ['#181c27', '#212738'].map(f => contraste(hex2rgb(apagado), hex2rgb(f)));
  confere(piores.every(c => c >= 4.5), '--texto-apagado nos painéis: ' + piores.map(c => c.toFixed(2)).join(' / '),
          '--texto-apagado abaixo de 4,5: ' + piores.map(c => c.toFixed(2)).join(' / '));

  // =========================================================
  secao('MENU NO CELULAR');
  const cel = await (await nav.newContext({ viewport: { width: 390, height: 800 } })).newPage();
  await entrar(cel, 'aluno@devfox.local', 'aluno123');
  const nomes = await cel.$$eval('.menu .menu-item', ns => ns.map(n => n.innerText.trim() || n.textContent.trim()));
  const visiveis = await cel.$$eval('.menu .menu-item span', ns => ns.filter(s => s.getBoundingClientRect().width > 2).length);
  const nomesAcessiveis = await cel.$$eval('.menu .menu-item', ns => ns.map(n => n.textContent.trim()));
  confere(visiveis === 0 && nomesAcessiveis.every(t => t.length > 0),
          'no celular só aparecem ícones, mas os ' + nomesAcessiveis.length + ' itens têm nome (' + nomesAcessiveis.join(', ') + ')',
          'item sem nome acessível: ' + JSON.stringify(nomes));
  const topos = await cel.$$eval('.menu .menu-item', ns => [...new Set(ns.map(n => Math.round(n.getBoundingClientRect().top)))]);
  confere(topos.length === 1, 'todos os ícones numa linha só (o "Sair" não cai para baixo)', 'menu em ' + topos.length + ' linhas');

  // =========================================================
  secao('PERFIL');
  await pg.goto(BASE + '/perfil.php');
  const verTodas = await pg.$$eval('a[href="atividades.php"]', ns => ns.length);
  confere(verTodas === 1, 'um só "Ver todas" para o histórico', verTodas + ' links para o histórico');
  const avatares = await pg.$$eval('#dialogoAvatar input[name=avatar]', ns => ns.map(n => n.value));
  const imgs = await pg.$$eval('#dialogoAvatar img', ns => ns.map(n => n.getAttribute('src')));
  confere(avatares.length === 8 && new Set(imgs).size === imgs.length,
          '8 avatares, cada um com imagem própria (acabaram as 4 cópias recoloridas)', 'avatares: ' + JSON.stringify(imgs));
  let carregaram = 0;
  for (const src of imgs) if ((await pg.request.get(BASE + '/' + src)).ok()) carregaram++;
  confere(carregaram === imgs.length, 'todas as imagens de avatar carregam', carregaram + ' de ' + imgs.length);

  // =========================================================
  secao('VÍDEO DA AULA SÓ DO YOUTUBE');
  const adm = await (await nav.newContext()).newPage();
  await entrar(adm, 'admin@devfox.local', 'devfox123');
  const modulo = sql("SELECT id FROM modulos WHERE trilha_id = 1 AND tipo = 'aula' ORDER BY ordem, id LIMIT 1");
  for (const [valor, deveAceitar] of [['javascript:alert(1)', false], ['https://youtu.be/dQw4w9WgXcQ', true]]) {
    await adm.goto(BASE + '/admin_modulo.php?id=' + modulo);
    await adm.evaluate(() => { document.getElementById('url_video').type = 'text'; });
    await adm.fill('#url_video', valor);
    await adm.click('form:has(#url_video) button[type=submit]');
    await adm.waitForLoadState();
    const gravado = sql('SELECT COALESCE(url_video, \'\') FROM modulos WHERE id = ' + modulo);
    if (deveAceitar) {
      confere(gravado === 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
              'link youtu.be vira o endereço de incorporação', 'gravou ' + gravado);
    } else {
      confere(!gravado.startsWith('javascript') && (await adm.textContent('body')).includes('Vídeo inválido'),
              '"javascript:" é recusado', 'aceitou ' + gravado);
    }
  }
  // o que já estiver no banco também é conferido na hora de mostrar
  sql("UPDATE modulos SET url_video = 'javascript:alert(1)' WHERE id = " + modulo);
  await pg.goto(BASE + '/desafio.php?modulo=' + modulo);
  confere(!(await pg.$('.caixa-video iframe')), 'endereço perigoso gravado direto no banco não vira iframe',
          'iframe com javascript: na página');

  // =========================================================
  secao('JAVASCRIPT');
  confere(errosJs.length === 0, 'nenhum erro de JavaScript', 'erros: ' + errosJs.join(' | '));

  await nav.close();
  console.log('\n' + (falhas === 0 ? '>>> TODOS OS TESTES PASSARAM' : '>>> ' + falhas + ' TESTE(S) FALHARAM'));
  process.exit(falhas === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(1); });
