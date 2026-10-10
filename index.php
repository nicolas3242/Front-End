<?php
/**
 * index.php — porta de entrada: manda para as trilhas ou para o login.
 */

require_once __DIR__ . '/includes/auth.php';

header('Location: ' . (usuarioLogado() ? 'inicio.php' : 'login.php'));
exit;
