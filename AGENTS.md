# AGENTS.md — DevFox

> **Fonte canônica de contexto e regras para QUALQUER agente de IA** que trabalhar neste
> projeto. `CLAUDE.md` apenas aponta para este arquivo. Se algo aqui conflitar com outro
> arquivo, **este vence**.

---

## 1. Objetivo do projeto

DevFox é uma plataforma web educacional gamificada para ensinar lógica de programação e
JavaScript, inspirada em apps como Duolingo. É o TCC de Murilo Martins Souza e 1-2 colegas,
com prazo de entrega de menos de 2 meses a partir de 29/08/2026. Resultado esperado: um MVP
funcional (não um mockup) com cadastro/login, trilhas de aprendizagem, aulas, desafios com
correção automática e acompanhamento de progresso.

## 2. Escopo — o que este projeto NÃO é (por enquanto)

- Não inclui Daily Bug, PvP, loja/moedas (funcional), comunidade ou perfil personalizável
  nesta fase — são "fase 2", documentadas para não se perderem, mas **fora do MVP**. Não
  implementar sem decisão explícita revertendo esta. Exceção registrada em 29/08/2026: a Loja
  aparece na sidebar como item inativo/"em breve" (só visual, sem tela funcional).
- Não executa código do usuário no backend/servidor — a correção roda inteiramente no
  navegador (Web Worker). Ver §6.
- Não ensina outras linguagens além de JavaScript nesta fase.
- Não é um backend próprio (Node/Express) — usa Supabase como BaaS.

## 3. Stack e integrações

> ⚠ = sistema/serviço com **side-effect**: altera estado **fora** do controle de versão.

- Linguagem / runtime: PHP (procedural, sem framework) no backend; JavaScript puro no front
  para interações pontuais (ex: envio de desafio via fetch)
- Frameworks e bibliotecas principais: PDO (acesso a banco), nenhum framework PHP; CSS próprio
  (`assets/css/styles.css`), fonte Poppins via Google Fonts, Font Awesome via cdnjs em algumas telas
- Onde o projeto roda e onde é publicado: dev local via XAMPP (Apache + MySQL). Hospedagem de
  produção A DEFINIR — precisa suportar PHP + MySQL (ver PERFIL.md §13).
- Serviços externos:
  - **MySQL local (XAMPP)** ⚠ — banco `devfox`, schema em `database/banco.sql`. É a fonte da
    verdade de usuários, trilhas, módulos, atividades e progresso.

**Decisão de 29/08/2026 (revisando decisão anterior):** um dos colegas já havia construído um
protótipo funcional em PHP + MySQL cobrindo a maior parte das telas do MVP. Diante disso, a
equipe decidiu **adotar PHP + MySQL como stack real**, abandonando o plano anterior de
React + Supabase — para não descartar trabalho já feito e porque um backend autoral em PHP
resolve a dúvida sobre "engenharia própria" para a banca. Ver `AI_HANDOFF.md` para o histórico
completo da decisão e os bugs críticos encontrados nesse protótipo que precisam ser corrigidos
antes de continuar.

## 4. Fonte da verdade por assunto

| Assunto | Fonte da verdade | Onde fica |
|---|---|---|
| Usuários, autenticação | Supabase Auth | painel do Supabase |
| Trilhas, aulas, desafios, progresso | Supabase (Postgres) | painel do Supabase |
| Identidade visual (mascote, cores, tipografia) | Documento de apresentação do projeto | DevFox_Apresentacao_do_Projeto.docx / PERFIL.md §8 |
| Estado do projeto e decisões recentes | `AI_HANDOFF.md` | raiz do projeto |
| Contexto de negócio, público e tom | `PERFIL.md` | raiz do projeto |

> Qualquer dado citado dentro de um documento é **snapshot datado**. Conferir na fonte antes de usar.

## 5. Como rodar e validar ("estado bom")

