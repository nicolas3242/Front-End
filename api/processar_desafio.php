<?php
/**
 * api/processar_desafio.php — recebe a resposta do aluno e devolve JSON.
 *
 * COMO A CORREÇÃO FUNCIONA
 * -----------------------------------------------------------------------------
 * O código do aluno é executado no navegador dele, dentro de um Web Worker
 * (assets/js/executor.worker.js). O navegador devolve apenas O QUE O CÓDIGO
 * PRODUZIU em cada caso de teste. Este arquivo compara essas saídas com os
 * gabaritos guardados no banco e decide se a atividade foi concluída.
 *
 * Duas decisões de segurança sustentam esse desenho:
 *
 * 1. O GABARITO DOS CASOS OCULTOS NUNCA SAI DAQUI.
 *    O navegador recebe só as ENTRADAS dos casos. Para fraudar, o aluno teria
 *    que adivinhar a saída correta de uma entrada que ele vê — ou seja, teria
 *    que resolver o exercício.
 *
 * 2. QUEM JULGA É O SERVIDOR.
 *    Se o navegador dissesse "passei", bastaria o DevTools para forjar isso.
 *    Ele só informa o que executou; a decisão é sempre feita aqui.
 *
 * ⚠ Limite honesto deste desenho: como a execução acontece na máquina do
 *   aluno, ele pode responder ao servidor com uma saída que o código dele não
 *   produziu de fato. O que se garante é que essa saída precisa estar CORRETA
 *   para a atividade contar. Eliminar isso por completo exigiria executar o
 *   código no servidor, com todo o custo de isolamento que essa escolha traz —
 *   ver a decisão registrada no AI_HANDOFF.md.
 *
 * O XP continua sendo concedido no máximo uma vez por atividade, por
 * concluirAtividade() (includes/auth.php).
 */

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/conquistas.php';

header('Content-Type: application/json; charset=utf-8');

