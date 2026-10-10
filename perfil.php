<?php
/**
 * perfil.php — perfil do aluno, no layout do wireframe.
 *
 * Blocos: cartão do perfil (avatar, nível, XP, ações e 4 números), conquistas
 * (as 4 mais recentes, com "Ver todas"), estatísticas com a variação da
 * semana, frase do dia, mascote e atividade recente.
 *
 * Tudo vem do banco. As regras de conta (nome, senha) ficam em Configurações;
 * aqui só se troca o avatar, escolhido de um catálogo fixo (includes/avatares.php).
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';        // CSRF, redirecionarCom(), consumirFlash()
require_once __DIR__ . '/includes/avatares.php';

exigirLogin();
$alunoId = (int) usuarioAtualId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $codigo = (string) ($_POST['avatar'] ?? '');

    // Só aceita código do catálogo: nunca um caminho enviado pelo navegador.
    if (!array_key_exists($codigo, avataresDisponiveis())) {
        redirecionarCom('perfil.php', 'Avatar inválido.', 'erro');
    }
    $pdo->prepare("UPDATE usuarios SET foto_avatar = ? WHERE id = ?")->execute([$codigo, $alunoId]);
    redirecionarCom('perfil.php', 'Avatar atualizado.');
}

$flash = consumirFlash();

$tituloPagina    = 'Perfil';
$subtituloPagina = 'Gerencie sua conta, acompanhe seu progresso e conquistas.';
require_once __DIR__ . '/includes/header.php';

$dados   = dadosDasTrilhas($pdo, $alunoId);
$numeros = estatisticasDoPerfil($pdo, $alunoId, $dados);
$eventos = historicoDoUsuario($pdo, $alunoId, 4);

$stmt = $pdo->prepare("SELECT melhor_sequencia FROM usuarios WHERE id = ?");
$stmt->execute([$alunoId]);
$melhorSequencia = max((int) $stmt->fetchColumn(), $ofensivaAtual);

// Conquistas: as desbloqueadas (mais recentes primeiro) e, se sobrar espaço,
// as próximas a desbloquear — o aluno vê o que falta.
$stmt = $pdo->prepare(
    "SELECT c.titulo, c.descricao, c.icone, c.raridade, cu.desbloqueada_em
     FROM conquistas c
     LEFT JOIN conquistas_usuarios cu ON cu.conquista_id = c.id AND cu.usuario_id = ?
     WHERE c.ativa = 1 OR cu.id IS NOT NULL      -- desativada só aparece para quem já tinha ganhado
     ORDER BY (cu.id IS NULL), cu.desbloqueada_em DESC, c.id"
);
$stmt->execute([$alunoId]);
$conquistas = $stmt->fetchAll();
$totalConquistas = count($conquistas);

// Admin vê as conquistas abertas, como o aluno as verá depois de ganhar —
// é a única forma de conferir arte, título e texto sem ter que jogar o
// sistema inteiro. Elas ficam marcadas como PRÉVIA, e os números do perfil
// continuam sendo os reais (a prévia não conta como conquista ganha, nem
// aqui, nem no ranking, nem nos relatórios).
$previaAdmin = usuarioEhAdmin();

$proximoNivelXp = $nivelAtual * XP_POR_NIVEL;

// Frase do dia: muda uma vez por dia, igual para todo mundo.
$frases = [
    ['Cada linha de código te aproxima do seu melhor.', 'Continue aprendendo e evoluindo!'],
    ['Errar faz parte: todo bug corrigido é algo que você aprendeu.', 'Leia a mensagem de erro com calma.'],
    ['Um pouco por dia vale mais que muito de uma vez.', 'Mantenha sua sequência acesa!'],
    ['Programar é resolver problemas, um passo de cada vez.', 'Quebre o desafio em partes menores.'],
    ['Quem ensina o computador também aprende a pensar.', 'Seu raciocínio está ficando mais afiado.'],
];
[$frase, $complemento] = $frases[(int) date('z') % count($frases)];
?>

<?php if ($flash): ?>
    <?= exibeMensagem($flash[0], $flash[1]) ?>
<?php endif; ?>

<div class="colunas colunas-perfil">

    <div class="coluna-principal">

        <section class="painel cartao-perfil">
            <div class="cartao-perfil-topo">
                <div class="perfil-avatar">
                    <?= imgAvatar($usuarioLogadoDados['foto_avatar'], 'avatar-perfil', $classeAvatar) ?>
                </div>

                <div class="cartao-perfil-info">
                    <h2><?= e($usuarioLogadoDados['nome']) ?></h2>
                    <p class="perfil-papel">
                        <span><?= e($tituloExibido) ?></span>
                        <span class="selo-nivel">Nível <?= $nivelAtual ?></span>
                    </p>
                    <small class="perfil-xp">
                        <?= number_format($xpAtual, 0, ',', '.') ?> / <?= number_format($proximoNivelXp, 0, ',', '.') ?> XP
                    </small>
                    <div class="barra-progresso" title="<?= xpNoNivel($xpAtual) ?> de <?= XP_POR_NIVEL ?> XP neste nível">
                        <div class="barra-progresso-preenchida" style="width: <?= percentualDoNivel($xpAtual) ?>%;"></div>
                    </div>
                </div>

                <div class="cartao-perfil-acoes">
                    <a href="configuracoes.php" class="botao botao-contorno botao-destaque-suave">
                        <?= icone('lapis', 16) ?> Editar perfil
                    </a>
                    <button type="button" class="botao botao-secundario" id="btnAvatar"
                            aria-haspopup="dialog" aria-controls="dialogoAvatar">
                        <?= icone('perfil', 16) ?> Alterar avatar
                    </button>
                </div>
            </div>

            <ul class="perfil-numeros">
                <li><?= icone('fogo', 24) ?><div><strong><?= $ofensivaAtual ?></strong><small>Sequência atual</small></div></li>
                <li><?= icone('estrela', 24) ?><div><strong><?= (int) $resumoLateral['conquistas'] ?></strong><small>Conquistas</small></div></li>
                <li><?= icone('mapa', 24) ?><div><strong><?= $numeros['trilhas'] ?></strong><small>Trilhas concluídas</small></div></li>
                <li><?= icone('alvo', 24) ?><div><strong><?= $numeros['desafios'] ?></strong><small>Desafios concluídos</small></div></li>
            </ul>
        </section>

        <section class="painel">
            <div class="painel-inicio-cabecalho">
                <h2 class="painel-titulo-inicio">Conquistas</h2>
                <a href="conquistas.php" class="link-laranja">Ver todas</a>
            </div>

            <div class="grade-conquistas grade-conquistas-perfil">
                <?php foreach (array_slice($conquistas, 0, 4) as $c): ?>
                    <?php
                        $ganha  = $c['desbloqueada_em'] !== null;
                        $aberta = $ganha || $previaAdmin;
                    ?>
                    <div class="conquista conquista-<?= e($c['raridade']) ?> <?= $aberta ? '' : 'conquista-bloqueada' ?>">
                        <?= seloConquista((string) $c['icone'], (string) $c['raridade'], !$aberta) ?>
                        <strong><?= e($c['titulo']) ?></strong>
                        <small><?= e($c['descricao']) ?></small>
                        <small class="conquista-data">
                            <?php if ($ganha): ?>
                                <?= date('d/m/Y', strtotime($c['desbloqueada_em'])) ?>
                            <?php elseif ($previaAdmin): ?>
                                <span class="selo selo-previa">prévia de admin</span>
                            <?php else: ?>
                                Bloqueada
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="painel">
            <h2 class="painel-titulo-inicio">Estatísticas</h2>

            <div class="grade-estatisticas">
                <div class="cartao-estatistica">
                    <span class="cartao-numero-rotulo"><span class="icone-circulo">XP</span> XP total</span>
                    <strong><?= number_format($xpAtual, 0, ',', '.') ?></strong>
                    <small class="variacao <?= $numeros['xp_semana'] > 0 ? 'variacao-positiva' : '' ?>">
                        +<?= number_format($numeros['xp_semana'], 0, ',', '.') ?> esta semana
                    </small>
                </div>
                <div class="cartao-estatistica">
                    <span class="cartao-numero-rotulo"><?= icone('alvo', 20) ?> Desafios concluídos</span>
                    <strong><?= $numeros['desafios'] ?></strong>
                    <small class="variacao <?= $numeros['desafios_semana'] > 0 ? 'variacao-positiva' : '' ?>">
                        +<?= $numeros['desafios_semana'] ?> esta semana
                    </small>
                </div>
                <div class="cartao-estatistica">
                    <span class="cartao-numero-rotulo cor-verde"><?= icone('mapa', 20) ?> Trilhas concluídas</span>
                    <strong><?= $numeros['trilhas'] ?></strong>
                    <small class="variacao <?= $numeros['trilhas_semana'] > 0 ? 'variacao-positiva' : '' ?>">
                        +<?= $numeros['trilhas_semana'] ?> esta semana
                    </small>
                </div>
                <div class="cartao-estatistica">
                    <span class="cartao-numero-rotulo"><?= icone('fogo', 20) ?> Sequência atual</span>
                    <strong><?= plural($ofensivaAtual, 'dia', 'dias') ?></strong>
                    <small class="variacao destaque-laranja">Melhor: <?= plural($melhorSequencia, 'dia', 'dias') ?></small>
                </div>
            </div>
        </section>

        <section class="painel frase-perfil">
            <span class="frase-aspas"><?= icone('aspas', 34) ?></span>
            <div>
                <p><?= e($frase) ?></p>
                <small><?= e($complemento) ?></small>
            </div>
            <img src="assets/imagens/logo-marca.png" alt="" class="frase-marca">
        </section>
    </div>

    <aside class="coluna-lateral coluna-lateral-perfil">
        <section class="painel painel-mascote">
            <h2 class="painel-titulo-inicio">Mascote</h2>
            <p class="aviso-suave">Seu companheiro de jornada</p>
            <div class="mascote<?= classeMoldura($usuarioLogadoDados['moldura_slug']) ?>">
                <img src="assets/imagens/mascote.jpg" alt="A raposa do DevFox sentada numa montanha">
                <span class="mascote-anel" aria-hidden="true"></span>
            </div>
            <a href="loja.php" class="botao botao-contorno botao-bloco">
                <?= icone('camiseta', 18) ?> Personalizar mascote
            </a>
            <small class="ajuda-campo mascote-ajuda">O anel acompanha a moldura que você equipar na Loja.</small>
        </section>

        <section class="painel">
            <div class="painel-inicio-cabecalho">
                <h2 class="painel-titulo-inicio">Atividade recente</h2>
                <a href="atividades.php" class="link-laranja">Ver todas</a>
            </div>

            <?php if (empty($eventos)): ?>
                <p class="aviso-suave">
                    Nada por aqui ainda. <a href="trilhas.php">Comece uma trilha</a> e sua atividade aparece aqui.
                </p>
            <?php else: ?>
                <ul class="lista-eventos">
                    <?php foreach ($eventos as $ev): ?>
                        <?php [$ic, $rotulo, $cor] = aparenciaDoEvento($ev['tipo']); ?>
                        <li>
                            <span class="evento-icone evento-<?= $cor ?>"><?= icone($ic, 18) ?></span>
                            <div>
                                <strong><?= e($rotulo) ?></strong>
                                <small><?= e($ev['texto']) ?><?= $ev['tipo'] === 'atividade' ? ' · ' . e($ev['detalhe']) : '' ?></small>
                            </div>
                            <time datetime="<?= e($ev['quando']) ?>"><?= e(tempoRelativo($ev['quando'])) ?></time>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        </section>
    </aside>
</div>

<?php /* Escolha de avatar numa janela <dialog>, aberta por assets/js/perfil.js
         (o botão "Alterar avatar" precisa de JavaScript). */ ?>
<dialog id="dialogoAvatar" class="dialogo" aria-labelledby="tituloAvatar">
    <form method="post" id="escolher-avatar">
        <?= campoCsrf() ?>
        <h2 id="tituloAvatar" class="painel-titulo-inicio">Escolha seu avatar</h2>
        <div class="grade-avatares">
            <?php foreach (avataresDisponiveis() as $codigo => [$arquivo, $fundo, $rotulo]): ?>
                <label class="opcao-avatar">
                    <input type="radio" name="avatar" value="<?= e($codigo) ?>"
                           <?= codigoAvatar($usuarioLogadoDados['foto_avatar']) === $codigo ? 'checked' : '' ?>>
                    <?= imgAvatar($codigo, 'avatar-opcao') ?>
                    <small><?= e($rotulo) ?></small>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="dialogo-acoes">
            <button type="button" class="botao botao-secundario" data-fechar>Cancelar</button>
            <button type="submit" class="botao botao-primario">Salvar avatar</button>
        </div>
    </form>
</dialog>

<script src="<?= asset('js/perfil.js') ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
