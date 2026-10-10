<?php
/**
 * admin_registro.php — registro das ações dos administradores (ADM-05).
 *
 * Responde "quem publicou, apagou ou desativou isso, e quando?". As linhas são
 * gravadas por registrarAcaoAdmin() (includes/admin.php) em todo POST da
 * Administração que altera dados. É só leitura: ninguém edita nem apaga o
 * registro pela tela — um histórico que o próprio admin pudesse limpar não
 * serviria para nada.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';

exigirAdmin();

const REGISTRO_POR_PAGINA = 50;

$areas  = ['trilha' => 'Trilhas', 'modulo' => 'Módulos', 'atividade' => 'Atividades', 'aluno' => 'Alunos',
           'conquista' => 'Conquistas', 'loja' => 'Loja', 'suporte' => 'Suporte'];
$area   = array_key_exists($_GET['area'] ?? '', $areas) ? $_GET['area'] : '';
$pagina = min(10000, max(1, (int) ($_GET['pagina'] ?? 1)));

$onde   = $area !== '' ? 'WHERE r.acao LIKE ?' : '';
$params = $area !== '' ? [$area . '.%'] : [];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_registro r $onde");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT r.acao, r.alvo, r.criado_em, u.nome AS admin
     FROM admin_registro r LEFT JOIN usuarios u ON u.id = r.admin_id
     $onde ORDER BY r.criado_em DESC, r.id DESC
     LIMIT " . REGISTRO_POR_PAGINA . " OFFSET " . (($pagina - 1) * REGISTRO_POR_PAGINA)
);
$stmt->execute($params);
$linhas  = $stmt->fetchAll();
$paginas = max(1, (int) ceil($total / REGISTRO_POR_PAGINA));

$tituloPagina    = 'Registro de ações';
$subtituloPagina = 'O que cada administrador fez, e quando.';
require_once __DIR__ . '/includes/header.php';
?>

<?= abasAdmin('admin_registro.php', suporteNaoLidas($pdo)) ?>

<form method="get" class="filtros-admin">
    <div class="campo">
        <label for="area">Área</label>
        <select id="area" name="area" onchange="this.form.submit()">
            <option value="">Todas</option>
            <?php foreach ($areas as $v => $r): ?>
                <option value="<?= e($v) ?>" <?= $area === $v ? 'selected' : '' ?>><?= e($r) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <noscript><button type="submit" class="botao botao-primario">Filtrar</button></noscript>
</form>

<section class="painel">
    <?php if (empty($linhas)): ?>
        <p class="aviso-suave">Nenhuma ação registrada<?= $area !== '' ? ' nesta área' : '' ?> ainda.</p>
    <?php else: ?>
        <div class="tabela-admin-envolve">
            <table class="tabela-admin" id="tabelaRegistro">
                <thead><tr><th>Quando</th><th>Quem</th><th>O que fez</th></tr></thead>
                <tbody>
                <?php foreach ($linhas as $l): ?>
                    <tr>
                        <td><time datetime="<?= e($l['criado_em']) ?>"><?= e(date('d/m/Y H:i', strtotime($l['criado_em']))) ?></time></td>
                        <td><?= $l['admin'] !== null ? e($l['admin']) : '<span class="aviso-suave">conta excluída</span>' ?></td>
                        <td><?= e(rotuloAcaoAdmin($l['acao'])) ?><?= $l['alvo'] !== null ? ' <strong>' . e($l['alvo']) . '</strong>' : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($paginas > 1): ?>
            <nav class="paginacao" aria-label="Páginas">
                <?php $q = static fn(int $p): string => 'admin_registro.php?' . http_build_query(array_filter(['area' => $area, 'pagina' => $p])); ?>
                <?php if ($pagina > 1): ?><a href="<?= e($q($pagina - 1)) ?>" class="botao botao-secundario botao-pequeno">Mais novas</a><?php endif; ?>
                <span>Página <?= $pagina ?> de <?= $paginas ?></span>
                <?php if ($pagina < $paginas): ?><a href="<?= e($q($pagina + 1)) ?>" class="botao botao-secundario botao-pequeno">Mais antigas</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
