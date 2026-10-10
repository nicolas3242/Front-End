# Banco de dados do DevFox

Documentação do schema — o que mudou em cada versão e, principalmente, por quê.
A versão atual é a **v13**.

## Arquivos desta pasta

| Arquivo | Para que serve |
|---|---|
| `banco.sql` | Cria o banco **do zero**, já com dados de exemplo. É o arquivo de referência do schema (v13). O conteúdo das trilhas não é escrito à mão aqui: é gerado por `tools/gerar_banco.py` — ver `tools/README.md`. |
| `migracao_v1_para_v2.sql` | Atualiza um banco v1 **que já tem dados**. Rode uma vez só. |
| `migracao_v2_para_v3.sql` | Atualiza um banco v2 para a v3 (papel admin, provas e progressão). Rode uma vez só, depois da anterior. |
| `migracao_v3_para_v4.sql` | Atualiza um banco v3 para a v4 (tabela `dicas_vistas`). Rode uma vez só, depois da anterior. |
| `migracao_v9_para_v10.sql` | Atualiza um banco v9 para a v10 (área do administrador: colunas `ativo`, `arquivada`, `ativa`, regras de conquista e a tabela `admin_registro`). Rode uma vez só. |
| `migracao_v10_para_v11.sql` | Adiciona Python como linguagem de trilha e ajusta a trilha de Lógica para o executor Pyodide. Rode uma vez só. |
| `migracao_v11_para_v12.sql` | Atualiza bancos existentes da v11 para a v12, adicionando questões complementares com IDs exclusivos. |
| `migracao_v12_para_v13.sql` | Atualiza bancos existentes da v12 para a v13, corrigindo a ordem dos módulos de JavaScript Básico e removendo atividades de HTML/CSS vinculadas incorretamente à trilha JS. Faça backup antes de executar. |
| `migracao_v8_para_v9.sql` | Atualiza um banco v8 para a v9 (só uma tabela nova: `tentativas_login`). Rode uma vez só. |
| `migracao_v7_para_v8.sql` | Atualiza um banco v7 para a v8 (recorde de sequência e código do avatar). Rode uma vez só. |
| `migracao_v6_para_v7.sql` | Atualiza um banco v6 para a v7 (só tabelas novas: lembrar-me, redefinição de senha, suporte). Rode uma vez só. |
| `migracao_v5_para_v6.sql` | Atualiza um banco v5 para a v6 (linguagem da trilha, pré-requisitos e as 5 trilhas novas). Rode uma vez só. Se você criou conteúdo pela Administração, prefira `banco.sql`. |
| `migracao_v4_para_v5.sql` | Atualiza um banco v4 para a v5 (Loja: `itens_loja`, `compras`, item equipado em `usuarios`). Rode uma vez só, depois da anterior. |
| `banco_v11_backup.sql` | Cópia integral do `banco.sql` da versão 11, preservada como backup antes da correção. Não use para criar um banco novo. |

**Se você está começando agora:** importe `banco.sql` no phpMyAdmin e pronto.
**Se já tem um banco antigo com dados:** faça backup e rode as migrações em
ordem, a partir da versão que você tem, terminando em `migracao_v13_para_v14.sql`.

Todos os caminhos foram testados em MariaDB 10.11 (a mesma família que vem no
XAMPP) e chegam ao mesmo schema.

### Contas de teste criadas pelo `banco.sql`

| E-mail | Senha | Tipo |
|---|---|---|
| `admin@devfox.local` | `devfox123` | administrador |
| `aluno@devfox.local` | `aluno123` | aluno |

⚠ Só para desenvolvimento local. Apague ou troque as senhas antes de publicar.

---

## O que mudou, e por quê

### 1. Farm de XP eliminado — a correção mais importante

**O problema:** na v1, `api/processar_desafio.php` somava XP toda vez que a
resposta estava certa, e nada impedia o aluno de enviar a mesma atividade de
novo. Bastava acertar e apertar F5 repetidamente para subir no ranking sem
limite. Com o ranking na tela principal, isso invalidava a gamificação inteira.

**A solução:** o histórico de tentativas e o registro de conclusão passaram a
ser duas coisas separadas.

