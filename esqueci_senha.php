<?php
/**
 * esqueci_senha.php — pede um link para criar uma senha nova.
 *
 * Decisões de segurança:
 *   - A resposta é SEMPRE a mesma, exista o e-mail ou não. Dizer "e-mail não
 *     cadastrado" transformaria esta tela num detector de contas.
 *   - O link leva um token aleatório de 64 caracteres; o banco guarda só o
 *     hash dele, com validade de 1 hora e uso único (includes/conta.php).
 *   - Uma espera de 60 s entre pedidos na mesma sessão evita o envio em massa.
 *
 * Sem servidor de e-mail (XAMPP), a mensagem é gravada em logs/emails.log —
 * ver EMAIL_MODO em includes/conta.php.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';        // CSRF
require_once __DIR__ . '/includes/layout_auth.php';

$enviado = false;
$erro    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $email = trim((string) ($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (($espera = segundosParaNovoEnvio('senha')) > 0) {
        $erro = 'Aguarde ' . plural($espera, 'segundo', 'segundos') . ' para pedir outro link.';
    } else {
        registrarEnvio('senha');

        $stmt = $pdo->prepare("SELECT id, nome, email FROM usuarios WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            enviarLinkDeSenha($pdo, $usuario);
        }

        $enviado = true;
    }
}

abrirPaginaAuth('Esqueci minha senha');
?>
        <h1>Esqueci minha senha</h1>
        <p class="auth-subtitulo">Informe o e-mail da sua conta e enviaremos um link para criar uma senha nova.</p>

        <?php if ($enviado): ?>
            <?= exibeMensagem('Se este e-mail estiver cadastrado, enviamos um link para criar uma nova senha. '
                            . 'Ele vale por ' . REDEFINICAO_MINUTOS . ' minutos.') ?>
            <?php if (EMAIL_MODO === 'log'): ?>
                <p class="aviso-suave aviso-dev">
                    <?= icone('lampada', 14) ?>
                    Ambiente de desenvolvimento: sem servidor de e-mail, a mensagem foi gravada em
                    <code>logs/emails.log</code>, na pasta do projeto.
                </p>
            <?php endif; ?>
            <a href="login.php" class="botao botao-contorno botao-bloco botao-auth">Voltar para o login</a>
        <?php else: ?>
            <?php if ($erro !== ''): ?>
                <?= exibeMensagem($erro, 'erro') ?>
            <?php endif; ?>

            <form method="POST" novalidate>
                <?= campoCsrf() ?>
                <div class="campo">
                    <label for="email">E-mail</label>
                    <div class="campo-icone">
                        <?= icone('email', 18) ?>
                        <input type="email" id="email" name="email" required autocomplete="email"
                               placeholder="Digite seu e-mail" value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <button type="submit" class="botao botao-primario botao-bloco botao-auth">
                    Enviar link <?= icone('seta', 18) ?>
                </button>
            </form>

            <p class="auth-rodape"><a href="login.php">Voltar para o login</a></p>
        <?php endif; ?>
<?php fecharPaginaAuth(); ?>
