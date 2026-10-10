<?php
/**
 * admin_modulo.php — edita um módulo e lista as atividades dele.
 *
 * Terceira tela da Administração:
 *     administracao.php → admin_trilha.php → ESTA → admin_atividade.php
 *
 * Num módulo de AULA, o que se cadastra aqui é a explicação (teoria e vídeo) e
 * as atividades que aplicam o conteúdo — nessa ordem, que é a ordem que o aluno
 * vê na tela de desafio.
 *
 * Num módulo de PROVA não há teoria: ele agrupa as questões que avaliam os
 * módulos anteriores. Cada questão aponta qual módulo ela cobra, e é disso que
 * sai a indicação do que revisar quando o aluno reprova.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/ui.php';          // urlVideoSegura()

exigirAdmin();

$moduloId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['modulo_id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT m.*, t.id AS trilha_id, t.titulo AS trilha_titulo
     FROM modulos m JOIN trilhas t ON t.id = m.trilha_id
     WHERE m.id = ?"
);
$stmt->execute([$moduloId]);
$modulo = $stmt->fetch();

if (!$modulo) {
    redirecionarCom('administracao.php', 'Módulo não encontrado.', 'erro');
}

$ehProva = $modulo['tipo'] === 'prova';
$volta   = 'admin_modulo.php?id=' . $moduloId;

/* -----------------------------------------------------------------------------
 * POST
 * -------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_modulo') {
        $titulo = trim($_POST['titulo'] ?? '');

        if ($titulo === '') {
            redirecionarCom($volta, 'O título do módulo não pode ficar vazio.', 'erro');
        }

        $descricao = trim($_POST['descricao'] ?? '');
        $teoria    = trim($_POST['teoria_texto'] ?? '');
        $video     = trim($_POST['url_video'] ?? '');
        if ($video !== '') {
            // Só vídeo do YouTube, gravado já no formato de incorporação (ver urlVideoSegura()).
            $videoSeguro = urlVideoSegura($video);
            if ($videoSeguro === null) {
                redirecionarCom($volta, 'Vídeo inválido: cole um link do YouTube (youtube.com/watch?v=… ou youtu.be/…).', 'erro');
            }
            $video = $videoSeguro;
        }
        $nota      = $ehProva ? max(1, min(100, (int) ($_POST['nota_minima'] ?? 70)))
                              : (int) $modulo['nota_minima'];

        $pdo->prepare(
            "UPDATE modulos
                SET titulo = ?, descricao = ?, teoria_texto = ?, url_video = ?, nota_minima = ?
              WHERE id = ?"
        )->execute([
            $titulo,
            $descricao !== '' ? $descricao : null,
            $teoria !== ''    ? $teoria    : null,
            $video !== ''     ? $video     : null,
            $nota,
            $moduloId,
        ]);

        registrarAcaoAdmin($pdo, 'modulo.editar', 'Módulo: ' . $titulo);
        redirecionarCom($volta, 'Módulo atualizado.');
    }

    if ($acao === 'mover') {
        $atividadeId = (int) ($_POST['atividade_id'] ?? 0);
        $direcao     = ($_POST['direcao'] ?? '') === 'cima' ? 'cima' : 'baixo';

        $stmt = $pdo->prepare("SELECT id, ordem FROM atividades WHERE id = ? AND modulo_id = ?");
        $stmt->execute([$atividadeId, $moduloId]);
        $atual = $stmt->fetch();

        if ($atual) {
            $comparador = $direcao === 'cima' ? '<' : '>';
            $sentido    = $direcao === 'cima' ? 'DESC' : 'ASC';

            $stmt = $pdo->prepare(
                "SELECT id, ordem FROM atividades
                  WHERE modulo_id = ? AND (ordem $comparador ? OR (ordem = ? AND id $comparador ?))
                  ORDER BY ordem $sentido, id $sentido
                  LIMIT 1"
            );
            $stmt->execute([$moduloId, (int) $atual['ordem'], (int) $atual['ordem'], $atividadeId]);
            $vizinho = $stmt->fetch();

            if ($vizinho) {
                $troca = $pdo->prepare("UPDATE atividades SET ordem = ? WHERE id = ?");
                $troca->execute([(int) $vizinho['ordem'], $atividadeId]);
                $troca->execute([(int) $atual['ordem'], (int) $vizinho['id']]);
            }
        }

        header('Location: ' . $volta);
        exit;
    }

    if ($acao === 'apagar_atividade') {
        $atividadeId = (int) ($_POST['atividade_id'] ?? 0);

        // Mesma regra dos módulos: nada que já tenha progresso de aluno é
        // apagado, porque a cascata levaria o XP junto.
        if (atividadeTemProgresso($pdo, $atividadeId)) {
            redirecionarCom($volta,
                'Esta atividade não pode ser apagada: algum aluno já a concluiu.', 'erro');
        }

        $stmt = $pdo->prepare("SELECT titulo FROM atividades WHERE id = ? AND modulo_id = ?");
        $stmt->execute([$atividadeId, $moduloId]);
        $tituloApagado = (string) $stmt->fetchColumn();
        $pdo->prepare("DELETE FROM atividades WHERE id = ? AND modulo_id = ?")
            ->execute([$atividadeId, $moduloId]);
        registrarAcaoAdmin($pdo, 'atividade.apagar', 'Atividade: ' . $tituloApagado);

        redirecionarCom($volta, 'Atividade apagada.');
    }

    header('Location: ' . $volta);
    exit;
}

$flash = consumirFlash();

$tituloPagina    = $modulo['titulo'];
$subtituloPagina = ($ehProva ? 'Prova de fim de nível' : 'Módulo de aula') . ' · ' . $modulo['trilha_titulo'];

require_once __DIR__ . '/includes/header.php';

$stmt = $pdo->prepare(
    "SELECT a.*,
            (SELECT COUNT(*) FROM alternativas al WHERE al.atividade_id = a.id) AS alternativas,
            (SELECT COUNT(*) FROM casos_teste ct WHERE ct.atividade_id = a.id)  AS casos,
            (SELECT COUNT(*) FROM casos_teste ct WHERE ct.atividade_id = a.id AND ct.visivel = 0) AS ocultos,
            (SELECT COUNT(*) FROM atividades_concluidas ac WHERE ac.atividade_id = a.id) AS conclusoes,
            r.titulo AS referencia_titulo
     FROM atividades a
     LEFT JOIN modulos r ON r.id = a.modulo_referencia_id
     WHERE a.modulo_id = ?
     ORDER BY a.ordem, a.id"
);
$stmt->execute([$moduloId]);
$atividades = $stmt->fetchAll();

$ultimo = count($atividades) - 1;
?>

<?= trilhaDeNavegacaoAdmin([
    ['titulo' => $modulo['trilha_titulo'], 'url' => 'admin_trilha.php?id=' . (int) $modulo['trilha_id']],
    ['titulo' => $modulo['titulo']],
]) ?>

<?php if ($flash): ?>
    <?= exibeMensagem($flash[0], $flash[1]) ?>
<?php endif; ?>

<div class="colunas">

    <div class="coluna-principal">

        <section class="painel">
            <h3 class="painel-titulo">
                <?= icone($ehProva ? 'prova' : 'alvo') ?>
                <?= $ehProva ? 'Questões da prova' : 'Atividades' ?>
                <small class="contador"><?= count($atividades) ?></small>
            </h3>

            <?php if (empty($atividades)): ?>
                <p class="aviso-suave">
                    <?php if ($ehProva): ?>
                        Nenhuma questão ainda. Uma prova sem questão não corrige nada e fica
                        travando a trilha — cadastre ao menos três.
                    <?php else: ?>
                        Nenhuma atividade ainda. Um módulo vazio nunca é concluído pelo aluno,
                        e por isso trava o módulo seguinte.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <ol class="lista-admin lista-admin-numerada">
                    <?php foreach ($atividades as $i => $atividade): ?>
                        <?php
                            $temProgresso = (int) $atividade['conclusoes'] > 0;
                            $problema = null;

                            // Avisos que impedem a atividade de funcionar de verdade.
                            if (ehQuiz($atividade['tipo'])) {
                                if ((int) $atividade['alternativas'] < 2) {
                                    $problema = 'sem alternativas suficientes';
                                }
                            } elseif ((int) $atividade['casos'] === 0) {
                                $problema = 'sem casos de teste';
                            } elseif ((int) $atividade['ocultos'] === 0) {
                                $problema = 'sem caso oculto';
                            }

                            if ($ehProva && empty($atividade['modulo_referencia_id'])) {
                                $problema = 'não diz qual módulo avalia';
                            }
                        ?>
                        <li class="linha-admin">
                            <span class="linha-admin-ordem"><?= $i + 1 ?></span>

                            <div class="linha-admin-info">
                                <strong><?= e($atividade['titulo']) ?></strong>
                                <small>
                                    <?= e(rotuloTipoAtividade($atividade['tipo'])) ?>
                                    &middot; <?= (int) $atividade['xp_recompensa'] ?> XP
                                    <?php if (ehQuiz($atividade['tipo'])): ?>
                                        &middot; <?= plural((int) $atividade['alternativas'], 'alternativa', 'alternativas') ?>
                                    <?php else: ?>
                                        &middot; <?= plural((int) $atividade['casos'], 'caso', 'casos') ?>
                                        (<?= (int) $atividade['ocultos'] ?> oculto<?= (int) $atividade['ocultos'] === 1 ? '' : 's' ?>)
                                    <?php endif; ?>
                                    <?php if ($atividade['referencia_titulo']): ?>
                                        &middot; avalia <?= e($atividade['referencia_titulo']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>

                            <?php if ($problema !== null): ?>
                                <span class="selo selo-alerta" title="Esta atividade não funciona assim">
                                    <?= e($problema) ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($temProgresso): ?>
                                <span class="selo" title="Alunos já concluíram">
                                    <?= (int) $atividade['conclusoes'] ?> conclu<?= (int) $atividade['conclusoes'] === 1 ? 'são' : 'sões' ?>
                                </span>
                            <?php endif; ?>

                            <div class="linha-admin-acoes">
                                <form method="POST" class="formulario-embutido">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="modulo_id" value="<?= $moduloId ?>">
                                    <input type="hidden" name="acao" value="mover">
                                    <input type="hidden" name="atividade_id" value="<?= (int) $atividade['id'] ?>">
                                    <button type="submit" name="direcao" value="cima"
                                            class="botao botao-secundario botao-icone"
                                            title="Mover para cima" <?= $i === 0 ? 'disabled' : '' ?>>&uarr;</button>
                                    <button type="submit" name="direcao" value="baixo"
                                            class="botao botao-secundario botao-icone"
                                            title="Mover para baixo" <?= $i === $ultimo ? 'disabled' : '' ?>>&darr;</button>
                                </form>

                                <a href="admin_atividade.php?id=<?= (int) $atividade['id'] ?>"
                                   class="botao botao-primario botao-pequeno">Editar</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <a href="admin_atividade.php?modulo=<?= $moduloId ?>" class="botao botao-primario botao-bloco">
                <?= $ehProva ? 'Nova questão' : 'Nova atividade' ?>
            </a>
        </section>

    </div>

    <aside class="coluna-lateral">

        <section class="painel">
            <h3 class="painel-titulo"><?= icone('admin') ?> Dados do módulo</h3>

            <form method="POST">
                <?= campoCsrf() ?>
                <input type="hidden" name="modulo_id" value="<?= $moduloId ?>">
                <input type="hidden" name="acao" value="salvar_modulo">

                <div class="campo">
                    <label for="titulo">Título</label>
                    <input type="text" id="titulo" name="titulo" required maxlength="100"
                           value="<?= e($modulo['titulo']) ?>">
                </div>

                <div class="campo">
                    <label for="descricao">Descrição curta</label>
                    <textarea id="descricao" name="descricao" rows="2"><?= e($modulo['descricao']) ?></textarea>
                </div>

                <?php if ($ehProva): ?>
                    <div class="campo">
                        <label for="nota_minima">Nota mínima para aprovar (%)</label>
                        <input type="number" id="nota_minima" name="nota_minima" min="1" max="100"
                               value="<?= (int) $modulo['nota_minima'] ?>">
                    </div>
                <?php else: ?>
                    <div class="campo">
                        <label for="teoria_texto">Explicação</label>
                        <textarea id="teoria_texto" name="teoria_texto" rows="10"
                                  placeholder="O conteúdo que o aluno lê antes de resolver as atividades."><?= e($modulo['teoria_texto']) ?></textarea>
                        <small class="ajuda-campo">
                            Separe parágrafos com uma linha em branco. É o texto que aparece no
                            alto da tela de desafio.
                        </small>
                    </div>

                    <div class="campo">
                        <label for="url_video">Vídeo (opcional)</label>
                        <input type="url" id="url_video" name="url_video"
                               placeholder="https://www.youtube.com/watch?v=..."
                               value="<?= e($modulo['url_video']) ?>">
                        <small class="ajuda-campo">
                            Cole o link do YouTube como ele aparece no navegador (ou o de
                            compartilhar, <code>youtu.be/…</code>). O sistema converte sozinho;
                            links de outros sites são recusados.
                        </small>
                    </div>
                <?php endif; ?>

                <button type="submit" class="botao botao-primario botao-bloco">Salvar módulo</button>
            </form>
        </section>

        <?php if (!$ehProva): ?>
            <section class="painel">
                <h3 class="painel-titulo"><?= icone('lampada') ?> Como o aluno vê</h3>
                <p class="aviso-suave">
                    Primeiro a explicação acima, depois as atividades na ordem da lista. O módulo
                    seguinte só abre quando <strong>todas</strong> as atividades daqui forem
                    concluídas.
                </p>
            </section>
        <?php endif; ?>

    </aside>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
