<?php
/**
 * admin_relatorios.php — o "lado do ensino" do DevFox (ADM-80 a ADM-85).
 *
 * Tudo sai de tabelas que o sistema já gravava e nenhuma tela lia:
 *   - `submissoes`       → toda tentativa, certa ou errada (atividades difíceis);
 *   - `atividades_concluidas`, `tentativas_prova` → funil e progresso;
 *   - `dicas_vistas`     → onde os alunos mais pedem ajuda.
 *
 * Período (7 dias, 30 dias, tudo) vale para o que é EVENTO (tentativas, dicas).
 * Funil e progresso mostram a SITUAÇÃO ATUAL de cada aluno.
 * Cada tabela pode ser baixada em CSV (separador ";" e BOM, para o Excel em
 * português abrir com acentos) — útil para o capítulo de resultados do TCC.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';

exigirAdmin();

/** Tentativas mínimas para uma atividade entrar no ranking de dificuldade. */
const RELATORIO_MIN_TENTATIVAS = 3;

[$periodo, $rotuloPeriodo, $noPeriodoS, $opcoesPeriodo] = periodoRelatorio('s.enviado_em');
[, , $noPeriodoD] = periodoRelatorio('dv.vista_em');

/* ---- visão geral do período ---- */
$geral = $pdo->query(
    "SELECT COUNT(*) AS respostas, COALESCE(SUM(s.correta), 0) AS certas, COUNT(DISTINCT s.aluno_id) AS alunos
     FROM submissoes s WHERE $noPeriodoS"
)->fetch();

/* ---- ADM-80: atividades mais difíceis ---- */
$dificeis = $pdo->query(
    "SELECT a.id, a.titulo, a.tipo, m.titulo AS modulo, t.titulo AS trilha,
            COUNT(*) AS tentativas, COUNT(DISTINCT s.aluno_id) AS alunos,
            SUM(s.correta = 0) AS erradas,
            ROUND(SUM(s.correta = 0) / COUNT(*) * 100) AS taxa_erro
     FROM submissoes s
     JOIN atividades a ON a.id = s.atividade_id
     JOIN modulos m ON m.id = a.modulo_id
     JOIN trilhas t ON t.id = m.trilha_id
     WHERE $noPeriodoS
     GROUP BY a.id, a.titulo, a.tipo, m.titulo, t.titulo
     HAVING COUNT(*) >= " . RELATORIO_MIN_TENTATIVAS . "
     ORDER BY taxa_erro DESC, tentativas DESC
     LIMIT 15"
)->fetchAll();

/* ---- ADM-83: onde mais se pede dica ---- */
$dicas = $pdo->query(
    "SELECT a.id, a.titulo, t.titulo AS trilha, COUNT(*) AS dicas
     FROM dicas_vistas dv
     JOIN atividades a ON a.id = dv.atividade_id
     JOIN modulos m ON m.id = a.modulo_id
     JOIN trilhas t ON t.id = m.trilha_id
     WHERE $noPeriodoD
     GROUP BY a.id, a.titulo, t.titulo
     ORDER BY dicas DESC LIMIT 10"
)->fetchAll();

/* ---- ADM-81: funil por trilha (situação atual) ---- */
$trilhas = $pdo->query(
    "SELECT t.id, t.titulo,
            (SELECT COUNT(*) FROM atividades a JOIN modulos m ON m.id = a.modulo_id WHERE m.trilha_id = t.id) AS total,
            (SELECT m.id FROM modulos m WHERE m.trilha_id = t.id AND m.tipo = 'prova'
               AND EXISTS (SELECT 1 FROM atividades a WHERE a.modulo_id = m.id)
             ORDER BY m.ordem DESC LIMIT 1) AS prova_id
     FROM trilhas t WHERE t.publicada = 1 ORDER BY t.ordem, t.id"
)->fetchAll();

