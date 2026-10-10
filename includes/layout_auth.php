<?php
/**
 * includes/layout_auth.php — moldura das telas públicas (sem login):
 * esqueci a senha, redefinir senha e suporte. Mesmo visual do login, com a
 * ilustração de fundo, para o aluno não sentir que "saiu" do sistema.
 */

require_once __DIR__ . '/ui.php';

function abrirPaginaAuth(string $titulo): void
{
    ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevFox &mdash; <?= e($titulo) ?></title>
    <link rel="icon" type="image/png" href="assets/imagens/logo-marca.png">
    <link rel="stylesheet" href="<?= asset('css/styles.css') ?>">
</head>
<body>
<div class="auth auth-com-fundo">
    <main class="auth-formulario">
        <a href="login.php" class="auth-marca">
            <img src="assets/imagens/logo-marca.png" alt="DevFox">
            <span>Dev<b>Fox</b></span>
        </a>
    <?php
}

function fecharPaginaAuth(): void
{
    ?>
    </main>
</div>
<script src="<?= asset('js/auth.js') ?>"></script>
</body>
</html>
    <?php
}
