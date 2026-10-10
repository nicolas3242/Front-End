<?php
/**
 * ranking.php — classificação dos alunos por XP.
 *
 * Só entram usuários do tipo 'aluno': quem administra o conteúdo não compete.
 * O XP mostrado aqui agora é confiável, porque o farm por reenvio da mesma
 * atividade foi eliminado (ver includes/auth.php, concluirAtividade()).
 */

require_once __DIR__ . '/config/conexao.php';
$tituloPagina    = 'Ranking';
$subtituloPagina = 'Os alunos com mais XP na plataforma.';

require_once __DIR__ . '/includes/header.php';

$alunoId = usuarioAtualId();

$ranking = $pdo->query(
    "SELECT u.id, u.nome, u.xp, u.sequencia_dias, u.ultimo_dia_ativo, u.foto_avatar,
            m.slug AS moldura_slug, t.nome AS titulo_loja
     FROM usuarios u
     LEFT JOIN itens_loja m ON m.id = u.moldura_id
     LEFT JOIN itens_loja t ON t.id = u.titulo_id
     WHERE u.tipo = 'aluno'
     ORDER BY u.xp DESC, u.nome ASC
     LIMIT 20"
)->fetchAll();

// Posição do usuário atual, mesmo que ele esteja fora do top 20.
$stmt = $pdo->prepare(
    "SELECT COUNT(*) + 1
     FROM usuarios
     WHERE tipo = 'aluno' AND xp > (SELECT xp FROM usuarios WHERE id = ?)"
);
$stmt->execute([$alunoId]);
$minhaPosicao = (int) $stmt->fetchColumn();
?>

<?php if (!$ehAdmin): ?>
    <p class="destaque-posicao">
        Sua posição: <strong>#<?= $minhaPosicao ?></strong>
        · <?= number_format($xpAtual, 0, ',', '.') ?> XP
    </p>
<?php endif; ?>

<?php if (empty($ranking)): ?>

<div class="painel vazio">
    <h3>Nenhum aluno cadastrado ainda</h3>
    <p>Assim que alguém ganhar XP, a classificação aparece aqui.</p>
</div>

<?php else: ?>

<div class="painel painel-tabela">
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Aluno</th>
                        <th>Nível</th>
                        <th>Sequência</th>
                        <th>XP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ranking as $posicao => $aluno): ?>
                        <tr class="<?= (int) $aluno['id'] === $alunoId ? 'linha-destacada' : '' ?>">
                            <td class="posicao"><?= $posicao + 1 ?></td>
                            <td>
                                <div class="ranking-aluno">
                                    <?= imgAvatar($aluno['foto_avatar'], 'avatar-pequeno', classeMoldura($aluno['moldura_slug'])) ?>
                                    <div>
                                        <?= e($aluno['nome']) ?>
                                        <?php if ((int) $aluno['id'] === $alunoId): ?>
                                            <span class="selo selo-voce">você</span>
                                        <?php endif; ?>
                                        <small class="ranking-titulo">
                                            <?= e($aluno['titulo_loja'] ?? tituloDoNivel(calcularNivel((int) $aluno['xp']))) ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td class="num"><?= calcularNivel((int) $aluno['xp']) ?></td>
                            <?php // Vigente: quem parou de estudar aparece com 0, não com o número antigo. ?>
                            <td class="num"><?= ofensivaVigente((int) $aluno['sequencia_dias'], $aluno['ultimo_dia_ativo']) ?></td>
                            <td class="num destaque-xp">
                                <?= number_format((int) $aluno['xp'], 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
