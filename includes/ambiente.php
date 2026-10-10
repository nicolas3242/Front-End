<?php
/**
 * includes/ambiente.php — fuso horário e sessão, iguais em todas as páginas.
 * -----------------------------------------------------------------------------
 * Incluído por auth.php e csrf.php (e, por eles, por todas as páginas).
 *
 * FUSO HORÁRIO
 * O PHP do XAMPP vem com `date.timezone = Europe/Berlin` no php.ini, e o MySQL
 * usa o fuso do Windows. Sem fixar os dois, o "hoje" da sequência de dias vira
 * às 19h/20h de Brasília e o "há 2 horas" do sino mente. Aqui os dois ficam em
 * America/Sao_Paulo: o PHP por date_default_timezone_set(), e a conexão do
 * MySQL por `SET time_zone` com o mesmo deslocamento — as colunas TIMESTAMP
 * são convertidas para esse fuso ao serem lidas e gravadas.
 *
 * SESSÃO
 * O cookie de sessão sai com HttpOnly (JavaScript não lê — uma falha de XSS
 * não rouba a sessão) e SameSite=Lax (o navegador não manda o cookie em POST
 * vindo de outro site). `use_strict_mode` recusa um id de sessão inventado por
 * quem tenta fixar a sessão da vítima.
 */

const FUSO_HORARIO = 'America/Sao_Paulo';

date_default_timezone_set(FUSO_HORARIO);

/** Põe a conexão do MySQL no mesmo fuso do PHP. Chamada uma vez por requisição. */
function sincronizarFusoDoBanco(PDO $pdo): void
{
    static $feito = false;
    if ($feito) {
        return;
    }
    // date('P') = deslocamento atual, ex.: "-03:00". Não depende das tabelas de
    // fuso do MySQL (que o XAMPP não instala), por isso não usa o nome do fuso.
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    $feito = true;
}

/** Abre a sessão com cookie seguro. Seguro chamar mais de uma vez. */
function iniciarSessao(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,          // até fechar o navegador ("Lembrar-me" é outro cookie)
        'path'     => '/',
        'secure'   => $https,     // só HTTPS quando o site estiver em HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    sincronizarFusoDoBanco($GLOBALS['pdo']);
}