// Atividades concluídas por (aluno, trilha), só de alunos (admin não entra na conta).
$feitas = [];
foreach ($pdo->query(
    "SELECT ac.aluno_id, m.trilha_id, COUNT(*) AS feitas
     FROM atividades_concluidas ac
     JOIN atividades a ON a.id = ac.atividade_id
     JOIN modulos m ON m.id = a.modulo_id
     JOIN usuarios u ON u.id = ac.aluno_id AND u.tipo = 'aluno'
     GROUP BY ac.aluno_id, m.trilha_id"
) as $l) {
    $feitas[(int) $l['trilha_id']][(int) $l['aluno_id']] = (int) $l['feitas'];
}
$provas = [];   // [modulo][aluno] = ['fez' => bool, 'aprovado' => bool]
foreach ($pdo->query(
    "SELECT tp.modulo_id, tp.aluno_id, MAX(tp.aprovado) AS aprovado
     FROM tentativas_prova tp JOIN usuarios u ON u.id = tp.aluno_id AND u.tipo = 'aluno'
     GROUP BY tp.modulo_id, tp.aluno_id"
) as $l) {
    $provas[(int) $l['modulo_id']][(int) $l['aluno_id']] = (int) $l['aprovado'] === 1;
}

$funil = [];
foreach ($trilhas as $t) {
    $porAluno = $feitas[(int) $t['id']] ?? [];
    $total = max(1, (int) $t['total']);
    $tentaram = $t['prova_id'] ? ($provas[(int) $t['prova_id']] ?? []) : [];
    $iniciaram = count($porAluno);
    $metade    = count(array_filter($porAluno, static fn(int $n): bool => $n / $total >= 0.5));
    $fizeram   = count($tentaram);
    // Concluiu = aprovado na prova (a regra de trilhaConcluida); trilha sem prova = 100% das atividades.
    $concluiram = $t['prova_id']
        ? count(array_filter($tentaram))
        : count(array_filter($porAluno, static fn(int $n): bool => $n >= $total));
    $funil[] = ['trilha' => $t['titulo'], 'tem_prova' => (bool) $t['prova_id'],
                'iniciaram' => $iniciaram, 'metade' => $metade, 'prova' => $fizeram, 'concluiram' => $concluiram];
}

/* ---- ADM-82: progresso por aluno (top 50 por XP) ---- */
$alunos = $pdo->query(
    "SELECT id, nome, email, xp FROM usuarios WHERE tipo = 'aluno' ORDER BY xp DESC, nome LIMIT 50"
)->fetchAll();
$totaisTrilha = array_column($trilhas, 'total', 'id');
$pct = static function (int $alunoId, int $trilhaId) use ($feitas, $totaisTrilha): int {
    $total = (int) ($totaisTrilha[$trilhaId] ?? 0);
    return $total > 0 ? (int) min(100, round(($feitas[$trilhaId][$alunoId] ?? 0) / $total * 100)) : 0;
};

