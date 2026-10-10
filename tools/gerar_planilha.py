# -*- coding: utf-8 -*-
"""Gera DevFox_Plano_ate_01-12.xlsx — plano de trabalho até a entrega."""

import datetime as dt
from openpyxl import Workbook
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.formatting.rule import FormulaRule
from openpyxl.worksheet.datavalidation import DataValidation
from openpyxl.comments import Comment
from openpyxl.utils import get_column_letter

SAIDA = '/mnt/user-data/outputs/DevFox_Plano_ate_01-12.xlsx'

# ---------------------------------------------------------------- estilo
FONTE = 'Arial'
LARANJA = 'FF6B00'
ESCURO = '1A1D26'
CINZA_TXT = '5B6475'
CINZA_BORDA = 'D5D9E0'
FUNDO_EDITAVEL = 'FFF4E6'   # colunas que o time preenche

def f(tam=10, negrito=False, cor='222222', italico=False):
    return Font(name=FONTE, size=tam, bold=negrito, color=cor, italic=italico)

fino = Side(style='thin', color=CINZA_BORDA)
BORDA = Border(left=fino, right=fino, top=fino, bottom=fino)
QUEBRA = Alignment(wrap_text=True, vertical='top')
CENTRO = Alignment(horizontal='center', vertical='top', wrap_text=True)

def cabecalho(ws, linha, titulos, larguras, editaveis=()):
    for i, (t, w) in enumerate(zip(titulos, larguras), start=1):
        c = ws.cell(row=linha, column=i, value=t)
        c.font = f(10, True, 'FFFFFF')
        c.fill = PatternFill('solid', fgColor=LARANJA if t in editaveis else ESCURO)
        c.alignment = Alignment(horizontal='center', vertical='center', wrap_text=True)
        c.border = BORDA
        ws.column_dimensions[get_column_letter(i)].width = w
    ws.row_dimensions[linha].height = 30

INICIO = dt.date(2026, 9, 21)
ENTREGA = dt.date(2026, 12, 1)

