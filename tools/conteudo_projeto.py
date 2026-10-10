# -*- coding: utf-8 -*-
"""tools/conteudo_projeto.py — trilha Projeto Final (JavaScript)."""
from conteudo_base import QUIZ, COMPLETAR, CORRIGIR, LIVRE, quiz, codigo  # noqa: F401


# ---------------------------------------------------------------------
# Peças já resolvidas do projeto. Cada passo é corrigido isoladamente, então
# o código inicial de um passo traz tudo o que foi construído antes dele.
# ---------------------------------------------------------------------

F_CRIAR = (
    'function criarTarefa(titulo, prioridade) {\n'
    '  return { titulo: titulo, prioridade: prioridade, concluida: false };\n'
    '}'
)

F_ADICIONAR = (
    'function adicionarTarefa(lista, tarefa) {\n'
    '  lista.push(tarefa);\n'
    '  return lista.length;\n'
    '}'
)

F_CONCLUIR = (
    'function concluirTarefa(lista, titulo) {\n'
    '  const tarefa = lista.find(t => t.titulo === titulo);\n'
    '  if (tarefa === undefined) {\n'
    '    return false;\n'
    '  }\n'
    '  tarefa.concluida = true;\n'
    '  return true;\n'
    '}'
)

F_PENDENTES = (
    'function pendentes(lista) {\n'
    '  return lista.filter(t => !t.concluida).map(t => t.titulo);\n'
    '}'
)

F_CONTAR = (
    'function contarPorPrioridade(lista) {\n'
    '  const contagem = {};\n'
    '  for (const tarefa of lista) {\n'
    '    if (contagem[tarefa.prioridade] === undefined) {\n'
    '      contagem[tarefa.prioridade] = 0;\n'
    '    }\n'
    '    contagem[tarefa.prioridade] += 1;\n'
    '  }\n'
    '  return contagem;\n'
    '}'
)

F_CONTAR_BUG = (
    'function contarPorPrioridade(lista) {\n'
    '  const contagem = {};\n'
    '  for (const tarefa of lista) {\n'
    '    if (contagem[tarefa.prioridade] === undefined) {\n'
    '      contagem[tarefa.prioridade] = 0;\n'
    '    }\n'
    '    contagem[tarefa.prioridade] = 1;\n'
    '  }\n'
    '  return contagem;\n'
    '}'
)

F_RESUMO = (
    'function resumo(lista) {\n'
    '  const total = lista.length;\n'
    '  const feitas = lista.filter(t => t.concluida).length;\n'
    '  const faltam = total - feitas;\n'
    '  const tTarefa = total === 1 ? "tarefa" : "tarefas";\n'
    '  const tConcluida = feitas === 1 ? "concluída" : "concluídas";\n'
    '  const tPendente = faltam === 1 ? "pendente" : "pendentes";\n'
    '  return total + " " + tTarefa + ", " + feitas + " " + tConcluida + ", " + faltam + " " + tPendente;\n'
    '}'
)


def juntar(*partes):
    return '\n\n'.join(partes)


def stub(assinatura):
    return 'function ' + assinatura + ' {\n  // escreva seu código aqui\n}'


