# -*- coding: utf-8 -*-
"""tools/conteudo_css.py — trilha CSS (linguagem html: casos são verificações)."""
from conteudo_base import QUIZ, COMPLETAR, CORRIGIR, LIVRE, quiz, codigo  # noqa: F401


def pagina(css, corpo):
    """Monta o documento do exercício: o aluno mexe no <style>, o HTML vem pronto."""
    return '<style>\n' + css + '\n</style>\n\n' + corpo


# =====================================================================
# MÓDULO 1 — SELETORES E CORES
# =====================================================================

M1 = dict(
    titulo='Seletores e Cores',
    descricao='Escolher quais elementos estilizar e mudar suas cores.',
    teoria=(
        'Uma regra CSS tem duas partes: o seletor, que diz QUAIS elementos serão estilizados, '
        'e o bloco entre chaves, com as declarações no formato propriedade: valor;. '
        'Em h1 { color: #ff6b00; }, o seletor é h1 e a declaração pinta o texto de laranja.\n\n'
        'Os três seletores básicos são: o nome da tag (p pega todos os parágrafos), a classe '
        'com ponto na frente (.destaque pega todo elemento com class="destaque") e o id com '
        'cerquilha (#topo pega o elemento com id="topo"). A propriedade color muda a cor do '
        'texto; background-color muda a cor de fundo. As cores podem ser escritas em '
        'hexadecimal (#ff6b00), por nome (white) ou em rgb(255, 107, 0).\n\n'
        'O erro mais comum de quem começa é trocar o ponto pela cerquilha: escrever .topo '
        'quando o HTML tem id="topo". O navegador não avisa nada, a regra simplesmente não '
        'encontra ninguém e a página continua sem estilo.'
    ),
    atividades=[
        quiz('Selecionando por classe',
             'Qual seletor pega o elemento <p class="destaque">?',
             [('#destaque', False), ('.destaque', True), ('destaque', False), ('p#destaque', False)],
             dica='Classe usa um símbolo, id usa outro. Qual é o da classe?'),

        codigo(COMPLETAR, 'Pintando o título',
               'Complete o seletor para que APENAS o elemento com a classe titulo fique com a cor '
               'laranja #ff6b00. O parágrafo deve continuar com a cor padrão (preto).',
               pagina('___ {\n  color: #ff6b00;\n}',
                      '<h1 class="titulo">DevFox</h1>\n<p>Aprenda programando.</p>'),
               'Para selecionar por classe, escreva o nome da classe com um ponto na frente.',
               pagina('.titulo {\n  color: #ff6b00;\n}',
                      '<h1 class="titulo">DevFox</h1>\n<p>Aprenda programando.</p>'),
               [('O título fica laranja',            'estilo(".titulo", "color")', 'rgb(255, 107, 0)', True),
                ('O parágrafo continua preto',       'estilo("p", "color")',       'rgb(0, 0, 0)',     False)]),

        codigo(LIVRE, 'Caixa de aviso',
               'Escreva o CSS para: o elemento com id aviso ter fundo (background-color) #222222 e '
               'texto (color) branco (white); e os elementos com a classe nota terem o texto azul #0066cc.',
               pagina('/* escreva seu CSS aqui */',
                      '<div id="aviso">Sua sequência termina hoje!</div>\n'
                      '<p class="nota">Faça uma atividade para não perder.</p>\n'
                      '<p class="nota">Cada dia conta.</p>'),
               'Use #aviso para o id e .nota para a classe. Dentro de cada bloco, uma declaração por linha, '
               'sempre terminando com ponto e vírgula.',
               pagina('#aviso {\n  background-color: #222222;\n  color: white;\n}\n\n.nota {\n  color: #0066cc;\n}',
                      '<div id="aviso">Sua sequência termina hoje!</div>\n'
                      '<p class="nota">Faça uma atividade para não perder.</p>\n'
                      '<p class="nota">Cada dia conta.</p>'),
               [('Fundo escuro no aviso',  'estilo("#aviso", "background-color")', 'rgb(34, 34, 34)',   True),
                ('Texto branco no aviso',  'estilo("#aviso", "color")',            'rgb(255, 255, 255)', True),
                ('Nota em azul',           'estilo(".nota", "color")',             'rgb(0, 102, 204)',  False)]),

        codigo(CORRIGIR, 'O seletor errado',
               'O título deveria ficar laranja (#ff6b00) sobre fundo quase preto (#1a1a1a), mas continua '
               'sem estilo nenhum. Olhe o HTML e corrija o seletor.',
               pagina('.titulo {\n  color: #ff6b00;\n  background-color: #1a1a1a;\n}',
                      '<h1 id="titulo">Bem-vindo ao DevFox</h1>'),
               'No HTML, o título tem id, e não class. Qual símbolo seleciona um id?',
               pagina('#titulo {\n  color: #ff6b00;\n  background-color: #1a1a1a;\n}',
                      '<h1 id="titulo">Bem-vindo ao DevFox</h1>'),
               [('O título fica laranja',   'estilo("#titulo", "color")',            'rgb(255, 107, 0)', True),
                ('O fundo fica escuro',     'estilo("h1", "background-color")',      'rgb(26, 26, 26)',  False)],
               dificuldade='medio'),
    ])