# ---------------------------------------------------------------- tarefas
# (id, frente, tarefa, por que importa, status, prioridade, horas, responsavel,
#  semana, criterio de pronto, origem)
TAREFAS = [
 # ---------------- SEMANA 1 — fechar pendências
 ('T01', 'Infraestrutura', 'Commitar a Administração (DEV-9) e o CSRF (DEV-13)',
  'Está no disco desde 19/09 01:35, mas o último commit é de 01:20. É o maior pedaço de trabalho do projeto SEM backup — um HD que falhe hoje leva junto.',
  'Concluído', 'Crítica', 0.25, 'Murilo', 1,
  '"git status" limpo e o commit aparece no GitHub.', 'Análise 21/09'),
 ('T02', 'Infraestrutura', 'Apagar diagnostico.php e o arquivo vazio "v3" da pasta',
  'O diagnostico.php expõe a estrutura do banco — ferramenta de desenvolvimento que não pode chegar à produção. O "v3" (0 bytes, criado 19/09 00:11) é resto de comando.',
  'Concluído', 'Alta', 0.1, 'Murilo', 1,
  'Nenhum dos dois arquivos existe; "git status" não os lista.', 'Análise 21/09'),
 ('T03', 'Infraestrutura', 'Conferir que o banco local está no schema v4',
  'As conquistas (DEV-12) dependem da tabela dicas_vistas. Sem ela, abrir uma dica quebra a tela.',
  'Concluído', 'Alta', 0.25, 'Murilo', 1,
  '"SHOW TABLES LIKE \'dicas_vistas\'" retorna 1 linha. Se não retornar, reimportar banco.sql pelo cmd.', 'Análise 21/09'),
 ('T04', 'Validação', 'Confirmar a stack com o orientador (DEV-4)',
  'Pendente desde 29/08 — há mais de 3 semanas. Se ele esperar framework ou outra stack, descobrir em novembro é tarde demais para mudar.',
  'A fazer', 'Crítica', 0.5, 'Murilo', 1,
  'Resposta do orientador registrada no AI_HANDOFF.md.', 'Backlog'),
 ('T05', 'Infraestrutura', 'Adicionar os colegas como colaboradores no GitHub (resto do DEV-2)',
  'O repositório é privado e só você tem acesso. Quem escreve a documentação precisa ler o código.',
  'A fazer', 'Alta', 0.25, 'Murilo', 1,
  'Cada colega consegue fazer "git clone".', 'Backlog'),
 ('T06', 'Infraestrutura', 'Rodar o sistema na máquina de cada colega (DEV-1)',
  'Até hoje só rodou na sua. Se só funciona num computador, a apresentação depende de um único aparelho.',
  'Em andamento', 'Alta', 2, 'Time', 1,
  'Cada integrante abre localhost/DevFox, faz login e conclui uma atividade.', 'Backlog'),
 ('T07', 'Infraestrutura', 'Definir quem faz o quê até a entrega (DEV-5)',
  'A documentação já tem dono. O resto (testes com usuários, slides, ensaio) ainda não.',
  'Em andamento', 'Média', 0.5, 'Time', 1,
  'Coluna "Responsável" desta planilha preenchida por completo.', 'Backlog'),

 # ---------------- SEMANA 2 — segurança
 ('T08', 'Segurança', 'CSRF nos formulários fora da Administração',
  'O DEV-13 cobriu só a Administração. Login, cadastro, prova e as APIs de desafio e dica ainda aceitam POST forjado.',
  'A fazer', 'Alta', 3, 'Murilo', 2,
  'POST sem token recusado com 403 em todas as telas; teste automatizado cobrindo.', 'Backlog'),
 ('T09', 'Segurança', 'Auditoria de XSS e SQL injection, arquivo por arquivo (DEV-14)',
  'É a pergunta técnica mais provável da banca. O código usa prepared statements e escape de saída, mas os arquivos herdados do protótipo nunca foram auditados um a um.',
  'A fazer', 'Alta', 4, 'Murilo', 2,
  'Cadastrar usuário com nome <script>alert(1)</script> não executa nada em nenhuma tela; nenhuma query concatena entrada do usuário.', 'Backlog'),
 ('T10', 'Segurança', 'Limitar tentativas de login (DEV-16)',
  'Sem limite, dá para testar senhas indefinidamente. Baixo risco num TCC, mas é pergunta clássica de banca.',
  'A fazer', 'Média', 3, 'Murilo', 2,
  'Após 5 tentativas erradas, o login fica bloqueado por alguns minutos.', 'Backlog'),
 ('T11', 'Qualidade', 'Decidir o destino da "Loja" e do sino de notificações',
  'LOJA FEITA em 21/09 (loja mínima, só cosméticos, schema v5). SINO FEITO em 21/09: painel com eventos reais e ponto só quando há novidade.',
  'Concluído', 'Média', 1, 'Murilo', 2,
  'Nenhum elemento clicável da interface leva a lugar nenhum.', 'Análise 21/09'),

 # ---------------- SEMANA 3 — publicar
 ('T12', 'Deploy', 'Escolher a hospedagem (DEV-17)',
  'Precisa de PHP + MySQL — Vercel e Netlify NÃO servem. Opções: hospedagem compartilhada, Railway, Render, InfinityFree.',
  'A fazer', 'Alta', 2, 'Murilo', 3,
  'Hospedagem escolhida, conta criada e acessível ao time.', 'Backlog'),
 ('T13', 'Deploy', 'Preparar o projeto para produção',
  'Configurações de desenvolvimento no ar viram brecha: senha no código, erro do PHP mostrando caminho de arquivo, pasta de testes acessível.',
  'A fazer', 'Alta', 3, 'Murilo', 3,
  'Credenciais por variável de ambiente; display_errors desligado; /tools, /testes, /docs e *.md bloqueados; contas @devfox.local com senha nova ou removidas.', 'Backlog'),
 ('T14', 'Deploy', 'Publicar numa URL pública (DEV-18)',
  'Sem isso o orientador não consegue abrir o sistema, e a apresentação depende da sua máquina.',
  'A fazer', 'Alta', 3, 'Murilo', 3,
  'Sistema acessível por URL; roteiro do COMO_RODAR.md percorrido no ambiente publicado.', 'Backlog'),
 ('T15', 'Validação', 'Mandar o link publicado ao orientador',
  'Fecha o DEV-4 com o sistema na mão dele, não só a descrição. Feedback em outubro dá tempo de corrigir.',
  'A fazer', 'Alta', 0.25, 'Murilo', 3,
  'Orientador acessou e respondeu.', 'Análise 21/09'),

 # ---------------- SEMANA 4 — validar
 ('T16', 'Qualidade', 'Testar em celular e tablet reais (DEV-15)',
  'Toda a responsividade foi validada só no simulador do navegador. Toque, teclado virtual e o editor de código se comportam diferente num aparelho de verdade.',
  'A fazer', 'Média', 2, 'Time', 4,
  'Todas as telas usáveis em 360px; editor de código funciona com teclado virtual.', 'Backlog'),
 ('T17', 'Qualidade', 'Rodar as 5 suítes de teste automatizado numa máquina do time',
  'Hoje só rodaram no ambiente de desenvolvimento. "Temos testes" vale muito mais na banca se o time souber rodá-los na frente de alguém.',
  'A fazer', 'Média', 2, 'Murilo', 4,
  'teste-worker, teste-prova, teste-conquistas, teste-admin e validar_conteudo passando na máquina de alguém do grupo.', 'Análise 21/09'),
 ('T18', 'Validação', 'Teste com 5 a 10 usuários reais iniciantes',
  'A tese do TCC é reduzir a desistência de iniciantes — sem ninguém usando, isso é opinião. Mesmo um teste pequeno (colegas de outra turma) vira a seção de RESULTADOS da monografia.',
  'A fazer', 'Alta', 6, 'Time', 5,
  'Pelo menos 5 pessoas usaram por 20 min; anotado onde travaram, o que acharam e quanto avançaram.', 'Análise 21/09'),
 ('T19', 'Qualidade', 'Corrigir o que o teste com usuários revelar',
  'É a razão de testar cedo: sobra tempo para agir sobre o que aparecer.',
  'A fazer', 'Alta', 6, 'Murilo', 6,
  'Cada problema achado no T18 resolvido ou registrado como limitação conhecida.', 'Análise 21/09'),

 # ---------------- SEMANAS 6-7 — acabamento
 ('T20', 'Qualidade', 'Revisão humana das 63 atividades',
  'O validador confere que o gabarito bate com a solução — não confere se o enunciado está claro ou se a dica ajuda. O PERFIL.md exige revisão humana de todo conteúdo gerado com apoio de IA.',
  'A fazer', 'Alta', 4, 'Time', 6,
  'Cada atividade lida por um humano; correções feitas pela Administração.', 'Análise 21/09'),
 ('T21', 'Documentação', 'Passar o material técnico para quem escreve a documentação',
  'Quase tudo já existe no repositório (PERFIL, database/README, AI_HANDOFF, tools/README), mas espalhado. Uma conversa de 1 hora economiza dias de escrita no escuro.',
  'A fazer', 'Média', 1, 'Murilo', 6,
  'Colega sabe onde está cada decisão técnica e tem o diagrama do banco.', 'Análise 21/09'),
 ('T22', 'Documentação', 'Documentação escrita do TCC (DEV-19)',
  'Responsabilidade de outro integrante do grupo. Aqui só para acompanhamento.',
  'Com o colega', 'Alta', 0, 'Colega (documentação)', 7,
  'Documento cobrindo problema, solução, decisões técnicas, resultados e trabalhos futuros.', 'Backlog'),
 ('T23', 'Documentação', 'Atualizar o backlog do Jira',
  'O backlog-jira.md ainda fala em "painel do professor" e lista como pendente o que já foi entregue. Documento desatualizado confunde quem chega depois.',
  'A fazer', 'Baixa', 0.5, 'Murilo', 7,
  'Backlog reflete o estado real; itens concluídos marcados como Done.', 'Análise 21/09'),

 # ---------------- SEMANAS 8-9 — apresentação
 ('T24', 'Apresentação', 'Slides da apresentação (DEV-20)',
  'Sem slides não há defesa.',
  'A fazer', 'Alta', 6, 'Time', 8,
  'Slides prontos cobrindo problema, solução, arquitetura, demonstração e resultados.', 'Backlog'),
 ('T25', 'Apresentação', 'Roteiro da demonstração ao vivo',
  'Improvisar na frente da banca é onde as coisas quebram. Os momentos mais fortes: o laço infinito sendo interrompido e o caso oculto barrando a resposta chumbada.',
  'A fazer', 'Alta', 2, 'Murilo', 8,
  'Roteiro escrito: qual conta, qual trilha, qual desafio, em que ordem e o que falar em cada passo.', 'Backlog'),
 ('T26', 'Apresentação', 'Preparar respostas às perguntas prováveis da banca',
  'Por que PHP sem framework? Como a correção funciona se o código roda no navegador? Como o XP é protegido contra fraude? Todas têm boa resposta — mas precisam estar na ponta da língua.',
  'A fazer', 'Alta', 2, 'Time', 8,
  'Lista de perguntas com resposta curta combinada entre o grupo.', 'Backlog'),
 ('T27', 'Apresentação', 'Plano B: demonstração offline',
  'Se a internet da sala falhar, o sistema publicado não abre. Com o XAMPP num notebook, a demonstração acontece mesmo assim — foi por isso que o editor de código foi embutido no projeto em vez de vir de CDN.',
  'A fazer', 'Alta', 1, 'Murilo', 9,
  'Sistema completo rodando num notebook sem internet, testado com o Wi-Fi desligado.', 'Análise 21/09'),
 ('T28', 'Apresentação', 'Ensaio completo, cronometrado, no ambiente publicado',
  'A primeira vez que a apresentação acontece não pode ser na banca.',
  'A fazer', 'Alta', 3, 'Time', 9,
  'Dois ensaios completos dentro do tempo, com alguém de fora fazendo perguntas.', 'Backlog'),
 ('T29', 'Infraestrutura', 'Congelar a versão da entrega (tag v1.0 no Git)',
  'Garante que dá para voltar exatamente ao que foi apresentado, mesmo que alguém mexa depois.',
  'A fazer', 'Média', 0.25, 'Murilo', 9,
  '"git tag v1.0" criada e enviada ao GitHub.', 'Análise 21/09'),

 # ---------------- SEMANA 10 — folga
 ('T30', 'Apresentação', 'Semana de folga — nenhuma tarefa nova (DEV-21)',
  'É onde cabem os imprevistos que sempre aparecem. Planejar a última semana cheia é planejar atrasar.',
  'A fazer', 'Média', 0, 'Time', 10,
  'Nenhuma tarefa nova começada depois de 22/11.', 'Backlog'),
]

