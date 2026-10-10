/**
 * teste-inicio.js — Início, Desafios, Configurações e o sino.
 * -----------------------------------------------------------------------------
 * Ferramenta de verificação; não é entregue ao aluno.
 *
 * O que este teste prova:
 *   - o login leva ao Início, e os números do Início batem com o banco;
 *   - "Continuar aprendendo" leva ao próximo passo REAL do aluno;
 *   - "Desafios em destaque" e a tela Desafios nunca oferecem desafio de
 *     módulo bloqueado (e abrir pela URL continua barrado);
 *   - Configurações: senha atual errada, confirmação diferente e senha curta
 *     são recusadas; a troca certa funciona e a senha antiga para de valer;
 *   - o sino mostra eventos reais e o ponto some depois de aberto;
 *   - POST sem token CSRF é recusado.
 *
 * ⚠ MEXE NO BANCO (cria usuário, conclui atividades). Rode em desenvolvimento.
 */

const { chromium } = require('playwright');
const { execSync } = require('child_process');
const { resolverAtual, sair } = require('./ajudantes_teste');

const BASE  = process.env.DEVFOX_URL   || 'http://127.0.0.1:8080';
const MYSQL = process.env.DEVFOX_MYSQL || 'mysql -u root devfox';

let falhas = 0;
const ok    = t => console.log('  ✔ ' + t);
const falha = t => { console.log('  ✘ FALHOU: ' + t); falhas++; };
const secao = t => console.log('\n' + t);
const confere = (cond, bom, ruim) => cond ? ok(bom) : falha(ruim);
const numero = t => parseInt(String(t).replace(/\D/g, ''), 10);

