# AI Handoff — DevFox

> Passagem de bastão entre agentes. **Foque no que o Git NÃO captura:** intenção, decisões,
> becos sem saída já testados e o próximo passo.
>
> **Este arquivo é lido a cada sessão: mantenha-o ENXUTO.**

## ▶ PRÓXIMO PASSO — boas-vindas no 1º acesso; revisão pedagógica das 5 trilhas novas; deploy (DEV-17/18: `display_errors=Off`, `EMAIL_MODO`, `URL_BASE`, apagar `diagnostico.php` se existir). Admin: só faltam ADM-27/49 (duplicar) e ADM-92 (responder pela tela, depende de SMTP) — P3. Banco: **schema v10**.

**✅ Painel vira a tela inicial do admin + layout do wireframe — 23/09/2026.**
`inicio.php` redireciona admin para `administracao.php` (com `?aluno=1` ele ainda vê o Início do aluno; há um botão para isso na faixa de
boas-vindas). As suítes que esperavam `**/inicio.php` depois do login agora aceitam as duas telas. Layout: `.painel-grade` = conteúdo largo
à esquerda (`.linha-graficos` e `.linha-baixo`) + `.coluna-direita` fixa (resumo, ações rápidas, feed), 4 cartões `.cartao-kpi` em colunas
fixas (auto-fit deixava um cartão órfão). `min-width: 0` nos filhos do grid — sem isso a rosca empurrava a coluna vizinha para fora da tela.
Relatórios passou a usar os mesmos cartões e a mesma grade, para as duas telas não terem estilos diferentes.

**✅ Painel inicial do admin no formato do wireframe — 23/09/2026** (`testes/teste-admin-acesso.js`).
`administracao.php` ganhou: saudação com data por extenso (`dataPorExtenso()`/`diaDaSemana()` em ui.php, sem intl — no XAMPP do Windows o
locale pt_BR costuma faltar), 6 cartões com variação de 30 dias contra os 30 anteriores (`variacao()` devolve null sem base, e a tela diz
"sem base de comparação" em vez de inventar "+500%"), gráfico de linhas e rosca em SVG escritos à mão (`graficoDeLinhas()`,
`graficoDeRosca()` em includes/admin.php — CDN não carrega em XAMPP offline), ações rápidas (sem "adicionar usuário", a pedido),
feed `ultimasAtividades()` (UNION de 5 consultas pequenas), `desafiosEmDestaque()` e ranking. Busca única: `admin_busca.php` (alunos,
trilhas, módulos, atividades; `addcslashes` no LIKE). Cores dos gráficos conferidas para daltonismo, e todo gráfico tem legenda + tabela
equivalente. Conquistas aparecem abertas para o admin com selo "prévia de admin" — NADA é gravado em `conquistas_usuarios`: dar a
conquista de verdade colocaria o admin no ranking sem ter estudado.

**✅ Admin com tudo liberado, moldura pela tela e ordem sorteada — 22/09/2026** (`testes/teste-admin-acesso.js`, 20 verificações).
Admin na PRÓPRIA conta vê tudo aberto: `ehOProprioAdmin($alunoId)` (auth.php) libera pré-requisitos, sequência de módulos, trilhas em
rascunho (selo na lista) e a espera da prova. A checagem é por conta, e não `usuarioEhAdmin()` solto, senão a ficha em `admin_aluno.php`
mostraria o progresso do ALUNO com tudo "liberado". Barra `.barra-admin` em desafio.php e prova.php com atalhos de edição.
Loja: `estilosDeMoldura()` (includes/loja.php) é um catálogo fechado de aparências `.moldura-<slug>`; o admin cria moldura escolhendo uma
livre, e o slug do item É o estilo. Ordem sorteada das atividades dentro do módulo: `embaralharAtividades()` ordena por
`md5(aluno:atividade)` — sorteada entre alunos, ESTÁVEL para o mesmo aluno (se mudasse a cada F5, "Lição 3 de 16" e o botão "Próxima"
discordariam). Aplicada em desafio.php, progresso.php e proximoPassoDaTrilha; constante `ATIVIDADES_EMBARALHADAS` desliga tudo.
Alternativas de quiz também sorteadas por aluno; destaques do Início sorteados a cada visita.

**✅ Editor de código: linha do erro e barras de rolagem — 22/09/2026** (`testes/teste-editor.js`, 12 verificações).
Barra clara do Windows atravessando o editor: CodeMirror com `scrollbarStyle:'null'` (a altura é automática e as linhas quebram, então
barra nenhuma é necessária) + cor própria de barra no `:root` do CSS. Linha do erro marcada como no VS Code (fundo vermelho, número em
destaque, marca na coluna da esquerda): quem descobre a linha é `assets/js/executor.worker.js`. Detalhe que custou: a pilha do navegador
numera a partir da função que o `new Function` monta, e o cabeçalho varia por navegador — o deslocamento é MEDIDO com um erro de posição
conhecida, não chutado. Erro de SINTAXE não traz posição no Chrome: é achado recompilando linha a linha, ignorando "fim inesperado"
(trecho só incompleto). Sem linha confiável, não marca nada.

**✅ Área do administrador COMPLETA — 22/09/2026, schema v10** (`testes/teste-admin-gestao.js`, ~60 verificações; as 13 suítes passam).
Abas (`abasAdmin()`): Painel e conteúdo · Alunos · Relatórios · Conquistas · Loja · Suporte · Registro. Páginas novas: `admin_alunos.php`,
`admin_aluno.php` (ficha: progresso, provas, respostas, conquistas; promover/rebaixar, desativar, link de senha, excluir digitando o e-mail),
`admin_relatorios.php` (difíceis ≥3 tentativas, funil, dicas, mapa aluno×trilha, período 7/30/tudo, CSV com BOM, `;` e fórmula neutralizada),
`admin_conquistas.php` (regras de catálogo fechado `tiposDeRegra()`; as antigas ficam `'fixa'`), `admin_loja.php` (só cria TÍTULO; moldura precisa de CSS),
`admin_registro.php` (só leitura, gravado por `registrarAcaoAdmin()` em todo POST do admin). Painel: números (ADM-12), alertas (ADM-13), ordem e
arquivar trilha. `exigirLogin()` relê `tipo` e `ativo` a cada request (rebaixado/desativado cai na hora). Último admin ativo é protegido; ninguém
rebaixa/desativa/exclui a si mesmo pela ficha. Aluno exclui a própria conta em Configurações (senha). Admin vê módulo em rascunho e a solução.
Quiz editado mantém ids das alternativas (UPDATE por posição). Decisão: nada com progresso é apagado — arquivar/desativar.