SEMANAS = [
    (1,  'Fechar pendências e destravar o time'),
    (2,  'Segurança'),
    (3,  'Publicar online'),
    (4,  'Validar em aparelho real'),
    (5,  'Teste com usuários reais'),
    (6,  'Corrigir o que o teste revelar + revisão de conteúdo'),
    (7,  'Folga de desenvolvimento / apoio à documentação'),
    (8,  'Slides e roteiro da demonstração'),
    (9,  'Ensaios e plano B'),
    (10, 'Folga — nenhuma tarefa nova'),
]

CONCLUIDO = [
    ('29/08/2026', 'Escopo do MVP definido e fase 2 cortada',                 'Decisão', 'PERFIL.md §4'),
    ('29/08/2026', 'Stack decidida: PHP 8 + MySQL, sem framework',             'Decisão', 'PERFIL.md §7'),
    ('29/08/2026', 'Correção dos bugs do protótipo herdado (farm de XP duplo, correção por substring, funções inexistentes, logout, autopromoção a professor, acentuação)', 'Bug', 'AI_HANDOFF.md'),
    ('29/08/2026', 'Schema v2 do banco com migração',                          'Banco', 'database/migracao_v1_para_v2.sql'),
    ('11/09/2026', 'DEV-6 — código do aluno executado em Web Worker, correção no servidor', 'Motor', 'assets/js/executor.worker.js'),
    ('11/09/2026', 'DEV-8 — resultado por caso de teste, sem revelar o caso oculto', 'Motor', 'assets/js/desafios.js'),
    ('11/09/2026', 'Schema v3: provas de fim de nível e progressão sequencial', 'Banco', 'database/migracao_v2_para_v3.sql'),
    ('11/09/2026', 'Front refeito seguindo os wireframes, com a logo',        'Interface', 'assets/css/styles.css'),
    ('19/09/2026', 'DEV-2 — repositório Git privado no GitHub',               'Infra', 'github.com/martinsmurilo619-ship-it/devfox'),
    ('19/09/2026', 'DEV-3 — data da entrega definida: 01/12/2026',            'Decisão', 'AI_HANDOFF.md'),
    ('19/09/2026', 'DEV-10/11 — 3 trilhas completas: 15 módulos, 63 atividades, 104 casos de teste', 'Conteúdo', 'tools/conteudo.py'),
    ('19/09/2026', 'Validador de conteúdo: roda a solução de cada exercício contra os próprios casos', 'Qualidade', 'tools/validar_conteudo.js'),
    ('19/09/2026', 'DEV-7 — editor CodeMirror com destaque de sintaxe, servido localmente', 'Interface', 'assets/vendor/codemirror/'),
    ('19/09/2026', 'DEV-12 — desbloqueio das 6 conquistas + schema v4',        'Gamificação', 'includes/conquistas.php'),
    ('19/09/2026', 'DEV-9 — Administração completa com conferência de gabarito', 'Administração', 'admin_*.php  (⚠ ainda sem commit — ver T01)'),
    ('19/09/2026', 'DEV-13 — proteção CSRF na Administração',                 'Segurança', 'includes/csrf.php  (⚠ ainda sem commit — ver T01)'),
    ('19/09/2026', '5 suítes de teste automatizado (Playwright)',             'Qualidade', 'testes/'),
    ('20/09/2026', 'Administração (DEV-9) e CSRF (DEV-13) commitados; diagnostico.php removido', 'Infra', 'commit 83222c4'),
    ('21/09/2026', 'Loja mínima: moedas derivadas do XP, 9 itens cosméticos, compra atômica (FOR UPDATE), schema v5', 'Funcionalidade', 'loja.php, includes/loja.php, testes/teste-loja.js'),
    ('21/09/2026', 'Tela Início (wireframe), Desafios com bloqueio, Configurações (nome/senha) e sino de notificações', 'Funcionalidade', 'inicio.php, desafios.php, configuracoes.php, includes/progresso.php, testes/teste-inicio.js'),
    ('21/09/2026', 'Login e cadastro com ilustração de fundo; versão nos assets contra cache', 'Interface', 'login.php, cadastro.php, includes/ui.php (asset())'),
]

