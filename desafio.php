<?php
/**
 * desafio.php — tela de aula + exercício. É o ÚNICO fluxo de atividade.
 *
 * Segue a estrutura pedagógica do projeto: primeiro a explicação (teoria e/ou
 * vídeo do módulo), depois o exercício que aplica o conteúdo.
 *
 * Suporta os dois formatos de exercício do banco:
 *   - quiz                → alternativas vindas da tabela `alternativas`
 *   - exercício de código → executado no navegador (Web Worker) e julgado pelo
 *                           servidor contra a tabela `casos_teste`
 */

// As checagens de acesso rodam ANTES de includes/header.php porque algumas
// delas redirecionam — e não dá para redirecionar depois que o HTML começou
// a ser enviado ao navegador.
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/auth.php';

exigirLogin();

$alunoId = usuarioAtualId();

// Aceita ?id=N (atividade específica) ou ?modulo=N (primeira pendente do módulo).
$atividadeId  = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$moduloPedido = isset($_GET['modulo']) ? (int) $_GET['modulo'] : 0;

if ($atividadeId === 0 && $moduloPedido > 0) {
    // Na ordem DESTE aluno (ver embaralharAtividades em includes/auth.php),
    // com as ainda não feitas na frente.
    $stmt = $pdo->prepare(
        "SELECT a.id, a.modulo_id, (ac.id IS NOT NULL) AS feita
         FROM atividades a
         LEFT JOIN atividades_concluidas ac
                ON ac.atividade_id = a.id AND ac.aluno_id = ?
         WHERE a.modulo_id = ?
         ORDER BY a.ordem, a.id"
    );
    $stmt->execute([$alunoId, $moduloPedido]);
    $doModulo = embaralharAtividades($stmt->fetchAll(), $alunoId);

    foreach ($doModulo as $linha) {
        if ((int) $linha['feita'] === 0) {
            $atividadeId = (int) $linha['id'];
            break;
        }
    }
    if ($atividadeId === 0 && $doModulo !== []) {
        $atividadeId = (int) $doModulo[0]['id'];      // módulo todo concluído: revisão
    }
}

$stmt = $pdo->prepare(
    "SELECT a.*,
            m.id AS modulo_id, m.titulo AS modulo_titulo, m.teoria_texto, m.url_video,
            m.ordem AS modulo_ordem, m.tipo AS modulo_tipo,
            t.id AS trilha_id, t.titulo AS trilha_titulo, t.icone AS trilha_icone,
            t.linguagem
     FROM atividades a
     JOIN modulos m ON m.id = a.modulo_id
     JOIN trilhas t ON t.id = m.trilha_id
     WHERE a.id = ?"
);
$stmt->execute([$atividadeId]);
$atividade = $stmt->fetch();

// Questões de prova não são resolvidas avulsas: elas só existem dentro da
// avaliação, que é corrigida de uma vez e tem nota mínima.
if ($atividade && $atividade['modulo_tipo'] === 'prova') {
    header('Location: prova.php?modulo=' . (int) $atividade['modulo_id']);
    exit;
}

// Progressão sequencial: o módulo precisa estar liberado.
$bloqueado = $atividade && !moduloLiberado($pdo, (int) $atividade['modulo_id'], $alunoId);

$tituloPagina    = 'Atividade';
$subtituloPagina = 'Resolva o desafio e avance na sua trilha de aprendizado.';

require_once __DIR__ . '/includes/header.php';