M1 = dict(
    titulo='Projeto: Gerenciador de Tarefas',
    descricao='Construa, passo a passo, um pequeno gerenciador de tarefas com arrays, objetos e funções.',
    teoria=(
        'Neste projeto você vai juntar tudo o que aprendeu para construir um programa completo: um '
        'gerenciador de tarefas, parecido com uma lista de afazeres. Ele guarda tarefas, marca as que '
        'foram feitas, mostra o que ainda falta e gera um resumo. Não é preciso nada novo: variáveis, '
        'condicionais, laços, funções, arrays e objetos bastam.\n\n'
        'O coração do projeto é o modelo de dados. Cada tarefa é um objeto com três propriedades, sempre '
        'nesta ordem: titulo (texto, como "Estudar"), prioridade (texto: "alta" ou "baixa") e concluida '
        '(boolean, que começa como false). A lista de tarefas é um array desses objetos, por exemplo: '
        '[{ titulo: "Estudar", prioridade: "alta", concluida: false }].\n\n'
        'O plano é dividir o problema em funções pequenas, cada uma com uma única responsabilidade: '
        'criarTarefa monta o objeto, adicionarTarefa coloca na lista, concluirTarefa marca como feita, '
        'pendentes lista o que falta, contarPorPrioridade agrupa as tarefas e resumo descreve a lista em '
        'uma frase. Cada passo já traz pronto o código dos passos anteriores; você escreve só a parte nova.'
    ),
    atividades=[
        quiz('O modelo de dados',
             'No gerenciador, qual destes valores representa corretamente UMA tarefa nova, que ainda não foi feita?',
             [('{ titulo: "Estudar", prioridade: "alta", concluida: false }', True),
              ('["Estudar", "alta", false]', False),
              ('{ titulo: "Estudar", prioridade: "alta", concluida: "não" }', False),
              ('"Estudar - alta - pendente"', False)],
             dica='Cada tarefa é um objeto com titulo, prioridade e concluida, e concluida é um boolean.'),

        codigo(COMPLETAR, 'Passo 1: criar uma tarefa',
               'Complete a função criarTarefa(titulo, prioridade) para que ela devolva um objeto no formato '
               '{ titulo, prioridade, concluida }, nesta ordem. Toda tarefa nova começa ainda não concluída. '
               'Por exemplo, criarTarefa("Estudar", "alta") deve devolver '
               '{"titulo":"Estudar","prioridade":"alta","concluida":false}.',
               'function criarTarefa(titulo, prioridade) {\n'
               '  return { titulo: titulo, prioridade: prioridade, concluida: ___ };\n'
               '}',
               'Uma tarefa nova ainda não foi feita. Qual valor boolean representa "não"?',
               F_CRIAR,
               [('Tarefa de prioridade alta', 'criarTarefa("Estudar", "alta")',
                 '{"titulo":"Estudar","prioridade":"alta","concluida":false}', True),
                ('Tarefa de prioridade baixa', 'criarTarefa("Ler", "baixa")',
                 '{"titulo":"Ler","prioridade":"baixa","concluida":false}', False)]),

        codigo(LIVRE, 'Passo 2: adicionar à lista',
               'A função criarTarefa já está pronta. Escreva agora adicionarTarefa(lista, tarefa), que coloca '
               'a tarefa no FINAL do array lista (alterando o próprio array) e devolve a nova quantidade de '
               'tarefas da lista. Por exemplo, adicionarTarefa([], criarTarefa("Estudar", "alta")) devolve 1. '
               'Se a lista já tiver 2 tarefas, deve devolver 3.',
               juntar(F_CRIAR, stub('adicionarTarefa(lista, tarefa)')),
               'O método push coloca um item no final do array. Depois, a propriedade length diz quantos itens ele tem.',
               juntar(F_CRIAR, F_ADICIONAR),
               [('Lista vazia', 'adicionarTarefa([], criarTarefa("Estudar", "alta"))', '1', True),
                ('A tarefa entra no final da lista',
                 '(() => { const l = [criarTarefa("Estudar", "alta")]; adicionarTarefa(l, criarTarefa("Ler", "baixa")); '
                 'return l.map(t => t.titulo); })()',
                 '["Estudar","Ler"]', True),
                ('Lista com duas tarefas',
                 'adicionarTarefa([criarTarefa("A", "alta"), criarTarefa("B", "baixa")], criarTarefa("C", "alta"))',
                 '3', False)]),

        codigo(LIVRE, 'Passo 3: concluir uma tarefa',
               'Escreva concluirTarefa(lista, titulo). Ela procura na lista a tarefa com esse titulo; se '
               'encontrar, muda concluida para true e devolve true. Se nenhuma tarefa tiver esse titulo '
               '(inclusive quando a lista está vazia), não altera nada e devolve false.',
               juntar(F_CRIAR, F_ADICIONAR, stub('concluirTarefa(lista, titulo)')),
               'O método find devolve o primeiro objeto que satisfaz a condição, ou undefined se não achar nenhum. '
               'Como ele devolve o próprio objeto, alterar tarefa.concluida altera a tarefa dentro da lista.',
               juntar(F_CRIAR, F_ADICIONAR, F_CONCLUIR),
               [('Tarefa existente', 'concluirTarefa([criarTarefa("Estudar", "alta")], "Estudar")', 'true', True),
                ('A tarefa fica marcada como concluída',
                 '(() => { const l = [criarTarefa("Estudar", "alta"), criarTarefa("Ler", "baixa")]; '
                 'concluirTarefa(l, "Ler"); return l.map(t => t.concluida); })()',
                 '[false,true]', True),
                ('Título que não existe', 'concluirTarefa([criarTarefa("Estudar", "alta")], "Correr")', 'false', False),
                ('Lista vazia', 'concluirTarefa([], "Estudar")', 'false', False)],
               dificuldade='medio'),

        codigo(LIVRE, 'Passo 4: o que ainda falta',
               'Escreva pendentes(lista), que devolve um array com os TÍTULOS das tarefas ainda não concluídas, '
               'na mesma ordem em que aparecem na lista. Se todas estiverem concluídas, ou se a lista estiver '
               'vazia, devolva um array vazio []. Por exemplo, para Estudar (pendente) e Ler (concluída), '
               'deve devolver ["Estudar"].',
               juntar(F_CRIAR, F_ADICIONAR, F_CONCLUIR, stub('pendentes(lista)')),
               'Use filter para ficar só com as tarefas em que concluida é false e, depois, map para pegar só o titulo.',
               juntar(F_CRIAR, F_ADICIONAR, F_CONCLUIR, F_PENDENTES),
               [('Uma pendente e uma concluída',
                 'pendentes([criarTarefa("Estudar", "alta"), {titulo: "Ler", prioridade: "baixa", concluida: true}])',
                 '["Estudar"]', True),
                ('Todas pendentes',
                 'pendentes([criarTarefa("A", "alta"), criarTarefa("B", "baixa"), criarTarefa("C", "alta")])',
                 '["A","B","C"]', True),
                ('Todas concluídas',
                 'pendentes([{titulo: "Ler", prioridade: "baixa", concluida: true}, '
                 '{titulo: "Correr", prioridade: "alta", concluida: true}])',
                 '[]', False),
                ('Lista vazia', 'pendentes([])', '[]', False)],
               dificuldade='medio'),

        codigo(CORRIGIR, 'Passo 5: conserte a contagem',
               'A função contarPorPrioridade(lista) deveria devolver um objeto dizendo quantas tarefas há de '
               'cada prioridade, com as chaves na ordem em que cada prioridade aparece pela primeira vez. '
               'Por exemplo, para as prioridades "alta", "baixa", "baixa" o resultado deve ser '
               '{"alta":1,"baixa":2}. Para uma lista vazia, {}. Mas ela está contando errado. Encontre e corrija o erro.',
               juntar(F_CRIAR, F_ADICIONAR, F_CONCLUIR, F_PENDENTES, F_CONTAR_BUG),
               'Teste com duas tarefas de mesma prioridade: o valor da chave deveria aumentar, mas é sobrescrito.',
               juntar(F_CRIAR, F_ADICIONAR, F_CONCLUIR, F_PENDENTES, F_CONTAR),
               [('Prioridades misturadas',
                 'contarPorPrioridade([criarTarefa("A", "alta"), criarTarefa("B", "baixa"), criarTarefa("C", "baixa")])',
                 '{"alta":1,"baixa":2}', True),
                ('Três tarefas de prioridade alta',
                 'contarPorPrioridade([criarTarefa("A", "alta"), criarTarefa("B", "alta"), criarTarefa("C", "alta")])',
                 '{"alta":3}', False),
                ('Lista vazia', 'contarPorPrioridade([])', '{}', False)],
               dificuldade='medio'),

        codigo(LIVRE, 'Passo 6: o resumo da lista',
               'Escreva resumo(lista), que devolve uma frase no formato '
               '"<total> tarefas, <concluídas> concluídas, <pendentes> pendentes". '
               'Cada palavra fica no singular quando o número dela é exatamente 1 ("1 tarefa", "1 concluída", '
               '"1 pendente") e no plural em qualquer outro caso, inclusive 0. Exemplos: '
               '"3 tarefas, 1 concluída, 2 pendentes" e, para a lista vazia, "0 tarefas, 0 concluídas, 0 pendentes".',
               juntar(F_CRIAR, F_ADICIONAR, F_CONCLUIR, F_PENDENTES, F_CONTAR, stub('resumo(lista)')),
               'Conte as concluídas com filter(...).length; as pendentes são o total menos as concluídas. '
               'Para cada número, escolha a palavra com um if ou com o operador ternário (n === 1 ? "..." : "...").',
               juntar(F_CRIAR, F_ADICIONAR, F_CONCLUIR, F_PENDENTES, F_CONTAR, F_RESUMO),
               [('Três tarefas, uma concluída',
                 'resumo([criarTarefa("A", "alta"), {titulo: "B", prioridade: "baixa", concluida: true}, '
                 'criarTarefa("C", "baixa")])',
                 '3 tarefas, 1 concluída, 2 pendentes', True),
                ('Duas tarefas concluídas',
                 'resumo([{titulo: "A", prioridade: "alta", concluida: true}, '
                 '{titulo: "B", prioridade: "baixa", concluida: true}])',
                 '2 tarefas, 2 concluídas, 0 pendentes', True),
                ('Lista vazia', 'resumo([])', '0 tarefas, 0 concluídas, 0 pendentes', False),
                ('Uma única tarefa pendente', 'resumo([criarTarefa("A", "alta")])',
                 '1 tarefa, 0 concluídas, 1 pendente', False)],
               dificuldade='dificil'),
    ])


