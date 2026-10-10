# -*- coding: utf-8 -*-
"""
tools/gerar_banco.py — monta database/banco.sql a partir de tools/conteudo.py.

O SCHEMA não é gerado: ele é copiado tal e qual do banco.sql atual, até o
cabeçalho "DADOS INICIAIS". Só a seção de dados é reescrita. Assim, mexer no
conteúdo nunca altera a estrutura das tabelas por acidente.

Uso:  python3 tools/gerar_banco.py
"""

import io
import re
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from conteudo import TRILHAS, CONQUISTAS, ITENS_LOJA, XP, QUIZ  # noqa: E402

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ALVO = os.path.join(RAIZ, 'database', 'banco.sql')

MARCA = '-- =====================================================================\n-- DADOS INICIAIS'


def esc(valor):
    """Escapa um valor para string SQL. None vira NULL."""
    if valor is None:
        return 'NULL'
    texto = str(valor)
    texto = texto.replace('\\', '\\\\').replace("'", "\\'")
    texto = texto.replace('\r\n', '\n').replace('\n', '\\n')
    return "'" + texto + "'"


def bool_sql(valor):
    return '1' if valor else '0'


def numerar():
    """Atribui ids fixos a trilhas, módulos e atividades.

    Ids explícitos (e não AUTO_INCREMENT) porque as provas precisam apontar
    para módulos, e os testes automatizados referenciam atividades por id.
    """
    prox_modulo = 1
    prox_atividade = 1

    for i, trilha in enumerate(TRILHAS, start=1):
        trilha['id'] = i
        trilha.setdefault('ordem', i)          # ordem de exibição; o id segue a posição

        for j, modulo in enumerate(trilha['modulos'], start=1):
            modulo['id'] = prox_modulo
            # Na trilha JavaScript, mantém os IDs fixos, mas exibe
            # "Primeiras funções" depois dos módulos de repetição.
            if trilha['titulo'] == 'JavaScript Básico':
                if modulo['titulo'] == 'Primeiras funções':
                    modulo['ordem'] = 9
                elif 5 <= j <= 8:
                    modulo['ordem'] = j - 1
                elif j >= 9:
                    modulo['ordem'] = j + 1
                else:
                    modulo['ordem'] = j
            else:
                modulo['ordem'] = j
            prox_modulo += 1

            for k, atividade in enumerate(modulo['atividades'], start=1):
                atividade['id'] = prox_atividade
                atividade['ordem'] = k
                prox_atividade += 1

        prova = trilha['prova']
        prova['id'] = prox_modulo
        prova['ordem'] = len(trilha['modulos']) + 1
        prox_modulo += 1

        prova['questoes_num'] = []
        for k, (ref_indice, titulo, enunciado, alternativas) in enumerate(prova['questoes'], start=1):
            prova['questoes_num'].append(dict(
                id=prox_atividade,
                ordem=k,
                ref=trilha['modulos'][ref_indice]['id'],
                titulo=titulo,
                enunciado=enunciado,
                alternativas=alternativas,
                dificuldade='facil' if k <= 3 else 'medio',
            ))
            prox_atividade += 1

        prova['modulo_id'] = prova['id']


