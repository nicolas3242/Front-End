/**
 * teste-admin-gestao.js — a área do administrador (v10, docs/REQUISITOS_ADMIN.md).
 * -----------------------------------------------------------------------------
 * Ferramenta de verificação; não é entregue ao aluno.
 *
 * Painel (números, alertas, ordem, arquivar, apagar), "ver como aluno",
 * estatística da atividade, ids das alternativas preservados, alunos (busca,
 * ficha, promover/rebaixar, desativar, link de senha, excluir, travas),
 * exclusão da própria conta, relatórios e CSV, conquistas com regra, Loja,
 * filtros do suporte e o registro de ações.
 *
 * ⚠ MEXE NO BANCO e lê logs/emails.log. Rode num banco recém-importado.
 */

const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const { sair } = require('./ajudantes_teste');

const BASE  = process.env.DEVFOX_URL   || 'http://127.0.0.1:8080';
const MYSQL = process.env.DEVFOX_MYSQL || 'mysql -u root devfox';
const LOG   = process.env.DEVFOX_EMAILS || [
  path.join(__dirname, 'app', 'logs', 'emails.log'),
  path.join(__dirname, '..', 'logs', 'emails.log'),
].find(p => fs.existsSync(path.dirname(p)));

let falhas = 0;
const ok      = t => console.log('  ✔ ' + t);
const falha   = t => { console.log('  ✘ FALHOU: ' + t); falhas++; };
const secao   = t => console.log('\n' + t);
const confere = (cond, bom, ruim) => cond ? ok(bom) : falha(ruim);
const sql     = q => execSync(MYSQL + ' --default-character-set=utf8mb4 -N -B -e "' + q.replace(/"/g, '\\"') + '"').toString().trim();

async function novaPagina(nav) {
  const pg = await (await nav.newContext()).newPage();
  pg.on('dialog', d => d.accept());          // os confirm() de arquivar/excluir
  pg.erros = [];
  pg.on('pageerror', e => pg.erros.push(e.message));
  return pg;
}
async function entrar(pg, email, senha) {
  await sair(pg, BASE);
  await pg.goto(BASE + '/login.php');
  await pg.fill('#email', email);
  await pg.fill('#senha', senha);
  await pg.click('button[type=submit]');
  await pg.waitForLoadState();
  return pg.url();
}
async function cadastrar(pg, nome, email) {
  await sair(pg, BASE);
  await pg.goto(BASE + '/cadastro.php');
  await pg.fill('#nome', nome);
  await pg.fill('#email', email);
  await pg.fill('#senha', 'senha123');
  await pg.click('button[type=submit]');
  await pg.waitForLoadState();
  return sql("SELECT id FROM usuarios WHERE email = '" + email + "'");
}
const flash = async pg => ((await pg.$('.aviso')) ? (await pg.textContent('.aviso')).trim() : '');

(async () => {
  const nav = await chromium.launch();
  const adm = await novaPagina(nav);
  const alu = await novaPagina(nav);
  const t = Date.now();

  const idBia = await cadastrar(alu, 'Bia Teste', 'bia' + t + '@teste.local');
  const idLeo = await cadastrar(alu, '=HYPERLINK("x") Leo', 'leo' + t + '@teste.local');   // nome que vira fórmula no Excel
  await entrar(adm, 'admin@devfox.local', 'devfox123');

  // =========================================================
  secao('ACESSO');
  await entrar(alu, 'bia' + t + '@teste.local', 'senha123');
  let barrado = 0;
  for (const p of ['admin_alunos.php', 'admin_aluno.php?id=1', 'admin_relatorios.php', 'admin_conquistas.php', 'admin_loja.php', 'admin_registro.php']) {
    await alu.goto(BASE + '/' + p);
    if (alu.url().endsWith('/inicio.php')) barrado++;
  }
  confere(barrado === 6, 'aluno é barrado nas 6 telas novas da Administração', 'aluno entrou em ' + (6 - barrado) + ' tela(s)');

  // =========================================================
  secao('PAINEL');
  await adm.goto(BASE + '/administracao.php');
  confere((await adm.$$('.abas-admin a')).length === 7, '7 abas na Administração', 'abas: ' + (await adm.$$('.abas-admin a')).length);
  const cartoes = await adm.$$eval('.grade-kpi .cartao-kpi small', ns => ns.map(n => n.textContent));
  confere(['Total de alunos', 'Alunos ativos', 'Atividades concluídas', 'Novos cadastros'].every(r => cartoes.some(c => c.includes(r))),
          'os 4 cartões de número do painel',
          'cartões: ' + JSON.stringify(cartoes));

  sql("INSERT INTO modulos (trilha_id, titulo, tipo, ordem) VALUES (2, 'Modulo Vazio Teste', 'aula', 50)");
  await adm.goto(BASE + '/administracao.php');
  confere((await adm.textContent('.lista-alertas')).includes('Modulo Vazio Teste'), 'alerta de módulo sem atividades', 'sem alerta');

  // ordem: descer a trilha que está em 1º
  const primeira = sql('SELECT id FROM trilhas WHERE arquivada = 0 ORDER BY ordem, id LIMIT 1');
  await adm.click(`form:has(input[name=acao][value=mover]):has(input[name=trilha_id][value="${primeira}"]) button[value=baixo]`);
  await adm.waitForLoadState();
  confere(sql('SELECT id FROM trilhas WHERE arquivada = 0 ORDER BY ordem, id LIMIT 1 OFFSET 1') === primeira,
          'descer a 1ª trilha a coloca em 2º', 'ordem não mudou');
  await alu.goto(BASE + '/trilhas.php');
  const ordemAluno = await alu.$$eval('.linha-trilha', ns => ns.map(n => n.dataset.trilha));
  confere(ordemAluno[1] === primeira, 'a ordem nova vale na tela Trilhas do aluno', 'ordem do aluno: ' + ordemAluno.join(','));

  // arquivar a trilha 3 (sem progresso) → some do aluno; depois apagar de vez
  await adm.goto(BASE + '/administracao.php');
  await adm.click('form:has(input[name=acao][value=arquivar]):has(input[name=trilha_id][value="3"]) button');
  await adm.waitForLoadState();
  await alu.goto(BASE + '/trilhas.php');
  confere(sql('SELECT CONCAT(arquivada, publicada) FROM trilhas WHERE id = 3') === '10' && !(await alu.$('.linha-trilha[data-trilha="3"]')),
          'arquivar despublica e tira a trilha da tela do aluno', 'trilha arquivada continua visível');
  // a trilha 1 tem progresso da Bia? dá progresso e tenta apagar
  sql('INSERT INTO atividades_concluidas (aluno_id, atividade_id, xp_concedido) SELECT ' + idBia + ', a.id, 10 FROM atividades a JOIN modulos m ON m.id = a.modulo_id WHERE m.trilha_id = 4 LIMIT 1');
  await adm.goto(BASE + '/administracao.php');
  await adm.click('form:has(input[name=acao][value=arquivar]):has(input[name=trilha_id][value="4"]) button');
  await adm.waitForLoadState();
  let r = await adm.request.post(BASE + '/administracao.php', { form: {
    csrf: await adm.getAttribute('input[name=csrf]', 'value'), acao: 'apagar', trilha_id: '4' } });
  confere((await r.text()).includes('não pode ser apagada') && sql('SELECT COUNT(*) FROM trilhas WHERE id = 4') === '1',
          'trilha com progresso de aluno NÃO é apagada (só arquivada)', 'apagou trilha com progresso');
  await adm.goto(BASE + '/administracao.php');
  await adm.click('details.arquivadas-admin summary');
  await adm.click('form:has(input[name=acao][value=apagar]):has(input[name=trilha_id][value="3"]) button');
  await adm.waitForLoadState();
  confere(sql('SELECT COUNT(*) FROM trilhas WHERE id = 3') === '0', 'trilha arquivada sem progresso é apagada de vez', 'não apagou');
  await adm.goto(BASE + '/administracao.php');
  await adm.click('details.arquivadas-admin summary');
  await adm.click('form:has(input[name=acao][value=desarquivar]):has(input[name=trilha_id][value="4"]) button');
  await adm.waitForLoadState();
  confere(sql('SELECT CONCAT(arquivada, publicada) FROM trilhas WHERE id = 4') === '00', 'desarquivar volta como rascunho', 'estado errado');

  // =========================================================
  secao('CONTEÚDO');
  const mod3 = sql("SELECT id FROM modulos WHERE trilha_id = 1 AND tipo = 'aula' ORDER BY ordem, id LIMIT 1 OFFSET 2");
  await adm.goto(BASE + '/desafio.php?modulo=' + mod3);
  confere(!(await adm.textContent('body')).includes('bloqueado') && (await adm.$('#formDesafio')) !== null,
          'admin abre qualquer módulo "como aluno" (sem o bloqueio da sequência)', 'admin bloqueado');
  await adm.goto(BASE + '/admin_trilha.php?id=1');
  confere((await adm.$$('a[title="Ver como aluno"]')).length >= 4, 'botão "Ver como aluno" em cada módulo', 'sem botão');

  sql('INSERT INTO submissoes (aluno_id, atividade_id, resposta, correta) VALUES (' + idBia + ', 1, \'alternativa 2\', 0), (' + idBia + ', 1, \'alternativa 3\', 1)');
  await adm.goto(BASE + '/admin_atividade.php?id=1');
  const est = await adm.textContent('#estatisticasAtividade');
  confere(est.includes('50%') && est.includes('0%'), 'estatística da atividade: 50% das tentativas certas, 0% de primeira',
          'estatística: ' + est.replace(/\s+/g, ' ').slice(0, 160));

  const idsAntes = sql('SELECT GROUP_CONCAT(id ORDER BY ordem) FROM alternativas WHERE atividade_id = 1');
  await adm.fill('input[name="alt_texto[]"] >> nth=0', 'Texto editado pelo teste');
  await adm.click('form:has(#estatisticasAtividade) button[type=submit] >> text=Salvar atividade');
  await adm.waitForLoadState();
  confere(sql('SELECT GROUP_CONCAT(id ORDER BY ordem) FROM alternativas WHERE atividade_id = 1') === idsAntes &&
          sql("SELECT COUNT(*) FROM alternativas WHERE texto = 'Texto editado pelo teste'") === '1',
          'editar o quiz mantém os ids das alternativas (' + idsAntes + ')', 'ids mudaram');

  // =========================================================
  secao('ALUNOS');
  await adm.goto(BASE + '/admin_alunos.php?q=bia' + t);
  confere((await adm.$$('#tabelaAlunos tbody tr')).length === 1, 'busca por e-mail acha 1 conta', 'busca falhou');
  await adm.goto(BASE + '/admin_alunos.php?q=%25');
  confere((await adm.$$('#tabelaAlunos tbody tr')).length === 0, '"%" digitado é texto, não curinga do LIKE', 'o % virou curinga');
  await adm.goto(BASE + '/admin_aluno.php?id=' + idBia);
  confere((await adm.textContent('main')).includes('Progresso nas trilhas') && (await adm.textContent('main')).includes('Últimas respostas'),
          'ficha do aluno com progresso e respostas', 'ficha incompleta');

  // promover → Bia entra na Administração; rebaixar → perde na hora (mesma sessão)
  await adm.click('#btnTipo');
  await adm.waitForLoadState();
  await alu.goto(BASE + '/administracao.php');
  confere(alu.url().endsWith('/administracao.php'), 'promovida: a Bia abre a Administração', 'promoção não valeu');
  await adm.click('#btnTipo');
  await adm.waitForLoadState();
  await alu.goto(BASE + '/administracao.php');
  confere(alu.url().endsWith('/inicio.php'), 'rebaixada: perde o acesso na hora, sem precisar sair', 'continuou admin');

  // desativar → sessão cai e login recusa com aviso
  await adm.click('#btnSituacao');
  await adm.waitForLoadState();
  await alu.goto(BASE + '/inicio.php');
  confere(alu.url().includes('login.php') && (await alu.textContent('body')).includes('desativada'),
          'desativada: a sessão aberta cai com aviso', 'continuou logada: ' + alu.url());
  await entrar(alu, 'bia' + t + '@teste.local', 'senha123');
  confere(alu.url().endsWith('/login.php') && (await alu.textContent('body')).includes('desativada'),
          'desativada: o login com senha certa é recusado', 'entrou: ' + alu.url());
  await adm.click('#btnSituacao');
  await adm.waitForLoadState();
  confere((await entrar(alu, 'bia' + t + '@teste.local', 'senha123')).endsWith('/inicio.php'), 'reativada: entra de novo', 'não entrou');

  if (LOG && fs.existsSync(LOG)) fs.writeFileSync(LOG, '');
  await adm.click('#btnLinkSenha');
  await adm.waitForLoadState();
  confere(LOG && fs.readFileSync(LOG, 'utf8').includes('redefinir_senha.php?token='), 'link de nova senha enviado (emails.log)', 'e-mail não saiu');

  // travas: admin não mexe na própria conta; último admin não sai
  const idAdmin = sql("SELECT id FROM usuarios WHERE email = 'admin@devfox.local'");
  await adm.goto(BASE + '/admin_aluno.php?id=' + idAdmin);
  const token = await adm.getAttribute('input[name=csrf]', 'value');
  r = await adm.request.post(BASE + '/admin_aluno.php', { form: { csrf: token, id: idAdmin, acao: 'desativar' } });
  confere((await r.text()).includes('sua própria conta') && sql('SELECT ativo FROM usuarios WHERE id = ' + idAdmin) === '1',
          'admin não desativa a própria conta', 'desativou a si mesmo');

  // excluir: confirmação errada recusa; certa apaga tudo
  await adm.goto(BASE + '/admin_aluno.php?id=' + idLeo);
  await adm.fill('#confirmacao', 'errado@x');
  await adm.click('#formExcluir button');
  await adm.waitForLoadState();
  confere((await flash(adm)).includes('digite o e-mail') && sql('SELECT COUNT(*) FROM usuarios WHERE id = ' + idLeo) === '1',
          'excluir com a confirmação errada é recusado', 'excluiu sem confirmação');

  // =========================================================
  secao('RELATÓRIOS');
  for (let i = 0; i < 3; i++) sql('INSERT INTO submissoes (aluno_id, atividade_id, resposta, correta) VALUES (' + idLeo + ', 2, \'x\', 0)');
  sql('INSERT INTO atividades_concluidas (aluno_id, atividade_id, xp_concedido) VALUES (' + idLeo + ', 2, 10)');
  await adm.goto(BASE + '/admin_relatorios.php?periodo=tudo');
  confere((await adm.textContent('#relDificeis')).includes(sql('SELECT titulo FROM atividades WHERE id = 2')),
          'atividade com 3 tentativas erradas aparece nas "mais difíceis"', 'não apareceu');
  for (const id of ['#relFunil', '#relDicas', '#relProgresso']) {
    confere(await adm.$(id) !== null, 'seção ' + id.slice(4).toLowerCase() + ' presente', 'faltou ' + id);
  }
  r = await adm.request.get(BASE + '/admin_relatorios.php?periodo=tudo&csv=progresso');
  const csv = await r.text();
  confere(r.headers()['content-type'].includes('text/csv') && csv.charCodeAt(0) === 0xFEFF && csv.includes('Aluno;E-mail;XP'),
          'CSV com BOM (Excel abre com acentos) e separador ";"', 'CSV: ' + csv.slice(0, 60));
  confere(csv.includes("'=HYPERLINK"), 'nome começando com "=" sai neutralizado no CSV (sem virar fórmula)', 'fórmula no CSV');

  // agora exclui o Leo de verdade
  await adm.goto(BASE + '/admin_aluno.php?id=' + idLeo);
  await adm.fill('#confirmacao', 'leo' + t + '@teste.local');
  await adm.click('#formExcluir button');
  await adm.waitForLoadState();
  confere(sql('SELECT COUNT(*) FROM usuarios WHERE id = ' + idLeo) === '0' &&
          sql('SELECT COUNT(*) FROM atividades_concluidas WHERE aluno_id = ' + idLeo) === '0',
          'excluir com o e-mail certo apaga a conta e o progresso (LGPD)', 'conta ou progresso ficaram');

  // =========================================================
  secao('CONQUISTAS');
  await adm.goto(BASE + '/admin_conquistas.php');
  await adm.fill('#titulo', 'Primeiros Passos Teste');
  await adm.fill('#descricao', 'Conclua 1 atividade.');
  await adm.selectOption('#regra_tipo', 'atividades');
  await adm.fill('#regra_valor', '1');
  await adm.click('#formConquista button[type=submit]');
  await adm.waitForLoadState();
  confere(sql("SELECT CONCAT(regra_tipo, ':', regra_valor) FROM conquistas WHERE titulo = 'Primeiros Passos Teste'") === 'atividades:1',
          'conquista criada com regra do catálogo (atividades ≥ 1)', 'não criou');
  await entrar(alu, 'bia' + t + '@teste.local', 'senha123');      // o login confere as conquistas
  confere(sql("SELECT COUNT(*) FROM conquistas_usuarios cu JOIN conquistas c ON c.id = cu.conquista_id " +
              "WHERE cu.usuario_id = " + idBia + " AND c.titulo = 'Primeiros Passos Teste'") === '1',
          'a Bia (que tem atividade concluída) ganha a conquista nova', 'não ganhou');

  const idNova = sql("SELECT id FROM conquistas WHERE titulo = 'Primeiros Passos Teste'");
  await adm.goto(BASE + '/admin_conquistas.php');
  await adm.click(`form:has(input[name=acao][value=alternar]):has(input[name=id][value="${idNova}"]) button`);
  await adm.waitForLoadState();
  const idCai = await cadastrar(alu, 'Cai Teste', 'cai' + t + '@teste.local');
  sql('INSERT INTO atividades_concluidas (aluno_id, atividade_id, xp_concedido) VALUES (' + idCai + ', 1, 10)');
  await entrar(alu, 'cai' + t + '@teste.local', 'senha123');
  confere(sql('SELECT COUNT(*) FROM conquistas_usuarios WHERE usuario_id = ' + idCai + ' AND conquista_id = ' + idNova) === '0',
          'desativada: quem cumpre a regra depois não ganha mais', 'desativada foi concedida');
  await alu.goto(BASE + '/conquistas.php');
  confere(!(await alu.textContent('main')).includes('Primeiros Passos Teste'), 'desativada some do catálogo de quem não tem', 'apareceu para quem não tem');

  await adm.goto(BASE + '/admin_conquistas.php?editar=1');
  confere((await adm.$('#regra_tipo')) === null, 'conquista original: a regra não aparece para edição', 'regra da original editável');
  await adm.fill('#titulo', 'Código Iniciante 2');
  await adm.click('#formConquista button[type=submit]');
  await adm.waitForLoadState();
  confere(sql("SELECT CONCAT(titulo, '|', regra_tipo) FROM conquistas WHERE id = 1") === 'Código Iniciante 2|fixa',
          'título da original muda; a regra continua a do código', 'estado: ' + sql('SELECT titulo FROM conquistas WHERE id = 1'));

  // =========================================================
  secao('LOJA');
  await adm.goto(BASE + '/admin_loja.php');
  await adm.fill('#nome', 'Guardião do Git');
  await adm.fill('#preco', '42');
  await adm.click('#formItem button[type=submit]');
  await adm.waitForLoadState();
  const slug = sql("SELECT slug FROM itens_loja WHERE nome = 'Guardião do Git'");
  await alu.goto(BASE + '/loja.php');
  confere(slug.startsWith('guardi') && (await alu.$(`[data-item="${slug}"]`)) !== null,
          'título novo (' + slug + ') já aparece na Loja do aluno', 'não apareceu');
  const idItem = sql("SELECT id FROM itens_loja WHERE slug = '" + slug + "'");
  await adm.goto(BASE + '/admin_loja.php?editar=' + idItem);
  await adm.fill('#preco', '55');
  await adm.click('#formItem button[type=submit]');
  await adm.waitForLoadState();
  confere(sql('SELECT preco FROM itens_loja WHERE id = ' + idItem) === '55', 'preço editado', 'preço não mudou');
  await adm.click(`form:has(input[name=acao][value=alternar]):has(input[name=id][value="${idItem}"]) button`);
  await adm.waitForLoadState();
  await alu.goto(BASE + '/loja.php');
  confere(!(await alu.$(`[data-item="${slug}"]`)), 'fora da vitrine: some para quem não comprou', 'continuou à venda');

  // =========================================================
  secao('SUPORTE E REGISTRO');
  sql("INSERT INTO mensagens_suporte (nome, email, assunto, mensagem, lida) VALUES ('Ana', 'ana@x.local', 'tecnico', 'Mensagem de teste 1', 0), ('Rui', 'rui@x.local', 'conta', 'Mensagem de teste 2', 1)");
  await adm.goto(BASE + '/admin_suporte.php?estado=lidas');
  const lidas = await adm.$$eval('.mensagem-suporte', ns => ns.map(n => n.textContent));
  confere(lidas.length === 1 && lidas[0].includes('Rui'), 'filtro "lidas" mostra só a lida', 'filtro: ' + lidas.length);
  await adm.goto(BASE + '/admin_suporte.php?assunto=tecnico');
  confere((await adm.$$('.mensagem-suporte')).length === 1, 'filtro por assunto', 'filtro por assunto falhou');

  await adm.goto(BASE + '/admin_registro.php');
  const registro = await adm.textContent('#tabelaRegistro');
  const esperadas = ['Arquivou a trilha', 'Apagou a trilha', 'Promoveu a admin', 'Desativou a conta de', 'Excluiu a conta de',
                     'Criou a conquista', 'Criou o item da loja', 'Enviou link de nova senha'];
  const faltando = esperadas.filter(x => !registro.includes(x));
  confere(faltando.length === 0, 'registro guarda as ações (' + esperadas.length + ' tipos conferidos) com o nome do admin',
          'faltou no registro: ' + faltando.join(', '));
  await adm.goto(BASE + '/admin_registro.php?area=loja');
  confere(!(await adm.textContent('#tabelaRegistro')).includes('Promoveu'), 'filtro por área no registro', 'filtro do registro falhou');

  // =========================================================
  secao('EXCLUIR A PRÓPRIA CONTA (Configurações)');
  await entrar(alu, 'cai' + t + '@teste.local', 'senha123');
  await alu.goto(BASE + '/configuracoes.php');
  await alu.fill('#senha_exclusao', 'errada');
  await alu.click('#excluirConta button');
  await alu.waitForLoadState();
  confere(sql('SELECT COUNT(*) FROM usuarios WHERE id = ' + idCai) === '1', 'senha errada não exclui', 'excluiu com senha errada');
  await alu.fill('#senha_exclusao', 'senha123');
  await alu.click('#excluirConta button');
  await alu.waitForLoadState();
  confere(sql('SELECT COUNT(*) FROM usuarios WHERE id = ' + idCai) === '0' && alu.url().includes('login.php?excluida=1'),
          'com a senha certa a conta some e a pessoa sai do sistema', 'não excluiu: ' + alu.url());

  // =========================================================
  secao('JAVASCRIPT');
  const erros = [...adm.erros, ...alu.erros];
  confere(erros.length === 0, 'nenhum erro de JavaScript', 'erros: ' + erros.join(' | '));

  await nav.close();
  console.log('\n' + (falhas === 0 ? '>>> TODOS OS TESTES PASSARAM' : '>>> ' + falhas + ' TESTE(S) FALHARAM'));
  process.exit(falhas === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(1); });