PROVA = dict(
    titulo='Avaliação do Projeto',
    descricao='Questões sobre as decisões do projeto. Acerte 70% para concluir.',
    questoes=[
        (0, 'Questão 1', 'Por que a propriedade concluida de cada tarefa é um boolean (true/false) e não um texto?',
         [('Porque ela só tem dois estados possíveis e pode ser usada direto em condições e filtros', True),
          ('Porque objetos em JavaScript não aceitam propriedades de texto', False),
          ('Porque booleans ocupam mais espaço e deixam o programa mais seguro', False),
          ('Porque o método push só funciona com booleans', False)]),
        (0, 'Questão 2', 'Qual método de array é o mais adequado para localizar UMA tarefa pelo título, como em concluirTarefa?',
         [('map', False), ('find', True), ('reduce', False), ('push', False)]),
        (0, 'Questão 3', 'Na função pendentes, por que usamos filter e depois map?',
         [('filter ordena as tarefas e map remove as concluídas', False),
          ('filter fica só com as tarefas não concluídas e map transforma cada uma no seu título', True),
          ('filter soma as tarefas e map conta quantas são', False),
          ('Os dois fazem a mesma coisa; usar ambos é só por estilo', False)]),
        (0, 'Questão 4', 'Em contarPorPrioridade, o que acontece se a linha que atualiza a contagem usar "= 1" em vez de "+= 1"?',
         [('O programa dá erro e para de rodar', False),
          ('Cada prioridade aparece com no máximo 1, mesmo tendo várias tarefas', True),
          ('A contagem passa a ser o dobro do valor correto', False),
          ('Nada muda, porque as duas formas são equivalentes', False)]),
        (0, 'Questão 5', 'Qual a principal vantagem de dividir o gerenciador em várias funções pequenas?',
         [('O programa fica mais rápido porque tem mais funções', False),
          ('Cada função tem uma responsabilidade clara e pode ser testada e corrigida separadamente', True),
          ('Funções pequenas dispensam o uso de arrays e objetos', False),
          ('Assim não é preciso usar return em nenhuma delas', False)]),
    ])


TRILHA = dict(titulo='Projeto Final', descricao='Desenvolva um projeto completo aplicando tudo que aprendeu.',
              nivel='avancado', icone='projeto', linguagem='javascript',
              modulos=[M1], prova=PROVA)
