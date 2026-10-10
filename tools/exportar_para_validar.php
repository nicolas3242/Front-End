<?php
/**
 * tools/exportar_para_validar.php
 *
 * Despeja em JSON tudo que o validador precisa conferir. Roda contra o banco
 * já importado, e não contra o arquivo .sql, de propósito: assim o que se
 * valida é exatamente o que o sistema vai ler em produção, depois de passar
 * pelo parser do MySQL e pela codificação da conexão.
 */

// O projeto roda com os PHP na raiz (repositório) ou dentro de app/ (ambiente
// de desenvolvimento do agente). Aceita os dois, para o mesmo arquivo servir aos dois.
$candidatos = [__DIR__ . '/../config/conexao.php', __DIR__ . '/../app/config/conexao.php'];
$conexao = null;
foreach ($candidatos as $caminho) {
    if (file_exists($caminho)) { $conexao = $caminho; break; }
}
if ($conexao === null) {
    die("Nao encontrei config/conexao.php. Rode a partir da raiz do projeto.\n");
}
require_once $conexao;

$saida = ['codigo' => [], 'quiz' => [], 'provas' => [], 'modulos' => []];

// Exercícios de código, com solução de referência e casos de teste.
$stmt = $pdo->query(
    "SELECT a.id, a.titulo, a.tipo, a.solucao_exemplo, a.codigo_inicial, m.titulo AS modulo,
            t.linguagem
     FROM atividades a
     JOIN modulos m ON m.id = a.modulo_id
     JOIN trilhas t ON t.id = m.trilha_id
     WHERE a.tipo <> 'quiz' ORDER BY a.id"
);
foreach ($stmt as $a) {
    $casos = $pdo->prepare(
        "SELECT id, entrada, saida_esperada, visivel FROM casos_teste
         WHERE atividade_id = ? ORDER BY ordem, id"
    );
    $casos->execute([$a['id']]);
    $a['casos'] = $casos->fetchAll();
    $saida['codigo'][] = $a;
}

// Quizzes, para conferir o gabarito.
$stmt = $pdo->query(
    "SELECT a.id, a.titulo, a.modulo_id, a.modulo_referencia_id, m.tipo AS modulo_tipo
     FROM atividades a JOIN modulos m ON m.id = a.modulo_id
     WHERE a.tipo = 'quiz' ORDER BY a.id"
);
foreach ($stmt as $a) {
    $alt = $pdo->prepare("SELECT texto, correta FROM alternativas WHERE atividade_id = ? ORDER BY ordem, id");
    $alt->execute([$a['id']]);
    $a['alternativas'] = $alt->fetchAll();
    $saida['quiz'][] = $a;
}

// Módulos, para conferir ordem e sequência.
$saida['modulos'] = $pdo->query(
    "SELECT id, trilha_id, titulo, tipo, ordem,
            (SELECT COUNT(*) FROM atividades WHERE modulo_id = modulos.id) AS atividades
     FROM modulos ORDER BY trilha_id, ordem"
)->fetchAll();

file_put_contents(__DIR__ . '/dados.json', json_encode($saida, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "dados.json gerado: "
   . count($saida['codigo']) . " exercicios de codigo, "
   . count($saida['quiz']) . " quizzes, "
   . count($saida['modulos']) . " modulos\n";
