<?php
/**
 * administracao.php — central de gestão de conteúdo do DevFox.
 *
 * O DevFox é uma plataforma de conteúdo, como Duolingo e Alura: quem cadastra
 * trilhas, aulas e atividades é a equipe do produto, não um professor que se
 * cadastra na plataforma. Por isso esta tela se chama Administração, e o papel
 * no banco é `admin` — o antigo `professor` induzia a um modelo de sala de aula
 * que não é o do projeto.
 *
 * É a primeira de três telas encadeadas:
 *     administracao.php → admin_trilha.php → admin_modulo.php → admin_atividade.php
 *
 * O controle de acesso é feito no servidor com exigirAdmin(). A versão original
 * do protótipo usava um alert() em JavaScript, que não protege nada: o conteúdo
 * da página já tinha sido enviado ao navegador quando o alerta aparecia.
 *
 * Todo POST que altera dados passa por exigirCsrf().
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';

exigirAdmin();

$dificuldadesValidas = ['iniciante', 'intermediario', 'avancado'];

/* -----------------------------------------------------------------------------
 * POST — tratado antes de qualquer saída HTML, para poder redirecionar
 * (Post/Redirect/Get: atualizar a página não cadastra a mesma trilha de novo).
 * -------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();

    $acao     = $_POST['acao'] ?? 'criar';
    $trilhaId = (int) ($_POST['trilha_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, titulo, ordem, publicada, arquivada FROM trilhas WHERE id = ?");
    $stmt->execute([$trilhaId]);
    $alvo = $stmt->fetch();

    if ($acao !== 'criar' && !$alvo) {
        redirecionarCom('administracao.php', 'Trilha não encontrada.', 'erro');
    }

    if ($acao === 'publicar') {
        $novoEstado = (int) $alvo['publicada'] === 1 ? 0 : 1;
        if ($novoEstado === 1 && (int) $alvo['arquivada'] === 1) {
            redirecionarCom('administracao.php', 'Desarquive a trilha antes de publicar.', 'erro');
        }

        $pdo->prepare("UPDATE trilhas SET publicada = ? WHERE id = ?")->execute([$novoEstado, $trilhaId]);
        registrarAcaoAdmin($pdo, $novoEstado ? 'trilha.publicar' : 'trilha.despublicar', 'Trilha: ' . $alvo['titulo']);

        redirecionarCom('administracao.php',
            $novoEstado
                ? 'Trilha publicada. Os alunos já conseguem vê-la.'
                : 'Trilha despublicada. Ela some da lista dos alunos, mas nada é apagado.');
    }

    /* ---- ordem das trilhas na tela do aluno (ADM-25) ---- */
    if ($acao === 'mover') {
        $cima  = ($_POST['direcao'] ?? '') === 'cima';
        $comp  = $cima ? '<' : '>';
        $sent  = $cima ? 'DESC' : 'ASC';
        // $comp e $sent vêm do ternário acima, nunca do POST.
        $stmt = $pdo->prepare(
            "SELECT id, ordem FROM trilhas
              WHERE arquivada = 0 AND (ordem $comp ? OR (ordem = ? AND id $comp ?))
              ORDER BY ordem $sent, id $sent LIMIT 1"
        );
        $stmt->execute([(int) $alvo['ordem'], (int) $alvo['ordem'], $trilhaId]);
        if ($vizinho = $stmt->fetch()) {
            // Se as duas tinham a mesma ordem, a troca não mudaria nada: separa.
            $novaAlvo    = (int) $vizinho['ordem'];
            $novaVizinho = (int) $alvo['ordem'];
            if ($novaAlvo === $novaVizinho) {
                $novaAlvo += $cima ? 0 : 1;
                $novaVizinho += $cima ? 1 : 0;
            }
            $troca = $pdo->prepare("UPDATE trilhas SET ordem = ? WHERE id = ?");
            $troca->execute([$novaAlvo, $trilhaId]);
            $troca->execute([$novaVizinho, (int) $vizinho['id']]);
            registrarAcaoAdmin($pdo, 'trilha.mover', 'Trilha: ' . $alvo['titulo']);
        }
        header('Location: administracao.php');
        exit;
    }

    /* ---- arquivar / desarquivar (ADM-26) ---- */
    if ($acao === 'arquivar' || $acao === 'desarquivar') {
        $arquivar = $acao === 'arquivar';
        // Arquivar também despublica: a trilha some do aluno, mas o progresso
        // de quem já fez continua no banco. Desarquivar volta como rascunho.
        $pdo->prepare("UPDATE trilhas SET arquivada = ?, publicada = 0 WHERE id = ?")
            ->execute([$arquivar ? 1 : 0, $trilhaId]);
        registrarAcaoAdmin($pdo, 'trilha.' . $acao, 'Trilha: ' . $alvo['titulo']);
        redirecionarCom('administracao.php', $arquivar
            ? 'Trilha arquivada: saiu da lista dos alunos e do painel. O progresso de quem já fez foi mantido.'
            : 'Trilha desarquivada. Ela voltou como rascunho — publique quando quiser.');
    }

    /* ---- apagar de vez: só trilha que ninguém usou (ADM-26) ---- */
    if ($acao === 'apagar') {
        if (trilhaTemProgresso($pdo, $trilhaId)) {
            redirecionarCom('administracao.php',
                'Esta trilha não pode ser apagada: algum aluno já tem progresso nela. Arquive em vez de apagar.', 'erro');
        }
        $pdo->prepare("DELETE FROM trilhas WHERE id = ?")->execute([$trilhaId]);
        registrarAcaoAdmin($pdo, 'trilha.apagar', 'Trilha: ' . $alvo['titulo']);
        redirecionarCom('administracao.php', 'Trilha apagada.');
    }

    if ($acao !== 'criar') {
        redirecionarCom('administracao.php', 'Ação inválida.', 'erro');
    }

    $titulo      = trim($_POST['titulo'] ?? '');
    $descricao   = trim($_POST['descricao'] ?? '');
    $dificuldade = $_POST['nivel_dificuldade'] ?? 'iniciante';
    $icone       = trim($_POST['icone'] ?? '');
    $linguagem   = array_key_exists($_POST['linguagem'] ?? '', linguagensDeTrilha())
                   ? $_POST['linguagem'] : 'javascript';

    if (!array_key_exists($icone, iconesDeTrilha())) {
        $icone = '';
    }

    if (!in_array($dificuldade, $dificuldadesValidas, true)) {
        $dificuldade = 'iniciante';
    }

    if ($titulo === '') {
        redirecionarCom('administracao.php', 'Informe pelo menos o título da trilha.', 'erro');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO trilhas (titulo, descricao, nivel_dificuldade, icone, linguagem, ordem, publicada)
         VALUES (?, ?, ?, ?, ?, ?, 0)"
    );
    $stmt->execute([
        $titulo,
        $descricao !== '' ? $descricao : null,
        $dificuldade,
        $icone !== '' ? $icone : null,
        $linguagem,
        (int) $pdo->query("SELECT COALESCE(MAX(ordem), 0) + 1 FROM trilhas")->fetchColumn(),
    ]);

    $novaTrilha = (int) $pdo->lastInsertId();
    registrarAcaoAdmin($pdo, 'trilha.criar', 'Trilha: ' . $titulo);

    // Nasce despublicada de propósito: ninguém quer uma trilha vazia aparecendo
    // para os alunos enquanto o conteúdo ainda está sendo escrito.
    redirecionarCom('admin_trilha.php?id=' . $novaTrilha,
        'Trilha criada. Ela está como rascunho — publique quando o conteúdo estiver pronto.');
}

