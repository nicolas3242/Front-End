<?php
/**
 * includes/header.php
 * -----------------------------------------------------------------------------
 * Barra lateral e topo das páginas internas, seguindo os wireframes do projeto.
 *
 * Toda página autenticada inclui, nesta ordem:
 *     require_once __DIR__ . '/config/conexao.php';
 *     $tituloPagina = 'Trilhas de Aprendizado';          // opcional
 *     $subtituloPagina = 'Escolha uma trilha...';        // opcional
 *     require_once __DIR__ . '/includes/header.php';
 * e fecha com:
 *     require_once __DIR__ . '/includes/footer.php';
 *
 * O header já exige login — a página incluída não precisa repetir a checagem.
 * Páginas restritas ainda precisam chamar exigirAdmin().
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';        // token do botão Sair e das APIs (meta csrf)
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/loja.php';
require_once __DIR__ . '/progresso.php';
require_once __DIR__ . '/avatares.php';

if (!isset($pdo)) {
    die('Erro de configuração: inclua config/conexao.php antes de includes/header.php.');
}

exigirLogin();

$usuarioLogadoDados = usuarioAtual($pdo);

// A sessão aponta para um usuário que não existe mais no banco: encerra.
if ($usuarioLogadoDados === null) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// Lidos do banco a cada carregamento, e não da sessão, para que o XP ganho
// num desafio já apareça na barra lateral da página seguinte.
$xpAtual       = (int) $usuarioLogadoDados['xp'];
$nivelAtual    = calcularNivel($xpAtual);
// Vigente: quem não estudou nem hoje nem ontem já perdeu a sequência.
$ofensivaAtual = ofensivaVigente((int) $usuarioLogadoDados['sequencia_dias'], $usuarioLogadoDados['ultimo_dia_ativo']);
$ehAdmin       = $usuarioLogadoDados['tipo'] === 'admin';
$paginaAtual   = basename($_SERVER['PHP_SELF']);
$moedasAtuais  = saldoDeMoedas($pdo, (int) $usuarioLogadoDados['id']);

// Título sob o nome: o comprado na Loja tem prioridade sobre o do nível.
$tituloExibido = $ehAdmin
    ? 'Administrador'
    : ($usuarioLogadoDados['titulo_loja'] ?? tituloDoNivel($nivelAtual));
$classeAvatar  = classeMoldura($usuarioLogadoDados['moldura_slug']);

// Sino: os últimos acontecimentos do próprio usuário (ver notificacoesRecentes).
$notificacoes = notificacoesRecentes($pdo, (int) $usuarioLogadoDados['id']);

// Números do rodapé da barra lateral, como nos wireframes.
$stmt = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM conquistas_usuarios WHERE usuario_id = ?) AS conquistas,
        (SELECT COUNT(DISTINCT m.trilha_id)
           FROM atividades_concluidas ac
           JOIN atividades a ON a.id = ac.atividade_id
           JOIN modulos m    ON m.id = a.modulo_id
          WHERE ac.aluno_id = ?) AS trilhas"
);
$stmt->execute([usuarioAtualId(), usuarioAtualId()]);
$resumoLateral = $stmt->fetch() ?: ['conquistas' => 0, 'trilhas' => 0];

/** Marca o item de menu correspondente à página aberta. */
function menuAtivo(string ...$arquivos): string
{
    return in_array(basename($_SERVER['PHP_SELF']), $arquivos, true) ? 'ativo' : '';
}

$tituloPagina    = $tituloPagina    ?? 'DevFox';
$subtituloPagina = $subtituloPagina ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevFox — <?= e($tituloPagina) ?></title>
    <link rel="icon" type="image/png" href="assets/imagens/logo-marca.png">
    <link rel="stylesheet" href="<?= asset('css/styles.css') ?>">
    <?php // Lido pelo JS (desafios.js) para mandar o token nas chamadas às APIs. ?>
    <meta name="csrf" content="<?= e(tokenCsrf()) ?>">
</head>
<body>