**✅ Detalhes menores da auditoria CORRIGIDOS — 22/09/2026** (`testes/teste-menores.js`, 13 verificações; as 12 suítes passam).
Plural com `plural()` (Loja "Falta 1 moeda", suporte/esqueci "segundo(s)", prova "de N questão/questões");
"Avise seu professor" → "Avise a equipe do DevFox pelo Suporte"; contraste: `.botao-primario` com texto escuro
`#1a1204` (6,5:1; branco dava 2,86) e `--texto-apagado` `#858fa5` (≥ 4,58 em todos os fundos); menu do celular:
nomes escondidos com clip (continuam para leitor de tela), uma linha só; "Rodar código" guarda o `innerHTML`
(ícone não some); `menuAtivo` cobre desafio/prova (Trilhas) e `admin_*` (Administração); Perfil sem o 2º
"Ver todas" e comentário falso do `<dialog>` corrigido; **avatares**: 6 SVG novos gerados no mesmo estilo
(`assets/imagens/avatares/raposa-{vermelha,artica,feneco,prateada,dev,noturna}.svg`) + padrão + retrato = 8;
saíram as 3 logos recoloridas e o retrato espelhado (código antigo cai em 'padrao'); **vídeo da aula**:
`urlVideoSegura()` em ui.php aceita só YouTube e grava/mostra `youtube-nocookie.com/embed/ID`
(`javascript:` era XSS via `<iframe src>`); CSS morto removido (~3,3 KB: auth-arte, lista-atividade,
selo-embreve, menu-item-inativo, painel-link/legenda, botao-inativo, linha-trilha-requisito, meta-atual).
Arquivos a apagar no repositório: `assets/imagens/logo-completa.png`, `assets/imagens/avatares/raposa-retrato-espelho.jpg`.
**Novo documento:** `docs/REQUISITOS_ADMIN.md` — o que o admin vê/cria/edita/remove, situação (✅/🟡/❌),
prioridade P1–P3 e o que ele NÃO pode fazer.

**✅ Problemas médios da auditoria (6 a 11) CORRIGIDOS — 22/09/2026, schema v9** (`testes/teste-medios.js`,
29 verificações; contra o código anterior falha em 16). As 11 suítes passam.
- **Fuso e sessão:** `includes/ambiente.php` (novo, incluído por auth.php e csrf.php): `America/Sao_Paulo` no
  PHP + `SET time_zone = date('P')` na conexão; `iniciarSessao()` com cookie HttpOnly, SameSite=Lax, `secure`
  em HTTPS e `use_strict_mode`.
- **Sequência de dias:** conta ESTUDO — `atualizarOfensiva()` saiu do login e do "Lembrar-me" e foi para
  `registrarSubmissao()` (qualquer resposta enviada) e para a entrega da prova. `ofensivaVigente()` mostra 0
  para quem não estudou hoje nem ontem (barra lateral, Início, Perfil, Ranking).
- **Bloqueio:** liberado = TODOS os anteriores concluídos (`$anteriorConcluido && concluido`); módulo já
  concluído continua aberto para revisão.
- **Pré-requisitos:** `admin_trilha.php` recusa ciclo (busca no grafo); `requisitosPendentes()` ignora trilha
  exigida em rascunho.
- **CSRF:** login, cadastro, prova, `api/processar_desafio.php` e `api/dica.php` (`exigirCsrf(true)` responde
  JSON; o JS lê `<meta name="csrf">` do header). **Logout só por POST** (botão Sair na barra lateral, sem
  `type="submit"` escrito de propósito — ver comentário em header.php); GET em logout.php só redireciona.
- **Limite de login (v9, tabela `tentativas_login`):** 5 senhas erradas por e-mail em 15 min bloqueiam (vale
  para e-mail inexistente também); teto de 50 por IP (sala de aula = mesmo IP). E-mail inexistente roda um
  bcrypt mesmo assim (tempo de resposta não revela conta). E-mail do login/cadastro em minúsculas.
- **Prova:** questões e alternativas embaralhadas a cada abertura; espera de 5 min depois de REPROVAR
  (`PROVA_ESPERA_MINUTOS`, vale também para POST direto); aprovação `acertos*100 >= nota*total` (sem round).
- **Cadastro:** `textoPost()` (campo em lista não quebra), nome 2–100, e-mail ≤150, senha 6–72 bytes, mensagem
  neutra para e-mail repetido (esconder de vez exigiria confirmar cadastro por e-mail), corrida na chave
  única tratada.
- **Testes:** `ajudantes_teste.js` ganhou `sair()`, `responderProva()` (gabarito pelo banco) e
  `tokenDaPagina()`; `teste-prova.js` e `teste-conquistas.js` não usam mais gabarito por posição.

**✅ Bloco 1 da auditoria CORRIGIDO — 22/09/2026** (`testes/teste-graves.js`, 18 verificações; contra o
código antigo falha em 7). 1) `.htaccess` na raiz (bloqueia .sql/.md/.py/.bat/.xlsx/.log, arquivos
ocultos, `.git`, listagem de pasta) + `Require all denied` em `database/`, `tools/`, `testes/`, `docs/`,
`config/` — conferido num Apache 2.4 (403 em tudo isso; páginas e assets 200). 2) `api/processar_desafio.php`
recusa (403) questão de módulo `prova` e módulo não liberado; `moduloLiberado()` agora também recusa trilha
despublicada (admin passa, para conferir rascunho) — vale para API, dica, desafio e prova. 3) "Mostrar exemplo
de solução" só com `$jaConcluida`; por isso `ajudantes_teste.js` lê a solução do BANCO (`solucaoDoBanco`,
cliente mysql com utf8mb4). 4) Módulo vazio conta como concluído em `estadoDosModulos` e em
`requisitosPendentes` (SQL reescrito: pendente = não marcado E (aula com atividade por fazer OU prova com
questões)). 5) Regra única `trilhaConcluida($modulos)` em auth.php, usada por `dadosDasTrilhas` (percentual
100 quando concluída, máx. 99 quando não) e pela conquista `trilha_completa`. Extra: `atividades.php` limita
`?pagina` a 10000 (número gigante dava Fatal error). E `resumoDoRanking` numera todas as linhas antes de
procurar o aluno (bug do "Undefined array key posicao" com o aluno em 1º).

