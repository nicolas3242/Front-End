# -*- coding: utf-8 -*-
"""
tools/conteudo.py — o conteúdo pedagógico do DevFox, como dado.

Fica separado do SQL de propósito: aqui se escreve e se revisa o conteúdo,
e `gerar_banco.py` cuida de transformar isso em INSERTs. Quem for escrever
uma aula nova mexe só neste arquivo.

Convenções de casos de teste (precisam bater com assets/js/executor.worker.js):
  - `entrada` preenchida  → é avaliada como EXPRESSÃO depois do código do aluno
                            (ex.: "somar(10, 5)"), e o valor devolvido é a saída.
  - `entrada` None        → o código roda direto e a saída é o que ele imprimiu
                            com console.log.

⚠ A comparação no servidor (api/processar_desafio.php) é case-insensitive e
  colapsa espaços. Por isso NÃO existe exercício aqui cuja única diferença de
  resultado seja maiúscula/minúscula — ele passaria mesmo errado.

⚠ Todo exercício de código tem pelo menos UM caso oculto (visivel=False). É o
  que impede o aluno de passar chumbando a resposta visível.
"""

from conteudo_base import QUIZ, COMPLETAR, CORRIGIR, LIVRE, XP, quiz, codigo  # noqa: F401


# =====================================================================
# TRILHA 1 — LÓGICA DE PROGRAMAÇÃO
# =====================================================================

T1_M1 = dict(
    titulo='Variáveis e Tipos de Dados',
    descricao='O que são variáveis e como guardar informação na memória.',
    teoria=(
        'Uma variável é um espaço na memória com um nome, usado para guardar um valor. '
        'Em JavaScript declaramos variáveis com "let", quando o valor pode mudar ao longo '
        'do programa, ou com "const", quando ele é fixo e não pode ser reatribuído.\n\n'
        'Os tipos mais comuns são: number para números (10, 3.5), string para textos '
        '("DevFox", sempre entre aspas) e boolean para verdadeiro ou falso (true, false).\n\n'
        'Escolher entre let e const não é detalhe: const avisa a quem lê o código que aquele '
        'valor não muda, e o próprio JavaScript acusa erro se alguém tentar mudá-lo.'
    ),
    atividades=[
        quiz('Declarando uma variável',
             'Qual palavra-chave do JavaScript declara uma variável cujo valor PODE mudar depois?',
             [('const', False), ('let', True), ('fix', False), ('var!', False)],
             dica='Pense em qual delas você usaria para um placar que aumenta durante o jogo.'),

        codigo(COMPLETAR, 'Guardando um nome',
               'Complete o código para que a variável nome guarde o texto "DevFox" e seja exibida no console.',
               'let nome = ___;\nconsole.log(nome);',
               'Textos em JavaScript ficam entre aspas.',
               'let nome = "DevFox";\nconsole.log(nome);',
               [('Deve exibir o nome da plataforma', None, 'DevFox', True)]),

        codigo(LIVRE, 'Montando uma mensagem',
               'Escreva uma função criarMensagem(nome) que devolva o texto "Olá, " seguido do nome e de um "!". '
               'Por exemplo, criarMensagem("Ana") deve devolver "Olá, Ana!".',
               'function criarMensagem(nome) {\n  // escreva seu código aqui\n}',
               'Você pode juntar textos com o operador +. Repare que há um espaço depois da vírgula.',
               'function criarMensagem(nome) {\n  return "Olá, " + nome + "!";\n}',
               [('Nome comum',      'criarMensagem("Ana")',    'Olá, Ana!',    True),
                ('Outro nome',      'criarMensagem("Murilo")', 'Olá, Murilo!', True),
                ('Nome de uma letra', 'criarMensagem("J")',    'Olá, J!',      False)]),

        codigo(CORRIGIR, 'Conserte a declaração',
               'A função abaixo deveria somar 1 ao valor recebido, mas quebra ao rodar. '
               'Corrija a declaração da variável.',
               'function somarUm(valor) {\n  const total = valor;\n  total = total + 1;\n  return total;\n}',
               'O erro é "Assignment to constant variable". Qual palavra-chave permite mudar o valor depois?',
               'function somarUm(valor) {\n  let total = valor;\n  total = total + 1;\n  return total;\n}',
               [('Número positivo', 'somarUm(5)',  '6',  True),
                ('Zero',            'somarUm(0)',  '1',  True),
                ('Número negativo', 'somarUm(-3)', '-2', False)],
               dificuldade='medio'),
    ])