- `submissoes` — guarda **todas** as tentativas, inclusive os erros. Serve para
  o professor acompanhar dificuldade e para a análise de desempenho no TCC.
- `atividades_concluidas` — guarda **uma linha por (aluno, atividade)**,
  garantida por uma chave única. É ela que concede XP.

No PHP, o padrão a seguir é:

```php
$stmt = $pdo->prepare(
    "INSERT IGNORE INTO atividades_concluidas (aluno_id, atividade_id, xp_concedido)
     SELECT ?, id, xp_recompensa FROM atividades WHERE id = ?"
);
$stmt->execute([$aluno_id, $atividade_id]);

// Só soma XP se a linha foi realmente inserida (primeira conclusão).
if ($stmt->rowCount() === 1) {
    $pdo->prepare("UPDATE usuarios SET xp = xp + ? WHERE id = ?")
        ->execute([$xp, $aluno_id]);
}
```

Na migração, o XP de todo mundo é recalculado a partir das conclusões reais.
No teste que fizemos, um aluno com 3050 XP obtidos por reenvio voltou para os
350 XP que realmente conquistou — sem afetar quem tinha jogado limpo.

### 2. Aulas e atividades agora têm ordem

`modulos` e `atividades` ganharam a coluna `ordem`. Uma trilha é, por
definição, uma sequência progressiva — sem essa coluna não havia como dizer
qual aula vem antes de qual, nem calcular o "Módulo 4 de 7" que aparece nos
mockups.

Sempre consulte com `ORDER BY ordem, id`. O `id` serve de desempate caso duas
aulas acabem com a mesma ordem.

### 3. A resposta esperada saiu de `atividades`

Antes, cada atividade tinha uma única coluna `saida_esperada`, comparada com
`strpos()` — ou seja, bastava a resposta certa aparecer em qualquer lugar do
código enviado, até dentro de um comentário, para o exercício passar.

Agora:

- **Exercícios de código** usam `casos_teste` — vários casos por atividade,
  cada um com entrada e saída esperada.
- **Quiz** usa `alternativas` — as opções vêm do banco, e não mais chumbadas
  no código como estavam em `atividade.php`.

O campo `casos_teste.visivel` é o detalhe que fecha a brecha: casos com
`visivel = 1` aparecem na tela como "Saída esperada", e casos com `visivel = 0`
ficam ocultos. Assim o aluno não consegue passar chumbando o resultado que ele
vê na tela — ele precisa resolver o problema de verdade.

### 4. Ofensiva (sequência de dias) agora é calculável

`sequencia_dias` existia, mas não havia nenhuma data para comparar — era um
número parado no banco. Foi adicionada a coluna `ultimo_dia_ativo`. A regra,
a ser implementada no login:

- `ultimo_dia_ativo` é ontem → `sequencia_dias + 1`
- `ultimo_dia_ativo` é hoje → não faz nada
- qualquer outro caso → `sequencia_dias = 1`

### 5. `usuarios.nivel` foi removido

A coluna existia mas nunca era atualizada, enquanto `perfil.php` calculava o
nível na hora a partir do XP. Duas fontes para a mesma verdade sempre acabam
divergindo. O nível passa a ser **sempre** derivado:

```php
function calcularNivel(int $xp): int {
    return intdiv($xp, 500) + 1;
}
```

Mantivemos a fórmula linear (500 XP por nível) que já estava no código. Uma
curva progressiva, em que cada nível custa mais que o anterior, combina melhor
com o mockup — mas é um refinamento cosmético, e o prazo não justifica mexer
nisso agora.

### 6. Conquistas

As tabelas `conquistas` e `conquistas_usuarios` já existem e o catálogo já
está preenchido com 6 conquistas. **A lógica que desbloqueia cada uma ainda
não foi implementada** — a decisão foi deixar o schema pronto para não ter que
alterar o banco no meio do caminho, e implementar a lógica só se sobrar tempo.

### 7. Outros ajustes

- Valores de ENUM viraram códigos em minúsculo e sem acento (`intermediario`
  em vez de `Intermediário`). O rótulo bonito é responsabilidade do PHP na
  hora de exibir. Guardar texto acentuado como identificador é frágil.
