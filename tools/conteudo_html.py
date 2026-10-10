# -*- coding: utf-8 -*-
"""tools/conteudo_html.py — trilha HTML (linguagem html: casos são verificações)."""
from conteudo_base import QUIZ, COMPLETAR, CORRIGIR, LIVRE, quiz, codigo  # noqa: F401


M1 = dict(
    titulo='Estrutura, Títulos e Parágrafos',
    descricao='Como um documento HTML é organizado e como escrever títulos e textos.',
    teoria=(
        'HTML é a linguagem que descreve o conteúdo de uma página. Ela é feita de tags: '
        'uma tag de abertura, como <p>, o conteúdo, e uma tag de fechamento com barra, como </p>. '
        'Juntas, elas formam um elemento: <p>Olá, mundo!</p> é um parágrafo.\n\n'
        'Todo documento completo começa com <!DOCTYPE html> e tem a tag <html>, que guarda o '
        '<head> (informações sobre a página, como o <title> que aparece na aba) e o <body> '
        '(tudo o que aparece na tela). Os títulos vão de <h1>, o mais importante, até <h6>, '
        'o menos importante, e os textos corridos ficam em <p>.\n\n'
        'O erro mais comum é escolher o título pelo tamanho da letra: usar <h3> porque "fica '
        'menor" ou <h1> em tudo para "ficar grande". O número indica a importância do título '
        'na estrutura da página. A aparência se ajusta depois, com CSS.'
    ),
    atividades=[
        quiz('O título principal',
             'Qual tag deve ser usada para o título mais importante de uma página?',
             [('<title>', False), ('<h1>', True), ('<h6>', False), ('<head>', False)],
             dica='<title> aparece na aba do navegador, não no conteúdo da página.'),

        codigo(COMPLETAR, 'Minha primeira página',
               'Complete o código trocando os ___ pela tag de título principal, para que o texto '
               '"Minha primeira página" vire um título h1. O parágrafo "Olá, mundo!" deve continuar abaixo dele.',
               '<___>Minha primeira página</___>\n<p>Olá, mundo!</p>',
               'O título mais importante da página usa a tag de número 1.',
               '<h1>Minha primeira página</h1>\n<p>Olá, mundo!</p>',
               [('O título h1 existe com o texto certo', 'texto("h1")', 'Minha primeira página', True),
                ('Existe exatamente um h1', 'contar("h1")', '1', True),
                ('O parágrafo continua lá', 'texto("p")', 'Olá, mundo!', False)]),

        codigo(LIVRE, 'Caderno de receitas',
               'Escreva um título h1 com o texto "Receitas da Vó" e, abaixo dele, dois parágrafos: '
               'o primeiro com "Bolo de cenoura" e o segundo com "Pudim de leite".',
               '<!-- escreva seu HTML aqui -->\n',
               'Cada parágrafo tem a sua própria tag <p> ... </p>.',
               '<h1>Receitas da Vó</h1>\n<p>Bolo de cenoura</p>\n<p>Pudim de leite</p>',
               [('O título h1 está correto', 'texto("h1")', 'Receitas da Vó', True),
                ('Existem dois parágrafos', 'contar("p")', '2', True),
                ('O primeiro parágrafo', 'texto("p:first-of-type")', 'Bolo de cenoura', False),
                ('O segundo parágrafo', 'texto("p:last-of-type")', 'Pudim de leite', False)]),

        codigo(CORRIGIR, 'Conserte os títulos',
               'Nesta página, "Blog do Dev" é o título principal e deveria estar num h1, e '
               '"Últimas postagens" é um subtítulo em h2. Alguém usou a tag errada no título principal. '
               'Corrija, sem mudar os textos.',
               '<h3>Blog do Dev</h3>\n<h2>Últimas postagens</h2>\n<p>Hoje aprendi a usar títulos em HTML.</p>',
               'O número da tag indica a importância, não o tamanho da letra. Qual é o número do título principal?',
               '<h1>Blog do Dev</h1>\n<h2>Últimas postagens</h2>\n<p>Hoje aprendi a usar títulos em HTML.</p>',
               [('O título principal está num h1', 'texto("h1")', 'Blog do Dev', True),
                ('O subtítulo continua em h2', 'texto("h2")', 'Últimas postagens', True),
                ('Não sobrou nenhum h3', 'contar("h3")', '0', False)],
               dificuldade='medio'),
    ])