T1_M2 = dict(
    titulo='Operadores Aritméticos',
    descricao='Somar, subtrair, multiplicar e dividir valores.',
    teoria=(
        'Operadores aritméticos fazem cálculos com números: + (soma), - (subtração), '
        '* (multiplicação), / (divisão) e % (resto da divisão).\n\n'
        'O operador % costuma ser o menos intuitivo e o mais útil. Ele devolve o que sobra '
        'de uma divisão inteira: 7 % 2 é 1, porque 7 dividido por 2 dá 3 e sobra 1. '
        'É com ele que se descobre se um número é par — se o resto da divisão por 2 for 0, '
        'é par — ou se um número é múltiplo de outro.\n\n'
        'A ordem das operações é a mesma da matemática: multiplicação e divisão vêm antes '
        'de soma e subtração. Use parênteses quando quiser mudar essa ordem.'
    ),
    atividades=[
        quiz('O operador resto',
             'O que o operador % devolve?',
             [('O resultado da divisão', False), ('O resto da divisão', True),
              ('A porcentagem de um número', False), ('A raiz quadrada', False)],
             dica='Pense na divisão que você aprendeu na escola: quociente e o que sobra.'),

        codigo(LIVRE, 'Soma de Dois Números',
               'Escreva uma função somar(a, b) que devolva a soma dos dois números recebidos.',
               'function somar(a, b) {\n  // escreva seu código aqui\n}',
               'Use o operador + para somar os dois parâmetros e a palavra return para devolver o resultado.',
               'function somar(a, b) {\n  return a + b;\n}',
               [('Soma de dois positivos', 'somar(10, 5)',  '15', True),
                ('Soma com zero',          'somar(7, 0)',   '7',  True),
                ('Soma com negativo',      'somar(-4, 10)', '6',  False)]),

        codigo(LIVRE, 'Par ou Ímpar',
               'Escreva uma função ehPar(n) que devolva true quando n for par e false quando for ímpar.',
               'function ehPar(n) {\n  // escreva seu código aqui\n}',
               'Um número é par quando o resto da divisão dele por 2 é igual a zero.',
               'function ehPar(n) {\n  return n % 2 === 0;\n}',
               [('Número par',   'ehPar(4)', 'true',  True),
                ('Número ímpar', 'ehPar(7)', 'false', True),
                ('Zero é par',   'ehPar(0)', 'true',  False)]),

        codigo(COMPLETAR, 'Média de duas notas',
               'Complete a função para que ela devolva a média das duas notas recebidas.',
               'function media(a, b) {\n  return ___;\n}',
               'Some as duas notas primeiro e só depois divida por 2 — os parênteses importam.',
               'function media(a, b) {\n  return (a + b) / 2;\n}',
               [('Duas notas inteiras', 'media(8, 6)',  '7',   True),
                ('Média quebrada',      'media(10, 5)', '7.5', True),
                ('Duas notas zero',     'media(0, 0)',  '0',   False)]),
    ])

T1_M3 = dict(
    titulo='Condicionais',
    descricao='Fazer o programa tomar decisões com if e else.',
    teoria=(
        'Uma condicional permite que o programa siga caminhos diferentes conforme uma '
        'condição. O bloco do if executa quando a condição é verdadeira; o bloco do else, '
        'quando ela é falsa.\n\n'
        'As comparações usam === (igual), !== (diferente), > (maior), < (menor), '
        '>= (maior ou igual) e <= (menor ou igual).\n\n'
        'Atenção ao === com três sinais: ele compara valor E tipo. O == com dois sinais '
        'converte os tipos antes de comparar, o que produz surpresas como "5" == 5 sendo '
        'verdadeiro. Em JavaScript moderno, use sempre ===.\n\n'
        'O erro mais comum em condicionais é esquecer do limite: "maior de 18" e '
        '"18 ou mais" são condições diferentes, e quase sempre a segunda é a desejada.'
    ),
    atividades=[
        quiz('Incluindo o limite',
             'Para incluir quem tem exatamente 18 anos, qual operador usar em idade __ 18?',
             [('>', False), ('>=', True), ('==', False), ('<', False)],
             dica='Você precisa de um operador que aceite "maior" E também "igual".'),

        codigo(CORRIGIR, 'Conserte a condição',
               'A função abaixo deveria devolver "maior de idade" para quem tem 18 anos ou mais, '
               'mas está errada. Corrija a condição.',
               'function classificar(idade) {\n  if (idade > 18) {\n    return "maior de idade";\n  }\n  return "menor de idade";\n}',
               'Quem tem exatamente 18 anos também é maior de idade. Qual operador inclui o próprio 18?',
               'function classificar(idade) {\n  if (idade >= 18) {\n    return "maior de idade";\n  }\n  return "menor de idade";\n}',
               [('Exatamente 18 anos', 'classificar(18)', 'maior de idade', True),
                ('Mais de 18 anos',    'classificar(30)', 'maior de idade', True),
                ('Menos de 18 anos',   'classificar(15)', 'menor de idade', False)],
               dificuldade='medio'),

        codigo(LIVRE, 'O maior dos dois',
               'Escreva uma função maiorDeDois(a, b) que devolva o maior entre os dois números. '
               'Se forem iguais, devolva qualquer um deles.',
               'function maiorDeDois(a, b) {\n  // escreva seu código aqui\n}',
               'Compare a com b dentro de um if e devolva o vencedor.',
               'function maiorDeDois(a, b) {\n  if (a > b) {\n    return a;\n  }\n  return b;\n}',
               [('Segundo é maior', 'maiorDeDois(4, 9)',  '9',  True),
                ('Primeiro é maior', 'maiorDeDois(10, 2)', '10', True),
                ('Os dois iguais',  'maiorDeDois(5, 5)',  '5',  False)]),

        codigo(COMPLETAR, 'Aprovado ou reprovado',
               'Complete a condição para que alunos com nota 60 ou mais sejam aprovados.',
               'function situacao(nota) {\n  if (___) {\n    return "Aprovado";\n  }\n  return "Reprovado";\n}',
               'A nota 60 exata também aprova.',
               'function situacao(nota) {\n  if (nota >= 60) {\n    return "Aprovado";\n  }\n  return "Reprovado";\n}',
               [('Nota alta',      'situacao(75)', 'Aprovado',  True),
                ('Nota baixa',     'situacao(40)', 'Reprovado', True),
                ('Nota no limite', 'situacao(60)', 'Aprovado',  False)]),
    ])