**🔎 Auditoria completa — 21/09/2026 (graves, médios e menores corrigidos em 22/09).**
Bloco 1 (furos graves):
1. `database/`, `tools/`, `testes/`, `docs/` e `*.md` abrem pelo navegador (só `logs/` tem .htaccess):
   `banco.sql` entrega o gabarito inteiro; README expõe `admin@devfox.local / devfox123`.
2. `api/processar_desafio.php` não chama `moduloLiberado()`, não recusa módulo `prova` nem trilha
   despublicada → "oráculo" do gabarito da prova (+XP) e XP em módulo bloqueado (reproduzido).
3. `desafio.php` mostra "Mostrar exemplo de solução" antes de o aluno concluir.
4. Módulo de aula sem atividades (ou prova sem questões) nunca conclui → trava a trilha e o Projeto Final.
5. Dois critérios de "trilha concluída": progresso.php/conquistas.php exigem 100% das atividades,
   estadoDosModulos aceita prova aprovada com nota mínima → trilha presa em 95%, sem conquista.
Bloco 2: ofensiva só conta login e nunca zera na exibição; sem `date_default_timezone_set`/`SET time_zone`
(XAMPP costuma vir Europe/Berlin); bloqueio olha só o módulo anterior (`$anteriorConcluido = $modulo['concluido']`);
pré-requisitos circulares/despublicados não validados; CSRF ausente em login/cadastro/prova/APIs, logout por GET;
sem limite de login; cookie de sessão sem HttpOnly/SameSite; prova por tentativa e erro (sem embaralhar/limite);
cadastro: nome >100 ou `nome[]` → erro 500, e "e-mail já cadastrado" enumera contas.
Menores: "1 moedas"/"1 segundos" (usar `plural()`), "Avise seu professor" (não existe papel professor),
`round()` aprova 69,5% com mínimo 70, contraste branco/laranja 2,86:1, menu mobile sem nome acessível,
"Rodar código" perde o ícone, 2× "Ver todas" no Perfil. Falta para a banca: relatório do admin
(tabela `submissoes` nunca é lida), boas-vindas no 1º acesso, excluir conta (LGPD).

**✅ Trilhas fiel ao wireframe + fonte Figtree local — 21/09/2026.** Fonte trocada de Poppins
(Google Fonts) para **Figtree** (a mais próxima dos wireframes), hospedada em `assets/fonts/`
(OFL) — nenhuma página depende mais de CDN de fonte. Logos oficiais (`assets/imagens/logo-html.png`,
`logo-css.png`, `logo-js.png`, gerados a partir dos arquivos que o Murilo colocou, fundo branco
removido) via `logoDaTrilha()` em ui.php. Linha de trilha: ícone 74px, título 500, descrição
menor, "Iniciante | N módulos ›" agora É o botão que abre os módulos (sem linha "Ver módulos"),
motivo do bloqueio virou tooltip + texto para leitor de tela. Painel HTML e CSS com logo solto.
Barra lateral maior só em telas com altura ≥ 880px (notebook 1366×768 continua compacto).
Classe `.sr-only` criada. As 9 suítes passam.

**✅ Tela de Perfil (wireframe) — 22/09/2026.** `perfil.php` refeito com todos os blocos:
cartão (avatar com moldura, nível, XP até o próximo nível, Editar perfil → Configurações,
Alterar avatar → janela `<dialog>`, 4 números), 4 conquistas com selo hexagonal por raridade
(`seloConquista()` em ui.php) + `conquistas.php` (Ver todas), Estatísticas com "+N esta
semana" (últimos 7 dias) e "Melhor: N dias" (coluna nova `melhor_sequencia`), frase do dia,
Mascote (ilustração recortada da imagem de fundo; o anel acompanha a moldura da Loja;
"Personalizar mascote" → Loja) e Atividade recente + `atividades.php` (histórico paginado,
inclui "Iniciou nova trilha"). Avatares: catálogo fixo em `includes/avatares.php` (6 opções,
2 recortadas da ilustração); usado em barra lateral, ranking, Início, Loja e Perfil.
Cobertura: `teste-perfil.js` (29). As 9 suítes passam.


**✅ Login completo (wireframe): Lembrar-me, Esqueci minha senha, Suporte — 22/09/2026.**
- **Lembrar-me** (`includes/conta.php`, tabela `lembrar_tokens`): seletor + validador, só o
  hash no banco, cookie HttpOnly/SameSite=Lax por 30 dias, rotação a cada uso, cookie
  forjado derruba os tokens da conta, sair e trocar senha apagam. Entra por `exigirLogin()`.
- **Esqueci minha senha** (`esqueci_senha.php`, `redefinir_senha.php`): resposta idêntica
  para e-mail existente ou não (não vira detector de contas), token de 64 hex com hash no
  banco, 1 hora, uso único, espera de 60 s entre pedidos. **Sem servidor de e-mail no
  XAMPP**: `EMAIL_MODO='log'` grava a mensagem em `logs/emails.log` (bloqueado por
  .htaccess e no .gitignore). Em produção: definir `EMAIL_MODO='mail'` e `URL_BASE` em
  config/conexao.php — o link NÃO usa o cabeçalho Host (falha clássica de envenenamento).
- **Suporte** (`suporte.php`, `admin_suporte.php`, tabela `mensagens_suporte`): público,
  CSRF, campo isca, limites de tamanho, espera de 60 s; admin lê, responde por mailto e
  marca como lida (cartão na Administração com a contagem de não lidas).
- Cobertura: `teste-conta.js` (32). As 8 suítes passam.


