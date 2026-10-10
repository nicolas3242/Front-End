/**
 * Teste end-to-end da progressão sequencial e da prova de fim de nível.
 * Ferramenta de verificação — não é entregue ao aluno.
 */
const { chromium } = require('playwright');
const { concluirModulo, resolverAtual, esperarFeedback, sair, responderProva } = require('./ajudantes_teste');

const { execSync } = require('child_process');

const BASE  = process.env.DEVFOX_URL   || 'http://127.0.0.1:8080';
const MYSQL = process.env.DEVFOX_MYSQL || 'mysql -u root devfox';
const sql   = q => execSync(MYSQL + ' -N -B -e "' + q.replace(/"/g, '\\"') + '"').toString().trim();

let falhas = 0;
const ok    = t => console.log('  ✔ ' + t);
const falha = t => { console.log('  ✘ FALHOU: ' + t); falhas++; };
const secao = t => console.log('\n' + t);


(async () => {
  const navegador = await chromium.launch();
  const ctx = await navegador.newContext();
  const pg = await ctx.newPage();
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

  /** Conclui um módulo inteiro (ver tools/ajudantes_teste.js). */
  const modulo = (id) => concluirModulo(pg, BASE, id);

  /** Estado dos módulos da trilha 1, lido da tela de trilhas. */
  async function caminho() {
    await pg.goto(BASE + '/trilhas.php');
    return pg.$$eval('.linha-trilha:first-child .passo', ns => ns.map(n => ({
      titulo: n.querySelector('a, span:not(.passo-marca):not(.selo)')?.textContent.trim(),
      estado: n.className.match(/passo-(concluido|atual|bloqueado)/)?.[1],
      temLink: !!n.querySelector('a'),
    })));
  }

  // =========================================================
  secao('ALUNO NOVO — só o primeiro módulo liberado');
  await pg.goto(BASE + '/cadastro.php');
  await pg.fill('#nome', 'Testador Prova');
  await pg.fill('#email', 'prova@teste.local');
  await pg.fill('#senha', 'senha123');
  await pg.click('button[type=submit]');
  await pg.waitForTimeout(2500);
  await entrar('prova@teste.local', 'senha123');

  let c = await caminho();
  console.log('    caminho: ' + JSON.stringify(c.map(m => m.estado)));
  c[0].estado === 'atual' ? ok('módulo 1 liberado') : falha('módulo 1 deveria estar liberado: ' + c[0].estado);
  c.slice(1).every(m => m.estado === 'bloqueado')
    ? ok('demais módulos bloqueados') : falha('módulos deveriam estar bloqueados');
  c.slice(1).every(m => !m.temLink) ? ok('módulo bloqueado não é clicável') : falha('bloqueado virou link');

  // =========================================================
  secao('BLOQUEIO POR URL — não adianta digitar o endereço');
  await pg.goto(BASE + '/desafio.php?modulo=3');   // módulo 3 ainda bloqueado
  let txt = await pg.textContent('body');
  txt.includes('bloqueado') ? ok('acesso direto a módulo bloqueado é barrado') : falha('módulo bloqueado abriu pela URL');

  await pg.goto(BASE + '/prova.php?modulo=5');
  txt = await pg.textContent('body');
  txt.includes('bloqueada') ? ok('prova bloqueada antes de concluir a trilha') : falha('prova abriu cedo demais');

  // =========================================================
  secao('QUESTÃO DE PROVA NÃO É RESOLVIDA AVULSA');
  await pg.goto(BASE + '/desafio.php?modulo=5');   // módulo de prova
  pg.url().includes('prova.php')
    ? ok('questão de prova redireciona para a prova (' + pg.url().split('/').pop() + ')')
    : falha('questão de prova abriu como atividade avulsa: ' + pg.url());

  // =========================================================
  secao('LIBERAÇÃO PROGRESSIVA');
  await modulo(1);

  c = await caminho();
  (c[0].estado === 'concluido' && c[1].estado === 'atual')
    ? ok('concluir o módulo 1 liberou o módulo 2')
    : falha('liberação não ocorreu: ' + JSON.stringify(c.map(m => m.estado)));

  await modulo(2);
  await modulo(3);
  await modulo(4);

  c = await caminho();
  const prova = c[c.length - 1];
  prova.estado === 'atual' ? ok('prova liberada após concluir todos os módulos') : falha('prova não liberou: ' + prova.estado);

  // =========================================================
  secao('PROVA — REPROVAÇÃO (3 de 5 = 60%, mínimo 70%)');
  await pg.goto(BASE + '/prova.php?modulo=5');
  // Erra de propósito duas questões -> 3 de 5 = 60%, abaixo dos 70% exigidos.
  await responderProva(pg, 2);

  const pct = await pg.textContent('.resultado-percentual');
  pct.trim() === '60%' ? ok('nota calculada corretamente: ' + pct.trim()) : falha('nota errada: ' + pct);

  txt = await pg.textContent('body');
  txt.includes('Ainda não foi dessa vez') ? ok('reprovado corretamente') : falha('deveria reprovar');
  txt.includes('não perdeu nenhum progresso') ? ok('avisa que o progresso foi mantido') : falha('faltou o aviso de progresso');

  const rever = await pg.$$eval('.rever-modulos li a', ns => ns.map(n => n.textContent.trim()));
  rever.length > 0 ? ok('indica o que revisar: ' + JSON.stringify(rever)) : falha('não indicou módulos para revisar');

  // progresso preservado?
  c = await caminho();
  c.slice(0, 3).every(m => m.estado === 'concluido')
    ? ok('módulos concluídos continuam concluídos após reprovar')
    : falha('reprovar apagou progresso: ' + JSON.stringify(c.map(m => m.estado)));

  // =========================================================
  secao('PROVA — ESPERA DEPOIS DE REPROVAR (tentativa e erro não compensa)');
  await pg.goto(BASE + '/prova.php?modulo=5');
  (await pg.$('#esperaProva')) && !(await pg.$('.form-prova'))
    ? ok('logo depois de reprovar a prova não abre: pede para revisar antes')
    : falha('a prova abriu de novo sem espera');
  // POST direto (outra aba, formulário antigo) também não corrige durante a espera.
  const token = await pg.getAttribute('meta[name="csrf"]', 'content');
  const antes = sql('SELECT COUNT(*) FROM tentativas_prova');
  await pg.request.post(BASE + '/prova.php', { form: { csrf: token, modulo_id: '5' } });
  sql('SELECT COUNT(*) FROM tentativas_prova') === antes
    ? ok('entregar pelo POST durante a espera não registra tentativa')
    : falha('POST durante a espera foi corrigido');
  let r = await pg.request.post(BASE + '/prova.php', { form: { modulo_id: '5' } });
  r.status() === 403 ? ok('entrega da prova sem token CSRF → 403') : falha('prova sem CSRF devolveu ' + r.status());

  // O teste não espera 5 minutos: "envelhece" a tentativa no banco.
  sql('UPDATE tentativas_prova SET realizada_em = realizada_em - INTERVAL 10 MINUTE');

  // =========================================================
  secao('PROVA — APROVAÇÃO');
  await pg.goto(BASE + '/prova.php?modulo=5');
  await responderProva(pg, 0);

  txt = await pg.textContent('body');
  const pct2 = (await pg.textContent('.resultado-percentual')).trim();
  (pct2 === '100%' && txt.includes('Aprovado'))
    ? ok('aprovado com ' + pct2)
    : falha('deveria aprovar com 100% mas veio ' + pct2 +
            ' — o gabarito vem do banco; confira a prova da trilha 1');

  c = await caminho();
  c[c.length - 1].estado === 'concluido' ? ok('prova marcada como concluída na trilha') : falha('prova não concluiu');

  // =========================================================
  secao('XP DA PROVA NÃO PODE SER FARMADO');
  await pg.goto(BASE + '/perfil.php');
  const xpAntes = parseInt((await pg.textContent('.pilula-xp')).replace(/\D/g, ''), 10);

  await pg.goto(BASE + '/prova.php?modulo=5');
  (await pg.$('.form-prova'))
    ? ok('depois de APROVADO, refazer não tem espera')
    : falha('aprovado ficou preso na espera');
  await responderProva(pg, 0);

  await pg.goto(BASE + '/perfil.php');
  const xpDepois = parseInt((await pg.textContent('.pilula-xp')).replace(/\D/g, ''), 10);
  xpAntes === xpDepois
    ? ok('refazer a prova aprovado não concedeu XP de novo (' + xpAntes + ' XP)')
    : falha('XP subiu ao refazer: ' + xpAntes + ' → ' + xpDepois);

  // =========================================================
  secao('ADMINISTRAÇÃO (antigo painel do professor)');
  await pg.goto(BASE + '/administracao.php');
  pg.url().includes('inicio.php') ? ok('aluno é barrado na Administração') : falha('aluno entrou na Administração');

  await entrar('admin@devfox.local', 'devfox123');
  await pg.goto(BASE + '/administracao.php');
  txt = await pg.textContent('body');
  (pg.url().includes('administracao.php') && txt.includes('Administração'))
    ? ok('admin acessa a Administração') : falha('admin não acessou: ' + pg.url());

  const menu = await pg.textContent('.barra-lateral');
  (!menu.includes('Professor') && menu.includes('Administração'))
    ? ok('menu não fala mais em "Professor"') : falha('menu ainda menciona Professor');

  // =========================================================
  secao('ERROS DE JAVASCRIPT');
  errosJs.length === 0 ? ok('nenhum erro de JS') : falha('erros: ' + JSON.stringify(errosJs));

  await navegador.close();
  console.log('\n' + (falhas === 0 ? '>>> TODOS OS TESTES PASSARAM' : '>>> ' + falhas + ' TESTE(S) FALHARAM'));
  process.exit(falhas === 0 ? 0 : 1);
})();