T1_M4 = dict(
    titulo='Operadores Lógicos',
    descricao='Combinar condições com E, OU e NÃO.',
    teoria=(
        'Operadores lógicos combinam condições. São três:\n\n'
        '&& (E) devolve true apenas quando os DOIS lados são verdadeiros. '
        '"Tem 18 anos E tem carteira" só é verdade se as duas coisas forem verdade.\n\n'
        '|| (OU) devolve true quando PELO MENOS UM lado é verdadeiro. '
        '"É sábado OU é domingo" é verdade se qualquer um dos dois for.\n\n'
        '! (NÃO) inverte: !true vira false, e !false vira true.\n\n'
        'Trocar && por || é um dos erros mais difíceis de enxergar, porque o código '
        'continua rodando sem reclamar — só devolve a resposta errada. Na dúvida, '
        'leia a condição em voz alta em português e veja se faz sentido.'
    ),
    atividades=[
        quiz('Quando o E é verdadeiro',
             'O operador && devolve true em qual situação?',
             [('Quando pelo menos um dos lados é true', False),
              ('Quando os dois lados são true', True),
              ('Quando os dois lados são false', False),
              ('Sempre que o primeiro lado é true', False)],
             dica='Pense em "preciso de A E de B": falta um, já não dá.'),

        codigo(LIVRE, 'Pode votar?',
               'Escreva uma função podeVotar(idade, temTitulo) que devolva true somente quando a pessoa '
               'tiver 16 anos ou mais E possuir título de eleitor.',
               'function podeVotar(idade, temTitulo) {\n  // escreva seu código aqui\n}',
               'As duas condições precisam ser verdadeiras ao mesmo tempo.',
               'function podeVotar(idade, temTitulo) {\n  return idade >= 16 && temTitulo;\n}',
               [('Idade certa e com título', 'podeVotar(20, true)',  'true',  True),
                ('Jovem demais',             'podeVotar(14, true)',  'false', True),
                ('Idade certa, sem título',  'podeVotar(20, false)', 'false', False)]),

        codigo(COMPLETAR, 'É fim de semana?',
               'Complete a função para que ela devolva true quando o dia for "sabado" ou "domingo".',
               'function fimDeSemana(dia) {\n  return ___;\n}',
               'Basta um dos dois ser verdadeiro. Compare o dia com cada um dos nomes usando ===.',
               'function fimDeSemana(dia) {\n  return dia === "sabado" || dia === "domingo";\n}',
               [('Sábado',      'fimDeSemana("sabado")',  'true',  True),
                ('Dia de semana', 'fimDeSemana("terca")', 'false', True),
                ('Domingo',     'fimDeSemana("domingo")', 'true',  False)]),

        codigo(CORRIGIR, 'Conserte o desconto',
               'A regra é: tem desconto quem é estudante OU quem é idoso. A função abaixo só dá desconto '
               'a quem é as duas coisas. Corrija o operador.',
               'function temDesconto(ehEstudante, ehIdoso) {\n  return ehEstudante && ehIdoso;\n}',
               'Leia a regra em voz alta: "estudante OU idoso". Qual operador significa OU?',
               'function temDesconto(ehEstudante, ehIdoso) {\n  return ehEstudante || ehIdoso;\n}',
               [('Só estudante', 'temDesconto(true, false)',  'true',  True),
                ('Nenhum dos dois', 'temDesconto(false, false)', 'false', True),
                ('Só idoso',     'temDesconto(false, true)',  'true',  False)],
               dificuldade='medio'),
    ])

T1_PROVA = dict(
    titulo='Prova: Fundamentos de Lógica',
    descricao='Avaliação dos módulos de variáveis, operadores, condicionais e lógica. Acerte 70% para avançar.',
    questoes=[
        (0, 'Questão 1', 'Qual palavra-chave declara uma variável cujo valor NÃO pode mudar depois?',
         [('let', False), ('const', True), ('var', False), ('fixed', False)]),
        (0, 'Questão 2', 'Qual destes valores é do tipo string em JavaScript?',
         [('42', False), ('true', False), ('"DevFox"', True), ('[1, 2, 3]', False)]),
        (1, 'Questão 3', 'Qual é o resultado da expressão 7 % 2?',
         [('0', False), ('1', True), ('3', False), ('3.5', False)]),
        (2, 'Questão 4', 'O que o bloco else faz?',
         [('Executa quando a condição do if é falsa', True),
          ('Executa sempre, junto com o if', False),
          ('Repete o if até dar certo', False),
          ('Encerra o programa', False)]),
        (3, 'Questão 5', 'O que o operador ! faz com um valor booleano?',
         [('Soma 1 ao valor', False), ('Inverte o valor', True),
          ('Compara dois valores', False), ('Não faz nada', False)]),
    ])


# =====================================================================
# TRILHA 2 — JAVASCRIPT BÁSICO
# =====================================================================

