# DevFox — Backlog para o Jira

Backlog do MVP, pronto para virar issues. Cada item traz tipo, prioridade,
descrição e critério de aceite — é só copiar campo a campo.

**Estimativas:** são um chute inicial meu, para vocês terem um ponto de
partida. Quem calibra é o time, depois da primeira sprint. Escala usada:
1 = poucas horas · 3 = um dia · 5 = dois a três dias · 8 = uma semana.

**Contexto de prazo:** ~7 semanas até a banca (data exata ainda não definida —
ver DEV-3). As sprints sugeridas são de 1 semana.

---

## ÉPICO 1 — Infraestrutura e ambiente

### DEV-1 · Validar execução local do sistema em todas as máquinas do time

- **Tipo:** Task · **Prioridade:** Highest · **Estimativa:** 2 · **Sprint:** 1
- **Descrição:**
  O sistema foi testado em ambiente controlado (PHP 8.4 + MariaDB 10.11), mas
  ainda não rodou na máquina de ninguém do time. Mover o projeto para
  `C:\xampp\htdocs\DevFox`, importar `database/banco.sql` e percorrer o
  roteiro de teste do `COMO_RODAR.md`.
- **Critério de aceite:**
  - [ ] Cada integrante consegue abrir `http://localhost/DevFox` e fazer login
  - [ ] Cada integrante conclui pelo menos uma atividade e vê o XP subir
  - [ ] Reenviar a mesma atividade **não** concede XP de novo
  - [ ] Nenhum erro novo em `C:\xampp\apache\logs\error.log`
- **Bloqueia:** todas as demais tarefas de código

### DEV-2 · Criar repositório Git e subir o projeto

- **Tipo:** Task · **Prioridade:** Highest · **Estimativa:** 2 · **Sprint:** 1
- **Descrição:**
  O projeto não tem controle de versão. Todo o trabalho está numa pasta única,
  sem histórico e sem backup — um arquivo sobrescrito por engano é perda
  definitiva. O `.gitignore` e o `config/conexao.exemplo.php` já estão prontos
  na raiz.
- **Critério de aceite:**
  - [ ] Repositório remoto criado (GitHub) com o projeto completo
  - [ ] `config/conexao.php` **não** aparece no repositório
  - [ ] Os colegas têm acesso e conseguem clonar
  - [ ] README na raiz apontando para o `COMO_RODAR.md`

### DEV-3 · Definir a data da banca e ajustar o cronograma

- **Tipo:** Task · **Prioridade:** High · **Estimativa:** 1 · **Sprint:** 1
- **Descrição:**
  O cronograma atual assume ~8 semanas a partir de 01/09/2026, sem data
  confirmada. Sem a data real, não há como saber o que cortar se atrasar.
- **Critério de aceite:**
  - [ ] Data registrada no `AI_HANDOFF.md`
  - [ ] Semanas do plano de produção ajustadas à data real

### DEV-4 · Confirmar a stack com o orientador

- **Tipo:** Task · **Prioridade:** Medium · **Estimativa:** 1 · **Sprint:** 1
- **Descrição:**
  Confirmar que um backend PHP autoral (sem framework) atende os critérios de
  avaliação. Se o orientador esperar algo diferente, é melhor descobrir agora
  do que na semana da entrega.
- **Critério de aceite:**
  - [ ] Resposta do orientador registrada no `AI_HANDOFF.md`

### DEV-5 · Definir divisão de responsabilidades do time

- **Tipo:** Task · **Prioridade:** Medium · **Estimativa:** 1 · **Sprint:** 1
- **Descrição:**
  Definir quem cuida de backend, front e conteúdo pedagógico. As três frentes
  são razoavelmente independentes e dá para tocar em paralelo.
- **Critério de aceite:**
  - [ ] Cada épico com um responsável principal definido

---

## ÉPICO 2 — Motor de exercícios

### DEV-6 · Executar o código do aluno em Web Worker

