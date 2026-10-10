# Como ligar e testar o DevFox

Guia de bolso: ligar o projeto na sua máquina, conferir se está tudo de pé e
rodar os testes automáticos. Se algum passo falhar, a última seção lista os
erros mais comuns com a causa de cada um.

**Estado atual:** schema **v10**, 8 trilhas, 37 módulos, 159 atividades,
6 conquistas e 9 itens de Loja no `banco.sql`.

---

## Parte 1 — Ligar o projeto

### 1. A pasta precisa estar dentro do `htdocs`

O Apache só serve o que está lá dentro:

```
C:\xampp\htdocs\DevFox\
```

Duas regras que já custaram tempo neste projeto:

- **Uma cópia só.** Duas pastas (uma em `Downloads`, outra no `htdocs`) sempre
  acabam divergindo, e um dia você edita uma e publica a outra.
- **Nunca esvazie a pasta para "extrair por cima".** Quem faz isso apaga
  `config/`, `assets/vendor/` e a `.git` junto — foi exatamente o que
  aconteceu aqui uma vez. Extrair por cima, substituindo, basta.

### 2. Ligue o XAMPP

No **XAMPP Control Panel**, dê **Start** em **Apache** e em **MySQL**. Os dois
precisam ficar verdes. Se o Apache não subir, quase sempre é porta 80 ocupada.

### 3. Crie o arquivo de conexão (uma vez só)

O `config/conexao.php` não vai para o Git, porque é ele que guarda as
credenciais. Se não existir, crie a partir do modelo:

```
copy C:\xampp\htdocs\DevFox\config\conexao.exemplo.php C:\xampp\htdocs\DevFox\config\conexao.php
```

Ele já vem com o padrão do XAMPP (usuário `root`, sem senha). Se o seu MySQL
tiver senha, abra o arquivo e preencha a linha `$pass`.

### 4. Importe o banco

```
"C:\xampp\mysql\bin\mysql.exe" -u root < "C:\xampp\htdocs\DevFox\database\banco.sql"
```

O comando não escreve nada quando dá certo — silêncio é sucesso. Se aparecer
`ERROR`, leia a mensagem: costuma ser MySQL desligado ou caminho errado.

> Pelo phpMyAdmin também funciona (aba **Importar** → `database/banco.sql`),
> mas o comando acima é mais rápido e não estoura o limite de upload.

> **Já tem dados que quer manter?** Não importe o `banco.sql`: ele recria tudo.
> Rode só a migração que falta (`database/migracao_v9_para_v10.sql`, por
> exemplo) — a tabela de versões está no `database/README.md`.

### 5. Abra no navegador

```
http://localhost/DevFox
```

Se você acabou de trocar arquivos de CSS ou JavaScript, use **Ctrl+F5** na
primeira vez.

### Contas que vêm no banco

| Conta | E-mail | Senha |
|---|---|---|
| Administrador | `admin@devfox.local` | `devfox123` |
| Aluno | `aluno@devfox.local` | `aluno123` |

São contas de desenvolvimento. Troque as senhas antes de publicar em qualquer
lugar acessível pela internet.

O admin cai direto na **Administração** ao entrar; para ver a tela do aluno,
use o botão "Ver o Início do aluno" na faixa de boas-vindas.

---

## Parte 2 — Testes automáticos

São 16 suítes em `testes/`, escritas em Node.js com Playwright. Elas abrem um
navegador de verdade, fazem login, resolvem atividades e conferem o resultado
contra o banco. É o que garante que uma mudança nova não quebrou o que já
funcionava.

### Instalação (uma vez só)

