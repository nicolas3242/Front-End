<?php
/**
 * atividades.php — histórico completo do aluno ("Ver todas as atividades").
 * Paginado de 20 em 20; os eventos vêm de historicoDoUsuario().
 */

require_once __DIR__ . '/config/conexao.php';

$tituloPagina    = 'Atividade';
$subtituloPagina = 'Tudo o que você fez no DevFox, do mais recente ao mais antigo.';
require_once __DIR__ . '/includes/header.php';

const POR_PAGINA = 20;
// Limite de 10.000 páginas: sem teto, um número gigante na URL virava float na
// conta do deslocamento e derrubava a página com "Fatal error".
$pagina  = min(10000, max(1, (int) ($_GET['pagina'] ?? 1)));
// Pede um a mais para saber se existe próxima página sem contar tudo.
$eventos = historicoDoUsuario($pdo, (int) usuarioAtualId(), POR_PAGINA + 1, ($pagina - 1) * POR_PAGINA);
$temMais = count($eventos) > POR_PAGINA;
$eventos = array_slice($eventos, 0, POR_PAGINA);
?>

<nav class="trilha-navegacao"><a href="perfil.php">Perfil</a><?= icone('seta', 14) ?><strong>Atividade</strong></nav>

<section class="painel">
    <?php if (empty($eventos)): ?>
        <p class="aviso-suave">
            <?= $pagina > 1 ? 'Não há mais atividades.' : 'Nada por aqui ainda. <a href="trilhas.php">Comece uma trilha</a>.' ?>
        </p>
    <?php else: ?>
        <ul class="lista-eventos lista-eventos-completa">
            <?php foreach ($eventos as $ev): ?>
                <?php [$ic, $rotulo, $cor] = aparenciaDoEvento($ev['tipo']); ?>
                <li>
                    <span class="evento-icone evento-<?= $cor ?>"><?= icone($ic, 18) ?></span>
                    <div>
                        <strong><?= e($rotulo) ?>: <?= e($ev['texto']) ?></strong>
                        <small><?= e($ev['detalhe']) ?></small>
                    </div>
                    <time datetime="<?= e($ev['quando']) ?>" title="<?= e(date('d/m/Y H:i', strtotime($ev['quando']))) ?>">
                        <?= e(tempoRelativo($ev['quando'])) ?>
                    </time>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($pagina > 1 || $temMais): ?>
        <nav class="paginacao" aria-label="Páginas">
            <?php if ($pagina > 1): ?>
                <a href="atividades.php?pagina=<?= $pagina - 1 ?>" class="botao botao-secundario botao-pequeno">← Mais recentes</a>
            <?php endif; ?>
            <span>Página <?= $pagina ?></span>
            <?php if ($temMais): ?>
                <a href="atividades.php?pagina=<?= $pagina + 1 ?>" class="botao botao-secundario botao-pequeno">Mais antigas →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