T2_M1 = dict(
    titulo='Console, variáveis e valores',
    descricao='Comece exibindo mensagens e guardando informações.',
    teoria=(
        'O console.log() exibe valores no console do navegador. Use aspas para textos, como '
        'console.log("Olá!"). Variáveis guardam valores: let permite reatribuir um valor e const '
        'declara uma variável que não pode ser reatribuída. Os tipos básicos incluem number, string '
        'e boolean. Primeiro pratique declarar, atribuir e exibir valores antes de aprender funções.'
    ),
    atividades=[
        quiz('Exibindo uma mensagem', 'O que faz console.log("Olá")?',
             [('Exibe Olá no console', True), ('Cria uma página HTML', False), ('Declara uma função', False), ('Guarda um arquivo', False)]),
        codigo(COMPLETAR, 'Sua primeira variável', 'Complete para guardar o texto DevFox em nome e exibi-lo.',
               'let nome = ___;\nconsole.log(nome);', 'Textos precisam ficar entre aspas.',
               'let nome = "DevFox";\nconsole.log(nome);', [('Nome exibido', None, 'DevFox', True)]),
        codigo(COMPLETAR, 'Um valor constante', 'Use const para guardar o número 10 em pontos e exibi-lo.',
               '___ pontos = 10;\nconsole.log(pontos);', 'Use a palavra-chave para uma variável que não será reatribuída.',
               'const pontos = 10;\nconsole.log(pontos);', [('Pontos exibidos', None, '10', False)]),
    ])

T2_M2 = dict(
    titulo='Operadores e expressões',
    descricao='Faça cálculos simples com variáveis e exiba o resultado.',
    teoria=(
        'Use + para somar, - para subtrair, * para multiplicar e / para dividir. O operador % '
        'retorna o resto da divisão. As expressões podem usar variáveis: let total = preco * quantidade. '
        'Exiba o resultado com console.log(total).'
    ),
    atividades=[
        quiz('Operador de multiplicação', 'Qual operador multiplica dois números em JavaScript?',
             [('+', False), ('*', True), ('%', False), ('=', False)]),
        codigo(COMPLETAR, 'Somando dois valores', 'Complete para exibir a soma de 7 e 5.',
               'console.log(7 ___ 5);', 'Use o operador de adição.',
               'console.log(7 + 5);', [('Resultado da soma', None, '12', True)]),
        codigo(LIVRE, 'Calculando um total', 'Crie preco = 10 e quantidade = 3 usando let e exiba o resultado da multiplicação.',
               'let preco = 10;\nlet quantidade = 3;\n// exiba o total', 'Multiplique as variáveis dentro de console.log().',
               'let preco = 10;\nlet quantidade = 3;\nconsole.log(preco * quantidade);', [('Total calculado', None, '30', False)]),
    ])

T2_M3 = dict(
    titulo='Decisões com if e else',
    descricao='Execute instruções diferentes conforme uma condição.',
    teoria=(
        'O if executa um bloco quando a condição é verdadeira; else executa quando ela é falsa. '
        'Use === para comparar valores e >= para verificar se um valor é maior ou igual a outro. '
        'As chaves delimitam os blocos e console.log() exibe a mensagem escolhida.'
    ),
    atividades=[
        quiz('Condição verdadeira', 'Qual bloco roda quando a condição do if é falsa?',
             [('O bloco else', True), ('O bloco if', False), ('Uma função obrigatoriamente', False), ('Nenhum código pode rodar', False)]),
        codigo(COMPLETAR, 'Verificando a idade', 'Complete para exibir Maior de idade quando idade for 18 ou mais.',
               'let idade = 18;\nif (idade ___ 18) {\n  console.log("Maior de idade");\n} else {\n  console.log("Menor de idade");\n}', 'Use o operador que inclui o valor 18.',
               'let idade = 18;\nif (idade >= 18) {\n  console.log("Maior de idade");\n} else {\n  console.log("Menor de idade");\n}', [('Mensagem correta', None, 'Maior de idade', True)]),
        codigo(LIVRE, 'Aprovado ou reprovado', 'Crie nota = 7. Use if/else para exibir Aprovado se a nota for 7 ou mais; caso contrário, Reprovado.',
               'let nota = 7;\n// escreva a condição', 'Compare nota com 7 e use console.log() nos dois blocos.',
               'let nota = 7;\nif (nota >= 7) {\n  console.log("Aprovado");\n} else {\n  console.log("Reprovado");\n}', [('Nota aprovada', None, 'Aprovado', False)]),
    ])

T2_M4 = dict(
    titulo='Primeiras funções',
    descricao='Depois das variáveis, operadores e condicionais, aprenda a reutilizar código.',
    teoria=(
        'Uma função agrupa instruções para poderem ser reutilizadas. Ela é declarada com function, '
        'pode receber parâmetros e pode devolver um resultado com return. Use funções depois de '
        'entender variáveis, expressões e if/else.'
    ),
    atividades=[
        quiz('O que é uma função?', 'Para que serve uma função?',
             [('Agrupar código reutilizável', True), ('Exibir sempre uma mensagem', False), ('Declarar apenas números', False), ('Substituir todas as variáveis', False)]),
        codigo(COMPLETAR, 'Dobro de um número', 'Complete para a função dobro devolver o número multiplicado por 2.',
               'function dobro(n) {\n  return n ___ 2;\n}', 'Use o operador de multiplicação.',
               'function dobro(n) {\n  return n * 2;\n}', [('Dobro de 5', 'dobro(5)', '10', True)]),
        codigo(LIVRE, 'Somando com função', 'Escreva somar(a, b) para devolver a soma dos dois valores.',
               'function somar(a, b) {\n  // escreva seu código\n}', 'Use return a + b.',
               'function somar(a, b) {\n  return a + b;\n}', [('Soma de positivos', 'somar(2, 3)', '5', False)]),
    ])

