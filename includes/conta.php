<?php
/**
 * includes/conta.php — "Lembrar-me", "Esqueci minha senha" e envio de e-mail.
 * -----------------------------------------------------------------------------
 * As três coisas lidam com um SEGREDO que sai do servidor (um cookie ou um
 * link por e-mail). A regra é a mesma nas duas: o banco guarda só o HASH do
 * segredo. Quem conseguir ler o banco não consegue entrar na conta de ninguém.
 */

require_once __DIR__ . '/auth.php';

/** Quanto tempo o "Lembrar-me" mantém o usuário logado. */
const LEMBRAR_DIAS = 30;
const LEMBRAR_COOKIE = 'devfox_lembrar';

/** Validade do link de redefinição de senha. */
const REDEFINICAO_MINUTOS = 60;

/**
 * Como o e-mail sai:
 *   'log'  → grava em logs/emails.log (desenvolvimento, sem servidor de e-mail);
 *   'mail' → usa mail() do PHP (hospedagem com e-mail configurado).
 * Pode ser definido em config/conexao.php; o padrão é 'log'.
 */
if (!defined('EMAIL_MODO')) {
    define('EMAIL_MODO', 'log');
}


/* -----------------------------------------------------------------------------
 * Lembrar-me
 * -------------------------------------------------------------------------- */