**✅ Tela de Trilhas (wireframe) + 5 trilhas novas + corretor de HTML/CSS — 21/09/2026.**
Murilo escolheu "tela + tudo, inclusive HTML e CSS" (sabendo do custo). Entregue:
- `trilhas.php` refeito: lista na ordem do wireframe, ícones SVG próprios, um único botão
  principal (a trilha a seguir), painel lateral de destaque (trilhas com `destaque=1`) e
  "Recomendadas para você" (próxima lição, desafio para praticar, trilha nova).
- 6 trilhas: Lógica, HTML, CSS, JavaScript (inclui Repetição, Funções e Métodos), Desafios Práticos,
  Projeto Final. Conteúdo novo em `tools/conteudo_*.py`, escrito com apoio de IA e validado
  (`checar_trilha.py`, `validar_conteudo.js`). **Precisa de revisão humana antes da banca.**
- **Corretor de HTML/CSS** (`assets/js/executor-html.js`): o código do aluno vira página num
  iframe `sandbox="allow-same-origin"` SEM `allow-scripts` (nenhum script do aluno roda) e
  com CSP (nada externo carrega). Casos de teste são verificações — `existe`, `contar`,
  `texto`, `atributo`, `estilo` (valor CALCULADO). Mesma regra do resto: o navegador só
  responde, quem julga é `api/processar_desafio.php` (não mudou). Prévia ao vivo no desafio.
- **Pré-requisito entre trilhas** (`trilha_requisitos`): Projeto Final bloqueado até as 7
  outras concluídas; aplicado em `estadoDosModulos()`, logo vale pela URL também.
- Administração: linguagem, destaque, chamada e requisitos da trilha; conferência de
  gabarito funciona para HTML.
- Corrigida uma corrida na tela de desafio: ao reenviar, o "incorreta" da tentativa anterior
  ficava na tela até o servidor responder. Agora aparece "Conferindo sua resposta…".
- Cobertura: `teste-trilhas.js` (18). As 7 suítes passam, cada uma sobre banco recém-importado.


**✅ Tela Início + Desafios + Configurações + sino — CONCLUÍDOS em 21/09/2026**, seguindo o
wireframe de Início. Sem mudança de schema.
- `inicio.php` virou a primeira tela depois do login (login, cadastro, index e o bloqueio de
  admin redirecionam para lá). Blocos: banner com "Continuar aprendendo" (próximo passo real),
  4 números, Continue aprendendo (trilha em que o aluno mexeu por último), 3 Desafios em
  destaque (só de módulo LIBERADO), Top Ranking (top 3 + a linha do aluno), Acesso rápido.
- `desafios.php`: todos os exercícios de código com estado concluído/disponível/bloqueado e
  filtros. Bloqueado aparece sem link — a lista NÃO fura a progressão sequencial.
- `configuracoes.php`: nome e senha. Trocar senha exige a senha atual; e-mail não é editável
  (é o login e o sistema não envia e-mail para confirmar).
- Sino: painel com os eventos reais (conquista, atividade, prova, compra) montados por UNION
  das tabelas existentes — sem tabela de notificações. O "já vi" fica no localStorage, porque
  é só um lembrete visual; perder isso não perde dado.
- Cálculo de progresso compartilhado em `includes/progresso.php` (Trilhas, Início e Desafios
  usam o mesmo). `trilhas.php` foi refatorado para usar ele.
- `asset()` em ui.php põe `?v=<data do arquivo>` no CSS/JS: evita o navegador usar CSS velho
  com HTML novo (aconteceu com o Murilo no login).
- Cobertura: `teste-inicio.js`, 36 verificações. As 6 suítes passam, cada uma sobre banco
  recém-importado (worker e prova usam a conta fixa do aluno e dependem de banco limpo).

**✅ Loja mínima (T11) — CONCLUÍDA em 21/09/2026.** Murilo escolheu fazer a Loja em vez de
tirá-la do menu, sabendo que reabre escopo cortado em 29/08. Ficou mínima de propósito:
`loja.php` + `includes/loja.php`, 9 itens **só cosméticos** (5 molduras de avatar, 4 títulos),
comprar / equipar / tirar. Moldura aparece na barra lateral, no perfil e no ranking.

Três regras sustentam a Loja (detalhe no cabeçalho de `includes/loja.php`):
1. **Saldo derivado, não guardado:** `xp DIV 10 − SUM(compras.preco_pago)`. Não existe coluna
   `moedas`; moeda só vem de XP, que já é à prova de farm. 7.200 XP nas trilhas = 720 moedas;
   o catálogo soma 1.100 — ninguém compra tudo, de propósito.
2. **Compra atômica:** transação com `SELECT ... FOR UPDATE` na linha do usuário. Sem a trava,
   duas sessões comprando ao mesmo tempo gastavam além do saldo — o teste PROVOU isso (tirando
   a trava, `teste-loja.js` falha com saldo 0 e duas compras; com ela, passa).
3. **Servidor decide:** do navegador vem só o `item_id`; preço, saldo e posse vêm do banco.
   A coluna do UPDATE de equipar vem de lista fixa (`CATEGORIAS_LOJA`), nunca do POST.

⚠ **SCHEMA v5** — tabelas `itens_loja` e `compras`, colunas `usuarios.moldura_id` e
`usuarios.titulo_id`. `includes/auth.php` passou a fazer JOIN com `itens_loja`, então **com o
banco v4 TODA página logada quebra** (`Unknown column u.moldura_id`). Reimportar `banco.sql`
ou rodar `migracao_v4_para_v5.sql` (testada sobre um v4 com dados).

Cobertura: `teste-loja.js`, 29 verificações (saldo, anti-farm, preço forjado, compra repetida,
equipar sem ter, categoria forjada, corrida entre duas sessões, CSRF). A corrida só é testada
de verdade com servidor multiprocesso (`PHP_CLI_SERVER_WORKERS=4`). As 5 suítes passam.

**✅ DEV-9 (Administração) + DEV-13 (CSRF) — CONCLUÍDOS em 19/09/2026.** Quatro telas
encadeadas: `administracao.php` → `admin_trilha.php` → `admin_modulo.php` →
`admin_atividade.php`. Dá para criar e editar trilha, módulo (aula e prova), atividade,
alternativas e casos de teste, reordenar tudo e publicar/despublicar trilha.

