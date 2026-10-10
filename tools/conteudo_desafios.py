# -*- coding: utf-8 -*-
"""tools/conteudo_desafios.py — trilha Desafios Práticos (JavaScript)."""
from conteudo_base import QUIZ, COMPLETAR, CORRIGIR, LIVRE, quiz, codigo  # noqa: F401


# =====================================================================
# MÓDULO 1 — DESAFIOS COM TEXTO
# =====================================================================

M1 = dict(
    titulo='Desafios com Texto',
    descricao='Estratégias para analisar e transformar strings.',
    teoria=(
        'Antes de escrever qualquer linha, resolva o problema com um exemplo na mão. '
        'Pegue uma frase curta, faça o processo no papel e anote cada passo. Esses passos '
        'viram o algoritmo: "percorrer cada letra", "guardar numa lista", "juntar no final".\n\n'
        'Muitos desafios com texto ficam fáceis depois de uma NORMALIZAÇÃO: deixar tudo em '
        'minúsculas com toLowerCase() e remover o que não interessa, como espaços. Assim '
        '"Ana" e "ana" passam a ser a mesma coisa, e a comparação deixa de depender de detalhes.\n\n'
        'Outra estratégia poderosa é quebrar o texto em partes: split("") separa letras e '
        'split(" ") separa palavras. Com um array em mãos, você pode inverter, filtrar ou contar, '
        'e depois usar join para montar o texto de volta. Sempre teste o caso da string vazia.'
    ),
    atividades=[
        quiz('Normalizando antes de comparar',
             'Você quer saber se "Arara" é um palíndromo (lê-se igual de trás para frente). '
             'Qual é o primeiro passo mais seguro?',
             [('Comparar o texto original com ele mesmo', False),
              ('Converter tudo para minúsculas antes de inverter e comparar', True),
              ('Contar quantas letras a palavra tem', False),
              ('Trocar as vogais por números', False)],
             dica='"A" e "a" são caracteres diferentes para o JavaScript.'),

        codigo(COMPLETAR, 'Contando vogais',
               'Complete a função contarVogais(texto) para que ela devolva quantas vogais (a, e, i, o, u) '
               'o texto tem. Letras maiúsculas também contam. Texto vazio deve devolver 0. '
               'Considere apenas vogais sem acento.',
               'function contarVogais(texto) {\n'
               '  const vogais = "aeiou";\n'
               '  let total = 0;\n'
               '  for (const letra of texto.toLowerCase()) {\n'
               '    if (___) {\n'
               '      total++;\n'
               '    }\n'
               '  }\n'
               '  return total;\n'
               '}',
               'Verifique se a string vogais inclui a letra atual com o método includes.',
               'function contarVogais(texto) {\n'
               '  const vogais = "aeiou";\n'
               '  let total = 0;\n'
               '  for (const letra of texto.toLowerCase()) {\n'
               '    if (vogais.includes(letra)) {\n'
               '      total++;\n'
               '    }\n'
               '  }\n'
               '  return total;\n'
               '}',
               [('Palavra simples',        'contarVogais("programar")', '3', True),
                ('Com maiúsculas',         'contarVogais("DEVFOX")',    '2', True),
                ('Texto vazio',            'contarVogais("")',          '0', False),
                ('Sem nenhuma vogal',      'contarVogais("xyz")',       '0', False),
                ('Frase com espaços',      'contarVogais("Oi Ana")',    '4', False)]),

        codigo(LIVRE, 'Palíndromo',
               'Escreva uma função ehPalindromo(texto) que devolva true se o texto for lido igual '
               'de frente para trás e de trás para frente, e false caso contrário. Ignore espaços e '
               'diferenças entre maiúsculas e minúsculas: "A sacada da casa" é palíndromo. '
               'Um texto vazio é considerado palíndromo (devolve true).',
               'function ehPalindromo(texto) {\n  // escreva seu código aqui\n}',
               'Normalize primeiro: toLowerCase() e remova os espaços (split(" ").join("")). '
               'Depois inverta com split("").reverse().join("") e compare.',
               'function ehPalindromo(texto) {\n'
               '  const limpo = texto.toLowerCase().split(" ").join("");\n'
               '  const invertido = limpo.split("").reverse().join("");\n'
               '  return limpo === invertido;\n'
               '}',
               [('Palavra palíndromo',     'ehPalindromo("arara")',            'true',  True),
                ('Palavra comum',          'ehPalindromo("devfox")',           'false', True),
                ('Com maiúscula',          'ehPalindromo("Ana")',              'true',  False),
                ('Frase com espaços',      'ehPalindromo("A sacada da casa")', 'true',  False),
                ('Texto vazio',            'ehPalindromo("")',                 'true',  False),
                ('Quase palíndromo',       'ehPalindromo("ab")',               'false', False)],
               dificuldade='medio'),

        codigo(CORRIGIR, 'Invertendo a ordem das palavras',
               'A função inverterPalavras(frase) deveria devolver as palavras da frase em ordem inversa, '
               'separadas por um espaço: inverterPalavras("eu gosto de codar") deve devolver '
               '"codar de gosto eu". Com uma só palavra, devolve a própria palavra; com texto vazio, '
               'devolve texto vazio. Mas a função está perdendo uma palavra. Encontre e corrija o erro.',
               'function inverterPalavras(frase) {\n'
               '  const palavras = frase.split(" ");\n'
               '  const resultado = [];\n'
               '  for (let i = palavras.length - 1; i > 0; i--) {\n'
               '    resultado.push(palavras[i]);\n'
               '  }\n'
               '  return resultado.join(" ");\n'
               '}',
               'Qual é o índice da primeira palavra? Confira se o laço chega até ele.',
               'function inverterPalavras(frase) {\n'
               '  const palavras = frase.split(" ");\n'
               '  const resultado = [];\n'
               '  for (let i = palavras.length - 1; i >= 0; i--) {\n'
               '    resultado.push(palavras[i]);\n'
               '  }\n'
               '  return resultado.join(" ");\n'
               '}',
               [('Frase de quatro palavras', 'inverterPalavras("eu gosto de codar")', 'codar de gosto eu', True),
                ('Duas palavras',            'inverterPalavras("olá mundo")',         'mundo olá',         True),
                ('Uma palavra só',           'inverterPalavras("DevFox")',            'DevFox',            False),
                ('Texto vazio',              'inverterPalavras("")',                  '',                  False)],
               dificuldade='medio'),
    ])