M2 = dict(
    titulo='Listas e Links',
    descricao='Organizar itens em listas e ligar uma página a outra.',
    teoria=(
        'Listas agrupam itens relacionados. A <ul> é a lista não ordenada, com marcadores, '
        'boa para itens em que a ordem não importa, como uma lista de compras. A <ol> é a lista '
        'ordenada, numerada automaticamente, para passos em sequência. Nas duas, cada item fica '
        'dentro de um <li>: <ul><li>Arroz</li><li>Feijão</li></ul>.\n\n'
        'Links são criados com a tag <a>. O endereço de destino vai no atributo href, e o texto '
        'entre as tags é o que a pessoa clica: <a href="https://developer.mozilla.org">Documentação MDN</a>. '
        'O href pode ser um site completo ou outro arquivo do seu projeto, como "contato.html".\n\n'
        'Dois erros aparecem muito no começo: colocar <li> solto, fora de uma <ul> ou <ol>, e '
        'esquecer o href, o que deixa o link sem destino. Um <a> sem href parece texto comum e não leva a lugar nenhum.'
    ),
    atividades=[
        quiz('Lista numerada',
             'Qual tag cria uma lista em que os itens aparecem numerados (1, 2, 3...)?',
             [('<ul>', False), ('<ol>', True), ('<li>', False), ('<list>', False)],
             dica='Pense em "ordered list", lista ordenada.'),

        codigo(COMPLETAR, 'Link para a documentação',
               'Complete o código trocando o ___ pelo atributo que define o destino do link, para que '
               'o link "Documentação MDN" leve a https://developer.mozilla.org.',
               '<a ___="https://developer.mozilla.org">Documentação MDN</a>',
               'É o atributo de quatro letras que guarda o endereço do link.',
               '<a href="https://developer.mozilla.org">Documentação MDN</a>',
               [('O link aponta para a MDN', 'atributo("a", "href")', 'https://developer.mozilla.org', True),
                ('O texto do link continua o mesmo', 'texto("a")', 'Documentação MDN', False)]),

        codigo(LIVRE, 'Lista de compras',
               'Crie uma lista não ordenada (ul) com três itens, nesta ordem: "Arroz", "Feijão" e "Farofa". '
               'Não use lista numerada.',
               '<!-- escreva sua lista aqui -->\n',
               'Comece com <ul> e coloque cada item dentro de um <li>.',
               '<ul>\n  <li>Arroz</li>\n  <li>Feijão</li>\n  <li>Farofa</li>\n</ul>',
               [('A lista tem três itens', 'contar("ul > li")', '3', True),
                ('O primeiro item é Arroz', 'texto("ul > li")', 'Arroz', True),
                ('O último item é Farofa', 'texto("ul > li:last-child")', 'Farofa', False),
                ('Não há lista numerada', 'existe("ol")', 'não', False)]),

        codigo(CORRIGIR, 'Passo a passo numerado',
               'Os passos para fazer café precisam aparecer numerados, porque a ordem importa. '
               'Corrija o tipo de lista, mantendo os três itens e seus textos.',
               '<h2>Como fazer café</h2>\n<ul>\n  <li>Ferva a água</li>\n  <li>Coloque o pó no filtro</li>\n'
               '  <li>Despeje a água sobre o pó</li>\n</ul>',
               'Existe uma lista com marcadores e outra numerada. Qual das duas é para passos em sequência?',
               '<h2>Como fazer café</h2>\n<ol>\n  <li>Ferva a água</li>\n  <li>Coloque o pó no filtro</li>\n'
               '  <li>Despeje a água sobre o pó</li>\n</ol>',
               [('A lista numerada tem três passos', 'contar("ol > li")', '3', True),
                ('O primeiro passo está certo', 'texto("ol > li")', 'Ferva a água', True),
                ('Não sobrou lista com marcadores', 'existe("ul")', 'não', False)],
               dificuldade='medio'),
    ])