T2_PROVA = dict(
    titulo='Prova: JavaScript Básico',
    descricao='Avaliação de console, variáveis, operadores, condicionais e funções. Acerte 70% para avançar.',
    questoes=[
        (0, 'Questão 1', 'Onde aparece o resultado de um console.log()?',
         [('Na própria página, como texto', False),
          ('No console do navegador', True),
          ('Num arquivo salvo no computador', False),
          ('Em lugar nenhum', False)]),
        (3, 'Questão 2', 'O que uma função sem return devolve?',
         [('undefined', True), ('0', False), ('null', False), ('Um erro', False)]),
        (1, 'Questão 3', 'Qual é o resultado de 7 % 2?',
         [('0', False), ('1', True), ('2', False), ('3.5', False)]),
        (2, 'Questão 4', 'Qual bloco executa quando if é falso?',
         [('if', False), ('else', True), ('function', False), ('return', False)]),
        (3, 'Questão 5', 'Qual palavra devolve um resultado de uma função?',
         [('console', False), ('return', True), ('const', False), ('if', False)]),
    ])


# =====================================================================
# TRILHA 3 — ESTRUTURAS DE REPETIÇÃO
# =====================================================================

T3_M1 = dict(
    titulo='O laço while',
    descricao='Repetir enquanto uma condição continuar verdadeira.',
    teoria=(
        'O while repete um bloco de código enquanto a condição dele for verdadeira. '
        'A condição é testada ANTES de cada volta: se já começar falsa, o bloco não roda '
        'nenhuma vez.\n\n'
        'Todo while precisa de três coisas para funcionar: uma variável de controle '
        'iniciada antes do laço, uma condição que dependa dela, e uma linha DENTRO do '
        'laço que mude essa variável.\n\n'
        'Esquecer a terceira é o erro clássico: a condição nunca vira falsa e o laço roda '
        'para sempre. No DevFox o código é interrompido depois de 3 segundos, mas num '
        'programa de verdade isso trava a página inteira.\n\n'
        'Use while quando você não sabe de antemão quantas voltas serão necessárias.'
    ),
    atividades=[
        quiz('Quando o laço não para',
             'O que acontece se a condição de um while nunca se tornar falsa?',
             [('O laço roda para sempre e trava o programa', True),
              ('O laço roda uma vez e para sozinho', False),
              ('O JavaScript corrige a condição automaticamente', False),
              ('O laço nunca chega a rodar', False)],
             dica='Pense no que falta: algo dentro do laço precisa mudar a condição.'),

        codigo(LIVRE, 'Somando até n',
               'Use while para somar os números de 1 até 5 e exiba o total com console.log(). Não use funções.',
               'let i = 1;\nlet total = 0;\n// complete o while\nconsole.log(total);',
               'Enquanto i for menor ou igual a 5, some i ao total e aumente i em 1.',
               'let i = 1;\nlet total = 0;\nwhile (i <= 5) {\n  total = total + i;\n  i = i + 1;\n}\nconsole.log(total);',
               [('Soma de 1 a 5', None, '15', True), ('Verificação oculta', None, '15', False)], dificuldade='medio'),

        codigo(COMPLETAR, 'A condição do laço',
               'Complete a condição para contar de 1 até 3 usando while. Exiba o total com console.log(). Não use funções.',
               'let i = 1;\nlet total = 0;\nwhile (___) {\n  total = total + 1;\n  i = i + 1;\n}\nconsole.log(total);',
               'O laço continua enquanto i for menor ou igual a 3.',
               'let i = 1;\nlet total = 0;\nwhile (i <= 3) {\n  total = total + 1;\n  i = i + 1;\n}\nconsole.log(total);',
               [('Contagem', None, '3', True), ('Verificação oculta', None, '3', False)]),

        codigo(CORRIGIR, 'O laço que nunca termina',
               'O while abaixo não termina. Corrija-o para somar de 4 até 1 e exiba o total com console.log(). Não use funções.',
               'let n = 4;\nlet total = 0;\nwhile (n > 0) {\n  total = total + n;\n  // falta atualizar n\n}\nconsole.log(total);',
               'A cada volta, diminua n em 1.',
               'let n = 4;\nlet total = 0;\nwhile (n > 0) {\n  total = total + n;\n  n = n - 1;\n}\nconsole.log(total);',
               [('Soma regressiva', None, '10', True), ('Verificação oculta', None, '10', False)], dificuldade='dificil'),
    ])