# ================================================================= workbook
wb = Workbook()

# ---------------------------------------------------------------- Cronograma
cr = wb.active
cr.title = 'Cronograma'
cr.sheet_properties.tabColor = '4A5266'

cr['A1'] = 'Cronograma — 10 semanas até a entrega'
cr['A1'].font = f(14, True, ESCURO)
cr['A2'] = 'A data de início (C4) é o único valor fixo: todas as outras datas são calculadas a partir dela.'
cr['A2'].font = f(9, italico=True, cor=CINZA_TXT)

cr['A4'] = 'Início do plano'
cr['A4'].font = f(10, True)
cr['C4'] = INICIO
cr['C4'].number_format = 'dd/mm/yyyy'
cr['C4'].font = f(10, cor='0000FF')
cr['C4'].fill = PatternFill('solid', fgColor=FUNDO_EDITAVEL)
cr['C4'].comment = Comment('Segunda-feira em que o plano começa. Mude aqui e todas as semanas se ajustam.', 'DevFox')

cr['A5'] = 'Entrega'
cr['A5'].font = f(10, True)
cr['C5'] = ENTREGA
cr['C5'].number_format = 'dd/mm/yyyy'
cr['C5'].font = f(10, cor='0000FF')
cr['C5'].fill = PatternFill('solid', fgColor=FUNDO_EDITAVEL)
cr['C5'].comment = Comment('Informada por Murilo em 19/09/2026.', 'DevFox')