# =====================================================================
# MÓDULO 2 — DESAFIOS COM NÚMEROS
# =====================================================================

M2 = dict(
    titulo='Desafios com Números',
    descricao='Primos, dígitos, sequências e divisores.',
    teoria=(
        'Em desafios numéricos, a estratégia é transformar a definição matemática em passos. '
        '"Primo é o número maior que 1 que só é divisível por 1 e por ele mesmo" vira: '
        'se for menor que 2, não é primo; senão, procure um divisor entre 2 e o próprio número.\n\n'
        'Com os operadores % e Math.floor você desmonta um número: n % 10 devolve o último '
        'dígito e Math.floor(n / 10) remove esse dígito. Repetindo isso num while até o número '
        'chegar a 0, você visita cada dígito. Sequências como Fibonacci seguem a mesma ideia: '
        'guarde os dois valores anteriores e calcule o próximo a cada volta.\n\n'
        'Pense também em eficiência e nos limites. Para testar se n é primo, basta procurar '
        'divisores até a raiz quadrada de n, porque todo divisor maior tem um par menor. '
        'E sempre teste os casos de borda: 0, 1, 2 e números negativos.'
    ),
    atividades=[
        quiz('Até onde procurar divisores',
             'Para verificar se um número n é primo, até que valor basta procurar divisores?',
             [('Até n - 1, obrigatoriamente', False),
              ('Até a raiz quadrada de n', True),
              ('Até 10', False),
              ('Até n * 2', False)],
             dica='Se 36 = 4 x 9, o divisor 9 tem um par menor (4). Onde os pares se encontram?'),

        codigo(COMPLETAR, 'Soma dos dígitos',
               'Complete a função somaDigitos(n) para que ela devolva a soma dos dígitos de um número '
               'inteiro não negativo. Por exemplo, somaDigitos(123) devolve 6. Para 0, devolve 0.',
               'function somaDigitos(n) {\n'
               '  let soma = 0;\n'
               '  while (n > 0) {\n'
               '    soma += n % 10;\n'
               '    n = ___;\n'
               '  }\n'
               '  return soma;\n'
               '}',
               'Depois de somar o último dígito, remova-o: divida por 10 e arredonde para baixo com Math.floor.',
               'function somaDigitos(n) {\n'
               '  let soma = 0;\n'
               '  while (n > 0) {\n'
               '    soma += n % 10;\n'
               '    n = Math.floor(n / 10);\n'
               '  }\n'
               '  return soma;\n'
               '}',
               [('Três dígitos',       'somaDigitos(123)',  '6',  True),
                ('Com zeros no meio',  'somaDigitos(1005)', '6',  True),
                ('Zero',               'somaDigitos(0)',    '0',  False),
                ('Um dígito',          'somaDigitos(7)',    '7',  False),
                ('Número maior',       'somaDigitos(9876)', '30', False)]),

        codigo(LIVRE, 'Número primo',
               'Escreva uma função ehPrimo(n) que devolva true se n for primo e false caso contrário. '
               'Números menores que 2 (incluindo 0, 1 e negativos) não são primos.',
               'function ehPrimo(n) {\n  // escreva seu código aqui\n}',
               'Trate primeiro n < 2. Depois, use um for de 2 até i * i <= n: se n % i === 0, '
               'achou um divisor e não é primo.',
               'function ehPrimo(n) {\n'
               '  if (n < 2) {\n'
               '    return false;\n'
               '  }\n'
               '  for (let i = 2; i * i <= n; i++) {\n'
               '    if (n % i === 0) {\n'
               '      return false;\n'
               '    }\n'
               '  }\n'
               '  return true;\n'
               '}',
               [('Primo pequeno',        'ehPrimo(7)',  'true',  True),
                ('Não primo',            'ehPrimo(8)',  'false', True),
                ('Um não é primo',       'ehPrimo(1)',  'false', False),
                ('Dois é primo',         'ehPrimo(2)',  'true',  False),
                ('Negativo',             'ehPrimo(-5)', 'false', False),
                ('Produto de primos',    'ehPrimo(91)', 'false', False),
                ('Primo maior',          'ehPrimo(97)', 'true',  False)],
               dificuldade='medio'),

        codigo(CORRIGIR, 'Sequência de Fibonacci',
               'A função fibonacci(n) deveria devolver um array com os n primeiros termos da sequência '
               'de Fibonacci, começando por 0 e 1: fibonacci(5) devolve [0, 1, 1, 2, 3]. '
               'Para n = 0, devolve um array vazio. Mas ela está devolvendo termos a mais. Corrija.',
               'function fibonacci(n) {\n'
               '  const termos = [];\n'
               '  let a = 0;\n'
               '  let b = 1;\n'
               '  for (let i = 0; i <= n; i++) {\n'
               '    termos.push(a);\n'
               '    const proximo = a + b;\n'
               '    a = b;\n'
               '    b = proximo;\n'
               '  }\n'
               '  return termos;\n'
               '}',
               'Conte quantas vezes o laço roda para n = 1. Deveria ser uma vez só.',
               'function fibonacci(n) {\n'
               '  const termos = [];\n'
               '  let a = 0;\n'
               '  let b = 1;\n'
               '  for (let i = 0; i < n; i++) {\n'
               '    termos.push(a);\n'
               '    const proximo = a + b;\n'
               '    a = b;\n'
               '    b = proximo;\n'
               '  }\n'
               '  return termos;\n'
               '}',
               [('Cinco termos', 'fibonacci(5)', '[0,1,1,2,3]',          True),
                ('Oito termos',  'fibonacci(8)', '[0,1,1,2,3,5,8,13]',   True),
                ('Um termo',     'fibonacci(1)', '[0]',                  False),
                ('Zero termos',  'fibonacci(0)', '[]',                   False)],
               dificuldade='medio'),
    ])