function gravarCookieLembrar(string $valor, int $expira): void
{
    setcookie(LEMBRAR_COOKIE, $valor, [
        'expires'  => $expira,
        'path'     => '/',
        'httponly' => true,                       // JavaScript não lê
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

/** Cria um token novo para o usuário e manda o cookie. */
function criarLembrete(PDO $pdo, int $usuarioId): void
{
    $seletor   = bin2hex(random_bytes(12));       // 24 caracteres, identifica a linha
    $validador = bin2hex(random_bytes(32));       // o segredo; só o hash vai para o banco
    $expira    = time() + LEMBRAR_DIAS * 86400;

    $pdo->prepare(
        "INSERT INTO lembrar_tokens (usuario_id, seletor, validador_hash, expira_em) VALUES (?, ?, ?, ?)"
    )->execute([$usuarioId, $seletor, hash('sha256', $validador), date('Y-m-d H:i:s', $expira)]);

    gravarCookieLembrar($seletor . ':' . $validador, $expira);
}

/** Apaga o token deste navegador (logout). */
function esquecerLembrete(PDO $pdo): void
{
    $cookie = $_COOKIE[LEMBRAR_COOKIE] ?? '';
    if (is_string($cookie) && str_contains($cookie, ':')) {
        [$seletor] = explode(':', $cookie, 2);
        $pdo->prepare("DELETE FROM lembrar_tokens WHERE seletor = ?")->execute([$seletor]);
    }
    gravarCookieLembrar('', time() - 3600);
}

/** Apaga TODOS os tokens do usuário — ao trocar a senha, todo navegador "lembrado" sai. */
function esquecerTodosOsLembretes(PDO $pdo, int $usuarioId): void
{
    $pdo->prepare("DELETE FROM lembrar_tokens WHERE usuario_id = ?")->execute([$usuarioId]);
}

/**
 * Se não há sessão mas há cookie de "Lembrar-me" válido, faz o login.
 *
 * Cada uso troca o token por um novo (rotação): um cookie copiado só vale até
 * o dono voltar ao site. Se o seletor existe mas o validador não bate, alguém
 * está tentando um cookie forjado ou roubado — todos os tokens daquele usuário
 * são apagados por precaução.
 *
 * @return bool true se entrou
 */
function entrarPeloLembrete(PDO $pdo): bool
{
    if (usuarioLogado()) {
        return true;
    }

    $cookie = $_COOKIE[LEMBRAR_COOKIE] ?? '';
    if (!is_string($cookie) || !preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $cookie, $m)) {
        return false;
    }
    [, $seletor, $validador] = $m;

    $stmt = $pdo->prepare(
        "SELECT lt.usuario_id, lt.validador_hash, lt.expira_em, u.nome, u.tipo
         FROM lembrar_tokens lt
         JOIN usuarios u ON u.id = lt.usuario_id
         WHERE lt.seletor = ? AND u.ativo = 1"
    );
    $stmt->execute([$seletor]);
    $linha = $stmt->fetch();

    if (!$linha) {
        gravarCookieLembrar('', time() - 3600);
        return false;
    }

    if (!hash_equals($linha['validador_hash'], hash('sha256', $validador))) {
        esquecerTodosOsLembretes($pdo, (int) $linha['usuario_id']);
        gravarCookieLembrar('', time() - 3600);
        return false;
    }

    $pdo->prepare("DELETE FROM lembrar_tokens WHERE seletor = ?")->execute([$seletor]);

    if (strtotime($linha['expira_em']) < time()) {
        gravarCookieLembrar('', time() - 3600);
        return false;
    }

    iniciarSessaoUsuario([
        'id'   => $linha['usuario_id'],
        'nome' => $linha['nome'],
        'tipo' => $linha['tipo'],
    ]);
    criarLembrete($pdo, (int) $linha['usuario_id']);

    return true;
}


/* -----------------------------------------------------------------------------
 * Esqueci minha senha
 * -------------------------------------------------------------------------- */

/**
 * Cria um pedido de redefinição e devolve o token em texto (para o link).
 * Pedidos anteriores do mesmo usuário deixam de valer.
 */
function criarRedefinicao(PDO $pdo, int $usuarioId): string
{
    $token = bin2hex(random_bytes(32));

    $pdo->prepare("UPDATE redefinicoes_senha SET usado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL")
        ->execute([$usuarioId]);
    $pdo->prepare(
        "INSERT INTO redefinicoes_senha (usuario_id, token_hash, expira_em)
         VALUES (?, ?, NOW() + INTERVAL " . (int) REDEFINICAO_MINUTOS . " MINUTE)"
    )->execute([$usuarioId, hash('sha256', $token)]);

    return $token;
}

/** Pedido válido (existe, não usado, não expirado) para este token, ou null. */
function redefinicaoValida(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT r.id, r.usuario_id, u.nome, u.email
         FROM redefinicoes_senha r
         JOIN usuarios u ON u.id = r.usuario_id
         WHERE r.token_hash = ? AND r.usado_em IS NULL AND r.expira_em > NOW()"
    );
    $stmt->execute([hash('sha256', $token)]);

    return $stmt->fetch() ?: null;
}

/**
 * Endereço base do site para montar links de e-mail.
 *
 * Em produção, defina URL_BASE em config/conexao.php. Montar o link a partir
 * do cabeçalho Host da requisição é uma falha conhecida: um atacante pede a
 * redefinição da conta da vítima com um Host falso, e o e-mail legítimo sai
 * com um link apontando para o site dele.
 */
function urlBase(): string
{
    if (defined('URL_BASE')) {
        return rtrim(URL_BASE, '/');
    }

    $pasta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return 'http://localhost' . $pasta;
}


/* -----------------------------------------------------------------------------
 * E-mail
 * -------------------------------------------------------------------------- */

/** Caminho do arquivo onde os e-mails ficam no modo 'log'. */
function arquivoDeEmails(): string
{
    return dirname(__DIR__) . '/logs/emails.log';
}

/** Envia (ou registra, no modo 'log') um e-mail de texto simples. */
function enviarEmail(string $para, string $assunto, string $texto): bool
{
    if (EMAIL_MODO === 'mail') {
        $cabecalhos = "Content-Type: text/plain; charset=UTF-8\r\nFrom: DevFox <nao-responda@devfox.local>";
        return @mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $texto, $cabecalhos);
    }

    $pasta = dirname(arquivoDeEmails());
    if (!is_dir($pasta)) {
        @mkdir($pasta, 0775, true);
    }

    $registro = str_repeat('=', 60) . "\n"
              . 'Data: ' . date('d/m/Y H:i:s') . "\nPara: {$para}\nAssunto: {$assunto}\n\n{$texto}\n";

    return @file_put_contents(arquivoDeEmails(), $registro, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * Espera mínima entre dois envios do mesmo formulário, por sessão.
 * Não substitui um limite por IP, mas impede o clique repetido e o robô ingênuo.
 *
 * @return int segundos que ainda faltam (0 = pode enviar)
 */
function segundosParaNovoEnvio(string $chave, int $intervalo = 60): int
{
    $ultimo = (int) ($_SESSION['ultimo_envio'][$chave] ?? 0);
    return max(0, $ultimo + $intervalo - time());
}

function registrarEnvio(string $chave): void
{
    $_SESSION['ultimo_envio'][$chave] = time();
}


/* -----------------------------------------------------------------------------
 * Limite de tentativas de login (v9, tabela tentativas_login)
 * -------------------------------------------------------------------------- */

/** Senhas erradas permitidas por e-mail dentro da janela. */
const LOGIN_ERROS_POR_EMAIL = 5;
/** Teto por IP. Alto de propósito: uma sala de aula inteira sai pelo mesmo IP. */
const LOGIN_ERROS_POR_IP = 50;
/** Janela da contagem, em minutos. */
const LOGIN_JANELA_MINUTOS = 15;

function ipDoCliente(): string
{
    // REMOTE_ADDR e não X-Forwarded-For: esse cabeçalho o próprio atacante escreve.
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/**
 * Segundos que faltam para este e-mail (ou IP) poder tentar de novo; 0 = liberado.
 *
 * O bloqueio vale para qualquer e-mail digitado, exista ele ou não — senão o
 * bloqueio em si revelaria quais contas existem.
 */
function esperaDoLogin(PDO $pdo, string $email): int
{
    $maior = 0;
    foreach ([['email', $email, LOGIN_ERROS_POR_EMAIL], ['ip', ipDoCliente(), LOGIN_ERROS_POR_IP]] as [$coluna, $valor, $limite]) {
        // A conta de tempo fica toda no MySQL (NOW() e criado_em no mesmo relógio).
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS erros,
                    TIMESTAMPDIFF(SECOND, NOW(), MIN(criado_em) + INTERVAL " . LOGIN_JANELA_MINUTOS . " MINUTE) AS resta
             FROM (SELECT criado_em FROM tentativas_login
                   WHERE $coluna = ? AND criado_em > NOW() - INTERVAL " . LOGIN_JANELA_MINUTOS . " MINUTE
                   ORDER BY criado_em DESC LIMIT $limite) AS ultimas"
        );
        $stmt->execute([$valor]);
        $linha = $stmt->fetch();
        if ((int) $linha['erros'] >= $limite) {
            $maior = max($maior, (int) $linha['resta'], 1);
        }
    }
    return $maior;
}

/** Anota uma senha errada (e aproveita para apagar o que tem mais de 1 dia). */
function registrarErroDeLogin(PDO $pdo, string $email): void
{
    $pdo->prepare("INSERT INTO tentativas_login (email, ip) VALUES (?, ?)")
        ->execute([substr($email, 0, 150), ipDoCliente()]);
    $pdo->exec("DELETE FROM tentativas_login WHERE criado_em < NOW() - INTERVAL 1 DAY");
}

/** Login certo zera a contagem daquele e-mail. */
function limparErrosDeLogin(PDO $pdo, string $email): void
{
    $pdo->prepare("DELETE FROM tentativas_login WHERE email = ?")->execute([$email]);
}



/* -----------------------------------------------------------------------------
 * Contas (v10) — usados pelo "Esqueci minha senha", por Configurações e pela
 * ficha do aluno na Administração
 * -------------------------------------------------------------------------- */

/**
 * Cria um pedido de redefinição e manda o link por e-mail.
 * @param array $usuario precisa de id, nome e email
 */
function enviarLinkDeSenha(PDO $pdo, array $usuario): void
{
    $token = criarRedefinicao($pdo, (int) $usuario['id']);
    $link  = urlBase() . '/redefinir_senha.php?token=' . $token;

    enviarEmail($usuario['email'], 'DevFox — redefinição de senha',
        "Olá, {$usuario['nome']}!\n\n"
      . "Recebemos um pedido para criar uma nova senha na sua conta do DevFox.\n"
      . "Abra o link abaixo em até " . REDEFINICAO_MINUTOS . " minutos:\n\n{$link}\n\n"
      . "Se não foi você, ignore este e-mail: sua senha continua a mesma.\n");
}

/** Quantos admins ATIVOS existem (o sistema nunca pode ficar sem nenhum). */
function contarAdminsAtivos(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'admin' AND ativo = 1")->fetchColumn();
}

/**
 * Exclui a conta e TUDO que é do usuário (LGPD, "direito ao esquecimento").
 *
 * As chaves estrangeiras cuidam do resto em cascata: progresso, respostas,
 * conquistas, compras, tokens. Mensagens de suporte e o registro do admin
 * ficam, com o autor apagado (ON DELETE SET NULL) — são da plataforma.
 * Recusa excluir o último admin ativo.
 *
 * @return string|null mensagem de erro, ou null se excluiu
 */
function excluirConta(PDO $pdo, int $usuarioId): ?string
{
    $stmt = $pdo->prepare("SELECT tipo, ativo FROM usuarios WHERE id = ?");
    $stmt->execute([$usuarioId]);
    $conta = $stmt->fetch();
    if (!$conta) {
        return 'Conta não encontrada.';
    }
    if ($conta['tipo'] === 'admin' && (int) $conta['ativo'] === 1 && contarAdminsAtivos($pdo) <= 1) {
        return 'Esta é a única conta de administrador ativa: crie ou promova outra antes de excluir.';
    }
    $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$usuarioId]);
    return null;
}