Precisa do **Node.js** instalado (https://nodejs.org). Depois:

```
cd C:\xampp\htdocs\DevFox\testes
```
```
npm init -y
```
```
npm install playwright
```
```
npx playwright install chromium
```

### Rodando tudo de uma vez

Com o XAMPP ligado e o banco importado:

```
cd C:\xampp\htdocs\DevFox\testes
```
```
rodar-todos.bat
```

O arquivo `.bat` já aponta o endereço do site e o caminho do MySQL, roda as 16
suítes na ordem e marca com `***` a que falhar. Leva alguns minutos: ele abre
e fecha o navegador dezenas de vezes.

### Rodando uma suíte só

Dá para rodar uma por vez, mas aí é preciso dizer onde está o site e o banco
(as suítes usam o servidor embutido do PHP por padrão):

```
set DEVFOX_URL=http://localhost/DevFox
```
```
set DEVFOX_MYSQL="C:\xampp\mysql\bin\mysql.exe" -u root devfox
```
```
node teste-graves.js
```

Essas duas variáveis valem só para a janela do Prompt aberta.

### O que cada suíte cobre

| Arquivo | Cobre |
|---|---|
| `teste-worker.js` | O gabarito nunca chega ao navegador; laço infinito é interrompido; XP não é farmável |
| `teste-prova.js` | Sequência dos módulos, nota, reprovação sem perder progresso |
| `teste-conquistas.js` | Cada conquista desbloqueia na hora certa, e só uma vez |
| `teste-admin.js` | A Administração cria conteúdo que funciona para o aluno; CSRF |
| `teste-loja.js` | Moedas, compra, saldo e equipar item |
| `teste-inicio.js` | Números do Início batem com o banco; sino; Configurações |
| `teste-trilhas.js` | As 8 trilhas, bloqueio do Projeto Final, exercícios de HTML e CSS |
| `teste-conta.js` | Lembrar-me, recuperação de senha e Suporte |
| `teste-perfil.js` | Blocos do Perfil, avatar e histórico |
| `teste-graves.js` | Os 5 furos graves da auditoria |
| `teste-medios.js` | Os problemas médios (sequência de dias, CSRF, bloqueio de login…) |
| `teste-menores.js` | Detalhes de texto, contraste e acessibilidade |
| `teste-admin-gestao.js` | Área do administrador inteira (schema v10) |
| `teste-admin-acesso.js` | Admin vê tudo aberto; aluno continua barrado; painel e busca |
| `teste-editor.js` | Editor: linha do erro e barras de rolagem escuras |

**Importante:** as suítes mexem no banco (criam contas, concluem atividades,
criam itens). Rode contra o banco de desenvolvimento e reimporte o
`banco.sql` quando quiser voltar ao estado limpo.

---

## Parte 3 — Roteiro manual, antes de apresentar

Os testes automáticos cobrem a lógica; este roteiro é para ver com os próprios
olhos. Leva uns 10 minutos.

**Antes de tudo:** crie 4 ou 5 contas de aluno e conclua algumas atividades com
elas. Com um aluno só, os gráficos e os relatórios ficam vazios, e o painel não
mostra o que sabe fazer.

### Como aluno

- [ ] Abrir `trilhas.php` sem estar logado manda para o login
- [ ] Login errado mostra a mensagem certa; login certo abre o Início
- [ ] Numa conta nova, só o primeiro módulo aparece liberado
- [ ] Abrir um módulo bloqueado pelo endereço mostra "Módulo ainda bloqueado"
- [ ] Resolver um exercício de código dá XP, e **enviar de novo não dá XP outra vez**
- [ ] Escrever `naoExiste();` no editor e clicar em Rodar: a linha fica vermelha e o painel diz "Linha N"
- [ ] Reprovar na prova mantém o progresso e lista o que revisar
- [ ] O ranking mostra só alunos; a Loja só deixa comprar com saldo

### Como administrador

- [ ] O login cai na Administração, com as 7 abas
- [ ] O painel mostra números, gráfico, rosca, ações rápidas, últimas atividades, destaques e ranking
- [ ] A busca do topo encontra aluno, trilha e atividade
- [ ] Alunos → Ver ficha: progresso, provas, respostas e conquistas do aluno
- [ ] Relatórios: os 4 blocos e os botões CSV baixam arquivo que abre no Excel
- [ ] Conquistas: criar uma conquista nova pela tela
- [ ] Loja: criar um título e uma moldura
- [ ] Registro: as ações que você acabou de fazer aparecem listadas
- [ ] Trilha arquivada some da lista do aluno, e o progresso de quem fez continua no banco

### Visual

- [ ] Os acentos aparecem certos ("Lógica de Programação", não "LÃ³gica")
- [ ] Diminuindo a janela (ou F12 → modo celular), nada fica cortado nem some

---

## Parte 4 — Quando algo dá errado

| Sintoma | Causa provável |
|---|---|
| `Failed to open stream: config/conexao.php` | O arquivo de conexão não existe — veja o passo 3 |
| "Não foi possível conectar ao banco de dados" | MySQL desligado no XAMPP, ou o banco `devfox` não foi importado |
| `Unknown column` ou `Table doesn't exist` | O banco está numa versão antiga: reimporte o `banco.sql` (v10) |
| Página em branco | Erro fatal do PHP: olhe o fim de `C:\xampp\apache\logs\error.log` |
| A tela abre, mas sem estilo nenhum | O endereço não é `localhost/DevFox/...` (abriu o arquivo direto, com `file://`) |
| Mudei o CSS/JS e nada mudou | Ctrl+F5. Se continuar igual, o arquivo não foi substituído — confira a data dele na pasta |
| O editor de código some da tela de atividade | Falta a pasta `assets/vendor/codemirror` |
| Erro 404 em tudo | A pasta não está dentro de `C:\xampp\htdocs` |
| Teste falha com `ECONNREFUSED` | O `DEVFOX_URL` aponta para um endereço que não está no ar |
| Teste falha com `'mysql' não é reconhecido` | Falta o `DEVFOX_MYSQL` com o caminho completo do `mysql.exe` |

### Onde ver os erros

- **PHP / Apache:** `C:\xampp\apache\logs\error.log`
- **JavaScript:** F12 no navegador → aba **Console**
- **Requisições (API):** F12 → aba **Rede**
- **E-mails do sistema** (redefinição de senha): `logs/emails.log` dentro do
  projeto — em desenvolvimento nada é enviado de verdade, fica gravado aí

Durante o desenvolvimento, deixe `display_errors = On` no
`C:\xampp\php\php.ini`. **Desligue antes de publicar:** mensagem de erro expõe
caminho de arquivo e estrutura do banco.