O que justifica a tela existir é a **conferência de gabarito**: exercício de código só salva
depois que a solução de referência passar nos próprios casos de teste. A conferência roda no
MESMO Web Worker do aluno (`assets/js/admin-atividade.js`) com a MESMA normalização do
servidor. Sem isso, a tela seria um jeito mais fácil de cadastrar exercício quebrado do que o
pipeline `tools/` — e valeria menos que ele.

O servidor revalida tudo ao salvar: quiz com nº de alternativas corretas ≠ 1 é recusado,
exercício sem caso oculto é recusado, questão de prova sem `modulo_referencia_id` é recusada.

**CSRF (DEV-13) saiu junto**, em `includes/csrf.php`: token por sessão, `hash_equals()` na
comparação, `exigirCsrf()` em todo POST da Administração. Foi feito agora e não depois porque
esses são os formulários mais sensíveis do sistema — deixar para a tarefa de segurança
significaria mexer em todos de novo. **Os formulários FORA da Administração (login, cadastro,
desafio, prova) ainda não têm token** — fica para o DEV-14.

Conteúdo com progresso de aluno **não é apagado**: a cascata levaria junto as conclusões e o
XP de quem já fez. `atividadeTemProgresso()` e `moduloTemProgresso()` barram.

Cobertura: `teste-admin.js`, 25 verificações — inclusive que POST sem token CSRF é recusado
com 403, que gabarito errado não salva, e que o que o admin cadastra funciona de verdade para
o aluno (inclusive o caso oculto protegendo contra chumbo).

**✅ DEV-12 (conquistas) — CONCLUÍDO em 19/09/2026.** As 6 conquistas do catálogo agora
desbloqueiam de verdade, com aviso na tela. Regras em `includes/conquistas.php`, todas
avaliadas NO SERVIDOR; a garantia de "só uma vez" é a chave única `uq_conquista_usuario` mais
`INSERT IGNORE` + `rowCount()`, o mesmo padrão do anti-farm de XP.

⚠ **SCHEMA v4** — nova tabela `dicas_vistas`. Murilo precisa reimportar `banco.sql`, ou rodar
`migracao_v3_para_v4.sql` se tiver dado a preservar.

A parte que vale entender: a conquista "Por Conta Própria" (5 desafios sem dica) exigia saber
quem pediu ajuda. A dica **deixou de vir no HTML** da tela de desafio — é buscada em
`api/dica.php` quando o aluno abre o bloco, e é o servidor que grava o uso. A alternativa
óbvia (um campo `usou_dica` no POST da resposta) seria editável no DevTools. Mesma regra do
resto do sistema: nada que o navegador informe decide sozinho.

Aviso de conquista chega por dois caminhos, porque há dois momentos: na tela de desafio a
resposta da API traz as conquistas e o JS mostra na hora; na prova e no login (POST que
redireciona) elas ficam na sessão e o `footer.php` as imprime na página seguinte.

Cobertura: `teste-conquistas.js`, 19 verificações — inclusive que cada conquista **NÃO** cai
antes da condição, que é o erro fácil de não perceber.

**📅 PRAZO: 1º DE DEZEMBRO DE 2026** (informado por Murilo em 19/09/2026 — fecha DEV-3).
A **documentação escrita está com outro integrante do grupo**; não é frente de trabalho destas
sessões. Isso muda o cálculo de risco: são ~10 semanas, não ~5, e a maior tarefa do backlog
(DEV-19) saiu do prato. Sobra folga para acabamento.

⚠ Continua pendente desde 29/08: **confirmar com o orientador** que PHP autoral sem framework
atende os critérios de avaliação (DEV-4). É conversa de 10 minutos e pode invalidar
planejamento se a resposta for não.

**✅ DEV-7 (editor CodeMirror) — CONCLUÍDO em 19/09/2026.** CodeMirror 5.65.16 servido do
próprio projeto (`assets/vendor/codemirror/`), NÃO por CDN: numa apresentação, depender da rede
da sala é risco à toa. O `<textarea>` continua existindo por baixo e é espelhado a cada tecla —
testado bloqueando os arquivos do editor: a tela cai para o textarea e o exercício continua
resolvível, sem erro de JS. Tema próprio em `styles.css`, com seletores prefixados por
`.cm-s-devfox` porque o `codemirror.css` carrega depois e venceria em especificidade.

**✅ Conteúdo das 3 trilhas — CONCLUÍDO em 19/09/2026.** 15 módulos (12 de aula + 3 provas),
63 atividades e 104 casos de teste. Lógica de Programação, JavaScript Básico e Estruturas de
Repetição, cada uma com 4 módulos de aula + prova de fim de nível.

O conteúdo **não é escrito direto no SQL**: mora em `tools/conteudo.py` e o `banco.sql` é
gerado por `tools/gerar_banco.py` (que só reescreve a seção de dados — o schema é copiado
intacto). `tools/validar_conteudo.js` roda a solução de referência de cada exercício contra
os próprios casos de teste. **Leia `tools/README.md` antes de mexer em conteúdo.**

O validador já pegou um erro real: o exercício "O índice trocado" tinha um bug
(`matriz[i][i]` em vez de `matriz[i][j]`) que produzia O MESMO resultado nos casos escolhidos
— numa matriz 2x2, `2*(a+d)` é igual a `a+b+c+d` sempre que `a+d = b+c`. O aluno passaria sem
corrigir nada. Ao escrever caso oculto, confira que ele distingue de verdade a solução errada
mais provável.

**⚠ Ids de atividade mudam quando o conteúdo muda.** Foi o que quebrou as duas suítes desta
vez. Elas agora resolvem atividades sem depender de id, via `testes/ajudantes_teste.js`:
exercício de código copia a solução de referência da própria página; quiz tenta as
alternativas até acertar. Restaram uma tabela de 4 ids no topo de `teste-worker.js` e o
`GABARITO` da prova em `teste-prova.js` — ambos CONFERIDOS pelo próprio teste, que falha
dizendo exatamente o que atualizar.