$flash = consumirFlash();

$tituloPagina    = 'Administração';
$subtituloPagina = 'Conteúdo, alunos, relatórios e tudo o que o admin gerencia.';

require_once __DIR__ . '/includes/header.php';

$todas = $pdo->query(
    "SELECT t.id, t.titulo, t.nivel_dificuldade, t.publicada, t.arquivada, t.ordem, t.icone,
            (SELECT COUNT(*) FROM modulos m WHERE m.trilha_id = t.id) AS modulos,
            (SELECT COUNT(*) FROM atividades a
               JOIN modulos m ON m.id = a.modulo_id
              WHERE m.trilha_id = t.id) AS atividades
     FROM trilhas t
     ORDER BY t.ordem, t.id"
)->fetchAll();
$trilhas    = array_values(array_filter($todas, static fn(array $t): bool => (int) $t['arquivada'] === 0));
$arquivadas = array_values(array_filter($todas, static fn(array $t): bool => (int) $t['arquivada'] === 1));

/* ---- números gerais (ADM-12) ---- */
$n          = numerosDoPainel($pdo);
$taxaAcerto = $n['taxa_acerto'];
$suporteNaoLidas = suporteNaoLidas($pdo);

/* ---- painel inicial: gráficos, feed, destaques e ranking ---- */
$serie        = serieDosUltimosDias($pdo, 7);
$distribuicao = distribuicaoPorNivel($pdo);
$atividades   = ultimasAtividades($pdo, 6);
$destaques    = desafiosEmDestaque($pdo, 5);
$ranking      = resumoDoRanking($pdo, usuarioAtualId(), 5)['topo'];

