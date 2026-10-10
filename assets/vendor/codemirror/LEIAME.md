# CodeMirror 5.65.16 — cópia local

Biblioteca do editor de código usado na tela de desafio. Licença MIT (ver `LICENSE`).

## Por que está dentro do projeto, e não num CDN

O backlog previa carregar por CDN. Foi trocado de propósito: numa apresentação de
TCC, depender de rede é risco desnecessário. Se o Wi-Fi da sala falhar ou o CDN
estiver fora, o editor simplesmente não carrega — na frente da banca. Aqui, o
projeto funciona offline.

Custo: ~195 KB no repositório. Vale a troca.

## Arquivos

| Arquivo | O que é |
|---|---|
| `codemirror.min.js` / `codemirror.css` | o editor |
| `modo-javascript.min.js` | destaque de sintaxe JavaScript |
| `matchbrackets.min.js` | destaca o par de chaves/parênteses |
| `closebrackets.min.js` | fecha chaves e aspas automaticamente |

Minificados com terser a partir do pacote npm `codemirror@5.65.16`.

## Por que a versão 5 e não a 6

A 6 exige empacotador (bundler). Este projeto é PHP puro, sem etapa de build —
a 5 carrega com uma tag `<script>` e pronto.

## O tema

Não veio de pacote: está em `assets/css/styles.css`, na seção `.CodeMirror`, com
as cores do DevFox.