cab_cr = ['Semana', 'Início', 'Fim', 'Foco da semana', 'Tarefas', 'Horas planejadas', 'Concluídas', 'Situação']
cabecalho(cr, 7, cab_cr, [9, 12, 12, 46, 10, 15, 12, 16])

PRIM_CR = 8
for i, (n, foco) in enumerate(SEMANAS):
    r = PRIM_CR + i
    cr.cell(row=r, column=1, value=n)
    cr.cell(row=r, column=2, value=f'=$C$4+7*(A{r}-1)')
    cr.cell(row=r, column=3, value=f'=B{r}+6')
    cr.cell(row=r, column=4, value=foco)
    cr.cell(row=r, column=5, value=f'=COUNTIF(Tarefas!$J:$J,A{r})')
    cr.cell(row=r, column=6, value=f'=SUMIF(Tarefas!$J:$J,A{r},Tarefas!$H:$H)')
    cr.cell(row=r, column=7, value=f'=COUNTIFS(Tarefas!$J:$J,A{r},Tarefas!$F:$F,"Concluído")')
    cr.cell(row=r, column=8,
            value=f'=IF(E{r}=0,"—",IF(G{r}=E{r},"Fechada",IF(TODAY()>C{r},"Atrasada",IF(TODAY()>=B{r},"Em curso","Por vir"))))')
    for c in range(1, 9):
        cel = cr.cell(row=r, column=c)
        cel.font = f(10)
        cel.border = BORDA
        cel.alignment = CENTRO if c != 4 else QUEBRA
    cr.cell(row=r, column=2).number_format = 'dd/mm'
    cr.cell(row=r, column=3).number_format = 'dd/mm'
    cr.cell(row=r, column=6).number_format = '0.0'

ULT_CR = PRIM_CR + len(SEMANAS) - 1
r = ULT_CR + 1
cr.cell(row=r, column=1, value='Total').font = f(10, True)
cr.cell(row=r, column=5, value=f'=SUM(E{PRIM_CR}:E{ULT_CR})').font = f(10, True)
cr.cell(row=r, column=6, value=f'=SUM(F{PRIM_CR}:F{ULT_CR})').font = f(10, True)
cr.cell(row=r, column=6).number_format = '0.0'
cr.cell(row=r, column=7, value=f'=SUM(G{PRIM_CR}:G{ULT_CR})').font = f(10, True)
for c in range(1, 9):
    cr.cell(row=r, column=c).border = BORDA
    cr.cell(row=r, column=c).alignment = CENTRO

rng = f'H{PRIM_CR}:H{ULT_CR}'
cr.conditional_formatting.add(rng, FormulaRule(formula=[f'H{PRIM_CR}="Atrasada"'],
    fill=PatternFill('solid', fgColor='FDE2E1'), font=Font(name=FONTE, color='B42318', bold=True)))
cr.conditional_formatting.add(rng, FormulaRule(formula=[f'H{PRIM_CR}="Em curso"'],
    fill=PatternFill('solid', fgColor='FFF4E6'), font=Font(name=FONTE, color='B54708', bold=True)))