# =====================================================================
# MÓDULO 2 — TEXTO E FONTES
# =====================================================================

M2 = dict(
    titulo='Texto e Fontes',
    descricao='Tamanho, peso, alinhamento e espaçamento do texto.',
    teoria=(
        'Quatro propriedades resolvem a maior parte da tipografia: font-size define o tamanho '
        '(24px, 1.5rem), font-weight define o peso (normal ou bold, que equivale a 700), '
        'text-align alinha o texto dentro do elemento (left, center, right) e line-height '
        'controla a distância entre as linhas.\n\n'
        'Sobre as unidades: px é um tamanho fixo na tela; rem é relativo ao tamanho de fonte '
        'da página, que por padrão é 16px. Então 1.5rem vale 24px e 2rem vale 32px. Usar rem '
        'facilita respeitar quem aumentou a fonte no navegador.\n\n'
        'O erro mais comum aqui é esquecer o ponto e vírgula no fim de uma declaração. '
        'Sem ele, o navegador junta aquela linha com a próxima, não entende o resultado e '
        'descarta as DUAS declarações — e o erro aparece numa propriedade que estava certa.'
    ),
    atividades=[
        quiz('Centralizando o texto',
             'Qual declaração centraliza o texto de um parágrafo?',
             [('align: center;', False), ('text-align: center;', True),
              ('font-align: center;', False), ('text-position: middle;', False)],
             dica='A propriedade começa com "text" e trata do alinhamento.'),

        codigo(COMPLETAR, 'Título grande e centralizado',
               'Complete o CSS para que o h1 tenha tamanho de fonte 32px e o parágrafo fique centralizado.',
               pagina('h1 {\n  font-size: ___;\n}\n\np {\n  text-align: ___;\n}',
                      '<h1>Trilha CSS</h1>\n<p>Deixe suas páginas com a sua cara.</p>'),
               'O tamanho leva o número junto da unidade, sem espaço: 32px. O alinhamento centralizado é center.',
               pagina('h1 {\n  font-size: 32px;\n}\n\np {\n  text-align: center;\n}',
                      '<h1>Trilha CSS</h1>\n<p>Deixe suas páginas com a sua cara.</p>'),
               [('Título com 32px',       'estilo("h1", "font-size")', '32px',   True),
                ('Parágrafo centralizado', 'estilo("p", "text-align")', 'center', False)]),

        codigo(LIVRE, 'Frase de destaque',
               'Estilize os elementos com a classe destaque: texto em negrito (font-weight: bold), '
               'tamanho 24px (pode escrever 24px ou 1.5rem) e altura de linha (line-height) de 30px.',
               pagina('/* escreva seu CSS aqui */',
                      '<p class="destaque">Programar é resolver problemas.</p>\n<p>Um passo de cada vez.</p>'),
               'Um único bloco .destaque { } com três declarações resolve. Lembre das unidades.',
               pagina('.destaque {\n  font-weight: bold;\n  font-size: 24px;\n  line-height: 30px;\n}',
                      '<p class="destaque">Programar é resolver problemas.</p>\n<p>Um passo de cada vez.</p>'),
               [('Texto em negrito',   'estilo(".destaque", "font-weight")', '700',  True),
                ('Tamanho de 24px',    'estilo(".destaque", "font-size")',   '24px', True),
                ('Altura de linha',    'estilo(".destaque", "line-height")', '30px', False)]),

        codigo(CORRIGIR, 'O ponto e vírgula sumido',
               'O parágrafo deveria ter fonte de 18px, ficar centralizado e com cor #333333, mas o '
               'tamanho e o alinhamento não funcionam. Encontre e corrija o erro.',
               pagina('p {\n  font-size: 18px\n  text-align: center;\n  color: #333333;\n}',
                      '<p>Estudar um pouco todo dia vale mais que muito de uma vez.</p>'),
               'Olhe o fim de cada linha. Uma delas não termina como as outras.',
               pagina('p {\n  font-size: 18px;\n  text-align: center;\n  color: #333333;\n}',
                      '<p>Estudar um pouco todo dia vale mais que muito de uma vez.</p>'),
               [('Fonte de 18px',          'estilo("p", "font-size")',  '18px',            True),
                ('Cor cinza escuro',       'estilo("p", "color")',      'rgb(51, 51, 51)', True),
                ('Texto centralizado',     'estilo("p", "text-align")', 'center',          False)],
               dificuldade='medio'),
    ])


