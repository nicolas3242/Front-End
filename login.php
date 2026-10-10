<?php
/**
 * login.php — autenticação por e-mail e senha.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/conquistas.php';
require_once __DIR__ . '/includes/admin.php';        // consumirFlash()

// Quem já está logado (ou tem "Lembrar-me" válido) não precisa ver esta tela.
if (usuarioLogado() || entrarPeloLembrete($pdo)) {
    header('Location: inicio.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();   // sem isto, outro site logava a vítima na conta DELE (login CSRF)

    $email = mb_strtolower(trim(textoPost('email')));
    $senha = textoPost('senha');          // senha não leva trim: espaço pode ser proposital

    if ($email === '' || $senha === '') {
        $erro = 'Preencha o e-mail e a senha.';
    } elseif (($espera = esperaDoLogin($pdo, $email)) > 0) {
        // Força bruta: 5 senhas erradas no mesmo e-mail em 15 minutos bloqueiam.
        $minutos = (int) ceil($espera / 60);
        $erro = 'Muitas tentativas com senha errada. Tente de novo em '
              . plural($minutos, 'minuto', 'minutos') . ' ou use "Esqueci minha senha".';
    } else {
        $stmt = $pdo->prepare("SELECT id, nome, email, senha, tipo, ativo FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            $senhaOk = password_verify($senha, $usuario['senha']);
        } else {
            // E-mail que não existe: gasta o mesmo tempo de um bcrypt mesmo assim.
            // Sem isto, "e-mail inexistente" respondia bem mais rápido que "senha
            // errada", e o tempo de resposta revelava quem tem conta.
            password_hash($senha, PASSWORD_DEFAULT);
            $senhaOk = false;
        }

        if ($usuario && $senhaOk && (int) $usuario['ativo'] !== 1) {
            // Só avisa depois da senha certa: para quem não sabe a senha, conta
            // desativada responde igual a senha errada.
            $erro = 'Esta conta está desativada. Se acha que é um engano, fale com o suporte.';
        } elseif ($usuario && $senhaOk) {
            limparErrosDeLogin($pdo, $email);
            iniciarSessaoUsuario($usuario);

            // "Lembrar-me": só com a caixa marcada. Ver includes/conta.php.
            if (!empty($_POST['lembrar'])) {
                criarLembrete($pdo, (int) $usuario['id']);
            }

            // A sequência de dias agora conta estudo (registrarSubmissao), não
            // login; conferir as conquistas aqui continua útil para quem ganhou
            // uma pelo caminho e ainda não viu o aviso.
            guardarConquistasNaSessao(verificarConquistas($pdo, (int) $usuario['id']));

            header('Location: inicio.php');
            exit;
        } else {
            registrarErroDeLogin($pdo, $email);

            // Mensagem propositalmente genérica: dizer "este e-mail não existe"
            // revelaria quais e-mails estão cadastrados na plataforma.
            $erro = 'E-mail ou senha incorretos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevFox &mdash; Entrar</title>
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

        <h1>Bem-vindo de volta!</h1>
        <p class="auth-subtitulo">Entre para continuar sua jornada de aprendizado.</p>

        <?php if ($erro === '' && isset($_GET['excluida'])): ?>
            <?= exibeMensagem('Sua conta foi excluída, com todos os seus dados. Obrigado por ter estudado com a gente.') ?>
        <?php endif; ?>
        <?php if ($erro === '' && isset($_GET['desativada'])): ?>
            <?= exibeMensagem('Sua conta foi desativada. Se acha que é um engano, fale com o suporte.', 'erro') ?>
        <?php endif; ?>
        <?php if ($erro !== ''): ?>
            <?= exibeMensagem($erro, 'erro') ?>
        <?php endif; ?>
        <?php if ($flash = consumirFlash()): ?>
            <?= exibeMensagem($flash[0], $flash[1]) ?>
        <?php endif; ?>

        <form action="login.php" method="POST" novalidate>
            <?= campoCsrf() ?>
            <div class="campo">
                <label for="email">E-mail</label>
                <div class="campo-icone">
                    <?= icone('email', 18) ?>
                    <input type="email" id="email" name="email" required autocomplete="email"
                           placeholder="Digite seu e-mail" value="<?= e(textoPost('email')) ?>">
                </div>
            </div>

            <div class="campo">
                <label for="senha">Senha</label>
                <div class="campo-senha campo-icone">
                    <?= icone('cadeado', 18) ?>
                    <input type="password" id="senha" name="senha" required
                           autocomplete="current-password" placeholder="Digite sua senha">
                    <button type="button" class="botao-olho" data-alvo="senha"
                            aria-label="Mostrar ou ocultar a senha"><span class="olho-ver"><?= icone('olho', 18) ?></span><span class="olho-ocultar" hidden><?= icone('olho-cortado', 18) ?></span></button>
                </div>
            </div>

            <div class="auth-opcoes">
                <label class="linha-check">
                    <input type="checkbox" name="lembrar" value="1" <?= !empty($_POST['lembrar']) ? 'checked' : '' ?>>
                    Lembrar-me
                </label>
                <a href="esqueci_senha.php">Esqueci minha senha</a>
            </div>

            <button type="submit" class="botao botao-primario botao-bloco botao-auth">
                Entrar <?= icone('seta', 18) ?>
            </button>
        </form>

        <div class="auth-separador">ou</div>

        <a href="cadastro.php" class="botao botao-contorno botao-bloco botao-auth">
            <?= icone('usuario-mais', 18) ?> Criar uma conta
        </a>

        <p class="auth-rodape">Precisa de ajuda? Fale com o <a href="suporte.php">suporte</a>.</p>
    </main>


</div>

<script src="<?= asset('js/auth.js') ?>"></script>
</body>
</html>
