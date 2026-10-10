/**
 * Teste end-to-end do motor de correção (DEV-6), em Chromium de verdade.
 * Não é entregue ao aluno — é ferramenta de verificação.
 */
const { chromium } = require('playwright');
const { concluirModulo, rodar: rodarCodigo } = require('./ajudantes_teste');

const BASE = process.env.DEVFOX_URL || 'http://127.0.0.1:8080';

/**
 * Estes testes precisam de atividades ESPECÍFICAS (uma com caso oculto, uma que
 * devolva texto fixo, um quiz). Os ids abaixo vêm do conteúdo de
 * database/banco.sql e mudam quando o conteúdo muda — por isso cada um é
 * conferido pelo título antes do teste rodar, para a falha dizer exatamente o
 * que aconteceu em vez de quebrar lá na frente sem explicação.
 */
const QUIZ_LET   = 1;   // "Declarando uma variável"
const SOMA       = 6;   // "Soma de Dois Números"   — 2 casos visíveis + 1 oculto
const PAR_IMPAR  = 7;   // "Par ou Ímpar"           — ehPar(4)/ehPar(7), oculto ehPar(0)
const SAUDACAO   = 23;  // "Sua primeira mensagem"  — devolve "Olá, DevFox!"
let falhas = 0;

function ok(t)    { console.log('  ✔ ' + t); }
function falha(t) { console.log('  ✘ FALHOU: ' + t); falhas++; }
function secao(t) { console.log('\n' + t); }