# =====================================================================
# MÓDULO 3 — DESAFIOS COM LISTAS
# =====================================================================

M3 = dict(
    titulo='Desafios com Listas',
    descricao='Duplicados, posições e comparações entre arrays.',
    teoria=(
        'Um padrão muito comum com listas é construir um resultado novo: crie um array vazio, '
        'percorra a lista original e decida, item a item, o que entra. É assim que se removem '
        'duplicados (só entra quem ainda não está no resultado) e que se calcula a intersecção '
        'de duas listas (só entra quem também aparece na outra).\n\n'
        'Quando o problema pede "o maior", "o segundo maior" ou "o menor", mantenha variáveis '
        'que guardam os melhores valores vistos até agora e atualize-as a cada item. Pense no '
        'que acontece com valores repetidos, listas com um único item e listas vazias: o '
        'enunciado deve dizer o que devolver nesses casos.\n\n'
        'Para problemas de posição, como rotacionar, use o operador %: (i + k) % tamanho faz o '
        'índice "dar a volta" no fim do array. E k % tamanho resolve rotações maiores que a '
        'própria lista, já que rotacionar 5 vezes uma lista de 5 itens é o mesmo que não mexer.'
    ),
    atividades=[
        quiz('Dando a volta no array',
             'Numa lista de tamanho 4, qual índice é calculado por (3 + 2) % 4?',
             [('5', False), ('1', True), ('3', False), ('0', False)],
             dica='5 dividido por 4 dá 1 e sobra 1.'),

        codigo(COMPLETAR, 'Removendo duplicados',
               'Complete a função removerDuplicados(lista) para que ela devolva um novo array sem valores '
               'repetidos, mantendo a ordem da primeira aparição de cada um. '
               'removerDuplicados([3, 1, 3, 2, 1]) devolve [3, 1, 2]. Lista vazia devolve [].',
               'function removerDuplicados(lista) {\n'
               '  const resultado = [];\n'
               '  for (const item of lista) {\n'
               '    if (___) {\n'
               '      resultado.push(item);\n'
               '    }\n'
               '  }\n'
               '  return resultado;\n'
               '}',
               'O item só entra se o resultado AINDA NÃO o incluir. Use ! com includes.',
               'function removerDuplicados(lista) {\n'
               '  const resultado = [];\n'
               '  for (const item of lista) {\n'
               '    if (!resultado.includes(item)) {\n'
               '      resultado.push(item);\n'
               '    }\n'
               '  }\n'
               '  return resultado;\n'
               '}',
               [('Números repetidos',  'removerDuplicados([3, 1, 3, 2, 1])',        '[3,1,2]',             True),
                ('Textos repetidos',   'removerDuplicados(["a", "b", "a"])',        '["a","b"]',           True),
                ('Lista vazia',        'removerDuplicados([])',                     '[]',                  False),
                ('Todos iguais',       'removerDuplicados([7, 7, 7])',              '[7]',                 False),
                ('Sem repetição',      'removerDuplicados([5, 4, 3])',              '[5,4,3]',             False)]),

        codigo(LIVRE, 'Segundo maior',
               'Escreva uma função segundoMaior(lista) que devolva o segundo maior valor DISTINTO de uma '
               'lista de números. Em [4, 9, 9, 2], o maior é 9 e o segundo maior é 4. Se a lista não tiver '
               'pelo menos dois valores distintos (vazia, com um item ou com todos iguais), devolva null. '
               'A lista pode ter números negativos.',
               'function segundoMaior(lista) {\n  // escreva seu código aqui\n}',
               'Guarde duas variáveis começando em -Infinity: maior e segundo. Se o item for maior que o maior, '
               'o antigo maior vira segundo. Se ficar entre os dois (e for diferente do maior), vira o segundo.',
               'function segundoMaior(lista) {\n'
               '  let maior = -Infinity;\n'
               '  let segundo = -Infinity;\n'
               '  for (const n of lista) {\n'
               '    if (n > maior) {\n'
               '      segundo = maior;\n'
               '      maior = n;\n'
               '    } else if (n < maior && n > segundo) {\n'
               '      segundo = n;\n'
               '    }\n'
               '  }\n'
               '  return segundo === -Infinity ? null : segundo;\n'
               '}',
               [('Lista comum',          'segundoMaior([5, 1, 9, 3])',    '5',    True),
                ('Maior repetido',       'segundoMaior([4, 9, 9, 2])',    '4',    True),
                ('Um item só',           'segundoMaior([7])',             'null', False),
                ('Lista vazia',          'segundoMaior([])',              'null', False),
                ('Todos iguais',         'segundoMaior([2, 2, 2])',       'null', False),
                ('Só negativos',         'segundoMaior([-1, -5, -3])',    '-3',   False)],
               dificuldade='dificil'),

        codigo(CORRIGIR, 'Rotacionando um array',
               'A função rotacionar(lista, k) deveria devolver um novo array com os itens deslocados k '
               'posições para a direita; quem sai pelo fim volta pelo começo. rotacionar([1, 2, 3, 4, 5], 2) '
               'devolve [4, 5, 1, 2, 3]. k pode ser maior que o tamanho da lista, e lista vazia devolve []. '
               'Mas os itens estão indo parar fora do array. Corrija.',
               'function rotacionar(lista, k) {\n'
               '  if (lista.length === 0) {\n'
               '    return [];\n'
               '  }\n'
               '  const passos = k % lista.length;\n'
               '  const resultado = [];\n'
               '  for (let i = 0; i < lista.length; i++) {\n'
               '    resultado[i + passos] = lista[i];\n'
               '  }\n'
               '  return resultado;\n'
               '}',
               'O novo índice precisa "dar a volta" quando passa do fim. Use % lista.length nele também.',
               'function rotacionar(lista, k) {\n'
               '  if (lista.length === 0) {\n'
               '    return [];\n'
               '  }\n'
               '  const passos = k % lista.length;\n'
               '  const resultado = [];\n'
               '  for (let i = 0; i < lista.length; i++) {\n'
               '    resultado[(i + passos) % lista.length] = lista[i];\n'
               '  }\n'
               '  return resultado;\n'
               '}',
               [('Duas posições',          'rotacionar([1, 2, 3, 4, 5], 2)', '[4,5,1,2,3]', True),
                ('Uma posição',            'rotacionar(["a", "b", "c"], 1)', '["c","a","b"]', True),
                ('k maior que a lista',    'rotacionar([1, 2, 3], 4)',       '[3,1,2]',     False),
                ('Lista vazia',            'rotacionar([], 3)',              '[]',          False),
                ('Volta completa',         'rotacionar([1, 2, 3], 3)',       '[1,2,3]',     False)],
               dificuldade='dificil'),
    ])