# =====================================================================
# MÓDULO 3 — BOX MODEL
# =====================================================================

M3 = dict(
    titulo='Box Model',
    descricao='Margem, preenchimento, borda e largura: toda caixa da página.',
    teoria=(
        'Para o CSS, todo elemento é uma caixa com quatro camadas, de dentro para fora: o '
        'conteúdo (cujo tamanho é o width), o padding (espaço interno, entre o conteúdo e a '
        'borda), a border (a linha em volta) e a margin (espaço externo, que afasta a caixa '
        'das vizinhas).\n\n'
        'padding e margin aceitam um valor para os quatro lados (padding: 16px) ou lados '
        'separados (margin-top, margin-bottom...). A borda precisa de espessura, estilo e cor: '
        'border: 2px solid #ff6b00. Sem o estilo (solid, dashed...), a borda não aparece.\n\n'
        'O erro mais comum é esquecer a unidade: width: 300 não é 300px, é uma declaração '
        'inválida que o navegador ignora sem avisar. A única medida que dispensa unidade é o 0. '
        'Outro clássico é digitar o nome da propriedade errado, como pading ou widht.'
    ),
    atividades=[
        quiz('Espaço de dentro',
             'Qual propriedade cria espaço ENTRE o conteúdo e a borda de uma caixa?',
             [('margin', False), ('padding', True), ('border', False), ('width', False)],
             dica='Uma delas é o espaço de fora, que afasta as caixas vizinhas. Você quer a outra.'),

        codigo(COMPLETAR, 'Respiro na caixa',
               'Complete o CSS para que a caixa tenha padding de 20px em todos os lados. '
               'A borda de 2px já está pronta.',
               pagina('.caixa {\n  padding: ___;\n  border: 2px solid #ff6b00;\n}',
                      '<div class="caixa">Conteúdo com espaço para respirar.</div>'),
               'Um único valor no padding vale para os quatro lados. Não esqueça o px.',
               pagina('.caixa {\n  padding: 20px;\n  border: 2px solid #ff6b00;\n}',
                      '<div class="caixa">Conteúdo com espaço para respirar.</div>'),
               [('Padding em cima',      'estilo(".caixa", "padding-top")',      '20px', True),
                ('Borda de 2px',         'estilo(".caixa", "border-top-width")', '2px',  True),
                ('Padding na esquerda',  'estilo(".caixa", "padding-left")',     '20px', False)]),

        codigo(LIVRE, 'Montando um cartão',
               'Estilize a classe cartao com: largura (width) de 300px, padding de 16px, borda de 1px '
               'sólida (solid) na cor #cccccc e margem de baixo (margin-bottom) de 24px.',
               pagina('/* escreva seu CSS aqui */',
                      '<div class="cartao">Módulo 3: Box Model</div>\n<div class="cartao">Módulo 4: Flexbox</div>'),
               'Tudo cabe num bloco .cartao { }. A borda pode ser escrita numa linha só: border: 1px solid #cccccc;',
               pagina('.cartao {\n  width: 300px;\n  padding: 16px;\n  border: 1px solid #cccccc;\n  margin-bottom: 24px;\n}',
                      '<div class="cartao">Módulo 3: Box Model</div>\n<div class="cartao">Módulo 4: Flexbox</div>'),
               [('Largura de 300px',       'estilo(".cartao", "width")',            '300px',              True),
                ('Padding de 16px',        'estilo(".cartao", "padding-left")',     '16px',               True),
                ('Borda sólida',           'estilo(".cartao", "border-top-style")', 'solid',              True),
                ('Cor da borda',           'estilo(".cartao", "border-top-color")', 'rgb(204, 204, 204)', False),
                ('Margem de baixo',        'estilo(".cartao", "margin-bottom")',    '24px',               False)]),

        codigo(CORRIGIR, 'O botão desregulado',
               'O botão deveria ter padding de 12px em todos os lados e largura de 200px, mas nenhuma das '
               'duas coisas funciona. Há dois erros: um no nome de uma propriedade e outro numa medida.',
               pagina('.botao {\n  pading: 12px;\n  width: 200;\n  border: 2px solid #ff6b00;\n}',
                      '<div class="botao">Continuar</div>'),
               'Confira a grafia de padding letra por letra. E lembre: toda medida diferente de zero precisa de unidade.',
               pagina('.botao {\n  padding: 12px;\n  width: 200px;\n  border: 2px solid #ff6b00;\n}',
                      '<div class="botao">Continuar</div>'),
               [('Padding em cima',      'estilo(".botao", "padding-top")',   '12px',  True),
                ('Largura de 200px',     'estilo(".botao", "width")',         '200px', True),
                ('Padding na direita',   'estilo(".botao", "padding-right")', '12px',  False)],
               dificuldade='medio'),
    ])