**✅ Git — RESOLVIDO em 19/09/2026.** O repositório existe, é privado e está no GitHub:
https://github.com/martinsmurilo619-ship-it/devfox — commit inicial `2f9afad`, 45 arquivos.
A cópia canônica passou a ser `C:\xampp\htdocs\DevFox` (é a que roda no XAMPP e a que tem
o `.git`). As cópias em `Desktop\DevFox` e `Downloads\devfox\devfox` estão OBSOLETAS —
não gravar nelas. O repositório tem `.gitignore` (barra `config/conexao.php` e
`node_modules/`) e `.gitattributes` (normaliza CRLF).

**⚠ Banco desatualizado na máquina do Murilo (pendente).** Em 11/09 a primeira execução no
XAMPP dele deu `Fatal error: Unknown column 'm.tipo'` em `trilhas.php` → `estadoDosModulos()`:
o banco dele está num schema anterior ao v3. O front carregou inteiro — só a consulta quebrou.
A correção é reimportar `database/banco.sql` (já começa com `DROP TABLE IF EXISTS` de todas as
tabelas e pode ser importado por cima — testado). Ficou combinado ESPERAR o banco.sql com o
conteúdo novo, para ele importar uma vez só.

Para conferir a versão do banco antes de concluir qualquer coisa:

```sql
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA='devfox' AND TABLE_NAME='modulos' AND COLUMN_NAME='tipo') AS modulos_tipo,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA='devfox' AND TABLE_NAME='atividades'
      AND COLUMN_NAME='modulo_referencia_id') AS ativ_ref,
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA='devfox' AND TABLE_NAME='tentativas_prova') AS tab_prova;
```

Os três precisam dar 1. **Lição:** nunca dizer "o schema não mudou, não reimporte" sem antes
mandar rodar essa consulta — o schema do repositório e o banco da máquina do dev divergem.

**Retomar por aqui:** plano de produção completo (8 fases, com checklist por tarefa):
https://claude.ai/code/artifact/dc4afbc6-b6a3-4f35-8d23-cf5c0f133a7c
Para o banco: `database/README.md` documenta o schema v3 e as decisões.

**Estado exato em 11/09/2026:** O app **funciona ponta a ponta**. Cadastro → login →
trilha com módulos que abrem em sequência → aula (teoria/vídeo) → exercício com execução
real do código → prova de fim de nível → XP → ranking → perfil → logout. Tudo testado com
PHP 8.4 + MariaDB 10.11 + Chromium rodando de verdade, sem nenhum erro, warning ou notice.

**Banco (schema v3) — concluído:** `database/banco.sql` (criação do zero com dados de
exemplo, incluindo uma prova completa), `migracao_v1_para_v2.sql`, `migracao_v2_para_v3.sql`
e `README.md`. Todos os caminhos foram executados e chegam ao mesmo schema. O arquivo
original da v1 está preservado como `banco_v1_original.sql`.

**Fase 0 (PHP) — concluída:** criado `includes/auth.php` como única fonte de autenticação,
gamificação e progressão. Todas as páginas foram adaptadas ao schema. `atividade.php` e
`painel_professor.php` foram aposentados. `assets/css/styles.css` ganhou as classes que
faltavam e responsividade.

**Organização da pasta (11/09/2026):** a raiz guarda o sistema servido pelo Apache (a URL
continua `http://localhost/devfox`) mais os arquivos de convenção (`README.md`, `AGENTS.md`,
`CLAUDE.md`, `AI_HANDOFF.md`, `PERFIL.md`). `database/`, `testes/` e `docs/` separam schema,
testes e documentação; `docs/design/` guarda a logo original e os wireframes, que antes
estavam em `assets/imagens/` e seriam publicados pelo servidor sem necessidade.

⚠ **`device_bash` não funciona na máquina do Murilo** (Windows, atualização de 08/09/2026
quebrou o mount do workspace do Claude). As sessões só conseguem LER e ESCREVER arquivos via
device_list_dir / device_stage_files / device_commit_files — não dá para rodar git, mysql,
php nem mover/apagar arquivo. Tudo que exige comando é passado para o Murilo executar, e no
`cmd` do Windows **comando é sempre uma linha só** (mensagem de commit multilinha quebra:
use vários `-m`).

⚠ A reorganização foi feita por `organizar.bat` (move os arquivos e apaga `professor.php`, que
era código morto). As sessões de IA não conseguem mover nem apagar arquivos na máquina do
Murilo — o shell remoto não monta as pastas —, só criar e sobrescrever. **Se precisar mover
ou apagar algo, gere um `.bat` em vez de tentar direto.**

**Front-end (11/09/2026) — concluído:** todas as telas foram refeitas para seguir os
wireframes e receber a logo. O que mudou:
- `includes/ui.php` (novo): `icone()` devolve SVG inline (sem CDN, funciona offline),
  `tituloDoNivel()` e `aparenciaDaTrilha()`.
- `includes/header.php`: barra lateral fixa com logo, menu com ícones, cartão do usuário
  (nível, barra de XP, sequência/conquistas/trilhas) + topo com título, subtítulo e pílula
  de XP. Cada página define `$tituloPagina`/`$subtituloPagina` ANTES do include.
- `assets/css/styles.css` reescrito: vocabulário de classes em português
  (`.painel`, `.botao`, `.selo`, `.barra-progresso`, `.linha-trilha`, `.passo`, `.alternativa`,
  `.aviso`…). **As classes antigas (`.card`, `.btn`, `.form-group`, `.etiqueta`,
  `.progress-bar`, `.stat-card`, `.feedback-msg`, `.modulo-passo`) não existem mais** — quem
  criar tela nova precisa usar o vocabulário atual.
- Login e cadastro viraram tela dividida (formulário + arte com a logo).
- `assets/imagens/logo-marca.png` (cabeça da raposa, fundo transparente) e
  `logo-completa.png` (lockup completo), extraídas de `logo_devfox.png` com PIL.
- `assets/js/desafios.js` e `auth.js` acompanharam os nomes novos; o botão de olho agora
  troca dois SVGs em vez de sobrescrever o `textContent` (que apagava o ícone).
- Verificado com Playwright em 1440px e 390px: sem estouro horizontal, sem erro de PHP e
  sem erro de JS. As duas suítes de teste continuam passando inteiras.

