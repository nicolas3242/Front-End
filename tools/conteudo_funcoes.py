# -*- coding: utf-8 -*-
"""tools/conteudo_funcoes.py — trilha Funções e Métodos (JavaScript)."""
from conteudo_base import QUIZ, COMPLETAR, CORRIGIR, LIVRE, quiz, codigo  # noqa: F401


# =====================================================================
# MÓDULO 1 — PARÂMETROS E ESCOPO
# =====================================================================

M1 = dict(
    titulo='Parâmetros e Escopo',
    descricao='Valores padrão, onde cada variável existe e funções que usam outras funções.',
    teoria=(
        'Um parâmetro pode ter um valor padrão, escrito com = na própria declaração: '
        'function saudar(nome = "visitante"). Se quem chama a função não mandar esse '
        'argumento, o parâmetro assume o valor padrão em vez de ficar undefined.\n\n'
        'Escopo é a região do código onde uma variável existe. Uma variável criada com let '
        'ou const dentro de chaves { } só vive ali dentro: fora do bloco ela simplesmente '
        'não existe. Por isso funções diferentes podem ter variáveis com o mesmo nome sem '
        'uma atrapalhar a outra.\n\n'
        'O erro mais comum é declarar a variável dentro de um if e tentar usá-la depois dele, '
        'o que gera "ReferenceError: ... is not defined". A solução é declarar a variável '
        'antes do bloco e só atribuir o valor lá dentro. Outra boa prática é dividir o '
        'trabalho: uma função pequena pode chamar outra, e cada uma faz uma coisa só.'
    ),
    atividades=[
        quiz('Valor padrão',
             'Dado function saudar(nome = "visitante") { return "Oi, " + nome; }, '
             'o que saudar() devolve?',
             [('"Oi, undefined"', False), ('"Oi, visitante"', True),
              ('"Oi, "', False), ('Um erro, porque faltou o argumento', False)],
             dica='O valor depois do = só é usado quando o argumento não é enviado.'),

        codigo(COMPLETAR, 'Desconto padrão',
               'A função calcularPreco(valor, desconto) devolve o valor com o desconto (em %) aplicado. '
               'Complete a declaração para que o desconto seja 10 quando ele não for informado. '
               'Exemplo: calcularPreco(100) devolve 90 e calcularPreco(200, 50) devolve 100.',
               'function calcularPreco(valor, desconto = ___) {\n'
               '  return valor - valor * desconto / 100;\n}',
               'O valor padrão é escrito logo depois do = na lista de parâmetros.',
               'function calcularPreco(valor, desconto = 10) {\n'
               '  return valor - valor * desconto / 100;\n}',
               [('Sem informar o desconto', 'calcularPreco(100)',     '90',  True),
                ('Com desconto informado',  'calcularPreco(200, 50)', '100', True),
                ('Outro valor sem desconto', 'calcularPreco(80)',     '72',  False),
                ('Desconto zero informado', 'calcularPreco(80, 0)',   '80',  False)]),

        codigo(LIVRE, 'Uma função chamando outra',
               'Escreva duas funções. quadrado(n) devolve n multiplicado por ele mesmo. '
               'somaDosQuadrados(a, b) devolve quadrado(a) + quadrado(b), usando a primeira função. '
               'Exemplo: somaDosQuadrados(3, 4) devolve 25.',
               'function quadrado(n) {\n  // escreva seu código aqui\n}\n\n'
               'function somaDosQuadrados(a, b) {\n  // use a função quadrado aqui\n}',
               'Dentro de somaDosQuadrados, chame quadrado(a) e quadrado(b) e some os resultados.',
               'function quadrado(n) {\n  return n * n;\n}\n\n'
               'function somaDosQuadrados(a, b) {\n  return quadrado(a) + quadrado(b);\n}',
               [('Quadrado de 5',             'quadrado(5)',             '25', True),
                ('Soma dos quadrados de 3 e 4', 'somaDosQuadrados(3, 4)', '25', True),
                ('Soma com número negativo',  'somaDosQuadrados(-1, 2)', '5',  False),
                ('Soma com zero',             'somaDosQuadrados(0, 6)',  '36', False)]),

        codigo(CORRIGIR, 'A variável que sumiu',
               'A função classificarNota(nota) deveria devolver "aprovado" para nota 7 ou mais e '
               '"reprovado" para as demais, mas dá erro ao rodar. Corrija o escopo da variável situacao.',
               'function classificarNota(nota) {\n'
               '  if (nota >= 7) {\n'
               '    let situacao = "aprovado";\n'
               '  } else {\n'
               '    let situacao = "reprovado";\n'
               '  }\n'
               '  return situacao;\n}',
               'O erro é "situacao is not defined". Declare a variável ANTES do if e, dentro dos '
               'blocos, apenas atribua o valor.',
               'function classificarNota(nota) {\n'
               '  let situacao;\n'
               '  if (nota >= 7) {\n'
               '    situacao = "aprovado";\n'
               '  } else {\n'
               '    situacao = "reprovado";\n'
               '  }\n'
               '  return situacao;\n}',
               [('Nota alta',        'classificarNota(9)',   'aprovado',  True),
                ('Nota baixa',       'classificarNota(4)',   'reprovado', True),
                ('Exatamente 7',     'classificarNota(7)',   'aprovado',  False),
                ('Quase lá',         'classificarNota(6.9)', 'reprovado', False)],
               dificuldade='medio'),
    ])


