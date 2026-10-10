# CLAUDE.md — DevFox

> **A fonte canônica de contexto e regras deste projeto é o [`AGENTS.md`](./AGENTS.md).**
> Leia-o primeiro e siga-o integralmente. Este arquivo só reforça, ao Claude Code, os pontos
> específicos do ecossistema Claude — para não duplicar (nem divergir) do AGENTS.md.

## Antes de qualquer coisa
1. Leia `AGENTS.md`, `AI_HANDOFF.md` e `PERFIL.md`.
2. Rode `git log --oneline -10` e `git status` para ver onde o último agente parou (quando o
   repositório já existir — hoje ainda não existe).
3. Siga o **Protocolo de Handoff** (§10 do AGENTS.md): resuma → liste arquivos →
   plano curto → riscos → **aguarde aprovação** antes de editar.

## Regra de execução — planejar vs. executar
Antes de executar qualquer tarefa, avaliar **onde** ela deve ser feita.

- **Tarefas simples** (documentação, organização, revisão de texto, ajuste não técnico):
  perguntar ao usuário se quer que seja feita direto na sessão atual.
- **Tarefas técnicas** (código, terminal, banco de dados, automação, integração, deploy):
  **não executar direto em ferramenta de conversa.** Primeiro levantar o contexto, montar um
  plano estruturado e perguntar ao usuário se quer levar esse plano para execução no Claude Code.

## Conectores e ferramentas
- **Confirme o que está realmente conectado antes de afirmar que falta algo.** Rode
  `claude mcp list` (ou `/mcp`). Uma ferramenta pode estar **conectada mas não carregada** na
  sessão — nesse caso, recarregue a sessão e confira de novo. **Não conclua "não tenho acesso"
  sem checar.**
- **MySQL via XAMPP** — banco `devfox`, acessado por PDO em `config/conexao.php`. Local, sem
  credencial sensível (padrão `root` sem senha do XAMPP). Ao configurar produção, registrar aqui
  como o Claude Code deve acessar o banco real.

## Antes de encerrar a sessão
- **Sempre atualize `AI_HANDOFF.md`** com o que foi feito, decisões tomadas, o que falta e o
  próximo passo. Não deixe a próxima IA adivinhar. Faça isso **a cada checkpoint**, não só no fim.
- Checkpoint com **commit** (não stash), quando o repositório existir.

## Lembretes de segurança
- Nunca edite `.env` nem exponha credenciais (chaves do Supabase).
- Nada de comandos destrutivos sem aprovação.
- Nunca execute código enviado por um usuário do DevFox no backend/servidor — a correção de
  desafios roda só no navegador (Web Worker). Essa é a regra de negócio inviolável mais fácil de
  quebrar por conveniência técnica — repetida do AGENTS.md §6 porque custa caro (segurança) se
  for esquecida.