- `trilhas.publicada` permite ao professor deixar uma trilha em rascunho.
- `atividades.dica` e `atividades.solucao_exemplo` alimentam os botões "Usar
  dica" e "Mostrar exemplo" dos mockups.
- Índice em `usuarios.xp`, usado pelo ranking.
- `status ENUM(...) DEFAULT 'correto'` foi substituído por `correta TINYINT`
  com default `0`. O default anterior era perigoso: um INSERT que esquecesse o
  campo gravava "correto" silenciosamente.
- Timestamps de criação/atualização em todas as tabelas principais.

---

## Arquivos PHP afetados pelas mudanças de schema

Todos já foram ajustados e testados. A lista fica registrada porque explica o
que mudou e por quê:

| Arquivo | O que mudou |
|---|---|
| `api/processar_desafio.php` | Reescrito para validar contra `casos_teste` e conceder XP por `atividades_concluidas`. Na v3 passou a receber as saídas produzidas pelo Web Worker. |
| `administracao.php` | Era `professor.php`. Insere os códigos `iniciante`/`intermediario`/`avancado` e usa `exigirAdmin()`. |
| `cadastro.php` | Não aceita mais `tipo` vindo do formulário: cria somente alunos. |
| `trilhas.php`, `desafio.php` | Passaram a respeitar a progressão sequencial (`estadoDosModulos()`). |
| `prova.php` | Novo na v3: avaliação de fim de nível. |

O antigo `atividade.php` foi aposentado — trazia uma pergunta fixa no código,
sem ligação com o banco, e somava XP sem controle de repetição.

---

## Diagrama das relações

```
usuarios ─┬─< submissoes ────────────┐
          ├─< atividades_concluidas ─┤
          ├─< modulos_concluidos ──┐ │
          ├─< tentativas_prova ────┤ │
          ├─< dicas_vistas ────────┼─┤
          └─< conquistas_usuarios >─┼─┼── conquistas
                                    │ │
trilhas ──< modulos ────────────────┘ │
              └──< atividades ────────┴──┬─< casos_teste
                    │                    └─< alternativas
                    └── modulo_referencia_id ──> modulos
                        (só em questões de prova)
```

## ⚠ Se os acentos aparecerem errados no site ("LÃ³gica")

Os dois scripts começam com `SET NAMES utf8mb4;`. Sem essa linha, um cliente
MySQL configurado em latin1 — o padrão de várias instalações de linha de
comando — interpreta cada byte acentuado como um caractere separado e grava
tudo duplamente codificado. A importação não acusa erro nenhum: o problema só
aparece depois, na tela.

Para conferir se o banco de vocês está correto:

```sql
SELECT HEX(titulo) FROM trilhas WHERE titulo LIKE 'L%gica%';
```

O "ó" deve aparecer como `C3B3`. Se aparecer `C383C2B3`, os dados estão
duplamente codificados — nesse caso, o caminho mais simples é reimportar o
`banco.sql` do zero, já que ele agora traz a declaração de charset.

A migração **não corrige** acentos que já estejam errados: não há como
distinguir com segurança um dado corrompido de um dado correto, e um "conserto"
automático arriscaria estragar dados que estavam certos.

## Observação sobre a migração

Nos bancos migrados, os nomes das chaves estrangeiras ficam como o MySQL as
gerou na v1 (`atividades_ibfk_1`), enquanto num banco criado do zero eles são
nomes explícitos (`fk_atividades_modulo`). É só diferença de nome: a estrutura
e o comportamento são idênticos. Renomear chaves estrangeiras na migração seria
frágil, porque o nome automático depende da ordem em que as tabelas foram
criadas em cada máquina.


---

## O que mudou na v10 (22/09/2026)

Tudo para a área do administrador (`docs/REQUISITOS_ADMIN.md`). A regra de
fundo é a mesma de sempre: **nada que tenha progresso de aluno é apagado** —
por isso quase tudo aqui é uma coluna de "ativo/arquivado", não um DELETE.

