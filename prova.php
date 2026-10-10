<?php
/**
 * prova.php — avaliação de fim de nível da trilha.
 *
 * Um módulo do tipo 'prova' agrupa as questões que avaliam os módulos
 * anteriores. O aluno responde tudo de uma vez, o servidor corrige e compara
 * com a nota mínima do módulo. Só a aprovação libera o avanço na trilha.
 *
 * REPROVAÇÃO: o aluno NÃO perde o progresso nem refaz o nível inteiro. O
 * sistema aponta quais módulos ele errou (via `atividades.modulo_referencia_id`)
 * e ele revisa só aqueles antes de tentar de novo. A decisão foi deliberada: o
 * DevFox existe para reduzir a desistência de iniciantes, e mandar alguém
 * recomeçar tudo por causa de uma prova é o jeito mais rápido de provocar
 * exatamente a desistência que o projeto quer evitar.
 *
 * XP: concedido apenas pelas questões ACERTADAS, e uma única vez cada (a chave
 * única de `atividades_concluidas` garante isso). Quem repete a prova pode
 * conquistar o XP das questões que ainda faltavam.
 *
 * A correção acontece no servidor; o formulário é um POST comum, então a prova
 * funciona mesmo com o JavaScript desligado.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/conquistas.php';
require_once __DIR__ . '/includes/csrf.php';

exigirLogin();

/**
 * Depois de REPROVAR, o aluno espera este tempo para refazer. Sem espera, dava
 * para descobrir o gabarito por tentativa e erro: trocar uma resposta, entregar,
 * ver se a nota subiu, repetir. Com 5 minutos por tentativa (e questões e
 * alternativas embaralhadas a cada vez), isso deixa de compensar — e o tempo
 * serve para o que a tela pede: revisar os módulos indicados.
 */
const PROVA_ESPERA_MINUTOS = 5;

/** Segundos até poder refazer a prova (0 = pode). Só conta depois de reprovação. */
function esperaDaProva(PDO $pdo, int $alunoId, int $moduloId): int
{
    // A espera existe para impedir que o ALUNO descubra o gabarito por
    // tentativa e erro. O admin não está sendo avaliado: ele abre a prova para
    // conferir as questões, e ficar 5 minutos travado só atrapalharia.
    if (ehOProprioAdmin($alunoId)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT aprovado,
                TIMESTAMPDIFF(SECOND, NOW(), realizada_em + INTERVAL " . PROVA_ESPERA_MINUTOS . " MINUTE) AS resta
         FROM tentativas_prova
         WHERE aluno_id = ? AND modulo_id = ?
         ORDER BY realizada_em DESC, id DESC LIMIT 1"
    );
    $stmt->execute([$alunoId, $moduloId]);
    $ultima = $stmt->fetch();
    if (!$ultima || (int) $ultima['aprovado'] === 1) {
        return 0;
    }
    return max(0, (int) $ultima['resta']);
}