// Cartões do topo: valor, variação em 30 dias e para onde o cartão leva.
$cartoes = [
    ['perfil', 'Total de alunos',      $n['alunos'],       variacao($n['novos30'], $n['novos30ant']),           'admin_alunos.php',     'laranja'],
    ['fogo',   'Alunos ativos',         $n['ativos30'],     variacao($n['ativos30'], $n['ativos30ant']),         'admin_relatorios.php', 'azul'],
    ['check',  'Atividades concluídas', $n['concluidas30'], variacao($n['concluidas30'], $n['concluidas30ant']), 'admin_relatorios.php', 'verde'],
    ['estrela','Novos cadastros',       $n['novos30'],      variacao($n['novos30'], $n['novos30ant']),           'admin_alunos.php',     'roxo'],
];

/* ---- conteúdo com problema (ADM-13) ---- */
$alertas = [];
foreach ($pdo->query(
    "SELECT m.id, m.titulo, m.tipo, t.titulo AS trilha, t.publicada
       FROM modulos m JOIN trilhas t ON t.id = m.trilha_id
      WHERE t.arquivada = 0
        AND NOT EXISTS (SELECT 1 FROM atividades a WHERE a.modulo_id = m.id)
      ORDER BY t.ordem, m.ordem"
) as $m) {
    $alertas[] = [
        ($m['tipo'] === 'prova' ? 'Prova sem questões: ' : 'Módulo sem atividades: ') . $m['titulo'] . ' (' . $m['trilha'] . ')'
            . ((int) $m['publicada'] === 1 ? ' — trilha publicada' : ''),
        'admin_modulo.php?id=' . (int) $m['id'],
    ];
}
foreach ($trilhas as $t) {
    if ((int) $t['publicada'] === 1 && (int) $t['atividades'] === 0) {
        $alertas[] = ['Trilha publicada sem nenhuma atividade: ' . $t['titulo'], 'admin_trilha.php?id=' . (int) $t['id']];
    }
}
?>

<?= abasAdmin('administracao.php', $suporteNaoLidas) ?>

<form method="get" action="admin_busca.php" class="busca-admin" role="search">
    <label for="q" class="sr-only">Buscar alunos, trilhas, módulos e atividades</label>
    <?= icone('busca', 18) ?>
    <input type="search" id="q" name="q" placeholder="Buscar alunos, trilhas, módulos, atividades…" maxlength="60" required>
    <button type="submit" class="botao botao-primario">Buscar</button>
</form>

<?php if ($flash): ?>
    <?= exibeMensagem($flash[0], $flash[1]) ?>
<?php endif; ?>

<section class="boas-vindas">
    <img src="<?= asset('imagens/mascote.jpg') ?>" alt="" class="boas-vindas-mascote">
    <div>
        <h2>Bem-vindo de volta, <?= e(strtok(trim($_SESSION['usuario_nome'] ?? 'Admin'), ' ')) ?>!</h2>
        <p>Aqui você administra a plataforma inteira. Abaixo, o resumo do que está acontecendo.</p>
    </div>
    <a href="inicio.php?aluno=1" class="botao botao-secundario botao-pequeno boas-vindas-link">
        <?= icone('olho', 14) ?> Ver o Início do aluno</a>
    <div class="boas-vindas-data">
        <?= icone('calendario', 18) ?>
        <div><strong><?= e(dataPorExtenso()) ?></strong><small><?= e(diaDaSemana()) ?></small></div>
    </div>