/* ---- ADM-84: CSV ---- */
if (isset($_GET['csv'])) {
    $qual = (string) $_GET['csv'];
    $linhas = match ($qual) {
        'dificeis' => array_merge([['Atividade', 'Trilha', 'Módulo', 'Tentativas', 'Alunos', 'Erradas', '% de erro']],
            array_map(static fn($d) => [$d['titulo'], $d['trilha'], $d['modulo'], $d['tentativas'], $d['alunos'], $d['erradas'], $d['taxa_erro']], $dificeis)),
        'dicas'    => array_merge([['Atividade', 'Trilha', 'Dicas abertas']],
            array_map(static fn($d) => [$d['titulo'], $d['trilha'], $d['dicas']], $dicas)),
        'funil'    => array_merge([['Trilha', 'Iniciaram', 'Passaram da metade', 'Fizeram a prova', 'Concluíram']],
            array_map(static fn($f) => [$f['trilha'], $f['iniciaram'], $f['metade'], $f['tem_prova'] ? $f['prova'] : '-', $f['concluiram']], $funil)),
        'progresso'=> array_merge([array_merge(['Aluno', 'E-mail', 'XP'], array_map(static fn($t) => $t['titulo'] . ' (%)', $trilhas))],
            array_map(static fn($a) => array_merge([$a['nome'], $a['email'], $a['xp']],
                array_map(static fn($t) => $pct((int) $a['id'], (int) $t['id']), $trilhas)), $alunos)),
        default    => null,
    };
    if ($linhas === null) {
        http_response_code(404);
        exit('Relatório inexistente.');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="devfox-' . $qual . '-' . date('Y-m-d') . '.csv"');
    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");                     // BOM: o Excel reconhece o UTF-8
    foreach ($linhas as $linha) {
        // Célula começando com = + - @ vira fórmula no Excel (injeção de CSV):
        // um nome de aluno "=HYPERLINK(...)" executaria ao abrir. Um apóstrofo neutraliza.
        $linha = array_map(static fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $linha);
        fputcsv($saida, $linha, ';', '"', '\\');
    }
    exit;
}

$tituloPagina    = 'Relatórios';
$subtituloPagina = 'Como os alunos estão indo — e onde o conteúdo precisa de ajuste.';
require_once __DIR__ . '/includes/header.php';

$linkCsv = static fn(string $qual): string => 'admin_relatorios.php?' . http_build_query(['periodo' => $periodo, 'csv' => $qual]);
?>

<?= abasAdmin('admin_relatorios.php', suporteNaoLidas($pdo)) ?>

<div class="filtros-admin">
    <nav class="filtros-periodo" aria-label="Período">
        <?php foreach ($opcoesPeriodo as $codigo => $rotulo): ?>
            <a href="admin_relatorios.php?periodo=<?= e($codigo) ?>" class="<?= $codigo === $periodo ? 'ativa' : '' ?>"
               <?= $codigo === $periodo ? 'aria-current="true"' : '' ?>><?= e($rotulo) ?></a>
        <?php endforeach; ?>
    </nav>
</div>

<?php // Mesmos cartões do painel, para as duas telas não terem estilos diferentes. ?>
<div class="grade-kpi grade-kpi-3">
    <div class="cartao-kpi">
        <span class="cartao-kpi-icone tom-laranja"><?= icone('codigo', 22) ?></span>
        <div class="cartao-kpi-corpo"><small>Respostas enviadas</small>
            <div class="cartao-kpi-linha"><strong><?= number_format((int) $geral['respostas'], 0, ',', '.') ?></strong></div>
            <small class="cartao-kpi-nota"><?= e($rotuloPeriodo) ?></small></div>
    </div>
    <div class="cartao-kpi">
        <span class="cartao-kpi-icone tom-azul"><?= icone('alvo', 22) ?></span>
        <div class="cartao-kpi-corpo"><small>Taxa de acerto</small>
            <div class="cartao-kpi-linha"><strong><?= (int) $geral['respostas'] > 0 ? round($geral['certas'] / $geral['respostas'] * 100) . '%' : '—' ?></strong></div>
            <small class="cartao-kpi-nota">das tentativas</small></div>
    </div>
    <div class="cartao-kpi">
        <span class="cartao-kpi-icone tom-verde"><?= icone('perfil', 22) ?></span>
        <div class="cartao-kpi-corpo"><small>Alunos que responderam</small>
            <div class="cartao-kpi-linha"><strong><?= number_format((int) $geral['alunos'], 0, ',', '.') ?></strong></div>
            <small class="cartao-kpi-nota"><?= e($rotuloPeriodo) ?></small></div>
    </div>
</div>

<section class="painel" id="relDificeis">
    <div class="painel-inicio-cabecalho">
        <h3 class="painel-titulo"><?= icone('bug') ?> Atividades mais difíceis</h3>
        <a href="<?= e($linkCsv('dificeis')) ?>" class="botao botao-secundario botao-pequeno"><?= icone('download', 14) ?> CSV</a>
    </div>
    <p class="aviso-suave">Ordenadas pela % de tentativas erradas (<?= e(mb_strtolower($rotuloPeriodo)) ?>; só entram atividades com pelo menos <?= RELATORIO_MIN_TENTATIVAS ?> tentativas).
        Erro alto costuma ser enunciado confuso, caso de teste duro ou conteúdo que faltou explicar.</p>
    <?php if (empty($dificeis)): ?>
        <p class="aviso-suave">Ainda não há tentativas suficientes neste período.</p>
    <?php else: ?>
        <div class="tabela-admin-envolve"><table class="tabela-admin">
            <thead><tr><th>Atividade</th><th>Trilha · módulo</th><th class="num">Tentativas</th><th class="num">Alunos</th><th>% de erro</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($dificeis as $d): ?>
                <?php $taxa = (int) $d['taxa_erro']; ?>
                <tr>
                    <td><strong><?= e($d['titulo']) ?></strong></td>
                    <td><?= e($d['trilha']) ?> · <?= e($d['modulo']) ?></td>
                    <td class="num"><?= (int) $d['tentativas'] ?></td>
                    <td class="num"><?= (int) $d['alunos'] ?></td>
                    <td><div class="mini-barra"><div class="barra-progresso"><div class="barra-progresso-preenchida" style="width: <?= $taxa ?>%"></div></div>
                        <span class="<?= $taxa >= 60 ? 'taxa-alta' : ($taxa >= 30 ? 'taxa-media' : '') ?>"><?= $taxa ?>%</span></div></td>
                    <td class="num"><a href="admin_atividade.php?id=<?= (int) $d['id'] ?>" class="botao botao-secundario botao-pequeno">Revisar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<div class="linha-baixo">
    <section class="painel" id="relFunil">
        <div class="painel-inicio-cabecalho">
            <h3 class="painel-titulo"><?= icone('trilhas') ?> Funil por trilha</h3>
            <a href="<?= e($linkCsv('funil')) ?>" class="botao botao-secundario botao-pequeno"><?= icone('download', 14) ?> CSV</a>
        </div>
        <p class="aviso-suave">Situação atual dos alunos: onde eles param em cada trilha.</p>
        <?php $semAlunos = array_column(array_filter($funil, static fn($f) => $f['iniciaram'] === 0), 'trilha'); ?>
        <?php foreach (array_filter($funil, static fn($f) => $f['iniciaram'] > 0) as $f): ?>
            <?php $base = max(1, $f['iniciaram']); ?>
            <h4 class="funil-titulo"><?= e($f['trilha']) ?></h4>
            <div class="funil">
                <?php foreach ([['Iniciaram', $f['iniciaram']], ['Passaram da metade', $f['metade']],
                               ...($f['tem_prova'] ? [['Fizeram a prova', $f['prova']]] : []), ['Concluíram', $f['concluiram']]] as [$rot, $valor]): ?>
                    <div class="funil-linha">
                        <span><?= e($rot) ?></span>
                        <div class="barra-progresso"><div class="barra-progresso-preenchida" style="width: <?= round($valor / $base * 100) ?>%"></div></div>
                        <strong><?= (int) $valor ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <?php if ($semAlunos): ?>
            <p class="rodape-painel">Nenhum aluno começou ainda: <?= e(implode(', ', $semAlunos)) ?>.</p>
        <?php endif; ?>
    </section>

    <aside>
        <section class="painel" id="relDicas">
            <div class="painel-inicio-cabecalho">
                <h3 class="painel-titulo"><?= icone('lampada') ?> Mais pedidos de dica</h3>
                <a href="<?= e($linkCsv('dicas')) ?>" class="botao botao-secundario botao-pequeno"><?= icone('download', 14) ?> CSV</a>
            </div>
            <?php if (empty($dicas)): ?>
                <p class="aviso-suave">Nenhuma dica aberta neste período.</p>
            <?php else: ?>
                <ul class="lista-eventos">
                    <?php foreach ($dicas as $d): ?>
                        <li><span class="evento-icone evento-laranja"><?= icone('lampada', 16) ?></span>
                            <div><strong><a href="admin_atividade.php?id=<?= (int) $d['id'] ?>"><?= e($d['titulo']) ?></a></strong><small><?= e($d['trilha']) ?></small></div>
                            <time><?= plural((int) $d['dicas'], 'vez', 'vezes') ?></time></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </aside>
</div>

<section class="painel" id="relProgresso">
    <div class="painel-inicio-cabecalho">
        <h3 class="painel-titulo"><?= icone('perfil') ?> Progresso por aluno</h3>
        <a href="<?= e($linkCsv('progresso')) ?>" class="botao botao-secundario botao-pequeno"><?= icone('download', 14) ?> CSV</a>
    </div>
    <p class="aviso-suave">% das atividades de cada trilha que o aluno já concluiu (os 50 com mais XP).</p>
    <?php if (empty($alunos)): ?>
        <p class="aviso-suave">Nenhum aluno cadastrado.</p>
    <?php else: ?>
        <div class="tabela-admin-envolve"><table class="tabela-admin tabela-progresso">
            <thead><tr><th>Aluno</th><th class="num">XP</th>
                <?php foreach ($trilhas as $t): ?><th class="num" title="<?= e($t['titulo']) ?>"><?= e(mb_strimwidth($t['titulo'], 0, 14, '…')) ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($alunos as $a): ?>
                <tr>
                    <td><a href="admin_aluno.php?id=<?= (int) $a['id'] ?>"><?= e($a['nome']) ?></a></td>
                    <td class="num"><?= number_format((int) $a['xp'], 0, ',', '.') ?></td>
                    <?php foreach ($trilhas as $t): ?>
                        <?php $p = $pct((int) $a['id'], (int) $t['id']); ?>
                        <td class="num celula-pct" style="--pct: <?= $p ?>%"><?= $p > 0 ? $p . '%' : '<span class="aviso-suave">—</span>' ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