```
# instalar:
Instalar XAMPP (Apache + MySQL + PHP). Importar database/banco.sql no phpMyAdmin
(cria o banco `devfox` e os dados de exemplo). Se você JÁ tem o banco antigo com
dados, faça backup e rode database/migracao_v1_para_v2.sql em vez disso — uma vez só.
Contas de teste (só dev): professor@devfox.local / devfox123 e aluno@devfox.local / aluno123.
# rodar localmente:
Colocar a pasta do projeto em htdocs/ do XAMPP, iniciar Apache e MySQL, acessar
http://localhost/devfox/
# o que define "verde":
Não existe suíte de testes automatizada nem lint configurado ainda. "Estado bom" hoje é
manual: login, cadastro, navegação pelas trilhas e envio de um desafio funcionam sem erro
fatal do PHP. Ver checklist de bugs críticos em AI_HANDOFF.md — enquanto eles não forem
corrigidos, o estado NÃO é bom.
```

⚠ Sem lint, sem testes automatizados, sem CI. Isso é uma lacuna conhecida, não um esquecimento —
registrar aqui se/quando isso mudar.

**Publicação:** A DEFINIR — depende da hospedagem escolhida (ver §3).

## 6. Regras de negócio / de dados (invioláveis)

- Nunca renderizar telas como imagem estática — toda tela (login, cadastro, dashboard, trilhas
  etc.) deve ser aplicação web real e funcional, com validação de formulário de verdade.
- Nunca executar código arbitrário enviado pelo usuário diretamente no PHP (ex: `eval()`). A
  correção de "código livre" roda no navegador (Web Worker) contra casos de teste; o backend só
  recebe o resultado. Exercícios de "completar"/"corrigir erro" são validados por comparação de
  valor, nunca por `strpos()`/substring — isso deixa passar respostas coladas (bug corrigido na
  fase 1 do plano de produção).
- Nunca permitir que um usuário se autopromova a "professor" no cadastro público — a
  criação de contas com esse papel precisa de um caminho controlado (hoje: manual no banco).
- Nunca publicar conteúdo pedagógico (aula, exercício, gabarito) gerado por IA sem revisão
  humana explícita de alguém do time antes de ir ao ar.
- Toda alegação sobre identidade visual (cores, mascote, tom) deve ser rastreável ao documento
  de apresentação do projeto ou ao `PERFIL.md`, nunca inferida — os tokens reais já estão em
  `assets/css/styles.css` (`--bg-dark: #0f1117`, `--primary-orange: #ff6b00`, fonte Poppins).
- Antes de alterar `database/banco.sql` ou o schema em produção, revisar o impacto em progresso
  já registrado de usuários de teste.

## 7. Regras de edição

- **Plano antes de editar** (ver Protocolo de Handoff, §10).
- Commits pequenos e atômicos. Uma intenção por commit.
- Não introduzir dependência, serviço ou integração nova (ex: trocar Supabase por outro backend)
  sem registrar a decisão no handoff.
- Manter o "estado bom" (§5) assim que ele for definido.
- Seguir a separação interface/lógica/dados e a componentização descritas no documento de
  apresentação original: React componentizado, sem lógica de negócio misturada em componentes
  visuais.

### 7.1 Rito para mudanças com side-effect ⚠ (Supabase)

1. Escrever a mudança de forma **idempotente** (ex: migrations que podem rodar de novo sem
   duplicar dado).
2. **Ensaiar em projeto Supabase de teste** antes de aplicar em produção, quando produção existir.
3. **Aplicar somente com aprovação explícita** de Murilo ou do grupo.
4. **Verificar depois:** login, cadastro e leitura de progresso continuam funcionando sem erro.
5. **Reverter todo dado de teste** criado durante a validação.
6. Registrar no `AI_HANDOFF.md` o que foi aplicado.

### 7.2 Produção vs. teste

A DEFINIR — hoje não existe nenhum ambiente ainda. Quando o projeto Supabase for criado, decidir
se haverá projeto separado de teste/produção, considerando os limites do tier gratuito.

## 8. Arquivos e áreas que NÃO devem ser alterados