cr.conditional_formatting.add(rng, FormulaRule(formula=[f'H{PRIM_CR}="Fechada"'],
    fill=PatternFill('solid', fgColor='DCFAE6'), font=Font(name=FONTE, color='067647', bold=True)))

cr.freeze_panes = 'A8'

# ---------------------------------------------------------------- Tarefas
ta = wb.create_sheet('Tarefas', 0)
ta.sheet_properties.tabColor = LARANJA

cols = ['ID', 'Frente', 'Tarefa', 'Por que importa', 'Prioridade', 'Status',
        'Responsável', 'Esforço (h)', 'Origem', 'Semana', 'Início', 'Fim',
        'Critério de pronto', 'Observações']
larg = [6, 14, 38, 58, 11, 14, 16, 10, 13, 9, 10, 10, 46, 30]
EDITAVEIS = {'Status', 'Responsável', 'Semana', 'Observações'}

ta['A1'] = 'DevFox — o que falta até a entrega de 01/12/2026'
ta['A1'].font = f(14, True, ESCURO)
ta['A2'] = ('Colunas com cabeçalho LARANJA são as que o time preenche: Status, Responsável, Semana e '
            'Observações. As demais são o plano. Início e Fim vêm do Cronograma — mude a Semana e as datas acompanham.')
ta['A2'].font = f(9, italico=True, cor=CINZA_TXT)
ta.merge_cells('A2:N2')
ta.row_dimensions[2].height = 28
ta['A2'].alignment = Alignment(wrap_text=True, vertical='top')

LCAB = 4
cabecalho(ta, LCAB, cols, larg, EDITAVEIS)

PRIM = LCAB + 1
for i, t in enumerate(TAREFAS):
    r = PRIM + i
    (tid, frente, tarefa, porque, status, prio, horas, resp, semana, criterio, origem) = t
    valores = [tid, frente, tarefa, porque, prio, status, resp, horas, origem, semana,
               f'=IFERROR(INDEX(Cronograma!$B${PRIM_CR}:$B${ULT_CR},MATCH(J{r},Cronograma!$A${PRIM_CR}:$A${ULT_CR},0)),"")',
               f'=IFERROR(INDEX(Cronograma!$C${PRIM_CR}:$C${ULT_CR},MATCH(J{r},Cronograma!$A${PRIM_CR}:$A${ULT_CR},0)),"")',
               criterio, '']
    for c, v in enumerate(valores, start=1):
        cel = ta.cell(row=r, column=c, value=v)
        cel.border = BORDA
        cel.font = f(10)
        cel.alignment = QUEBRA if c in (3, 4, 13, 14) else CENTRO
        if cols[c - 1] in EDITAVEIS:
            cel.fill = PatternFill('solid', fgColor=FUNDO_EDITAVEL)
    ta.cell(row=r, column=3).font = f(10, True)
    ta.cell(row=r, column=11).number_format = 'dd/mm'
    ta.cell(row=r, column=12).number_format = 'dd/mm'
    ta.cell(row=r, column=8).number_format = '0.0#'
    ta.cell(row=r, column=11).font = f(10, cor='008000')   # vem de outra aba
    ta.cell(row=r, column=12).font = f(10, cor='008000')
    ta.row_dimensions[r].height = 62

ULT = PRIM + len(TAREFAS) - 1

# listas suspensas
dv_status = DataValidation(type='list', formula1='"A fazer,Em andamento,Concluído,Bloqueado,Com o colega"',
                           allow_blank=False, showErrorMessage=True,
                           errorTitle='Status inválido', error='Escolha um status da lista.')
dv_prio = DataValidation(type='list', formula1='"Crítica,Alta,Média,Baixa"', allow_blank=False)
dv_sem = DataValidation(type='whole', operator='between', formula1='1', formula2='10',
                        showErrorMessage=True, errorTitle='Semana inválida', error='Use um número de 1 a 10.')
for dv in (dv_status, dv_prio, dv_sem):
    ta.add_data_validation(dv)
dv_status.add(f'F{PRIM}:F{ULT + 50}')
dv_prio.add(f'E{PRIM}:E{ULT + 50}')
dv_sem.add(f'J{PRIM}:J{ULT + 50}')

# cores por status e prioridade
def regra(coluna, valor, fundo, texto, bold=True):
    ta.conditional_formatting.add(f'{coluna}{PRIM}:{coluna}{ULT + 50}',
        FormulaRule(formula=[f'${coluna}{PRIM}="{valor}"'],
                    fill=PatternFill('solid', fgColor=fundo),
                    font=Font(name=FONTE, color=texto, bold=bold)))