if (!$atividade) {
    echo '<div class="painel vazio"><h3>Atividade não encontrada</h3>'
       . '<p>Ela pode ter sido removida. <a href="trilhas.php">Voltar para as trilhas</a>.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if ($bloqueado) {
    // Trilha inteira fechada por pré-requisito (v6) ou só o módulo anterior?
    $faltamTrilhas = requisitosPendentes($pdo, (int) $atividade['trilha_id'], $alunoId);
    $motivo = $faltamTrilhas
        ? 'Esta trilha libera depois que você concluir: ' . e(implode(', ', $faltamTrilhas)) . '.'
        : 'Conclua o módulo anterior da trilha para liberar este.';

    echo '<div class="painel vazio"><h3>Módulo ainda bloqueado</h3>'
       . '<p>' . $motivo
       . ' <a href="trilhas.php">Voltar para as trilhas</a>.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$ehQuiz = $atividade['tipo'] === 'quiz';

// Alternativas do quiz. A coluna `correta` NÃO é enviada para a tela — a
// resposta certa nunca sai do servidor, senão bastaria olhar o código-fonte.
$alternativas = [];
if ($ehQuiz) {
    $stmt = $pdo->prepare(
        "SELECT id, texto FROM alternativas WHERE atividade_id = ? ORDER BY ordem, id"
    );
    $stmt->execute([$atividadeId]);
    $alternativas = $stmt->fetchAll();

    // Ordem sorteada por aluno, como já acontece na prova: assim "a resposta é
    // a segunda" não circula entre colegas. A correção não muda, porque a
    // resposta vai pelo id da alternativa, não pela posição na tela. Estável
    // para o mesmo aluno, para as letras não dançarem a cada F5.
    usort($alternativas, static fn(array $a, array $b): int => strcmp(
        chaveDeOrdem($alunoId, (int) $a['id']),
        chaveDeOrdem($alunoId, (int) $b['id'])
    ));
}

// Casos de teste. A mesma ordem é usada em api/processar_desafio.php, para que
// o veredito de cada caso volte alinhado com as linhas exibidas na tela.
$casos = [];
if (!$ehQuiz) {
    $stmt = $pdo->prepare(
        "SELECT id, descricao, entrada, saida_esperada, visivel
         FROM casos_teste
         WHERE atividade_id = ?
         ORDER BY ordem, id"
    );
    $stmt->execute([$atividadeId]);
    $casos = $stmt->fetchAll();
}

// O que é entregue ao JavaScript: id, entrada e se o caso é oculto.
//
// ⚠ `saida_esperada` NÃO entra aqui, em nenhum caso. O navegador precisa da
//   entrada para executar o código, mas o gabarito fica no servidor — é ele
//   quem compara. Mandar o gabarito para a tela tornaria os casos ocultos
//   visíveis na aba Rede do navegador, e a correção, fácil de burlar.
$casosParaJs = array_map(static function (array $caso): array {
    return [
        'id'      => (int) $caso['id'],
        'entrada' => $caso['entrada'],
        'oculto'  => ((int) $caso['visivel'] === 0),
    ];
}, $casos);

$casosVisiveis = array_values(array_filter($casos, static fn(array $c): bool => (int) $c['visivel'] === 1));
$totalOcultos  = count($casos) - count($casosVisiveis);

// Posição da atividade dentro da trilha, para a barra de progresso.
// Só entram módulos de aula: as questões de prova não são "lições".
$stmt = $pdo->prepare(
    "SELECT a.id, a.modulo_id
     FROM atividades a
     JOIN modulos m ON m.id = a.modulo_id
     WHERE m.trilha_id = ? AND m.tipo = 'aula'
     ORDER BY m.ordem, m.id, a.ordem, a.id"
);
$stmt->execute([(int) $atividade['trilha_id']]);
// Mesma ordem que o resto do sistema usa para este aluno: é ela que define a
// contagem "Lição 3 de 16" e para onde vai o botão "Próxima atividade".
$idsDaTrilha = array_map(
    static fn(array $l): int => (int) $l['id'],
    embaralharAtividades($stmt->fetchAll(), $alunoId)
);

$posicao       = array_search($atividadeId, $idsDaTrilha, true);
$posicao       = ($posicao === false) ? 0 : $posicao + 1;
$totalNaTrilha = count($idsDaTrilha);
$percentual    = $totalNaTrilha > 0 ? (int) round($posicao / $totalNaTrilha * 100) : 0;

// Progressão por módulo: primeiro conclua as atividades deste módulo; depois avance
// para o próximo módulo da mesma trilha (ou para a prova, quando houver).
$proximaUrl = null;
$proximaRotulo = 'Voltar para as trilhas →';
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM atividades a
     LEFT JOIN atividades_concluidas ac
       ON ac.atividade_id = a.id AND ac.aluno_id = ?
     WHERE a.modulo_id = ? AND a.id <> ? AND ac.id IS NULL"
);
$stmt->execute([$alunoId, (int) $atividade['modulo_id'], $atividadeId]);
$pendentesNoModulo = (int) $stmt->fetchColumn();