/** Envia a resposta JSON e encerra. */
function responder(array $dados, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Normaliza um texto antes de comparar.
 *
 * Ignora espaços nas pontas, espaços repetidos e diferença de maiúsculas — a
 * plataforma é para iniciantes, e reprovar alguém por ter escrito "True" em
 * vez de "true" ensina pouco. O conteúdo em si precisa estar certo.
 */
function normalizar(?string $valor): string
{
    $valor = trim((string) $valor);
    $valor = preg_replace('/\s+/u', ' ', $valor);

    return mb_strtolower($valor, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['sucesso' => false, 'mensagem' => 'Requisição inválida.'], 405);
}

if (!usuarioLogado()) {
    responder(['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Faça login novamente.'], 401);
}

// Token CSRF (vem do <meta name="csrf"> da página, enviado pelo desafios.js).
// Sem ele, um site externo podia mandar respostas em nome do aluno logado.
exigirCsrf(true);

$alunoId     = usuarioAtualId();
$atividadeId = isset($_POST['atividade_id']) ? (int) $_POST['atividade_id'] : 0;

$stmt = $pdo->prepare(
    "SELECT a.id, a.titulo, a.tipo, a.xp_recompensa, a.modulo_id, m.tipo AS modulo_tipo
     FROM atividades a
     JOIN modulos m ON m.id = a.modulo_id
     WHERE a.id = ?"
);
$stmt->execute([$atividadeId]);
$atividade = $stmt->fetch();

if (!$atividade) {
    responder(['sucesso' => false, 'mensagem' => 'Atividade não encontrada.'], 404);
}

// Questão de prova NÃO é corrigida aqui, uma por uma. Se fosse, o aluno
// testaria cada alternativa e descobriria o gabarito da prova antes de fazê-la
// (e ainda ganharia o XP de cada questão). A prova é corrigida de uma vez só,
// em prova.php, com nota mínima.
if ($atividade['modulo_tipo'] === 'prova') {
    responder(['sucesso' => false, 'mensagem' => 'Questões da prova são respondidas na própria prova.'], 403);
}

// A mesma regra de progressão das telas: módulo bloqueado, trilha com
// pré-requisito pendente ou trilha em rascunho não aceitam resposta. Sem isto,
// um POST direto (DevTools, curl) dava XP de conteúdo que o aluno nem alcançou.
if (!moduloLiberado($pdo, (int) $atividade['modulo_id'], $alunoId)) {
    responder(['sucesso' => false, 'mensagem' => 'Este módulo ainda está bloqueado.'], 403);
}

$correta         = false;
$detalhes        = [];
$respostaEnviada = null;
$mensagemErro    = 'Resposta incorreta. Revise o enunciado e tente de novo.';


/* -----------------------------------------------------------------------------
 * QUIZ — a alternativa correta é conferida no banco
 * -------------------------------------------------------------------------- */

if ($atividade['tipo'] === 'quiz') {

    $alternativaId   = isset($_POST['alternativa_id']) ? (int) $_POST['alternativa_id'] : 0;
    $respostaEnviada = 'alternativa ' . $alternativaId;

    // A alternativa precisa pertencer a ESTA atividade: sem esse filtro, daria
    // para enviar o id de uma alternativa correta de outro exercício.
    $stmt = $pdo->prepare("SELECT correta FROM alternativas WHERE id = ? AND atividade_id = ?");
    $stmt->execute([$alternativaId, $atividadeId]);
    $alternativa = $stmt->fetch();

    if (!$alternativa) {
        responder(['sucesso' => false, 'mensagem' => 'Escolha uma das alternativas.'], 422);
    }

    $correta = ((int) $alternativa['correta'] === 1);

}

/* -----------------------------------------------------------------------------
 * EXERCÍCIO DE CÓDIGO — compara as saídas produzidas com os gabaritos
 * -------------------------------------------------------------------------- */

else {

    $codigo     = trim((string) ($_POST['codigo'] ?? ''));

    // Na questão de classificação de nota, conferir só a saída "Aprovado"
    // permite passar com um print fixo. Exija uma estrutura condicional.
    if ((int) $atividade['id'] === 12
        && (!preg_match('/\bif\b/', $codigo) || !preg_match('/\belse\b/', $codigo))) {
        responder([
            'sucesso'  => false,
            'mensagem' => 'Use uma condição if/else para classificar a nota.',
        ], 422);
    }

    $resultados = json_decode((string) ($_POST['resultados'] ?? ''), true);

    if (!is_array($resultados)) {
        responder([
            'sucesso'  => false,
            'mensagem' => 'Rode seu código antes de enviar a resposta.',
        ], 422);
    }

    // Gabaritos. A ordem é a mesma usada em desafio.php ao montar a tela, para
    // que os detalhes devolvidos casem com as linhas exibidas.
    $stmt = $pdo->prepare(
        "SELECT id, descricao, entrada, saida_esperada, visivel
         FROM casos_teste
         WHERE atividade_id = ?
         ORDER BY ordem, id"
    );
    $stmt->execute([$atividadeId]);
    $casos = $stmt->fetchAll();

    if (empty($casos)) {
        responder([
            'sucesso'  => false,
            'mensagem' => 'Este exercício ainda não tem casos de teste cadastrados. Avise a equipe do DevFox pelo Suporte.',
        ], 422);
    }

    // Indexa o que o navegador devolveu, por id de caso.
    $porCaso = [];
    foreach ($resultados as $resultado) {
        if (is_array($resultado) && isset($resultado['caso_id'])) {
            $porCaso[(int) $resultado['caso_id']] = $resultado;
        }
    }

    $todosPassaram = true;
    $primeiroErro  = null;

    foreach ($casos as $caso) {
        $idCaso   = (int) $caso['id'];
        $devolvido = $porCaso[$idCaso] ?? null;

        $erro   = ($devolvido['erro'] ?? null) ? (string) $devolvido['erro'] : null;
        $saida  = $devolvido['saida'] ?? null;

        // Caso ausente na resposta: trata como falha. Ou o navegador não
        // executou tudo, ou alguém removeu um caso do envio.
        $passou = ($devolvido !== null)
            && ($erro === null)
            && (normalizar(is_string($saida) ? $saida : null) === normalizar($caso['saida_esperada']));

        if (!$passou) {
            $todosPassaram = false;
            if ($primeiroErro === null) {
                $primeiroErro = $erro ?: null;
            }
        }

        // O que volta para a tela não revela o gabarito de caso oculto.
        $detalhes[] = [
            'caso_id' => $idCaso,
            'oculto'  => ((int) $caso['visivel'] === 0),
            'passou'  => $passou,
        ];
    }

    $correta = $todosPassaram;

    if ($primeiroErro !== null) {
        $mensagemErro = 'Seu código gerou um erro: ' . $primeiroErro;
    } elseif (!$correta) {
        // A mensagem "quase lá" só vale quando TODOS os visíveis passaram e a
        // falha ficou restrita aos ocultos. Dizer isso quando os visíveis
        // também falharam confundiria o aluno, mandando ele procurar um
        // problema em lugar errado.
        $visiveisFalharam = false;
        $ocultosFalharam  = false;

        foreach ($detalhes as $detalhe) {
            if ($detalhe['passou']) {
                continue;
            }
            if ($detalhe['oculto']) {
                $ocultosFalharam = true;
            } else {
                $visiveisFalharam = true;
            }
        }

        $mensagemErro = ($ocultosFalharam && !$visiveisFalharam)
            ? 'Quase lá: os casos visíveis passaram, mas seu código falhou num caso de teste oculto. Pense em situações que o enunciado não mostra.'
            : 'Resposta incorreta. Compare o que seu código produziu com a saída esperada.';
    }

    // Guarda código e saídas juntos, para a equipe conseguir analisar depois (relatório do admin).
    $saidasTexto = [];
    foreach ($casos as $caso) {
        $d = $porCaso[(int) $caso['id']] ?? [];
        $saidasTexto[] = ($caso['entrada'] ?: 'console') . ' => ' . ($d['erro'] ?? $d['saida'] ?? '(sem resposta)');
    }
    $respostaEnviada = "CÓDIGO:\n" . $codigo . "\n---\nSAÍDAS:\n" . implode("\n", $saidasTexto);
}


/* -----------------------------------------------------------------------------
 * Registro e resposta
 * -------------------------------------------------------------------------- */

// Toda tentativa entra no histórico, inclusive as erradas.
registrarSubmissao($pdo, $alunoId, $atividadeId, $respostaEnviada, $correta);

if (!$correta) {
    responder([
        'sucesso'  => false,
        'mensagem' => $mensagemErro,
        'detalhes' => $detalhes,
    ]);
}

$xpGanho = concluirAtividade($pdo, $alunoId, $atividadeId);

// Avaliado DEPOIS da conclusão, para que a atividade recém-feita já conte.
// Vai na resposta em vez de ir para a sessão porque esta tela não recarrega:
// o JavaScript mostra o aviso na hora.
$conquistas = verificarConquistas($pdo, $alunoId);

responder([
    'sucesso'  => true,
    'xp'       => $xpGanho,
    'repetida' => $xpGanho === 0,
    'detalhes' => $detalhes,
    'conquistas' => array_map(static function (array $c): array {
        return ['titulo' => $c['titulo'], 'descricao' => $c['descricao'], 'raridade' => $c['raridade']];
    }, $conquistas),
    'mensagem' => $xpGanho > 0
        ? "Todos os casos passaram! Você ganhou {$xpGanho} XP."
        : 'Todos os casos passaram! Você já havia concluído esta atividade, então não ganha XP de novo.',
]);
