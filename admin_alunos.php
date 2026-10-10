<?php
/**
 * admin_alunos.php — lista e busca de alunos (ADM-75).
 *
 * Busca por nome ou e-mail, filtro por tipo (aluno/admin) e situação da conta.
 * Cada linha leva à ficha completa (admin_aluno.php), onde ficam as ações.
 * "Última atividade" = última resposta enviada (tabela `submissoes`).
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';

exigirAdmin();

const ALUNOS_POR_PAGINA = 30;

$busca    = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100));
$tipo     = in_array($_GET['tipo'] ?? '', ['aluno', 'admin'], true) ? $_GET['tipo'] : '';
$situacao = in_array($_GET['situacao'] ?? '', ['ativos', 'desativados'], true) ? $_GET['situacao'] : '';
$pagina   = min(10000, max(1, (int) ($_GET['pagina'] ?? 1)));

// Filtros montados só com trechos fixos; os valores vão como parâmetros.
$onde = ['1 = 1'];
$params = [];
if ($busca !== '') {
    $onde[] = '(u.nome LIKE ? OR u.email LIKE ?)';
    // % e _ digitados valem como texto, não como curinga do LIKE.
    $termo = '%' . addcslashes($busca, '%_\\') . '%';
    array_push($params, $termo, $termo);
}
if ($tipo !== '') {
    $onde[] = 'u.tipo = ?';
    $params[] = $tipo;
}
if ($situacao !== '') {
    $onde[] = 'u.ativo = ' . ($situacao === 'ativos' ? '1' : '0');
}
$where = implode(' AND ', $onde);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios u WHERE $where");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT u.id, u.nome, u.email, u.tipo, u.ativo, u.xp, u.sequencia_dias, u.ultimo_dia_ativo,
            u.foto_avatar, u.criado_em, m.slug AS moldura_slug,
            (SELECT MAX(s.enviado_em) FROM submissoes s WHERE s.aluno_id = u.id) AS ultima_resposta,
            (SELECT COUNT(*) FROM atividades_concluidas ac WHERE ac.aluno_id = u.id) AS concluidas
     FROM usuarios u
     LEFT JOIN itens_loja m ON m.id = u.moldura_id
     WHERE $where
     ORDER BY u.criado_em DESC, u.id DESC
     LIMIT " . ALUNOS_POR_PAGINA . " OFFSET " . (($pagina - 1) * ALUNOS_POR_PAGINA)
);
$stmt->execute($params);
$pessoas = $stmt->fetchAll();
$paginas = max(1, (int) ceil($total / ALUNOS_POR_PAGINA));

/** Link desta lista mantendo os filtros atuais. */
function linkLista(array $mudar): string
{
    $atual = array_filter([
        'q' => $_GET['q'] ?? '', 'tipo' => $_GET['tipo'] ?? '',
        'situacao' => $_GET['situacao'] ?? '', 'pagina' => $_GET['pagina'] ?? '',
    ], static fn($v) => $v !== '');
    return 'admin_alunos.php?' . http_build_query(array_merge($atual, $mudar));
}

$tituloPagina    = 'Alunos';
$subtituloPagina = plural($total, 'conta encontrada', 'contas encontradas') . '.';
require_once __DIR__ . '/includes/header.php';
?>

<?= abasAdmin('admin_alunos.php', suporteNaoLidas($pdo)) ?>

<form method="get" class="filtros-admin" role="search">
    <div class="campo">
        <label for="q">Buscar por nome ou e-mail</label>
        <input type="search" id="q" name="q" value="<?= e($busca) ?>" placeholder="Ex.: maria ou @gmail.com">
    </div>
    <div class="campo">
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
            <option value="">Todos</option>
            <option value="aluno" <?= $tipo === 'aluno' ? 'selected' : '' ?>>Alunos</option>
            <option value="admin" <?= $tipo === 'admin' ? 'selected' : '' ?>>Administradores</option>
        </select>
    </div>
    <div class="campo">
        <label for="situacao">Situação</label>
        <select id="situacao" name="situacao">
            <option value="">Todas</option>
            <option value="ativos" <?= $situacao === 'ativos' ? 'selected' : '' ?>>Ativas</option>
            <option value="desativados" <?= $situacao === 'desativados' ? 'selected' : '' ?>>Desativadas</option>
        </select>
    </div>
    <button type="submit" class="botao botao-primario"><?= icone('busca', 16) ?> Filtrar</button>
    <?php if ($busca !== '' || $tipo !== '' || $situacao !== ''): ?>
        <a href="admin_alunos.php" class="botao botao-secundario">Limpar</a>
    <?php endif; ?>
</form>

<section class="painel">
    <?php if (empty($pessoas)): ?>
        <p class="aviso-suave">Nenhuma conta com esses filtros.</p>
    <?php else: ?>
        <div class="tabela-admin-envolve">
            <table class="tabela-admin" id="tabelaAlunos">
                <thead>
                    <tr>
                        <th>Pessoa</th>
                        <th>Situação</th>
                        <th class="num">Nível</th>
                        <th class="num">XP</th>
                        <th class="num">Sequência</th>
                        <th class="num">Concluídas</th>
                        <th>Última resposta</th>
                        <th>Cadastro</th>
                        <th><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pessoas as $p): ?>
                        <tr class="<?= (int) $p['ativo'] === 1 ? '' : 'linha-inativa' ?>">
                            <td>
                                <div class="celula-pessoa">
                                    <?= imgAvatar($p['foto_avatar'], 'avatar-pequeno', classeMoldura($p['moldura_slug'])) ?>
                                    <div><strong><?= e($p['nome']) ?></strong><small><?= e($p['email']) ?></small></div>
                                </div>
                            </td>
                            <td>
                                <?php if ($p['tipo'] === 'admin'): ?><span class="selo selo-admin">admin</span><?php endif; ?>
                                <?php if ((int) $p['ativo'] !== 1): ?><span class="selo selo-inativo">desativada</span><?php endif; ?>
                                <?php if ($p['tipo'] !== 'admin' && (int) $p['ativo'] === 1): ?><span class="selo">aluno</span><?php endif; ?>
                            </td>
                            <td class="num"><?= calcularNivel((int) $p['xp']) ?></td>
                            <td class="num"><?= number_format((int) $p['xp'], 0, ',', '.') ?></td>
                            <td class="num"><?= ofensivaVigente((int) $p['sequencia_dias'], $p['ultimo_dia_ativo']) ?></td>
                            <td class="num"><?= (int) $p['concluidas'] ?></td>
                            <td><?= $p['ultima_resposta'] ? e(tempoRelativo($p['ultima_resposta'])) : '<span class="aviso-suave">nunca</span>' ?></td>
                            <td><?= e(date('d/m/Y', strtotime($p['criado_em']))) ?></td>
                            <td class="num"><a href="admin_aluno.php?id=<?= (int) $p['id'] ?>" class="botao botao-secundario botao-pequeno">Ver ficha</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($paginas > 1): ?>
            <nav class="paginacao" aria-label="Páginas">
                <?php if ($pagina > 1): ?><a href="<?= e(linkLista(['pagina' => $pagina - 1])) ?>" class="botao botao-secundario botao-pequeno">Anterior</a><?php endif; ?>
                <span>Página <?= $pagina ?> de <?= $paginas ?></span>
                <?php if ($pagina < $paginas): ?><a href="<?= e(linkLista(['pagina' => $pagina + 1])) ?>" class="botao botao-secundario botao-pequeno">Próxima</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