| Mudança | Para quê |
|---|---|
| `usuarios.ativo` (1/0) | Desativar uma conta sem apagar nada. `exigirLogin()` relê essa coluna (e o `tipo`) a cada página, então a conta desativada — ou o admin rebaixado — perde o acesso na hora, não só no próximo login. |
| `trilhas.arquivada` (1/0) | Tirar da lista do admin uma trilha que alunos já usaram. Arquivar também despublica. Apagar de verdade só é permitido se ninguém tiver progresso nela. |
| `conquistas.ativa` (1/0) | Desativar uma conquista: ela para de ser concedida e some para quem não tem; quem já ganhou continua vendo. |
| `conquistas.regra_tipo`, `regra_valor`, `regra_alvo` | Conquista criada pela tela. `regra_tipo` vem de um **catálogo fechado** (`tiposDeRegra()` em `includes/conquistas.php`: atividades, desafios, xp, sequencia, trilhas, sem_dica, trilha, prova). As conquistas originais ficam com `'fixa'` e continuam com a regra em PHP. O admin nunca escreve código ou SQL. |
| Tabela `admin_registro` | Quem fez o quê e quando (`admin_id`, `acao`, `alvo`, `criado_em`). `admin_id` é `ON DELETE SET NULL`: excluir a conta de um admin não apaga o histórico dele. Só leitura pela tela. |

Conferido: importar o `banco.sql` v9 e rodar `migracao_v9_para_v10.sql` dá o
mesmo schema que importar o `banco.sql` v10 do zero.

## O que mudou na v9 (22/09/2026)

### A tabela `tentativas_login`

Uma linha por **senha errada** (e-mail digitado, IP, horário). Com 5 erros no
mesmo e-mail em 15 minutos, o login desse e-mail fica bloqueado até a janela
passar — força bruta deixa de ser viável. O bloqueio vale também para e-mails
que não existem; senão o próprio bloqueio revelaria quem tem conta.

Há um teto por IP, mas **alto** (50 em 15 minutos) de propósito: numa escola,
uma sala inteira sai para a internet pelo mesmo IP, e um teto baixo trancaria a
turma toda por causa de um aluno.

Sem chave estrangeira: o e-mail pode nem existir. Linhas com mais de 1 dia são
apagadas no próprio login, então a tabela não cresce sem limite. Login certo
apaga as linhas daquele e-mail.

### Fuso horário (sem mudança de schema)

`includes/ambiente.php` fixa o PHP em `America/Sao_Paulo` e, a cada conexão,
roda `SET time_zone = '-03:00'` no MySQL. As colunas `TIMESTAMP` são
convertidas para esse fuso ao serem gravadas e lidas, então `NOW()`,
`CURDATE()` e o `date()` do PHP passam a concordar. Antes, com o php.ini do
XAMPP em `Europe/Berlin`, o "hoje" da sequência de dias virava às 19h/20h.

## O que mudou na v8 (22/09/2026)

- **`usuarios.melhor_sequencia`** — o recorde de dias seguidos, mostrado no Perfil
  ("Melhor: 12 dias"). Atualizado em `atualizarOfensiva()` com `GREATEST()`. Guardar
  o recorde é necessário porque a sequência atual zera: não dá para derivá-lo depois.
- **`usuarios.foto_avatar`** passa a guardar o **código** do avatar (`padrao`,
  `retrato`...), sempre conferido contra o catálogo em `includes/avatares.php`. Não há
  upload de imagem — o aluno escolhe de uma lista fixa.

## O que mudou na v7 (22/09/2026)

Três tabelas novas, nenhuma alteração nas existentes:

- **`lembrar_tokens`** — "Lembrar-me". Padrão seletor + validador: o cookie leva
  `seletor:validador`, o banco guarda o seletor e só o **hash** do validador. Cada
  uso troca o token (rotação); um validador errado para um seletor existente
  apaga todos os tokens da conta; sair e trocar a senha também apagam.
- **`redefinicoes_senha`** — "Esqueci minha senha". Só o hash do token, validade
  de 1 hora, uso único; um pedido novo invalida os anteriores.
- **`mensagens_suporte`** — "Fale com o suporte". Formulário público; a equipe lê
  em Administração → Suporte.

