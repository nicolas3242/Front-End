<?php
/**
 * redefinir_senha.php — cria a senha nova a partir do link enviado por e-mail.
 *
 * O token é conferido duas vezes: ao abrir a página e de novo ao salvar (entre
 * uma coisa e outra ele pode ter expirado, ou sido usado em outra aba). Depois
 * de usado, não vale mais; e todos os "Lembrar-me" da conta são apagados —
 * quem pediu a redefinição pode estar justamente tirando um intruso da conta.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';        // CSRF, redirecionarCom()
require_once __DIR__ . '/includes/layout_auth.php';

const SENHA_MINIMA = 6;

$token  = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$pedido = redefinicaoValida($pdo, $token);
$erro   = '';

if ($pedido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $nova     = (string) ($_POST['senha'] ?? '');
    $confirma = (string) ($_POST['confirmacao'] ?? '');

    if (mb_strlen($nova) < SENHA_MINIMA) {
        $erro = 'A senha precisa ter pelo menos ' . SENHA_MINIMA . ' caracteres.';
    } elseif ($nova !== $confirma) {
        $erro = 'A confirmação não é igual à senha.';
    } else {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")
            ->execute([password_hash($nova, PASSWORD_DEFAULT), (int) $pedido['usuario_id']]);
        $pdo->prepare("UPDATE redefinicoes_senha SET usado_em = NOW() WHERE id = ?")
            ->execute([(int) $pedido['id']]);
        esquecerTodosOsLembretes($pdo, (int) $pedido['usuario_id']);
        $pdo->commit();

        redirecionarCom('login.php', 'Senha alterada. Entre com a nova senha.');
    }
}

abrirPaginaAuth('Nova senha');
?>
        <?php if (!$pedido): ?>
            <h1>Link inválido</h1>
            <p class="auth-subtitulo">
                Este link expirou, já foi usado ou está incompleto. Os links valem por
                <?= REDEFINICAO_MINUTOS ?> minutos e funcionam uma vez só.
            </p>
            <a href="esqueci_senha.php" class="botao botao-primario botao-bloco botao-auth">Pedir um novo link</a>
            <p class="auth-rodape"><a href="login.php">Voltar para o login</a></p>
        <?php else: ?>
            <h1>Crie uma nova senha</h1>
            <p class="auth-subtitulo">Conta de <?= e($pedido['email']) ?></p>

            <?php if ($erro !== ''): ?>
                <?= exibeMensagem($erro, 'erro') ?>
            <?php endif; ?>

            <form method="POST" novalidate>
                <?= campoCsrf() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="campo">
                    <label for="senha">Nova senha</label>
                    <div class="campo-senha campo-icone">
                        <?= icone('cadeado', 18) ?>
                        <input type="password" id="senha" name="senha" required minlength="<?= SENHA_MINIMA ?>"
                               autocomplete="new-password" placeholder="Pelo menos <?= SENHA_MINIMA ?> caracteres">
                        <button type="button" class="botao-olho" data-alvo="senha"
                                aria-label="Mostrar ou ocultar a senha"><span class="olho-ver"><?= icone('olho', 18) ?></span><span class="olho-ocultar" hidden><?= icone('olho-cortado', 18) ?></span></button>
                    </div>
                </div>
                <div class="campo">
                    <label for="confirmacao">Confirme a nova senha</label>
                    <div class="campo-icone">
                        <?= icone('cadeado', 18) ?>
                        <input type="password" id="confirmacao" name="confirmacao" required
                               minlength="<?= SENHA_MINIMA ?>" autocomplete="new-password">
                    </div>
                </div>
                <button type="submit" class="botao botao-primario botao-bloco botao-auth">
                    Salvar nova senha <?= icone('seta', 18) ?>
                </button>
            </form>
        <?php endif; ?>
<?php fecharPaginaAuth(); ?>
