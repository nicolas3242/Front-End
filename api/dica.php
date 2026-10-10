<?php
/**
 * api/dica.php — entrega o texto da dica e registra que o aluno pediu ajuda.
 * -----------------------------------------------------------------------------
 * A dica NÃO vai mais no HTML da tela de desafio. Ela é buscada aqui no momento
 * em que o aluno abre "Usar dica", e é neste momento que o uso fica registrado
 * em `dicas_vistas`.
 *
 * Por que assim, e não com um campo "usou_dica" enviado junto com a resposta:
 * esse campo viria do navegador, e tudo que vem do navegador o aluno consegue
 * editar no DevTools. A conquista "Por Conta Própria" (acerte 5 desafios sem
 * usar dica) viraria enfeite. Aqui, quem registra é o servidor, no caminho por
 * onde a dica obrigatoriamente passa.
 *
 * Isso não torna o sistema à prova de tudo — um aluno determinado pode ler a
 * dica pelo DevTools sem clicar no botão. Mas aí ele está enganando a si
 * mesmo, que é problema diferente de poder forjar o resultado.
 */

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');

/** Responde em JSON e encerra. */
function responderDica(array $dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderDica(['sucesso' => false, 'mensagem' => 'Requisição inválida.'], 405);
}

if (!usuarioLogado()) {
    responderDica(['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Faça login novamente.'], 401);
}

// Token CSRF (vem do <meta name="csrf"> da página, enviado pelo desafios.js).
// Sem ele, um site externo podia mandar respostas em nome do aluno logado.
exigirCsrf(true);

$alunoId     = usuarioAtualId();
$atividadeId = isset($_POST['atividade_id']) ? (int) $_POST['atividade_id'] : 0;

$stmt = $pdo->prepare("SELECT dica, modulo_id FROM atividades WHERE id = ?");
$stmt->execute([$atividadeId]);
$atividade = $stmt->fetch();

if (!$atividade) {
    responderDica(['sucesso' => false, 'mensagem' => 'Atividade não encontrada.'], 404);
}

// Mesma checagem de progressão do resto do sistema: não dá para colher dicas de
// módulos que o aluno ainda não alcançou.
if (!moduloLiberado($pdo, (int) $atividade['modulo_id'], $alunoId)) {
    responderDica(['sucesso' => false, 'mensagem' => 'Este módulo ainda está bloqueado.'], 403);
}

if (empty($atividade['dica'])) {
    responderDica(['sucesso' => false, 'mensagem' => 'Esta atividade não tem dica.'], 404);
}

// INSERT IGNORE + chave única: abrir a mesma dica dez vezes gera uma linha só.
$pdo->prepare("INSERT IGNORE INTO dicas_vistas (aluno_id, atividade_id) VALUES (?, ?)")
    ->execute([$alunoId, $atividadeId]);

responderDica(['sucesso' => true, 'dica' => $atividade['dica']]);