- **Tipo:** Story · **Prioridade:** Highest · **Estimativa:** 8 · **Sprint:** 2
- **Descrição:**
  Hoje o servidor compara a **saída que o aluno digita**, sem executar o código
  dele — quem digitar o resultado esperado passa sem resolver nada. Rodar o
  JavaScript do aluno num Web Worker isolado no navegador, avaliar contra todos
  os casos de `casos_teste` (inclusive os `visivel = 0`) e enviar ao servidor
  apenas o veredito.
- **Critério de aceite:**
  - [ ] Aluno que digita a saída esperada sem escrever código **não** passa
  - [ ] Casos ocultos são avaliados junto com os visíveis
  - [ ] Um `while(true){}` do aluno é interrompido por timeout e não trava a
        página
  - [ ] Erro de sintaxe no código do aluno mostra mensagem clara, sem quebrar
        a tela
  - [ ] O servidor continua concedendo XP no máximo uma vez por atividade
- **Observação:** é a tarefa de maior valor técnico do projeto e a mais
  defensável numa banca. Merece ser a primeira depois da infraestrutura.

### DEV-7 · Substituir o textarea pelo editor CodeMirror

- **Tipo:** Story · **Prioridade:** High · **Estimativa:** 5 · **Sprint:** 2
- **Descrição:**
  O editor de código hoje é um `<textarea>` simples. Os mockups preveem
  numeração de linha e destaque de sintaxe. Integrar CodeMirror via CDN.
- **Critério de aceite:**
  - [ ] Numeração de linha e destaque de sintaxe JavaScript
  - [ ] Tema escuro coerente com a identidade (`#0f1117` / `#ff6b00`)
  - [ ] O código digitado continua sendo enviado corretamente ao backend
  - [ ] Funciona em tela de celular

### DEV-8 · Mostrar resultado por caso de teste

- **Tipo:** Story · **Prioridade:** Medium · **Estimativa:** 3 · **Sprint:** 3
- **Descrição:**
  Depois de rodar o código, mostrar quais casos passaram e quais falharam, em
  vez de um "certo/errado" único.
- **Critério de aceite:**
  - [ ] Cada caso visível aparece com ✅ ou ❌ e o valor obtido
  - [ ] Casos ocultos aparecem apenas como "caso oculto: passou/falhou", sem
        revelar entrada nem saída esperada

---

## ÉPICO 3 — Conteúdo e painel do professor

### DEV-9 · Cadastro de módulos e atividades pelo painel do professor

- **Tipo:** Story · **Prioridade:** High · **Estimativa:** 8 · **Sprint:** 3
- **Descrição:**
  O painel só cadastra trilhas. Módulos, atividades, alternativas de quiz e
  casos de teste ainda exigem phpMyAdmin — o que inviabiliza demonstrar a
  gestão de conteúdo na banca.
- **Critério de aceite:**
  - [ ] Professor cria, edita e ordena módulos dentro de uma trilha
  - [ ] Professor cria atividade do tipo quiz, com alternativas e a correta
  - [ ] Professor cria atividade de código, com casos de teste visíveis e ocultos
  - [ ] Todo acesso protegido por `exigirProfessor()`

### DEV-10 · Produzir a trilha "Lógica de Programação" completa

- **Tipo:** Story · **Prioridade:** Highest · **Estimativa:** 8 · **Sprint:** 3–5
- **Descrição:**
  Uma trilha completa, de ponta a ponta, é o que a banca vai percorrer. Gerar
  com apoio de IA e revisar manualmente. É a tarefa mais longa do projeto e a
  que menos acelera com código — começar cedo.
- **Critério de aceite:**
  - [ ] Pelo menos 5 módulos com teoria escrita
  - [ ] Pelo menos 3 atividades por módulo, variando os tipos
  - [ ] **Todo gabarito conferido rodando o código de verdade**, não só lido
  - [ ] Progressão de dificuldade coerente do início ao fim

### DEV-11 · Esboçar mais 1–2 trilhas parciais

- **Tipo:** Story · **Prioridade:** Medium · **Estimativa:** 3 · **Sprint:** 5
- **Descrição:**
  Trilhas com título, descrição e alguns módulos, para mostrar que a estrutura
  comporta crescimento. Não precisam estar completas.