**Decisões já tomadas (não reabrir):**
- **Pivô de stack:** adotar PHP + MySQL (o que já existe) em vez de React + Supabase (plano
  anterior, de 29/08/2026 mais cedo no mesmo dia, agora descartado). Motivo: aproveitar
  trabalho já feito dado o prazo curto, e um backend PHP autoral resolve a dúvida sobre
  "engenharia própria" para a banca.
- Escopo do MVP: núcleo de ensino (auth, trilhas, aulas, desafios, progresso, prova) + o que
  veio de brinde no protótipo (ranking, perfil, administração de conteúdo).
- Fluxo de exercício: manter `desafio.php` (ligado ao banco via `atividades`/`modulos`) como
  fluxo oficial; `atividade.php` (pergunta fixa no código, não usa o banco) será aposentado.
- Correção de exercício: comparação de valor normalizado para "completar"/"corrigir erro";
  execução no navegador (Web Worker) contra casos de teste para "código livre". Nunca `eval()`
  nem execução do código do aluno no PHP.
- Conteúdo pedagógico: gerado com apoio de IA, com revisão humana obrigatória antes de publicar.
- Editor de código (29/08/2026): trocar o `<textarea>` simples por um editor real (CodeMirror
  via CDN), com numeração de linha e destaque de sintaxe, como nos mockups enviados por Murilo.
  Entra no MVP (Fase 1 do plano de produção), não é "depois".
- Loja no menu (29/08/2026): aparecia como item inativo/"em breve". **Revisto em 21/09/2026:**
  Murilo optou por uma Loja mínima e funcional (só cosméticos, sem vantagem no aprendizado
  nem no ranking). Tirar do menu e "sino real, Loja sai" foram as alternativas descartadas.
- **Modelo de produto (11/09/2026):** o DevFox é uma PLATAFORMA DE CONTEÚDO, como Duolingo
  e Alura — o conteúdo é feito pela equipe, não por professores que se cadastram. O papel
  `professor` virou `admin` e o painel virou "Administração". O modelo de sala de aula
  (professor cria turma e acompanha seus alunos) foi considerado e descartado por escopo.
- **Estrutura pedagógica (11/09/2026):** cada módulo é a explicação (teoria e/ou vídeo)
  seguida das atividades que aplicam o conteúdo. Ao fim de cada nível há uma PROVA, que
  precisa ser aprovada (70%) para liberar o avanço. Módulos abrem em sequência.
- **Reprovação NÃO apaga progresso (11/09/2026):** o aluno revisa apenas os módulos das
  questões que errou e refaz a prova. Refazer o nível inteiro foi explicitamente rejeitado:
  o projeto existe para reduzir a desistência de iniciantes, e punir o erro com a perda do
  progresso é o caminho mais curto para causar exatamente essa desistência.
- **Correção de exercícios (DEV-6, 11/09/2026):** o código do aluno roda num Web Worker
  (`assets/js/executor.worker.js`), numa thread separada e sem acesso ao DOM. O navegador
  recebe apenas as ENTRADAS dos casos de teste — os gabaritos nunca saem do servidor — e
  devolve o que o código produziu. Quem compara e decide é `api/processar_desafio.php`.
  Laço infinito é cortado por timeout de 3s encerrando o worker. Limite honesto e
  documentado: como a execução é na máquina do aluno, ele pode responder com uma saída que
  seu código não produziu — mas essa saída precisa estar CORRETA para valer, inclusive nos
  casos ocultos, cujas entradas ele vê mas os gabaritos não.
- Schema v2 do banco (29/08/2026): `submissoes` guarda o histórico de tentativas e
  `atividades_concluidas` (com chave única por aluno+atividade) é a única que concede XP —
  é o que impede o farm. Resposta esperada saiu de `atividades` e foi para `casos_teste`
  (com casos ocultos) e `alternativas` (quiz). `usuarios.nivel` foi removido: nível é sempre
  derivado do XP em PHP. Detalhes e justificativas em `database/README.md`.

**⚠ Riscos desta frente:**
- **NÃO EXISTE REPOSITÓRIO GIT.** Todo o trabalho está só na pasta local, sem histórico e
  sem backup. Um arquivo sobrescrito por engano é perda definitiva. Criar o repositório é a
  tarefa mais urgente, antes de escrever mais código.
- **O sistema nunca foi executado numa máquina do time.** Tudo foi validado em ambiente
  controlado (PHP 8.4 + MariaDB 10.11 + Chromium). Quanto mais camadas forem construídas
  antes desse primeiro teste real, mais difícil fica isolar o que quebrou quando quebrar.
- Prazo menor que 2 meses — plano de produção completo em
  https://claude.ai/code/artifact/dc4afbc6-b6a3-4f35-8d23-cf5c0f133a7c assume início em
  01/09/2026; reajustar as semanas assim que a data exata da banca for confirmada.

**Pendente de Murilo/equipe:** confirmar com o orientador se o pivô para PHP autoral (em vez do
Supabase cogitado antes) resolve a dúvida sobre critério de avaliação. Isso NÃO trava a Fase 0,
que pode (e deve) começar imediatamente.

---

## Estado atual (11/09/2026)

- Branch: **repositório Git ainda não criado** — é a tarefa mais urgente
- Último commit: não se aplica
- Última ferramenta / agente usado: Claude (sessão Cowork) — reforma do front-end para os
  wireframes + logo (antes: DEV-6/Web Worker, schema v3, progressão sequencial e prova)
- "Estado bom" (`AGENTS.md` §5) passando? **Sim, no ambiente de desenvolvimento.** Duas
  suítes em Playwright/Chromium passam inteiras (`teste-worker.js` e `teste-prova.js`), sem
  erro nem warning de PHP. **Nunca foi executado numa máquina do time.**
- No ar / publicado agora: nada — só roda local via XAMPP

## Decisões tomadas (e o porquê)

- **PHP + MySQL em vez de React + Supabase** — porque um colega já tinha um protótipo
  funcional cobrindo a maior parte das telas do MVP; reconstruir do zero em outra stack, com
  menos de 2 meses de prazo, jogaria fora trabalho real. Alternativa descartada: manter o plano
  Supabase e tratar o PHP como referência descartável.
