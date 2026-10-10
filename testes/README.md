# Testes automatizados do DevFox

Duas suítes que sobem um navegador de verdade (Chromium, via Playwright) e usam o
sistema como um aluno usaria. Elas existem para provar, na defesa do TCC, que as
regras de negócio não são só "confia em mim":

| Arquivo | O que prova |
|---|---|
| `teste-worker.js` | O gabarito nunca chega ao navegador; código com laço infinito é interrompido; chumbar a resposta dos casos visíveis não passa; POST forjado é recusado; XP não pode ser farmado. |
| `teste-prova.js` | Módulos abrem em sequência (nem pela URL dá para pular); questão de prova não é resolvida avulsa; nota calculada certa; reprovar não apaga progresso; XP da prova não é farmável; aluno não entra na Administração. |
| `teste-conquistas.js` | Cada conquista desbloqueia na condição certa **e não antes**; nunca é concedida duas vezes; a dica não vem no HTML e seu uso é registrado pelo servidor. |
| `teste-admin.js` | A Administração cria e edita conteúdo que funciona para o aluno; gabarito errado não salva; POST sem token CSRF é recusado. |
| `teste-inicio.js` | O login leva ao Início e os números batem com o banco; destaques e a tela Desafios nunca oferecem módulo bloqueado; Configurações recusa senha atual errada, confirmação diferente e senha curta; o sino mostra eventos reais. |
| `teste-trilhas.js` | As 8 trilhas na ordem do wireframe; Projeto Final bloqueado (lista e URL) e liberado quando as outras terminam; exercício de HTML e de CSS corrigidos de verdade; `<script>` do aluno não executa. |
| `teste-perfil.js` | Blocos do Perfil; números = banco (desafios, conquistas, "+N esta semana", melhor sequência); troca de avatar (código fora do catálogo recusado, CSRF); "Ver todas" de conquistas e histórico paginado. |
| `teste-conta.js` | Lembrar-me (cookie HttpOnly, rotação, cookie forjado recusado, sair apaga); Esqueci a senha (mesma resposta para e-mail existente ou não, link de uso único, expira, pedido novo invalida o antigo); Suporte (isca, limites, espera, admin lê, HTML escapado). |
| `teste-graves.js` | Os 5 furos graves da auditoria: a API recusa questão de prova, módulo bloqueado e trilha em rascunho (gabarito da prova não vaza, sem XP); "Mostrar exemplo de solução" só depois de concluir; módulo vazio não trava a trilha; prova aprovada com a nota mínima conclui a trilha (100%, conquista, libera o Projeto Final); paginação com número gigante não derruba a página. Rodado contra o código antigo, falha em 7 pontos. |
| `teste-medios.js` | Os problemas médios da auditoria: sequência de dias conta estudo (não login), zera na tela de quem parou e usa o "hoje" de Brasília; bloqueio olha todos os módulos anteriores; requisito circular recusado e requisito em rascunho não prende; CSRF em login, cadastro, APIs e logout (que passou a ser POST); 5 senhas erradas bloqueiam o e-mail por 15 min (existindo ou não); cookie de sessão HttpOnly + SameSite; prova embaralhada e nota sem arredondar; cadastro sem erro 500 e sem confirmar quem tem conta. Rodado contra o código anterior, falha em 16 pontos. |
| `teste-menores.js` | Detalhes da auditoria: "Falta 1 moeda"; "Rodar código" mantém o ícone; contraste do botão principal e do texto apagado ≥ 4,5:1 (WCAG AA); menu do celular numa linha e com nome acessível em todos os itens; Perfil com um só "Ver todas" e 8 avatares diferentes; vídeo da aula só do YouTube (`javascript:` recusado ao salvar e ao exibir). |
| `teste-admin-gestao.js` | Área do administrador (schema v10): aluno não abre nenhuma página `admin_*`; painel com números e alertas; ordem e arquivamento de trilhas (apagar só sem progresso); estatísticas da atividade e ids das alternativas mantidos; lista, ficha, promover/rebaixar, desativar (a sessão cai na hora), link de senha e exclusão de conta (último admin protegido); relatórios e CSV com fórmula neutralizada; conquista criada pela tela desbloqueia pela regra; Loja; filtros do Suporte; registro de ações. |
| `teste-admin-acesso.js` | Admin navegando como aluno vê tudo aberto (módulo fora de ordem, trilha com pré-requisito, trilha em rascunho) e ganha os atalhos de edição, enquanto o ALUNO continua barrado em todos esses pontos; moldura criada pela tela com aparência do catálogo fechado (aparência inventada no POST é recusada) e à venda para o aluno; ordem sorteada das atividades: estável a cada F5, diferente entre pessoas e igual em todas as telas; conquistas em prévia para o admin (e bloqueadas para o aluno); painel inicial (blocos, gráficos em SVG, tabela equivalente, números iguais aos do banco) e busca única, com curinga do LIKE escapado. |
| `teste-editor.js` | Editor de código: as barras de rolagem claras não voltam (nem a da página, nem a que o CodeMirror desenhava atravessando o editor); a linha do erro é apontada — erro de execução e erro de sintaxe —, a marca some quando o aluno digita e código certo não marca nada. |
| `teste-loja.js` | Moeda só vem de XP (e XP repetido não gera moeda); preço forjado no POST é ignorado; não compra sem saldo nem duas vezes; não equipa o que não comprou; duas sessões comprando ao mesmo tempo não gastam além do saldo. |