</section>

<div class="grade-kpi">
    <?php foreach ($cartoes as [$ico, $rotulo, $valor, $var, $link, $tom]): ?>
        <a class="cartao-kpi" href="<?= e($link) ?>">
            <span class="cartao-kpi-icone tom-<?= e($tom) ?>"><?= icone($ico, 22) ?></span>
            <div class="cartao-kpi-corpo">
                <small><?= e($rotulo) ?></small>
                <div class="cartao-kpi-linha">
                    <strong><?= number_format((int) $valor, 0, ',', '.') ?></strong>
                    <?php if ($var !== null): ?>
                        <span class="variacao <?= $var >= 0 ? 'variacao-alta' : 'variacao-baixa' ?>">
                            <?= $var >= 0 ? '&uarr;' : '&darr;' ?><?= abs($var) ?>%
                        </span>
                    <?php endif; ?>
                </div>
                <small class="cartao-kpi-nota">
                    <?= $var === null ? 'sem base de comparação ainda' : 'em relação aos 30 dias anteriores' ?>
                </small>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<?php /* Estrutura do wireframe: à esquerda o conteúdo largo (gráficos em cima,
         destaques e ranking embaixo); à direita uma coluna fixa que acompanha
         a página inteira (resumo, ações rápidas e últimas atividades). */ ?>
