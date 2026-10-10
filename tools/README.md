# tools/ — pipeline de conteúdo

O conteúdo das trilhas **não é escrito direto no SQL**. Ele mora em
`conteudo.py`, e o `banco.sql` é gerado a partir dele.

A razão é simples: com 159 atividades e 297 casos de teste, escrever INSERT na mão
garante dois tipos de erro que só aparecem tarde demais — um gabarito que não
bate com a solução, e uma prova apontando para o módulo errado. O gerador elimina
o primeiro tipo, e o validador pega o segundo.

## Os arquivos

| Arquivo | O que faz |
|---|---|
| `conteudo_base.py` | O vocabulário: funções `quiz()` e `codigo()` e o significado dos casos de teste em JS e em HTML/CSS. |
| `conteudo_html.py`, `conteudo_css.py`, `conteudo_funcoes.py`, `conteudo_desafios.py`, `conteudo_projeto.py` | Uma trilha por arquivo (v6). `conteudo.py` junta todas em `TRILHAS`. |
| `checar_trilha.py` | Confere UMA trilha antes de ela entrar no banco: `python3 tools/checar_trilha.py conteudo_html`. |
| `validar_html.js` | Validador dos exercícios de HTML e CSS, num Chromium de verdade (Playwright). Chamado por `validar_conteudo.js`. |
| `conteudo.py` | O conteúdo em si: teoria, enunciados, dicas, soluções e casos de teste. **É aqui que se escreve aula nova.** |
| `gerar_banco.py` | Lê `conteudo.py` e reescreve a seção de dados de `database/banco.sql`. Não toca no schema. |
| `exportar_para_validar.php` | Lê o banco já importado e despeja em `dados.json` o que precisa ser conferido. |
| `validar_conteudo.js` | O validador. Roda a solução de referência de cada exercício contra os próprios casos de teste. |
| `rodar_isolado.js` | Executa um código em processo separado, com limite de tempo. Usado pelo validador para não travar em laço infinito. |

As suítes de `testes/` têm o seu próprio ajudante, `testes/ajudantes_teste.js`.


## Como adicionar ou mudar conteúdo

```bash
# 1. edite tools/conteudo.py

# 2. gere o SQL
python3 tools/gerar_banco.py

# 3. importe (isso APAGA os dados atuais — é um banco de desenvolvimento)
mysql -u root devfox < database/banco.sql

# 4. valide
php tools/exportar_para_validar.php
node tools/validar_conteudo.js
```

O passo 4 não é opcional. Ele confere:

- a **solução de referência passa em todos os casos de teste** do exercício;
- o **código inicial NÃO passa** (senão o exercício não exercita nada);
- todo exercício com mais de um comportamento tem **caso oculto**, e esse caso
  não repete a entrada de um visível;
- todo quiz tem **exatamente uma** alternativa correta, sem opções repetidas;
- toda questão de prova aponta um **módulo de aula da mesma trilha**, anterior à prova;
- os módulos têm **ordem sequencial** e a prova é sempre o último.

Ele já pegou erro de verdade: um exercício de matriz cujo bug (`matriz[i][i]` em
vez de `matriz[i][j]`) dava **o mesmo resultado** nos casos de teste escolhidos —
o aluno passaria sem corrigir nada.

## Ao escrever um exercício de código

Duas armadilhas que o validador não consegue adivinhar sozinho:

**A comparação no servidor ignora maiúsculas e colapsa espaços.** Então não
existe exercício cuja única diferença de resultado seja o caixa das letras — uma
função que devolvesse o texto sem alterar passaria num exercício de
`toUpperCase()`.

**O caso oculto precisa realmente distinguir.** Escolha uma entrada em que a
solução errada mais provável produza resultado DIFERENTE da certa. Se não
produzir, o caso oculto é decoração.


## Exercícios de HTML e CSS (v6)

Trilhas com `linguagem='html'` não rodam JavaScript: o código do aluno vira uma
página num iframe isolado e cada caso de teste é uma **verificação** sobre ela:

| Verificação | Devolve |
|---|---|
| `existe("nav a")` | `sim` / `não` |
| `contar("li")` | quantidade, ex. `3` |
| `texto("h1")` | texto do 1º elemento, espaços colapsados |
| `atributo("img", "alt")` | valor do atributo, ou `(sem atributo)` |
| `estilo("h1", "color")` | valor **calculado** pelo navegador: `rgb(255, 107, 0)`, `24px`, `flex` |

Elemento que não existe devolve `(nenhum elemento)`. No CSS, confira sempre
propriedades longas (`margin-top`, não `margin`) e escreva a saída no formato
calculado — o validador acusa quando não bate.
