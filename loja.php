<?php
/**
 * loja.php — troca de moedas por itens cosméticos.
 *
 * As regras (saldo derivado, compra atômica, servidor decide) estão em
 * includes/loja.php. Esta tela só recebe o pedido, chama as funções e mostra
 * o resultado, no padrão Post/Redirect/Get das telas de Administração: depois
 * de um POST sempre há redirecionamento, então apertar F5 não compra de novo.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';     // redirecionarCom(), consumirFlash(), CSRF
require_once __DIR__ . '/includes/loja.php';

exigirLogin();
$usuarioId = (int) usuarioAtualId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();

    $acao   = $_POST['acao'] ?? '';
    $itemId = (int) ($_POST['item_id'] ?? 0);

    if ($acao === 'comprar') {
        $stmt = $pdo->prepare("SELECT nome FROM itens_loja WHERE id = ?");
        $stmt->execute([$itemId]);
        $nome = (string) $stmt->fetchColumn();

        $resultado = comprarItem($pdo, $usuarioId, $itemId);

        if ($resultado === 'comprado') {
            // Quem compra quer usar: já sai equipado.
            equiparItem($pdo, $usuarioId, $itemId);
            redirecionarCom('loja.php', '"' . $nome . '" comprado e equipado. Saldo atual: '
                . plural(saldoDeMoedas($pdo, $usuarioId), 'moeda', 'moedas') . '.');
        }

        $mensagens = [
            'ja_tem'      => 'Você já tem "' . $nome . '".',
            'sem_saldo'   => 'Moedas insuficientes para "' . $nome . '". Conclua mais atividades para ganhar XP.',
            'inexistente' => 'Este item não está à venda.',
        ];
        redirecionarCom('loja.php', $mensagens[$resultado] ?? 'Não foi possível comprar.', 'erro');
    }

    if ($acao === 'equipar') {
        equiparItem($pdo, $usuarioId, $itemId)
            ? redirecionarCom('loja.php', 'Item equipado.')
            : redirecionarCom('loja.php', 'Você só pode equipar itens que comprou.', 'erro');
    }

    if ($acao === 'desequipar') {
        desequiparCategoria($pdo, $usuarioId, (string) ($_POST['categoria'] ?? ''))
            ? redirecionarCom('loja.php', 'Item retirado.')
            : redirecionarCom('loja.php', 'Categoria inválida.', 'erro');
    }

    redirecionarCom('loja.php', 'Ação inválida.', 'erro');
}

$flash = consumirFlash();

$tituloPagina    = 'Loja';
$subtituloPagina = 'Troque suas moedas por itens para personalizar seu perfil.';
require_once __DIR__ . '/includes/header.php';

$itens = catalogoDaLoja($pdo, $usuarioId);

$porCategoria = ['moldura' => [], 'titulo' => []];
foreach ($itens as $item) {
    $porCategoria[$item['categoria']][] = $item;
}

$secoes = [
    'moldura' => ['Molduras', 'Um anel colorido em volta do seu avatar, na barra lateral, no perfil e no ranking.'],
    'titulo'  => ['Títulos',  'Aparece sob o seu nome no lugar do título do nível.'],
];
?>

<?php if ($flash): ?>
    <?= exibeMensagem($flash[0], $flash[1]) ?>
<?php endif; ?>

<div class="painel loja-resumo">
    <div class="loja-saldo">
        <?= icone('moeda', 28) ?>
        <div>
            <small>Seu saldo</small>
            <strong><?= number_format($moedasAtuais, 0, ',', '.') ?> <?= $moedasAtuais === 1 ? 'moeda' : 'moedas' ?></strong>
        </div>
    </div>
    <p class="aviso-suave">
        Você ganha 1 moeda a cada <?= XP_POR_MOEDA ?> XP. Os itens são apenas visuais:
        nada na Loja dá vantagem nas atividades ou no ranking.
    </p>
    <div class="loja-visual">
        <?= imgAvatar($usuarioLogadoDados['foto_avatar'], '', $classeAvatar) ?>
        <div class="cartao-usuario-nome">
            <strong><?= e($usuarioLogadoDados['nome']) ?></strong>
            <small><?= e($tituloExibido) ?></small>
        </div>
    </div>
</div>

<?php foreach ($secoes as $categoria => [$tituloSecao, $explicacao]): ?>
    <?php if (empty($porCategoria[$categoria])) { continue; } ?>

    <section class="loja-secao">
        <div class="loja-secao-topo">
            <div>
                <h2 class="secao-titulo"><?= e($tituloSecao) ?></h2>
                <p class="aviso-suave"><?= e($explicacao) ?></p>
            </div>
        </div>

        <div class="grade-loja">
            <?php foreach ($porCategoria[$categoria] as $item): ?>
                <?php
                $comprado = (bool) $item['comprado'];
                $equipado = (bool) $item['equipado'];
                $falta    = (int) $item['preco'] - $moedasAtuais;
                ?>
                <article class="item-loja <?= $equipado ? 'item-loja-equipado' : '' ?>"
                         data-item="<?= e($item['slug']) ?>">

                    <div class="item-loja-previa">
                        <?php if ($categoria === 'moldura'): ?>
                            <img src="assets/imagens/logo-marca.png" alt=""
                                 class="avatar avatar-grande<?= classeMoldura($item['slug']) ?>">
                        <?php else: ?>
                            <?php /* Prévia de como fica: o seu nome com o título embaixo. */ ?>
                            <div class="cartao-usuario-nome item-loja-titulo">
                                <strong><?= e($usuarioLogadoDados['nome']) ?></strong>
                                <small><?= e($item['nome']) ?></small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <strong><?= e($item['nome']) ?></strong>
                    <small><?= e($item['descricao']) ?></small>

                    <?php if (!$comprado): ?>
                        <span class="item-loja-preco"><?= icone('moeda', 14) ?> <?= (int) $item['preco'] ?></span>

                        <form method="post" class="item-loja-acao">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="acao" value="comprar">
                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                            <?php if ($falta > 0): ?>
                                <button type="submit" class="botao botao-secundario botao-pequeno botao-bloco" disabled>
                                    <?= $falta === 1 ? 'Falta 1 moeda' : 'Faltam ' . $falta . ' moedas' ?>
                                </button>
                            <?php else: ?>
                                <button type="submit" class="botao botao-primario botao-pequeno botao-bloco">
                                    Comprar
                                </button>
                            <?php endif; ?>
                        </form>

                    <?php elseif ($equipado): ?>
                        <span class="selo selo-concluida"><?= icone('check', 12) ?> Equipado</span>

                        <form method="post" class="item-loja-acao">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="acao" value="desequipar">
                            <input type="hidden" name="categoria" value="<?= e($categoria) ?>">
                            <button type="submit" class="botao botao-secundario botao-pequeno botao-bloco">Tirar</button>
                        </form>

                    <?php else: ?>
                        <span class="selo">Comprado</span>

                        <form method="post" class="item-loja-acao">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="acao" value="equipar">
                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                            <button type="submit" class="botao botao-primario botao-pequeno botao-bloco">Equipar</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
