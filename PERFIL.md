# Perfil — DevFox

> Documento de referência do **projeto, do negócio e da operação**: o que é, para quem, com
> que regras e em que tom. **Não é fonte da verdade de dado operacional** — para isso, veja a
> tabela "Como este documento se encaixa", logo abaixo.
>
> **Última atualização:** 19/09/2026 · **Status:** material vivo; revisar sempre que houver
> mudança relevante em oferta, público, posicionamento ou operação.

## Como este documento se encaixa

| Assunto | Fonte da verdade | Onde fica |
|---|---|---|
| Regras técnicas para agentes de IA | `AGENTS.md` | raiz do projeto |
| Estado do projeto e decisões recentes | `AI_HANDOFF.md` | raiz do projeto |
| Identidade visual detalhada | Documento de apresentação do projeto | DevFox_Apresentacao_do_Projeto.docx |
| Identidade, público, tom, visão geral | **este arquivo** | — |

> Quando este documento cita um dado operacional, é **snapshot datado**. Confira na fonte antes
> de publicar qualquer coisa.

---

## 1. Resumo executivo

### Síntese

- **Nome:** DevFox
- **Categoria / setor:** EdTech — plataforma web educacional gamificada
- **O que entrega:** ensino de lógica de programação e JavaScript por meio de trilhas, aulas e
  desafios gamificados (XP, níveis). Moedas, loja, ranking, PvP e comunidade são fase 2.
- **Onde atua:** aplicação web, sem restrição geográfica declarada, conteúdo em português
- **Canal principal de contato / distribuição:** acesso direto via navegador
- **Responsável:** Murilo Martins Souza e equipe (1-2 colegas)
- **Estágio atual:** **sistema funcionando ponta a ponta, rodando localmente.** Cadastro,
  login, trilhas com progressão sequencial, aulas, exercícios com execução real de código,
  provas de fim de nível, XP, níveis, ofensiva diária, ranking, perfil e administração de
  conteúdo. 3 trilhas publicadas, 15 módulos, 63 atividades, 104 casos de teste.
  **Falta:** publicar online, desbloquear conquistas, cadastrar conteúdo pela tela.

---

## 2. Propósito e posicionamento

### Propósito
Reduzir a taxa de abandono de estudantes iniciantes em programação, organizando o aprendizado
em trilhas claras com progresso visível, em vez de conteúdo teórico fragmentado e desconectado.

### Posicionamento
Inspirado em plataformas de aprendizado gamificado no estilo Duolingo, adaptado ao universo de
programação. Não compete com cursos completos e aprofundados (bootcamps, faculdades) — é uma
porta de entrada prática e motivadora para quem está começando ou tentando retomar os estudos.

### Proposta de valor
Aprender lógica de programação e JavaScript de forma prática, progressiva e gamificada,
acompanhando visivelmente sua própria evolução.

### Mensagens prioritárias
1. Programação não precisa ser cansativa ou abstrata para começar.
2. Seu progresso é visível e mensurável, não uma sensação vaga de "estar estudando".
3. Aprender fazendo, com desafios práticos — não só teoria.

### Limite do posicionamento
DevFox não deve se apresentar como substituto de formação completa em programação, nem
prometer emprego ou certificação profissional. O foco declarado desta fase é lógica de
programação e JavaScript introdutório.

---

## 3. Público

### Público principal
- Pessoas que desejam iniciar seus estudos em programação
- Estudantes com dificuldade em lógica de programação
- Pessoas que já tentaram aprender programação e desistiram
- Usuários com conhecimento básico que querem aprofundar
- Estudantes de cursos técnicos, ensino médio, graduação e cursos livres

### Necessidade que o projeto resolve
Falta de acompanhamento de progresso, conteúdo excessivamente teórico e ausência de motivação
ou estrutura clara para continuar estudando.

