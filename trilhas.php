<?php
/**
 * trilhas.php — todas as trilhas, com progresso, destaque e recomendações.
 *
 * Segue o wireframe de Trilhas:
 *   - lista "Todas as trilhas": ícone, título, descrição, dificuldade, número
 *     de módulos, barra de progresso e UM botão de ação por trilha;
 *   - painel lateral com as trilhas marcadas como destaque na Administração;
 *   - "Recomendadas para você": próxima lição, um desafio para praticar e uma
 *     trilha nova para começar.
 *
 * Regras de progressão (sequencial, prova, trilha com pré-requisito) moram em
 * includes/auth.php e includes/progresso.php — esta tela só mostra o estado.
 */

require_once __DIR__ . '/config/conexao.php';

$tituloPagina    = 'Trilhas de Aprendizado';
$subtituloPagina = 'Escolha uma trilha ou continue de onde parou. Aprenda, pratique e evolua.';

require_once __DIR__ . '/includes/header.php';

$alunoId = (int) usuarioAtualId();

$dados     = dadosDasTrilhas($pdo, $alunoId);
$continuar = trilhaParaContinuar($pdo, $alunoId, $dados);
$idAtual   = $continuar ? (int) $continuar['trilha']['id'] : 0;
$recs      = recomendacoes($pdo, $alunoId, $dados, $continuar,
                           desafiosDoAluno($pdo, $alunoId, $dados));

$destaques = array_values(array_filter($dados, static fn(array $d): bool => (int) $d['trilha']['destaque'] === 1));

// Título do painel de destaque: "HTML e CSS" a partir das próprias trilhas.
$nomesDestaque = array_map(static fn(array $d): string => $d['trilha']['titulo'], $destaques);
$tituloDestaque = count($nomesDestaque) > 1
    ? implode(', ', array_slice($nomesDestaque, 0, -1)) . ' e ' . end($nomesDestaque)
    : ($nomesDestaque[0] ?? '');
$todasWeb = $destaques && !array_filter($destaques, static fn(array $d): bool => $d['trilha']['linguagem'] !== 'html');
$chamadaDestaque = $todasWeb ? 'Construa páginas web com estrutura e estilo.' : 'Trilhas em destaque para você.';

/**
 * Botão de ação da linha: rótulo, destino e se é o botão principal.
 * Só a trilha em andamento ganha o botão laranja cheio — como no wireframe,
 * um destaque por tela, não oito.
 */
function acaoDaTrilha(array $item, int $idAtual): array
{
    if ($item['bloqueada']) {
        return ['Bloqueada', null, 'bloqueada'];
    }
    $url = urlDoPasso($item['proximo']);
    if ($url === null) {
        return ['Em breve', null, 'bloqueada'];
    }
    if ($item['concluida']) {
        return ['Revisar', $url, 'contorno'];
    }
    if (($item['proximo']['tipo'] ?? '') === 'prova') {
        return ['Fazer a prova', $url, (int) $item['trilha']['id'] === $idAtual ? 'primario' : 'contorno'];
    }
    if ((int) $item['trilha']['id'] === $idAtual) {
        return [$item['feitas'] > 0 ? 'Continuar' : 'Começar', $url, 'primario'];
    }
    return ['Explorar', $url, 'contorno'];
}

/** "8 módulos", sem contar a prova — é o que o aluno percebe como conteúdo. */
function rotuloModulos(array $modulos): string
{
    $aulas = count(array_filter($modulos, static fn(array $m): bool => $m['tipo'] === 'aula'));
    return $aulas === 1 ? '1 projeto' : $aulas . ' módulos';
}
?>

<?php if (empty($dados)): ?>

    <div class="painel vazio">
        <h3>Nenhuma trilha publicada ainda</h3>
        <p>
            <?php if ($ehAdmin): ?>
                Cadastre a primeira trilha na <a href="administracao.php">Administração</a>.
            <?php else: ?>
                Assim que uma trilha for publicada, ela aparece aqui.
            <?php endif; ?>
        </p>
    </div>

<?php else: ?>

