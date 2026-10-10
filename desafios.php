<?php
/**
 * desafios.php — todos os desafios de código, com o estado de cada um.
 *
 * Esta tela NÃO é um atalho para pular a ordem das trilhas. Desafio de módulo
 * ainda bloqueado aparece (o aluno vê o que vem pela frente), mas sem link —
 * e desafio.php barra o acesso direto pela URL do mesmo jeito. O estado vem
 * de desafiosDoAluno(), o mesmo cálculo do Início.
 *
 * Quiz e questão de prova ficam de fora: "desafio" aqui é exercício de código.
 */

require_once __DIR__ . '/config/conexao.php';

$tituloPagina    = 'Desafios';
$subtituloPagina = 'Todos os exercícios de código, na ordem das trilhas.';
require_once __DIR__ . '/includes/header.php';

$alunoId  = (int) usuarioAtualId();
$desafios = desafiosDoAluno($pdo, $alunoId, dadosDasTrilhas($pdo, $alunoId));

$filtros = [
    'todos'      => 'Todos',
    'disponivel' => 'Disponíveis',
    'concluido'  => 'Concluídos',
    'bloqueado'  => 'Bloqueados',
];
$filtro = $_GET['filtro'] ?? 'todos';
if (!isset($filtros[$filtro])) {
    $filtro = 'todos';                     // valor desconhecido na URL: ignora
}

$contagem = ['todos' => count($desafios), 'disponivel' => 0, 'concluido' => 0, 'bloqueado' => 0];
foreach ($desafios as $d) {
    $contagem[$d['estado']]++;
}

// Agrupa por trilha, já aplicando o filtro.
$porTrilha = [];
foreach ($desafios as $d) {
    if ($filtro !== 'todos' && $d['estado'] !== $filtro) {
        continue;
    }
    $porTrilha[(int) $d['trilha_id']]['titulo']  = $d['trilha_titulo'];
    $porTrilha[(int) $d['trilha_id']]['icone']   = $d['trilha_icone'];
    $porTrilha[(int) $d['trilha_id']]['itens'][] = $d;
}
?>

<nav class="filtros" aria-label="Filtrar desafios">
    <?php foreach ($filtros as $chave => $rotulo): ?>
        <a href="desafios.php<?= $chave === 'todos' ? '' : '?filtro=' . $chave ?>"
           class="filtro <?= $filtro === $chave ? 'filtro-ativo' : '' ?>"
           <?= $filtro === $chave ? 'aria-current="page"' : '' ?>>
            <?= e($rotulo) ?> <span><?= $contagem[$chave] ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (empty($porTrilha)): ?>
    <div class="painel vazio">
        <h3>Nenhum desafio aqui</h3>
        <p>
            <?php if ($filtro === 'concluido'): ?>
                Você ainda não concluiu nenhum desafio. <a href="desafios.php?filtro=disponivel">Veja os disponíveis</a>.
            <?php elseif ($filtro === 'disponivel'): ?>
                Nenhum desafio liberado agora. Conclua o módulo atual da trilha para liberar os próximos.
            <?php else: ?>
                Não há desafios nesta categoria.
            <?php endif; ?>
        </p>
    </div>
<?php endif; ?>

<?php foreach ($porTrilha as $trilha): ?>
    <?php $aparencia = aparenciaDaTrilha($trilha['icone']); ?>
    <section class="painel grupo-desafios">
        <div class="painel-cabecalho">
            <div class="bloco-icone bloco-icone-pequeno" style="--cor-trilha: <?= e($aparencia['cor']) ?>">
                <?= e($aparencia['rotulo']) ?>
            </div>
            <div>
                <small>Trilha</small>
                <strong><?= e($trilha['titulo']) ?></strong>
            </div>
        </div>

        <ul class="lista-desafios">
            <?php foreach ($trilha['itens'] as $d): ?>
                <?php
                    $estado = $d['estado'];
                    $conteudo =
                        '<span class="linha-desafio-icone">' . icone(iconeDoTipo($d['tipo']), 18) . '</span>'
                      . '<span class="linha-desafio-info"><strong>' . e($d['titulo']) . '</strong>'
                      . '<small>' . e($d['modulo_titulo']) . ' • ' . e(rotuloDoTipo($d['tipo']))
                      . ' • ' . e(rotuloDificuldade($d['dificuldade'])) . '</small></span>'
                      . '<span class="linha-desafio-xp">+' . (int) $d['xp_recompensa'] . ' XP</span>';
                ?>
                <li>
                    <?php if ($estado === 'bloqueado'): ?>
                        <div class="linha-desafio linha-desafio-bloqueada" title="Conclua os módulos anteriores para liberar">
                            <?= $conteudo ?>
                            <span class="linha-desafio-estado"><?= icone('cadeado', 16) ?></span>
                        </div>
                    <?php else: ?>
                        <a href="desafio.php?id=<?= (int) $d['id'] ?>"
                           class="linha-desafio <?= $estado === 'concluido' ? 'linha-desafio-feita' : '' ?>">
                            <?= $conteudo ?>
                            <span class="linha-desafio-estado">
                                <?= $estado === 'concluido' ? icone('check', 16) : icone('chevron', 18) ?>
                            </span>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