# =====================================================================
# MÓDULO 4 — DESAFIOS COM DADOS
# =====================================================================

M4 = dict(
    titulo='Desafios com Dados',
    descricao='Contar, somar, agrupar e ordenar listas de objetos.',
    teoria=(
        'Programas reais trabalham com listas de objetos: produtos de um carrinho, jogadores de '
        'um ranking, pedidos de uma loja. A estratégia é sempre a mesma: descubra qual campo de '
        'cada objeto importa e o que deve acontecer com ele (somar, contar, comparar).\n\n'
        'Um objeto vazio funciona como um contador de muitas coisas ao mesmo tempo. Para cada '
        'item, use-o como chave: contagem[item] = (contagem[item] || 0) + 1. O "|| 0" resolve a '
        'primeira aparição, quando a chave ainda não existe. A mesma ideia serve para agrupar: '
        'em vez de somar 1, você empurra o item num array daquela chave.\n\n'
        'Para ordenar objetos, use sort com uma função de comparação: (a, b) => a.pontos - b.pontos '
        'deixa em ordem crescente e b.pontos - a.pontos em ordem decrescente. Lembre que sort '
        'altera o array original; se não quiser isso, ordene uma cópia feita com [...lista].'
    ),
    atividades=[
        quiz('Ordenando do maior para o menor',
             'Qual função de comparação ordena jogadores do MAIOR para o MENOR número de pontos com sort?',
             [('(a, b) => a.pontos - b.pontos', False),
              ('(a, b) => b.pontos - a.pontos', True),
              ('(a, b) => a.pontos > b.pontos', False),
              ('(a, b) => a.nome - b.nome', False)],
             dica='Se o resultado for positivo, b vem antes de a.'),

        codigo(COMPLETAR, 'Contando frequência',
               'Complete a função contarFrequencia(lista) para que ela devolva um objeto em que cada chave '
               'é um item da lista e o valor é quantas vezes ele aparece. '
               'contarFrequencia(["uva", "pera", "uva"]) devolve { uva: 2, pera: 1 }. Lista vazia devolve {}.',
               'function contarFrequencia(lista) {\n'
               '  const contagem = {};\n'
               '  for (const item of lista) {\n'
               '    contagem[item] = ___;\n'
               '  }\n'
               '  return contagem;\n'
               '}',
               'Some 1 ao valor atual da chave; se ela ainda não existir, comece de 0 usando ||.',
               'function contarFrequencia(lista) {\n'
               '  const contagem = {};\n'
               '  for (const item of lista) {\n'
               '    contagem[item] = (contagem[item] || 0) + 1;\n'
               '  }\n'
               '  return contagem;\n'
               '}',
               [('Frutas repetidas',  'contarFrequencia(["uva", "pera", "uva"])', '{"uva":2,"pera":1}', True),
                ('Todos diferentes',  'contarFrequencia(["a", "b", "c"])',        '{"a":1,"b":1,"c":1}', True),
                ('Lista vazia',       'contarFrequencia([])',                     '{}',                  False),
                ('Um item repetido',  'contarFrequencia(["x", "x", "x"])',        '{"x":3}',             False)]),

        codigo(LIVRE, 'Total do carrinho',
               'Escreva uma função totalCarrinho(itens) que receba uma lista de objetos '
               '{ nome, preco, quantidade } e devolva o valor total da compra (preco x quantidade de cada '
               'item). Se o total for 100 ou mais, aplique 10% de desconto. Carrinho vazio devolve 0. '
               'Exemplo: [{ nome: "caderno", preco: 50, quantidade: 2 }, { nome: "caneta", preco: 10, '
               'quantidade: 1 }] soma 110 e, com desconto, devolve 99.',
               'function totalCarrinho(itens) {\n  // escreva seu código aqui\n}',
               'Primeiro some preco * quantidade de todos os itens (reduce ou for). Só depois verifique '
               'se o total chegou a 100 e, se sim, multiplique por 0.9.',
               'function totalCarrinho(itens) {\n'
               '  let total = 0;\n'
               '  for (const item of itens) {\n'
               '    total += item.preco * item.quantidade;\n'
               '  }\n'
               '  if (total >= 100) {\n'
               '    total = total * 0.9;\n'
               '  }\n'
               '  return total;\n'
               '}',
               [('Sem desconto',
                 'totalCarrinho([{ nome: "lápis", preco: 30, quantidade: 1 }, { nome: "borracha", preco: 20, quantidade: 2 }])',
                 '70', True),
                ('Com desconto',
                 'totalCarrinho([{ nome: "caderno", preco: 50, quantidade: 2 }, { nome: "caneta", preco: 10, quantidade: 1 }])',
                 '99', True),
                ('Exatamente 100 tem desconto',
                 'totalCarrinho([{ nome: "mochila", preco: 25, quantidade: 4 }])',
                 '90', False),
                ('Carrinho vazio', 'totalCarrinho([])', '0', False),
                ('Logo abaixo do limite',
                 'totalCarrinho([{ nome: "livro", preco: 99, quantidade: 1 }])',
                 '99', False)],
               dificuldade='medio'),

        codigo(CORRIGIR, 'Ranking de jogadores',
               'A função ranking(jogadores) recebe uma lista de objetos { nome, pontos } e deveria devolver '
               'um array só com os nomes, do jogador com MAIS pontos para o com MENOS. '
               'Lista vazia devolve []. Mas o ranking está saindo de cabeça para baixo. Corrija.',
               'function ranking(jogadores) {\n'
               '  const ordenados = [...jogadores].sort((a, b) => a.pontos - b.pontos);\n'
               '  return ordenados.map((j) => j.nome);\n'
               '}',
               'Para ordem decrescente, a subtração na função de comparação precisa começar pelo b.',
               'function ranking(jogadores) {\n'
               '  const ordenados = [...jogadores].sort((a, b) => b.pontos - a.pontos);\n'
               '  return ordenados.map((j) => j.nome);\n'
               '}',
               [('Três jogadores',
                 'ranking([{ nome: "Ana", pontos: 50 }, { nome: "Bia", pontos: 80 }, { nome: "Caio", pontos: 30 }])',
                 '["Bia","Ana","Caio"]', True),
                ('Dois jogadores',
                 'ranking([{ nome: "Leo", pontos: 10 }, { nome: "Mia", pontos: 90 }])',
                 '["Mia","Leo"]', True),
                ('Lista vazia', 'ranking([])', '[]', False),
                ('Com pontuação zero',
                 'ranking([{ nome: "Zé", pontos: 0 }, { nome: "Rui", pontos: 15 }, { nome: "Lia", pontos: 40 }])',
                 '["Lia","Rui","Zé"]', False)],
               dificuldade='medio'),
    ])