(async () => {
  const navegador = await chromium.launch();
  const ctx = await navegador.newContext();
  const pg = await ctx.newPage();

  const errosConsole = [];
  pg.on('pageerror', e => errosConsole.push(e.message));

  // --- login ---
  await pg.goto(BASE + '/login.php');
  await pg.fill('#email', 'aluno@devfox.local');
  await pg.fill('#senha', 'aluno123');
  await pg.click('button[type=submit]');
  await pg.waitForURL(/(inicio|administracao)\.php/)   // o admin cai na Administração;
  secao('LOGIN');
  ok('autenticado');

  // Com a progressao sequencial, os modulos 2 e 3 so liberam depois do 1.
  // Resolve o modulo 1 para os testes seguintes alcancarem as atividades.
  const feitas = await concluirModulo(pg, BASE, 1);
  ok('modulo 1 concluido, ' + feitas + ' atividades (destrava os proximos)');

  // =========================================================
  secao('OS IDS DESTE TESTE AINDA APONTAM PARA AS ATIVIDADES CERTAS');
  for (const [id, esperado] of [[QUIZ_LET, 'Declarando uma variável'],
                                [SOMA, 'Soma de Dois Números'],
                                [PAR_IMPAR, 'Par ou Ímpar'],
                                [SAUDACAO, 'Sua primeira mensagem']]) {
    await pg.goto(BASE + '/desafio.php?id=' + id);
    const titulo = (await pg.textContent('.exercicio-titulo').catch(() => '')) || '';
    titulo.trim() === esperado
      ? ok('#' + id + ' = "' + esperado + '"')
      : falha('#' + id + ' deveria ser "' + esperado + '" mas abriu "' + titulo.trim() +
              '" — o conteudo mudou, atualize a tabela de ids no topo deste arquivo');
  }

  /** Escreve código no editor e clica em Rodar (ver tools/ajudantes_teste.js). */
  const rodar = (codigo) => rodarCodigo(pg, codigo);

  async function enviar() {
    await pg.click('button[type=submit]');
    await pg.waitForFunction(() => {
      const f = document.getElementById('feedbackMsg');
      return f && f.textContent.trim() !== '' && !f.textContent.includes('Executando') && !f.textContent.includes('Conferindo');
    }, { timeout: 15000 });
    return pg.textContent('#feedbackMsg');
  }

  const xp = async () => {
    const t = await pg.textContent('.pilula-xp');
    return parseInt(t.replace(/\D/g, ''), 10);
  };

  // =========================================================
  secao('ATIVIDADE 3 — Soma de Dois Numeros (2 visiveis + 1 oculto)');
  await pg.goto(BASE + '/desafio.php?id=' + SOMA);

  // o payload entregue ao JS pode conter SO id/entrada/oculto
  const payload = JSON.parse(await pg.textContent('#dadosCasos'));
  const chaves = [...new Set(payload.flatMap(c => Object.keys(c)))].sort();
  JSON.stringify(chaves) === '["entrada","id","oculto"]'
    ? ok('payload do navegador tem apenas ' + JSON.stringify(chaves) + ' — nenhum gabarito')
    : falha('payload expoe campos demais: ' + JSON.stringify(chaves));
  const gabaritoOculto = await (async () => {
    // saida esperada do caso oculto da atividade 3 e "6"; ele nao pode aparecer no payload
    return JSON.stringify(payload).includes('"6"');
  })();
  gabaritoOculto ? falha('gabarito do caso oculto vazou') : ok('gabarito do caso oculto nao esta no payload');

  // --- erro de sintaxe ---
  await rodar('function somar(a, b) { return a + ; }');
  let fb = await pg.textContent('#feedbackMsg');
  fb.includes('erro') || fb.includes('executou')
    ? ok('erro de sintaxe reportado sem quebrar a pagina')
    : falha('erro de sintaxe nao reportado: ' + fb);

  // --- codigo correto ---
  const xpAntes = await xp();
  await rodar('function somar(a, b) { return a + b; }');
  const saidas = await pg.$$eval('.resultado-saida', ns => ns.map(n => n.textContent));
  (saidas.includes('15') && saidas.includes('7'))
    ? ok('saidas dos casos visiveis corretas: ' + JSON.stringify(saidas))
    : falha('saidas inesperadas: ' + JSON.stringify(saidas));

  const ocultoNaTela = await pg.$$eval('.resultado-neutro', ns => ns.length);
  ocultoNaTela === 1 ? ok('caso oculto executado sem revelar valores') : falha('caso oculto exibido de forma errada');

  fb = await enviar();
  fb.includes('Todos os casos passaram') ? ok('servidor aprovou: ' + fb) : falha('servidor recusou: ' + fb);

  await pg.waitForTimeout(300);
  const marcas = await pg.$$eval('.linha-resultado.caso-passou', ns => ns.length);
  marcas === 3 ? ok('3 casos marcados como aprovados na tela') : falha('marcacao de casos errada: ' + marcas);

  // =========================================================
  secao('O TESTE QUE MAIS IMPORTA — burlar com resposta chumbada');
  await pg.goto(BASE + '/desafio.php?id=' + PAR_IMPAR);

  // passa nos visiveis olhando a tela, mas erra o oculto
  await rodar('function ehPar(n) { if (n === 4) return true; return false; }');
  fb = await enviar();
  const aprovou = fb.includes('Todos os casos passaram');
  (!aprovou && fb.includes('oculto'))
    ? ok('chumbar os casos visiveis NAO passa: "' + fb + '"')
    : falha('fraude por chumbo passou: ' + fb);

  // agora a solucao de verdade
  await rodar('function ehPar(n) { return n % 2 === 0; }');
  fb = await enviar();
  fb.includes('Todos os casos passaram') ? ok('solucao correta aprovada') : falha('solucao correta recusada: ' + fb);

  // =========================================================
  secao('LACO INFINITO');
  await pg.goto(BASE + '/desafio.php?id=' + SAUDACAO);
  const t0 = Date.now();
  await rodar('function saudacao() { while (true) {} }');
  const levou = Date.now() - t0;
  fb = await pg.textContent('#feedbackMsg');
  fb.includes('demorou demais') ? ok('laco infinito interrompido em ' + levou + 'ms') : falha('timeout nao acionou: ' + fb);
  await pg.click('#btnRodar');  // pagina ainda responde?
  await pg.waitForTimeout(200);
  ok('pagina continua respondendo depois do laco infinito');

  // =========================================================
  secao('ANTI-FARM continua valendo');
  const SOLUCAO6 = 'function saudacao() { return "Olá, DevFox!"; }';

  // primeira conclusao (deve pagar XP)
  await pg.goto(BASE + '/desafio.php?id=' + SAUDACAO);
  await rodar(SOLUCAO6);
  fb = await enviar();
  fb.includes('ganhou') ? ok('primeira conclusao concedeu XP') : falha('primeira conclusao nao pagou XP: ' + fb);

  // XP consolidado, lido de pagina recarregada
  await pg.goto(BASE + '/perfil.php');
  const xpDepoisDaPrimeira = await xp();

  // reenvios (nao podem pagar nada)
  for (let i = 0; i < 3; i++) {
    await pg.goto(BASE + '/desafio.php?id=' + SAUDACAO);
    await rodar(SOLUCAO6);
    fb = await enviar();
  }
  fb.includes('já havia concluído') ? ok('reenvio avisa que ja estava concluida') : falha('mensagem de reenvio errada: ' + fb);

  await pg.goto(BASE + '/perfil.php');
  const xpDepoisDosReenvios = await xp();
  xpDepoisDaPrimeira === xpDepoisDosReenvios
    ? ok('3 reenvios nao concederam XP algum (' + xpDepoisDaPrimeira + ' XP antes e depois)')
    : falha('XP subiu no reenvio: ' + xpDepoisDaPrimeira + ' -> ' + xpDepoisDosReenvios);

  // =========================================================
  secao('FRAUDE VIA POST FORJADO (sem navegador)');
  const forjado = await pg.evaluate(async (idAtividade) => {
    const d = new FormData();
    d.append('atividade_id', String(idAtividade));
    // manda "passou" com saidas inventadas
    d.append('resultados', JSON.stringify([
      { caso_id: 999, saida: 'qualquer coisa', erro: null, passou: true }
    ]));
    d.append('codigo', 'nao resolvi nada');
    // Com o token certo: o que se testa aqui é o SERVIDOR julgando, não o CSRF.
    d.append('csrf', document.querySelector('meta[name="csrf"]').content);
    const r = await fetch('api/processar_desafio.php', { method: 'POST', body: d });
    return r.json();
  }, SOMA);
  forjado.sucesso === false ? ok('POST forjado recusado: "' + forjado.mensagem + '"') : falha('POST forjado foi aceito!');

  // =========================================================
  secao('QUIZ continua funcionando');
  await pg.goto(BASE + '/desafio.php?id=' + QUIZ_LET);
  await pg.check('.alternativa input[type=radio] >> nth=1');   // "let"
  fb = await enviar();
  fb.includes('passaram') ? ok('quiz aprovado: ' + fb) : falha('quiz falhou: ' + fb);

  // =========================================================
  secao('ERROS DE JAVASCRIPT NA PAGINA');
  errosConsole.length === 0 ? ok('nenhum erro de JS') : falha('erros: ' + JSON.stringify(errosConsole));

  await navegador.close();
  console.log('\n' + (falhas === 0 ? '>>> TODOS OS TESTES PASSARAM' : '>>> ' + falhas + ' TESTE(S) FALHARAM'));
  process.exit(falhas === 0 ? 0 : 1);
})();
