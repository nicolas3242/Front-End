# DevFox: requisitos do Administrador

**Versão:** 22/09/2026. É um levantamento feito a partir do código atual (schema v10). Atualizado depois da implementação da área do administrador.

**Para que serve:** definir o que o administrador precisa ver, criar, editar e remover no DevFox. Também mostra o que já existe e o que falta, em que ordem fazer, e o que o administrador **não pode** fazer.

## 1. Quem é o administrador

- **Quem é:** a equipe que mantém o conteúdo e acompanha os alunos. O DevFox é uma plataforma de conteúdo, como Duolingo e Alura, não um sistema de sala de aula. Por isso não existe "professor que se cadastra": o papel se chama `admin` (`usuarios.tipo`).
- **Como uma conta vira admin:** pela ficha do aluno (Administração → Alunos → Ver ficha → Promover a admin). A primeira conta admin vem do `banco.sql`.
- **Regras que valem para todas as telas de administração:**
  - A permissão é conferida **no servidor**, em toda página e em toda ação (`exigirAdmin()`). Esconder o menu não é controle de acesso.
  - Toda ação que altera dados é um POST com token CSRF, no padrão Post/Redirect/Get: F5 não reenvia o formulário.
  - **O progresso do aluno nunca some por causa de uma edição de conteúdo.** Só pode ser apagado o que nenhum aluno usou. O resto se **despublica** ou se **desativa**.

**Legenda:**
- **Situação:** ✅ já existe · 🟡 existe em parte · ❌ falta
- **Prioridade:** **P1** antes da banca · **P2** desejável · **P3** futuro

## 2. Acesso e segurança

| ID | Requisito | Situação | Prior. |
|---|---|---|---|
| ADM-01 | Só o admin abre `administracao.php` e as páginas `admin_*`; o aluno é mandado para o Início | ✅ | — |
| ADM-02 | CSRF em todo POST de administração | ✅ | — |
| ADM-03 | Admin consegue abrir trilha em rascunho como se fosse aluno, para conferir antes de publicar | ✅ botão "Ver como aluno" (olho) em cada módulo; o admin abre rascunho e vê a solução | — |
| ADM-04 | Promover um aluno a admin e rebaixar um admin, pela tela, sem editar o banco | ✅ na ficha do aluno (`admin_aluno.php`); ninguém rebaixa a própria conta | — |
| ADM-05 | Registro das ações do admin: quem publicou, editou ou apagou o quê, e quando | ✅ tabela `admin_registro` + aba Registro (só leitura) | — |

## 3. Painel inicial (visão geral)

| ID | Requisito | Situação | Prior. |
|---|---|---|---|
| ADM-10 | Lista das trilhas, com estado (publicada ou rascunho), número de módulos e de atividades | ✅ | — |
| ADM-11 | Cartão do Suporte com a contagem de mensagens não lidas | ✅ | — |
| ADM-12 | Números gerais: total de alunos, alunos ativos nos últimos 7 dias, atividades concluídas na semana, taxa de acerto geral | ✅ painel mostra alunos, ativos em 7 dias, novos em 7 dias, concluídas na semana e taxa de acerto (30 dias) | — |
| ADM-13 | Alerta de conteúdo com problema: módulo vazio, prova sem questões, trilha publicada sem atividades | ✅ alertas no painel | — |
| ADM-14 | **Painel inicial completo:** saudação com a data, 6 cartões com variação em 30 dias, gráfico de linhas (cadastros × ativos nos últimos 7 dias), rosca da distribuição por nível, ações rápidas, últimas atividades, desafios em destaque e ranking geral | ✅ tudo lido do banco; nenhum número de exemplo | — |
| ADM-15 | **Busca única** por aluno, trilha, módulo e atividade (`admin_busca.php`) | ✅ | — |

## 4. Trilhas

| ID | Requisito | Situação | Prior. |
|---|---|---|---|
| ADM-20 | Criar trilha. Ela nasce como rascunho | ✅ | — |
| ADM-21 | Editar título, descrição, dificuldade, ícone, subtítulo e destaque | ✅ | — |
| ADM-22 | Linguagem (JavaScript ou HTML/CSS), travada quando a trilha já tem atividades | ✅ | — |
| ADM-23 | Pré-requisitos (outras trilhas que precisam estar concluídas), recusando ciclo | ✅ | — |
| ADM-24 | Publicar e despublicar | ✅ | — |
| ADM-25 | Mudar a ordem das trilhas na tela do aluno | ✅ subir e descer no painel | — |
| ADM-26 | Arquivar uma trilha (tirar da lista do admin sem apagar o progresso). Apagar só quando ninguém a usou | ✅ arquivar/desarquivar; apagar só sem progresso | — |
| ADM-27 | Duplicar uma trilha como base para outra | ❌ | P3 |

## 5. Módulos (aulas e provas)