T3_M2 = dict(
    titulo='O laço for',
    descricao='Repetir um número conhecido de vezes.',
    teoria=(
        'O for junta as três partes do laço numa linha só: '
        'for (inicialização; condição; incremento).\n\n'
        'Em for (let i = 1; i <= 5; i++), o i começa em 1, o laço continua enquanto i for '
        'menor ou igual a 5, e a cada volta o i aumenta em 1. O i++ é uma forma curta de '
        'escrever i = i + 1.\n\n'
        'Como as três partes ficam juntas e visíveis, é muito mais difícil esquecer o '
        'incremento — por isso o for é preferido quando você JÁ SABE quantas voltas quer.\n\n'
        'O erro mais comum aqui é de limite: trocar < por <= muda o número de voltas em um. '
        'Esse engano tem até nome próprio na área: erro de um a mais, ou off-by-one.'
    ),
    atividades=[
        quiz('As partes do for',
             'Quais são as três partes que ficam entre os parênteses de um for?',
             [('Inicialização, condição e incremento', True),
              ('Início, meio e fim', False),
              ('Variável, função e retorno', False),
              ('Condição, bloco e return', False)],
             dica='São as mesmas três coisas que um while precisa, só que escritas juntas.'),

        codigo(LIVRE, 'Fatorial',
               'Use for para calcular 5! (5 × 4 × 3 × 2 × 1) e exiba o resultado com console.log(). Não use funções.',
               'let total = 1;\n// escreva o for aqui\nconsole.log(total);',
               'Comece total em 1 e multiplique pelos números de 2 até 5.',
               'let total = 1;\nfor (let i = 2; i <= 5; i++) {\n  total = total * i;\n}\nconsole.log(total);',
               [('Fatorial de 5', None, '120', True), ('Verificação oculta', None, '120', False)], dificuldade='medio'),

        codigo(COMPLETAR, 'Somando só os pares',
               'Complete a condição para somar os números pares de 1 até 10 e exiba o total com console.log(). Não use funções.',
               'let total = 0;\nfor (let i = 1; i <= 10; i++) {\n  if (___) {\n    total = total + i;\n  }\n}\nconsole.log(total);',
               'Use o resto da divisão por 2 para identificar os pares.',
               'let total = 0;\nfor (let i = 1; i <= 10; i++) {\n  if (i % 2 === 0) {\n    total = total + i;\n  }\n}\nconsole.log(total);',
               [('Soma dos pares', None, '30', True), ('Verificação oculta', None, '30', False)], dificuldade='medio'),

        codigo(CORRIGIR, 'Falta uma volta',
               'O for deveria somar de 1 até 5, mas deixa o último número de fora. Corrija e exiba o total com console.log(). Não use funções.',
               'let total = 0;\nfor (let i = 1; i < 5; i++) {\n  total = total + i;\n}\nconsole.log(total);',
               'Ajuste o limite para incluir o próprio 5.',
               'let total = 0;\nfor (let i = 1; i <= 5; i++) {\n  total = total + i;\n}\nconsole.log(total);',
               [('Soma de 1 a 5', None, '15', True), ('Verificação oculta', None, '15', False)], dificuldade='medio'),
    ])

T3_M3 = dict(
    titulo='Percorrendo arrays',
    descricao='Usar laços para visitar cada item de uma lista.',
    teoria=(
        'Juntar laço e array é o que torna as duas coisas realmente úteis. O padrão é '
        'sempre o mesmo: for (let i = 0; i < lista.length; i++), e dentro do laço você '
        'usa lista[i] para chegar ao item da vez.\n\n'
        'Preste atenção nos dois limites. O i começa em 0, porque é ali que o array '
        'começa. E a condição usa < lista.length, sem o igual: numa lista de 3 itens, '
        'os índices válidos são 0, 1 e 2 — o índice 3 não existe.\n\n'
        'Esse padrão resolve uma família inteira de problemas: somar todos os itens, '
        'contar quantos atendem a uma regra, achar o maior. Muda só o que acontece dentro.'
    ),
    atividades=[
        quiz('Até onde vai o laço',
             'Numa lista com 3 itens, até qual índice o laço deve ir?',
             [('Até 3', False), ('Até 2', True), ('Até 4', False), ('Até 1', False)],
             dica='O índice do último item é sempre length - 1.'),

        codigo(LIVRE, 'Somando a lista',
               'Percorra o array [1, 2, 3] com for, some seus itens e exiba o total com console.log(). Não use funções.',
               'let lista = [1, 2, 3];\nlet total = 0;\n// percorra o array\nconsole.log(total);',
               'Use i começando em 0 e continue enquanto i < lista.length. Some lista[i].',
               'let lista = [1, 2, 3];\nlet total = 0;\nfor (let i = 0; i < lista.length; i++) {\n  total = total + lista[i];\n}\nconsole.log(total);',
               [('Soma da lista', None, '6', True), ('Verificação oculta', None, '6', False)], dificuldade='medio'),

        codigo(COMPLETAR, 'O maior da lista',
               'Complete a condição para encontrar o maior número de [3, 9, 4] e exiba-o com console.log(). Não use funções.',
               'let lista = [3, 9, 4];\nlet maiorValor = lista[0];\nfor (let i = 1; i < lista.length; i++) {\n  if (___) {\n    maiorValor = lista[i];\n  }\n}\nconsole.log(maiorValor);',
               'Compare o item atual com maiorValor.',
               'let lista = [3, 9, 4];\nlet maiorValor = lista[0];\nfor (let i = 1; i < lista.length; i++) {\n  if (lista[i] > maiorValor) {\n    maiorValor = lista[i];\n  }\n}\nconsole.log(maiorValor);',
               [('Maior número', None, '9', True), ('Verificação oculta', None, '9', False)], dificuldade='medio'),

        codigo(LIVRE, 'Contando os pares',
               'Conte quantos números pares existem em [1, 2, 3, 4] e exiba a quantidade com console.log(). Não use funções.',
               'let lista = [1, 2, 3, 4];\nlet total = 0;\n// percorra e conte os pares\nconsole.log(total);',
               'Percorra a lista com for e use if com % 2 === 0.',
               'let lista = [1, 2, 3, 4];\nlet total = 0;\nfor (let i = 0; i < lista.length; i++) {\n  if (lista[i] % 2 === 0) {\n    total = total + 1;\n  }\n}\nconsole.log(total);',
               [('Contagem de pares', None, '2', True), ('Verificação oculta', None, '2', False)], dificuldade='medio'),
    ])