if ($pendentesNoModulo > 0) {
    $proximaUrl = 'desafio.php?modulo=' . (int) $atividade['modulo_id'];
    $proximaRotulo = 'Próxima questão →';
} else {
    $stmt = $pdo->prepare(
        "SELECT id, tipo FROM modulos
         WHERE trilha_id = ? AND (ordem > ? OR (ordem = ? AND id > ?))
         ORDER BY ordem, id LIMIT 1"
    );
    $stmt->execute([
        (int) $atividade['trilha_id'],
        (int) $atividade['modulo_ordem'],
        (int) $atividade['modulo_ordem'],
        (int) $atividade['modulo_id'],
    ]);
    $proximoModulo = $stmt->fetch();

    if ($proximoModulo) {
        $proximaUrl = $proximoModulo['tipo'] === 'prova'
            ? 'prova.php?modulo=' . (int) $proximoModulo['id']
            : 'desafio.php?modulo=' . (int) $proximoModulo['id'];
        $proximaRotulo = $proximoModulo['tipo'] === 'prova'
            ? 'Ir para a prova →'
            : 'Próximo módulo →';
    }
}

// Já concluída? Muda o aviso de XP: refazer não paga de novo.
$stmt = $pdo->prepare(
    "SELECT 1 FROM atividades_concluidas WHERE aluno_id = ? AND atividade_id = ?"
);
$stmt->execute([$alunoId, $atividadeId]);
$jaConcluida = (bool) $stmt->fetchColumn();

$aparencia = aparenciaDaTrilha($atividade['trilha_icone']);
?>

<nav class="trilha-navegacao">
    <a href="trilhas.php">Trilhas</a>
    <?= icone('seta', 14) ?>
    <a href="trilhas.php"><?= e($atividade['trilha_titulo']) ?></a>
    <?= icone('seta', 14) ?>
    <strong><?= e($atividade['modulo_titulo']) ?></strong>
</nav>

<?php // Admin navegando como aluno: atalho para editar o que está vendo.
      // Aluno nenhum vê esta barra, e as páginas de administração conferem a
      // permissão no servidor de qualquer jeito. ?>
<?php if (usuarioEhAdmin()): ?>
    <div class="barra-admin" role="note">
        <span><?= icone('escudo', 16) ?> Você está vendo como aluno.</span>
        <a href="admin_atividade.php?id=<?= (int) $atividade['id'] ?>" class="botao botao-secundario botao-pequeno">
            <?= icone('lapis', 14) ?> Editar atividade</a>
        <a href="admin_modulo.php?id=<?= (int) $atividade['modulo_id'] ?>" class="botao botao-secundario botao-pequeno">Editar módulo</a>
        <a href="admin_trilha.php?id=<?= (int) $atividade['trilha_id'] ?>" class="botao botao-secundario botao-pequeno">Editar trilha</a>
    </div>
<?php endif; ?>

<div class="faixa-progresso">
    <span>Lição <strong><?= $posicao ?></strong> de <?= $totalNaTrilha ?></span>
    <div class="barra-progresso">
        <div class="barra-progresso-preenchida" style="width: <?= $percentual ?>%;"></div>
    </div>
    <strong class="faixa-progresso-valor"><?= $percentual ?>%</strong>
</div>