# =====================================================================
# MÓDULO 2 — ARROW FUNCTIONS E CALLBACKS
# =====================================================================

M2 = dict(
    titulo='Arrow Functions e Callbacks',
    descricao='Funções como valores: guardar em variáveis e passar como argumento.',
    teoria=(
        'Arrow function é um jeito mais curto de escrever uma função: '
        'const dobro = (n) => n * 2; faz o mesmo que uma function que devolve n * 2. '
        'Quando o corpo é uma única expressão, sem chaves, o resultado é devolvido '
        'automaticamente, sem precisar escrever return.\n\n'
        'Em JavaScript, funções são valores como números e textos: podem ser guardadas em '
        'variáveis e enviadas para outras funções. Uma função recebida como argumento se chama '
        'callback. Quem recebe decide quando chamá-la, escrevendo fn(valor).\n\n'
        'O erro mais comum é colocar chaves na arrow function e esquecer o return: '
        'n => { n * 2 } devolve undefined, porque com chaves o retorno deixa de ser automático. '
        'Outro erro frequente é passar o resultado em vez da função: executar(dobro(3)) '
        'envia o número 6, e não a função dobro.'
    ),
    atividades=[
        quiz('Lendo uma arrow function',
             'Dado const dobro = n => n * 2;, qual é o valor de dobro(4)?',
             [('4', False), ('8', True), ('undefined', False), ('"n * 2"', False)],
             dica='Sem chaves, a expressão depois da seta é devolvida automaticamente.'),

        codigo(COMPLETAR, 'Total da compra',
               'Complete a arrow function calcularTotal(preco, quantidade) para que ela devolva '
               'o preço multiplicado pela quantidade. Exemplo: calcularTotal(10, 3) devolve 30.',
               'const calcularTotal = (preco, quantidade) => ___;',
               'Depois da seta, escreva só a expressão que deve ser devolvida.',
               'const calcularTotal = (preco, quantidade) => preco * quantidade;',
               [('Três unidades',    'calcularTotal(10, 3)',  '30', True),
                ('Preço quebrado',   'calcularTotal(2.5, 4)', '10', True),
                ('Quantidade zero',  'calcularTotal(7, 0)',   '0',  False)]),

        codigo(LIVRE, 'Aplicar duas vezes',
               'Escreva uma função aplicarDuasVezes(fn, valor) que chame a função fn sobre valor e, '
               'depois, chame fn de novo sobre o resultado, devolvendo o valor final. '
               'Exemplo: aplicarDuasVezes(n => n + 3, 1) devolve 7.',
               'function aplicarDuasVezes(fn, valor) {\n  // escreva seu código aqui\n}',
               'Guarde fn(valor) em uma variável e devolva fn aplicada a essa variável. '
               'Ou, direto: fn(fn(valor)).',
               'function aplicarDuasVezes(fn, valor) {\n  return fn(fn(valor));\n}',
               [('Somando 3 duas vezes',   'aplicarDuasVezes(n => n + 3, 1)',       '7',    True),
                ('Dobrando duas vezes',    'aplicarDuasVezes(n => n * 2, 5)',       '20',   True),
                ('Funciona com textos',    'aplicarDuasVezes(t => t + "!", "oi")',  'oi!!', False),
                ('Subtraindo duas vezes',  'aplicarDuasVezes(n => n - 10, 0)',      '-20',  False)]),

        codigo(CORRIGIR, 'A metade que sumiu',
               'A arrow function metade(n) deveria devolver a metade do número, e a função executar(fn, x) '
               'apenas chama o callback. Mas metade está devolvendo undefined. Corrija a arrow function.',
               'const metade = n => { n / 2 };\n\n'
               'function executar(fn, x) {\n  return fn(x);\n}',
               'Com chaves, a arrow function precisa de return. Remova as chaves ou escreva return.',
               'const metade = n => n / 2;\n\n'
               'function executar(fn, x) {\n  return fn(x);\n}',
               [('Metade de 8',             'metade(8)',            '4',   True),
                ('Usada como callback',     'executar(metade, 10)', '5',   True),
                ('Metade de número ímpar',  'metade(3)',            '1.5', False),
                ('Callback com negativo',   'executar(metade, -6)', '-3',  False)],
               dificuldade='medio'),
    ])


