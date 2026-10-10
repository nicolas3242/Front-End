# -*- coding: utf-8 -*-
"""Trilha inicial de lógica em Python, executada no navegador pelo Pyodide."""
from conteudo_base import QUIZ, COMPLETAR, CORRIGIR, LIVRE, quiz, codigo

M1 = dict(
    titulo='Primeiros passos com Python',
    descricao='Use print() para mostrar mensagens e resultados.',
    teoria=('Python é uma linguagem de programação com uma escrita simples. A função print() mostra '
            'uma mensagem ou valor na saída do programa. Textos ficam entre aspas; números podem ser '
            'escritos diretamente. Cada linha é executada em sequência, de cima para baixo.'),
    atividades=[
        quiz('Para que serve print()?', 'O que o comando print("Olá") faz?',
             [('Mostra Olá na saída do programa', True), ('Cria uma variável', False), ('Repete o programa', False), ('Fecha o programa', False)]),
        codigo(COMPLETAR, 'Minha primeira mensagem', 'Complete para exibir a mensagem DevFox.',
               '___("DevFox")', 'Use o comando que mostra valores na saída.',
               'print("DevFox")', [('Mensagem exibida', None, 'DevFox', True)]),
        codigo(LIVRE, 'Duas mensagens', 'Exiba a palavra Python e, na linha seguinte, a palavra DevFox.',
               '# escreva seu código aqui', 'Use um print() para cada linha.',
               'print("Python")\nprint("DevFox")', [('Duas linhas', None, 'Python\nDevFox', True)]),
    ])

M2 = dict(
    titulo='Variáveis e tipos de dados',
    descricao='Guarde textos, números e valores verdadeiros ou falsos.',
    teoria=('Variáveis guardam valores e recebem nomes. Em Python, basta escrever nome = valor; '
            'não se usa let ou const. Textos são strings (str), números inteiros são int e números '
            'decimais são float. True e False são valores booleanos (bool). Use print(nome) para exibir '
            'o valor guardado.'),
    atividades=[
        quiz('Criando uma variável', 'Como guardar o número 10 na variável idade em Python?',
             [('idade == 10', False), ('idade = 10', True), ('let idade = 10', False), ('const idade: 10', False)]),
        codigo(COMPLETAR, 'Guardando uma idade', 'Complete para guardar 18 em idade e exibir esse valor.',
               'idade = ___\nprint(idade)', 'O valor é um número, então não precisa de aspas.',
               'idade = 18\nprint(idade)', [('Idade exibida', None, '18', True)]),
        codigo(LIVRE, 'Apresente-se', 'Crie uma variável nome com seu nome (use Ana como exemplo) e exiba-a com print().',
               'nome = "Ana"\n# exiba a variável', 'Passe o nome da variável para print(), sem aspas.',
               'nome = "Ana"\nprint(nome)', [('Nome exibido', None, 'Ana', True)]),
    ])

M3 = dict(
    titulo='Operadores e comparações',
    descricao='Faça contas e compare valores antes de tomar decisões.',
    teoria=('Python usa +, -, *, / e % para operações aritméticas. Comparações como ==, !=, >, <, >= '
            'e <= produzem True ou False. Atenção: = atribui um valor a uma variável; == compara dois valores.'),
    atividades=[
        quiz('Comparando valores', 'Qual operador verifica se dois valores são iguais?',
             [('=', False), ('==', True), ('!=', False), ('>=', False)]),
        codigo(COMPLETAR, 'Somando valores', 'Complete para exibir a soma de 7 e 5.',
               'print(7 ___ 5)', 'Use o operador de adição.', 'print(7 + 5)', [('Resultado da soma', None, '12', True)]),
        codigo(LIVRE, 'Maior de idade?', 'Crie idade = 18 e exiba o resultado de idade >= 18.',
               'idade = 18\n# mostre a comparação', 'Use print() com a comparação.',
               'idade = 18\nprint(idade >= 18)', [('Comparação verdadeira', None, 'True', True)]),
    ])

M4 = dict(
    titulo='Decisões com if, else e elif',
    descricao='Faça o programa escolher o que executar conforme uma condição.',
    teoria=('O if executa um bloco quando a condição é verdadeira. O else executa quando ela é falsa. '
            'O elif permite testar outra condição caso as anteriores não sejam verdadeiras. Em Python, '
            'os dois-pontos : iniciam o bloco e a indentação (espaços no início da linha) define quais '
            'instruções pertencem a ele.'),
    atividades=[
        quiz('Quando usar else?', 'Quando o bloco else é executado?',
             [('Quando a condição do if é falsa', True), ('Sempre antes do if', False), ('Só quando há uma função', False), ('Quando o programa inicia', False)]),
        codigo(COMPLETAR, 'Verificando a idade', 'Complete para exibir Maior de idade quando idade for 18 ou mais; caso contrário, Menor de idade.',
               'idade = 18\nif idade >= 18:\n    print("Maior de idade")\n___:\n    print("Menor de idade")', 'A segunda alternativa usa a palavra-chave que acompanha if.',
               'idade = 18\nif idade >= 18:\n    print("Maior de idade")\nelse:\n    print("Menor de idade")', [('Maior de idade', None, 'Maior de idade', True)]),
        codigo(LIVRE, 'Classificando uma nota', 'Crie nota = 7. Se nota for 7 ou mais, exiba Aprovado; caso seja menor, exiba Reprovado.',
               'nota = 7\n# escreva a condição', 'Use if/else e mantenha o print indentado dentro de cada bloco.',
               'nota = 7\nif nota >= 7:\n    print("Aprovado")\nelse:\n    print("Reprovado")', [('Nota aprovada', None, 'Aprovado', True), ('Verificação adicional', None, 'Aprovado', False)]),
    ])

PROVA = dict(
    titulo='Prova: Fundamentos de Lógica',
    descricao='Revise print, variáveis, operadores e condicionais em Python.',
    questoes=[
        (0, 'Questão 1', 'Qual comando exibe um valor em Python?', [('print()', True), ('console.log()', False), ('echo()', False), ('show()', False)]),
        (1, 'Questão 2', 'Como se atribui o valor 5 à variável pontos?', [('pontos == 5', False), ('pontos = 5', True), ('let pontos = 5', False), ('const pontos = 5', False)]),
        (2, 'Questão 3', 'Qual é o resultado de 7 % 2 em Python?', [('0', False), ('1', True), ('3', False), ('3.5', False)]),
        (3, 'Questão 4', 'Para que serve elif?', [('Testar outra condição', True), ('Declarar uma variável', False), ('Exibir texto', False), ('Encerrar o programa', False)]),
        (3, 'Questão 5', 'O que define os blocos de código em Python?', [('Chaves {}', False), ('Indentação', True), ('Ponto e vírgula', False), ('Parênteses', False)]),
    ])

TRILHA = dict(titulo='Lógica de Programação', descricao='Aprenda lógica com Python, do primeiro print às decisões com if, else e elif.', nivel='iniciante', icone='logica', ordem=1, linguagem='python', modulos=[M1, M2, M3, M4], prova=PROVA)