- `.env` e qualquer arquivo com credencial real (chaves do Supabase). **Nunca commitar segredo.**
- A `service_role key` do Supabase nunca deve aparecer no código do frontend nem em commits —
  só a `anon key` pública pode estar no cliente.
- Nada gerado automaticamente deve ser editado à mão (aplicável quando houver build gerado).

## 9. Restrições de segurança

- Nunca executar comando destrutivo — apagar em massa, remover estrutura de dados, reescrever
  histórico remoto, resetar branch — **sem aprovação explícita**.
- Nunca usar nem expor credencial real (chaves do Supabase, tokens) em log, código, commit ou handoff.
- Nunca conceder ao frontend mais permissão do que a tarefa exige — usar Row Level Security
  (RLS) do Supabase desde o início; não deixar tabelas abertas "por enquanto".
- Em ambiente com terminal ou navegador liberado, começar travado: acesso restrito ao
  workspace, execução sob revisão.
- ⚠ **Um agente por vez** ao tocar o projeto Supabase compartilhado — dois agentes mexendo no
  mesmo banco ao mesmo tempo colidem, mesmo em branches de código diferentes.

## 10. Protocolo de Handoff (revezamento entre IAs)

**Antes de editar qualquer arquivo, numa sessão nova:**
1. Resumir o estado atual (`git log --oneline -10` e `git status`, quando o repositório existir).
2. Ler `AI_HANDOFF.md` e este arquivo.
3. Listar os arquivos relevantes para a tarefa.
4. Apresentar um plano curto.
5. Apontar os riscos — especialmente nas integrações ⚠.
6. **Aguardar aprovação.**

**Antes de encerrar a sessão ou trocar de ferramenta:**
1. Atualizar `AI_HANDOFF.md`: intenção, decisões, becos sem saída, próximo passo.
2. Checkpoint com **commit** (quando o repositório existir):
   ```bash
   git add -A && git commit -m "wip: checkpoint before switching agent"
   ```
   Prefira commit a `git stash` — stash é local e invisível para o próximo agente.
3. Garantir que o "estado bom" (§5) ainda passa — ou registrar o que está quebrado e por quê.

> **Atualize o handoff a cada checkpoint, ANTES de chegar ao limite de contexto** — não depois.
>
> Regra de ouro: nunca troque de agente com working tree suja e handoff desatualizado.

## 11. Mapa de pastas

- **Raiz:** páginas PHP renderizadas diretamente (`login.php`, `cadastro.php`, `trilhas.php`,
  `desafio.php`, `atividade.php` — ver nota abaixo, `ranking.php`, `perfil.php`, `professor.php`,
  `painel_professor.php`, `index.php`, `logout.php`). Fonte viva.
- **`config/`** — `conexao.php`: credenciais e conexão PDO com o MySQL. Nunca commitar credencial
  real aqui; hoje usa o padrão local do XAMPP (`root` sem senha), que só serve para dev local.
- **`database/`** — `banco.sql` (schema v2 completo + dados de exemplo, fonte viva do schema),
  `migracao_v1_para_v2.sql` (atualiza um banco v1 que já tem dados; rodar uma vez só),
  `README.md` (documentação das decisões do schema e lista dos arquivos PHP impactados) e
  `banco_v1_original.sql` (backup da primeira versão, não usar para criar o banco).
- **`includes/`** — `header.php` (sidebar, guarda de sessão, abre `<html>`) e `footer.php`
  (fecha as tags). Toda página autenticada inclui os dois.
- **`api/`** — endpoints chamados via fetch pelo front (hoje só `processar_desafio.php`).
- **`assets/`** — `css/styles.css` (tokens visuais e layout), `js/` (interações client-side),
  `imagens/` (logo e material de referência visual).
- ⚠ **`atividade.php` vs. `desafio.php`:** hoje são dois fluxos de exercício divergentes e não
  conectados (ver `AI_HANDOFF.md`). Até a fase 0 do plano de produção resolver isso, tratar
  `atividade.php` como código a ser aposentado, não como referência para código novo.
