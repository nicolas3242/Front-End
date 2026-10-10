# -*- coding: utf-8 -*-
"""
tools/checar_trilha.py — confere UMA trilha antes de ela entrar no banco.

    python3 tools/checar_trilha.py conteudo_html      (nome do módulo, sem .py)

O arquivo precisa definir TRILHA = dict(titulo, descricao, nivel, icone,
linguagem, modulos=[...], prova=dict(...)), no formato de conteudo.py.

Confere as mesmas regras do validador do banco (validar_conteudo.js), sem
precisar importar nada:
  - solução de referência passa em todos os casos (JS no Node, HTML/CSS no
    Chromium via tools/validar_html.js);
  - código inicial NÃO passa em todos;
  - exercício com entradas diferentes tem caso oculto e caso visível, e o
    oculto não repete a entrada de um visível;
  - quiz com exatamente 1 alternativa correta, >= 3 alternativas, sem repetir;
  - questão de prova aponta um módulo que existe (índice 0..n-1);
  - campos obrigatórios preenchidos.
"""

import importlib
import json
import os
import subprocess
import sys
import tempfile

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, AQUI)

from conteudo_base import QUIZ  # noqa: E402

problemas = []


def erro(msg):
    problemas.append(msg)
    print('  ✘ ' + msg)


def normalizar(v):
    return ' '.join(str('' if v is None else v).split()).lower()


def rodar_js(codigo, entrada):
    try:
        saida = subprocess.run(
            ['node', os.path.join(AQUI, 'rodar_isolado.js'), json.dumps({'codigo': codigo, 'entrada': entrada})],
            capture_output=True, text=True, timeout=3)
        return json.loads(saida.stdout)
    except Exception:  # noqa: BLE001 — travou (laço infinito) ou não executou
        return {'saida': None, 'erro': 'travou ou nao executou'}


def main():
    if len(sys.argv) < 2:
        sys.exit('uso: python3 tools/checar_trilha.py <modulo>')

    trilha = importlib.import_module(sys.argv[1].replace('.py', '')).TRILHA
    ling = trilha.get('linguagem', 'javascript')
    print('TRILHA: {} ({})'.format(trilha['titulo'], ling))

    for campo in ('titulo', 'descricao', 'nivel', 'icone', 'modulos', 'prova'):
        if not trilha.get(campo):
            erro('trilha sem o campo ' + campo)

    html = []
    total = 0
    for mi, m in enumerate(trilha['modulos']):
        for campo in ('titulo', 'descricao', 'teoria', 'atividades'):
            if not m.get(campo):
                erro('modulo {} sem {}'.format(mi, campo))
        for a in m['atividades']:
            total += 1
            rot = '"{}" (modulo {})'.format(a['titulo'], mi)
            if a['tipo'] == QUIZ:
                certas = sum(1 for _, ok in a['alternativas'] if ok)
                if certas != 1:
                    erro(rot + ': {} alternativas corretas'.format(certas))
                if len(a['alternativas']) < 3:
                    erro(rot + ': menos de 3 alternativas')
                textos = [normalizar(t) for t, _ in a['alternativas']]
                if len(set(textos)) != len(textos):
                    erro(rot + ': alternativas repetidas')
                continue

            for campo in ('enunciado', 'codigo_inicial', 'dica', 'solucao_exemplo', 'casos'):
                if not a.get(campo):
                    erro(rot + ': sem ' + campo)

            casos = a['casos']
            entradas = {normalizar(e) for _, e, _, _ in casos}
            if len(entradas) == 1:
                if len(casos) != 1:
                    erro(rot + ': todos os casos com a mesma entrada')
            else:
                vis = [e for _, e, _, v in casos if v]
                ocu = [e for _, e, _, v in casos if not v]
                if not ocu:
                    erro(rot + ': nenhum caso oculto')
                if not vis:
                    erro(rot + ': nenhum caso visivel')
                for o in ocu:
                    if normalizar(o) in {normalizar(v) for v in vis}:
                        erro(rot + ': caso oculto repete entrada visivel')

            if ling == 'html':
                html.append(dict(id=total, titulo=a['titulo'], solucao_exemplo=a['solucao_exemplo'],
                                 codigo_inicial=a['codigo_inicial'],
                                 casos=[dict(entrada=e, saida_esperada=s) for _, e, s, _ in casos]))
                continue

            for i, (_, e, s, _) in enumerate(casos, 1):
                r = rodar_js(a['solucao_exemplo'], e)
                if r.get('erro') or normalizar(r.get('saida')) != normalizar(s):
                    erro('{} caso {} [{}]: esperado {!r}, obtido {!r} {}'.format(
                        rot, i, e or 'console.log', s, r.get('saida'), r.get('erro') or ''))
            ok_todos = True
            for _, e, s, _ in casos:
                r = rodar_js(a['codigo_inicial'], e)
                if r.get('erro') or normalizar(r.get('saida')) != normalizar(s):
                    ok_todos = False
                    break
            if ok_todos:
                erro(rot + ': codigo inicial JA passa em todos os casos')

    n = len(trilha['modulos'])
    for q in trilha['prova']['questoes']:
        ref, titulo, _, alts = q
        if not (0 <= ref < n):
            erro('prova "{}": modulo de referencia {} inexistente'.format(titulo, ref))
        if sum(1 for _, ok in alts if ok) != 1:
            erro('prova "{}": precisa de exatamente 1 alternativa correta'.format(titulo))

    if html:
        with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False, encoding='utf-8') as f:
            json.dump(html, f, ensure_ascii=False)
        r = subprocess.run(['node', os.path.join(AQUI, 'validar_html.js'), f.name])
        if r.returncode != 0:
            problemas.append('html')

    print('\n{} atividades. {}'.format(
        total, 'TUDO CERTO' if not problemas else '{} PROBLEMA(S)'.format(len(problemas))))
    sys.exit(1 if problemas else 0)


if __name__ == '__main__':
    main()