# =====================================================================
# MÓDULO 3 — MÉTODOS DE ARRAY
# =====================================================================

M3 = dict(
    titulo='Métodos de Array',
    descricao='map, filter, reduce, find, some, every e includes na prática.',
    teoria=(
        'Arrays têm métodos prontos que recebem um callback. map cria um array novo '
        'transformando cada item; filter cria um array novo só com os itens que passam num '
        'teste; find devolve o primeiro item que passa no teste (ou undefined); some responde '
        'se ALGUM item passa e every se TODOS passam; includes diz se um valor está no array.\n\n'
        'reduce junta todos os itens em um único valor. Ele recebe um callback com o acumulado '
        'e o item atual, e um valor inicial: numeros.reduce((soma, n) => soma + n, 0) soma tudo '
        'começando do zero. Esses métodos podem ser encadeados: '
        'numeros.filter(n => n > 0).map(n => n * 2).\n\n'
        'O erro mais comum é esquecer o valor inicial do reduce. Com um array vazio, '
        'reduce sem valor inicial quebra com "Reduce of empty array with no initial value". '
        'Outro engano frequente é usar filter quando se quer um único item: filter sempre '
        'devolve um array, mesmo que tenha só um elemento; para um item só, use find.'
    ),
    atividades=[
        quiz('Escolhendo o método',
             'Qual método devolve um NOVO array contendo apenas os itens que passam em um teste?',
             [('map', False), ('filter', True), ('find', False), ('includes', False)],
             dica='O nome do método em inglês quer dizer "filtrar".'),

        codigo(COMPLETAR, 'Dobrando com map',
               'Complete a função dobrarTodos(numeros) para que ela devolva um novo array com cada '
               'número multiplicado por 2. Exemplo: dobrarTodos([1, 2, 3]) devolve [2,4,6].',
               'function dobrarTodos(numeros) {\n  return numeros.___(n => n * 2);\n}',
               'Você quer transformar cada item, mantendo a mesma quantidade de itens.',
               'function dobrarTodos(numeros) {\n  return numeros.map(n => n * 2);\n}',
               [('Três números',        'dobrarTodos([1, 2, 3])', '[2,4,6]',   True),
                ('Com negativo',        'dobrarTodos([-5, 10])',  '[-10,20]',  True),
                ('Array vazio',         'dobrarTodos([])',        '[]',        False),
                ('Um número só',        'dobrarTodos([0.5])',     '[1]',       False)]),

        codigo(LIVRE, 'Soma dos pares',
               'Escreva uma função somarPares(numeros) que devolva a soma apenas dos números pares '
               'do array. Se não houver pares, devolva 0. Exemplo: somarPares([1, 2, 3, 4]) devolve 6. '
               'Tente usar filter e reduce.',
               'function somarPares(numeros) {\n  // escreva seu código aqui\n}',
               'Primeiro filtre com n % 2 === 0; depois some com reduce((soma, n) => soma + n, 0).',
               'function somarPares(numeros) {\n'
               '  return numeros.filter(n => n % 2 === 0).reduce((soma, n) => soma + n, 0);\n}',
               [('Mistura de pares e ímpares', 'somarPares([1, 2, 3, 4])',   '6',  True),
                ('Sem nenhum par',             'somarPares([7, 9])',         '0',  True),
                ('Com negativo',               'somarPares([10, -2, 5])',    '8',  False),
                ('Array vazio',                'somarPares([])',             '0',  False)]),

        codigo(CORRIGIR, 'Carrinho vazio',
               'A função totalCarrinho(precos) deveria devolver a soma dos preços, e 0 quando o carrinho '
               'estiver vazio. Com o carrinho vazio, porém, ela quebra. Corrija o reduce.',
               'function totalCarrinho(precos) {\n'
               '  return precos.reduce((soma, preco) => soma + preco);\n}',
               'O erro é "Reduce of empty array with no initial value". Passe o valor inicial 0 '
               'como segundo argumento do reduce.',
               'function totalCarrinho(precos) {\n'
               '  return precos.reduce((soma, preco) => soma + preco, 0);\n}',
               [('Três produtos',   'totalCarrinho([10, 20, 5])', '35', True),
                ('Carrinho vazio',  'totalCarrinho([])',          '0',  True),
                ('Um produto só',   'totalCarrinho([7.5])',       '7.5', False)],
               dificuldade='medio'),
    ])