$alunoId  = usuarioAtualId();
$moduloId = isset($_GET['modulo']) ? (int) $_GET['modulo'] : (int) ($_POST['modulo_id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT m.id, m.titulo, m.descricao, m.nota_minima, m.trilha_id, m.tipo,
            t.titulo AS trilha_titulo
     FROM modulos m
     JOIN trilhas t ON t.id = m.trilha_id
     WHERE m.id = ?"
);
$stmt->execute([$moduloId]);
$modulo = $stmt->fetch();

$moduloValido = $modulo && $modulo['tipo'] === 'prova';
$liberado     = $moduloValido && moduloLiberado($pdo, $moduloId, $alunoId);


/* -----------------------------------------------------------------------------
 * Correção
 * -------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $moduloValido && $liberado) {
    exigirCsrf();

    // Entregou antes de a espera acabar (outra aba, F5 no formulário antigo):
    // não corrige, só volta para a tela, que mostra quanto falta.
    if (esperaDaProva($pdo, $alunoId, $moduloId) > 0) {
        header('Location: prova.php?modulo=' . $moduloId);
        exit;
    }

    $respostas = $_POST['resposta'] ?? [];
    if (!is_array($respostas)) {
        $respostas = [];
    }

    // Gabarito e referência de cada questão, direto do banco.
    $stmt = $pdo->prepare(
        "SELECT a.id AS atividade_id,
                a.modulo_referencia_id,
                (SELECT al.id FROM alternativas al
                  WHERE al.atividade_id = a.id AND al.correta = 1
                  ORDER BY al.id LIMIT 1) AS alternativa_correta
         FROM atividades a
         WHERE a.modulo_id = ?
         ORDER BY a.ordem, a.id"
    );
    $stmt->execute([$moduloId]);
    $questoes = $stmt->fetchAll();

    $acertos          = 0;
    $certas           = [];
    $modulosParaRever = [];

    foreach ($questoes as $questao) {
        $idQuestao  = (int) $questao['atividade_id'];
        $respondida = isset($respostas[$idQuestao]) ? (int) $respostas[$idQuestao] : 0;

        if ($respondida > 0 && $respondida === (int) $questao['alternativa_correta']) {
            $acertos++;
            $certas[] = $idQuestao;
        } elseif ($questao['modulo_referencia_id']) {
            $modulosParaRever[(int) $questao['modulo_referencia_id']] = true;
        }
    }

    $total      = count($questoes);
    $percentual = $total > 0 ? (int) round($acertos / $total * 100) : 0;
    // Aprovação comparada em números inteiros, sem arredondar: com round(),
    // 69,5% virava 70% e passava numa prova de mínimo 70.
    $aprovado   = $total > 0 && $acertos * 100 >= (int) $modulo['nota_minima'] * $total;

    $pdo->prepare(
        "INSERT INTO tentativas_prova (aluno_id, modulo_id, acertos, total, percentual, aprovado)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([$alunoId, $moduloId, $acertos, $total, $percentual, $aprovado ? 1 : 0]);

    atualizarOfensiva($pdo, $alunoId);   // fazer a prova conta como dia de estudo

    // XP só pelas questões acertadas, e uma vez cada.
    $xpGanho = 0;
    foreach ($certas as $idQuestao) {
        $xpGanho += concluirAtividade($pdo, $alunoId, $idQuestao);
    }

    if ($aprovado) {
        concluirModulo($pdo, $alunoId, $moduloId);
    }

    // A prova é um POST que redireciona (Post/Redirect/Get), então o aviso de
    // conquista não pode ser mostrado agora: fica guardado na sessão e o rodapé
    // o imprime na página seguinte.
    guardarConquistasNaSessao(verificarConquistas($pdo, $alunoId));

    // Guardado na sessão e redirecionado (Post/Redirect/Get): atualizar a
    // página depois do resultado não reenvia a prova.
    $_SESSION['resultado_prova'][$moduloId] = [
        'acertos'    => $acertos,
        'total'      => $total,
        'percentual' => $percentual,
        'aprovado'   => $aprovado,
        'xp'         => $xpGanho,
        'rever'      => array_keys($modulosParaRever),
    ];

    header('Location: prova.php?modulo=' . $moduloId);
    exit;
}

$resultado = $_SESSION['resultado_prova'][$moduloId] ?? null;
unset($_SESSION['resultado_prova'][$moduloId]);

$tituloPagina    = $moduloValido ? $modulo['titulo'] : 'Prova';
$subtituloPagina = $moduloValido ? 'Avaliação de fim de nível · ' . $modulo['trilha_titulo'] : '';

require_once __DIR__ . '/includes/header.php';

if (!$moduloValido) {
    echo '<div class="painel vazio"><h3>Prova não encontrada</h3>'
       . '<p><a href="trilhas.php">Voltar para as trilhas</a>.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if (!$liberado) {
    echo '<div class="painel vazio">' . icone('cadeado', 32)
       . '<h3>Prova ainda bloqueada</h3>'
       . '<p>Conclua todos os módulos anteriores da trilha para liberar a avaliação.'
       . ' <a href="trilhas.php">Voltar para as trilhas</a>.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Questões com suas alternativas. A coluna `correta` não é consultada aqui:
// o gabarito nunca é enviado para a tela.
$stmt = $pdo->prepare(
    "SELECT id, titulo, enunciado FROM atividades WHERE modulo_id = ? ORDER BY ordem, id"
);
$stmt->execute([$moduloId]);
$questoes = $stmt->fetchAll();

$alternativasPorQuestao = [];
if (!empty($questoes)) {
    $ids  = array_column($questoes, 'id');
    $mark = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare(
        "SELECT id, atividade_id, texto FROM alternativas
         WHERE atividade_id IN ($mark) ORDER BY atividade_id, ordem, id"
    );
    $stmt->execute($ids);

    foreach ($stmt->fetchAll() as $alternativa) {
        $alternativasPorQuestao[(int) $alternativa['atividade_id']][] = $alternativa;
    }
}

// Ordem nova a cada vez que a prova abre: questões e alternativas. As
// respostas vão pelo id (resposta[id_da_questão] = id_da_alternativa), então
// embaralhar não muda a correção — só impede decorar "a 3ª é a letra B".
shuffle($questoes);
foreach ($alternativasPorQuestao as &$lista) {
    shuffle($lista);
}
unset($lista);

$espera   = esperaDaProva($pdo, $alunoId, $moduloId);
$ultima   = ultimaTentativaProva($pdo, $alunoId, $moduloId);
$aprovado = $resultado ? $resultado['aprovado'] : (bool) ($ultima['aprovado'] ?? false);

// Títulos dos módulos a revisar, quando houve reprovação.
$titulosParaRever = [];
if ($resultado && !empty($resultado['rever'])) {
    $mark = implode(',', array_fill(0, count($resultado['rever']), '?'));
    $stmt = $pdo->prepare("SELECT id, titulo FROM modulos WHERE id IN ($mark) ORDER BY ordem, id");
    $stmt->execute($resultado['rever']);
    $titulosParaRever = $stmt->fetchAll();
}
?>

<nav class="trilha-navegacao">
    <a href="trilhas.php">Trilhas</a>
    <?= icone('seta', 14) ?>
    <a href="trilhas.php"><?= e($modulo['trilha_titulo']) ?></a>
    <?= icone('seta', 14) ?>
    <strong><?= e($modulo['titulo']) ?></strong>
</nav>

<?php if (usuarioEhAdmin()): ?>
    <div class="barra-admin" role="note">
        <span><?= icone('escudo', 16) ?> Você está vendo como aluno. A espera entre tentativas não vale para o admin.</span>
        <a href="admin_modulo.php?id=<?= (int) $moduloId ?>" class="botao botao-secundario botao-pequeno">
            <?= icone('lapis', 14) ?> Editar a prova</a>
    </div>
<?php endif; ?>

<?php if ($resultado): ?>

    <div class="cartao-resultado <?= $resultado['aprovado'] ? 'resultado-aprovado' : 'resultado-reprovado' ?>">
        <div class="resultado-nota">
            <span class="resultado-percentual"><?= (int) $resultado['percentual'] ?>%</span>
            <small><?= (int) $resultado['acertos'] ?> de <?= plural((int) $resultado['total'], 'questão', 'questões') ?></small>
        </div>

        <div class="resultado-texto">
            <?php if ($resultado['aprovado']): ?>
                <h2>Aprovado!</h2>
                <p>
                    Você atingiu a nota mínima de <?= (int) $modulo['nota_minima'] ?>% e liberou
                    o próximo módulo da trilha.
                    <?php if ((int) $resultado['xp'] > 0): ?>
                        Ganhou <strong>+<?= (int) $resultado['xp'] ?> XP</strong>.
                    <?php endif; ?>
                </p>
                <a href="trilhas.php" class="botao botao-primario">Continuar na trilha</a>
            <?php else: ?>
                <h2>Ainda não foi dessa vez</h2>
                <p>
                    A nota mínima é <?= (int) $modulo['nota_minima'] ?>%.
                    <strong>Você não perdeu nenhum progresso</strong> — revise os pontos abaixo
                    e refaça a prova em seguida.
                    <?php if ((int) $resultado['xp'] > 0): ?>
                        As questões que você acertou renderam <strong>+<?= (int) $resultado['xp'] ?> XP</strong>.
                    <?php endif; ?>
                </p>

                <?php if (!empty($titulosParaRever)): ?>
                    <div class="rever-modulos">
                        <strong>Revise estes módulos:</strong>
                        <ul>
                            <?php foreach ($titulosParaRever as $rever): ?>
                                <li>
                                    <a href="desafio.php?modulo=<?= (int) $rever['id'] ?>">
                                        <?= e($rever['titulo']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <p class="aviso aviso-info">
                    Você pode refazer a prova em <?= PROVA_ESPERA_MINUTOS ?> minutos — use esse tempo para revisar.
                </p>
                <a href="prova.php?modulo=<?= $moduloId ?>" class="botao botao-secundario">Voltar para a prova</a>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>

    <div class="cabecalho-prova">
        <div>
            <p><?= e($modulo['descricao']) ?></p>
        </div>
        <div class="prova-meta">
            <span class="selo selo-prova">Nota mínima: <?= (int) $modulo['nota_minima'] ?>%</span>
            <span class="selo"><?= plural(count($questoes), 'questão', 'questões') ?></span>
        </div>
    </div>

    <?php if ($ultima): ?>
        <p class="aviso aviso-info">
            Última tentativa: <strong><?= (int) $ultima['percentual'] ?>%</strong>
            (<?= (int) $ultima['acertos'] ?>/<?= (int) $ultima['total'] ?>) —
            <?= $ultima['aprovado'] ? 'aprovado' : 'não aprovado' ?>,
            em <?= date('d/m/Y', strtotime($ultima['realizada_em'])) ?>.
            <?= $aprovado ? 'Você já passou nesta prova; refazer não tira o que conquistou.' : '' ?>
        </p>
    <?php endif; ?>

    <?php if ($espera > 0 && !empty($questoes)): ?>
        <div class="painel vazio" id="esperaProva">
            <?= icone('cadeado', 32) ?>
            <h3>Revise antes de tentar de novo</h3>
            <p>
                Você poderá refazer a prova em
                <strong><?= plural((int) ceil($espera / 60), 'minuto', 'minutos') ?></strong>.
                Enquanto isso, revise os módulos da trilha.
                <a href="trilhas.php">Voltar para as trilhas</a>.
            </p>
        </div>
    <?php elseif (empty($questoes)): ?>
        <div class="painel vazio">
            <h3>Esta prova ainda não tem questões</h3>
            <p>Avise a equipe do DevFox. <a href="trilhas.php">Voltar para as trilhas</a>.</p>
        </div>
    <?php else: ?>
        <form method="POST" action="prova.php" class="form-prova">
            <input type="hidden" name="modulo_id" value="<?= $moduloId ?>">
            <?= campoCsrf() ?>

            <?php foreach ($questoes as $indice => $questao): ?>
                <section class="painel questao-prova">
                    <h3>
                        <span class="questao-numero"><?= $indice + 1 ?></span>
                        <?= e($questao['enunciado']) ?>
                    </h3>

                    <div class="alternativas">
                        <?php foreach ($alternativasPorQuestao[(int) $questao['id']] ?? [] as $i => $alternativa): ?>
                            <label class="alternativa">
                                <input type="radio" required
                                       name="resposta[<?= (int) $questao['id'] ?>]"
                                       value="<?= (int) $alternativa['id'] ?>">
                                <span class="alternativa-letra"><?= chr(65 + $i) ?></span>
                                <span><?= e($alternativa['texto']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <div class="acoes-exercicio">
                <a href="trilhas.php" class="botao botao-secundario">Voltar</a>
                <button type="submit" class="botao botao-primario">Entregar prova</button>
            </div>
        </form>
    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