### Ocasiões e contexto de uso
Estudo autodidata, fora de sala de aula, a qualquer momento — uso via navegador, sem
dependência de instalação.

### Quem NÃO é o público
Programadores avançados buscando conteúdo aprofundado ou especializado — não é o foco desta fase.

---

## 4. Oferta

| Item | O que é | Observação |
|---|---|---|
| Trilhas de aprendizagem | Sequência progressiva de aulas e desafios sobre lógica de programação e JavaScript | 3 trilhas completas (Lógica, JavaScript Básico, Estruturas de Repetição), cada uma com 4 módulos de aula + prova de fim de nível |
| Desafios práticos | Exercícios de código corrigidos automaticamente | Corrigidos no navegador do usuário, nunca no servidor |
| Gamificação (XP, níveis, ofensiva, ranking) | Mecânica de progresso visível | Implementado. O catálogo de 6 conquistas existe no banco, mas **nada as desbloqueia ainda** |
| Loja, moedas, ranking, PvP, comunidade, perfil personalizável | Mecânicas sociais/competitivas e de personalização | Fase 2 — fora do MVP, não implementar agora |

### Divergências conhecidas a resolver
O documento de apresentação original (seção 7) listava PvP, Loja e Comunidade como
"funcionalidades previstas", enquanto a seção 14 do mesmo documento tratava essas mesmas
funcionalidades como "evolução futura". **Resolvido em 29/08/2026:** tudo isso é fase 2, fora
do MVP.

---

## 5. Operação

### Como funciona na prática
Usuário cria conta (e-mail e senha) → escolhe trilha → lê a explicação do módulo → resolve as
atividades → concluir o módulo libera o próximo → ao fim do nível faz uma prova, e só a
aprovação libera o avanço. Reprovar **não apaga progresso**: o sistema indica quais módulos
revisar. Progresso, XP e ofensiva ficam registrados e visíveis.

> Login com Google estava no plano original e **não foi implementado**. A autenticação é por
> e-mail e senha, com `password_hash()`.

### Prazos, janelas e limites
**Entrega em 1º de dezembro de 2026** (confirmado por Murilo em 19/09/2026).
A **documentação escrita está sob responsabilidade de outro integrante do grupo** — não é
frente de trabalho de Murilo nem das sessões de IA.

### Pagamento / contrapartida
Não se aplica nesta fase — projeto acadêmico, sem modelo de cobrança definido.

### Regras invioláveis de atendimento e entrega
Ver `AGENTS.md` §6.

---

## 6. Diretrizes do produto / serviço

### Padrões de qualidade
Toda tela deve ser aplicação web real e funcional (não imagem estática/mockup). Responsiva,
sem depender de zoom do navegador. Exercícios gerados com apoio de IA precisam de revisão
humana antes de publicados.

### Como algo novo entra na oferta
Qualquer funcionalidade de "fase 2" (loja, PvP, ranking, comunidade) só entra em
desenvolvimento depois do MVP do núcleo de ensino estar pronto e validado — decisão registrada
em 29/08/2026, não deve ser reaberta sem justificativa nova.

---

## 7. Tecnologia e automação

### Em produção
Nada ainda — o sistema roda localmente, via XAMPP, em `C:\xampp\htdocs\DevFox`. Publicar
numa URL pública é tarefa em aberto (DEV-17/18).

### Stack
**PHP 8 + MySQL/MariaDB, procedural, sem framework.** PDO com prepared statements reais
(`ATTR_EMULATE_PREPARES = false`). Front em HTML/CSS/JavaScript puro, sem etapa de build.
Editor de código: CodeMirror 5, servido pelo próprio projeto e não por CDN.

Código em repositório Git privado: https://github.com/martinsmurilo619-ship-it/devfox

### Princípios
- Automação (geração de conteúdo por IA) nunca pode publicar exercício sem revisão humana.
- Correção de código nunca roda no servidor — sempre no navegador do usuário.

---

## 8. Identidade e comunicação