# =====================================================================
# MÓDULO 4 — OBJETOS E MÉTODOS
# =====================================================================

M4 = dict(
    titulo='Objetos e Métodos',
    descricao='Propriedades, métodos, a palavra this e Object.keys.',
    teoria=(
        'Um objeto agrupa informações relacionadas em pares chave: valor, como '
        '{ nome: "Ana", idade: 20 }. Cada valor é acessado com ponto (pessoa.nome) ou com '
        'colchetes (pessoa["nome"]). Quando o valor de uma propriedade é uma função, ela se '
        'chama método: jogador.ganhar(10).\n\n'
        'Dentro de um método, a palavra this representa o próprio objeto que chamou o método. '
        'Assim, this.pontos lê ou altera a propriedade pontos desse objeto. Para percorrer um '
        'objeto, Object.keys(obj) devolve um array com os nomes das chaves e Object.values(obj) '
        'um array com os valores.\n\n'
        'O erro mais comum é escrever um método como arrow function: ganhar: (qtd) => { ... }. '
        'Arrow functions não têm this próprio, então this.pontos não aponta para o objeto e o '
        'resultado vira NaN ou um erro. Para métodos que usam this, use a forma '
        'ganhar(qtd) { ... } ou ganhar: function (qtd) { ... }.'
    ),
    atividades=[
        quiz('Quem é o this?',
             'Dentro de um método escrito como depositar(valor) { this.saldo += valor; }, '
             'a quem this se refere?',
             [('Ao próprio objeto que chamou o método', True),
              ('Ao parâmetro valor', False),
              ('À última variável criada no programa', False),
              ('A nada: this sempre vale undefined', False)],
             dica='Em conta.depositar(50), quem está antes do ponto?'),

        codigo(COMPLETAR, 'Conta bancária',
               'A função criarConta(saldoInicial) devolve um objeto com a propriedade saldo e o método '
               'depositar(valor), que soma o valor ao saldo e devolve o novo saldo. Complete a linha que '
               'altera o saldo do próprio objeto. Exemplo: criarConta(100).depositar(50) devolve 150.',
               'function criarConta(saldoInicial) {\n'
               '  return {\n'
               '    saldo: saldoInicial,\n'
               '    depositar(valor) {\n'
               '      ___ += valor;\n'
               '      return this.saldo;\n'
               '    }\n'
               '  };\n}',
               'Para falar da propriedade saldo do próprio objeto, use this seguido de ponto.',
               'function criarConta(saldoInicial) {\n'
               '  return {\n'
               '    saldo: saldoInicial,\n'
               '    depositar(valor) {\n'
               '      this.saldo += valor;\n'
               '      return this.saldo;\n'
               '    }\n'
               '  };\n}',
               [('Depósito simples',       'criarConta(100).depositar(50)', '150', True),
                ('Conta começando do zero', 'criarConta(0).depositar(20)',  '20',  False),
                ('Dois depósitos seguidos',
                 '(() => { const c = criarConta(10); c.depositar(5); return c.depositar(5); })()',
                 '20', False)]),

        codigo(LIVRE, 'Listando as chaves',
               'Escreva uma função listarChaves(obj) que devolva um texto com os nomes das chaves do '
               'objeto separados por vírgula e espaço. Exemplo: listarChaves({ nome: "Ana", idade: 20 }) '
               'devolve "nome, idade".',
               'function listarChaves(obj) {\n  // escreva seu código aqui\n}',
               'Object.keys(obj) devolve um array com as chaves; o método join(", ") junta os itens '
               'de um array em um texto.',
               'function listarChaves(obj) {\n  return Object.keys(obj).join(", ");\n}',
               [('Duas chaves',   'listarChaves({ nome: "Ana", idade: 20 })', 'nome, idade', True),
                ('Uma chave só',  'listarChaves({ cor: "laranja" })',         'cor',         True),
                ('Três chaves',   'listarChaves({ a: 1, b: 2, c: 3 })',       'a, b, c',     False)]),

        codigo(CORRIGIR, 'Pontos que não somam',
               'A função criarJogador(nome) devolve um objeto com nome, pontos (começando em 0) e o método '
               'ganhar(qtd), que soma qtd aos pontos e devolve o total. Mas ganhar não altera os pontos do '
               'jogador. Corrija o método.',
               'function criarJogador(nome) {\n'
               '  return {\n'
               '    nome: nome,\n'
               '    pontos: 0,\n'
               '    ganhar: (qtd) => {\n'
               '      this.pontos += qtd;\n'
               '      return this.pontos;\n'
               '    }\n'
               '  };\n}',
               'Arrow functions não têm this próprio. Troque por ganhar(qtd) { ... } para que this '
               'seja o objeto do jogador.',
               'function criarJogador(nome) {\n'
               '  return {\n'
               '    nome: nome,\n'
               '    pontos: 0,\n'
               '    ganhar(qtd) {\n'
               '      this.pontos += qtd;\n'
               '      return this.pontos;\n'
               '    }\n'
               '  };\n}',
               [('Primeira pontuação', 'criarJogador("Fox").ganhar(10)', '10', True),
                ('Pontos acumulando',
                 '(() => { const j = criarJogador("Ana"); j.ganhar(5); return j.ganhar(3); })()',
                 '8', False)],
               dificuldade='medio'),
    ])


