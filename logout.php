<?php
/**
 * logout.php — encerra a sessão do usuário.
 *
 * A versão anterior chamava session_unset() e session_destroy() sem nunca ter
 * iniciado a sessão (config/conexao.php só abre o banco), então o logout não
 * funcionava: o usuário continuava logado. Incluir auth.php resolve, porque é
 * ele quem chama session_start().
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// Só sai por POST com token (o botão Sair da barra lateral). Por GET, qualquer
// página de outro site com <img src=".../logout.php"> deslogava o aluno.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (usuarioLogado() ? 'inicio.php' : 'login.php'));
    exit;
}
exigirCsrf();

// Sair também desliga o "Lembrar-me" deste navegador — senão o próximo
// acesso entraria de novo sozinho, e "sair" não significaria nada.
esquecerLembrete($pdo);

$_SESSION = [];

// Remove também o cookie de sessão do navegador.
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: login.php');
exit;