# =====================================================================
# MÓDULO 4 — FLEXBOX
# =====================================================================

M4 = dict(
    titulo='Flexbox',
    descricao='Alinhar e distribuir elementos em linha ou em coluna.',
    teoria=(
        'Flexbox é o jeito moderno de organizar elementos lado a lado ou um embaixo do outro. '
        'Ao colocar display: flex num elemento, ele vira um contêiner flex e os filhos DIRETOS '
        'dele passam a ser organizados em linha. flex-direction: column troca a linha por coluna, '
        'e gap define o espaço entre os filhos.\n\n'
        'Duas propriedades fazem o alinhamento: justify-content distribui os filhos no eixo '
        'principal (na horizontal, quando a direção é linha) com valores como center e '
        'space-between; align-items alinha no eixo cruzado (na vertical, quando a direção é '
        'linha), com valores como center.\n\n'
        'O erro mais comum é colocar display: flex nos próprios itens, e não no elemento que '
        'os envolve. As propriedades de flex vão no PAI: é ele quem organiza os filhos. '
        'Colocadas nos filhos, elas só organizariam o que estiver dentro de cada um.'
    ),
    atividades=[
        quiz('Onde vai o flex',
             'Você quer colocar três <div class="item"> lado a lado. Eles estão dentro de uma '
             '<div class="linha">. Onde deve ir o display: flex?',
             [('Em .item', False), ('Em .linha', True), ('No body', False), ('Em cada item, com um id', False)],
             dica='O flex vai no elemento que organiza os filhos.'),

        codigo(COMPLETAR, 'Menu espalhado',
               'Complete o CSS para que o menu seja um contêiner flex e distribua os links com '
               'o espaço sobrando ENTRE eles (space-between).',
               pagina('.menu {\n  display: ___;\n  justify-content: ___;\n}',
                      '<nav class="menu">\n  <a href="#">Início</a>\n  <a href="#">Trilhas</a>\n  <a href="#">Perfil</a>\n</nav>'),
               'O primeiro espaço liga o flexbox. O segundo é o valor que joga o espaço para o meio dos itens.',
               pagina('.menu {\n  display: flex;\n  justify-content: space-between;\n}',
                      '<nav class="menu">\n  <a href="#">Início</a>\n  <a href="#">Trilhas</a>\n  <a href="#">Perfil</a>\n</nav>'),
               [('O menu é flex',         'estilo(".menu", "display")',         'flex',          True),
                ('Espaço entre os links', 'estilo(".menu", "justify-content")', 'space-between', False)]),

        codigo(LIVRE, 'Painel em coluna',
               'Faça a classe painel virar um contêiner flex em COLUNA (flex-direction: column), com os '
               'itens centralizados no eixo cruzado (align-items: center) e 12px de espaço entre eles (gap).',
               pagina('/* escreva seu CSS aqui */',
                      '<div class="painel">\n  <div>Nível 3</div>\n  <div>450 XP</div>\n  <div>7 dias seguidos</div>\n</div>'),
               'Quatro declarações no bloco .painel { }: display, flex-direction, align-items e gap.',
               pagina('.painel {\n  display: flex;\n  flex-direction: column;\n  align-items: center;\n  gap: 12px;\n}',
                      '<div class="painel">\n  <div>Nível 3</div>\n  <div>450 XP</div>\n  <div>7 dias seguidos</div>\n</div>'),
               [('O painel é flex',        'estilo(".painel", "display")',        'flex',   True),
                ('Direção em coluna',      'estilo(".painel", "flex-direction")', 'column', True),
                ('Itens centralizados',    'estilo(".painel", "align-items")',    'center', False),
                ('Espaço entre os itens',  'estilo(".painel", "row-gap")',        '12px',   False)]),

        codigo(CORRIGIR, 'Flex no lugar errado',
               'Os três itens deveriam ficar lado a lado, centralizados na horizontal (justify-content: '
               'center) e na vertical (align-items: center) dentro da linha. O CSS foi colocado no '
               'elemento errado. Corrija, deixando essas três declarações na classe linha.',
               pagina('.linha {\n  height: 120px;\n}\n\n.item {\n  display: flex;\n  justify-content: center;\n  align-items: center;\n}',
                      '<div class="linha">\n  <div class="item">HTML</div>\n  <div class="item">CSS</div>\n  <div class="item">JS</div>\n</div>'),
               'Quem organiza os filhos é o pai. As propriedades de flex precisam estar no elemento que envolve os itens.',
               pagina('.linha {\n  height: 120px;\n  display: flex;\n  justify-content: center;\n  align-items: center;\n}',
                      '<div class="linha">\n  <div class="item">HTML</div>\n  <div class="item">CSS</div>\n  <div class="item">JS</div>\n</div>'),
               [('A linha é flex',              'estilo(".linha", "display")',         'flex',   True),
                ('Centralizado na horizontal',  'estilo(".linha", "justify-content")', 'center', True),
                ('Centralizado na vertical',    'estilo(".linha", "align-items")',     'center', False)],
               dificuldade='medio'),
    ])