<div class="colunas colunas-trilhas <?= $destaques ? '' : 'colunas-sem-lateral' ?>">

    <div class="coluna-principal">
        <h2 class="secao-titulo-claro" id="todas-as-trilhas">Todas as trilhas</h2>

        <div class="lista-trilhas">
            <?php foreach ($dados as $item): ?>
                <?php
                    $trilha = $item['trilha'];
                    [$rotulo, $url, $estilo] = acaoDaTrilha($item, $idAtual);
                ?>
                <article class="linha-trilha <?= $item['bloqueada'] ? 'trilha-bloqueada' : '' ?> <?= $item['concluida'] ? 'trilha-concluida' : '' ?>"
                         data-trilha="<?= (int) $trilha['id'] ?>">

                    <?= iconeDaTrilha($trilha['icone'], $item['bloqueada']) ?>

                    <div class="linha-trilha-info">
                        <h3><?= e($trilha['titulo']) ?>
                            <?php if (!empty($item['rascunho'])): ?>
                                <span class="selo selo-inativo" title="Só o administrador vê esta trilha">rascunho</span>
                            <?php endif; ?>
                        </h3>
                        <p><?= e($trilha['descricao']) ?></p>

                        <?php
                            $metaNivel = '<span class="ponto-nivel nivel-' . e($trilha['nivel_dificuldade']) . '"></span>'
                                       . '<span class="rotulo-nivel">' . e(rotuloDificuldade($trilha['nivel_dificuldade'])) . '</span>'
                                       . '<span class="separador" aria-hidden="true">|</span>'
                                       . '<span>' . e(rotuloModulos($item['modulos'])) . '</span>';
                            $faltam = $item['requisitos_pendentes'];
                            $motivoBloqueio = count($faltam) >= count($dados) - 1
                                ? 'Libera quando você concluir todas as outras trilhas.'
                                : 'Conclua antes: ' . implode(', ', $faltam) . '.';
                        ?>

                        <?php if ($item['bloqueada'] || empty($item['modulos'])): ?>
                            <div class="linha-trilha-meta"><?= $metaNivel ?></div>
                            <?php if ($item['bloqueada']): ?>
                                <span class="sr-only"><?= e($motivoBloqueio) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <details class="caminho">
                                <summary class="linha-trilha-meta" title="Ver os módulos desta trilha">
                                    <?= $metaNivel ?>
                                    <span class="caminho-seta"><?= icone('chevron', 14) ?></span>
                                    <span class="sr-only">— ver módulos</span>
                                </summary>
                                <ol class="caminho-modulos">
                                    <?php foreach ($item['modulos'] as $modulo): ?>
                                        <?php
                                            if ($modulo['concluido']) {
                                                $estadoMod = 'concluido';
                                                $marca     = icone('check', 16);
                                            } elseif (!$modulo['liberado']) {
                                                $estadoMod = 'bloqueado';
                                                $marca     = icone('cadeado', 16);
                                            } else {
                                                $estadoMod = 'atual';
                                                $marca     = icone('play', 16);
                                            }
                                            $ehProva = ($modulo['tipo'] === 'prova');
                                            $destino = $ehProva
                                                ? 'prova.php?modulo=' . (int) $modulo['id']
                                                : 'desafio.php?modulo=' . (int) $modulo['id'];
                                        ?>
                                        <li class="passo passo-<?= $estadoMod ?>">
                                            <span class="passo-marca"><?= $marca ?></span>
                                            <?php if ($modulo['liberado']): ?>
                                                <a href="<?= $destino ?>"><?= e($modulo['titulo']) ?></a>
                                            <?php else: ?>
                                                <span title="Conclua o módulo anterior para liberar"><?= e($modulo['titulo']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($ehProva): ?>
                                                <span class="selo selo-prova">prova</span>
                                            <?php elseif ((int) $modulo['total_atividades'] > 0): ?>
                                                <small class="passo-contagem">
                                                    <?= (int) $modulo['atividades_feitas'] ?>/<?= (int) $modulo['total_atividades'] ?>
                                                </small>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </details>
                        <?php endif; ?>
                    </div>

                    <div class="linha-trilha-progresso">
                        <?php if ($item['bloqueada']): ?>
                            <span class="progresso-bloqueado" title="<?= e($motivoBloqueio) ?>"><?= icone('cadeado', 18) ?> Bloqueada</span>
                        <?php else: ?>
                            <strong><?= $item['percentual'] ?>%</strong>
                            <div class="barra-progresso">
                                <div class="barra-progresso-preenchida" style="width: <?= $item['percentual'] ?>%;"></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="linha-trilha-acao">
                        <?php if ($url === null): ?>
                            <span class="botao botao-trilha botao-trilha-bloqueada" aria-disabled="true"
                                  <?= $item['bloqueada'] ? 'title="' . e($motivoBloqueio) . '"' : '' ?>>
                                <?= e($rotulo) ?> <?= icone('cadeado', 14) ?>
                            </span>
                        <?php else: ?>
                            <a href="<?= e($url) ?>" class="botao botao-trilha <?= $estilo === 'primario' ? 'botao-primario' : 'botao-contorno' ?>">
                                <?= e($rotulo) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($destaques): ?>
        <aside class="coluna-lateral painel painel-destaque-trilhas" aria-label="Trilhas em destaque">
            <div class="destaque-topo">
                <span class="destaque-topo-icone">&lt;/&gt;</span>
                <div>
                    <h2><?= e($tituloDestaque) ?></h2>
                    <p><?= e($chamadaDestaque) ?></p>
                </div>
            </div>

            <?php foreach ($destaques as $item): ?>
                <?php
                    $trilha = $item['trilha'];
                    $urlDestaque = $item['bloqueada'] ? null : urlDoPasso($item['proximo']);
                ?>
                <div class="cartao-destaque cartao-destaque-<?= e($trilha['icone'] ?? 'padrao') ?>">
                    <div class="cartao-destaque-marca">
                        <?php $logo = $item['bloqueada'] ? null : logoDaTrilha($trilha['icone']); ?>
                        <?php if ($logo !== null): ?>
                            <img src="<?= e($logo) ?>" alt="" class="cartao-destaque-logo">
                        <?php else: ?>
                            <?= iconeDaTrilha($trilha['icone'], $item['bloqueada'], 'bloco-trilha-grande') ?>
                        <?php endif; ?>
                        <strong><?= e($trilha['titulo']) ?></strong>
                    </div>
                    <?php if (!empty($trilha['subtitulo'])): ?>
                        <h3><?= e($trilha['subtitulo']) ?></h3>
                    <?php endif; ?>
                    <p><?= e($trilha['descricao']) ?></p>
                    <?php if ($urlDestaque): ?>
                        <a href="<?= e($urlDestaque) ?>" class="botao botao-contorno botao-bloco">
                            <?= $item['concluida'] ? 'Revisar' : ($item['feitas'] > 0 ? 'Continuar' : 'Explorar') ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </aside>
    <?php endif; ?>
</div>

<?php if (array_filter($recs)): ?>
    <section class="recomendadas" aria-labelledby="tituloRecomendadas">
        <div class="recomendadas-topo">
            <h2 class="secao-titulo-claro" id="tituloRecomendadas">Recomendadas para você</h2>
            <a href="desafios.php" class="link-seta">Ver todos os desafios <?= icone('seta', 16) ?></a>
        </div>

        <div class="grade-recomendadas">
            <?php
                $cartoes = [
                    'proxima'  => ['Próxima lição',      'play'],
                    'pratica'  => ['Continue praticando', 'alvo'],
                    'sugestao' => ['Sugestão para você',  null],
                ];
            ?>
            <?php foreach ($cartoes as $chave => [$rotuloRec, $iconeRec]): ?>
                <?php if (!$recs[$chave]) { continue; } $r = $recs[$chave]; ?>
                <a href="<?= e($r['url']) ?>" class="cartao-recomendado cartao-recomendado-<?= $chave ?>">
                    <?php if ($iconeRec !== null): ?>
                        <span class="cartao-recomendado-icone"><?= icone($iconeRec, 20) ?></span>
                    <?php else: ?>
                        <?= iconeDaTrilha($r['icone'] ?? null, false, 'bloco-trilha-mini') ?>
                    <?php endif; ?>
                    <span class="cartao-recomendado-info">
                        <small><?= e($rotuloRec) ?></small>
                        <strong><?= e($r['titulo']) ?></strong>
                        <em><?= e($r['meta']) ?></em>
                    </span>
                    <span class="cartao-recomendado-seta"><?= icone('seta', 18) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
