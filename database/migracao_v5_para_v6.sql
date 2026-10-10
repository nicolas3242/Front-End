-- =====================================================================
-- DevFox — Migração do schema v5 para v6
-- =====================================================================
-- Use se você JÁ TEM o banco na v5 e não quer perder o progresso dos alunos.
-- Para criar do zero, use `banco.sql`, que já vem na v6.
--
-- ⚠ FAÇA BACKUP ANTES. ⚠ RODE UMA VEZ SÓ (não é idempotente).
-- ⚠ Se você criou trilhas, módulos ou atividades pela Administração, esta
--   migração pode falhar por id repetido. Nesse caso use banco.sql.
--
-- O que muda na v6:
--   - trilhas ganha linguagem (javascript/html), subtitulo e destaque;
--   - tabela trilha_requisitos (trilha que só abre depois de outras);
--   - 5 trilhas novas: HTML, CSS, Funções e Métodos, Desafios Práticos e
--     Projeto Final (este exige todas as outras concluídas);
--   - a ordem de exibição segue o wireframe de Trilhas.
-- =====================================================================

SET NAMES utf8mb4;

USE devfox;


ALTER TABLE trilhas
    ADD COLUMN linguagem ENUM('javascript','html') NOT NULL DEFAULT 'javascript' AFTER publicada,
    ADD COLUMN subtitulo VARCHAR(80) NULL AFTER linguagem,
    ADD COLUMN destaque  TINYINT(1)  NOT NULL DEFAULT 0 AFTER subtitulo;