# =====================================================================
# PROVA
# =====================================================================

PROVA = dict(
    titulo='Prova: Desafios Práticos',
    descricao='Avaliação das estratégias para resolver desafios com texto, números, listas e dados. '
              'Acerte 70% para avançar.',
    questoes=[
        (0, 'Questão 1', 'Qual expressão devolve o texto "devfox" invertido ("xofved")?',
         [('"devfox".reverse()', False),
          ('"devfox".split("").reverse().join("")', True),
          ('"devfox".split(" ").join("")', False),
          ('"devfox".toLowerCase()', False)]),
        (1, 'Questão 2', 'Se n vale 472, qual é o valor de n % 10 e de Math.floor(n / 10)?',
         [('2 e 47', True), ('47 e 2', False), ('4 e 72', False), ('2 e 47.2', False)]),
        (1, 'Questão 3', 'O MDC (máximo divisor comum) de 12 e 18 é:',
         [('2', False), ('3', False), ('6', True), ('36', False)]),
        (2, 'Questão 4', 'Qual código devolve a intersecção de a = [1, 2, 3] e b = [2, 3, 4], ou seja, [2, 3]?',
         [('a.filter((x) => b.includes(x))', True),
          ('a.filter((x) => !b.includes(x))', False),
          ('a.concat(b)', False),
          ('a.map((x) => b.includes(x))', False)]),
        (3, 'Questão 5', 'Você quer agrupar produtos por categoria num objeto grupos. Para cada produto p, '
                         'o que garante que o array da categoria exista antes do push?',
         [('grupos[p.categoria] = grupos[p.categoria] || [];', True),
          ('grupos.push(p.categoria);', False),
          ('grupos[p.categoria] = 0;', False),
          ('delete grupos[p.categoria];', False)]),
    ])


TRILHA = dict(titulo='Desafios Práticos', descricao='Resolva desafios práticos e aplique seus conhecimentos.',
              nivel='avancado', icone='desafios', linguagem='javascript',
              modulos=[M1, M2, M3, M4], prova=PROVA)