def gerar():
    numerar()
    L = []
    add = L.append

    add(MARCA)
    add('-- =====================================================================')
    add('-- Gerado por tools/gerar_banco.py a partir de tools/conteudo.py.')
    add('-- Para mudar o conteúdo, edite conteudo.py e rode o gerador de novo —')
    add('-- editar este trecho na mão faz o próximo gerador apagar sua alteração.')
    add('-- =====================================================================')
    add('')

    # ---------------- contas de teste ----------------
    add('-- ---------------------------------------------------------------------')
    add('-- Contas de teste')
    add('-- ---------------------------------------------------------------------')
    add('-- ⚠ SOMENTE PARA DESENVOLVIMENTO LOCAL. Troque as senhas (ou apague estas')
    add('--   contas) antes de qualquer publicação. As senhas em texto puro são:')
    add('--     admin@devfox.local  → devfox123')
    add('--     aluno@devfox.local  → aluno123')
    add('--')
    add('-- Contas de admin são criadas AQUI ou direto no banco, nunca pela tela de')
    add('-- cadastro: o cadastro público cria apenas alunos.')
    add('-- ---------------------------------------------------------------------')
    add('INSERT INTO usuarios (nome, email, senha, tipo) VALUES')
    add("('Admin DevFox',   'admin@devfox.local', "
        "'$2y$12$JnEgiuwPMktGOYX2NztOZ./xu6gB/jjgGVr3E9UgL6xPlicFiNuOS', 'admin'),")
    add("('Aluno de Teste', 'aluno@devfox.local', "
        "'$2y$12$GLZdNa.8q3c1y1mS12H8UOghFtgWr3oBUsFY9Tpn7/u2gbJqyJ4Ti', 'aluno');")
    add('')
    add('')

    # ---------------- trilhas ----------------
    add('-- ---------------------------------------------------------------------')
    add('-- Trilhas')
    add('-- ---------------------------------------------------------------------')
    add('INSERT INTO trilhas (id, titulo, descricao, nivel_dificuldade, icone, ordem, publicada, '
        'linguagem, subtitulo, destaque) VALUES')
    linhas = []
    for t in TRILHAS:
        linhas.append('({id}, {tit}, {desc}, {niv}, {ic}, {ord}, 1, {ling}, {sub}, {dest})'.format(
            id=t['id'], tit=esc(t['titulo']), desc=esc(t['descricao']),
            niv=esc(t['nivel']), ic=esc(t['icone']), ord=t['ordem'],
            ling=esc(t.get('linguagem', 'javascript')), sub=esc(t.get('subtitulo')),
            dest=bool_sql(t.get('destaque'))))
    add(',\n'.join(linhas) + ';')
    add('')

    # requisitos entre trilhas (v6), referenciados pelo título
    por_titulo = {t['titulo']: t['id'] for t in TRILHAS}
    pares = [(t['id'], por_titulo[r]) for t in TRILHAS for r in t.get('requisitos', [])]
    if pares:
        add('-- Trilhas que só abrem depois de outras concluídas')
        add('INSERT INTO trilha_requisitos (trilha_id, requisito_id) VALUES')
        add(',\n'.join('({}, {})'.format(a, b) for a, b in pares) + ';')
    add('')
    add('')

    for t in TRILHAS:
        add('-- =====================================================================')
        add('-- TRILHA {}: {}'.format(t['id'], t['titulo']))
        add('-- =====================================================================')
        add('')

        # módulos de aula
        add('INSERT INTO modulos (id, trilha_id, titulo, descricao, teoria_texto, ordem) VALUES')
        linhas = []
        for m in t['modulos']:
            linhas.append('({id}, {tid}, {tit},\n    {desc},\n    {teo},\n    {ord})'.format(
                id=m['id'], tid=t['id'], tit=esc(m['titulo']),
                desc=esc(m['descricao']), teo=esc(m['teoria']), ord=m['ordem']))
        add(',\n'.join(linhas) + ';')
        add('')

        # atividades de cada módulo
        for m in t['modulos']:
            add('-- Módulo {}: {}'.format(m['id'], m['titulo']))
            for a in m['atividades']:
                if a['tipo'] == QUIZ:
                    add('INSERT INTO atividades '
                        '(id, modulo_id, titulo, enunciado, tipo, dificuldade, dica, xp_recompensa, ordem) VALUES')
                    add('({id}, {mid}, {tit},\n    {enun},\n    {tipo}, {dif}, {dica}, {xp}, {ord});'.format(
                        id=a['id'], mid=m['id'], tit=esc(a['titulo']), enun=esc(a['enunciado']),
                        tipo=esc(a['tipo']), dif=esc(a['dificuldade']), dica=esc(a.get('dica')),
                        xp=XP[a['tipo']], ord=a['ordem']))
                    add('INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES')
                    linhas = ['({}, {}, {}, {})'.format(a['id'], esc(txt), bool_sql(ok), i)
                              for i, (txt, ok) in enumerate(a['alternativas'], start=1)]
                    add(',\n'.join(linhas) + ';')
                else:
                    add('INSERT INTO atividades '
                        '(id, modulo_id, titulo, enunciado, tipo, dificuldade, codigo_inicial, dica, '
                        'solucao_exemplo, xp_recompensa, ordem) VALUES')
                    add('({id}, {mid}, {tit},\n    {enun},\n    {tipo}, {dif},\n    {ini},\n    {dica},\n'
                        '    {sol},\n    {xp}, {ord});'.format(
                            id=a['id'], mid=m['id'], tit=esc(a['titulo']), enun=esc(a['enunciado']),
                            tipo=esc(a['tipo']), dif=esc(a['dificuldade']),
                            ini=esc(a['codigo_inicial']), dica=esc(a['dica']),
                            sol=esc(a['solucao_exemplo']), xp=XP[a['tipo']], ord=a['ordem']))
                    add('INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem) VALUES')
                    linhas = ['({}, {}, {}, {}, {}, {})'.format(
                        a['id'], esc(d), esc(e), esc(s), bool_sql(v), i)
                        for i, (d, e, s, v) in enumerate(a['casos'], start=1)]
                    add(',\n'.join(linhas) + ';')
                add('')
            add('')

        # prova
        p = t['prova']
        add('-- ---------------------------------------------------------------------')
        add('-- Prova de fim de nível da trilha {}'.format(t['id']))
        add('-- ---------------------------------------------------------------------')
        add('-- Cada questão aponta, por `modulo_referencia_id`, o módulo que ela avalia —')
        add('-- é assim que o sistema diz ao aluno reprovado O QUE revisar, em vez de')
        add('-- mandá-lo refazer tudo.')
        add('-- ---------------------------------------------------------------------')
        add('INSERT INTO modulos (id, trilha_id, titulo, tipo, nota_minima, descricao, ordem) VALUES')
        add("({id}, {tid}, {tit}, 'prova', 70,\n    {desc},\n    {ord});".format(
            id=p['id'], tid=t['id'], tit=esc(p['titulo']), desc=esc(p['descricao']), ord=p['ordem']))
        add('')
        add('INSERT INTO atividades '
            '(id, modulo_id, modulo_referencia_id, titulo, enunciado, tipo, dificuldade, xp_recompensa, ordem) VALUES')
        linhas = []
        for q in p['questoes_num']:
            linhas.append("({id}, {mid}, {ref}, {tit}, {enun}, 'quiz', {dif}, 50, {ord})".format(
                id=q['id'], mid=p['id'], ref=q['ref'], tit=esc(q['titulo']),
                enun=esc(q['enunciado']), dif=esc(q['dificuldade']), ord=q['ordem']))
        add(',\n'.join(linhas) + ';')
        add('')
        add('INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES')
        linhas = []
        for q in p['questoes_num']:
            for i, (txt, ok) in enumerate(q['alternativas'], start=1):
                linhas.append('({}, {}, {}, {})'.format(q['id'], esc(txt), bool_sql(ok), i))
        add(',\n'.join(linhas) + ';')
        add('')
        add('')

    # ---------------- conquistas ----------------
    add('-- ---------------------------------------------------------------------')
    add('-- Catálogo de conquistas')
    add('-- ---------------------------------------------------------------------')
    add('-- A regra de cada conquista está em includes/conquistas.php, pelo código.')
    add('-- ---------------------------------------------------------------------')
    add('INSERT INTO conquistas (codigo, titulo, descricao, icone, raridade) VALUES')
    linhas = ['({}, {}, {}, {}, {})'.format(esc(c), esc(t_), esc(d), esc(i), esc(r))
              for c, t_, d, i, r in CONQUISTAS]
    add(',\n'.join(linhas) + ';')
    add('')
    add('')

    # ---------------- loja ----------------
    add('-- ---------------------------------------------------------------------')
    add('-- Catálogo da Loja (v5) — só itens cosméticos')
    add('-- ---------------------------------------------------------------------')
    add('INSERT INTO itens_loja (slug, nome, descricao, categoria, preco, ordem) VALUES')
    linhas = []
    for ordem, (slug, nome, desc, cat, preco) in enumerate(ITENS_LOJA, 1):
        if not re.fullmatch(r'[a-z0-9-]+', slug):
            raise SystemExit('Slug inválido na loja: ' + slug)
        linhas.append('({}, {}, {}, {}, {}, {})'.format(
            esc(slug), esc(nome), esc(desc), esc(cat), int(preco), ordem))
    add(',\n'.join(linhas) + ';')
    add('')

    return '\n'.join(L)