<div class="painel-grade">
  <div class="painel-esquerda">
    <div class="linha-graficos">
    <section class="painel">
        <h3 class="painel-titulo"><?= icone('perfil') ?> Evolução dos alunos
            <small class="painel-legenda">Cadastros e alunos ativos nos últimos 7 dias.</small></h3>

        <div class="legenda-grafico">
            <span><i style="background: <?= COR_CADASTROS ?>"></i> Cadastros</span>
            <span><i style="background: <?= COR_ATIVOS ?>"></i> Ativos</span>
        </div>

        <?= graficoDeLinhas($serie) ?>

        <?php // Mesmo conteúdo em texto: para leitor de tela e para quem não distingue as cores. ?>
        <details class="tabela-do-grafico">
            <summary>Ver os números em tabela</summary>
            <table class="tabela-admin">
                <thead><tr><th>Dia</th><th class="num">Cadastros</th><th class="num">Ativos</th></tr></thead>
                <tbody>
                <?php foreach ($serie as $d): ?>
                    <tr><td><?= e($d['rotulo']) ?></td><td class="num"><?= (int) $d['cadastros'] ?></td><td class="num"><?= (int) $d['ativos'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </details>
    </section>

    <section class="painel">
        <h3 class="painel-titulo"><?= icone('escudo') ?> Distribuição por nível
            <small class="painel-legenda">O nível vem do XP acumulado.</small></h3>
        <div class="bloco-rosca">
            <?= graficoDeRosca($distribuicao) ?>
            <ul class="legenda-rosca">
                <?php $totalAlunos = array_sum(array_column($distribuicao, 'total')); ?>
                <?php foreach ($distribuicao as $faixa): ?>
                    <li>
                        <i style="background: <?= e($faixa['cor']) ?>"></i>
                        <span><?= e($faixa['rotulo']) ?></span>
                        <strong><?= $totalAlunos ? round($faixa['total'] / $totalAlunos * 100) : 0 ?>%</strong>
                        <small><?= (int) $faixa['total'] ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    </div>

    <div class="linha-baixo">
      <section class="painel">
        <h3 class="painel-titulo"><?= icone('alvo') ?> Desafios em destaque
            <small class="painel-legenda">Os mais concluídos pelos alunos.</small></h3>
        <?php if (empty($destaques)): ?>
            <p class="aviso-suave">Nenhum desafio cadastrado ainda.</p>
        <?php else: ?>
            <div class="tabela-admin-envolve">
                <table class="tabela-admin" id="tabelaDestaques">
                    <thead><tr><th>Desafio</th><th>Dificuldade</th><th class="num">Concluíram</th><th><span class="sr-only">Editar</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($destaques as $d): ?>
                        <tr>
                            <td><strong><?= e($d['titulo']) ?></strong>
                                <small class="ajuda-campo" style="display:block"><?= e($d['trilha']) ?> · <?= e(rotuloTipoAtividade($d['tipo'])) ?></small></td>
                            <td><span class="selo selo-<?= e($d['dificuldade']) ?>"><?= e(dificuldades()[$d['dificuldade']] ?? $d['dificuldade']) ?></span></td>
                            <td class="num"><?= (int) $d['concluiram'] ?></td>
                            <td class="num"><a href="admin_atividade.php?id=<?= (int) $d['id'] ?>" class="botao botao-secundario botao-icone"
                                   title="Editar" aria-label="Editar <?= e($d['titulo']) ?>"><?= icone('lapis', 14) ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

      <section class="painel">
        <h3 class="painel-titulo"><?= icone('ranking') ?> Ranking geral
            <a href="ranking.php" class="link-laranja painel-titulo-link">Ver completo</a></h3>
        <?php if (empty($ranking)): ?>
            <p class="aviso-suave">Ninguém pontuou ainda.</p>
        <?php else: ?>
            <ol class="lista-ranking-admin">
                <?php foreach ($ranking as $linha): ?>
                    <li>
                        <span class="posicao-ranking posicao-<?= (int) $linha['posicao'] ?>"><?= (int) $linha['posicao'] ?></span>
                        <?= imgAvatar($linha['foto_avatar'], 'avatar-pequeno', classeMoldura($linha['moldura_slug'] ?? null)) ?>
                        <a href="admin_aluno.php?id=<?= (int) $linha['id'] ?>"><?= e($linha['nome']) ?></a>
                        <strong><?= number_format((int) $linha['xp'], 0, ',', '.') ?> XP</strong>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
    </div>
  </div>

  <aside class="coluna-direita">
    <section class="painel">
            <h3 class="painel-titulo"><?= icone('alvo') ?> Resumo</h3>
            <ul class="resumo-lado">
                <li>
                    <span class="cartao-kpi-icone tom-azul"><?= icone('alvo', 18) ?></span>
                    <div><strong>Taxa de acerto</strong>
                        <small><?= plural((int) $n['envios30'], 'resposta', 'respostas') ?> em 30 dias</small></div>
                    <b><?= $taxaAcerto === null ? '—' : $taxaAcerto . '%' ?></b>
                </li>
                <li>
                    <span class="cartao-kpi-icone tom-laranja"><?= icone('email', 18) ?></span>
                    <div><strong>Suporte</strong>
                        <small><?= $suporteNaoLidas > 0 ? 'mensagens não lidas' : 'nada pendente' ?></small></div>
                    <b><a href="admin_suporte.php"><?= $suporteNaoLidas ?></a></b>
                </li>
            </ul>
        </section>

    <section class="painel">
        <h3 class="painel-titulo"><?= icone('xp') ?> Ações rápidas</h3>
        <div class="acoes-rapidas">
            <a href="#novaTrilha" class="acao-rapida"><?= icone('trilhas', 22) ?><span>Criar trilha</span></a>
            <a href="admin_conquistas.php" class="acao-rapida"><?= icone('estrela', 22) ?><span>Criar conquista</span></a>
            <a href="admin_loja.php" class="acao-rapida"><?= icone('loja', 22) ?><span>Gerenciar Loja</span></a>
            <a href="admin_relatorios.php" class="acao-rapida"><?= icone('relatorio', 22) ?><span>Ver relatórios</span></a>
        </div>
        <p class="rodape-painel">Desafio novo nasce dentro de um módulo: abra a trilha, escolha o módulo e use "Nova atividade".</p>
    </section>

    <section class="painel">
        <h3 class="painel-titulo"><?= icone('sino') ?> Últimas atividades
            <a href="admin_registro.php" class="link-laranja painel-titulo-link">Registro do admin</a></h3>
        <?php if (empty($atividades)): ?>
            <p class="aviso-suave">Nada aconteceu ainda. Assim que os alunos usarem a plataforma, aparece aqui.</p>
        <?php else: ?>
            <ul class="feed-admin">
                <?php
                    $iconesFeed = ['cadastro' => 'perfil', 'atividade' => 'check', 'loja' => 'loja',
                                   'conquista' => 'estrela', 'suporte' => 'email'];
                ?>
                <?php foreach ($atividades as $ev): ?>
                    <li>
                        <span class="feed-icone feed-<?= e($ev['tipo']) ?>"><?= icone($iconesFeed[$ev['tipo']] ?? 'check', 16) ?></span>
                        <div><strong><?= e($ev['texto']) ?></strong><small><?= e($ev['quem']) ?></small></div>
                        <time datetime="<?= e($ev['quando']) ?>"><?= e(tempoRelativo($ev['quando'])) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
  </aside>
</div>

<?php if ($alertas): ?>
    <section class="painel">
        <h3 class="painel-titulo"><?= icone('bug') ?> Conteúdo para revisar <small class="contador"><?= count($alertas) ?></small></h3>
        <ul class="lista-alertas">
            <?php foreach ($alertas as [$texto, $link]): ?>
                <li><?= icone('bug', 16) ?><span><?= e($texto) ?></span><a href="<?= e($link) ?>">Resolver</a></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<div class="colunas">

    <section class="painel coluna-principal">
        <h3 class="painel-titulo">
            <?= icone('trilhas') ?> Trilhas
            <small class="contador"><?= count($trilhas) ?></small>
        </h3>

        <?php if (empty($trilhas)): ?>
            <p class="aviso-suave">Nenhuma trilha cadastrada ainda. Crie a primeira ao lado.</p>
        <?php else: ?>
            <div class="lista-admin">
                <?php $ultimaPos = count($trilhas) - 1; ?>
                <?php foreach ($trilhas as $pos => $trilha): ?>
                    <?php $aparencia = aparenciaDaTrilha($trilha['icone']); ?>
                    <article class="linha-admin">
                        <span class="bloco-icone bloco-icone-pequeno" style="--cor-trilha: <?= e($aparencia['cor']) ?>">
                            <?= e($aparencia['rotulo']) ?>
                        </span>

                        <div class="linha-admin-info">
                            <strong><?= e($trilha['titulo']) ?></strong>
                            <small>
                                <?= e(rotuloDificuldade($trilha['nivel_dificuldade'])) ?>
                                &middot; <?= plural((int) $trilha['modulos'], 'módulo', 'módulos') ?>
                                &middot; <?= plural((int) $trilha['atividades'], 'atividade', 'atividades') ?>
                            </small>
                        </div>

                        <?php if ((int) $trilha['publicada'] === 1): ?>
                            <span class="selo selo-concluida"><?= icone('check', 14) ?> publicada</span>
                        <?php else: ?>
                            <span class="selo">rascunho</span>
                        <?php endif; ?>

                        <div class="linha-admin-acoes">
                            <?php // Ordem em que as trilhas aparecem para o aluno (ADM-25). ?>
                            <form method="POST" action="administracao.php" class="formulario-embutido">
                                <?= campoCsrf() ?>
                                <input type="hidden" name="acao" value="mover">
                                <input type="hidden" name="trilha_id" value="<?= (int) $trilha['id'] ?>">
                                <button type="submit" name="direcao" value="cima" class="botao botao-secundario botao-icone"
                                        title="Subir" aria-label="Subir <?= e($trilha['titulo']) ?>" <?= $pos === 0 ? 'disabled' : '' ?>>&uarr;</button>
                                <button type="submit" name="direcao" value="baixo" class="botao botao-secundario botao-icone"
                                        title="Descer" aria-label="Descer <?= e($trilha['titulo']) ?>" <?= $pos === $ultimaPos ? 'disabled' : '' ?>>&darr;</button>
                            </form>

                            <form method="POST" action="administracao.php" class="formulario-embutido">
                                <?= campoCsrf() ?>
                                <input type="hidden" name="acao" value="publicar">
                                <input type="hidden" name="trilha_id" value="<?= (int) $trilha['id'] ?>">
                                <button type="submit" class="botao botao-secundario botao-pequeno">
                                    <?= (int) $trilha['publicada'] === 1 ? 'Despublicar' : 'Publicar' ?>
                                </button>
                            </form>

                            <form method="POST" action="administracao.php" class="formulario-embutido"
                                  onsubmit="return confirm('Arquivar esta trilha? Ela sai da lista dos alunos, mas o progresso fica guardado.');">
                                <?= campoCsrf() ?>
                                <input type="hidden" name="acao" value="arquivar">
                                <input type="hidden" name="trilha_id" value="<?= (int) $trilha['id'] ?>">
                                <button type="submit" class="botao botao-secundario botao-icone" title="Arquivar"
                                        aria-label="Arquivar <?= e($trilha['titulo']) ?>"><?= icone('arquivo', 16) ?></button>
                            </form>

                            <a href="admin_trilha.php?id=<?= (int) $trilha['id'] ?>"
                               class="botao botao-primario botao-pequeno">Gerenciar</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($arquivadas): ?>
            <details class="caminho arquivadas-admin">
                <summary><?= icone('arquivo', 14) ?> Trilhas arquivadas (<?= count($arquivadas) ?>)</summary>
                <div class="lista-admin">
                    <?php foreach ($arquivadas as $trilha): ?>
                        <article class="linha-admin linha-inativa">
                            <div class="linha-admin-info">
                                <strong><?= e($trilha['titulo']) ?></strong>
                                <small><?= plural((int) $trilha['atividades'], 'atividade', 'atividades') ?></small>
                            </div>
                            <div class="linha-admin-acoes">
                                <form method="POST" action="administracao.php" class="formulario-embutido">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="acao" value="desarquivar">
                                    <input type="hidden" name="trilha_id" value="<?= (int) $trilha['id'] ?>">
                                    <button type="submit" class="botao botao-secundario botao-pequeno">Desarquivar</button>
                                </form>
                                <?php if (!trilhaTemProgresso($pdo, (int) $trilha['id'])): ?>
                                    <form method="POST" action="administracao.php" class="formulario-embutido"
                                          onsubmit="return confirm('Apagar de vez? Isto não pode ser desfeito.');">
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="acao" value="apagar">
                                        <input type="hidden" name="trilha_id" value="<?= (int) $trilha['id'] ?>">
                                        <button type="submit" class="botao botao-perigo botao-pequeno"><?= icone('lixeira', 14) ?> Apagar</button>
                                    </form>
                                <?php else: ?>
                                    <small class="ajuda-campo" title="Algum aluno tem progresso nela">não pode ser apagada</small>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>

        <p class="rodape-painel">
            Uma trilha nova nasce como rascunho e só aparece para os alunos depois de publicada.
            As setas definem a ordem na tela Trilhas do aluno. Arquivar tira a trilha de circulação sem apagar o progresso de ninguém.
        </p>
    </section>

    <aside class="coluna-lateral">
        <section class="painel" id="novaTrilha">
            <h3 class="painel-titulo"><?= icone('livro') ?> Nova trilha</h3>

            <form method="POST" action="administracao.php">
                <?= campoCsrf() ?>
                <input type="hidden" name="acao" value="criar">

                <div class="campo">
                    <label for="titulo">Título</label>
                    <input type="text" id="titulo" name="titulo" required maxlength="100"
                           placeholder="Ex: Programação Orientada a Objetos">
                </div>

                <div class="campo">
                    <label for="descricao">Descrição</label>
                    <textarea id="descricao" name="descricao" rows="3"
                              placeholder="Resumo do que o aluno vai aprender"></textarea>
                </div>

                <div class="campo">
                    <label for="nivel_dificuldade">Dificuldade</label>
                    <select id="nivel_dificuldade" name="nivel_dificuldade">
                        <?php foreach ($dificuldadesValidas as $codigo): ?>
                            <option value="<?= e($codigo) ?>"><?= e(rotuloDificuldade($codigo)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="icone">Ícone</label>
                    <select id="icone" name="icone">
                        <?php foreach (iconesDeTrilha() as $valor => $rotulo): ?>
                            <option value="<?= e($valor) ?>"><?= e($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="linguagem">Linguagem dos exercícios</label>
                    <select id="linguagem" name="linguagem">
                        <?php foreach (linguagensDeTrilha() as $valor => $rotulo): ?>
                            <option value="<?= e($valor) ?>"><?= e($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="ajuda-campo">Define como os exercícios são corrigidos. Não pode mudar depois que a trilha tiver atividades.</small>
                </div>

                <button type="submit" class="botao botao-primario botao-bloco">Criar trilha</button>
            </form>
        </section>
    </aside>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
