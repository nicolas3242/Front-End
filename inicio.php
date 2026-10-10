<?php
/**
 * inicio.php — painel inicial do aluno (primeira tela depois do login).
 *
 * Reúne o que já existe em outras telas, sem regra nova:
 *   - números (nível, XP, sequência, conquistas) — os mesmos da barra lateral;
 *   - "Continue aprendendo" e "Desafios em destaque" — includes/progresso.php,
 *     o mesmo cálculo de Trilhas e Desafios, respeitando o bloqueio sequencial;
 *   - Top Ranking — a mesma ordenação de ranking.php.
 *
 * Nada aqui é dado de exemplo: tudo sai do banco para o aluno logado.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/auth.php';

exigirLogin();

// O admin cai na Administração: a tela inicial dele é o painel de gestão, não
// o painel de estudo. Com ?aluno=1 ele vê esta tela mesmo assim — serve para
// conferir como o aluno enxerga o Início, e é o mesmo princípio do "Ver como
// aluno" das outras telas.
if (usuarioEhAdmin() && !isset($_GET['aluno'])) {
    header('Location: administracao.php');
    exit;
}

// Primeiro nome na saudação: "Olá, Aluno!" em vez de "Olá, Aluno de Teste!".
$primeiroNome = strtok(trim($_SESSION['usuario_nome'] ?? ''), ' ') ?: 'dev';

$tituloPagina    = 'Olá, ' . $primeiroNome . '!';
$subtituloPagina = 'Continue sua jornada e evolua suas habilidades.';
require_once __DIR__ . '/includes/header.php';

$alunoId = (int) usuarioAtualId();

$dados     = dadosDasTrilhas($pdo, $alunoId);
$continuar = trilhaParaContinuar($pdo, $alunoId, $dados);
$desafios  = desafiosDoAluno($pdo, $alunoId, $dados);
$ranking   = resumoDoRanking($pdo, $alunoId, $ehAdmin ? 4 : 3);

// Desafios em destaque: 3 sorteados entre os que o aluno JÁ PODE fazer.
// Antes eram sempre os 3 primeiros da lista, então a tela era idêntica todo
// dia. Aqui o sorteio é a cada visita, de propósito: o bloco é uma vitrine,
// não a sequência da trilha — para seguir na ordem existe o "Continue
// aprendendo" logo acima, que continua apontando o próximo passo certo.
$disponiveis = array_values(array_filter(
    $desafios,
    static fn(array $d): bool => $d['estado'] === 'disponivel'
));
shuffle($disponiveis);
$destaques = array_slice($disponiveis, 0, 3);

$totalConquistas = (int) $pdo->query("SELECT COUNT(*) FROM conquistas WHERE ativa = 1")->fetchColumn();
$proximoNivelXp  = $nivelAtual * XP_POR_NIVEL;          // XP em que começa o próximo nível

// Botão principal do banner: o próximo passo real do aluno.
if ($continuar !== null) {
    $urlContinuar  = urlDoPasso($continuar['proximo']) ?? 'trilhas.php';
    $rotuloBotao   = $continuar['feitas'] > 0 ? 'Continuar aprendendo' : 'Começar a aprender';
    $moduloAtual   = moduloAtualDaTrilha($continuar['modulos']);
} else {
    $urlContinuar  = 'trilhas.php';
    $rotuloBotao   = 'Revisar trilhas';
    $moduloAtual   = null;
}
?>

<section class="banner-inicio">
    <div class="banner-inicio-texto">
        <h2>Seu próximo avanço <span>está te esperando.</span></h2>
        <p>Aprenda. Pratique. Construa.<br>Evolua como um verdadeiro dev.</p>

        <div class="banner-inicio-acoes">
            <a href="<?= e($urlContinuar) ?>" class="botao botao-primario" id="btnContinuar">
                <?= e($rotuloBotao) ?> <?= icone('seta', 18) ?>
            </a>
            <a href="trilhas.php" class="botao botao-contorno-claro">Explorar trilhas</a>
        </div>
    </div>
</section>

<section class="grade-numeros grade-numeros-inicio" aria-label="Seus números">
    <div class="cartao-numero-inicio">
        <span class="cartao-numero-rotulo"><?= icone('escudo', 18) ?> Nível</span>
        <strong><?= $nivelAtual ?></strong>
        <small class="destaque-laranja"><?= e(tituloDoNivel($nivelAtual)) ?></small>
    </div>

    <div class="cartao-numero-inicio">
        <span class="cartao-numero-rotulo"><?= icone('xp', 18) ?> XP</span>
        <strong>
            <?= number_format($xpAtual, 0, ',', '.') ?>
            <small class="cartao-numero-de">de <?= number_format($proximoNivelXp, 0, ',', '.') ?> XP</small>
        </strong>
        <div class="barra-progresso barra-progresso-fina" title="<?= xpNoNivel($xpAtual) ?> de <?= XP_POR_NIVEL ?> XP neste nível">
            <div class="barra-progresso-preenchida" style="width: <?= percentualDoNivel($xpAtual) ?>%;"></div>
        </div>
    </div>

    <div class="cartao-numero-inicio">
        <span class="cartao-numero-rotulo"><?= icone('fogo', 18) ?> Sequência</span>
        <strong><?= $ofensivaAtual ?></strong>
        <small class="destaque-laranja"><?= $ofensivaAtual === 1 ? 'dia seguido' : 'dias seguidos' ?></small>
    </div>

    <div class="cartao-numero-inicio">
        <span class="cartao-numero-rotulo"><?= icone('estrela', 18) ?> Conquistas</span>
        <strong><?= (int) $resumoLateral['conquistas'] ?></strong>
        <small class="destaque-laranja">em <?= $totalConquistas ?> disponíveis</small>
    </div>
</section>

<div class="colunas colunas-inicio">

    <div class="painel painel-inicio">
        <h2 class="painel-titulo-inicio">Continue aprendendo</h2>

        <?php if ($continuar !== null): ?>
            <a href="<?= e($urlContinuar) ?>" class="cartao-continuar">
                <div class="cartao-continuar-topo">
                    <?= iconeDaTrilha($continuar['trilha']['icone']) ?>
                    <div class="cartao-continuar-info">
                        <small><?= $continuar['feitas'] > 0 ? 'Trilha em andamento' : 'Comece por aqui' ?></small>
                        <strong><?= e($continuar['trilha']['titulo']) ?></strong>
                        <span><?= e($continuar['trilha']['descricao']) ?></span>
                    </div>
                    <span class="selo-percentual"><?= $continuar['percentual'] ?>%</span>
                </div>

                <div class="barra-progresso">
                    <div class="barra-progresso-preenchida" style="width: <?= $continuar['percentual'] ?>%;"></div>
                </div>

                <div class="cartao-continuar-rodape">
                    <span>
                        Módulo <?= $moduloAtual['posicao'] ?> de <?= $moduloAtual['total'] ?>
                        <?php if ($moduloAtual['modulo'] !== null): ?>
                            · <?= e($moduloAtual['modulo']['titulo']) ?>
                        <?php endif; ?>
                    </span>
                    <span><?= $continuar['percentual'] ?>% concluído</span>
                </div>
            </a>
        <?php else: ?>
            <div class="vazio-compacto">
                <?= icone('check', 22) ?>
                <p>Você concluiu todas as trilhas publicadas. Que tal revisar ou subir no ranking?</p>
            </div>
        <?php endif; ?>

        <h2 class="painel-titulo-inicio painel-titulo-separado">Desafios em destaque</h2>

        <?php if (empty($destaques)): ?>
            <div class="vazio-compacto">
                <?= icone('alvo', 22) ?>
                <p>Nenhum desafio liberado agora. Conclua o módulo atual para liberar os próximos.</p>
            </div>
        <?php else: ?>
            <ul class="lista-desafios">
                <?php foreach ($destaques as $d): ?>
                    <li>
                        <a href="desafio.php?id=<?= (int) $d['id'] ?>" class="linha-desafio">
                            <span class="linha-desafio-icone"><?= icone(iconeDoTipo($d['tipo']), 18) ?></span>
                            <span class="linha-desafio-info">
                                <strong><?= e($d['titulo']) ?></strong>
                                <small><?= e($d['trilha_titulo']) ?> • <?= e($d['modulo_titulo']) ?></small>
                            </span>
                            <span class="linha-desafio-xp">+<?= (int) $d['xp_recompensa'] ?> XP</span>
                            <?= icone('chevron', 18) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <a href="desafios.php" class="link-centro">Ver todos os desafios</a>
    </div>

    <div class="coluna-lateral coluna-lateral-inicio">

        <div class="painel painel-inicio">
            <div class="painel-inicio-cabecalho">
                <h2 class="painel-titulo-inicio"><?= icone('ranking', 18) ?> Top Ranking</h2>
                <a href="ranking.php">Ver ranking</a>
            </div>

            <?php if (empty($ranking['topo'])): ?>
                <p class="aviso-suave">Ninguém pontuou ainda. Seja o primeiro!</p>
            <?php else: ?>
                <ol class="mini-ranking">
                    <?php
                    $linhas = $ranking['topo'];
                    if ($ranking['eu'] !== null) {
                        $linhas[] = $ranking['eu'];
                    }
                    ?>
                    <?php foreach ($linhas as $linha): ?>
                        <?php $souEu = (int) $linha['id'] === $alunoId; ?>
                        <li class="<?= $souEu ? 'mini-ranking-eu' : '' ?>">
                            <span class="medalha medalha-<?= min(4, (int) $linha['posicao']) ?>"><?= (int) $linha['posicao'] ?></span>
                            <?= imgAvatar($linha['foto_avatar'], 'avatar-pequeno', classeMoldura($linha['moldura_slug'])) ?>
                            <span class="mini-ranking-nome"><?= e($linha['nome']) ?></span>
                            <span class="mini-ranking-xp"><?= number_format((int) $linha['xp'], 0, ',', '.') ?> XP</span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>

        <div class="painel painel-inicio">
            <h2 class="painel-titulo-inicio">Acesso rápido</h2>
            <nav class="acesso-rapido" aria-label="Acesso rápido">
                <a href="trilhas.php"><?= icone('trilhas', 26) ?><span>Trilhas</span></a>
                <a href="desafios.php"><?= icone('alvo', 26) ?><span>Desafios</span></a>
                <a href="ranking.php"><?= icone('ranking', 26) ?><span>Ranking</span></a>
                <a href="loja.php"><?= icone('loja', 26) ?><span>Loja</span></a>
            </nav>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
