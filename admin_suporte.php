<?php
/**
 * admin_suporte.php — caixa de entrada das mensagens de suporte.
 *
 * Lista as mensagens (não lidas primeiro), com resposta por e-mail em um
 * clique (mailto) e marcar como lida/não lida. Só admin.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';

exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $id   = (int) ($_POST['id'] ?? 0);
    $lida = ($_POST['acao'] ?? '') === 'lida' ? 1 : 0;
    $stmt = $pdo->prepare("SELECT nome FROM mensagens_suporte WHERE id = ?");
    $stmt->execute([$id]);
    $pdo->prepare("UPDATE mensagens_suporte SET lida = ? WHERE id = ?")->execute([$lida, $id]);
    registrarAcaoAdmin($pdo, $lida ? 'suporte.lida' : 'suporte.nao_lida', (string) $stmt->fetchColumn());
    // Volta para a mesma lista filtrada.
    $filtroVolta = array_intersect_key($_GET, array_flip(['assunto', 'estado']));
    redirecionarCom('admin_suporte.php' . ($filtroVolta ? '?' . http_build_query($filtroVolta) : ''),
        $lida ? 'Mensagem marcada como lida.' : 'Mensagem marcada como não lida.');
}

$flash = consumirFlash();
$rotulos = ['conteudo' => 'Conteúdo', 'tecnico' => 'Técnico', 'conta' => 'Conta', 'outro' => 'Outro'];

// Filtros (ADM-91): assunto e lida/não lida. Os valores são conferidos contra
// listas fixas antes de entrar no SQL.
$assunto = array_key_exists($_GET['assunto'] ?? '', $rotulos) ? $_GET['assunto'] : '';
$estado  = in_array($_GET['estado'] ?? '', ['nao_lidas', 'lidas'], true) ? $_GET['estado'] : '';
$onde = ['1 = 1'];
$params = [];
if ($assunto !== '') { $onde[] = 'assunto = ?'; $params[] = $assunto; }
if ($estado !== '')  { $onde[] = 'lida = ' . ($estado === 'lidas' ? '1' : '0'); }

$stmt = $pdo->prepare(
    "SELECT id, nome, email, assunto, mensagem, lida, criado_em, usuario_id
     FROM mensagens_suporte WHERE " . implode(' AND ', $onde) . " ORDER BY lida ASC, criado_em DESC LIMIT 200"
);
$stmt->execute($params);
$mensagens = $stmt->fetchAll();
$naoLidas = suporteNaoLidas($pdo);

$tituloPagina    = 'Suporte';
$subtituloPagina = $naoLidas === 1 ? '1 mensagem não lida.' : $naoLidas . ' mensagens não lidas.';
require_once __DIR__ . '/includes/header.php';
?>

<?= abasAdmin('admin_suporte.php', $naoLidas) ?>

<form method="get" class="filtros-admin">
    <div class="campo">
        <label for="assunto">Assunto</label>
        <select id="assunto" name="assunto">
            <option value="">Todos</option>
            <?php foreach ($rotulos as $v => $r): ?>
                <option value="<?= e($v) ?>" <?= $assunto === $v ? 'selected' : '' ?>><?= e($r) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo">
        <label for="estado">Situação</label>
        <select id="estado" name="estado">
            <option value="">Todas</option>
            <option value="nao_lidas" <?= $estado === 'nao_lidas' ? 'selected' : '' ?>>Não lidas</option>
            <option value="lidas" <?= $estado === 'lidas' ? 'selected' : '' ?>>Lidas</option>
        </select>
    </div>
    <button type="submit" class="botao botao-primario">Filtrar</button>
    <?php if ($assunto !== '' || $estado !== ''): ?><a href="admin_suporte.php" class="botao botao-secundario">Limpar</a><?php endif; ?>
</form>

<?php if ($flash): ?>
    <?= exibeMensagem($flash[0], $flash[1]) ?>
<?php endif; ?>

<?php if (empty($mensagens)): ?>
    <div class="painel vazio">
        <h3>Nenhuma mensagem<?= ($assunto !== '' || $estado !== '') ? ' com esses filtros' : '' ?></h3>
        <p>Quando alguém usar "Fale com o suporte", a mensagem aparece aqui.</p>
    </div>
<?php endif; ?>

<div class="lista-suporte">
    <?php foreach ($mensagens as $m): ?>
        <article class="painel mensagem-suporte <?= (int) $m['lida'] === 0 ? 'mensagem-nao-lida' : '' ?>">
            <div class="mensagem-suporte-topo">
                <div>
                    <strong><?= e($m['nome']) ?></strong>
                    <small><?= e($m['email']) ?><?= $m['usuario_id'] ? ' · aluno cadastrado' : '' ?></small>
                </div>
                <span class="selo"><?= e($rotulos[$m['assunto']] ?? $m['assunto']) ?></span>
                <time><?= e(date('d/m/Y H:i', strtotime($m['criado_em']))) ?></time>
            </div>
            <p class="mensagem-suporte-texto"><?= nl2br(e($m['mensagem'])) ?></p>
            <div class="mensagem-suporte-acoes">
                <a class="botao botao-secundario botao-pequeno"
                   href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: sua mensagem ao DevFox') ?>">
                    <?= icone('email', 14) ?> Responder por e-mail
                </a>
                <form method="post">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                    <input type="hidden" name="acao" value="<?= (int) $m['lida'] === 0 ? 'lida' : 'nao_lida' ?>">
                    <button type="submit" class="botao botao-secundario botao-pequeno">
                        <?= (int) $m['lida'] === 0 ? 'Marcar como lida' : 'Marcar como não lida' ?>
                    </button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
