<?php
/**
 * cadastro.php — criação de conta de ALUNO.
 *
 * ⚠ REGRA DE SEGURANÇA: esta tela cria SOMENTE contas de aluno.
 *
 * A versão anterior tinha um <select> deixando o visitante escolher entre
 * "aluno" e "professor", sem verificação nenhuma — ou seja, qualquer pessoa
 * podia se cadastrar com o papel privilegiado e entrar no painel de conteúdo.
 * O tipo agora é fixado no servidor e o campo não existe mais no formulário.
 *
 * Contas de administrador são criadas direto no banco (ver database/README.md).
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/csrf.php';

if (usuarioLogado()) {
    header('Location: inicio.php');
    exit;
}

$erro    = '';
$sucesso = '';

/** Mesmas regras em todo lugar (Configurações usa 6 também). */
const CADASTRO_SENHA_MINIMA = 6;
/** O bcrypt só usa os primeiros 72 bytes: acima disso, o resto seria ignorado sem aviso. */
const CADASTRO_SENHA_MAXIMA = 72;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();

    // textoPost(): `nome[]=x` chega como array e derrubava a página (erro 500).
    $nome  = trim(preg_replace('/\s+/u', ' ', textoPost('nome')));
    $email = mb_strtolower(trim(textoPost('email')));
    $senha = textoPost('senha');

    if ($nome === '' || $email === '' || $senha === '') {
        $erro = 'Preencha todos os campos.';
    } elseif (mb_strlen($nome) < 2 || mb_strlen($nome) > 100) {
        // Limites das colunas do banco: sem esta checagem, um nome de 101
        // caracteres virava exceção do MySQL e erro 500.
        $erro = 'O nome precisa ter entre 2 e 100 caracteres.';
    } elseif (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Digite um e-mail válido.';
    } elseif (mb_strlen($senha) < CADASTRO_SENHA_MINIMA) {
        $erro = 'A senha precisa ter pelo menos ' . CADASTRO_SENHA_MINIMA . ' caracteres.';
    } elseif (strlen($senha) > CADASTRO_SENHA_MAXIMA) {
        $erro = 'A senha pode ter no máximo ' . CADASTRO_SENHA_MAXIMA . ' caracteres.';
    } else {
        // Mensagem neutra para e-mail repetido: "Este e-mail já está cadastrado"
        // confirmava, para qualquer um, quem tem conta. (Esconder por completo
        // exigiria confirmar o cadastro por e-mail — ver AI_HANDOFF.)
        $mensagemRepetido = 'Não foi possível criar a conta com esse e-mail. '
                          . 'Se você já tem cadastro, entre ou use "Esqueci minha senha".';

        $checagem = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $checagem->execute([$email]);

        if ($checagem->fetch()) {
            $erro = $mensagemRepetido;
        } else {
            try {
                $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'aluno')")
                    ->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);

                $sucesso = 'Conta criada! Redirecionando para o login…';
                header('refresh:2;url=login.php');
            } catch (PDOException $ex) {
                // Dois cadastros com o mesmo e-mail ao mesmo tempo: o segundo bate
                // na chave única do banco. Antes isso virava erro 500.
                if ($ex->getCode() !== '23000') {
                    throw $ex;
                }
                $erro = $mensagemRepetido;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevFox &mdash; Criar conta</title>
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

        <h1>Crie sua conta</h1>
        <p class="auth-subtitulo">Comece a aprender programa&ccedil;&atilde;o hoje mesmo.</p>

        <?php if ($erro !== ''): ?>
            <?= exibeMensagem($erro, 'erro') ?>
        <?php endif; ?>

        <?php if ($sucesso !== ''): ?>
            <?= exibeMensagem($sucesso) ?>
        <?php endif; ?>

        <form action="cadastro.php" method="POST" novalidate>
            <?= campoCsrf() ?>
            <div class="campo">
                <label for="nome">Nome completo</label>
                <div class="campo-icone">
                    <?= icone('perfil', 18) ?>
                    <input type="text" id="nome" name="nome" required autocomplete="name"
                           placeholder="Digite seu nome" maxlength="100" value="<?= e(textoPost('nome')) ?>">
                </div>
            </div>

            <div class="campo">
                <label for="email">E-mail</label>
                <div class="campo-icone">
                    <?= icone('email', 18) ?>
                    <input type="email" id="email" name="email" required autocomplete="email"
                           placeholder="Digite seu e-mail" maxlength="150" value="<?= e(textoPost('email')) ?>">
                </div>
            </div>

            <div class="campo">
                <label for="senha">Senha</label>
                <div class="campo-senha campo-icone">
                    <?= icone('cadeado', 18) ?>
                    <input type="password" id="senha" name="senha" required minlength="6"
                           autocomplete="new-password" placeholder="Pelo menos 6 caracteres">
                    <button type="button" class="botao-olho" data-alvo="senha"
                            aria-label="Mostrar ou ocultar a senha"><span class="olho-ver"><?= icone('olho', 18) ?></span><span class="olho-ocultar" hidden><?= icone('olho-cortado', 18) ?></span></button>
                </div>
            </div>

            <button type="submit" class="botao botao-primario botao-bloco botao-auth">
                Criar conta <?= icone('seta', 18) ?>
            </button>
        </form>

        <p class="auth-rodape">
            J&aacute; tem uma conta? <a href="login.php">Entrar</a>
        </p>
    </main>


</div>

<script src="<?= asset('js/auth.js') ?>"></script>
</body>
</html>
