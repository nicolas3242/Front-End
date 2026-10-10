<?php
/**
 * includes/loja.php — moedas, compra e itens equipados.
 * -----------------------------------------------------------------------------
 * Três regras sustentam a Loja. Se alguma cair, a Loja vira um furo:
 *
 * 1. O SALDO É DERIVADO, NÃO GUARDADO.
 *        saldo = xp DIV XP_POR_MOEDA - soma do que já foi gasto
 *    Não existe coluna `moedas`. A única forma de ganhar moeda é ganhar XP, e
 *    o XP já é protegido contra farm em concluirAtividade(). Uma coluna própria
 *    seria mais um lugar para um UPDATE esquecido deixar os números em
 *    desacordo — o mesmo motivo pelo qual o nível também não é guardado.
 *
 * 2. A COMPRA É ATÔMICA.
 *    Conferir o saldo e registrar a compra em dois passos soltos deixa uma
 *    janela: duas requisições simultâneas (dois cliques rápidos, duas abas)
 *    leriam o mesmo saldo e as duas passariam. Por isso a compra roda numa
 *    transação que trava a linha do usuário (SELECT ... FOR UPDATE): a segunda
 *    requisição espera a primeira terminar e já enxerga o saldo novo.
 *
 * 3. O SERVIDOR DECIDE TUDO.
 *    Preço, saldo e posse vêm do banco. Do navegador chega só o id do item; um
 *    preço enviado pelo formulário seria editável no DevTools.
 */

require_once __DIR__ . '/auth.php';

/** Quanto XP vale uma moeda. 7.200 XP nas trilhas = 720 moedas no total. */
const XP_POR_MOEDA = 10;

/** Categorias de item e a coluna de `usuarios` que guarda o equipado. */
const CATEGORIAS_LOJA = [
    'moldura' => 'moldura_id',
    'titulo'  => 'titulo_id',
];

/** Moedas disponíveis agora. */
function saldoDeMoedas(PDO $pdo, int $usuarioId): int
{
    $stmt = $pdo->prepare(
        "SELECT u.xp,
                COALESCE((SELECT SUM(c.preco_pago) FROM compras c WHERE c.usuario_id = u.id), 0) AS gasto
         FROM usuarios u
         WHERE u.id = ?"
    );
    $stmt->execute([$usuarioId]);
    $r = $stmt->fetch();

    if (!$r) {
        return 0;
    }

    return max(0, intdiv((int) $r['xp'], XP_POR_MOEDA) - (int) $r['gasto']);
}

/**
 * Catálogo à venda, marcando o que o usuário já tem e o que está equipado.
 *
 * Itens desativados não aparecem para quem não comprou, mas continuam
 * aparecendo para quem comprou — o aluno não perde o que pagou.
 */
function catalogoDaLoja(PDO $pdo, int $usuarioId): array
{
    $stmt = $pdo->prepare(
        "SELECT i.id, i.slug, i.nome, i.descricao, i.categoria, i.preco,
                (c.id IS NOT NULL) AS comprado,
                (i.id = u.moldura_id OR i.id = u.titulo_id) AS equipado
         FROM itens_loja i
         JOIN usuarios u       ON u.id = ?
         LEFT JOIN compras c   ON c.item_id = i.id AND c.usuario_id = u.id
         WHERE i.ativo = 1 OR c.id IS NOT NULL
         ORDER BY i.categoria, i.ordem, i.id"
    );
    $stmt->execute([$usuarioId]);

    return $stmt->fetchAll();
}

/**
 * Compra um item.
 *
 * @return string 'comprado' | 'ja_tem' | 'sem_saldo' | 'inexistente'
 */
function comprarItem(PDO $pdo, int $usuarioId, int $itemId): string
{
    $pdo->beginTransaction();

    try {
        // Trava a linha do usuário até o COMMIT (regra 2 do cabeçalho).
        $stmt = $pdo->prepare("SELECT xp FROM usuarios WHERE id = ? FOR UPDATE");
        $stmt->execute([$usuarioId]);
        if ($stmt->fetchColumn() === false) {
            $pdo->rollBack();
            return 'inexistente';
        }

        $stmt = $pdo->prepare("SELECT preco FROM itens_loja WHERE id = ? AND ativo = 1");
        $stmt->execute([$itemId]);
        $preco = $stmt->fetchColumn();
        if ($preco === false) {
            $pdo->rollBack();
            return 'inexistente';
        }
        $preco = (int) $preco;

        // Posse antes de saldo: quem já tem o item e está sem moeda deve ouvir
        // "você já tem", não "moedas insuficientes".
        $stmt = $pdo->prepare("SELECT 1 FROM compras WHERE usuario_id = ? AND item_id = ?");
        $stmt->execute([$usuarioId, $itemId]);
        if ($stmt->fetchColumn() !== false) {
            $pdo->rollBack();
            return 'ja_tem';
        }

        if (saldoDeMoedas($pdo, $usuarioId) < $preco) {
            $pdo->rollBack();
            return 'sem_saldo';
        }

        // A chave única é a última barreira: mesmo que algo acima falhe, o
        // banco não aceita a mesma compra duas vezes.
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO compras (usuario_id, item_id, preco_pago) VALUES (?, ?, ?)"
        );
        $stmt->execute([$usuarioId, $itemId, $preco]);

        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            return 'ja_tem';
        }

        $pdo->commit();
        return 'comprado';

    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