function sql(consulta) {
  return execSync(MYSQL + ' -N -B -e "' + consulta.replace(/"/g, '\\"') + '"').toString().trim();
}

(async () => {
  const navegador = await chromium.launch();
  const ctx = await navegador.newContext();
  const pg  = await ctx.newPage();
  const errosJs = [];
  pg.on('pageerror', e => errosJs.push(e.message));

  const email = 'inicio' + Date.now() + '@teste.local';
  let senha = 'senha123';

  async function entrar(s = senha) {
    await pg.goto(BASE + '/login.php');
    await pg.fill('#email', email);
    await pg.fill('#senha', s);
    await pg.click('button[type=submit]');
    await pg.waitForLoadState();
    return pg.url();
  }

  async function postar(pagina, campos, comToken = true) {
    await pg.goto(BASE + '/' + pagina);
    const token = await pg.getAttribute('input[name=csrf]', 'value');
    const form = Object.assign(comToken ? { csrf: token } : {}, campos);
    const r = await pg.request.post(BASE + '/' + pagina, { form });
    return { status: r.status(), html: await r.text() };
  }

  // =========================================================
  secao('LOGIN LEVA AO INÍCIO');
  await pg.goto(BASE + '/cadastro.php');
  await pg.fill('#nome', 'Testador Inicio');
  await pg.fill('#email', email);
  await pg.fill('#senha', senha);
  await pg.click('button[type=submit]');
  await pg.waitForTimeout(2000);
  const url = await entrar();
  confere(url.endsWith('/inicio.php'), 'login redireciona para inicio.php', 'login foi para ' + url);

  const saudacao = (await pg.textContent('.topo-titulo h1')).trim();
  confere(saudacao === 'Olá, Testador!', 'saudação usa o primeiro nome', 'saudação: ' + saudacao);

  for (const bloco of ['.banner-inicio', '.grade-numeros-inicio', '.cartao-continuar',
                       '.mini-ranking', '.acesso-rapido']) {
    confere(await pg.$(bloco) !== null, 'bloco presente: ' + bloco, 'bloco ausente: ' + bloco);
  }
  const menu = await pg.$$eval('.menu .menu-item span', n => n.map(x => x.textContent.trim()));
  const esperado = ['Início', 'Trilhas', 'Desafios', 'Ranking', 'Loja', 'Perfil', 'Configurações', 'Sair'];
  confere(JSON.stringify(menu) === JSON.stringify(esperado), 'menu na ordem do wireframe',
          'menu: ' + menu.join(', '));

  // =========================================================
  secao('ALUNO NOVO — números zerados e primeiro passo');
  const cartoes = await pg.$$eval('.cartao-numero-inicio > strong', n => n.map(x => x.firstChild.textContent.trim()));
  confere(cartoes[0] === '1' && numero(cartoes[1]) === 0,
          'nível 1 e 0 XP para aluno novo', 'cartões: ' + JSON.stringify(cartoes));

  const hrefContinuar = await pg.getAttribute('#btnContinuar', 'href');
  confere(/desafio\.php\?id=\d+/.test(hrefContinuar), '"Começar" aponta para uma atividade (' + hrefContinuar + ')',
          'botão principal aponta para ' + hrefContinuar);

  const destaques = await pg.$$eval('.lista-desafios a', n => n.map(a => a.getAttribute('href')));
  confere(destaques.length === 3, '3 desafios em destaque', destaques.length + ' desafios em destaque');

  // Cada destaque precisa estar em módulo liberado: abrir não pode dar "bloqueado".
  let algumBloqueado = false;
  for (const href of destaques) {
    await pg.goto(BASE + '/' + href);
    if ((await pg.content()).includes('Módulo ainda bloqueado')) { algumBloqueado = true; }
  }
  confere(!algumBloqueado, 'nenhum destaque é de módulo bloqueado', 'destaque levou a módulo bloqueado');

  // =========================================================
  secao('PROGRESSO REFLETE NO INÍCIO');
  await pg.goto(BASE + '/' + hrefContinuar);        // 1ª atividade (pode ser quiz)
  await resolverAtual(pg);
  await pg.goto(BASE + '/' + destaques[0]);          // e um desafio de código
  await resolverAtual(pg);
  await pg.goto(BASE + '/inicio.php');
  const xpBanco = parseInt(sql("SELECT xp FROM usuarios WHERE email = '" + email + "'"), 10);
  const xpTela  = numero(await pg.$eval('.cartao-numero-inicio:nth-child(2) > strong', n => n.firstChild.textContent));
  confere(xpBanco > 0 && xpTela === xpBanco, 'cartão de XP = banco (' + xpBanco + ')',
          'XP na tela ' + xpTela + ', no banco ' + xpBanco);
  const novoHref = await pg.getAttribute('#btnContinuar', 'href');
  confere(novoHref !== hrefContinuar, 'botão principal avançou para o próximo passo',
          'botão continua apontando para a atividade já feita');
  confere((await pg.textContent('.cartao-continuar small')).includes('em andamento'),
          'trilha aparece como "em andamento"', 'rótulo da trilha não mudou');

  // =========================================================
  secao('DESAFIOS — lista não fura o bloqueio');
  await pg.goto(BASE + '/desafios.php');
  const bloqueados = await pg.$$('.linha-desafio-bloqueada');
  const linkEmBloqueado = await pg.$$('a.linha-desafio-bloqueada');
  confere(bloqueados.length > 0 && linkEmBloqueado.length === 0,
          bloqueados.length + ' desafios bloqueados, nenhum com link', 'desafio bloqueado com link');

  const contagens = await pg.$$eval('.filtro span', n => n.map(x => parseInt(x.textContent, 10)));
  confere(contagens[0] === contagens[1] + contagens[2] + contagens[3],
          'filtros somam: ' + contagens[0] + ' = ' + contagens.slice(1).join(' + '), 'contagem dos filtros não fecha: ' + contagens);
  confere(contagens[2] >= 1, 'o desafio resolvido aparece como concluído', 'nenhum concluído na lista');

  const idBloqueado = sql(
    "SELECT a.id FROM atividades a JOIN modulos m ON m.id = a.modulo_id " +
    "WHERE m.trilha_id = 1 AND m.ordem = 3 AND a.tipo <> 'quiz' LIMIT 1");
  await pg.goto(BASE + '/desafio.php?id=' + idBloqueado);
  confere((await pg.content()).includes('Módulo ainda bloqueado'),
          'abrir desafio bloqueado pela URL continua barrado', 'desafio bloqueado abriu pela URL');

  await pg.goto(BASE + '/desafios.php?filtro=<script>');
  confere(await pg.$('.filtro-ativo') !== null && !(await pg.content()).includes('<script>alert'),
          'filtro inválido na URL é ignorado', 'filtro inválido quebrou a tela');

  // =========================================================
  secao('CONFIGURAÇÕES');
  let r = await postar('configuracoes.php', { acao: 'senha', senha_atual: 'errada', senha_nova: 'nova1234', senha_confirmacao: 'nova1234' });
  confere(r.html.includes('senha atual não confere'), 'senha atual errada é recusada', 'aceitou senha atual errada');
  r = await postar('configuracoes.php', { acao: 'senha', senha_atual: senha, senha_nova: 'nova1234', senha_confirmacao: 'outra999' });
  confere(r.html.includes('confirmação não é igual'), 'confirmação diferente é recusada', 'aceitou confirmação diferente');
  r = await postar('configuracoes.php', { acao: 'senha', senha_atual: senha, senha_nova: '123', senha_confirmacao: '123' });
  confere(r.html.includes('pelo menos 6'), 'senha curta é recusada', 'aceitou senha curta');
  r = await postar('configuracoes.php', { acao: 'senha', senha_atual: senha, senha_nova: 'nova1234', senha_confirmacao: 'nova1234' }, false);
  confere(r.status === 403, 'troca de senha sem CSRF → 403', 'sem CSRF devolveu ' + r.status);

  r = await postar('configuracoes.php', { acao: 'senha', senha_atual: senha, senha_nova: 'nova1234', senha_confirmacao: 'nova1234' });
  confere(r.html.includes('Senha alterada'), 'troca de senha válida funciona', 'troca válida falhou');
  await sair(pg, BASE);
  confere(!(await entrar('senha123')).endsWith('/inicio.php'), 'senha antiga não entra mais', 'senha antiga ainda entra');
  senha = 'nova1234';
  confere((await entrar()).endsWith('/inicio.php'), 'senha nova entra', 'senha nova não entra');

  r = await postar('configuracoes.php', { acao: 'nome', nome: '  Maria   Clara  ' });
  await pg.goto(BASE + '/inicio.php');
  confere((await pg.textContent('.topo-titulo h1')).trim() === 'Olá, Maria!' &&
          (await pg.textContent('.cartao-usuario-nome strong')).trim() === 'Maria Clara',
          'nome novo aparece na saudação e na barra lateral (espaços normalizados)', 'nome não atualizou');
  r = await postar('configuracoes.php', { acao: 'nome', nome: 'X' });
  confere(r.html.includes('entre 2 e 100'), 'nome de 1 letra é recusado', 'aceitou nome de 1 letra');

  // =========================================================
  secao('SINO');
  await pg.evaluate(() => localStorage.clear());
  await pg.goto(BASE + '/inicio.php');
  const itens = await pg.$$eval('.item-sino strong', n => n.map(x => x.textContent.trim()));
  confere(itens.length >= 1, 'sino lista ' + itens.length + ' evento(s) reais', 'sino vazio após concluir atividade');
  confere(await pg.isVisible('.sino-ponto'), 'ponto de novidade aparece', 'ponto não apareceu');
  confere(!(await pg.isVisible('#painelSino')), 'painel começa fechado', 'painel começou aberto');
  await pg.click('#botaoSino');
  confere(await pg.isVisible('#painelSino') && !(await pg.isVisible('.sino-ponto')),
          'abrir o sino mostra o painel e apaga o ponto', 'abrir o sino não funcionou');
  await pg.keyboard.press('Escape');
  confere(!(await pg.isVisible('#painelSino')), 'Esc fecha o painel', 'Esc não fechou');
  await pg.reload();
  confere(!(await pg.isVisible('.sino-ponto')), 'ponto não volta depois de visto', 'ponto voltou após recarregar');

  // =========================================================
  secao('RANKING COM O ALUNO EM 1º');
  // Regressão: quando o aluno logado era o 1º, as linhas abaixo dele saíam
  // sem posição e o PHP imprimia "Warning: Undefined array key posicao".
  const uid = sql("SELECT id FROM usuarios WHERE email = '" + email + "'");
  sql('UPDATE usuarios SET xp = (SELECT m FROM (SELECT MAX(xp) + 1000 AS m FROM usuarios) t) WHERE id = ' + uid);
  await pg.goto(BASE + '/inicio.php');
  const html = await pg.content();
  const posicoes = await pg.$$eval('.mini-ranking .medalha', n => n.map(x => x.textContent.trim()));
  confere(!/Warning|Notice|Undefined/.test(html), 'Início sem aviso do PHP com o aluno em 1º', 'PHP imprimiu aviso na página');
  confere(posicoes.length >= 2 && posicoes.every((p, i) => p === String(i + 1)),
          'mini-ranking numerado 1..' + posicoes.length, 'posições: ' + JSON.stringify(posicoes));

  // =========================================================
  secao('JAVASCRIPT');
  confere(errosJs.length === 0, 'nenhum erro de JavaScript', 'erros: ' + errosJs.join(' | '));

  await navegador.close();
  console.log('\n' + (falhas === 0 ? '>>> TODOS OS TESTES PASSARAM' : '>>> ' + falhas + ' TESTE(S) FALHARAM'));
  process.exit(falhas === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(1); });