- **Critério de aceite:**
  - [ ] 1 ou 2 trilhas cadastradas com pelo menos 1 módulo e 1 atividade cada
  - [ ] Aparecem corretamente na tela de trilhas, com o progresso certo

### DEV-12 · Implementar o desbloqueio de conquistas

- **Tipo:** Story · **Prioridade:** Low · **Estimativa:** 5 · **Sprint:** 6
- **Descrição:**
  As tabelas `conquistas` e `conquistas_usuarios` existem e o catálogo já tem
  6 conquistas, mas nada as desbloqueia. Item de fase 2 — só fazer se as
  tarefas acima estiverem fechadas.
- **Critério de aceite:**
  - [ ] As 6 conquistas do catálogo desbloqueiam nas condições descritas
  - [ ] A conquista aparece no perfil com data de desbloqueio
  - [ ] Uma conquista nunca é concedida duas vezes

---

## ÉPICO 4 — Segurança e qualidade

### DEV-13 · Proteção CSRF nos formulários que alteram dados

- **Tipo:** Task · **Prioridade:** High · **Estimativa:** 3 · **Sprint:** 6
- **Descrição:**
  Nenhum formulário usa token CSRF. O caso mais sensível é o painel do
  professor, onde um POST forjado cadastraria conteúdo.
- **Critério de aceite:**
  - [ ] Token gerado por sessão e validado em todo POST que altera dados
  - [ ] POST sem token válido é rejeitado

### DEV-14 · Auditoria de XSS e SQL injection

- **Tipo:** Task · **Prioridade:** High · **Estimativa:** 3 · **Sprint:** 6
- **Descrição:**
  Revisar todas as queries e todas as saídas. O código atual usa prepared
  statements e `htmlspecialchars()`, mas isso precisa ser conferido arquivo a
  arquivo, principalmente no que for escrito daqui em diante.
- **Critério de aceite:**
  - [ ] Nenhuma query concatena entrada do usuário
  - [ ] Todo dado vindo do banco ou do usuário é escapado na saída
  - [ ] Teste manual: cadastrar usuário com nome `<script>alert(1)</script>`
        não executa nada em nenhuma tela

### DEV-15 · Testar responsividade em celular e tablet

- **Tipo:** Task · **Prioridade:** Medium · **Estimativa:** 3 · **Sprint:** 6
- **Descrição:**
  O CSS ganhou media queries, mas isso foi validado só redimensionando a
  janela — não em aparelho real.
- **Critério de aceite:**
  - [ ] Todas as telas usáveis em 360px de largura
  - [ ] Nenhuma barra de rolagem horizontal na página
  - [ ] Editor de código utilizável no celular

### DEV-16 · Limitar tentativas de login

- **Tipo:** Task · **Prioridade:** Low · **Estimativa:** 3 · **Sprint:** 7
- **Descrição:**
  Não há limite de tentativas de senha. Baixo risco num TCC, mas é o tipo de
  pergunta que uma banca técnica faz.
- **Critério de aceite:**
  - [ ] Após N tentativas erradas, o login é bloqueado temporariamente

---

## ÉPICO 5 — Deploy e entrega

### DEV-17 · Escolher e configurar a hospedagem

- **Tipo:** Task · **Prioridade:** High · **Estimativa:** 3 · **Sprint:** 7
- **Descrição:**
  Precisa suportar PHP + MySQL. Comparar hospedagem compartilhada tradicional
  com plataformas tipo Railway/Render.
- **Critério de aceite:**
  - [ ] Hospedagem definida e acessível pelo time
  - [ ] Credenciais fora do código, por variável de ambiente

### DEV-18 · Publicar em produção

- **Tipo:** Task · **Prioridade:** High · **Estimativa:** 3 · **Sprint:** 7
- **Critério de aceite:**
  - [ ] Sistema acessível por uma URL pública
  - [ ] Banco de produção criado a partir de `database/banco.sql`
  - [ ] Contas de teste (`@devfox.local`) removidas ou com senha trocada
  - [ ] `display_errors` desligado
  - [ ] Roteiro completo do `COMO_RODAR.md` percorrido no ambiente publicado