| ID | Requisito | Situação | Prior. |
|---|---|---|---|
| ADM-30 | Criar módulo de aula ou de prova; na prova, definir a nota mínima | ✅ | — |
| ADM-31 | Editar teoria (texto) e vídeo da aula | ✅ | — |
| ADM-32 | Reordenar módulos (subir e descer) | ✅ | — |
| ADM-33 | Apagar módulo só se nenhum aluno tiver progresso nele | ✅ | — |
| ADM-34 | Validar a URL do vídeo: só YouTube, convertido para o endereço de incorporação | ✅ feito em 22/09. Antes aceitava qualquer texto, inclusive `javascript:`, e ele ia para `<iframe src>` | — |
| ADM-35 | Mostrar "vazio" no módulo sem atividades e na prova sem questões | ✅ selo "vazio" no módulo | — |

## 6. Atividades e desafios

| ID | Requisito | Situação | Prior. |
|---|---|---|---|
| ADM-40 | Criar e editar os 4 tipos: quiz, completar, corrigir erro e código livre | ✅ | — |
| ADM-41 | Quiz: alternativas, com exatamente uma correta | ✅ | — |
| ADM-42 | Código: casos de teste visíveis e ocultos | ✅ | — |
| ADM-43 | HTML/CSS: verificações (`existe`, `contar`, `texto`, `atributo`, `estilo`) | ✅ | — |
| ADM-44 | "Conferir gabarito": a solução de exemplo precisa passar nos casos antes de salvar | ✅ | — |
| ADM-45 | Dica, solução de exemplo, XP e dificuldade | ✅ | — |
| ADM-46 | Na prova, cada questão aponta o módulo que ela avalia (para o aluno saber o que revisar) | ✅ | — |
| ADM-47 | Reordenar e apagar, apagando só o que não tem progresso | ✅ | — |
| ADM-48 | Estatística da atividade: tentativas, % de acerto na 1ª tentativa, alunos que desistiram | ✅ painel de estatísticas na edição da atividade | — |
| ADM-49 | Duplicar uma atividade | ❌ | P3 |
| ADM-50 | Ao editar um quiz, manter os ids das alternativas (hoje elas são recriadas, e uma prova aberta nesse momento é corrigida errado) | ✅ alternativas reaproveitadas por posição | — |

## 7. Provas

| ID | Requisito | Situação | Prior. |
|---|---|---|---|
| ADM-55 | Questões da prova cadastradas como atividades do módulo de prova | ✅ | — |
| ADM-56 | Embaralhamento e espera depois de reprovar | ✅ automáticos (a espera é a constante `PROVA_ESPERA_MINUTOS`, 5 min) | — |
| ADM-57 | Ver as tentativas de prova de cada aluno (nota e data) | ✅ na ficha do aluno | — |

## 8. Conquistas

**Situação atual:** o catálogo, com as 6 conquistas, está no banco (`conquistas`: código, título, descrição, ícone e raridade). A **regra** de cada uma, porém, está escrita no PHP (`includes/conquistas.php`), presa ao código da conquista. Por isso hoje o admin não cria conquista nova sem mexer no código.

| ID | Requisito | Situação | Prior. |
|---|---|---|---|
| ADM-60 | Listar as conquistas, com quantos alunos desbloquearam cada uma | ✅ `admin_conquistas.php` | — |
| ADM-61 | Editar título, descrição, ícone e raridade (bronze, prata, ouro, especial) | ✅ | — |
| ADM-62 | Ativar e desativar uma conquista. Quem já ganhou continua com ela | ✅ coluna `ativa` | — |
| ADM-63 | **Criar conquista nova pela tela**, escolhendo um **tipo de regra** e um **valor**. Tipos de regra: concluir N atividades; concluir N desafios de código; atingir N de XP; sequência de N dias; concluir a trilha X; concluir N trilhas; acertar N sem usar dica; ser aprovado na prova X com nota ≥ N | ✅ catálogo fechado de 8 tipos de regra (`tiposDeRegra()`) | — |

As regras são um catálogo fechado, e não código livre, de propósito: deixar o admin escrever SQL ou PHP numa caixa de texto seria uma falha de segurança grave.

## 9. Loja

**Situação atual:** a tabela `itens_loja` já tem nome, descrição, categoria (moldura ou título), preço, ordem e o campo `ativo`, mas não há tela de administração. As molduras dependem de uma classe CSS (`slug`), então uma moldura **nova** também precisa de CSS. Título novo não precisa.

| ID | Requisito | Situação | Prior. |
|---|---|---|---|
| ADM-70 | Listar itens, com quantas vezes cada um foi comprado | ✅ `admin_loja.php` | — |
| ADM-71 | Criar e editar título (nome, descrição, preço) | ✅ | — |
| ADM-74 | **Criar moldura pela tela**, escolhendo a aparência num catálogo fechado de estilos CSS (`estilosDeMoldura()`) | ✅ 13 aparências; estilo já usado sai da lista | — |
| ADM-72 | Editar preço e descrição das molduras existentes | ✅ | — |
| ADM-73 | Desativar item: sai da vitrine, mas quem comprou continua com ele. Nunca apagar item já comprado | ✅ | — |