### Identidade visual
Mascote: raposa low-poly em laranja/preto/branco, com duas versões de logo (fundo claro e
fundo escuro) — recebidas de Murilo em 29/08/2026, coerentes com os tokens já usados no CSS do
protótipo (`--bg-dark:#0f1117`, `--primary-orange:#ff6b00`, fonte Poppins). Cor de destaque:
laranja. Base visual: tons escuros. Estilo: moderno, minimalista, tecnológico.

Mockups de referência (29/08/2026) confirmam o padrão de layout: sidebar fixa à esquerda com
ícones + rótulo (Início, Trilhas, Desafios, Ranking, Loja, Perfil, Configurações), barra
superior com XP/sequência/avatar circular com iniciais, cards com barra de progresso, tabela de
ranking, e tela de desafio com editor de código com numeração de linha + painel separado de
"saída esperada". Fonte: mockups enviados por Murilo (referência de produto, não protótipo
funcional).

### Canais
Aplicação web — único canal nesta fase.

### Ritmo e pilares de conteúdo
A DEFINIR — plano editorial das trilhas ainda não fechado.

---

## 9. Personalidade e tom de voz

### Personalidade
A DEFINIR — o documento original especifica identidade visual, mas não tom de voz textual.
Critério de decisão: validar com o time ao escrever os primeiros textos de UI (onboarding,
mensagens de erro, feedback de desafio).

### Tom de voz
A DEFINIR.

### Evitar
A DEFINIR — recomendação a validar: evitar jargão técnico não explicado e tom "corporativo de
curso online genérico", já que o diferencial declarado é soar mais próximo de produto digital
moderno do que de ambiente acadêmico tradicional.

---

## 10. Diferenciais

- Combinação entre ensino de programação e experiência gamificada real, não apenas pontuação decorativa.
- Identidade visual própria (raposa, tema escuro + laranja), mais próxima de produto digital
  moderno do que de ambiente acadêmico tradicional.
- Correção de exercícios no navegador — decisão técnica que também é diferencial de
  simplicidade e segurança (nenhum código de usuário roda no servidor).

---

## 11. Riscos e pontos cegos

### Escopo maior que o prazo permite
O documento original lista 8+ subsistemas independentes (auth, trilhas, Daily Bug, ranking,
PvP, loja, perfil, comunidade). Mitigação aplicada: escopo do MVP cortado para o núcleo de
ensino (decisão de 29/08/2026), e esse núcleo foi entregue. **O risco agora é o oposto:**
reabrir escopo de fase 2 com a entrega marcada para 1º de dezembro.

### Critério de avaliação da banca sobre a stack
O risco original era o inverso do atual: usar Supabase (BaaS) poderia soar como "menos
engenharia própria". Com o pivô para PHP autoral esse risco caiu — mas **continua sem
confirmação do orientador**, pendente desde 29/08/2026 (§13).

### O sistema nunca rodou fora da máquina do desenvolvedor
Até 19/09/2026 o DevFox só foi executado em `localhost`. O backlog prevê ensaiar a
apresentação no ambiente publicado, não na máquina de quem desenvolveu — ver DEV-17/18/20.

### Conteúdo pedagógico gerado por IA sem revisão
Gerar aulas/exercícios com IA e publicar sem revisão manual pode resultar em gabarito errado de
lógica de programação — falha visível e embaraçosa numa banca. Mitigação: revisão humana
obrigatória antes de publicar (ver `AGENTS.md` §6). **Reforço automatizado desde 19/09/2026:**
`tools/validar_conteudo.js` roda a solução de referência de cada exercício contra os próprios
casos de teste e recusa gabarito que não bate. Já pegou um erro real — ver `tools/README.md`.