### DEV-19 · Documentação escrita do TCC

- **Tipo:** Task · **Prioridade:** High · **Estimativa:** 8 · **Sprint:** 6–8
- **Descrição:**
  Monografia/relatório. Boa parte do material já existe: `PERFIL.md` cobre
  problema, público e justificativa; `database/README.md` cobre as decisões de
  modelagem; o `AI_HANDOFF.md` tem o histórico de decisões com os porquês.
- **Critério de aceite:**
  - [ ] Documento cobrindo problema, solução, decisões técnicas e resultados
  - [ ] Diagrama entidade-relacionamento do banco
  - [ ] Seção de trabalhos futuros (fase 2: loja, PvP, comunidade)

### DEV-20 · Slides e ensaio da apresentação

- **Tipo:** Task · **Prioridade:** High · **Estimativa:** 5 · **Sprint:** 8
- **Critério de aceite:**
  - [ ] Slides prontos
  - [ ] Demonstração ensaiada no ambiente publicado, não em localhost
  - [ ] Roteiro de demonstração definido (qual trilha, qual desafio, em que ordem)
  - [ ] Respostas preparadas para: por que PHP sem framework, como a correção
        funciona, como o XP é protegido contra fraude

### DEV-21 · Semana de folga antes da banca

- **Tipo:** Task · **Prioridade:** Medium · **Estimativa:** — · **Sprint:** 8
- **Descrição:**
  Reservar os últimos 3 a 4 dias sem nenhuma tarefa nova planejada. Não é
  preguiça: é onde cabem os imprevistos que sempre aparecem.
- **Critério de aceite:**
  - [ ] Nenhuma tarefa nova agendada nesse período

---

## Já concluído (criar como Done, se quiserem registrar no Jira)

| Item | Tipo |
|---|---|
| Definir escopo do MVP e cortar funcionalidades de fase 2 | Task |
| Decidir a stack (PHP + MySQL) e registrar a decisão | Task |
| Reescrever o schema do banco (v2) com script de migração | Story |
| Corrigir farm de XP (reenvio da mesma atividade concedia XP infinito) | Bug |
| Corrigir correção de exercício por substring (burlável) | Bug |
| Corrigir funções `usuarioLogado()` e `exibeMensagem()` inexistentes | Bug |
| Corrigir logout que não encerrava a sessão | Bug |
| Corrigir autopromoção a professor pela tela de cadastro | Bug |
| Corrigir `desafio.php` carregando arquivo JS com nome errado | Bug |
| Corrigir segundo farm de XP em `atividade.php` | Bug |
| Corrigir acentuação duplamente codificada nos scripts SQL | Bug |
| Unificar os dois fluxos de exercício divergentes | Task |
| Completar o CSS com as classes faltantes e responsividade | Task |
| Escrever documentação do projeto (AGENTS, PERFIL, HANDOFF, COMO_RODAR) | Task |

---

## Sugestão de sprints

| Sprint | Foco | Issues |
|---|---|---|
| 1 | Destravar o time | DEV-1 a DEV-5 |
| 2 | Motor de exercícios | DEV-6, DEV-7 |
| 3 | Painel e início do conteúdo | DEV-8, DEV-9, DEV-10 (início) |
| 4–5 | Conteúdo pedagógico | DEV-10, DEV-11 |
| 6 | Segurança, polimento, documentação | DEV-12 a DEV-15, DEV-19 |
| 7 | Deploy | DEV-16, DEV-17, DEV-18 |
| 8 | Apresentação e folga | DEV-20, DEV-21 |

**Caminho crítico:** DEV-1 → DEV-2 → DEV-6 → DEV-10. Se algo atrasar, o
primeiro corte deve ser DEV-12 (conquistas), depois DEV-16 (limite de login) e
DEV-8 (resultado por caso de teste) — nenhum dos três compromete a
demonstração do produto.