# =====================================================================
# PROVA
# =====================================================================

PROVA = dict(
    titulo='Prova: CSS',
    descricao='Avaliação dos módulos de seletores, texto, box model e flexbox. Acerte 70% para avançar.',
    questoes=[
        (0, 'Questão 1', 'O HTML tem <div id="topo">. Qual seletor pega esse elemento?',
         [('.topo', False), ('#topo', True), ('topo', False), ('*topo', False)]),
        (1, 'Questão 2', 'Por que esquecer o ponto e vírgula em "font-size: 18px" pode quebrar a linha seguinte?',
         [('Porque o navegador junta as duas linhas numa declaração inválida e descarta ambas', True),
          ('Porque o navegador apaga a regra inteira e todas as outras da página', False),
          ('Porque o ponto e vírgula é o que define o tamanho da fonte', False),
          ('Não quebra: o ponto e vírgula é opcional em qualquer linha', False)]),
        (2, 'Questão 3', 'Qual propriedade afasta uma caixa das caixas vizinhas, criando espaço do lado de FORA da borda?',
         [('padding', False), ('margin', True), ('width', False), ('gap-border', False)]),
        (2, 'Questão 4', 'O que acontece com a declaração "width: 300;"?',
         [('A largura vira 300px automaticamente', False),
          ('A largura vira 300% da página', False),
          ('Ela é inválida e o navegador a ignora', True),
          ('O navegador mostra uma mensagem de erro na página', False)]),
        (3, 'Questão 5', 'Num contêiner com display: flex em linha, qual propriedade distribui os itens na horizontal?',
         [('align-items', False), ('justify-content', True), ('text-align', False), ('flex-direction', False)]),
    ])


TRILHA = dict(titulo='CSS', descricao='Estilize suas páginas e crie layouts modernos.',
              nivel='iniciante', icone='css', linguagem='html',
              modulos=[M1, M2, M3, M4], prova=PROVA)