- **`desafio.php` como fluxo oficial de exercício, `atividade.php` aposentado** — porque
  `desafio.php` já usa o banco de dados de verdade (`atividades`/`modulos`); `atividade.php`
  tem uma pergunta fixa no código-fonte e não é extensível.
- **Correção por comparação de valor / Web Worker, não `strpos()`** — porque a implementação
  atual (`api/processar_desafio.php`) aprova qualquer resposta que contenha a string esperada
  em qualquer lugar, o que não valida nada de verdade.

## Bugs encontrados e corrigidos na Fase 0 (29/08/2026)

Além dos já conhecidos, a revisão e os testes revelaram outros:

- `logout.php` chamava `session_unset()`/`session_destroy()` sem nunca ter iniciado a
  sessão — **o logout não funcionava**, o usuário continuava logado.
- `desafio.php` carregava `assets/js/desafio.js` (singular), mas o arquivo se chama
  `desafios.js` (plural): o script nunca era carregado.
- `atividade.php` tinha um SEGUNDO farm de XP, ainda mais direto: `UPDATE usuarios SET
  xp = xp + ?` sem controle nenhum de repetição.
- Faltavam no CSS várias classes usadas pelas páginas (`.desafio-container`, `.code-editor`,
  `.stat-card`, `.alert-success`, `.feedback-msg`) e duas variáveis inexistentes
  (`--error-color`, `--orange-brand`): essas telas renderizavam sem estilo.
- O CSS não tinha **nenhuma media query**, com sidebar fixa de 250px: em telas pequenas a
  barra cobria o conteúdo. O documento do projeto exige responsividade.
- Os arquivos `.sql` não declaravam charset. Importados por um cliente configurado em
  latin1, gravavam os acentos duplamente codificados ("LÃ³gica") sem acusar erro.
- `professor.php` "protegia" o acesso com um `alert()` em JavaScript, depois de a página já
  ter sido enviada ao navegador — o que não protege nada.

## Becos sem saída (já tentei, não funcionou)

- **Correção via `strpos($resposta, $saida_esperada)`** — não rola porque não verifica lógica
  nem valor real, só se o texto esperado aparece em algum lugar da resposta enviada. Fica fácil
  de burlar colando a resposta certa dentro de um comentário.
- **Cadastro com seleção livre de "professor"** — não rola porque não há nenhuma verificação;
  vira uma porta aberta pra qualquer visitante acessar o painel de gestão de conteúdo.

## O que falta fazer

Lista completa e priorizada, com dono sugerido por tarefa, está no plano de produção:
https://claude.ai/code/artifact/dc4afbc6-b6a3-4f35-8d23-cf5c0f133a7c

Resumo das fases:
- [x] **Banco de dados refeito (schema v2)** — 29/08/2026, testado em MariaDB
- [x] **Fase 0 concluída** — 29/08/2026: app roda ponta a ponta, sem erro fatal, sem farm
      de XP, com controle de acesso funcionando
- [x] **DEV-6 concluída** — 11/09/2026: execução real do código do aluno em Web Worker,
      com julgamento no servidor, casos ocultos e proteção contra laço infinito. Validada
      em Chromium com Playwright (`teste-worker.js`, no ambiente de desenvolvimento)
- [x] **Schema v3 + progressão + prova** — 11/09/2026: papel admin, módulos com tipo
      aula/prova, travamento sequencial, tela de prova com nota mínima e indicação do que
      revisar. Validado em `teste-prova.js` (Playwright) e a migração v2→v3 foi testada
      sobre um banco v2 com dados
- [ ] **Criar repositório Git** — urgente, ainda não existe
- [ ] **Rodar o sistema numa máquina do time** — ainda não aconteceu
- [ ] Fase 1 (semana 2): correção de exercício de verdade (Web Worker / comparação de valor)
- [ ] Fase 2 (semanas 3–4): trilhas e progresso vindos do banco, sem dado fixo no código
- [ ] Fase 3 (semanas 4–6): conteúdo pedagógico (1 trilha completa + parciais)
- [ ] Fase 4 (semana 6–7): segurança (SQL injection, XSS, controle de papel) e polimento visual
- [ ] Fase 5 (semana 8): deploy em hospedagem com PHP + MySQL, ensaio da banca

## Riscos e pontos de atenção

- Não alterar credenciais reais nem editar `config/conexao.php` com senha real fora do ambiente
  local — hoje usa o padrão do XAMPP (`root` sem senha), que não pode ir para produção assim.
- Não executar comandos destrutivos sem aprovação.
- ⚠ Não demonstrar o sistema para ninguém (orientador incluso) antes da Fase 0 estar concluída
  — o app quebra com erro fatal em páginas centrais.
- ⚠ Não publicar exercício/aula gerado por IA sem revisão humana.

## Onde está o detalhe (não duplicar aqui)

- **Regras, convenções e ritos:** `AGENTS.md`.
- **Contexto de negócio, público e tom:** `PERFIL.md`.
- **Plano de produção completo (fases, tarefas, dono sugerido):**
  https://claude.ai/code/artifact/dc4afbc6-b6a3-4f35-8d23-cf5c0f133a7c
- **Documento de apresentação original do projeto:** `DevFox_Apresentacao_do_Projeto.docx`
  (visão inicial; o escopo de funcionalidades nele segue válido, a stack nele descrita não).

## Prompt para o próximo agente

```text
Leia AGENTS.md, CLAUDE.md, AI_HANDOFF.md e PERFIL.md, e rode `git log --oneline -10` e `git status`
(quando o repositório existir).
Continue de onde o último agente parou — comece pela Fase 0 do plano de produção linkado acima
se ela ainda não estiver concluída.

Antes de editar qualquer arquivo:
1. Resuma o estado atual.
2. Liste os arquivos relevantes.
3. Mostre um plano curto.
4. Aponte os riscos.
5. Aguarde minha aprovação.

Não edite config/conexao.php com credencial real, não altere credenciais e não execute
comandos destrutivos.
```

---

> **Ao trabalhar:** atualize este handoff a cada checkpoint (intenção, decisão, próximo passo)
> e feche com **commit** — não stash. Mantenha enxuto.
