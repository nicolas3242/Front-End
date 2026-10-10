<?php
/**
 * admin_busca.php — busca única do painel: alunos, trilhas, módulos e atividades.
 *
 * Existe porque, com o conteúdo crescendo, achar "aquela atividade do laço"
 * exigia abrir trilha por trilha. Aqui o admin digita um pedaço do nome e cai
 * direto na tela de edição certa.
 *
 * A busca é só LEITURA e não inventa permissão nenhuma: exigirAdmin() continua
 * valendo, e cada resultado leva para uma página que confere o acesso de novo.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';

exigirAdmin();

const RESULTADOS_POR_GRUPO = 8;

$termo = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 60));

$alunos = $trilhas = $modulos = $atividades = [];

if ($termo !== '') {
    // % e _ digitados valem como texto, não como curinga do LIKE.
    $like = '%' . addcslashes($termo, '%_\\') . '%';

    $busca = static function (PDO $pdo, string $sql, string $like): array {
        $stmt = $pdo->prepare($sql . ' LIMIT ' . RESULTADOS_POR_GRUPO);
        $stmt->execute(array_fill(0, substr_count($sql, '?'), $like));
        return $stmt->fetchAll();
    };

    $alunos = $busca($pdo,
        "SELECT id, nome, email, tipo, ativo, xp FROM usuarios
          WHERE nome LIKE ? OR email LIKE ? ORDER BY nome", $like);

    $trilhas = $busca($pdo,
        "SELECT id, titulo, publicada, arquivada FROM trilhas
          WHERE titulo LIKE ? OR descricao LIKE ? ORDER BY ordem, id", $like);

    $modulos = $busca($pdo,
        "SELECT m.id, m.titulo, m.tipo, t.titulo AS trilha
           FROM modulos m JOIN trilhas t ON t.id = m.trilha_id
          WHERE m.titulo LIKE ? ORDER BY t.ordem, m.ordem", $like);

    $atividades = $busca($pdo,
        "SELECT a.id, a.titulo, a.tipo, m.titulo AS modulo, t.titulo AS trilha
           FROM atividades a
           JOIN modulos m ON m.id = a.modulo_id
           JOIN trilhas t ON t.id = m.trilha_id
          WHERE a.titulo LIKE ? OR a.enunciado LIKE ? ORDER BY t.ordem, m.ordem, a.ordem", $like);
}

$total = count($alunos) + count($trilhas) + count($modulos) + count($atividades);

$tituloPagina    = 'Busca';
$subtituloPagina = $termo === ''
    ? 'Procure alunos, trilhas, módulos e atividades.'
    : plural($total, 'resultado encontrado', 'resultados encontrados') . ' para "' . $termo . '".';
require_once __DIR__ . '/includes/header.php';
?>

<?= abasAdmin('administracao.php', suporteNaoLidas($pdo)) ?>

<form method="get" action="admin_busca.php" class="busca-admin" role="search">
    <label for="q" class="sr-only">Buscar</label>
    <?= icone('busca', 18) ?>
    <input type="search" id="q" name="q" value="<?= e($termo) ?>" maxlength="60" required
           placeholder="Buscar alunos, trilhas, módulos, atividades…" autofocus>
    <button type="submit" class="botao botao-primario">Buscar</button>
</form>

<?php if ($termo === ''): ?>
    <p class="aviso-suave">Digite pelo menos uma palavra para buscar.</p>
<?php elseif ($total === 0): ?>
    <section class="painel vazio">
        <h3>Nada encontrado</h3>
        <p>Nenhum aluno, trilha, módulo ou atividade com "<?= e($termo) ?>".</p>
    </section>
<?php else: ?>

    <?php if ($alunos): ?>
        <section class="painel">
            <h3 class="painel-titulo"><?= icone('perfil') ?> Alunos <small class="contador"><?= count($alunos) ?></small></h3>
            <ul class="lista-resultados">
                <?php foreach ($alunos as $a): ?>
                    <li>
                        <a href="admin_aluno.php?id=<?= (int) $a['id'] ?>"><?= e($a['nome']) ?></a>
                        <small><?= e($a['email']) ?> · <?= number_format((int) $a['xp'], 0, ',', '.') ?> XP</small>
                        <?php if ($a['tipo'] === 'admin'): ?><span class="selo selo-admin">admin</span><?php endif; ?>
                        <?php if ((int) $a['ativo'] !== 1): ?><span class="selo selo-inativo">desativada</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($trilhas): ?>
        <section class="painel">
            <h3 class="painel-titulo"><?= icone('trilhas') ?> Trilhas <small class="contador"><?= count($trilhas) ?></small></h3>
            <ul class="lista-resultados">
                <?php foreach ($trilhas as $t): ?>
                    <li>
                        <a href="admin_trilha.php?id=<?= (int) $t['id'] ?>"><?= e($t['titulo']) ?></a>
                        <?php if ((int) $t['arquivada'] === 1): ?><span class="selo selo-inativo">arquivada</span>
                        <?php elseif ((int) $t['publicada'] === 1): ?><span class="selo selo-concluida">publicada</span>
                        <?php else: ?><span class="selo">rascunho</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($modulos): ?>
        <section class="painel">
            <h3 class="painel-titulo"><?= icone('livro') ?> Módulos <small class="contador"><?= count($modulos) ?></small></h3>
            <ul class="lista-resultados">
                <?php foreach ($modulos as $m): ?>
                    <li>
                        <a href="admin_modulo.php?id=<?= (int) $m['id'] ?>"><?= e($m['titulo']) ?></a>
                        <small><?= e($m['trilha']) ?> · <?= $m['tipo'] === 'prova' ? 'prova' : 'aula' ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($atividades): ?>
        <section class="painel">
            <h3 class="painel-titulo"><?= icone('codigo') ?> Atividades <small class="contador"><?= count($atividades) ?></small></h3>
            <ul class="lista-resultados">
                <?php foreach ($atividades as $a): ?>
                    <li>
                        <a href="admin_atividade.php?id=<?= (int) $a['id'] ?>"><?= e($a['titulo']) ?></a>
                        <small><?= e($a['trilha']) ?> · <?= e($a['modulo']) ?> · <?= e(rotuloTipoAtividade($a['tipo'])) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <p class="rodape-painel">Cada grupo mostra no máximo <?= RESULTADOS_POR_GRUPO ?> resultados. Refine a busca para ver outros.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
