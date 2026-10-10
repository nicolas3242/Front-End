# DevFox

Plataforma gamificada para aprender lógica de programação e JavaScript. O aluno
percorre trilhas com aulas e exercícios, escreve código de verdade no navegador,
ganha XP, sobe de nível e faz uma prova no fim de cada nível para avançar.

Projeto de TCC. PHP 8 + MySQL, sem framework.

---

## Como rodar em 5 minutos

1. Copie esta pasta para `C:\xampp\htdocs\devfox`
2. Abra o XAMPP e ligue **Apache** e **MySQL**
3. Vá em <http://localhost/phpmyadmin> → aba **Importar** → escolha
   `database/banco.sql` → **Executar**
4. Confira se `config/conexao.php` bate com o seu MySQL (padrão do XAMPP:
   usuário `root`, senha vazia)
5. Acesse <http://localhost/devfox>

Contas de teste já criadas pelo `banco.sql`:

| Perfil | E-mail | Senha |
|---|---|---|
| Aluno | `aluno@devfox.local` | `aluno123` |
| Administrador | `admin@devfox.local` | `devfox123` |

O passo a passo completo, com os erros mais comuns e como resolver cada um, está
em **[`docs/COMO_RODAR.md`](docs/COMO_RODAR.md)**.

---

## Mapa da pasta

```
DevFox/
├── index.php  login.php  cadastro.php  logout.php     entrada e autenticacao
├── trilhas.php                                        lista de trilhas e progresso
├── desafio.php                                        aula + exercicio
├── prova.php                                          avaliacao de fim de nivel
├── perfil.php  ranking.php                            gamificacao
├── administracao.php                                  cadastro de conteudo (so admin)
│
├── includes/       auth.php (login, XP, progressao), header, footer, ui (icones)
├── api/            processar_desafio.php — o juiz das respostas, roda no servidor
├── assets/         css, js (executor.worker.js roda o codigo do aluno), imagens
├── config/         conexao.php (PDO) e conexao.exemplo.php
│
├── database/       banco.sql, migracoes e README explicando o schema
├── testes/         duas suites em Playwright + README de como rodar
└── docs/           COMO_RODAR, BACKLOG_JIRA, plano de producao e os wireframes
```

Na raiz também ficam os arquivos que orientam as IAs que trabalham no projeto —
`AGENTS.md` (as regras), `CLAUDE.md`, `AI_HANDOFF.md` (o estado atual e o próximo
passo) e `PERFIL.md` (o contexto do produto). Eles precisam ficar na raiz: é onde
as ferramentas procuram.

---

## As três decisões que explicam o código

**O código do aluno roda no navegador, não no servidor.** Um Web Worker
(`assets/js/executor.worker.js`) executa o JavaScript numa thread separada, o que
permite matar um laço infinito sem travar a página. Rodar código de terceiros no
servidor exigiria isolamento por container — inviável no prazo, e um buraco de
segurança se feito pela metade.

**Quem decide se a resposta está certa é o servidor.** O navegador recebe só as
**entradas** dos casos de teste; as saídas esperadas nunca saem do banco. O
`api/processar_desafio.php` compara e decide. Qualquer coisa julgada no navegador
seria forjável pelo DevTools em dez segundos.

**Reprovar na prova não apaga progresso.** O sistema aponta quais módulos o aluno
errou e ele revisa só aqueles. O DevFox existe para reduzir desistência de
iniciantes; mandar alguém recomeçar o nível é o caminho mais rápido para provocar
exatamente a desistência que o projeto quer evitar.

---

## Testes

As duas suítes em `testes/` sobem um Chromium de verdade e usam o sistema como um
aluno usaria. Elas provam, na defesa, que as regras acima não são só promessa:
que o gabarito não vaza, que laço infinito é interrompido, que XP não pode ser
farmado, que módulo bloqueado não abre nem pela URL e que aluno não entra na
Administração.

Como rodar: **[`testes/README.md`](testes/README.md)**.

---

## Estado do projeto

O sistema funciona ponta a ponta: cadastro → login → trilha → aula → exercício com
execução real de código → prova → XP → ranking → perfil.

O que ainda falta, priorizado e pronto para lançar no Jira, está em
**[`docs/BACKLOG_JIRA.md`](docs/BACKLOG_JIRA.md)**. O plano de produção em fases
está em `docs/plano-producao.html` (abra no navegador).

O próximo passo sempre atual fica no topo do **`AI_HANDOFF.md`**.