## O que mudou na v6 (21/09/2026)

### `trilhas.linguagem`

Diz QUAL executor corrige os exercícios da trilha: `javascript` (Web Worker, como
sempre) ou `html` (página num iframe isolado, com verificações — ver
`assets/js/executor-html.js`). É da trilha, e não da atividade, porque uma
trilha mistura as duas coisas raramente e o editor, a prévia e a correção mudam
juntos. A Administração não deixa trocar a linguagem de trilha que já tem
atividades: os casos de teste das duas linguagens têm formatos diferentes.

### `trilha_requisitos`

Trilha que só abre depois de outras concluídas (o Projeto Final exige as outras
sete). Tabela à parte porque uma trilha pode ter vários requisitos. A regra é
aplicada em `estadoDosModulos()`, então vale também para quem tenta abrir pela URL.

### `trilhas.subtitulo` e `trilhas.destaque`

Alimentam o painel lateral da tela de Trilhas ("HTML e CSS").

### Conteúdo

De 3 para 8 trilhas (159 atividades, 297 casos de teste). As três antigas
mantiveram os ids; a ordem de exibição segue o wireframe pelo campo `ordem`.

## O que mudou na v5 (21/09/2026)

### A Loja: `itens_loja` e `compras`

`itens_loja` é o catálogo — só itens cosméticos (moldura do avatar e título sob o nome).
Nada que a loja venda dá vantagem nas atividades: se desse, o ranking passaria a medir quem
gastou moeda, e não quem aprendeu. Item que sai de venda é **desativado** (`ativo = 0`), nunca
apagado, e a FK de `compras` é `RESTRICT` justamente para impedir a exclusão acidental.

`compras` registra quem comprou o quê. A chave única `(usuario_id, item_id)` impede comprar o
mesmo item duas vezes, no mesmo padrão `INSERT IGNORE` + `rowCount()` do XP e das conquistas.

### Por que não existe coluna de moedas

O saldo é **derivado**: `xp DIV 10 − SUM(compras.preco_pago)`. Uma coluna `moedas` seria uma
segunda fonte da verdade — o mesmo erro evitado com `usuarios.nivel` na v2. Assim, a única
forma de ganhar moeda é ganhar XP, que já é protegido contra farm. `preco_pago` guarda o preço
do momento da compra, para que mudar o preço depois não altere o saldo de quem já comprou.

### Compra atômica

Conferir o saldo e gravar a compra em dois passos soltos permite que duas requisições
simultâneas leiam o mesmo saldo e as duas passem. `comprarItem()` (em `includes/loja.php`)
roda numa transação que começa com `SELECT ... FOR UPDATE` na linha do usuário: a segunda
compra espera a primeira terminar e já enxerga o saldo novo.

### Item equipado

`usuarios.moldura_id` e `usuarios.titulo_id` apontam para `itens_loja` com `ON DELETE SET NULL`.
As FKs são criadas no fim do schema, por `ALTER TABLE`, porque `itens_loja` precisa existir antes.

## O que mudou na v4 (19/09/2026)

### A tabela `dicas_vistas`

Uma tabela só, e ela existe por causa de uma conquista: **"Por Conta Própria"**,
que pede 5 desafios acertados sem usar dica. Sem registrar quem pediu ajuda, não
há como saber quem não pediu.

O detalhe que importa é ONDE o registro acontece. A dica deixou de vir no HTML da
tela de desafio: ela é buscada em `api/dica.php` quando o aluno abre o bloco, e é
esse endpoint que grava a linha. A alternativa óbvia — um campo `usou_dica`
enviado junto com a resposta — seria editável pelo aluno no DevTools, e a
conquista viraria enfeite.

É a mesma regra que vale para o XP no resto do sistema: **nada que o navegador
informe decide alguma coisa sozinho.**

```sql
CREATE TABLE dicas_vistas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id     INT NOT NULL,
    atividade_id INT NOT NULL,
    vista_em     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dicas_aluno_atividade (aluno_id, atividade_id)
);
```

A chave única faz o trabalho de sempre: abrir a mesma dica dez vezes gera uma
linha só.

