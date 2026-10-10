<?php
/**
 * admin_loja.php — itens da Loja pela Administração (ADM-70 a ADM-73).
 *
 *   - lista com quantas vezes cada item foi comprado;
 *   - editar nome, descrição e preço de qualquer item;
 *   - criar TÍTULO novo (é só texto). Moldura nova não se cria por aqui: cada
 *     moldura é uma classe CSS (.moldura-<slug>) e precisa de quem escreva o CSS;
 *   - tirar da vitrine / colocar à venda. Item nunca é apagado: quem comprou
 *     continua com ele equipado (includes/loja.php mostra item inativo a quem
 *     já tem).
 *
 * Mudar o preço vale para as próximas compras; quem já comprou pagou o preço
 * da época (compras.preco_pago), então o saldo de ninguém muda.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/loja.php';   // estilosDeMoldura(), classeMoldura()

exigirAdmin();

const PRECO_MAXIMO = 100000;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    $item = null;
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM itens_loja WHERE id = ?");
        $stmt->execute([$id]);
        if (!($item = $stmt->fetch())) {
            redirecionarCom('admin_loja.php', 'Item não encontrado.', 'erro');
        }
    }

    if ($acao === 'alternar' && $item) {
        $novo = (int) $item['ativo'] === 1 ? 0 : 1;
        $pdo->prepare("UPDATE itens_loja SET ativo = ? WHERE id = ?")->execute([$novo, $id]);
        registrarAcaoAdmin($pdo, $novo ? 'loja.ativar' : 'loja.desativar', $item['nome']);
        redirecionarCom('admin_loja.php', $novo ? 'Item de volta à venda.' : 'Item fora da vitrine. Quem comprou continua com ele.');
    }

    if ($acao === 'salvar') {
        $nome      = trim(textoPost('nome'));
        $descricao = trim(textoPost('descricao'));
        $preco     = (int) ($_POST['preco'] ?? 0);
        $volta     = 'admin_loja.php' . ($id ? '?editar=' . $id : '');

        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 60) {
            redirecionarCom($volta, 'O nome precisa ter entre 2 e 60 caracteres.', 'erro');
        }
        if (mb_strlen($descricao) > 160) {
            redirecionarCom($volta, 'A descrição pode ter no máximo 160 caracteres.', 'erro');
        }
        if ($preco < 1 || $preco > PRECO_MAXIMO) {
            redirecionarCom($volta, 'O preço precisa ser de 1 a ' . number_format(PRECO_MAXIMO, 0, ',', '.') . ' moedas.', 'erro');
        }

        if ($item) {
            $pdo->prepare("UPDATE itens_loja SET nome = ?, descricao = ?, preco = ? WHERE id = ?")
                ->execute([$nome, $descricao, $preco, $id]);   // descricao é NOT NULL no banco (vazia = '')
            registrarAcaoAdmin($pdo, 'loja.editar', $nome);
            redirecionarCom('admin_loja.php', 'Item atualizado.');
        }

        // Item novo: título ou moldura.
        $categoria = ($_POST['categoria'] ?? 'titulo') === 'moldura' ? 'moldura' : 'titulo';

        if ($categoria === 'moldura') {
            // A aparência vem do catálogo fechado de estilos (includes/loja.php):
            // o slug do item É o estilo, porque é ele que casa com a classe
            // `.moldura-<slug>` do CSS. Estilo já usado não entra de novo.
            $slug = (string) ($_POST['estilo'] ?? '');

            if (!array_key_exists($slug, estilosDeMolduraLivres($pdo))) {
                redirecionarCom($volta, 'Escolha uma aparência de moldura que ainda não esteja em uso.', 'erro');
            }
        } else {
            // Título: o slug sai do nome (sem acento, minúsculo) e ganha um
            // número se já existir.
            $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome) ?: 'titulo')), '-') ?: 'titulo';
            $base = substr($base, 0, 34);
            $slug = $base;
            $existe = $pdo->prepare("SELECT COUNT(*) FROM itens_loja WHERE slug = ?");
            for ($n = 2; $existe->execute([$slug]) && (int) $existe->fetchColumn() > 0; $n++) {
                $slug = $base . '-' . $n;
            }
        }

        $ordem = (int) $pdo->query("SELECT COALESCE(MAX(ordem), 0) + 1 FROM itens_loja")->fetchColumn();
        $pdo->prepare(
            "INSERT INTO itens_loja (slug, nome, descricao, categoria, preco, ordem, ativo) VALUES (?, ?, ?, ?, ?, ?, 1)"
        )->execute([$slug, $nome, $descricao, $categoria, $preco, $ordem]);
        registrarAcaoAdmin($pdo, 'loja.criar', $nome);
        redirecionarCom('admin_loja.php',
            ($categoria === 'moldura' ? 'Moldura criada' : 'Título criado') . ' e já à venda na Loja.');
    }

    redirecionarCom('admin_loja.php', 'Ação inválida.', 'erro');
}

$flash = consumirFlash();

$itens = $pdo->query(
    "SELECT i.*, COUNT(c.id) AS compras, COALESCE(SUM(c.preco_pago), 0) AS moedas
     FROM itens_loja i LEFT JOIN compras c ON c.item_id = i.id
     GROUP BY i.id ORDER BY i.categoria, i.ordem, i.id"
)->fetchAll();

$estilosLivres = estilosDeMolduraLivres($pdo);

$editando = null;
foreach ($itens as $i) {
    if ((int) ($_GET['editar'] ?? 0) === (int) $i['id']) {
        $editando = $i;
    }
}

$tituloPagina    = 'Loja';
$subtituloPagina = 'Itens à venda, preços e o que os alunos compram.';
require_once __DIR__ . '/includes/header.php';
?>

<?= abasAdmin('admin_loja.php', suporteNaoLidas($pdo)) ?>

<?php if ($flash): ?>
    <?= exibeMensagem($flash[0], $flash[1]) ?>
<?php endif; ?>

<div class="colunas">
    <section class="painel coluna-principal">
        <h3 class="painel-titulo"><?= icone('loja') ?> Itens</h3>
        <div class="tabela-admin-envolve">
            <table class="tabela-admin" id="tabelaLoja">
                <thead><tr><th>Item</th><th>Tipo</th><th class="num">Preço</th><th class="num">Compras</th><th><span class="sr-only">Ações</span></th></tr></thead>
                <tbody>
                <?php foreach ($itens as $i): ?>
                    <tr class="<?= (int) $i['ativo'] === 1 ? '' : 'linha-inativa' ?>">
                        <td><strong><?= e($i['nome']) ?></strong>
                            <?php if ((int) $i['ativo'] !== 1): ?><span class="selo selo-inativo">fora da vitrine</span><?php endif; ?>
                            <small class="ajuda-campo" style="display:block"><?= e((string) $i['descricao']) ?></small></td>
                        <td><?= $i['categoria'] === 'moldura' ? 'Moldura' : 'Título' ?></td>
                        <td class="num"><?= number_format((int) $i['preco'], 0, ',', '.') ?></td>
                        <td class="num"><?= (int) $i['compras'] ?></td>
                        <td>
                            <div class="acoes-linha">
                                <a href="admin_loja.php?editar=<?= (int) $i['id'] ?>" class="botao botao-secundario botao-icone"
                                   title="Editar" aria-label="Editar <?= e($i['nome']) ?>"><?= icone('lapis', 14) ?></a>
                                <form method="post">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="acao" value="alternar">
                                    <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                                    <button type="submit" class="botao botao-secundario botao-pequeno">
                                        <?= (int) $i['ativo'] === 1 ? 'Tirar da vitrine' : 'Colocar à venda' ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="rodape-painel">Item comprado nunca é apagado: fora da vitrine, ele some para quem não tem, e quem comprou continua usando.
            Mudar o preço vale só para as próximas compras.</p>
    </section>

    <aside class="coluna-lateral">
        <section class="painel" id="formItem">
            <h3 class="painel-titulo"><?= icone($editando ? 'lapis' : 'loja') ?>
                <?= $editando ? 'Editar ' . ($editando['categoria'] === 'moldura' ? 'moldura' : 'título') : 'Novo item' ?></h3>
            <form method="post">
                <?= campoCsrf() ?>
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">

                <?php if (!$editando): ?>
                    <div class="campo">
                        <label for="categoria">Tipo do item</label>
                        <select id="categoria" name="categoria">
                            <option value="titulo">Título (texto ao lado do nome)</option>
                            <option value="moldura" <?= empty($estilosLivres) ? 'disabled' : '' ?>>
                                Moldura (anel em volta do avatar)</option>
                        </select>
                    </div>

                    <div class="campo" id="campoEstilo" hidden>
                        <label for="estilo">Aparência da moldura</label>
                        <select id="estilo" name="estilo">
                            <?php foreach ($estilosLivres as $slug => $rotulo): ?>
                                <option value="<?= e($slug) ?>"><?= e($rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="ajuda-campo">
                            <?= empty($estilosLivres)
                                ? 'Todas as aparências prontas já têm item na Loja. Para ter mais, é preciso acrescentar uma regra no CSS.'
                                : 'As aparências são um catálogo fechado: o admin escolhe uma, não escreve CSS.' ?>
                        </small>
                        <div class="amostras-moldura" aria-hidden="true">
                            <?php foreach ($estilosLivres as $slug => $rotulo): ?>
                                <span class="avatar avatar-pequeno moldura moldura-<?= e($slug) ?>"
                                      data-estilo="<?= e($slug) ?>" title="<?= e($rotulo) ?>"></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="campo">
                    <label for="nome">Nome</label>
                    <input type="text" id="nome" name="nome" required maxlength="60" value="<?= e($editando['nome'] ?? '') ?>" placeholder="Ex.: Guardião do Git">
                </div>
                <div class="campo">
                    <label for="descricao">Descrição</label>
                    <input type="text" id="descricao" name="descricao" maxlength="160" value="<?= e((string) ($editando['descricao'] ?? '')) ?>">
                </div>
                <div class="campo">
                    <label for="preco">Preço (moedas)</label>
                    <input type="number" id="preco" name="preco" min="1" max="<?= PRECO_MAXIMO ?>" required value="<?= (int) ($editando['preco'] ?? 100) ?>">
                    <small class="ajuda-campo">1 moeda a cada <?= XP_POR_MOEDA ?> XP.</small>
                </div>
                <button type="submit" class="botao botao-primario botao-bloco"><?= $editando ? 'Salvar alterações' : 'Criar item' ?></button>
                <?php if ($editando): ?>
                    <a href="admin_loja.php" class="botao botao-secundario botao-bloco" style="margin-top: 10px;">Cancelar</a>
                <?php endif; ?>
            </form>

            <?php if (!$editando): ?>
                <?php // Sem JavaScript o campo da aparência fica visível e o servidor
                      // valida do mesmo jeito: a tela continua utilizável. ?>
                <script>
                    (function () {
                        var tipo   = document.getElementById('categoria');
                        var estilo = document.getElementById('campoEstilo');
                        function alternar() { estilo.hidden = tipo.value !== 'moldura'; }
                        tipo.addEventListener('change', alternar);
                        alternar();
                    })();
                </script>
            <?php endif; ?>
        </section>
    </aside>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