def main():
    atual = io.open(ALVO, encoding='utf-8').read()
    if MARCA not in atual:
        raise SystemExit('Marcador "DADOS INICIAIS" não encontrado em ' + ALVO)

    schema = atual.split(MARCA)[0]
    novo = schema + gerar()

    # As questões complementares pertencem à migração versionada v11 -> v12.
    # Ao gerar o banco do zero, reutiliza essa mesma fonte para manter os dois
    # caminhos consistentes sem precisar de um SQL de atualização separado.
    migracao_path = os.path.join(RAIZ, 'database', 'migracao_v11_para_v12.sql')
    if os.path.exists(migracao_path):
        complemento = io.open(migracao_path, encoding='utf-8').read()
        complemento = re.sub(r'^.*?USE\s+devfox\s*;\s*', '', complemento, count=1, flags=re.I | re.S)
        novo += '\n\n' + complemento.strip() + '\n'

    io.open(ALVO, 'w', encoding='utf-8', newline='\n').write(novo)

    modulos = sum(len(t['modulos']) + 1 for t in TRILHAS)
    aulas = sum(len(m['atividades']) for t in TRILHAS for m in t['modulos'])
    questoes = sum(len(t['prova']['questoes']) for t in TRILHAS)
    casos = sum(len(a['casos']) for t in TRILHAS for m in t['modulos']
                for a in m['atividades'] if a['tipo'] != QUIZ)

    print('banco.sql gerado')
    print('  trilhas .............. {}'.format(len(TRILHAS)))
    print('  modulos .............. {} ({} aulas + {} provas)'.format(
        modulos, modulos - len(TRILHAS), len(TRILHAS)))
    print('  atividades de aula ... {}'.format(aulas))
    print('  questoes de prova .... {}'.format(questoes))
    print('  casos de teste ....... {}'.format(casos))
    print('  total de atividades .. {}'.format(aulas + questoes))


if __name__ == '__main__':
    main()