M3 = dict(
    titulo='Imagens e Atributos',
    descricao='Colocar imagens na página e configurá-las com atributos.',
    teoria=(
        'Atributos são informações extras que ficam dentro da tag de abertura, no formato '
        'nome="valor". Você já viu o href do link. A imagem usa outros: '
        '<img src="raposa.png" alt="Uma raposa laranja" width="200">.\n\n'
        'A tag <img> não tem fechamento. O src diz qual arquivo mostrar, o alt descreve a imagem '
        'em texto e o width define a largura em pixels. O alt não é enfeite: é ele que os leitores '
        'de tela leem para pessoas cegas, e é ele que aparece quando a imagem não carrega.\n\n'
        'O erro mais comum é um erro de digitação no nome do atributo, como escrever scr em vez de src. '
        'O navegador não reclama: ele só ignora o atributo que não conhece, e a imagem simplesmente não aparece.'
    ),
    atividades=[
        quiz('Para que serve o alt',
             'Qual é a função do atributo alt em uma imagem?',
             [('Definir a largura da imagem', False),
              ('Descrever a imagem em texto, para leitores de tela e quando ela não carrega', True),
              ('Indicar o endereço do arquivo da imagem', False),
              ('Fazer a imagem virar um link', False)],
             dica='Pense em quem não consegue ver a imagem.'),

        codigo(COMPLETAR, 'Mostrando a raposa',
               'Complete o código trocando o ___ pelo atributo que indica o arquivo da imagem, para que '
               'ela mostre o arquivo "raposa.png". O texto alternativo "Uma raposa laranja" deve continuar.',
               '<img ___="raposa.png" alt="Uma raposa laranja">',
               'É o atributo que diz de onde vem a imagem (source, em inglês).',
               '<img src="raposa.png" alt="Uma raposa laranja">',
               [('A imagem aponta para raposa.png', 'atributo("img", "src")', 'raposa.png', True),
                ('O texto alternativo continua', 'atributo("img", "alt")', 'Uma raposa laranja', False)]),

        codigo(LIVRE, 'O logo da página',
               'Crie uma imagem com o arquivo "logo.png", o texto alternativo "Logo do DevFox" '
               'e a largura de 200 pixels (atributo width="200").',
               '<!-- escreva sua imagem aqui -->\n',
               'Todos os atributos ficam dentro da mesma tag <img>, separados por espaço.',
               '<img src="logo.png" alt="Logo do DevFox" width="200">',
               [('Existe uma imagem', 'existe("img")', 'sim', True),
                ('O texto alternativo está certo', 'atributo("img", "alt")', 'Logo do DevFox', True),
                ('A largura é 200', 'atributo("img", "width")', '200', False),
                ('O arquivo é logo.png', 'atributo("img", "src")', 'logo.png', False)]),

        codigo(CORRIGIR, 'A foto que não aparece',
               'A foto do gato deveria aparecer e, ao ser clicada, levar para "galeria.html". '
               'Mas a imagem não carrega. Encontre o erro e corrija, mantendo o link e o texto alternativo.',
               '<a href="galeria.html">\n  <img scr="gato.jpg" alt="Gato dormindo no sofá">\n</a>',
               'Leia com atenção o nome de cada atributo da imagem, letra por letra.',
               '<a href="galeria.html">\n  <img src="gato.jpg" alt="Gato dormindo no sofá">\n</a>',
               [('A imagem aponta para gato.jpg', 'atributo("img", "src")', 'gato.jpg', True),
                ('A imagem continua dentro do link', 'existe("a > img")', 'sim', False),
                ('O link leva para a galeria', 'atributo("a", "href")', 'galeria.html', False)],
               dificuldade='medio'),
    ])