regra('F', 'Concluído',    'DCFAE6', '067647')
regra('F', 'Em andamento', 'FEF0C7', 'B54708')
regra('F', 'Bloqueado',    'FDE2E1', 'B42318')
regra('F', 'Com o colega', 'E0EAFF', '3538CD')
regra('E', 'Crítica', 'FDE2E1', 'B42318')
regra('E', 'Alta',    'FFF4E6', 'B54708')

# linha inteira esmaecida quando concluída
ta.conditional_formatting.add(f'A{PRIM}:D{ULT + 50}',
    FormulaRule(formula=[f'$F{PRIM}="Concluído"'], font=Font(name=FONTE, color='98A2B3')))

ta.freeze_panes = f'D{PRIM}'
ta.auto_filter.ref = f'A{LCAB}:N{ULT}'

ta.cell(row=ULT + 2, column=1,
        value='Esforço em horas é estimativa inicial, para dar ordem de grandeza. '
              'Origem "Análise 21/09" = item que não estava no backlog e entrou na revisão de 21/09/2026.').font = f(9, italico=True, cor=CINZA_TXT)

# ---------------------------------------------------------------- Concluído
co = wb.create_sheet('Concluído')
co.sheet_properties.tabColor = '12B76A'
co['A1'] = 'O que já foi entregue'
co['A1'].font = f(14, True, ESCURO)
co['A2'] = 'Útil para a seção de desenvolvimento da monografia — cada linha aponta onde está a evidência no repositório.'
co['A2'].font = f(9, italico=True, cor=CINZA_TXT)
cabecalho(co, 4, ['Data', 'Entrega', 'Área', 'Onde está'], [12, 72, 16, 46])
for i, (data, entrega, area, onde) in enumerate(CONCLUIDO):
    r = 5 + i
    d, m, a = map(int, data.split('/'))
    vals = [dt.date(a, m, d), entrega, area, onde]
    for c, v in enumerate(vals, start=1):
        cel = co.cell(row=r, column=c, value=v)
        cel.font = f(10)
        cel.border = BORDA
        cel.alignment = QUEBRA if c in (2, 4) else CENTRO
    co.cell(row=r, column=1).number_format = 'dd/mm/yyyy'
    if '⚠' in onde:
        co.cell(row=r, column=4).font = f(10, True, 'B54708')
    co.row_dimensions[r].height = 32 if len(entrega) > 70 else 18
co.freeze_panes = 'A5'

# ---------------------------------------------------------------- Painel
pa = wb.create_sheet('Painel', 0)
pa.sheet_properties.tabColor = ESCURO
for col, w in zip('ABCDEFG', [30, 14, 4, 30, 14, 4, 40]):
    pa.column_dimensions[col].width = w

pa['A1'] = 'DevFox — Painel de acompanhamento'
pa['A1'].font = f(16, True, ESCURO)
pa['A2'] = 'Atualiza sozinho a partir das abas Tarefas e Cronograma. Não precisa editar nada aqui.'
pa['A2'].font = f(9, italico=True, cor=CINZA_TXT)

def bloco(celula, titulo):
    pa[celula] = titulo
    pa[celula].font = f(11, True, LARANJA)

def linha(r, col, rotulo, formula, fmt=None, destaque=False):
    a = pa.cell(row=r, column=col, value=rotulo)
    a.font = f(10, destaque)
    b = pa.cell(row=r, column=col + 1, value=formula)
    b.font = f(12 if destaque else 10, True, ESCURO if not destaque else LARANJA)
    b.alignment = Alignment(horizontal='right')
    if fmt:
        b.number_format = fmt
    for c in (col, col + 1):
        pa.cell(row=r, column=c).border = Border(bottom=fino)

T = f'Tarefas!$F${PRIM}:$F${ULT}'
H = f'Tarefas!$H${PRIM}:$H${ULT}'
P = f'Tarefas!$E${PRIM}:$E${ULT}'

bloco('A4', 'PRAZO')
linha(5, 1, 'Entrega', f'=Cronograma!$C$5', 'dd/mm/yyyy')
linha(6, 1, 'Hoje', '=TODAY()', 'dd/mm/yyyy')
linha(7, 1, 'Dias restantes', '=MAX(0,Cronograma!$C$5-TODAY())', '0', destaque=True)
linha(8, 1, 'Semanas restantes', '=MAX(0,(Cronograma!$C$5-TODAY())/7)', '0.0')
linha(9, 1, 'Semana atual do plano',
      f'=IF(TODAY()<Cronograma!$C$4,"antes do início",IF(TODAY()>Cronograma!$C${ULT_CR},"reta final",INT((TODAY()-Cronograma!$C$4)/7)+1))')