## 10. Alunos (usuários)

| ID | Requisito | Situação | Prior. |
|---|---|---|---|
| ADM-75 | Listar e buscar alunos por nome ou e-mail, com XP, nível, sequência, última atividade e data de cadastro | ✅ `admin_alunos.php` | — |
| ADM-76 | Ficha do aluno: trilhas e % de cada uma, módulos concluídos, tentativas de prova, conquistas e últimas respostas | ✅ `admin_aluno.php` | — |
| ADM-77 | Desativar ou reativar conta (o aluno não entra mais, mas nada é apagado) | ✅ coluna `ativo`; a sessão cai na hora | — |
| ADM-78 | Enviar link de redefinição de senha a pedido do aluno | ✅ | — |
| ADM-79 | Excluir a conta (LGPD), feito pelo próprio aluno em Configurações ou pelo admin a pedido | ✅ em Configurações (com senha) e na ficha (digitando o e-mail) | — |

## 11. Relatórios (o que a banca vai perguntar)

A tabela `submissoes` guarda **todas** as tentativas: resposta, se estava correta e quando. Nenhuma tela lê essa tabela. É ela que transforma o DevFox de "jogo" em "plataforma de ensino".

| ID | Requisito | Situação | Prior. |
|---|---|---|---|
| ADM-80 | **Atividades mais difíceis:** ranking por taxa de erro (tentativas erradas ÷ tentativas), com link para editar a atividade | ✅ `admin_relatorios.php` (mín. 3 tentativas) | — |
| ADM-81 | **Funil por trilha:** quantos alunos começaram, chegaram à prova, foram aprovados e concluíram | ✅ | — |
| ADM-82 | **Progresso por aluno:** tabela aluno × trilha com % concluído | ✅ mapa de calor | — |
| ADM-83 | Uso de dica por atividade (tabela `dicas_vistas`) | ✅ | — |
| ADM-84 | Exportar os relatórios em CSV (abre no Excel, útil para o capítulo de resultados do TCC) | ✅ 4 relatórios em CSV (UTF-8, `;`) | — |
| ADM-85 | Filtro por período (7 dias, 30 dias, tudo) | ✅ | — |

## 12. Suporte

| ID | Requisito | Situação | Prior. |
|---|---|---|---|
| ADM-90 | Caixa de mensagens, com marcar como lida e não lida | ✅ | — |
| ADM-91 | Filtrar por assunto e por lida ou não lida | ✅ | — |
| ADM-92 | Responder pela tela. Depende de envio de e-mail; hoje `EMAIL_MODO='log'` | 🟡 o botão "Responder por e-mail" abre o programa de e-mail (mailto); envio pela tela depende de SMTP | P3 |

## 13. O que o administrador NÃO pode fazer (de propósito)

| Proibido | Por quê |
|---|---|
| Ver a senha de alguém | O banco só guarda o hash (bcrypt). Nem o admin consegue recuperar a senha, só gerar um link de redefinição |
| Dar ou tirar XP e moedas "na mão" | O XP vem de atividades concluídas (`atividades_concluidas`) e as moedas são calculadas a partir do XP. Um ajuste manual quebraria essa rastreabilidade e o ranking. Se um dia for preciso, criar um lançamento de ajuste registrado com motivo, nunca um UPDATE direto |
| Apagar trilha, módulo ou atividade que algum aluno já usou | Levaria junto o progresso e o XP de quem fez. O certo é despublicar ou desativar |
| Apagar item da loja já comprado ou conquista já desbloqueada | Mesmo motivo: desativar, nunca apagar |
| Escrever regra de conquista como código ou SQL | Seria injeção de código pela própria tela. As regras são um catálogo fechado (ADM-63) |
| Trocar a linguagem de uma trilha que já tem atividades | Os casos de teste de JavaScript e de HTML têm formatos diferentes, e todos os exercícios quebrariam |
| Ver a sessão ou o "Lembrar-me" de um aluno | Os tokens ficam só em hash |

## 14. Situação em 22/09 e o que falta

Tudo que era P1 e P2 está feito e coberto por `testes/teste-admin-gestao.js`. Ficou de fora, de propósito:

- **ADM-27 e ADM-49 (duplicar trilha e atividade), P3.** Não aparecem na defesa e copiar trilha envolve pré-requisitos e casos de teste; fica para depois da entrega.
- **ADM-92 (responder pela tela), P3.** Precisa de SMTP em produção. Hoje o admin responde pelo próprio e-mail.
- **Aparência de moldura fora do catálogo.** O admin cria molduras pela tela (ADM-74), mas escolhendo entre as 13 aparências prontas. Uma aparência nova exige uma regra `.moldura-<slug>` no CSS e uma linha em `estilosDeMoldura()` — de propósito: deixar o admin escrever CSS pela tela seria abrir a porta para injeção de estilo e script.