M4 = dict(
    titulo='Tags Semânticas e Formulários',
    descricao='Dar significado às partes da página e receber dados do usuário.',
    teoria=(
        'Tags semânticas dizem o que cada parte da página é. <header> é o cabeçalho, <nav> guarda '
        'os links de navegação, <main> é o conteúdo principal e <footer> é o rodapé. Na tela elas '
        'parecem uma <div> qualquer, mas ajudam leitores de tela, buscadores e quem vai ler o seu código.\n\n'
        'Formulários recebem dados. O <input> é o campo, e o atributo type muda o tipo: "text" para '
        'texto, "email" para e-mail, "password" para senha. O <label> é o rótulo do campo, e se liga '
        'a ele pelo atributo for, que precisa ter o mesmo valor do id do input: '
        '<label for="email">E-mail</label> <input type="email" id="email">. Um <button> envia o formulário.\n\n'
        'O erro mais comum é o for do label não bater com o id do input. Nada quebra na tela, mas '
        'clicar no rótulo deixa de selecionar o campo e o leitor de tela não sabe qual rótulo é de qual campo.'
    ),
    atividades=[
        quiz('O conteúdo principal',
             'Qual tag semântica deve envolver o conteúdo principal da página?',
             [('<header>', False), ('<main>', True), ('<footer>', False), ('<nav>', False)],
             dica='Procure a palavra que significa "principal" em inglês.'),

        codigo(COMPLETAR, 'Campo de e-mail',
               'Complete o código trocando o ___ pelo tipo de campo certo para e-mail. '
               'O label "E-mail" deve continuar ligado ao campo de id "email".',
               '<label for="email">E-mail</label>\n<input type="___" id="email">',
               'O valor do type para e-mail é a própria palavra, em inglês e em minúsculas.',
               '<label for="email">E-mail</label>\n<input type="email" id="email">',
               [('O campo é do tipo email', 'atributo("input", "type")', 'email', True),
                ('O label aponta para o campo', 'atributo("label", "for")', 'email', False)]),

        codigo(LIVRE, 'Esqueleto da loja',
               'Monte a estrutura de uma página com três partes semânticas, nesta ordem: um header com '
               'um h1 "Minha Loja"; um main com um parágrafo "Produtos em destaque"; e um footer com '
               'um parágrafo "Contato: loja@exemplo.com".',
               '<!-- escreva a estrutura aqui -->\n',
               'Cada parte é uma tag (<header>, <main>, <footer>) com o seu conteúdo dentro.',
               '<header>\n  <h1>Minha Loja</h1>\n</header>\n<main>\n  <p>Produtos em destaque</p>\n</main>\n'
               '<footer>\n  <p>Contato: loja@exemplo.com</p>\n</footer>',
               [('O título está dentro do header', 'texto("header h1")', 'Minha Loja', True),
                ('O parágrafo está dentro do main', 'texto("main p")', 'Produtos em destaque', True),
                ('O rodapé tem o contato', 'texto("footer p")', 'Contato: loja@exemplo.com', False)]),

        codigo(CORRIGIR, 'O rótulo perdido',
               'O rótulo "Nome" deveria estar ligado ao campo de texto, mas o for do label e o id do input '
               'não batem. O label está certo: corrija o id do campo para "nome". Mantenha o botão "Enviar".',
               '<form>\n  <label for="nome">Nome</label>\n  <input type="text" id="usuario">\n'
               '  <button>Enviar</button>\n</form>',
               'O valor do for do label precisa ser exatamente igual ao id do input.',
               '<form>\n  <label for="nome">Nome</label>\n  <input type="text" id="nome">\n'
               '  <button>Enviar</button>\n</form>',
               [('O campo tem o id nome', 'atributo("input", "id")', 'nome', True),
                ('Existe um campo com id nome', 'existe("input#nome")', 'sim', False),
                ('O botão continua lá', 'texto("button")', 'Enviar', False)],
               dificuldade='medio'),
    ])

PROVA = dict(
    titulo='Prova: HTML',
    descricao='Avaliação dos módulos de estrutura, listas e links, imagens e formulários. Acerte 70% para avançar.',
    questoes=[
        (0, 'Questão 1', 'Em qual parte do documento fica o conteúdo que aparece na tela?',
         [('<head>', False), ('<body>', True), ('<title>', False), ('<!DOCTYPE html>', False)]),
        (1, 'Questão 2', 'Qual atributo define o destino de um link <a>?',
         [('src', False), ('href', True), ('link', False), ('alt', False)]),
        (1, 'Questão 3', 'Qual tag representa cada item de uma lista <ul> ou <ol>?',
         [('<item>', False), ('<li>', True), ('<p>', False), ('<list>', False)]),
        (2, 'Questão 4', 'O que acontece com <img scr="foto.png"> (com src escrito errado)?',
         [('O navegador corrige sozinho e mostra a foto', False),
          ('A imagem não aparece, porque o atributo é ignorado', True),
          ('A página inteira para de funcionar', False),
          ('A foto aparece, mas sem texto alternativo', False)]),
        (3, 'Questão 5', 'Para ligar <label for="idade"> a um campo, o que o <input> precisa ter?',
         [('name="label"', False), ('id="idade"', True), ('type="idade"', False), ('for="idade"', False)]),
    ])

TRILHA = dict(titulo='HTML', descricao='Estrutura e conteúdo de páginas web com HTML.',
              nivel='iniciante', icone='html', linguagem='html',
              modulos=[M1, M2, M3, M4], prova=PROVA)