# =====================================================================
# PROVA
# =====================================================================

PROVA = dict(
    titulo='Prova: Funções e Métodos',
    descricao='Avaliação de parâmetros e escopo, arrow functions, métodos de array e objetos. '
              'Acerte 70% para avançar.',
    questoes=[
        (0, 'Questão 1',
         'Uma variável foi declarada com let dentro do bloco de um if. O que acontece ao usá-la '
         'depois que o if termina?',
         [('Ela continua valendo normalmente', False),
          ('Ocorre um ReferenceError, porque ela só existe dentro do bloco', True),
          ('Ela passa a valer 0', False),
          ('Ela vira uma variável global', False)]),
        (1, 'Questão 2',
         'O que a arrow function const f = n => { n + 1 }; devolve ao chamar f(5)?',
         [('6', False), ('5', False), ('undefined', True), ('Um erro de sintaxe', False)]),
        (2, 'Questão 3',
         'Qual é o resultado de [3, 8, 12].find(n => n > 5)?',
         [('[8, 12]', False), ('8', True), ('true', False), ('12', False)]),
        (2, 'Questão 4',
         'Qual método responde true se PELO MENOS UM item do array passa no teste?',
         [('every', False), ('map', False), ('some', True), ('reduce', False)]),
        (3, 'Questão 5',
         'O que Object.keys({ nome: "Fox", nivel: 3 }) devolve?',
         [('["Fox", 3]', False), ('["nome", "nivel"]', True),
          ('2', False), ('{ nome: "Fox" }', False)]),
    ])


TRILHA = dict(titulo='Funções e Métodos', descricao='Organize seu código em funções e métodos reutilizáveis.',
              nivel='intermediario', icone='funcao', linguagem='javascript',
              modulos=[M1, M2, M3, M4], prova=PROVA)