<div class="app">

    <aside class="barra-lateral">
        <a href="inicio.php" class="marca">
            <img src="assets/imagens/logo-marca.png" alt="" class="marca-simbolo">
            <span class="marca-nome">Dev<span>Fox</span></span>
        </a>

        <nav class="menu" aria-label="Navegação principal">
            <a href="inicio.php" class="menu-item <?= menuAtivo('inicio.php') ?>">
                <?= icone('inicio') ?><span>Início</span>
            </a>
            <a href="trilhas.php" class="menu-item <?= menuAtivo('trilhas.php', 'desafio.php', 'prova.php') ?>">
                <?= icone('trilhas') ?><span>Trilhas</span>
            </a>
            <a href="desafios.php" class="menu-item <?= menuAtivo('desafios.php') ?>">
                <?= icone('alvo') ?><span>Desafios</span>
            </a>
            <a href="ranking.php" class="menu-item <?= menuAtivo('ranking.php') ?>">
                <?= icone('ranking') ?><span>Ranking</span>
            </a>

            <a href="loja.php" class="menu-item <?= menuAtivo('loja.php') ?>">
                <?= icone('loja') ?><span>Loja</span>
            </a>

            <hr class="menu-divisor">

            <a href="perfil.php" class="menu-item <?= menuAtivo('perfil.php', 'conquistas.php', 'atividades.php') ?>">
                <?= icone('perfil') ?><span>Perfil</span>
            </a>
            <a href="configuracoes.php" class="menu-item <?= menuAtivo('configuracoes.php') ?>">
                <?= icone('admin') ?><span>Configurações</span>
            </a>

            <?php if ($ehAdmin): ?>
                <a href="administracao.php" class="menu-item <?= menuAtivo('administracao.php', 'admin_trilha.php', 'admin_modulo.php', 'admin_atividade.php', 'admin_suporte.php', 'admin_alunos.php', 'admin_aluno.php', 'admin_relatorios.php', 'admin_conquistas.php', 'admin_loja.php', 'admin_registro.php', 'admin_busca.php') ?>">
                    <?= icone('painel') ?><span>Administração</span>
                </a>
            <?php endif; ?>

            <?php // Sair é POST com token: por GET, qualquer <img src="logout.php"> em outro site deslogava o aluno. ?>
            <form method="post" action="logout.php" class="form-sair">
                <?= campoCsrf() ?>
                <?php // Sem type="submit" escrito: dentro de <form>, <button> já envia. Assim o
                      // botão Sair não é pego por seletores "button[type=submit]" (os testes
                      // e scripts que procuram o botão de enviar do conteúdo da página). ?>
                <button class="menu-item menu-item-sair">
                    <?= icone('sair') ?><span>Sair</span>
                </button>
            </form>
        </nav>

        <div class="cartao-usuario">
            <div class="cartao-usuario-topo">
                <?= imgAvatar($usuarioLogadoDados['foto_avatar'], '', $classeAvatar) ?>
                <div class="cartao-usuario-nome">
                    <strong><?= e($usuarioLogadoDados['nome']) ?></strong>
                    <small><?= e($tituloExibido) ?></small>
                </div>
            </div>

            <div class="cartao-usuario-nivel">
                <span>Nível <?= $nivelAtual ?></span>
                <span class="numeros"><?= xpNoNivel($xpAtual) ?> / <?= XP_POR_NIVEL ?> XP</span>
            </div>
            <div class="barra-progresso barra-progresso-fina">
                <div class="barra-progresso-preenchida" style="width: <?= percentualDoNivel($xpAtual) ?>%;"></div>
            </div>

            <ul class="cartao-usuario-numeros">
                <li>
                    <?= icone('fogo', 18) ?>
                    <strong><?= $ofensivaAtual ?></strong>
                    <small>Sequência</small>
                </li>
                <li>
                    <?= icone('estrela', 18) ?>
                    <strong><?= (int) $resumoLateral['conquistas'] ?></strong>
                    <small>Conquistas</small>
                </li>
                <li>
                    <?= icone('camadas', 18) ?>
                    <strong><?= (int) $resumoLateral['trilhas'] ?></strong>
                    <small>Trilhas</small>
                </li>
            </ul>
        </div>
    </aside>

    <main class="conteudo">
        <header class="topo">
            <div class="topo-titulo">
                <h1><?= e($tituloPagina) ?></h1>
                <?php if ($subtituloPagina !== ''): ?>
                    <p><?= e($subtituloPagina) ?></p>
                <?php endif; ?>
            </div>

            <div class="topo-acoes">
                <span class="pilula-xp" title="XP total acumulado">
                    <?= icone('xp', 16) ?>
                    <?= number_format($xpAtual, 0, ',', '.') ?> XP
                </span>
                <a href="loja.php" class="pilula-moedas" title="Moedas para gastar na Loja (1 a cada <?= XP_POR_MOEDA ?> XP)">
                    <?= icone('moeda', 16) ?>
                    <span id="saldoMoedas"><?= number_format($moedasAtuais, 0, ',', '.') ?></span>
                </a>
                <?php
                    // Marca de tempo do evento mais novo: o JS compara com a última
                    // vez que o sino foi aberto NESTE navegador para decidir se
                    // mostra o ponto de "novidade".
                    $maisNova = $notificacoes ? strtotime($notificacoes[0]['quando']) : 0;
                    $textoTipo = [
                        'conquista'    => ['estrela', 'Conquista desbloqueada'],
                        'atividade'    => ['check',   'Atividade concluída'],
                        'prova_ok'     => ['prova',   'Prova aprovada'],
                        'prova_falhou' => ['prova',   'Prova não aprovada'],
                        'compra'       => ['loja',    'Compra na Loja'],
                    ];
                ?>
                <div class="sino" data-usuario="<?= (int) $usuarioLogadoDados['id'] ?>"
                     data-mais-nova="<?= (int) $maisNova ?>">
                    <button type="button" class="botao-sino" id="botaoSino"
                            aria-haspopup="true" aria-expanded="false" aria-controls="painelSino"
                            title="Notificações">
                        <?= icone('sino', 20) ?>
                        <span class="sino-ponto" hidden></span>
                    </button>

                    <div class="painel-sino" id="painelSino" hidden>
                        <strong class="painel-sino-titulo">Notificações</strong>

                        <?php if (empty($notificacoes)): ?>
                            <p class="painel-sino-vazio">
                                Nada por aqui ainda. Conclua uma atividade para começar.
                            </p>
                        <?php else: ?>
                            <ul class="lista-sino">
                                <?php foreach ($notificacoes as $n): ?>
                                    <?php [$ic, $rotulo] = $textoTipo[$n['tipo']] ?? ['sino', 'Novidade']; ?>
                                    <li class="item-sino item-sino-<?= e($n['tipo']) ?>">
                                        <span class="item-sino-icone"><?= icone($ic, 16) ?></span>
                                        <span class="item-sino-texto">
                                            <small><?= e($rotulo) ?></small>
                                            <strong><?= e($n['texto']) ?></strong>
                                            <em><?= e($n['detalhe']) ?></em>
                                        </span>
                                        <time datetime="<?= e($n['quando']) ?>"><?= e(tempoRelativo($n['quando'])) ?></time>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <a href="perfil.php" class="painel-sino-rodape">Ver meu progresso</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="pagina">