<div class="colunas">

    <div class="coluna-principal">

        <?php if (!empty($atividade['teoria_texto']) || !empty($atividade['url_video'])): ?>
            <section class="painel painel-teoria">
                <div class="painel-cabecalho">
                    <span class="bloco-icone bloco-icone-pequeno" style="--cor-trilha: <?= e($aparencia['cor']) ?>">
                        <?= icone('livro', 18) ?>
                    </span>
                    <div>
                        <small>Explicação</small>
                        <strong><?= e($atividade['modulo_titulo']) ?></strong>
                    </div>
                </div>

                <?php // Mesmo o que já está no banco passa pela checagem: só YouTube vira iframe. ?>
                <?php if (($videoSeguro = urlVideoSegura($atividade['url_video'])) !== null): ?>
                    <div class="caixa-video">
                        <iframe src="<?= e($videoSeguro) ?>" title="Vídeo da aula"
                                allowfullscreen loading="lazy"></iframe>
                    </div>
                <?php endif; ?>

                <?php if (!empty($atividade['teoria_texto'])): ?>
                    <p class="texto-teoria"><?= nl2br(e($atividade['teoria_texto'])) ?></p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="painel painel-exercicio">
            <div class="painel-cabecalho">
                <span class="bloco-icone bloco-icone-pequeno" style="--cor-trilha: <?= e($aparencia['cor']) ?>">
                    <?= icone('codigo', 18) ?>
                </span>
                <div>
                    <strong class="exercicio-titulo"><?= e($atividade['titulo']) ?></strong>
                    <small><?= e($atividade['enunciado']) ?></small>
                </div>
            </div>

            <?php $linguagem = $atividade['linguagem'] ?? 'javascript'; $ehHtml = $linguagem === 'html'; $ehPython = $linguagem === 'python'; ?>
            <form id="formDesafio"
                  data-atividade="<?= $atividadeId ?>"
                  data-linguagem="<?= e($linguagem) ?>"
                  data-tipo="<?= e($atividade['tipo']) ?>"
                  data-worker-python="<?= e(asset('js/executor-python.worker.js')) ?>"
                  <?= $proximaUrl ? 'data-proxima-url="' . e($proximaUrl) . '"' : '' ?>
                  data-proxima-rotulo="<?= e($proximaRotulo) ?>"
                  data-modulo-concluido="<?= $pendentesNoModulo === 0 ? '1' : '0' ?>">

                <input type="hidden" name="atividade_id" value="<?= $atividadeId ?>">

                <?php if ($ehQuiz): ?>

                    <?php if (empty($alternativas)): ?>
                        <p class="aviso-suave">Este quiz ainda não tem alternativas cadastradas.</p>
                    <?php else: ?>
                        <div class="alternativas">
                            <?php foreach ($alternativas as $i => $alt): ?>
                                <label class="alternativa">
                                    <input type="radio" name="alternativa_id" required
                                           value="<?= (int) $alt['id'] ?>">
                                    <span class="alternativa-letra"><?= chr(65 + $i) ?></span>
                                    <span class="alternativa-texto"><?= e($alt['texto']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>

                    <div class="editor">
                        <div class="editor-topo">
                            <label for="codigo">Editor de código</label>
                            <span class="selo-linguagem <?= $ehHtml ? 'selo-linguagem-html' : '' ?>"><?= $ehHtml ? 'HTML e CSS' : ($ehPython ? 'Python' : 'JavaScript') ?></span>
                        </div>
                        <textarea id="codigo" name="codigo" class="editor-area" spellcheck="false"
                                  rows="12"><?= e($atividade['codigo_inicial']) ?></textarea>
                    </div>

                    <?php if ($ehHtml): ?>
                        <?php /* Prévia do que o aluno escreveu. Sem allow-scripts: nenhum
                                 <script> dele executa. Ver assets/js/executor-html.js. */ ?>
                        <div class="previa-html">
                            <strong class="saida-esperada-titulo"><?= icone('olho', 16) ?> Como a página fica</strong>
                            <iframe id="previaHtml" sandbox="allow-same-origin" title="Prévia do seu HTML"></iframe>
                        </div>
                    <?php endif; ?>

                    <button type="button" id="btnRodar" class="botao botao-secundario botao-bloco botao-rodar">
                        <?= icone('play', 16) ?> Rodar código
                    </button>

                    <?php /* Entradas dos casos para o Web Worker. Sem gabarito. */ ?>
                    <script type="application/json" id="dadosCasos"><?= json_encode($casosParaJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

                    <div id="painelResultados" class="resultados" hidden></div>

                    <?php if (!empty($casosVisiveis)): ?>
                        <div class="saida-esperada">
                            <strong class="saida-esperada-titulo">
                                <?= icone('alvo', 16) ?> <?= $ehHtml ? 'O que será conferido' : 'Saída esperada' ?>
                            </strong>

                            <?php foreach ($casosVisiveis as $caso): ?>
                                <div class="caso">
                                    <?php if (!empty($caso['entrada'])): ?>
                                        <code class="caso-entrada"><?= e($caso['entrada']) ?></code>
                                        <?= icone('seta', 14) ?>
                                    <?php endif; ?>
                                    <code class="caso-saida"><?= e($caso['saida_esperada']) ?></code>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($totalOcultos > 0): ?>
                                <p class="aviso-ocultos">
                                    <?= icone('cadeado', 14) ?>
                                    <strong><?= $totalOcultos ?>
                                    <?= $totalOcultos === 1 ? 'caso de teste oculto' : 'casos de teste ocultos' ?></strong>
                                    além dos exemplos acima — seu código precisa funcionar neles também.
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

                <div id="feedbackMsg" class="aviso" role="status" aria-live="polite"></div>

                <div class="acoes-exercicio">
                    <a href="trilhas.php" class="botao botao-secundario">
                        <?= icone('voltar', 16) ?> Voltar
                    </a>
                    <button type="submit" class="botao botao-primario">Enviar resposta</button>
                </div>
            </form>
        </section>
    </div>

    <aside class="coluna-lateral">

        <section class="painel">
            <h3 class="painel-titulo"><?= icone('alvo', 18) ?> Sobre o desafio</h3>

            <dl class="ficha">
                <dt>Objetivo</dt>
                <dd><?= e($atividade['enunciado']) ?></dd>

                <dt>Dificuldade</dt>
                <dd>
                    <span class="medidor medidor-<?= e($atividade['dificuldade']) ?>">
                        <i></i><i></i><i></i>
                    </span>
                    <?= e(rotuloDificuldade($atividade['dificuldade'])) ?>
                </dd>

                <dt>Recompensa</dt>
                <dd class="recompensa">
                    <?php if ($jaConcluida): ?>
                        <span class="selo selo-concluida"><?= icone('check', 14) ?> Já concluída</span>
                    <?php else: ?>
                        +<?= (int) $atividade['xp_recompensa'] ?> XP
                    <?php endif; ?>
                </dd>
            </dl>
        </section>

        <section class="painel">
            <h3 class="painel-titulo"><?= icone('check', 18) ?> Passos para concluir</h3>
            <ol class="passos">
                <li class="passo-feito"><span>1</span> Ler a explicação e o enunciado</li>
                <li><span>2</span> <?= $ehQuiz ? 'Escolher a alternativa' : 'Escrever a lógica no editor' ?></li>
                <li><span>3</span> <?= $ehQuiz ? 'Enviar a resposta' : 'Rodar o código e enviar a resposta' ?></li>
            </ol>
        </section>

        <?php if (!empty($atividade['dica']) || !empty($atividade['solucao_exemplo'])): ?>
            <section class="painel">
                <h3 class="painel-titulo"><?= icone('lampada', 18) ?> Precisa de ajuda?</h3>

                <?php if (!empty($atividade['dica'])): ?>
                    <?php
                        /*
                         * O texto da dica NÃO vem no HTML: é buscado em api/dica.php
                         * quando o aluno abre o bloco. É lá que o uso fica registrado,
                         * no servidor — ver o comentário daquele arquivo e a conquista
                         * "Por Conta Própria".
                         */
                    ?>
                    <details class="revelar" id="blocoDica" data-atividade="<?= $atividadeId ?>">
                        <summary><?= icone('lampada', 16) ?> Usar dica</summary>
                        <p id="textoDica">Carregando…</p>
                    </details>
                <?php endif; ?>

                <?php // Só depois de concluir: antes disso, a solução pronta vale o XP sem esforço nenhum. ?>
                <?php if (($jaConcluida || usuarioEhAdmin()) && !empty($atividade['solucao_exemplo'])): ?>
                    <details class="revelar">
                        <summary><?= icone('olho', 16) ?> Mostrar exemplo de solução</summary>
                        <pre class="bloco-codigo"><?= e($atividade['solucao_exemplo']) ?></pre>
                    </details>
                <?php endif; ?>
            </section>
        <?php endif; ?>

    </aside>
</div>

<?php if (!$ehQuiz): ?>
    <?php
        /*
         * Editor de código (CodeMirror 5), servido do próprio projeto e não de um
         * CDN: numa apresentação de TCC, depender da rede da sala é risco à toa.
         * Ver assets/vendor/codemirror/LEIAME.md.
         *
         * Se estes arquivos não carregarem, a tela continua funcionando: o
         * <textarea> original segue lá e o desafios.js detecta a ausência do
         * CodeMirror e trabalha direto com ele.
         */
    ?>
    <link rel="stylesheet" href="assets/vendor/codemirror/codemirror.css">
    <script src="assets/vendor/codemirror/codemirror.min.js"></script>
    <?php if (($atividade['linguagem'] ?? '') === 'html'): ?>
        <script src="assets/vendor/codemirror/modo-xml.min.js"></script>
        <script src="assets/vendor/codemirror/modo-css.min.js"></script>
        <script src="assets/vendor/codemirror/modo-javascript.min.js"></script>
        <script src="assets/vendor/codemirror/modo-htmlmixed.min.js"></script>
        <script src="<?= asset('js/executor-html.js') ?>"></script>
    <?php elseif (($atividade['linguagem'] ?? '') === 'python'): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/python/python.min.js"></script>
    <?php else: ?>
        <script src="assets/vendor/codemirror/modo-javascript.min.js"></script>
    <?php endif; ?>
    <script src="assets/vendor/codemirror/matchbrackets.min.js"></script>
    <script src="assets/vendor/codemirror/closebrackets.min.js"></script>
<?php endif; ?>

<script src="<?= asset('js/desafios.js') ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