T3_M4 = dict(
    titulo='Laços aninhados',
    descricao='Um laço dentro do outro, para percorrer duas dimensões.',
    teoria=(
        'Um laço aninhado é um for dentro de outro for. A cada UMA volta do laço de fora, '
        'o laço de dentro roda INTEIRO. Por isso o total de execuções é a multiplicação '
        'dos dois: 3 voltas por fora e 4 por dentro dão 12 execuções.\n\n'
        'É o que se usa para percorrer uma matriz — um array cujos itens são outros arrays, '
        'como [[1, 2], [3, 4]]. O laço de fora escolhe a linha, o de dentro percorre as '
        'colunas daquela linha: matriz[i][j].\n\n'
        'Duas armadilhas. A primeira é usar o mesmo nome de variável nos dois laços, ou '
        'escrever matriz[i][i] em vez de matriz[i][j] — o código roda e entrega resultado '
        'errado, sem avisar. A segunda é o custo: laços aninhados crescem rápido, e com '
        'listas grandes isso pesa de verdade.'
    ),
    atividades=[
        quiz('Quantas voltas no total',
             'Um laço de 3 voltas contém outro de 4 voltas. Quantas vezes o bloco de dentro executa?',
             [('7', False), ('12', True), ('4', False), ('3', False)],
             dica='O laço de dentro roda inteiro a cada volta do de fora.'),

        codigo(LIVRE, 'Contando os passos',
               'Use dois for aninhados: 3 voltas no laço externo e 4 no interno. Conte as execuções e exiba o total com console.log(). Não use funções.',
               'let total = 0;\n// escreva os dois laços\nconsole.log(total);',
               'O laço interno deve executar 4 vezes para cada uma das 3 voltas externas.',
               'let total = 0;\nfor (let i = 0; i < 3; i++) {\n  for (let j = 0; j < 4; j++) {\n    total = total + 1;\n  }\n}\nconsole.log(total);',
               [('Contagem de passos', None, '12', True), ('Verificação oculta', None, '12', False)], dificuldade='medio'),

        codigo(COMPLETAR, 'Somando a matriz',
               'Complete o limite do laço interno para somar todos os números de [[1, 2], [3, 4]] e exiba o total com console.log(). Não use funções.',
               'let matriz = [[1, 2], [3, 4]];\nlet total = 0;\nfor (let i = 0; i < matriz.length; i++) {\n  for (let j = 0; j < ___; j++) {\n    total = total + matriz[i][j];\n  }\n}\nconsole.log(total);',
               'O limite é o tamanho da linha atual: matriz[i].length.',
               'let matriz = [[1, 2], [3, 4]];\nlet total = 0;\nfor (let i = 0; i < matriz.length; i++) {\n  for (let j = 0; j < matriz[i].length; j++) {\n    total = total + matriz[i][j];\n  }\n}\nconsole.log(total);',
               [('Soma da matriz', None, '10', True), ('Verificação oculta', None, '10', False)], dificuldade='dificil'),

        codigo(CORRIGIR, 'O índice trocado',
               'O código deve somar todos os valores da matriz [[1, 2], [3, 5]], mas usa o índice errado. Corrija e exiba o total com console.log(). Não use funções.',
               'let matriz = [[1, 2], [3, 5]];\nlet total = 0;\nfor (let i = 0; i < matriz.length; i++) {\n  for (let j = 0; j < matriz[i].length; j++) {\n    total = total + matriz[i][i];\n  }\n}\nconsole.log(total);',
               'Dentro do laço interno, use j para selecionar a coluna atual.',
               'let matriz = [[1, 2], [3, 5]];\nlet total = 0;\nfor (let i = 0; i < matriz.length; i++) {\n  for (let j = 0; j < matriz[i].length; j++) {\n    total = total + matriz[i][j];\n  }\n}\nconsole.log(total);',
               [('Soma correta', None, '11', True), ('Verificação oculta', None, '11', False)], dificuldade='dificil'),
    ])

T3_PROVA = dict(
    titulo='Prova: Estruturas de Repetição',
    descricao='Avaliação dos módulos de while, for, arrays e laços aninhados. Acerte 70% para avançar.',
    questoes=[
        (0, 'Questão 1', 'O que falta num while que roda para sempre?',
         [('Uma linha dentro do laço que mude a variável da condição', True),
          ('Um return antes do laço', False),
          ('Mais uma condição no if', False),
          ('Um ponto e vírgula no fim', False)]),
        (1, 'Questão 2', 'Quantas voltas dá o laço for (let i = 1; i <= 5; i++)?',
         [('4', False), ('5', True), ('6', False), ('1', False)]),
        (2, 'Questão 3', 'Qual é a condição correta para percorrer um array inteiro?',
         [('i <= lista.length', False), ('i < lista.length', True),
          ('i < lista.length - 2', False), ('i > 0', False)]),
        (2, 'Questão 4', 'Em que valor o índice deve começar ao percorrer um array?',
         [('0', True), ('1', False), ('-1', False), ('lista.length', False)]),
        (3, 'Questão 5', 'Numa matriz, o que matriz[i][j] representa?',
         [('O item da linha i e coluna j', True),
          ('A soma da linha i com a coluna j', False),
          ('O tamanho da linha i', False),
          ('A última linha da matriz', False)]),
    ])