### Protótipo herdado tinha bugs que bloqueiam o uso — RESOLVIDO
A base PHP+MySQL adotada em 29/08/2026 tinha, na revisão inicial, funções chamadas e nunca
definidas (`usuarioLogado()`, `exibeMensagem()`) que quebram várias páginas com erro fatal,
correção de exercício por substring (facilmente burlável) e cadastro público permitindo
autopromoção a "professor". Mitigação: fase 0 do plano de produção corrige isso antes de
qualquer feature nova — ver `AI_HANDOFF.md`. **Concluído em 29/08/2026:** as três falhas
foram corrigidas e cobertas por testes automatizados (`testes/`).

---

## 12. Prioridades recomendadas
1. Confirmar com o orientador que **PHP autoral sem framework** atende os critérios de
   avaliação. Pendente desde 29/08/2026.
2. Publicar numa URL pública, para o orientador acessar e para ensaiar a apresentação fora
   do `localhost`.
3. Fechar as lacunas visíveis do produto: desbloqueio de conquistas e cadastro de conteúdo
   pela tela de Administração.

---

## 13. Informações ainda não consolidadas
- Confirmação do orientador de que PHP autoral sem framework atende os critérios.
- Tom de voz e personalidade de comunicação do produto.
- Onde hospedar em produção (precisa suportar PHP + MySQL — Vercel não serve mais para essa stack).
- Cronograma detalhado até 1º de dezembro.

---

## 14. Contexto curto para uso em IA

```text
DevFox é uma plataforma web educacional gamificada para aprender lógica de programação e
JavaScript, inspirada em apps como Duolingo. É o TCC de Murilo Martins Souza e 1-2 colegas,
com entrega em 1º de dezembro de 2026. Estágio atual: sistema funcionando ponta a ponta,
rodando localmente; 3 trilhas com 63 atividades; falta publicar online. A documentação escrita
está com outro integrante do grupo.

Tom de voz: ainda não definido formalmente — tratar como acessível, encorajador e direto até
confirmação.

Oferta do MVP: cadastro/login por e-mail e senha, trilhas com progressão sequencial, aulas com
teoria, desafios com execução real do código, provas de fim de nível, XP, níveis, ofensiva
diária, ranking, perfil e administração de conteúdo. Fora do MVP: Daily Bug, PvP, loja/moedas,
comunidade, perfil personalizável — fase 2. O ranking saiu da fase 2: veio pronto do protótipo.

Como a correção funciona (detalhe que a banca costuma perguntar): o código do aluno roda num
Web Worker NO NAVEGADOR, isolado, com timeout de 3s para laço infinito. O navegador recebe só
as ENTRADAS dos casos de teste — as saídas esperadas nunca saem do servidor. Quem compara e
decide é sempre o servidor.

Identidade visual: mascote raposa, tema escuro, laranja como cor de destaque, estilo moderno,
minimalista e tecnológico.

Stack: PHP + MySQL (sem framework), rodando em XAMPP localmente. Decisão de 29/08/2026: um
colega já tinha um protótipo funcional nessa stack cobrindo a maior parte das telas do MVP, e a
equipe optou por adotá-la em vez de reconstruir em React + Supabase (plano anterior,
descartado).

O que a IA NÃO deve inventar: tom de voz definitivo, hospedagem, cronograma — está em
"informações ainda não consolidadas" (§13), trate como A DEFINIR. O schema do banco NÃO é
chute: está documentado em database/README.md e gerado por database/banco.sql.
```

---

## 15. Regra de manutenção deste documento

Ao atualizar:

1. registrar a nova data no início;
2. **substituir** a informação antiga, em vez de manter versões conflitantes lado a lado;
3. distinguir decisão final de teste e de ideia (ideia vai para a §13);
4. conferir todo dado operacional contra a **fonte da verdade** (tabela do topo), nunca contra
   a memória nem contra uma versão anterior deste arquivo;
5. documentar qualquer mudança que afete quem usa, quem produz ou quem comunica;
6. **preservar a seção de riscos**, mesmo quando ela contrariar decisões desejadas.