bloco('D4', 'PROGRESSO')
linha(5, 4, 'Tarefas no plano', f'=COUNTA(Tarefas!$A${PRIM}:$A${ULT})')
linha(6, 4, 'Concluídas', f'=COUNTIF({T},"Concluído")')
linha(7, 4, '% concluído', f'=IFERROR(E6/E5,0)', '0%', destaque=True)
linha(8, 4, 'Horas restantes (estimativa)', f'=SUMIF({T},"<>Concluído",{H})', '0.0')
linha(9, 4, 'Horas por semana até a entrega', f'=IFERROR(E8/MAX(1,B8),0)', '0.0')

bloco('A12', 'POR STATUS')
for i, st in enumerate(['A fazer', 'Em andamento', 'Concluído', 'Bloqueado', 'Com o colega']):
    linha(13 + i, 1, st, f'=COUNTIF({T},"{st}")')

bloco('D12', 'PENDENTES POR PRIORIDADE')
for i, pr in enumerate(['Crítica', 'Alta', 'Média', 'Baixa']):
    linha(13 + i, 4, pr, f'=COUNTIFS({P},"{pr}",{T},"<>Concluído")')
pa['D17'] = '⚠ Se "Crítica" não for 0 no fim da semana 1, o plano já começou atrasado.'
pa['D17'].font = f(9, italico=True, cor='B42318')
pa.merge_cells('D17:G17')

bloco('A20', 'O QUE A ANÁLISE DE 21/09 ENCONTROU')
achados = [
    ('Loja entregue em 21/09 — o banco precisa ir para a v5.',
     'Sem reimportar banco.sql (ou rodar migracao_v4_para_v5.sql), toda página logada quebra com "Unknown column u.moldura_id".'),
    ('A confirmação com o orientador está parada há 3 semanas.',
     'É conversa de 10 minutos e é a única coisa que pode invalidar o plano inteiro. → T04'),
    ('Sobra tempo — e isso é uma oportunidade, não folga.',
     'O código que falta cabe em 3 semanas. As outras 7 permitem o que a maioria dos TCCs não tem: teste com usuários reais, que vira a seção de resultados. → T18'),
    ('Todos os itens do menu e do topo funcionam.',
     'Início, Desafios, Configurações e o sino entraram em 21/09. Nenhum elemento clicável leva a lugar nenhum.'),
]
r = 21
for titulo, texto in achados:
    pa.cell(row=r, column=1, value='• ' + titulo).font = f(10, True, ESCURO)
    pa.merge_cells(start_row=r, start_column=1, end_row=r, end_column=7)
    pa.cell(row=r + 1, column=1, value=texto).font = f(10, cor=CINZA_TXT)
    pa.cell(row=r + 1, column=1).alignment = Alignment(wrap_text=True, vertical='top')
    pa.merge_cells(start_row=r + 1, start_column=1, end_row=r + 1, end_column=7)
    pa.row_dimensions[r + 1].height = 16
    r += 3

bloco(f'A{r}', 'COMO USAR')
instr = [
    'Aba Tarefas: atualize Status, Responsável, Semana e Observações (cabeçalho laranja). O resto é o plano.',
    'Mudou uma tarefa de semana? Troque o número na coluna Semana — Início e Fim se ajustam sozinhos.',
    'Aba Cronograma: mostra a carga de cada semana e se ela está atrasada. Só a data de início (C4) é editável.',
    'Aba Concluído: o que já foi entregue, com onde está a evidência no repositório. Serve de roteiro para a monografia.',
]
for i, t in enumerate(instr):
    c = pa.cell(row=r + 1 + i, column=1, value=f'{i + 1}. {t}')
    c.font = f(10)
    pa.merge_cells(start_row=r + 1 + i, start_column=1, end_row=r + 1 + i, end_column=7)

# ordem das abas: Painel, Tarefas, Cronograma, Concluído
wb._sheets = [wb['Painel'], wb['Tarefas'], wb['Cronograma'], wb['Concluído']]
wb.active = 0

for ws in wb.worksheets:
    ws.sheet_view.showGridLines = False
    # impressão: paisagem, cabendo na largura da folha
    ws.page_setup.orientation = 'landscape'
    ws.page_setup.paperSize = ws.PAPERSIZE_A4
    ws.page_setup.fitToWidth = 1
    ws.page_setup.fitToHeight = 0
    ws.sheet_properties.pageSetUpPr.fitToPage = True
    ws.print_options.horizontalCentered = True
    ws.page_margins.left = ws.page_margins.right = 0.4

# a aba Tarefas repete o cabeçalho em cada página impressa
wb['Tarefas'].print_title_rows = f'{LCAB}:{LCAB}'

wb.save(SAIDA)
print('salvo:', SAIDA, '|', len(TAREFAS), 'tarefas,', len(CONCLUIDO), 'concluídas')