from conteudo_logica_python import TRILHA as T_LOGICA  # noqa: E402
from conteudo_html import TRILHA as T_HTML          # noqa: E402
from conteudo_css import TRILHA as T_CSS            # noqa: E402
from conteudo_funcoes import TRILHA as T_FUNCOES    # noqa: E402
from conteudo_desafios import TRILHA as T_DESAFIOS  # noqa: E402
from conteudo_projeto import TRILHA as T_PROJETO    # noqa: E402

# A POSIÇÃO nesta lista define os ids (trilha, módulo, atividade) — as três
# primeiras ficam onde sempre estiveram para os ids não mudarem. A ORDEM DE
# EXIBIÇÃO é o campo `ordem`, que segue o wireframe.
TRILHAS = [
    dict(T_LOGICA, ordem=1),

    dict(T_HTML, ordem=2, destaque=True, subtitulo='Estrutura e Conteúdo',
         resumo='Aprenda a construir a estrutura de páginas web com HTML.'),
    dict(T_CSS, ordem=3, destaque=True, subtitulo='Estilo e Apresentação',
         resumo='Estilize suas páginas e crie layouts modernos com CSS.'),

    # JavaScript reúne o conteúdo básico, laços de repetição e funções/métodos
    # em uma única trilha progressiva, mantendo os módulos e atividades originais.
    dict(titulo='JavaScript Básico',
         descricao='Aprenda JavaScript do básico aos laços de repetição, funções e métodos.',
         nivel='iniciante', icone='js', ordem=4,
         modulos=[T2_M1, T2_M2, T2_M3, T2_M4,
                  T3_M1, T3_M2, T3_M3, T3_M4,
                  *T_FUNCOES['modulos']],
         prova=dict(
             titulo='Prova: JavaScript',
             descricao='Avaliação de fundamentos, estruturas de repetição, funções e métodos. Acerte 70% para avançar.',
             questoes=[
                 *T2_PROVA['questoes'],
                 *((ref + 4, titulo, enunciado, alternativas)
                   for ref, titulo, enunciado, alternativas in T3_PROVA['questoes']),
                 *((ref + 8, titulo, enunciado, alternativas)
                   for ref, titulo, enunciado, alternativas in T_FUNCOES['prova']['questoes']),
             ])),

    dict(T_DESAFIOS, ordem=5),
    # O Projeto Final só abre depois de TODAS as outras trilhas concluídas.
    dict(T_PROJETO, ordem=6, requisitos=['Lógica de Programação', 'HTML', 'CSS',
                                         'JavaScript Básico', 'Desafios Práticos']),
]

CONQUISTAS = [
    ('primeira_aula',    'Código Iniciante',  'Complete sua primeira aula.',             'estrela', 'bronze'),
    ('primeiro_desafio', 'Primeiro Desafio',  'Conclua seu primeiro desafio de código.', 'trofeu',  'prata'),
    ('sequencia_7',      'Sequência de 7',    'Mantenha 7 dias seguidos de estudo.',     'fogo',    'especial'),
    ('explorador',       'Explorador',        'Inicie 3 trilhas diferentes.',            'mapa',    'ouro'),
    ('trilha_completa',  'Trilha Concluída',  'Conclua uma trilha inteira.',             'medalha', 'ouro'),
    ('sem_dica',         'Por Conta Própria', 'Acerte 5 desafios sem usar dica.',        'lampada', 'prata'),
]


# ---------------------------------------------------------------------------
# Loja (v5) — só itens cosméticos.
#
# Moeda = XP DIV 10 (ver includes/loja.php). As trilhas somam 7.200 XP, ou seja,
# 720 moedas para quem fizer TUDO. O catálogo soma 1.100 de propósito: ninguém
# compra tudo, e escolher o que comprar é parte da graça.
#
# (slug, nome, descrição, categoria, preço). O slug de moldura vira classe CSS.
# ---------------------------------------------------------------------------
ITENS_LOJA = [
    ('brasa',     'Moldura Brasa',     'Anel laranja, a cor da raposa.',                 'moldura',  60),
    ('oceano',    'Moldura Oceano',    'Anel azul, calmo como um código sem bug.',       'moldura',  60),
    ('floresta',  'Moldura Floresta',  'Anel verde, para quem vive em "testes passaram".', 'moldura',  80),
    ('neon',      'Moldura Neon',      'Anel roxo que brilha na barra lateral.',         'moldura', 150),
    ('ouro',      'Moldura Ouro',      'Anel dourado. Para quem chegou longe.',          'moldura', 250),
    ('cacador-de-bugs',  'Caçador de Bugs',  'Para quem lê a mensagem de erro antes de pedir ajuda.',          'titulo',   80),
    ('raposa-noturna',   'Raposa Noturna',   'Para quem resolve desafio depois da meia-noite.',          'titulo',  100),
    ('mestre-do-loop',   'Mestre do Loop',   'Para quem já não tem medo de laço infinito.',          'titulo',  120),
    ('lenda-do-console', 'Lenda do Console', 'Para quem depura tudo com console.log — e acerta.',          'titulo',  200),
]