-- ---------------------------------------------------------------------
-- 16. trilha_requisitos  (v6)
-- ---------------------------------------------------------------------
-- Uma trilha pode exigir que outras estejam CONCLUÍDAS antes de abrir (ex.: o
-- Projeto Final exige todas as outras). Tabela própria, e não uma coluna
-- "requisito_id" em trilhas, porque uma trilha pode ter vários requisitos.
--
-- Quem aplica a regra é estadoDosModulos() (includes/auth.php): trilha com
-- requisito pendente tem TODOS os módulos bloqueados — inclusive pela URL.
-- ---------------------------------------------------------------------
CREATE TABLE trilha_requisitos (
    trilha_id    INT NOT NULL,
    requisito_id INT NOT NULL,

    PRIMARY KEY (trilha_id, requisito_id),
    CONSTRAINT fk_req_trilha FOREIGN KEY (trilha_id)
        REFERENCES trilhas(id) ON DELETE CASCADE,
    CONSTRAINT fk_req_requisito FOREIGN KEY (requisito_id)
        REFERENCES trilhas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Ordem de exibição do wireframe: Lógica, HTML, CSS, JS Básico, Repetição...
UPDATE trilhas SET ordem = 1 WHERE id = 1;
UPDATE trilhas SET ordem = 4 WHERE id = 2;
UPDATE trilhas SET ordem = 5 WHERE id = 3;

INSERT INTO trilhas (id, titulo, descricao, nivel_dificuldade, icone, ordem, publicada, linguagem, subtitulo, destaque) VALUES
(4, 'HTML', 'Estrutura e conteúdo de páginas web com HTML.', 'iniciante', 'html', 2, 1, 'html', 'Estrutura e Conteúdo', 1),
(5, 'CSS', 'Estilize suas páginas e crie layouts modernos.', 'iniciante', 'css', 3, 1, 'html', 'Estilo e Apresentação', 1),
(6, 'Funções e Métodos', 'Organize seu código em funções e métodos reutilizáveis.', 'intermediario', 'funcao', 6, 1, 'javascript', NULL, 0),
(7, 'Desafios Práticos', 'Resolva desafios práticos e aplique seus conhecimentos.', 'avancado', 'desafios', 7, 1, 'javascript', NULL, 0),
(8, 'Projeto Final', 'Desenvolva um projeto completo aplicando tudo que aprendeu.', 'avancado', 'projeto', 8, 1, 'javascript', NULL, 0);

INSERT INTO trilha_requisitos (trilha_id, requisito_id) VALUES
(8, 1),
(8, 4),
(8, 5),
(8, 2),
(8, 3),
(8, 6),
(8, 7);


-- =====================================================================
-- TRILHA 4: HTML
-- =====================================================================

INSERT INTO modulos (id, trilha_id, titulo, descricao, teoria_texto, ordem) VALUES
(16, 4, 'Estrutura, Títulos e Parágrafos',
    'Como um documento HTML é organizado e como escrever títulos e textos.',
    'HTML é a linguagem que descreve o conteúdo de uma página. Ela é feita de tags: uma tag de abertura, como <p>, o conteúdo, e uma tag de fechamento com barra, como </p>. Juntas, elas formam um elemento: <p>Olá, mundo!</p> é um parágrafo.\n\nTodo documento completo começa com <!DOCTYPE html> e tem a tag <html>, que guarda o <head> (informações sobre a página, como o <title> que aparece na aba) e o <body> (tudo o que aparece na tela). Os títulos vão de <h1>, o mais importante, até <h6>, o menos importante, e os textos corridos ficam em <p>.\n\nO erro mais comum é escolher o título pelo tamanho da letra: usar <h3> porque "fica menor" ou <h1> em tudo para "ficar grande". O número indica a importância do título na estrutura da página. A aparência se ajusta depois, com CSS.',
    1),
(17, 4, 'Listas e Links',
    'Organizar itens em listas e ligar uma página a outra.',
    'Listas agrupam itens relacionados. A <ul> é a lista não ordenada, com marcadores, boa para itens em que a ordem não importa, como uma lista de compras. A <ol> é a lista ordenada, numerada automaticamente, para passos em sequência. Nas duas, cada item fica dentro de um <li>: <ul><li>Arroz</li><li>Feijão</li></ul>.\n\nLinks são criados com a tag <a>. O endereço de destino vai no atributo href, e o texto entre as tags é o que a pessoa clica: <a href="https://developer.mozilla.org">Documentação MDN</a>. O href pode ser um site completo ou outro arquivo do seu projeto, como "contato.html".\n\nDois erros aparecem muito no começo: colocar <li> solto, fora de uma <ul> ou <ol>, e esquecer o href, o que deixa o link sem destino. Um <a> sem href parece texto comum e não leva a lugar nenhum.',
    2),
(18, 4, 'Imagens e Atributos',
    'Colocar imagens na página e configurá-las com atributos.',
    'Atributos são informações extras que ficam dentro da tag de abertura, no formato nome="valor". Você já viu o href do link. A imagem usa outros: <img src="raposa.png" alt="Uma raposa laranja" width="200">.\n\nA tag <img> não tem fechamento. O src diz qual arquivo mostrar, o alt descreve a imagem em texto e o width define a largura em pixels. O alt não é enfeite: é ele que os leitores de tela leem para pessoas cegas, e é ele que aparece quando a imagem não carrega.\n\nO erro mais comum é um erro de digitação no nome do atributo, como escrever scr em vez de src. O navegador não reclama: ele só ignora o atributo que não conhece, e a imagem simplesmente não aparece.',
    3),
(19, 4, 'Tags Semânticas e Formulários',
    'Dar significado às partes da página e receber dados do usuário.',
    'Tags semânticas dizem o que cada parte da página é. <header> é o cabeçalho, <nav> guarda os links de navegação, <main> é o conteúdo principal e <footer> é o rodapé. Na tela elas parecem uma <div> qualquer, mas ajudam leitores de tela, buscadores e quem vai ler o seu código.\n\nFormulários recebem dados. O <input> é o campo, e o atributo type muda o tipo: "text" para texto, "email" para e-mail, "password" para senha. O <label> é o rótulo do campo, e se liga a ele pelo atributo for, que precisa ter o mesmo valor do id do input: <label for="email">E-mail</label> <input type="email" id="email">. Um <button> envia o formulário.\n\nO erro mais comum é o for do label não bater com o id do input. Nada quebra na tela, mas clicar no rótulo deixa de selecionar o campo e o leitor de tela não sabe qual rótulo é de qual campo.',
    4);

-- Módulo 16: Estrutura, Títulos e Parágrafos
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(64, 16, 'O título principal',
    'Qual tag deve ser usada para o título mais importante de uma página?',
    'quiz', 'facil', '<title> aparece na aba do navegador, não no conteúdo da página.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(64, '<title>', 0, 1),
(64, '<h1>', 1, 2),
(64, '<h6>', 0, 3),
(64, '<head>', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(65, 16, 'Minha primeira página',
    'Complete o código trocando os ___ pela tag de título principal, para que o texto "Minha primeira página" vire um título h1. O parágrafo "Olá, mundo!" deve continuar abaixo dele.',
    'completar', 'facil',
    '<___>Minha primeira página</___>\n<p>Olá, mundo!</p>',
    'O título mais importante da página usa a tag de número 1.',
    '<h1>Minha primeira página</h1>\n<p>Olá, mundo!</p>',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(65, 'O título h1 existe com o texto certo', 'texto("h1")', 'Minha primeira página', 1, 1),
(65, 'Existe exatamente um h1', 'contar("h1")', '1', 1, 2),
(65, 'O parágrafo continua lá', 'texto("p")', 'Olá, mundo!', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(66, 16, 'Caderno de receitas',
    'Escreva um título h1 com o texto "Receitas da Vó" e, abaixo dele, dois parágrafos: o primeiro com "Bolo de cenoura" e o segundo com "Pudim de leite".',
    'codigo_livre', 'facil',
    '<!-- escreva seu HTML aqui -->\n',
    'Cada parágrafo tem a sua própria tag <p> ... </p>.',
    '<h1>Receitas da Vó</h1>\n<p>Bolo de cenoura</p>\n<p>Pudim de leite</p>',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(66, 'O título h1 está correto', 'texto("h1")', 'Receitas da Vó', 1, 1),
(66, 'Existem dois parágrafos', 'contar("p")', '2', 1, 2),
(66, 'O primeiro parágrafo', 'texto("p:first-of-type")', 'Bolo de cenoura', 0, 3),
(66, 'O segundo parágrafo', 'texto("p:last-of-type")', 'Pudim de leite', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(67, 16, 'Conserte os títulos',
    'Nesta página, "Blog do Dev" é o título principal e deveria estar num h1, e "Últimas postagens" é um subtítulo em h2. Alguém usou a tag errada no título principal. Corrija, sem mudar os textos.',
    'corrigir_erro', 'medio',
    '<h3>Blog do Dev</h3>\n<h2>Últimas postagens</h2>\n<p>Hoje aprendi a usar títulos em HTML.</p>',
    'O número da tag indica a importância, não o tamanho da letra. Qual é o número do título principal?',
    '<h1>Blog do Dev</h1>\n<h2>Últimas postagens</h2>\n<p>Hoje aprendi a usar títulos em HTML.</p>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(67, 'O título principal está num h1', 'texto("h1")', 'Blog do Dev', 1, 1),
(67, 'O subtítulo continua em h2', 'texto("h2")', 'Últimas postagens', 1, 2),
(67, 'Não sobrou nenhum h3', 'contar("h3")', '0', 0, 3);


-- Módulo 17: Listas e Links
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(68, 17, 'Lista numerada',
    'Qual tag cria uma lista em que os itens aparecem numerados (1, 2, 3...)?',
    'quiz', 'facil', 'Pense em "ordered list", lista ordenada.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(68, '<ul>', 0, 1),
(68, '<ol>', 1, 2),
(68, '<li>', 0, 3),
(68, '<list>', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(69, 17, 'Link para a documentação',
    'Complete o código trocando o ___ pelo atributo que define o destino do link, para que o link "Documentação MDN" leve a https://developer.mozilla.org.',
    'completar', 'facil',
    '<a ___="https://developer.mozilla.org">Documentação MDN</a>',
    'É o atributo de quatro letras que guarda o endereço do link.',
    '<a href="https://developer.mozilla.org">Documentação MDN</a>',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(69, 'O link aponta para a MDN', 'atributo("a", "href")', 'https://developer.mozilla.org', 1, 1),
(69, 'O texto do link continua o mesmo', 'texto("a")', 'Documentação MDN', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(70, 17, 'Lista de compras',
    'Crie uma lista não ordenada (ul) com três itens, nesta ordem: "Arroz", "Feijão" e "Farofa". Não use lista numerada.',
    'codigo_livre', 'facil',
    '<!-- escreva sua lista aqui -->\n',
    'Comece com <ul> e coloque cada item dentro de um <li>.',
    '<ul>\n  <li>Arroz</li>\n  <li>Feijão</li>\n  <li>Farofa</li>\n</ul>',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(70, 'A lista tem três itens', 'contar("ul > li")', '3', 1, 1),
(70, 'O primeiro item é Arroz', 'texto("ul > li")', 'Arroz', 1, 2),
(70, 'O último item é Farofa', 'texto("ul > li:last-child")', 'Farofa', 0, 3),
(70, 'Não há lista numerada', 'existe("ol")', 'não', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(71, 17, 'Passo a passo numerado',
    'Os passos para fazer café precisam aparecer numerados, porque a ordem importa. Corrija o tipo de lista, mantendo os três itens e seus textos.',
    'corrigir_erro', 'medio',
    '<h2>Como fazer café</h2>\n<ul>\n  <li>Ferva a água</li>\n  <li>Coloque o pó no filtro</li>\n  <li>Despeje a água sobre o pó</li>\n</ul>',
    'Existe uma lista com marcadores e outra numerada. Qual das duas é para passos em sequência?',
    '<h2>Como fazer café</h2>\n<ol>\n  <li>Ferva a água</li>\n  <li>Coloque o pó no filtro</li>\n  <li>Despeje a água sobre o pó</li>\n</ol>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(71, 'A lista numerada tem três passos', 'contar("ol > li")', '3', 1, 1),
(71, 'O primeiro passo está certo', 'texto("ol > li")', 'Ferva a água', 1, 2),
(71, 'Não sobrou lista com marcadores', 'existe("ul")', 'não', 0, 3);


-- Módulo 18: Imagens e Atributos
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(72, 18, 'Para que serve o alt',
    'Qual é a função do atributo alt em uma imagem?',
    'quiz', 'facil', 'Pense em quem não consegue ver a imagem.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(72, 'Definir a largura da imagem', 0, 1),
(72, 'Descrever a imagem em texto, para leitores de tela e quando ela não carrega', 1, 2),
(72, 'Indicar o endereço do arquivo da imagem', 0, 3),
(72, 'Fazer a imagem virar um link', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(73, 18, 'Mostrando a raposa',
    'Complete o código trocando o ___ pelo atributo que indica o arquivo da imagem, para que ela mostre o arquivo "raposa.png". O texto alternativo "Uma raposa laranja" deve continuar.',
    'completar', 'facil',
    '<img ___="raposa.png" alt="Uma raposa laranja">',
    'É o atributo que diz de onde vem a imagem (source, em inglês).',
    '<img src="raposa.png" alt="Uma raposa laranja">',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(73, 'A imagem aponta para raposa.png', 'atributo("img", "src")', 'raposa.png', 1, 1),
(73, 'O texto alternativo continua', 'atributo("img", "alt")', 'Uma raposa laranja', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(74, 18, 'O logo da página',
    'Crie uma imagem com o arquivo "logo.png", o texto alternativo "Logo do DevFox" e a largura de 200 pixels (atributo width="200").',
    'codigo_livre', 'facil',
    '<!-- escreva sua imagem aqui -->\n',
    'Todos os atributos ficam dentro da mesma tag <img>, separados por espaço.',
    '<img src="logo.png" alt="Logo do DevFox" width="200">',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(74, 'Existe uma imagem', 'existe("img")', 'sim', 1, 1),
(74, 'O texto alternativo está certo', 'atributo("img", "alt")', 'Logo do DevFox', 1, 2),
(74, 'A largura é 200', 'atributo("img", "width")', '200', 0, 3),
(74, 'O arquivo é logo.png', 'atributo("img", "src")', 'logo.png', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(75, 18, 'A foto que não aparece',
    'A foto do gato deveria aparecer e, ao ser clicada, levar para "galeria.html". Mas a imagem não carrega. Encontre o erro e corrija, mantendo o link e o texto alternativo.',
    'corrigir_erro', 'medio',
    '<a href="galeria.html">\n  <img scr="gato.jpg" alt="Gato dormindo no sofá">\n</a>',
    'Leia com atenção o nome de cada atributo da imagem, letra por letra.',
    '<a href="galeria.html">\n  <img src="gato.jpg" alt="Gato dormindo no sofá">\n</a>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(75, 'A imagem aponta para gato.jpg', 'atributo("img", "src")', 'gato.jpg', 1, 1),
(75, 'A imagem continua dentro do link', 'existe("a > img")', 'sim', 0, 2),
(75, 'O link leva para a galeria', 'atributo("a", "href")', 'galeria.html', 0, 3);


-- Módulo 19: Tags Semânticas e Formulários
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(76, 19, 'O conteúdo principal',
    'Qual tag semântica deve envolver o conteúdo principal da página?',
    'quiz', 'facil', 'Procure a palavra que significa "principal" em inglês.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(76, '<header>', 0, 1),
(76, '<main>', 1, 2),
(76, '<footer>', 0, 3),
(76, '<nav>', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(77, 19, 'Campo de e-mail',
    'Complete o código trocando o ___ pelo tipo de campo certo para e-mail. O label "E-mail" deve continuar ligado ao campo de id "email".',
    'completar', 'facil',
    '<label for="email">E-mail</label>\n<input type="___" id="email">',
    'O valor do type para e-mail é a própria palavra, em inglês e em minúsculas.',
    '<label for="email">E-mail</label>\n<input type="email" id="email">',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(77, 'O campo é do tipo email', 'atributo("input", "type")', 'email', 1, 1),
(77, 'O label aponta para o campo', 'atributo("label", "for")', 'email', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(78, 19, 'Esqueleto da loja',
    'Monte a estrutura de uma página com três partes semânticas, nesta ordem: um header com um h1 "Minha Loja"; um main com um parágrafo "Produtos em destaque"; e um footer com um parágrafo "Contato: loja@exemplo.com".',
    'codigo_livre', 'facil',
    '<!-- escreva a estrutura aqui -->\n',
    'Cada parte é uma tag (<header>, <main>, <footer>) com o seu conteúdo dentro.',
    '<header>\n  <h1>Minha Loja</h1>\n</header>\n<main>\n  <p>Produtos em destaque</p>\n</main>\n<footer>\n  <p>Contato: loja@exemplo.com</p>\n</footer>',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(78, 'O título está dentro do header', 'texto("header h1")', 'Minha Loja', 1, 1),
(78, 'O parágrafo está dentro do main', 'texto("main p")', 'Produtos em destaque', 1, 2),
(78, 'O rodapé tem o contato', 'texto("footer p")', 'Contato: loja@exemplo.com', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(79, 19, 'O rótulo perdido',
    'O rótulo "Nome" deveria estar ligado ao campo de texto, mas o for do label e o id do input não batem. O label está certo: corrija o id do campo para "nome". Mantenha o botão "Enviar".',
    'corrigir_erro', 'medio',
    '<form>\n  <label for="nome">Nome</label>\n  <input type="text" id="usuario">\n  <button>Enviar</button>\n</form>',
    'O valor do for do label precisa ser exatamente igual ao id do input.',
    '<form>\n  <label for="nome">Nome</label>\n  <input type="text" id="nome">\n  <button>Enviar</button>\n</form>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(79, 'O campo tem o id nome', 'atributo("input", "id")', 'nome', 1, 1),
(79, 'Existe um campo com id nome', 'existe("input#nome")', 'sim', 0, 2),
(79, 'O botão continua lá', 'texto("button")', 'Enviar', 0, 3);


-- ---------------------------------------------------------------------
-- Prova de fim de nível da trilha 4
-- ---------------------------------------------------------------------
-- Cada questão aponta, por `modulo_referencia_id`, o módulo que ela avalia —
-- é assim que o sistema diz ao aluno reprovado O QUE revisar, em vez de
-- mandá-lo refazer tudo.
-- ---------------------------------------------------------------------
INSERT INTO modulos (id, trilha_id, titulo, tipo, nota_minima, descricao, ordem) VALUES
(20, 4, 'Prova: HTML', 'prova', 70,
    'Avaliação dos módulos de estrutura, listas e links, imagens e formulários. Acerte 70% para avançar.',
    5);

INSERT INTO atividades (id, modulo_id, modulo_referencia_id, titulo, enunciado, tipo, dificuldade, xp_recompensa, ordem) VALUES
(80, 20, 16, 'Questão 1', 'Em qual parte do documento fica o conteúdo que aparece na tela?', 'quiz', 'facil', 50, 1),
(81, 20, 17, 'Questão 2', 'Qual atributo define o destino de um link <a>?', 'quiz', 'facil', 50, 2),
(82, 20, 17, 'Questão 3', 'Qual tag representa cada item de uma lista <ul> ou <ol>?', 'quiz', 'facil', 50, 3),
(83, 20, 18, 'Questão 4', 'O que acontece com <img scr="foto.png"> (com src escrito errado)?', 'quiz', 'medio', 50, 4),
(84, 20, 19, 'Questão 5', 'Para ligar <label for="idade"> a um campo, o que o <input> precisa ter?', 'quiz', 'medio', 50, 5);

INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(80, '<head>', 0, 1),
(80, '<body>', 1, 2),
(80, '<title>', 0, 3),
(80, '<!DOCTYPE html>', 0, 4),
(81, 'src', 0, 1),
(81, 'href', 1, 2),
(81, 'link', 0, 3),
(81, 'alt', 0, 4),
(82, '<item>', 0, 1),
(82, '<li>', 1, 2),
(82, '<p>', 0, 3),
(82, '<list>', 0, 4),
(83, 'O navegador corrige sozinho e mostra a foto', 0, 1),
(83, 'A imagem não aparece, porque o atributo é ignorado', 1, 2),
(83, 'A página inteira para de funcionar', 0, 3),
(83, 'A foto aparece, mas sem texto alternativo', 0, 4),
(84, 'name="label"', 0, 1),
(84, 'id="idade"', 1, 2),
(84, 'type="idade"', 0, 3),
(84, 'for="idade"', 0, 4);


-- =====================================================================
-- TRILHA 5: CSS
-- =====================================================================

INSERT INTO modulos (id, trilha_id, titulo, descricao, teoria_texto, ordem) VALUES
(21, 5, 'Seletores e Cores',
    'Escolher quais elementos estilizar e mudar suas cores.',
    'Uma regra CSS tem duas partes: o seletor, que diz QUAIS elementos serão estilizados, e o bloco entre chaves, com as declarações no formato propriedade: valor;. Em h1 { color: #ff6b00; }, o seletor é h1 e a declaração pinta o texto de laranja.\n\nOs três seletores básicos são: o nome da tag (p pega todos os parágrafos), a classe com ponto na frente (.destaque pega todo elemento com class="destaque") e o id com cerquilha (#topo pega o elemento com id="topo"). A propriedade color muda a cor do texto; background-color muda a cor de fundo. As cores podem ser escritas em hexadecimal (#ff6b00), por nome (white) ou em rgb(255, 107, 0).\n\nO erro mais comum de quem começa é trocar o ponto pela cerquilha: escrever .topo quando o HTML tem id="topo". O navegador não avisa nada, a regra simplesmente não encontra ninguém e a página continua sem estilo.',
    1),
(22, 5, 'Texto e Fontes',
    'Tamanho, peso, alinhamento e espaçamento do texto.',
    'Quatro propriedades resolvem a maior parte da tipografia: font-size define o tamanho (24px, 1.5rem), font-weight define o peso (normal ou bold, que equivale a 700), text-align alinha o texto dentro do elemento (left, center, right) e line-height controla a distância entre as linhas.\n\nSobre as unidades: px é um tamanho fixo na tela; rem é relativo ao tamanho de fonte da página, que por padrão é 16px. Então 1.5rem vale 24px e 2rem vale 32px. Usar rem facilita respeitar quem aumentou a fonte no navegador.\n\nO erro mais comum aqui é esquecer o ponto e vírgula no fim de uma declaração. Sem ele, o navegador junta aquela linha com a próxima, não entende o resultado e descarta as DUAS declarações — e o erro aparece numa propriedade que estava certa.',
    2),
(23, 5, 'Box Model',
    'Margem, preenchimento, borda e largura: toda caixa da página.',
    'Para o CSS, todo elemento é uma caixa com quatro camadas, de dentro para fora: o conteúdo (cujo tamanho é o width), o padding (espaço interno, entre o conteúdo e a borda), a border (a linha em volta) e a margin (espaço externo, que afasta a caixa das vizinhas).\n\npadding e margin aceitam um valor para os quatro lados (padding: 16px) ou lados separados (margin-top, margin-bottom...). A borda precisa de espessura, estilo e cor: border: 2px solid #ff6b00. Sem o estilo (solid, dashed...), a borda não aparece.\n\nO erro mais comum é esquecer a unidade: width: 300 não é 300px, é uma declaração inválida que o navegador ignora sem avisar. A única medida que dispensa unidade é o 0. Outro clássico é digitar o nome da propriedade errado, como pading ou widht.',
    3),
(24, 5, 'Flexbox',
    'Alinhar e distribuir elementos em linha ou em coluna.',
    'Flexbox é o jeito moderno de organizar elementos lado a lado ou um embaixo do outro. Ao colocar display: flex num elemento, ele vira um contêiner flex e os filhos DIRETOS dele passam a ser organizados em linha. flex-direction: column troca a linha por coluna, e gap define o espaço entre os filhos.\n\nDuas propriedades fazem o alinhamento: justify-content distribui os filhos no eixo principal (na horizontal, quando a direção é linha) com valores como center e space-between; align-items alinha no eixo cruzado (na vertical, quando a direção é linha), com valores como center.\n\nO erro mais comum é colocar display: flex nos próprios itens, e não no elemento que os envolve. As propriedades de flex vão no PAI: é ele quem organiza os filhos. Colocadas nos filhos, elas só organizariam o que estiver dentro de cada um.',
    4);

-- Módulo 21: Seletores e Cores
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(85, 21, 'Selecionando por classe',
    'Qual seletor pega o elemento <p class="destaque">?',
    'quiz', 'facil', 'Classe usa um símbolo, id usa outro. Qual é o da classe?', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(85, '#destaque', 0, 1),
(85, '.destaque', 1, 2),
(85, 'destaque', 0, 3),
(85, 'p#destaque', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(86, 21, 'Pintando o título',
    'Complete o seletor para que APENAS o elemento com a classe titulo fique com a cor laranja #ff6b00. O parágrafo deve continuar com a cor padrão (preto).',
    'completar', 'facil',
    '<style>\n___ {\n  color: #ff6b00;\n}\n</style>\n\n<h1 class="titulo">DevFox</h1>\n<p>Aprenda programando.</p>',
    'Para selecionar por classe, escreva o nome da classe com um ponto na frente.',
    '<style>\n.titulo {\n  color: #ff6b00;\n}\n</style>\n\n<h1 class="titulo">DevFox</h1>\n<p>Aprenda programando.</p>',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(86, 'O título fica laranja', 'estilo(".titulo", "color")', 'rgb(255, 107, 0)', 1, 1),
(86, 'O parágrafo continua preto', 'estilo("p", "color")', 'rgb(0, 0, 0)', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(87, 21, 'Caixa de aviso',
    'Escreva o CSS para: o elemento com id aviso ter fundo (background-color) #222222 e texto (color) branco (white); e os elementos com a classe nota terem o texto azul #0066cc.',
    'codigo_livre', 'facil',
    '<style>\n/* escreva seu CSS aqui */\n</style>\n\n<div id="aviso">Sua sequência termina hoje!</div>\n<p class="nota">Faça uma atividade para não perder.</p>\n<p class="nota">Cada dia conta.</p>',
    'Use #aviso para o id e .nota para a classe. Dentro de cada bloco, uma declaração por linha, sempre terminando com ponto e vírgula.',
    '<style>\n#aviso {\n  background-color: #222222;\n  color: white;\n}\n\n.nota {\n  color: #0066cc;\n}\n</style>\n\n<div id="aviso">Sua sequência termina hoje!</div>\n<p class="nota">Faça uma atividade para não perder.</p>\n<p class="nota">Cada dia conta.</p>',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(87, 'Fundo escuro no aviso', 'estilo("#aviso", "background-color")', 'rgb(34, 34, 34)', 1, 1),
(87, 'Texto branco no aviso', 'estilo("#aviso", "color")', 'rgb(255, 255, 255)', 1, 2),
(87, 'Nota em azul', 'estilo(".nota", "color")', 'rgb(0, 102, 204)', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(88, 21, 'O seletor errado',
    'O título deveria ficar laranja (#ff6b00) sobre fundo quase preto (#1a1a1a), mas continua sem estilo nenhum. Olhe o HTML e corrija o seletor.',
    'corrigir_erro', 'medio',
    '<style>\n.titulo {\n  color: #ff6b00;\n  background-color: #1a1a1a;\n}\n</style>\n\n<h1 id="titulo">Bem-vindo ao DevFox</h1>',
    'No HTML, o título tem id, e não class. Qual símbolo seleciona um id?',
    '<style>\n#titulo {\n  color: #ff6b00;\n  background-color: #1a1a1a;\n}\n</style>\n\n<h1 id="titulo">Bem-vindo ao DevFox</h1>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(88, 'O título fica laranja', 'estilo("#titulo", "color")', 'rgb(255, 107, 0)', 1, 1),
(88, 'O fundo fica escuro', 'estilo("h1", "background-color")', 'rgb(26, 26, 26)', 0, 2);


-- Módulo 22: Texto e Fontes
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(89, 22, 'Centralizando o texto',
    'Qual declaração centraliza o texto de um parágrafo?',
    'quiz', 'facil', 'A propriedade começa com "text" e trata do alinhamento.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(89, 'align: center;', 0, 1),
(89, 'text-align: center;', 1, 2),
(89, 'font-align: center;', 0, 3),
(89, 'text-position: middle;', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(90, 22, 'Título grande e centralizado',
    'Complete o CSS para que o h1 tenha tamanho de fonte 32px e o parágrafo fique centralizado.',
    'completar', 'facil',
    '<style>\nh1 {\n  font-size: ___;\n}\n\np {\n  text-align: ___;\n}\n</style>\n\n<h1>Trilha CSS</h1>\n<p>Deixe suas páginas com a sua cara.</p>',
    'O tamanho leva o número junto da unidade, sem espaço: 32px. O alinhamento centralizado é center.',
    '<style>\nh1 {\n  font-size: 32px;\n}\n\np {\n  text-align: center;\n}\n</style>\n\n<h1>Trilha CSS</h1>\n<p>Deixe suas páginas com a sua cara.</p>',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(90, 'Título com 32px', 'estilo("h1", "font-size")', '32px', 1, 1),
(90, 'Parágrafo centralizado', 'estilo("p", "text-align")', 'center', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(91, 22, 'Frase de destaque',
    'Estilize os elementos com a classe destaque: texto em negrito (font-weight: bold), tamanho 24px (pode escrever 24px ou 1.5rem) e altura de linha (line-height) de 30px.',
    'codigo_livre', 'facil',
    '<style>\n/* escreva seu CSS aqui */\n</style>\n\n<p class="destaque">Programar é resolver problemas.</p>\n<p>Um passo de cada vez.</p>',
    'Um único bloco .destaque { } com três declarações resolve. Lembre das unidades.',
    '<style>\n.destaque {\n  font-weight: bold;\n  font-size: 24px;\n  line-height: 30px;\n}\n</style>\n\n<p class="destaque">Programar é resolver problemas.</p>\n<p>Um passo de cada vez.</p>',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(91, 'Texto em negrito', 'estilo(".destaque", "font-weight")', '700', 1, 1),
(91, 'Tamanho de 24px', 'estilo(".destaque", "font-size")', '24px', 1, 2),
(91, 'Altura de linha', 'estilo(".destaque", "line-height")', '30px', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(92, 22, 'O ponto e vírgula sumido',
    'O parágrafo deveria ter fonte de 18px, ficar centralizado e com cor #333333, mas o tamanho e o alinhamento não funcionam. Encontre e corrija o erro.',
    'corrigir_erro', 'medio',
    '<style>\np {\n  font-size: 18px\n  text-align: center;\n  color: #333333;\n}\n</style>\n\n<p>Estudar um pouco todo dia vale mais que muito de uma vez.</p>',
    'Olhe o fim de cada linha. Uma delas não termina como as outras.',
    '<style>\np {\n  font-size: 18px;\n  text-align: center;\n  color: #333333;\n}\n</style>\n\n<p>Estudar um pouco todo dia vale mais que muito de uma vez.</p>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(92, 'Fonte de 18px', 'estilo("p", "font-size")', '18px', 1, 1),
(92, 'Cor cinza escuro', 'estilo("p", "color")', 'rgb(51, 51, 51)', 1, 2),
(92, 'Texto centralizado', 'estilo("p", "text-align")', 'center', 0, 3);


-- Módulo 23: Box Model
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(93, 23, 'Espaço de dentro',
    'Qual propriedade cria espaço ENTRE o conteúdo e a borda de uma caixa?',
    'quiz', 'facil', 'Uma delas é o espaço de fora, que afasta as caixas vizinhas. Você quer a outra.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(93, 'margin', 0, 1),
(93, 'padding', 1, 2),
(93, 'border', 0, 3),
(93, 'width', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(94, 23, 'Respiro na caixa',
    'Complete o CSS para que a caixa tenha padding de 20px em todos os lados. A borda de 2px já está pronta.',
    'completar', 'facil',
    '<style>\n.caixa {\n  padding: ___;\n  border: 2px solid #ff6b00;\n}\n</style>\n\n<div class="caixa">Conteúdo com espaço para respirar.</div>',
    'Um único valor no padding vale para os quatro lados. Não esqueça o px.',
    '<style>\n.caixa {\n  padding: 20px;\n  border: 2px solid #ff6b00;\n}\n</style>\n\n<div class="caixa">Conteúdo com espaço para respirar.</div>',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(94, 'Padding em cima', 'estilo(".caixa", "padding-top")', '20px', 1, 1),
(94, 'Borda de 2px', 'estilo(".caixa", "border-top-width")', '2px', 1, 2),
(94, 'Padding na esquerda', 'estilo(".caixa", "padding-left")', '20px', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(95, 23, 'Montando um cartão',
    'Estilize a classe cartao com: largura (width) de 300px, padding de 16px, borda de 1px sólida (solid) na cor #cccccc e margem de baixo (margin-bottom) de 24px.',
    'codigo_livre', 'facil',
    '<style>\n/* escreva seu CSS aqui */\n</style>\n\n<div class="cartao">Módulo 3: Box Model</div>\n<div class="cartao">Módulo 4: Flexbox</div>',
    'Tudo cabe num bloco .cartao { }. A borda pode ser escrita numa linha só: border: 1px solid #cccccc;',
    '<style>\n.cartao {\n  width: 300px;\n  padding: 16px;\n  border: 1px solid #cccccc;\n  margin-bottom: 24px;\n}\n</style>\n\n<div class="cartao">Módulo 3: Box Model</div>\n<div class="cartao">Módulo 4: Flexbox</div>',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(95, 'Largura de 300px', 'estilo(".cartao", "width")', '300px', 1, 1),
(95, 'Padding de 16px', 'estilo(".cartao", "padding-left")', '16px', 1, 2),
(95, 'Borda sólida', 'estilo(".cartao", "border-top-style")', 'solid', 1, 3),
(95, 'Cor da borda', 'estilo(".cartao", "border-top-color")', 'rgb(204, 204, 204)', 0, 4),
(95, 'Margem de baixo', 'estilo(".cartao", "margin-bottom")', '24px', 0, 5);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(96, 23, 'O botão desregulado',
    'O botão deveria ter padding de 12px em todos os lados e largura de 200px, mas nenhuma das duas coisas funciona. Há dois erros: um no nome de uma propriedade e outro numa medida.',
    'corrigir_erro', 'medio',
    '<style>\n.botao {\n  pading: 12px;\n  width: 200;\n  border: 2px solid #ff6b00;\n}\n</style>\n\n<div class="botao">Continuar</div>',
    'Confira a grafia de padding letra por letra. E lembre: toda medida diferente de zero precisa de unidade.',
    '<style>\n.botao {\n  padding: 12px;\n  width: 200px;\n  border: 2px solid #ff6b00;\n}\n</style>\n\n<div class="botao">Continuar</div>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(96, 'Padding em cima', 'estilo(".botao", "padding-top")', '12px', 1, 1),
(96, 'Largura de 200px', 'estilo(".botao", "width")', '200px', 1, 2),
(96, 'Padding na direita', 'estilo(".botao", "padding-right")', '12px', 0, 3);


-- Módulo 24: Flexbox
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(97, 24, 'Onde vai o flex',
    'Você quer colocar três <div class="item"> lado a lado. Eles estão dentro de uma <div class="linha">. Onde deve ir o display: flex?',
    'quiz', 'facil', 'O flex vai no elemento que organiza os filhos.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(97, 'Em .item', 0, 1),
(97, 'Em .linha', 1, 2),
(97, 'No body', 0, 3),
(97, 'Em cada item, com um id', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(98, 24, 'Menu espalhado',
    'Complete o CSS para que o menu seja um contêiner flex e distribua os links com o espaço sobrando ENTRE eles (space-between).',
    'completar', 'facil',
    '<style>\n.menu {\n  display: ___;\n  justify-content: ___;\n}\n</style>\n\n<nav class="menu">\n  <a href="#">Início</a>\n  <a href="#">Trilhas</a>\n  <a href="#">Perfil</a>\n</nav>',
    'O primeiro espaço liga o flexbox. O segundo é o valor que joga o espaço para o meio dos itens.',
    '<style>\n.menu {\n  display: flex;\n  justify-content: space-between;\n}\n</style>\n\n<nav class="menu">\n  <a href="#">Início</a>\n  <a href="#">Trilhas</a>\n  <a href="#">Perfil</a>\n</nav>',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(98, 'O menu é flex', 'estilo(".menu", "display")', 'flex', 1, 1),
(98, 'Espaço entre os links', 'estilo(".menu", "justify-content")', 'space-between', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(99, 24, 'Painel em coluna',
    'Faça a classe painel virar um contêiner flex em COLUNA (flex-direction: column), com os itens centralizados no eixo cruzado (align-items: center) e 12px de espaço entre eles (gap).',
    'codigo_livre', 'facil',
    '<style>\n/* escreva seu CSS aqui */\n</style>\n\n<div class="painel">\n  <div>Nível 3</div>\n  <div>450 XP</div>\n  <div>7 dias seguidos</div>\n</div>',
    'Quatro declarações no bloco .painel { }: display, flex-direction, align-items e gap.',
    '<style>\n.painel {\n  display: flex;\n  flex-direction: column;\n  align-items: center;\n  gap: 12px;\n}\n</style>\n\n<div class="painel">\n  <div>Nível 3</div>\n  <div>450 XP</div>\n  <div>7 dias seguidos</div>\n</div>',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(99, 'O painel é flex', 'estilo(".painel", "display")', 'flex', 1, 1),
(99, 'Direção em coluna', 'estilo(".painel", "flex-direction")', 'column', 1, 2),
(99, 'Itens centralizados', 'estilo(".painel", "align-items")', 'center', 0, 3),
(99, 'Espaço entre os itens', 'estilo(".painel", "row-gap")', '12px', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(100, 24, 'Flex no lugar errado',
    'Os três itens deveriam ficar lado a lado, centralizados na horizontal (justify-content: center) e na vertical (align-items: center) dentro da linha. O CSS foi colocado no elemento errado. Corrija, deixando essas três declarações na classe linha.',
    'corrigir_erro', 'medio',
    '<style>\n.linha {\n  height: 120px;\n}\n\n.item {\n  display: flex;\n  justify-content: center;\n  align-items: center;\n}\n</style>\n\n<div class="linha">\n  <div class="item">HTML</div>\n  <div class="item">CSS</div>\n  <div class="item">JS</div>\n</div>',
    'Quem organiza os filhos é o pai. As propriedades de flex precisam estar no elemento que envolve os itens.',
    '<style>\n.linha {\n  height: 120px;\n  display: flex;\n  justify-content: center;\n  align-items: center;\n}\n</style>\n\n<div class="linha">\n  <div class="item">HTML</div>\n  <div class="item">CSS</div>\n  <div class="item">JS</div>\n</div>',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(100, 'A linha é flex', 'estilo(".linha", "display")', 'flex', 1, 1),
(100, 'Centralizado na horizontal', 'estilo(".linha", "justify-content")', 'center', 1, 2),
(100, 'Centralizado na vertical', 'estilo(".linha", "align-items")', 'center', 0, 3);


-- ---------------------------------------------------------------------
-- Prova de fim de nível da trilha 5
-- ---------------------------------------------------------------------
-- Cada questão aponta, por `modulo_referencia_id`, o módulo que ela avalia —
-- é assim que o sistema diz ao aluno reprovado O QUE revisar, em vez de
-- mandá-lo refazer tudo.
-- ---------------------------------------------------------------------
INSERT INTO modulos (id, trilha_id, titulo, tipo, nota_minima, descricao, ordem) VALUES
(25, 5, 'Prova: CSS', 'prova', 70,
    'Avaliação dos módulos de seletores, texto, box model e flexbox. Acerte 70% para avançar.',
    5);

INSERT INTO atividades (id, modulo_id, modulo_referencia_id, titulo, enunciado, tipo, dificuldade, xp_recompensa, ordem) VALUES
(101, 25, 21, 'Questão 1', 'O HTML tem <div id="topo">. Qual seletor pega esse elemento?', 'quiz', 'facil', 50, 1),
(102, 25, 22, 'Questão 2', 'Por que esquecer o ponto e vírgula em "font-size: 18px" pode quebrar a linha seguinte?', 'quiz', 'facil', 50, 2),
(103, 25, 23, 'Questão 3', 'Qual propriedade afasta uma caixa das caixas vizinhas, criando espaço do lado de FORA da borda?', 'quiz', 'facil', 50, 3),
(104, 25, 23, 'Questão 4', 'O que acontece com a declaração "width: 300;"?', 'quiz', 'medio', 50, 4),
(105, 25, 24, 'Questão 5', 'Num contêiner com display: flex em linha, qual propriedade distribui os itens na horizontal?', 'quiz', 'medio', 50, 5);

INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(101, '.topo', 0, 1),
(101, '#topo', 1, 2),
(101, 'topo', 0, 3),
(101, '*topo', 0, 4),
(102, 'Porque o navegador junta as duas linhas numa declaração inválida e descarta ambas', 1, 1),
(102, 'Porque o navegador apaga a regra inteira e todas as outras da página', 0, 2),
(102, 'Porque o ponto e vírgula é o que define o tamanho da fonte', 0, 3),
(102, 'Não quebra: o ponto e vírgula é opcional em qualquer linha', 0, 4),
(103, 'padding', 0, 1),
(103, 'margin', 1, 2),
(103, 'width', 0, 3),
(103, 'gap-border', 0, 4),
(104, 'A largura vira 300px automaticamente', 0, 1),
(104, 'A largura vira 300% da página', 0, 2),
(104, 'Ela é inválida e o navegador a ignora', 1, 3),
(104, 'O navegador mostra uma mensagem de erro na página', 0, 4),
(105, 'align-items', 0, 1),
(105, 'justify-content', 1, 2),
(105, 'text-align', 0, 3),
(105, 'flex-direction', 0, 4);


-- =====================================================================
-- TRILHA 6: Funções e Métodos
-- =====================================================================

INSERT INTO modulos (id, trilha_id, titulo, descricao, teoria_texto, ordem) VALUES
(26, 6, 'Parâmetros e Escopo',
    'Valores padrão, onde cada variável existe e funções que usam outras funções.',
    'Um parâmetro pode ter um valor padrão, escrito com = na própria declaração: function saudar(nome = "visitante"). Se quem chama a função não mandar esse argumento, o parâmetro assume o valor padrão em vez de ficar undefined.\n\nEscopo é a região do código onde uma variável existe. Uma variável criada com let ou const dentro de chaves { } só vive ali dentro: fora do bloco ela simplesmente não existe. Por isso funções diferentes podem ter variáveis com o mesmo nome sem uma atrapalhar a outra.\n\nO erro mais comum é declarar a variável dentro de um if e tentar usá-la depois dele, o que gera "ReferenceError: ... is not defined". A solução é declarar a variável antes do bloco e só atribuir o valor lá dentro. Outra boa prática é dividir o trabalho: uma função pequena pode chamar outra, e cada uma faz uma coisa só.',
    1),
(27, 6, 'Arrow Functions e Callbacks',
    'Funções como valores: guardar em variáveis e passar como argumento.',
    'Arrow function é um jeito mais curto de escrever uma função: const dobro = (n) => n * 2; faz o mesmo que uma function que devolve n * 2. Quando o corpo é uma única expressão, sem chaves, o resultado é devolvido automaticamente, sem precisar escrever return.\n\nEm JavaScript, funções são valores como números e textos: podem ser guardadas em variáveis e enviadas para outras funções. Uma função recebida como argumento se chama callback. Quem recebe decide quando chamá-la, escrevendo fn(valor).\n\nO erro mais comum é colocar chaves na arrow function e esquecer o return: n => { n * 2 } devolve undefined, porque com chaves o retorno deixa de ser automático. Outro erro frequente é passar o resultado em vez da função: executar(dobro(3)) envia o número 6, e não a função dobro.',
    2),
(28, 6, 'Métodos de Array',
    'map, filter, reduce, find, some, every e includes na prática.',
    'Arrays têm métodos prontos que recebem um callback. map cria um array novo transformando cada item; filter cria um array novo só com os itens que passam num teste; find devolve o primeiro item que passa no teste (ou undefined); some responde se ALGUM item passa e every se TODOS passam; includes diz se um valor está no array.\n\nreduce junta todos os itens em um único valor. Ele recebe um callback com o acumulado e o item atual, e um valor inicial: numeros.reduce((soma, n) => soma + n, 0) soma tudo começando do zero. Esses métodos podem ser encadeados: numeros.filter(n => n > 0).map(n => n * 2).\n\nO erro mais comum é esquecer o valor inicial do reduce. Com um array vazio, reduce sem valor inicial quebra com "Reduce of empty array with no initial value". Outro engano frequente é usar filter quando se quer um único item: filter sempre devolve um array, mesmo que tenha só um elemento; para um item só, use find.',
    3),
(29, 6, 'Objetos e Métodos',
    'Propriedades, métodos, a palavra this e Object.keys.',
    'Um objeto agrupa informações relacionadas em pares chave: valor, como { nome: "Ana", idade: 20 }. Cada valor é acessado com ponto (pessoa.nome) ou com colchetes (pessoa["nome"]). Quando o valor de uma propriedade é uma função, ela se chama método: jogador.ganhar(10).\n\nDentro de um método, a palavra this representa o próprio objeto que chamou o método. Assim, this.pontos lê ou altera a propriedade pontos desse objeto. Para percorrer um objeto, Object.keys(obj) devolve um array com os nomes das chaves e Object.values(obj) um array com os valores.\n\nO erro mais comum é escrever um método como arrow function: ganhar: (qtd) => { ... }. Arrow functions não têm this próprio, então this.pontos não aponta para o objeto e o resultado vira NaN ou um erro. Para métodos que usam this, use a forma ganhar(qtd) { ... } ou ganhar: function (qtd) { ... }.',
    4);

-- Módulo 26: Parâmetros e Escopo
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(106, 26, 'Valor padrão',
    'Dado function saudar(nome = "visitante") { return "Oi, " + nome; }, o que saudar() devolve?',
    'quiz', 'facil', 'O valor depois do = só é usado quando o argumento não é enviado.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(106, '"Oi, undefined"', 0, 1),
(106, '"Oi, visitante"', 1, 2),
(106, '"Oi, "', 0, 3),
(106, 'Um erro, porque faltou o argumento', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(107, 26, 'Desconto padrão',
    'A função calcularPreco(valor, desconto) devolve o valor com o desconto (em %) aplicado. Complete a declaração para que o desconto seja 10 quando ele não for informado. Exemplo: calcularPreco(100) devolve 90 e calcularPreco(200, 50) devolve 100.',
    'completar', 'facil',
    'function calcularPreco(valor, desconto = ___) {\n  return valor - valor * desconto / 100;\n}',
    'O valor padrão é escrito logo depois do = na lista de parâmetros.',
    'function calcularPreco(valor, desconto = 10) {\n  return valor - valor * desconto / 100;\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(107, 'Sem informar o desconto', 'calcularPreco(100)', '90', 1, 1),
(107, 'Com desconto informado', 'calcularPreco(200, 50)', '100', 1, 2),
(107, 'Outro valor sem desconto', 'calcularPreco(80)', '72', 0, 3),
(107, 'Desconto zero informado', 'calcularPreco(80, 0)', '80', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(108, 26, 'Uma função chamando outra',
    'Escreva duas funções. quadrado(n) devolve n multiplicado por ele mesmo. somaDosQuadrados(a, b) devolve quadrado(a) + quadrado(b), usando a primeira função. Exemplo: somaDosQuadrados(3, 4) devolve 25.',
    'codigo_livre', 'facil',
    'function quadrado(n) {\n  // escreva seu código aqui\n}\n\nfunction somaDosQuadrados(a, b) {\n  // use a função quadrado aqui\n}',
    'Dentro de somaDosQuadrados, chame quadrado(a) e quadrado(b) e some os resultados.',
    'function quadrado(n) {\n  return n * n;\n}\n\nfunction somaDosQuadrados(a, b) {\n  return quadrado(a) + quadrado(b);\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(108, 'Quadrado de 5', 'quadrado(5)', '25', 1, 1),
(108, 'Soma dos quadrados de 3 e 4', 'somaDosQuadrados(3, 4)', '25', 1, 2),
(108, 'Soma com número negativo', 'somaDosQuadrados(-1, 2)', '5', 0, 3),
(108, 'Soma com zero', 'somaDosQuadrados(0, 6)', '36', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(109, 26, 'A variável que sumiu',
    'A função classificarNota(nota) deveria devolver "aprovado" para nota 7 ou mais e "reprovado" para as demais, mas dá erro ao rodar. Corrija o escopo da variável situacao.',
    'corrigir_erro', 'medio',
    'function classificarNota(nota) {\n  if (nota >= 7) {\n    let situacao = "aprovado";\n  } else {\n    let situacao = "reprovado";\n  }\n  return situacao;\n}',
    'O erro é "situacao is not defined". Declare a variável ANTES do if e, dentro dos blocos, apenas atribua o valor.',
    'function classificarNota(nota) {\n  let situacao;\n  if (nota >= 7) {\n    situacao = "aprovado";\n  } else {\n    situacao = "reprovado";\n  }\n  return situacao;\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(109, 'Nota alta', 'classificarNota(9)', 'aprovado', 1, 1),
(109, 'Nota baixa', 'classificarNota(4)', 'reprovado', 1, 2),
(109, 'Exatamente 7', 'classificarNota(7)', 'aprovado', 0, 3),
(109, 'Quase lá', 'classificarNota(6.9)', 'reprovado', 0, 4);


-- Módulo 27: Arrow Functions e Callbacks
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(110, 27, 'Lendo uma arrow function',
    'Dado const dobro = n => n * 2;, qual é o valor de dobro(4)?',
    'quiz', 'facil', 'Sem chaves, a expressão depois da seta é devolvida automaticamente.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(110, '4', 0, 1),
(110, '8', 1, 2),
(110, 'undefined', 0, 3),
(110, '"n * 2"', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(111, 27, 'Total da compra',
    'Complete a arrow function calcularTotal(preco, quantidade) para que ela devolva o preço multiplicado pela quantidade. Exemplo: calcularTotal(10, 3) devolve 30.',
    'completar', 'facil',
    'const calcularTotal = (preco, quantidade) => ___;',
    'Depois da seta, escreva só a expressão que deve ser devolvida.',
    'const calcularTotal = (preco, quantidade) => preco * quantidade;',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(111, 'Três unidades', 'calcularTotal(10, 3)', '30', 1, 1),
(111, 'Preço quebrado', 'calcularTotal(2.5, 4)', '10', 1, 2),
(111, 'Quantidade zero', 'calcularTotal(7, 0)', '0', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(112, 27, 'Aplicar duas vezes',
    'Escreva uma função aplicarDuasVezes(fn, valor) que chame a função fn sobre valor e, depois, chame fn de novo sobre o resultado, devolvendo o valor final. Exemplo: aplicarDuasVezes(n => n + 3, 1) devolve 7.',
    'codigo_livre', 'facil',
    'function aplicarDuasVezes(fn, valor) {\n  // escreva seu código aqui\n}',
    'Guarde fn(valor) em uma variável e devolva fn aplicada a essa variável. Ou, direto: fn(fn(valor)).',
    'function aplicarDuasVezes(fn, valor) {\n  return fn(fn(valor));\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(112, 'Somando 3 duas vezes', 'aplicarDuasVezes(n => n + 3, 1)', '7', 1, 1),
(112, 'Dobrando duas vezes', 'aplicarDuasVezes(n => n * 2, 5)', '20', 1, 2),
(112, 'Funciona com textos', 'aplicarDuasVezes(t => t + "!", "oi")', 'oi!!', 0, 3),
(112, 'Subtraindo duas vezes', 'aplicarDuasVezes(n => n - 10, 0)', '-20', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(113, 27, 'A metade que sumiu',
    'A arrow function metade(n) deveria devolver a metade do número, e a função executar(fn, x) apenas chama o callback. Mas metade está devolvendo undefined. Corrija a arrow function.',
    'corrigir_erro', 'medio',
    'const metade = n => { n / 2 };\n\nfunction executar(fn, x) {\n  return fn(x);\n}',
    'Com chaves, a arrow function precisa de return. Remova as chaves ou escreva return.',
    'const metade = n => n / 2;\n\nfunction executar(fn, x) {\n  return fn(x);\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(113, 'Metade de 8', 'metade(8)', '4', 1, 1),
(113, 'Usada como callback', 'executar(metade, 10)', '5', 1, 2),
(113, 'Metade de número ímpar', 'metade(3)', '1.5', 0, 3),
(113, 'Callback com negativo', 'executar(metade, -6)', '-3', 0, 4);


-- Módulo 28: Métodos de Array
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(114, 28, 'Escolhendo o método',
    'Qual método devolve um NOVO array contendo apenas os itens que passam em um teste?',
    'quiz', 'facil', 'O nome do método em inglês quer dizer "filtrar".', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(114, 'map', 0, 1),
(114, 'filter', 1, 2),
(114, 'find', 0, 3),
(114, 'includes', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(115, 28, 'Dobrando com map',
    'Complete a função dobrarTodos(numeros) para que ela devolva um novo array com cada número multiplicado por 2. Exemplo: dobrarTodos([1, 2, 3]) devolve [2,4,6].',
    'completar', 'facil',
    'function dobrarTodos(numeros) {\n  return numeros.___(n => n * 2);\n}',
    'Você quer transformar cada item, mantendo a mesma quantidade de itens.',
    'function dobrarTodos(numeros) {\n  return numeros.map(n => n * 2);\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(115, 'Três números', 'dobrarTodos([1, 2, 3])', '[2,4,6]', 1, 1),
(115, 'Com negativo', 'dobrarTodos([-5, 10])', '[-10,20]', 1, 2),
(115, 'Array vazio', 'dobrarTodos([])', '[]', 0, 3),
(115, 'Um número só', 'dobrarTodos([0.5])', '[1]', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(116, 28, 'Soma dos pares',
    'Escreva uma função somarPares(numeros) que devolva a soma apenas dos números pares do array. Se não houver pares, devolva 0. Exemplo: somarPares([1, 2, 3, 4]) devolve 6. Tente usar filter e reduce.',
    'codigo_livre', 'facil',
    'function somarPares(numeros) {\n  // escreva seu código aqui\n}',
    'Primeiro filtre com n % 2 === 0; depois some com reduce((soma, n) => soma + n, 0).',
    'function somarPares(numeros) {\n  return numeros.filter(n => n % 2 === 0).reduce((soma, n) => soma + n, 0);\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(116, 'Mistura de pares e ímpares', 'somarPares([1, 2, 3, 4])', '6', 1, 1),
(116, 'Sem nenhum par', 'somarPares([7, 9])', '0', 1, 2),
(116, 'Com negativo', 'somarPares([10, -2, 5])', '8', 0, 3),
(116, 'Array vazio', 'somarPares([])', '0', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(117, 28, 'Carrinho vazio',
    'A função totalCarrinho(precos) deveria devolver a soma dos preços, e 0 quando o carrinho estiver vazio. Com o carrinho vazio, porém, ela quebra. Corrija o reduce.',
    'corrigir_erro', 'medio',
    'function totalCarrinho(precos) {\n  return precos.reduce((soma, preco) => soma + preco);\n}',
    'O erro é "Reduce of empty array with no initial value". Passe o valor inicial 0 como segundo argumento do reduce.',
    'function totalCarrinho(precos) {\n  return precos.reduce((soma, preco) => soma + preco, 0);\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(117, 'Três produtos', 'totalCarrinho([10, 20, 5])', '35', 1, 1),
(117, 'Carrinho vazio', 'totalCarrinho([])', '0', 1, 2),
(117, 'Um produto só', 'totalCarrinho([7.5])', '7.5', 0, 3);


-- Módulo 29: Objetos e Métodos
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(118, 29, 'Quem é o this?',
    'Dentro de um método escrito como depositar(valor) { this.saldo += valor; }, a quem this se refere?',
    'quiz', 'facil', 'Em conta.depositar(50), quem está antes do ponto?', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(118, 'Ao próprio objeto que chamou o método', 1, 1),
(118, 'Ao parâmetro valor', 0, 2),
(118, 'À última variável criada no programa', 0, 3),
(118, 'A nada: this sempre vale undefined', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(119, 29, 'Conta bancária',
    'A função criarConta(saldoInicial) devolve um objeto com a propriedade saldo e o método depositar(valor), que soma o valor ao saldo e devolve o novo saldo. Complete a linha que altera o saldo do próprio objeto. Exemplo: criarConta(100).depositar(50) devolve 150.',
    'completar', 'facil',
    'function criarConta(saldoInicial) {\n  return {\n    saldo: saldoInicial,\n    depositar(valor) {\n      ___ += valor;\n      return this.saldo;\n    }\n  };\n}',
    'Para falar da propriedade saldo do próprio objeto, use this seguido de ponto.',
    'function criarConta(saldoInicial) {\n  return {\n    saldo: saldoInicial,\n    depositar(valor) {\n      this.saldo += valor;\n      return this.saldo;\n    }\n  };\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(119, 'Depósito simples', 'criarConta(100).depositar(50)', '150', 1, 1),
(119, 'Conta começando do zero', 'criarConta(0).depositar(20)', '20', 0, 2),
(119, 'Dois depósitos seguidos', '(() => { const c = criarConta(10); c.depositar(5); return c.depositar(5); })()', '20', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(120, 29, 'Listando as chaves',
    'Escreva uma função listarChaves(obj) que devolva um texto com os nomes das chaves do objeto separados por vírgula e espaço. Exemplo: listarChaves({ nome: "Ana", idade: 20 }) devolve "nome, idade".',
    'codigo_livre', 'facil',
    'function listarChaves(obj) {\n  // escreva seu código aqui\n}',
    'Object.keys(obj) devolve um array com as chaves; o método join(", ") junta os itens de um array em um texto.',
    'function listarChaves(obj) {\n  return Object.keys(obj).join(", ");\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(120, 'Duas chaves', 'listarChaves({ nome: "Ana", idade: 20 })', 'nome, idade', 1, 1),
(120, 'Uma chave só', 'listarChaves({ cor: "laranja" })', 'cor', 1, 2),
(120, 'Três chaves', 'listarChaves({ a: 1, b: 2, c: 3 })', 'a, b, c', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(121, 29, 'Pontos que não somam',
    'A função criarJogador(nome) devolve um objeto com nome, pontos (começando em 0) e o método ganhar(qtd), que soma qtd aos pontos e devolve o total. Mas ganhar não altera os pontos do jogador. Corrija o método.',
    'corrigir_erro', 'medio',
    'function criarJogador(nome) {\n  return {\n    nome: nome,\n    pontos: 0,\n    ganhar: (qtd) => {\n      this.pontos += qtd;\n      return this.pontos;\n    }\n  };\n}',
    'Arrow functions não têm this próprio. Troque por ganhar(qtd) { ... } para que this seja o objeto do jogador.',
    'function criarJogador(nome) {\n  return {\n    nome: nome,\n    pontos: 0,\n    ganhar(qtd) {\n      this.pontos += qtd;\n      return this.pontos;\n    }\n  };\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(121, 'Primeira pontuação', 'criarJogador("Fox").ganhar(10)', '10', 1, 1),
(121, 'Pontos acumulando', '(() => { const j = criarJogador("Ana"); j.ganhar(5); return j.ganhar(3); })()', '8', 0, 2);


-- ---------------------------------------------------------------------
-- Prova de fim de nível da trilha 6
-- ---------------------------------------------------------------------
-- Cada questão aponta, por `modulo_referencia_id`, o módulo que ela avalia —
-- é assim que o sistema diz ao aluno reprovado O QUE revisar, em vez de
-- mandá-lo refazer tudo.
-- ---------------------------------------------------------------------
INSERT INTO modulos (id, trilha_id, titulo, tipo, nota_minima, descricao, ordem) VALUES
(30, 6, 'Prova: Funções e Métodos', 'prova', 70,
    'Avaliação de parâmetros e escopo, arrow functions, métodos de array e objetos. Acerte 70% para avançar.',
    5);

INSERT INTO atividades (id, modulo_id, modulo_referencia_id, titulo, enunciado, tipo, dificuldade, xp_recompensa, ordem) VALUES
(122, 30, 26, 'Questão 1', 'Uma variável foi declarada com let dentro do bloco de um if. O que acontece ao usá-la depois que o if termina?', 'quiz', 'facil', 50, 1),
(123, 30, 27, 'Questão 2', 'O que a arrow function const f = n => { n + 1 }; devolve ao chamar f(5)?', 'quiz', 'facil', 50, 2),
(124, 30, 28, 'Questão 3', 'Qual é o resultado de [3, 8, 12].find(n => n > 5)?', 'quiz', 'facil', 50, 3),
(125, 30, 28, 'Questão 4', 'Qual método responde true se PELO MENOS UM item do array passa no teste?', 'quiz', 'medio', 50, 4),
(126, 30, 29, 'Questão 5', 'O que Object.keys({ nome: "Fox", nivel: 3 }) devolve?', 'quiz', 'medio', 50, 5);

INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(122, 'Ela continua valendo normalmente', 0, 1),
(122, 'Ocorre um ReferenceError, porque ela só existe dentro do bloco', 1, 2),
(122, 'Ela passa a valer 0', 0, 3),
(122, 'Ela vira uma variável global', 0, 4),
(123, '6', 0, 1),
(123, '5', 0, 2),
(123, 'undefined', 1, 3),
(123, 'Um erro de sintaxe', 0, 4),
(124, '[8, 12]', 0, 1),
(124, '8', 1, 2),
(124, 'true', 0, 3),
(124, '12', 0, 4),
(125, 'every', 0, 1),
(125, 'map', 0, 2),
(125, 'some', 1, 3),
(125, 'reduce', 0, 4),
(126, '["Fox", 3]', 0, 1),
(126, '["nome", "nivel"]', 1, 2),
(126, '2', 0, 3),
(126, '{ nome: "Fox" }', 0, 4);


-- =====================================================================
-- TRILHA 7: Desafios Práticos
-- =====================================================================

INSERT INTO modulos (id, trilha_id, titulo, descricao, teoria_texto, ordem) VALUES
(31, 7, 'Desafios com Texto',
    'Estratégias para analisar e transformar strings.',
    'Antes de escrever qualquer linha, resolva o problema com um exemplo na mão. Pegue uma frase curta, faça o processo no papel e anote cada passo. Esses passos viram o algoritmo: "percorrer cada letra", "guardar numa lista", "juntar no final".\n\nMuitos desafios com texto ficam fáceis depois de uma NORMALIZAÇÃO: deixar tudo em minúsculas com toLowerCase() e remover o que não interessa, como espaços. Assim "Ana" e "ana" passam a ser a mesma coisa, e a comparação deixa de depender de detalhes.\n\nOutra estratégia poderosa é quebrar o texto em partes: split("") separa letras e split(" ") separa palavras. Com um array em mãos, você pode inverter, filtrar ou contar, e depois usar join para montar o texto de volta. Sempre teste o caso da string vazia.',
    1),
(32, 7, 'Desafios com Números',
    'Primos, dígitos, sequências e divisores.',
    'Em desafios numéricos, a estratégia é transformar a definição matemática em passos. "Primo é o número maior que 1 que só é divisível por 1 e por ele mesmo" vira: se for menor que 2, não é primo; senão, procure um divisor entre 2 e o próprio número.\n\nCom os operadores % e Math.floor você desmonta um número: n % 10 devolve o último dígito e Math.floor(n / 10) remove esse dígito. Repetindo isso num while até o número chegar a 0, você visita cada dígito. Sequências como Fibonacci seguem a mesma ideia: guarde os dois valores anteriores e calcule o próximo a cada volta.\n\nPense também em eficiência e nos limites. Para testar se n é primo, basta procurar divisores até a raiz quadrada de n, porque todo divisor maior tem um par menor. E sempre teste os casos de borda: 0, 1, 2 e números negativos.',
    2),
(33, 7, 'Desafios com Listas',
    'Duplicados, posições e comparações entre arrays.',
    'Um padrão muito comum com listas é construir um resultado novo: crie um array vazio, percorra a lista original e decida, item a item, o que entra. É assim que se removem duplicados (só entra quem ainda não está no resultado) e que se calcula a intersecção de duas listas (só entra quem também aparece na outra).\n\nQuando o problema pede "o maior", "o segundo maior" ou "o menor", mantenha variáveis que guardam os melhores valores vistos até agora e atualize-as a cada item. Pense no que acontece com valores repetidos, listas com um único item e listas vazias: o enunciado deve dizer o que devolver nesses casos.\n\nPara problemas de posição, como rotacionar, use o operador %: (i + k) % tamanho faz o índice "dar a volta" no fim do array. E k % tamanho resolve rotações maiores que a própria lista, já que rotacionar 5 vezes uma lista de 5 itens é o mesmo que não mexer.',
    3),
(34, 7, 'Desafios com Dados',
    'Contar, somar, agrupar e ordenar listas de objetos.',
    'Programas reais trabalham com listas de objetos: produtos de um carrinho, jogadores de um ranking, pedidos de uma loja. A estratégia é sempre a mesma: descubra qual campo de cada objeto importa e o que deve acontecer com ele (somar, contar, comparar).\n\nUm objeto vazio funciona como um contador de muitas coisas ao mesmo tempo. Para cada item, use-o como chave: contagem[item] = (contagem[item] || 0) + 1. O "|| 0" resolve a primeira aparição, quando a chave ainda não existe. A mesma ideia serve para agrupar: em vez de somar 1, você empurra o item num array daquela chave.\n\nPara ordenar objetos, use sort com uma função de comparação: (a, b) => a.pontos - b.pontos deixa em ordem crescente e b.pontos - a.pontos em ordem decrescente. Lembre que sort altera o array original; se não quiser isso, ordene uma cópia feita com [...lista].',
    4);

-- Módulo 31: Desafios com Texto
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(127, 31, 'Normalizando antes de comparar',
    'Você quer saber se "Arara" é um palíndromo (lê-se igual de trás para frente). Qual é o primeiro passo mais seguro?',
    'quiz', 'facil', '"A" e "a" são caracteres diferentes para o JavaScript.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(127, 'Comparar o texto original com ele mesmo', 0, 1),
(127, 'Converter tudo para minúsculas antes de inverter e comparar', 1, 2),
(127, 'Contar quantas letras a palavra tem', 0, 3),
(127, 'Trocar as vogais por números', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(128, 31, 'Contando vogais',
    'Complete a função contarVogais(texto) para que ela devolva quantas vogais (a, e, i, o, u) o texto tem. Letras maiúsculas também contam. Texto vazio deve devolver 0. Considere apenas vogais sem acento.',
    'completar', 'facil',
    'function contarVogais(texto) {\n  const vogais = "aeiou";\n  let total = 0;\n  for (const letra of texto.toLowerCase()) {\n    if (___) {\n      total++;\n    }\n  }\n  return total;\n}',
    'Verifique se a string vogais inclui a letra atual com o método includes.',
    'function contarVogais(texto) {\n  const vogais = "aeiou";\n  let total = 0;\n  for (const letra of texto.toLowerCase()) {\n    if (vogais.includes(letra)) {\n      total++;\n    }\n  }\n  return total;\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(128, 'Palavra simples', 'contarVogais("programar")', '3', 1, 1),
(128, 'Com maiúsculas', 'contarVogais("DEVFOX")', '2', 1, 2),
(128, 'Texto vazio', 'contarVogais("")', '0', 0, 3),
(128, 'Sem nenhuma vogal', 'contarVogais("xyz")', '0', 0, 4),
(128, 'Frase com espaços', 'contarVogais("Oi Ana")', '4', 0, 5);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(129, 31, 'Palíndromo',
    'Escreva uma função ehPalindromo(texto) que devolva true se o texto for lido igual de frente para trás e de trás para frente, e false caso contrário. Ignore espaços e diferenças entre maiúsculas e minúsculas: "A sacada da casa" é palíndromo. Um texto vazio é considerado palíndromo (devolve true).',
    'codigo_livre', 'medio',
    'function ehPalindromo(texto) {\n  // escreva seu código aqui\n}',
    'Normalize primeiro: toLowerCase() e remova os espaços (split(" ").join("")). Depois inverta com split("").reverse().join("") e compare.',
    'function ehPalindromo(texto) {\n  const limpo = texto.toLowerCase().split(" ").join("");\n  const invertido = limpo.split("").reverse().join("");\n  return limpo === invertido;\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(129, 'Palavra palíndromo', 'ehPalindromo("arara")', 'true', 1, 1),
(129, 'Palavra comum', 'ehPalindromo("devfox")', 'false', 1, 2),
(129, 'Com maiúscula', 'ehPalindromo("Ana")', 'true', 0, 3),
(129, 'Frase com espaços', 'ehPalindromo("A sacada da casa")', 'true', 0, 4),
(129, 'Texto vazio', 'ehPalindromo("")', 'true', 0, 5),
(129, 'Quase palíndromo', 'ehPalindromo("ab")', 'false', 0, 6);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(130, 31, 'Invertendo a ordem das palavras',
    'A função inverterPalavras(frase) deveria devolver as palavras da frase em ordem inversa, separadas por um espaço: inverterPalavras("eu gosto de codar") deve devolver "codar de gosto eu". Com uma só palavra, devolve a própria palavra; com texto vazio, devolve texto vazio. Mas a função está perdendo uma palavra. Encontre e corrija o erro.',
    'corrigir_erro', 'medio',
    'function inverterPalavras(frase) {\n  const palavras = frase.split(" ");\n  const resultado = [];\n  for (let i = palavras.length - 1; i > 0; i--) {\n    resultado.push(palavras[i]);\n  }\n  return resultado.join(" ");\n}',
    'Qual é o índice da primeira palavra? Confira se o laço chega até ele.',
    'function inverterPalavras(frase) {\n  const palavras = frase.split(" ");\n  const resultado = [];\n  for (let i = palavras.length - 1; i >= 0; i--) {\n    resultado.push(palavras[i]);\n  }\n  return resultado.join(" ");\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(130, 'Frase de quatro palavras', 'inverterPalavras("eu gosto de codar")', 'codar de gosto eu', 1, 1),
(130, 'Duas palavras', 'inverterPalavras("olá mundo")', 'mundo olá', 1, 2),
(130, 'Uma palavra só', 'inverterPalavras("DevFox")', 'DevFox', 0, 3),
(130, 'Texto vazio', 'inverterPalavras("")', '', 0, 4);


-- Módulo 32: Desafios com Números
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(131, 32, 'Até onde procurar divisores',
    'Para verificar se um número n é primo, até que valor basta procurar divisores?',
    'quiz', 'facil', 'Se 36 = 4 x 9, o divisor 9 tem um par menor (4). Onde os pares se encontram?', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(131, 'Até n - 1, obrigatoriamente', 0, 1),
(131, 'Até a raiz quadrada de n', 1, 2),
(131, 'Até 10', 0, 3),
(131, 'Até n * 2', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(132, 32, 'Soma dos dígitos',
    'Complete a função somaDigitos(n) para que ela devolva a soma dos dígitos de um número inteiro não negativo. Por exemplo, somaDigitos(123) devolve 6. Para 0, devolve 0.',
    'completar', 'facil',
    'function somaDigitos(n) {\n  let soma = 0;\n  while (n > 0) {\n    soma += n % 10;\n    n = ___;\n  }\n  return soma;\n}',
    'Depois de somar o último dígito, remova-o: divida por 10 e arredonde para baixo com Math.floor.',
    'function somaDigitos(n) {\n  let soma = 0;\n  while (n > 0) {\n    soma += n % 10;\n    n = Math.floor(n / 10);\n  }\n  return soma;\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(132, 'Três dígitos', 'somaDigitos(123)', '6', 1, 1),
(132, 'Com zeros no meio', 'somaDigitos(1005)', '6', 1, 2),
(132, 'Zero', 'somaDigitos(0)', '0', 0, 3),
(132, 'Um dígito', 'somaDigitos(7)', '7', 0, 4),
(132, 'Número maior', 'somaDigitos(9876)', '30', 0, 5);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(133, 32, 'Número primo',
    'Escreva uma função ehPrimo(n) que devolva true se n for primo e false caso contrário. Números menores que 2 (incluindo 0, 1 e negativos) não são primos.',
    'codigo_livre', 'medio',
    'function ehPrimo(n) {\n  // escreva seu código aqui\n}',
    'Trate primeiro n < 2. Depois, use um for de 2 até i * i <= n: se n % i === 0, achou um divisor e não é primo.',
    'function ehPrimo(n) {\n  if (n < 2) {\n    return false;\n  }\n  for (let i = 2; i * i <= n; i++) {\n    if (n % i === 0) {\n      return false;\n    }\n  }\n  return true;\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(133, 'Primo pequeno', 'ehPrimo(7)', 'true', 1, 1),
(133, 'Não primo', 'ehPrimo(8)', 'false', 1, 2),
(133, 'Um não é primo', 'ehPrimo(1)', 'false', 0, 3),
(133, 'Dois é primo', 'ehPrimo(2)', 'true', 0, 4),
(133, 'Negativo', 'ehPrimo(-5)', 'false', 0, 5),
(133, 'Produto de primos', 'ehPrimo(91)', 'false', 0, 6),
(133, 'Primo maior', 'ehPrimo(97)', 'true', 0, 7);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(134, 32, 'Sequência de Fibonacci',
    'A função fibonacci(n) deveria devolver um array com os n primeiros termos da sequência de Fibonacci, começando por 0 e 1: fibonacci(5) devolve [0, 1, 1, 2, 3]. Para n = 0, devolve um array vazio. Mas ela está devolvendo termos a mais. Corrija.',
    'corrigir_erro', 'medio',
    'function fibonacci(n) {\n  const termos = [];\n  let a = 0;\n  let b = 1;\n  for (let i = 0; i <= n; i++) {\n    termos.push(a);\n    const proximo = a + b;\n    a = b;\n    b = proximo;\n  }\n  return termos;\n}',
    'Conte quantas vezes o laço roda para n = 1. Deveria ser uma vez só.',
    'function fibonacci(n) {\n  const termos = [];\n  let a = 0;\n  let b = 1;\n  for (let i = 0; i < n; i++) {\n    termos.push(a);\n    const proximo = a + b;\n    a = b;\n    b = proximo;\n  }\n  return termos;\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(134, 'Cinco termos', 'fibonacci(5)', '[0,1,1,2,3]', 1, 1),
(134, 'Oito termos', 'fibonacci(8)', '[0,1,1,2,3,5,8,13]', 1, 2),
(134, 'Um termo', 'fibonacci(1)', '[0]', 0, 3),
(134, 'Zero termos', 'fibonacci(0)', '[]', 0, 4);


-- Módulo 33: Desafios com Listas
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(135, 33, 'Dando a volta no array',
    'Numa lista de tamanho 4, qual índice é calculado por (3 + 2) % 4?',
    'quiz', 'facil', '5 dividido por 4 dá 1 e sobra 1.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(135, '5', 0, 1),
(135, '1', 1, 2),
(135, '3', 0, 3),
(135, '0', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(136, 33, 'Removendo duplicados',
    'Complete a função removerDuplicados(lista) para que ela devolva um novo array sem valores repetidos, mantendo a ordem da primeira aparição de cada um. removerDuplicados([3, 1, 3, 2, 1]) devolve [3, 1, 2]. Lista vazia devolve [].',
    'completar', 'facil',
    'function removerDuplicados(lista) {\n  const resultado = [];\n  for (const item of lista) {\n    if (___) {\n      resultado.push(item);\n    }\n  }\n  return resultado;\n}',
    'O item só entra se o resultado AINDA NÃO o incluir. Use ! com includes.',
    'function removerDuplicados(lista) {\n  const resultado = [];\n  for (const item of lista) {\n    if (!resultado.includes(item)) {\n      resultado.push(item);\n    }\n  }\n  return resultado;\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(136, 'Números repetidos', 'removerDuplicados([3, 1, 3, 2, 1])', '[3,1,2]', 1, 1),
(136, 'Textos repetidos', 'removerDuplicados(["a", "b", "a"])', '["a","b"]', 1, 2),
(136, 'Lista vazia', 'removerDuplicados([])', '[]', 0, 3),
(136, 'Todos iguais', 'removerDuplicados([7, 7, 7])', '[7]', 0, 4),
(136, 'Sem repetição', 'removerDuplicados([5, 4, 3])', '[5,4,3]', 0, 5);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(137, 33, 'Segundo maior',
    'Escreva uma função segundoMaior(lista) que devolva o segundo maior valor DISTINTO de uma lista de números. Em [4, 9, 9, 2], o maior é 9 e o segundo maior é 4. Se a lista não tiver pelo menos dois valores distintos (vazia, com um item ou com todos iguais), devolva null. A lista pode ter números negativos.',
    'codigo_livre', 'dificil',
    'function segundoMaior(lista) {\n  // escreva seu código aqui\n}',
    'Guarde duas variáveis começando em -Infinity: maior e segundo. Se o item for maior que o maior, o antigo maior vira segundo. Se ficar entre os dois (e for diferente do maior), vira o segundo.',
    'function segundoMaior(lista) {\n  let maior = -Infinity;\n  let segundo = -Infinity;\n  for (const n of lista) {\n    if (n > maior) {\n      segundo = maior;\n      maior = n;\n    } else if (n < maior && n > segundo) {\n      segundo = n;\n    }\n  }\n  return segundo === -Infinity ? null : segundo;\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(137, 'Lista comum', 'segundoMaior([5, 1, 9, 3])', '5', 1, 1),
(137, 'Maior repetido', 'segundoMaior([4, 9, 9, 2])', '4', 1, 2),
(137, 'Um item só', 'segundoMaior([7])', 'null', 0, 3),
(137, 'Lista vazia', 'segundoMaior([])', 'null', 0, 4),
(137, 'Todos iguais', 'segundoMaior([2, 2, 2])', 'null', 0, 5),
(137, 'Só negativos', 'segundoMaior([-1, -5, -3])', '-3', 0, 6);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(138, 33, 'Rotacionando um array',
    'A função rotacionar(lista, k) deveria devolver um novo array com os itens deslocados k posições para a direita; quem sai pelo fim volta pelo começo. rotacionar([1, 2, 3, 4, 5], 2) devolve [4, 5, 1, 2, 3]. k pode ser maior que o tamanho da lista, e lista vazia devolve []. Mas os itens estão indo parar fora do array. Corrija.',
    'corrigir_erro', 'dificil',
    'function rotacionar(lista, k) {\n  if (lista.length === 0) {\n    return [];\n  }\n  const passos = k % lista.length;\n  const resultado = [];\n  for (let i = 0; i < lista.length; i++) {\n    resultado[i + passos] = lista[i];\n  }\n  return resultado;\n}',
    'O novo índice precisa "dar a volta" quando passa do fim. Use % lista.length nele também.',
    'function rotacionar(lista, k) {\n  if (lista.length === 0) {\n    return [];\n  }\n  const passos = k % lista.length;\n  const resultado = [];\n  for (let i = 0; i < lista.length; i++) {\n    resultado[(i + passos) % lista.length] = lista[i];\n  }\n  return resultado;\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(138, 'Duas posições', 'rotacionar([1, 2, 3, 4, 5], 2)', '[4,5,1,2,3]', 1, 1),
(138, 'Uma posição', 'rotacionar(["a", "b", "c"], 1)', '["c","a","b"]', 1, 2),
(138, 'k maior que a lista', 'rotacionar([1, 2, 3], 4)', '[3,1,2]', 0, 3),
(138, 'Lista vazia', 'rotacionar([], 3)', '[]', 0, 4),
(138, 'Volta completa', 'rotacionar([1, 2, 3], 3)', '[1,2,3]', 0, 5);


-- Módulo 34: Desafios com Dados
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(139, 34, 'Ordenando do maior para o menor',
    'Qual função de comparação ordena jogadores do MAIOR para o MENOR número de pontos com sort?',
    'quiz', 'facil', 'Se o resultado for positivo, b vem antes de a.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(139, '(a, b) => a.pontos - b.pontos', 0, 1),
(139, '(a, b) => b.pontos - a.pontos', 1, 2),
(139, '(a, b) => a.pontos > b.pontos', 0, 3),
(139, '(a, b) => a.nome - b.nome', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(140, 34, 'Contando frequência',
    'Complete a função contarFrequencia(lista) para que ela devolva um objeto em que cada chave é um item da lista e o valor é quantas vezes ele aparece. contarFrequencia(["uva", "pera", "uva"]) devolve { uva: 2, pera: 1 }. Lista vazia devolve {}.',
    'completar', 'facil',
    'function contarFrequencia(lista) {\n  const contagem = {};\n  for (const item of lista) {\n    contagem[item] = ___;\n  }\n  return contagem;\n}',
    'Some 1 ao valor atual da chave; se ela ainda não existir, comece de 0 usando ||.',
    'function contarFrequencia(lista) {\n  const contagem = {};\n  for (const item of lista) {\n    contagem[item] = (contagem[item] || 0) + 1;\n  }\n  return contagem;\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(140, 'Frutas repetidas', 'contarFrequencia(["uva", "pera", "uva"])', '{"uva":2,"pera":1}', 1, 1),
(140, 'Todos diferentes', 'contarFrequencia(["a", "b", "c"])', '{"a":1,"b":1,"c":1}', 1, 2),
(140, 'Lista vazia', 'contarFrequencia([])', '{}', 0, 3),
(140, 'Um item repetido', 'contarFrequencia(["x", "x", "x"])', '{"x":3}', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(141, 34, 'Total do carrinho',
    'Escreva uma função totalCarrinho(itens) que receba uma lista de objetos { nome, preco, quantidade } e devolva o valor total da compra (preco x quantidade de cada item). Se o total for 100 ou mais, aplique 10% de desconto. Carrinho vazio devolve 0. Exemplo: [{ nome: "caderno", preco: 50, quantidade: 2 }, { nome: "caneta", preco: 10, quantidade: 1 }] soma 110 e, com desconto, devolve 99.',
    'codigo_livre', 'medio',
    'function totalCarrinho(itens) {\n  // escreva seu código aqui\n}',
    'Primeiro some preco * quantidade de todos os itens (reduce ou for). Só depois verifique se o total chegou a 100 e, se sim, multiplique por 0.9.',
    'function totalCarrinho(itens) {\n  let total = 0;\n  for (const item of itens) {\n    total += item.preco * item.quantidade;\n  }\n  if (total >= 100) {\n    total = total * 0.9;\n  }\n  return total;\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(141, 'Sem desconto', 'totalCarrinho([{ nome: "lápis", preco: 30, quantidade: 1 }, { nome: "borracha", preco: 20, quantidade: 2 }])', '70', 1, 1),
(141, 'Com desconto', 'totalCarrinho([{ nome: "caderno", preco: 50, quantidade: 2 }, { nome: "caneta", preco: 10, quantidade: 1 }])', '99', 1, 2),
(141, 'Exatamente 100 tem desconto', 'totalCarrinho([{ nome: "mochila", preco: 25, quantidade: 4 }])', '90', 0, 3),
(141, 'Carrinho vazio', 'totalCarrinho([])', '0', 0, 4),
(141, 'Logo abaixo do limite', 'totalCarrinho([{ nome: "livro", preco: 99, quantidade: 1 }])', '99', 0, 5);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(142, 34, 'Ranking de jogadores',
    'A função ranking(jogadores) recebe uma lista de objetos { nome, pontos } e deveria devolver um array só com os nomes, do jogador com MAIS pontos para o com MENOS. Lista vazia devolve []. Mas o ranking está saindo de cabeça para baixo. Corrija.',
    'corrigir_erro', 'medio',
    'function ranking(jogadores) {\n  const ordenados = [...jogadores].sort((a, b) => a.pontos - b.pontos);\n  return ordenados.map((j) => j.nome);\n}',
    'Para ordem decrescente, a subtração na função de comparação precisa começar pelo b.',
    'function ranking(jogadores) {\n  const ordenados = [...jogadores].sort((a, b) => b.pontos - a.pontos);\n  return ordenados.map((j) => j.nome);\n}',
    200, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(142, 'Três jogadores', 'ranking([{ nome: "Ana", pontos: 50 }, { nome: "Bia", pontos: 80 }, { nome: "Caio", pontos: 30 }])', '["Bia","Ana","Caio"]', 1, 1),
(142, 'Dois jogadores', 'ranking([{ nome: "Leo", pontos: 10 }, { nome: "Mia", pontos: 90 }])', '["Mia","Leo"]', 1, 2),
(142, 'Lista vazia', 'ranking([])', '[]', 0, 3),
(142, 'Com pontuação zero', 'ranking([{ nome: "Zé", pontos: 0 }, { nome: "Rui", pontos: 15 }, { nome: "Lia", pontos: 40 }])', '["Lia","Rui","Zé"]', 0, 4);


-- ---------------------------------------------------------------------
-- Prova de fim de nível da trilha 7
-- ---------------------------------------------------------------------
-- Cada questão aponta, por `modulo_referencia_id`, o módulo que ela avalia —
-- é assim que o sistema diz ao aluno reprovado O QUE revisar, em vez de
-- mandá-lo refazer tudo.
-- ---------------------------------------------------------------------
INSERT INTO modulos (id, trilha_id, titulo, tipo, nota_minima, descricao, ordem) VALUES
(35, 7, 'Prova: Desafios Práticos', 'prova', 70,
    'Avaliação das estratégias para resolver desafios com texto, números, listas e dados. Acerte 70% para avançar.',
    5);

INSERT INTO atividades (id, modulo_id, modulo_referencia_id, titulo, enunciado, tipo, dificuldade, xp_recompensa, ordem) VALUES
(143, 35, 31, 'Questão 1', 'Qual expressão devolve o texto "devfox" invertido ("xofved")?', 'quiz', 'facil', 50, 1),
(144, 35, 32, 'Questão 2', 'Se n vale 472, qual é o valor de n % 10 e de Math.floor(n / 10)?', 'quiz', 'facil', 50, 2),
(145, 35, 32, 'Questão 3', 'O MDC (máximo divisor comum) de 12 e 18 é:', 'quiz', 'facil', 50, 3),
(146, 35, 33, 'Questão 4', 'Qual código devolve a intersecção de a = [1, 2, 3] e b = [2, 3, 4], ou seja, [2, 3]?', 'quiz', 'medio', 50, 4),
(147, 35, 34, 'Questão 5', 'Você quer agrupar produtos por categoria num objeto grupos. Para cada produto p, o que garante que o array da categoria exista antes do push?', 'quiz', 'medio', 50, 5);

INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(143, '"devfox".reverse()', 0, 1),
(143, '"devfox".split("").reverse().join("")', 1, 2),
(143, '"devfox".split(" ").join("")', 0, 3),
(143, '"devfox".toLowerCase()', 0, 4),
(144, '2 e 47', 1, 1),
(144, '47 e 2', 0, 2),
(144, '4 e 72', 0, 3),
(144, '2 e 47.2', 0, 4),
(145, '2', 0, 1),
(145, '3', 0, 2),
(145, '6', 1, 3),
(145, '36', 0, 4),
(146, 'a.filter((x) => b.includes(x))', 1, 1),
(146, 'a.filter((x) => !b.includes(x))', 0, 2),
(146, 'a.concat(b)', 0, 3),
(146, 'a.map((x) => b.includes(x))', 0, 4),
(147, 'grupos[p.categoria] = grupos[p.categoria] || [];', 1, 1),
(147, 'grupos.push(p.categoria);', 0, 2),
(147, 'grupos[p.categoria] = 0;', 0, 3),
(147, 'delete grupos[p.categoria];', 0, 4);


-- =====================================================================
-- TRILHA 8: Projeto Final
-- =====================================================================

INSERT INTO modulos (id, trilha_id, titulo, descricao, teoria_texto, ordem) VALUES
(36, 8, 'Projeto: Gerenciador de Tarefas',
    'Construa, passo a passo, um pequeno gerenciador de tarefas com arrays, objetos e funções.',
    'Neste projeto você vai juntar tudo o que aprendeu para construir um programa completo: um gerenciador de tarefas, parecido com uma lista de afazeres. Ele guarda tarefas, marca as que foram feitas, mostra o que ainda falta e gera um resumo. Não é preciso nada novo: variáveis, condicionais, laços, funções, arrays e objetos bastam.\n\nO coração do projeto é o modelo de dados. Cada tarefa é um objeto com três propriedades, sempre nesta ordem: titulo (texto, como "Estudar"), prioridade (texto: "alta" ou "baixa") e concluida (boolean, que começa como false). A lista de tarefas é um array desses objetos, por exemplo: [{ titulo: "Estudar", prioridade: "alta", concluida: false }].\n\nO plano é dividir o problema em funções pequenas, cada uma com uma única responsabilidade: criarTarefa monta o objeto, adicionarTarefa coloca na lista, concluirTarefa marca como feita, pendentes lista o que falta, contarPorPrioridade agrupa as tarefas e resumo descreve a lista em uma frase. Cada passo já traz pronto o código dos passos anteriores; você escreve só a parte nova.',
    1);

-- Módulo 36: Projeto: Gerenciador de Tarefas
INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES
(148, 36, 'O modelo de dados',
    'No gerenciador, qual destes valores representa corretamente UMA tarefa nova, que ainda não foi feita?',
    'quiz', 'facil', 'Cada tarefa é um objeto com titulo, prioridade e concluida, e concluida é um boolean.', 100, 1);
INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(148, '{ titulo: "Estudar", prioridade: "alta", concluida: false }', 1, 1),
(148, '["Estudar", "alta", false]', 0, 2),
(148, '{ titulo: "Estudar", prioridade: "alta", concluida: "não" }', 0, 3),
(148, '"Estudar - alta - pendente"', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(149, 36, 'Passo 1: criar uma tarefa',
    'Complete a função criarTarefa(titulo, prioridade) para que ela devolva um objeto no formato { titulo, prioridade, concluida }, nesta ordem. Toda tarefa nova começa ainda não concluída. Por exemplo, criarTarefa("Estudar", "alta") deve devolver {"titulo":"Estudar","prioridade":"alta","concluida":false}.',
    'completar', 'facil',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: ___ };\n}',
    'Uma tarefa nova ainda não foi feita. Qual valor boolean representa "não"?',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}',
    100, 2);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(149, 'Tarefa de prioridade alta', 'criarTarefa("Estudar", "alta")', '{"titulo":"Estudar","prioridade":"alta","concluida":false}', 1, 1),
(149, 'Tarefa de prioridade baixa', 'criarTarefa("Ler", "baixa")', '{"titulo":"Ler","prioridade":"baixa","concluida":false}', 0, 2);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(150, 36, 'Passo 2: adicionar à lista',
    'A função criarTarefa já está pronta. Escreva agora adicionarTarefa(lista, tarefa), que coloca a tarefa no FINAL do array lista (alterando o próprio array) e devolve a nova quantidade de tarefas da lista. Por exemplo, adicionarTarefa([], criarTarefa("Estudar", "alta")) devolve 1. Se a lista já tiver 2 tarefas, deve devolver 3.',
    'codigo_livre', 'facil',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  // escreva seu código aqui\n}',
    'O método push coloca um item no final do array. Depois, a propriedade length diz quantos itens ele tem.',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}',
    150, 3);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(150, 'Lista vazia', 'adicionarTarefa([], criarTarefa("Estudar", "alta"))', '1', 1, 1),
(150, 'A tarefa entra no final da lista', '(() => { const l = [criarTarefa("Estudar", "alta")]; adicionarTarefa(l, criarTarefa("Ler", "baixa")); return l.map(t => t.titulo); })()', '["Estudar","Ler"]', 1, 2),
(150, 'Lista com duas tarefas', 'adicionarTarefa([criarTarefa("A", "alta"), criarTarefa("B", "baixa")], criarTarefa("C", "alta"))', '3', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(151, 36, 'Passo 3: concluir uma tarefa',
    'Escreva concluirTarefa(lista, titulo). Ela procura na lista a tarefa com esse titulo; se encontrar, muda concluida para true e devolve true. Se nenhuma tarefa tiver esse titulo (inclusive quando a lista está vazia), não altera nada e devolve false.',
    'codigo_livre', 'medio',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  // escreva seu código aqui\n}',
    'O método find devolve o primeiro objeto que satisfaz a condição, ou undefined se não achar nenhum. Como ele devolve o próprio objeto, alterar tarefa.concluida altera a tarefa dentro da lista.',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  const tarefa = lista.find(t => t.titulo === titulo);\n  if (tarefa === undefined) {\n    return false;\n  }\n  tarefa.concluida = true;\n  return true;\n}',
    150, 4);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(151, 'Tarefa existente', 'concluirTarefa([criarTarefa("Estudar", "alta")], "Estudar")', 'true', 1, 1),
(151, 'A tarefa fica marcada como concluída', '(() => { const l = [criarTarefa("Estudar", "alta"), criarTarefa("Ler", "baixa")]; concluirTarefa(l, "Ler"); return l.map(t => t.concluida); })()', '[false,true]', 1, 2),
(151, 'Título que não existe', 'concluirTarefa([criarTarefa("Estudar", "alta")], "Correr")', 'false', 0, 3),
(151, 'Lista vazia', 'concluirTarefa([], "Estudar")', 'false', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(152, 36, 'Passo 4: o que ainda falta',
    'Escreva pendentes(lista), que devolve um array com os TÍTULOS das tarefas ainda não concluídas, na mesma ordem em que aparecem na lista. Se todas estiverem concluídas, ou se a lista estiver vazia, devolva um array vazio []. Por exemplo, para Estudar (pendente) e Ler (concluída), deve devolver ["Estudar"].',
    'codigo_livre', 'medio',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  const tarefa = lista.find(t => t.titulo === titulo);\n  if (tarefa === undefined) {\n    return false;\n  }\n  tarefa.concluida = true;\n  return true;\n}\n\nfunction pendentes(lista) {\n  // escreva seu código aqui\n}',
    'Use filter para ficar só com as tarefas em que concluida é false e, depois, map para pegar só o titulo.',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  const tarefa = lista.find(t => t.titulo === titulo);\n  if (tarefa === undefined) {\n    return false;\n  }\n  tarefa.concluida = true;\n  return true;\n}\n\nfunction pendentes(lista) {\n  return lista.filter(t => !t.concluida).map(t => t.titulo);\n}',
    150, 5);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(152, 'Uma pendente e uma concluída', 'pendentes([criarTarefa("Estudar", "alta"), {titulo: "Ler", prioridade: "baixa", concluida: true}])', '["Estudar"]', 1, 1),
(152, 'Todas pendentes', 'pendentes([criarTarefa("A", "alta"), criarTarefa("B", "baixa"), criarTarefa("C", "alta")])', '["A","B","C"]', 1, 2),
(152, 'Todas concluídas', 'pendentes([{titulo: "Ler", prioridade: "baixa", concluida: true}, {titulo: "Correr", prioridade: "alta", concluida: true}])', '[]', 0, 3),
(152, 'Lista vazia', 'pendentes([])', '[]', 0, 4);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(153, 36, 'Passo 5: conserte a contagem',
    'A função contarPorPrioridade(lista) deveria devolver um objeto dizendo quantas tarefas há de cada prioridade, com as chaves na ordem em que cada prioridade aparece pela primeira vez. Por exemplo, para as prioridades "alta", "baixa", "baixa" o resultado deve ser {"alta":1,"baixa":2}. Para uma lista vazia, {}. Mas ela está contando errado. Encontre e corrija o erro.',
    'corrigir_erro', 'medio',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  const tarefa = lista.find(t => t.titulo === titulo);\n  if (tarefa === undefined) {\n    return false;\n  }\n  tarefa.concluida = true;\n  return true;\n}\n\nfunction pendentes(lista) {\n  return lista.filter(t => !t.concluida).map(t => t.titulo);\n}\n\nfunction contarPorPrioridade(lista) {\n  const contagem = {};\n  for (const tarefa of lista) {\n    if (contagem[tarefa.prioridade] === undefined) {\n      contagem[tarefa.prioridade] = 0;\n    }\n    contagem[tarefa.prioridade] = 1;\n  }\n  return contagem;\n}',
    'Teste com duas tarefas de mesma prioridade: o valor da chave deveria aumentar, mas é sobrescrito.',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  const tarefa = lista.find(t => t.titulo === titulo);\n  if (tarefa === undefined) {\n    return false;\n  }\n  tarefa.concluida = true;\n  return true;\n}\n\nfunction pendentes(lista) {\n  return lista.filter(t => !t.concluida).map(t => t.titulo);\n}\n\nfunction contarPorPrioridade(lista) {\n  const contagem = {};\n  for (const tarefa of lista) {\n    if (contagem[tarefa.prioridade] === undefined) {\n      contagem[tarefa.prioridade] = 0;\n    }\n    contagem[tarefa.prioridade] += 1;\n  }\n  return contagem;\n}',
    200, 6);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(153, 'Prioridades misturadas', 'contarPorPrioridade([criarTarefa("A", "alta"), criarTarefa("B", "baixa"), criarTarefa("C", "baixa")])', '{"alta":1,"baixa":2}', 1, 1),
(153, 'Três tarefas de prioridade alta', 'contarPorPrioridade([criarTarefa("A", "alta"), criarTarefa("B", "alta"), criarTarefa("C", "alta")])', '{"alta":3}', 0, 2),
(153, 'Lista vazia', 'contarPorPrioridade([])', '{}', 0, 3);

INSERT INTO atividades (id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem) VALUES
(154, 36, 'Passo 6: o resumo da lista',
    'Escreva resumo(lista), que devolve uma frase no formato "<total> tarefas, <concluídas> concluídas, <pendentes> pendentes". Cada palavra fica no singular quando o número dela é exatamente 1 ("1 tarefa", "1 concluída", "1 pendente") e no plural em qualquer outro caso, inclusive 0. Exemplos: "3 tarefas, 1 concluída, 2 pendentes" e, para a lista vazia, "0 tarefas, 0 concluídas, 0 pendentes".',
    'codigo_livre', 'dificil',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  const tarefa = lista.find(t => t.titulo === titulo);\n  if (tarefa === undefined) {\n    return false;\n  }\n  tarefa.concluida = true;\n  return true;\n}\n\nfunction pendentes(lista) {\n  return lista.filter(t => !t.concluida).map(t => t.titulo);\n}\n\nfunction contarPorPrioridade(lista) {\n  const contagem = {};\n  for (const tarefa of lista) {\n    if (contagem[tarefa.prioridade] === undefined) {\n      contagem[tarefa.prioridade] = 0;\n    }\n    contagem[tarefa.prioridade] += 1;\n  }\n  return contagem;\n}\n\nfunction resumo(lista) {\n  // escreva seu código aqui\n}',
    'Conte as concluídas com filter(...).length; as pendentes são o total menos as concluídas. Para cada número, escolha a palavra com um if ou com o operador ternário (n === 1 ? "..." : "...").',
    'function criarTarefa(titulo, prioridade) {\n  return { titulo: titulo, prioridade: prioridade, concluida: false };\n}\n\nfunction adicionarTarefa(lista, tarefa) {\n  lista.push(tarefa);\n  return lista.length;\n}\n\nfunction concluirTarefa(lista, titulo) {\n  const tarefa = lista.find(t => t.titulo === titulo);\n  if (tarefa === undefined) {\n    return false;\n  }\n  tarefa.concluida = true;\n  return true;\n}\n\nfunction pendentes(lista) {\n  return lista.filter(t => !t.concluida).map(t => t.titulo);\n}\n\nfunction contarPorPrioridade(lista) {\n  const contagem = {};\n  for (const tarefa of lista) {\n    if (contagem[tarefa.prioridade] === undefined) {\n      contagem[tarefa.prioridade] = 0;\n    }\n    contagem[tarefa.prioridade] += 1;\n  }\n  return contagem;\n}\n\nfunction resumo(lista) {\n  const total = lista.length;\n  const feitas = lista.filter(t => t.concluida).length;\n  const faltam = total - feitas;\n  const tTarefa = total === 1 ? "tarefa" : "tarefas";\n  const tConcluida = feitas === 1 ? "concluída" : "concluídas";\n  const tPendente = faltam === 1 ? "pendente" : "pendentes";\n  return total + " " + tTarefa + ", " + feitas + " " + tConcluida + ", " + faltam + " " + tPendente;\n}',
    150, 7);
INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES
(154, 'Três tarefas, uma concluída', 'resumo([criarTarefa("A", "alta"), {titulo: "B", prioridade: "baixa", concluida: true}, criarTarefa("C", "baixa")])', '3 tarefas, 1 concluída, 2 pendentes', 1, 1),
(154, 'Duas tarefas concluídas', 'resumo([{titulo: "A", prioridade: "alta", concluida: true}, {titulo: "B", prioridade: "baixa", concluida: true}])', '2 tarefas, 2 concluídas, 0 pendentes', 1, 2),
(154, 'Lista vazia', 'resumo([])', '0 tarefas, 0 concluídas, 0 pendentes', 0, 3),
(154, 'Uma única tarefa pendente', 'resumo([criarTarefa("A", "alta")])', '1 tarefa, 0 concluídas, 1 pendente', 0, 4);


-- ---------------------------------------------------------------------
-- Prova de fim de nível da trilha 8
-- ---------------------------------------------------------------------
-- Cada questão aponta, por `modulo_referencia_id`, o módulo que ela avalia —
-- é assim que o sistema diz ao aluno reprovado O QUE revisar, em vez de
-- mandá-lo refazer tudo.
-- ---------------------------------------------------------------------
INSERT INTO modulos (id, trilha_id, titulo, tipo, nota_minima, descricao, ordem) VALUES
(37, 8, 'Avaliação do Projeto', 'prova', 70,
    'Questões sobre as decisões do projeto. Acerte 70% para concluir.',
    2);

INSERT INTO atividades (id, modulo_id, modulo_referencia_id, titulo, enunciado, tipo, dificuldade, xp_recompensa, ordem) VALUES
(155, 37, 36, 'Questão 1', 'Por que a propriedade concluida de cada tarefa é um boolean (true/false) e não um texto?', 'quiz', 'facil', 50, 1),
(156, 37, 36, 'Questão 2', 'Qual método de array é o mais adequado para localizar UMA tarefa pelo título, como em concluirTarefa?', 'quiz', 'facil', 50, 2),
(157, 37, 36, 'Questão 3', 'Na função pendentes, por que usamos filter e depois map?', 'quiz', 'facil', 50, 3),
(158, 37, 36, 'Questão 4', 'Em contarPorPrioridade, o que acontece se a linha que atualiza a contagem usar "= 1" em vez de "+= 1"?', 'quiz', 'medio', 50, 4),
(159, 37, 36, 'Questão 5', 'Qual a principal vantagem de dividir o gerenciador em várias funções pequenas?', 'quiz', 'medio', 50, 5);

INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES
(155, 'Porque ela só tem dois estados possíveis e pode ser usada direto em condições e filtros', 1, 1),
(155, 'Porque objetos em JavaScript não aceitam propriedades de texto', 0, 2),
(155, 'Porque booleans ocupam mais espaço e deixam o programa mais seguro', 0, 3),
(155, 'Porque o método push só funciona com booleans', 0, 4),
(156, 'map', 0, 1),
(156, 'find', 1, 2),
(156, 'reduce', 0, 3),
(156, 'push', 0, 4),
(157, 'filter ordena as tarefas e map remove as concluídas', 0, 1),
(157, 'filter fica só com as tarefas não concluídas e map transforma cada uma no seu título', 1, 2),
(157, 'filter soma as tarefas e map conta quantas são', 0, 3),
(157, 'Os dois fazem a mesma coisa; usar ambos é só por estilo', 0, 4),
(158, 'O programa dá erro e para de rodar', 0, 1),
(158, 'Cada prioridade aparece com no máximo 1, mesmo tendo várias tarefas', 1, 2),
(158, 'A contagem passa a ser o dobro do valor correto', 0, 3),
(158, 'Nada muda, porque as duas formas são equivalentes', 0, 4),
(159, 'O programa fica mais rápido porque tem mais funções', 0, 1),
(159, 'Cada função tem uma responsabilidade clara e pode ser testada e corrigida separadamente', 1, 2),
(159, 'Funções pequenas dispensam o uso de arrays e objetos', 0, 3),
(159, 'Assim não é preciso usar return em nenhuma delas', 0, 4);


-- =====================================================================
-- Conferência:
--   SELECT COUNT(*) FROM trilhas;             -- 8
--   SELECT COUNT(*) FROM trilha_requisitos;   -- 7
-- =====================================================================