As suítes usam `ajudantes_teste.js`, que resolve as atividades lendo a solução de
referência **no banco** (exercício de código) ou tentando as alternativas até
acertar (quiz). Até 22/09 a solução era lida do "Mostrar exemplo" da página, mas
esse bloco agora só aparece depois que o aluno conclui a atividade. O mesmo
arquivo tem `responderProva()` (a prova vem embaralhada, então a alternativa
certa é buscada no banco pelo id, não pela posição) e `sair()` (o logout agora
é o botão Sair, um POST com token — abrir `logout.php` pelo endereço não
desloga mais). É por isso que eles **não quebram quando o conteúdo das
trilhas muda** — não guardam cópia de solução nem dependem de id de atividade,
salvo por uma tabela pequena no topo de `teste-worker.js`, que é conferida pelo
título antes de cada rodada.

Para conferir o CONTEÚDO em si (gabarito bate com a solução, caso oculto
distingue de verdade, prova aponta o módulo certo), o lugar é outro:
`tools/validar_conteudo.js`. Veja `tools/README.md`.

## Como rodar

Precisa do Node.js instalado (https://nodejs.org) e do XAMPP rodando com o banco
`devfox` já importado.

```bash
cd testes
npm init -y
npm install playwright
npx playwright install chromium
```

Depois diga onde está o site e como falar com o banco (não precisa editar
arquivo nenhum — as suítes leem essas duas variáveis):

```bash
set DEVFOX_URL=http://localhost/DevFox
set DEVFOX_MYSQL="C:\xampp\mysql\bin\mysql.exe" -u root devfox
```

No Windows, `rodar-todos.bat` já faz isso e roda as 16 suítes em ordem,
marcando com `***` a que falhar. Para rodar uma por vez:

```bash
node teste-worker.js
node teste-prova.js
node teste-conquistas.js
node teste-admin.js
node teste-loja.js
node teste-inicio.js
node teste-trilhas.js
node teste-conta.js
node teste-perfil.js
node teste-graves.js
node teste-medios.js
node teste-menores.js
node teste-admin-gestao.js
node teste-editor.js
node teste-admin-acesso.js
```

Todas as suítes consultam o banco pelo cliente `mysql` (no mínimo para buscar a solução
de referência das atividades). No XAMPP, antes de rodar:
`set DEVFOX_MYSQL=C:\xampp\mysql\bin\mysql.exe -u root devfox`.
O teste de corrida só prova algo com um servidor que atende requisições em paralelo (o Apache
do XAMPP atende; o `php -S` só com `PHP_CLI_SERVER_WORKERS=4`).

## Proteção das pastas (.htaccess)

O `php -S` usado nos testes **ignora `.htaccess`**. As regras que impedem baixar
`database/banco.sql`, `README.md`, `tools/`, `testes/`, `docs/` e `config/` pelo
navegador valem só no Apache (XAMPP). Para conferir no XAMPP, abra no navegador:
`http://localhost/DevFox/database/banco.sql` e `http://localhost/DevFox/README.md` —
os dois precisam responder **403 Forbidden**. (Conferido num Apache 2.4 em 22/09.)

## ⚠ Atenção

Todas as suítes **criam usuários e concluem atividades no banco**. Rode num banco
de desenvolvimento, nunca num que você queira manter limpo. Para reiniciar do zero,
reimporte `database/banco.sql`.

Saída esperada no fim de cada arquivo: `>>> TODOS OS TESTES PASSARAM`.
