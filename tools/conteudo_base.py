# -*- coding: utf-8 -*-
"""
tools/conteudo_base.py — o "vocabulário" com que o conteúdo é escrito.

Fica separado de conteudo.py para que cada trilha possa morar no seu próprio
arquivo (conteudo_html.py, conteudo_css.py...) usando as mesmas funções.

Casos de teste — o significado de `entrada` depende da linguagem da trilha:

  JavaScript (executor.worker.js)
    - entrada preenchida → avaliada como EXPRESSÃO depois do código do aluno
                           (ex.: "somar(10, 5)"); o valor devolvido é a saída.
    - entrada None       → o código roda direto; a saída é o que ele imprimiu.

  HTML e CSS (executor-html.js) — a entrada é sempre uma VERIFICAÇÃO:
      existe("sel")            → "sim" / "não"
      contar("sel")            → "3"
      texto("sel")             → texto do 1º elemento (espaços colapsados)
      atributo("sel", "attr")  → valor do atributo ("(sem atributo)" se faltar)
      estilo("sel", "prop")    → valor CALCULADO: "rgb(255, 107, 0)", "24px"...
    Elemento inexistente devolve "(nenhum elemento)".
"""

QUIZ, COMPLETAR, CORRIGIR, LIVRE = 'quiz', 'completar', 'corrigir_erro', 'codigo_livre'

XP = {QUIZ: 100, COMPLETAR: 100, CORRIGIR: 200, LIVRE: 150}


def quiz(titulo, enunciado, alternativas, dificuldade='facil', dica=None):
    """alternativas: lista de (texto, correta)."""
    return dict(tipo=QUIZ, titulo=titulo, enunciado=enunciado, dificuldade=dificuldade,
                dica=dica, alternativas=alternativas)


def codigo(tipo, titulo, enunciado, inicial, dica, solucao, casos, dificuldade='facil'):
    """casos: lista de (descricao, entrada, saida_esperada, visivel)."""
    return dict(tipo=tipo, titulo=titulo, enunciado=enunciado, dificuldade=dificuldade,
                codigo_inicial=inicial, dica=dica, solucao_exemplo=solucao, casos=casos)