> Quem já tinha concluído atividades antes desta migração conta como "não usou
> dica" nelas, porque não existe registro em contrário. É o comportamento
> desejado — ninguém perde conquista por causa de uma mudança de schema.

As regras de desbloqueio das 6 conquistas ficam em `includes/conquistas.php`, e
são todas avaliadas no servidor, consultando o banco.

---

## O que mudou na v3 (11/09/2026)

### O papel "professor" virou "admin"

O DevFox é uma **plataforma de conteúdo**, como Duolingo e Alura: quem cadastra
trilhas e atividades é a equipe do produto. Não existe professor que se cadastra
na plataforma para criar conteúdo próprio — isso seria um sistema de sala de
aula, que é outro produto. O nome antigo induzia a esse modelo errado.

### Módulos têm tipo e nota mínima

`modulos.tipo` é `aula` ou `prova`. Um módulo de aula é a explicação (teoria
e/ou vídeo) seguida das atividades; um módulo de prova agrupa as questões que
avaliam os módulos anteriores e tem `nota_minima` (padrão 70%).

### Progressão sequencial

Cada módulo só libera quando o anterior é concluído, e a prova precisa ser
aprovada para o avanço continuar. A lógica está em `estadoDosModulos()`
(`includes/auth.php`) e usa a tabela `modulos_concluidos`, que já existia.

Um módulo de aula conta como concluído quando todas as suas atividades foram
feitas, ou quando marcado explicitamente — o que é necessário para módulos só
de teoria, sem exercício. Um módulo de prova só conta quando aprovado.

### Reprovar não apaga progresso

`atividades.modulo_referencia_id` diz qual módulo cada questão de prova avalia.
É isso que permite ao sistema indicar ao aluno reprovado **exatamente o que
revisar**, em vez de mandá-lo refazer o nível inteiro.

Essa escolha é deliberada: o DevFox existe para reduzir a desistência de
iniciantes, e obrigar alguém a recomeçar tudo por causa de uma prova é o
caminho mais curto para provocar justamente essa desistência.

### Histórico de tentativas

`tentativas_prova` guarda cada tentativa com acertos, percentual e resultado.
Diferente de `atividades_concluidas`, aqui pode haver várias linhas por
(aluno, módulo): é o histórico que mostra a evolução.

### XP da prova

Concedido apenas pelas questões acertadas, uma única vez cada — a chave única
de `atividades_concluidas` cuida disso. Quem repete a prova pode conquistar o
XP das questões que ainda faltavam, e refazer uma prova já aprovada não rende
XP nenhum.


### v11 — Executor Python

A coluna `trilhas.linguagem` agora aceita `python`, além de `javascript` e `html`. A trilha de Lógica usa Pyodide no navegador. Em bancos existentes, rode `migracao_v10_para_v11.sql`; essa migração altera o executor configurado, mas não recria atividades antigas.


### v12 — Questões complementares e IDs exclusivos

As questões complementares foram renumeradas para IDs exclusivos (1000–1035), evitando colisões com atividades já existentes. O arquivo `banco.sql` continua sendo o único arquivo necessário para criar um banco novo do zero e inclui as questões da v12. Para quem já possui um banco v11 com dados, use somente `migracao_v11_para_v12.sql` após fazer backup; não é necessário executar outro arquivo de atualização de questões. A migração usa inserções idempotentes para evitar erros quando as questões já existem. A versão anterior completa está preservada em `banco_v11_backup.sql`.


### v13 — Ajustes da trilha JavaScript Básico

A migração `migracao_v12_para_v13.sql` aplica aos bancos existentes os ajustes de ordem dos módulos e remove atividades de HTML/CSS que estavam vinculadas incorretamente à trilha JavaScript. Não é necessário criar outro banco: faça backup e aplique a migração sobre o banco v12 existente.


### v14 — Limpeza da trilha JavaScript

A migração `migracao_v13_para_v14.sql` remove apenas as atividades de HTML/CSS
que estavam vinculadas incorretamente à trilha JavaScript Básico. Não cria banco
novo e não altera os demais módulos ou trilhas. Para bancos na v13, faça backup
e execute essa migração uma única vez.
