<?php
/**
 * conquistas.php — todas as conquistas, desbloqueadas e por desbloquear.
 * ("Ver todas" do Perfil.)
 */

require_once __DIR__ . '/config/conexao.php';

$tituloPagina    = 'Conquistas';
$subtituloPagina = 'Tudo o que você já desbloqueou — e o que ainda falta.';
require_once __DIR__ . '/includes/header.php';

$stmt = $pdo->prepare(
    "SELECT c.titulo, c.descricao, c.icone, c.raridade, cu.desbloqueada_em
     FROM conquistas c
     LEFT JOIN conquistas_usuarios cu ON cu.conquista_id = c.id AND cu.usuario_id = ?
     WHERE c.ativa = 1 OR cu.id IS NOT NULL      -- desativada só aparece para quem já tinha ganhado
     ORDER BY (cu.id IS NULL), cu.desbloqueada_em DESC, c.id"
);
$stmt->execute([usuarioAtualId()]);
$conquistas = $stmt->fetchAll();
$feitas = count(array_filter($conquistas, static fn(array $c): bool => $c['desbloqueada_em'] !== null));

// Ver perfil.php: para o admin as conquistas aparecem abertas, marcadas como
// prévia. A contagem "X de Y" continua sendo a real.
$previaAdmin = usuarioEhAdmin();
$raridades = ['bronze' => 'Bronze', 'prata' => 'Prata', 'ouro' => 'Ouro', 'especial' => 'Especial'];
?>

<nav class="trilha-navegacao"><a href="perfil.php">Perfil</a><?= icone('seta', 14) ?><strong>Conquistas</strong></nav>

<section class="painel">
    <div class="painel-inicio-cabecalho">
        <h2 class="painel-titulo-inicio">Suas conquistas</h2>
        <span class="selo"><?= $feitas ?> de <?= count($conquistas) ?></span>
    </div>
    <div class="barra-progresso conquistas-barra">
        <div class="barra-progresso-preenchida" style="width: <?= count($conquistas) ? round($feitas / count($conquistas) * 100) : 0 ?>%;"></div>
    </div>

    <div class="grade-conquistas grade-conquistas-perfil">
        <?php foreach ($conquistas as $c): ?>
            <?php
                $ganha  = $c['desbloqueada_em'] !== null;
                $aberta = $ganha || $previaAdmin;
            ?>
            <div class="conquista conquista-<?= e($c['raridade']) ?> <?= $aberta ? '' : 'conquista-bloqueada' ?>">
                <?= seloConquista((string) $c['icone'], (string) $c['raridade'], !$aberta) ?>
                <strong><?= e($c['titulo']) ?></strong>
                <small><?= e($c['descricao']) ?></small>
                <small class="conquista-data">
                    <?php if ($ganha): ?>
                        Desbloqueada em <?= date('d/m/Y', strtotime($c['desbloqueada_em'])) ?>
                    <?php elseif ($previaAdmin): ?>
                        <span class="selo selo-previa">prévia de admin</span> · <?= e($raridades[$c['raridade']] ?? '') ?>
                    <?php else: ?>
                        <?= e($raridades[$c['raridade']] ?? '') ?> · a desbloquear
                    <?php endif; ?>
                </small>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