/**
 * Equipa um item que o usuário POSSUI.
 *
 * @return bool false se o item não existe ou não foi comprado por ele.
 */
function equiparItem(PDO $pdo, int $usuarioId, int $itemId): bool
{
    $stmt = $pdo->prepare(
        "SELECT i.categoria
         FROM compras c
         JOIN itens_loja i ON i.id = c.item_id
         WHERE c.usuario_id = ? AND c.item_id = ?"
    );
    $stmt->execute([$usuarioId, $itemId]);
    $categoria = $stmt->fetchColumn();

    if ($categoria === false || !isset(CATEGORIAS_LOJA[$categoria])) {
        return false;
    }

    // O nome da coluna vem da lista fixa acima, nunca do usuário.
    $coluna = CATEGORIAS_LOJA[$categoria];
    $pdo->prepare("UPDATE usuarios SET {$coluna} = ? WHERE id = ?")
        ->execute([$itemId, $usuarioId]);

    return true;
}

/** Tira o item equipado de uma categoria. */
function desequiparCategoria(PDO $pdo, int $usuarioId, string $categoria): bool
{
    if (!isset(CATEGORIAS_LOJA[$categoria])) {
        return false;
    }

    $coluna = CATEGORIAS_LOJA[$categoria];
    $pdo->prepare("UPDATE usuarios SET {$coluna} = NULL WHERE id = ?")
        ->execute([$usuarioId]);

    return true;
}

/**
 * Classes CSS do avatar conforme a moldura equipada ('' se nenhuma).
 *
 * O slug é filtrado de novo aqui, mesmo já sendo validado no gerador do
 * banco: ele vai parar dentro de um atributo HTML, e um admin pode ter criado
 * o item direto no banco.
 */
function classeMoldura(?string $slug): string
{
    $slug = preg_replace('/[^a-z0-9-]/', '', (string) $slug);

    return $slug === '' ? '' : ' moldura moldura-' . $slug;
}

/**
 * Aparências de moldura disponíveis, para o admin criar itens pela tela.
 *
 * Cada moldura é um anel desenhado em CSS (`.moldura-<slug>` em styles.css).
 * Como o admin não escreve CSS — e não DEVE escrever, senão a tela vira um
 * editor de folha de estilo com tudo que isso tem de risco —, as aparências
 * são um catálogo fechado: ele escolhe uma da lista e dá nome, descrição e
 * preço. O slug do item passa a ser o nome do estilo, que é o que liga o item
 * à classe do CSS.
 *
 * Para acrescentar uma aparência nova: escreva a regra `.moldura-<slug>` no
 * styles.css e acrescente a linha aqui. São os dois lugares, e só eles.
 *
 * @return array<string,string> slug => nome legível
 */
function estilosDeMoldura(): array
{
    return [
        'brasa'     => 'Brasa (laranja)',
        'oceano'    => 'Oceano (azul)',
        'floresta'  => 'Floresta (verde)',
        'neon'      => 'Neon (roxo com brilho)',
        'ouro'      => 'Ouro',
        'rubi'      => 'Rubi (vermelho)',
        'gelo'      => 'Gelo (azul claro)',
        'limao'     => 'Limão (verde e amarelo)',
        'ametista'  => 'Ametista (roxo claro)',
        'cobre'     => 'Cobre',
        'abissal'   => 'Abissal (cinza escuro)',
        'aurora'    => 'Aurora (ciano, roxo e rosa)',
        'fantasma'  => 'Fantasma (prata com brilho)',
    ];
}

/**
 * Aparências que ainda não têm item na Loja.
 *
 * Duas molduras com o mesmo slug seriam o mesmo item repetido, então o que já
 * foi usado sai da lista.
 *
 * @return array<string,string> slug => nome legível
 */
function estilosDeMolduraLivres(PDO $pdo): array
{
    $usados = $pdo->query("SELECT slug FROM itens_loja WHERE categoria = 'moldura'")
                  ->fetchAll(PDO::FETCH_COLUMN);

    return array_diff_key(estilosDeMoldura(), array_flip($usados));
}
