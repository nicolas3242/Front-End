<?php
/**
 * includes/csrf.php — proteção contra requisição forjada de outro site.
 * -----------------------------------------------------------------------------
 * O ataque que isto impede: um site qualquer monta um formulário escondido que
 * aponta para o DevFox e faz o navegador enviá-lo. Como o navegador manda o
 * cookie de sessão junto automaticamente, o servidor veria uma requisição
 * perfeitamente autenticada — feita sem o usuário saber. Num painel de
 * administração isso significa cadastrar, editar ou apagar conteúdo em nome de
 * quem está logado.
 *
 * A defesa: cada formulário carrega um token que só existe na sessão do próprio
 * usuário. O site atacante não tem como ler esse valor (a política de mesma
 * origem do navegador impede), então não consegue montar um envio válido.
 *
 * Um token por sessão, não por formulário: é mais simples, resolve o mesmo
 * problema, e não quebra quando a pessoa abre várias abas — que é o efeito
 * colateral chato do token de uso único.
 */

require_once __DIR__ . '/ambiente.php';   // cookie de sessão HttpOnly + SameSite
iniciarSessao();

/** Token da sessão atual, criado na primeira vez que é pedido. */
function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        // random_bytes é criptograficamente seguro. rand() e uniqid() não são:
        // são previsíveis o bastante para um atacante adivinhar o valor.
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

/** O campo escondido, pronto para colar dentro de um <form>. */
function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Confere o token e encerra a requisição se não bater.
 *
 * Chame no começo do tratamento de TODO POST que altera dados.
 *
 * A comparação usa hash_equals() em vez de === porque o === para na primeira
 * diferença, e o tempo que ele leva conta quantos caracteres bateram. É pouco,
 * mas é informação de graça para quem estiver tentando adivinhar o token.
 */
function exigirCsrf(bool $respostaJson = false): void
{
    $enviado = $_POST['csrf'] ?? '';
    $esperado = $_SESSION['csrf'] ?? '';

    if ($esperado === '' || !is_string($enviado) || !hash_equals($esperado, $enviado)) {
        http_response_code(403);
        $texto = 'Requisição bloqueada: token de segurança inválido. '
               . 'Volte, recarregue a página e tente de novo.';
        if ($respostaJson) {
            // As APIs respondem JSON; o JS da tela mostra a mensagem.
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(['sucesso' => false, 'mensagem' => $texto], JSON_UNESCAPED_UNICODE));
        }
        die($texto);
    }
}
