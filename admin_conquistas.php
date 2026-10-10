<?php
/**
 * admin_conquistas.php — conquistas pela Administração (ADM-60 a ADM-63).
 *
 *   - lista com quantos alunos desbloquearam cada uma;
 *   - editar título, descrição, ícone e raridade de QUALQUER conquista;
 *   - ativar/desativar: desativada não é mais concedida, e quem já ganhou
 *     continua com ela (nada é apagado — conquista não tem "excluir");
 *   - criar conquista nova escolhendo um TIPO DE REGRA do catálogo fechado
 *     (includes/conquistas.php → tiposDeRegra()) e um valor. As 6 originais
 *     têm a regra escrita no código ('fixa') e ela não muda pela tela.
 *
 * Quem já cumpre a regra de uma conquista nova ganha na próxima vez que o
 * sistema conferir (ao entrar, ao responder uma atividade ou ao fazer prova).
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/conquistas.php';

exigirAdmin();

/** Ícones que o selo aceita (o mesmo conjunto usado nas conquistas originais, e alguns a mais). */
const ICONES_CONQUISTA = ['estrela' => 'Estrela', 'trofeu' => 'Troféu', 'medalha' => 'Medalha', 'fogo' => 'Fogo',
                          'mapa' => 'Mapa', 'lampada' => 'Lâmpada', 'codigo' => 'Código', 'alvo' => 'Alvo',
                          'escudo' => 'Escudo', 'livro' => 'Livro', 'xp' => 'Raio (XP)', 'check' => 'Certo'];
const RARIDADES = ['bronze' => 'Bronze', 'prata' => 'Prata', 'ouro' => 'Ouro', 'especial' => 'Especial'];

$trilhas = $pdo->query("SELECT id, titulo FROM trilhas WHERE arquivada = 0 ORDER BY ordem, id")->fetchAll(PDO::FETCH_KEY_PAIR);
$provas  = $pdo->query(
    "SELECT m.id, CONCAT(m.titulo, ' (', t.titulo, ')') FROM modulos m JOIN trilhas t ON t.id = m.trilha_id
      WHERE m.tipo = 'prova' AND t.arquivada = 0 ORDER BY t.ordem, m.ordem"
)->fetchAll(PDO::FETCH_KEY_PAIR);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    $atual = null;
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM conquistas WHERE id = ?");
        $stmt->execute([$id]);
        $atual = $stmt->fetch() ?: null;
        if (!$atual) {
            redirecionarCom('admin_conquistas.php', 'Conquista não encontrada.', 'erro');
        }
    }

    if ($acao === 'alternar' && $atual) {
        $nova = (int) $atual['ativa'] === 1 ? 0 : 1;
        $pdo->prepare("UPDATE conquistas SET ativa = ? WHERE id = ?")->execute([$nova, $id]);
        registrarAcaoAdmin($pdo, $nova ? 'conquista.ativar' : 'conquista.desativar', 'Conquista: ' . $atual['titulo']);
        redirecionarCom('admin_conquistas.php', $nova
            ? 'Conquista ativada: volta a ser concedida.'
            : 'Conquista desativada: ninguém mais ganha, e quem já tinha continua com ela.');
    }

    if ($acao === 'salvar') {
        $titulo    = trim(textoPost('titulo'));
        $descricao = trim(textoPost('descricao'));
        $icone     = array_key_exists(textoPost('icone'), ICONES_CONQUISTA) ? textoPost('icone') : 'estrela';
        $raridade  = array_key_exists(textoPost('raridade'), RARIDADES) ? textoPost('raridade') : 'bronze';
        $volta     = 'admin_conquistas.php' . ($id ? '?editar=' . $id : '');

        if (mb_strlen($titulo) < 2 || mb_strlen($titulo) > 100) {
            redirecionarCom($volta, 'O título precisa ter entre 2 e 100 caracteres.', 'erro');
        }
        if (mb_strlen($descricao) > 255) {
            redirecionarCom($volta, 'A descrição pode ter no máximo 255 caracteres.', 'erro');
        }

        // Regra: só nas criadas pelo admin. A das originais ('fixa') é código e não muda por aqui.
        $tipo = $atual['regra_tipo'] ?? textoPost('regra_tipo');
        $valor = null;
        $alvo  = null;
        if ($tipo !== 'fixa') {
            $tipos = tiposDeRegra();
            if (!isset($tipos[$tipo])) {
                redirecionarCom($volta, 'Escolha o tipo de regra da conquista.', 'erro');
            }
            [, $tipoAlvo, $min, $max] = $tipos[$tipo];
            $valor = $tipoAlvo === 'trilha' ? 1 : (int) ($_POST['regra_valor'] ?? 0);
            if ($valor < $min || $valor > $max) {
                redirecionarCom($volta, 'O valor da regra precisa estar entre ' . $min . ' e ' . $max . '.', 'erro');
            }
            if ($tipoAlvo !== null) {
                $alvo = (int) ($_POST['regra_alvo_' . $tipoAlvo] ?? 0);
                $lista = $tipoAlvo === 'trilha' ? $trilhas : $provas;
                if (!isset($lista[$alvo])) {
                    redirecionarCom($volta, 'Escolha ' . ($tipoAlvo === 'trilha' ? 'a trilha' : 'a prova') . ' da regra.', 'erro');
                }
            }
        }

        if ($atual) {
            $pdo->prepare(
                "UPDATE conquistas SET titulo = ?, descricao = ?, icone = ?, raridade = ?, regra_valor = ?, regra_alvo = ?
                 WHERE id = ?"
            )->execute([$titulo, $descricao !== '' ? $descricao : null, $icone, $raridade,
                        $tipo === 'fixa' ? null : $valor, $tipo === 'fixa' ? null : $alvo, $id]);
            registrarAcaoAdmin($pdo, 'conquista.editar', 'Conquista: ' . $titulo);
            redirecionarCom('admin_conquistas.php', 'Conquista atualizada.');
        }

        // Código interno único e estável (o título pode mudar à vontade).
        $codigo = 'adm_' . bin2hex(random_bytes(5));
        $pdo->prepare(
            "INSERT INTO conquistas (codigo, titulo, descricao, icone, raridade, ativa, regra_tipo, regra_valor, regra_alvo)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)"
        )->execute([$codigo, $titulo, $descricao !== '' ? $descricao : null, $icone, $raridade, $tipo, $valor, $alvo]);
        registrarAcaoAdmin($pdo, 'conquista.criar', 'Conquista: ' . $titulo);
        redirecionarCom('admin_conquistas.php', 'Conquista criada. Quem já cumpre a regra ganha na próxima vez que entrar ou responder uma atividade.');
    }

    redirecionarCom('admin_conquistas.php', 'Ação inválida.', 'erro');
}

$flash = consumirFlash();

$conquistas = $pdo->query(
    "SELECT c.*, (SELECT COUNT(*) FROM conquistas_usuarios cu
                    JOIN usuarios u ON u.id = cu.usuario_id AND u.tipo = 'aluno'
                   WHERE cu.conquista_id = c.id) AS alunos
     FROM conquistas c ORDER BY c.ativa DESC, c.id"
)->fetchAll();
$totalAlunos = max(1, (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'aluno'")->fetchColumn());
$nomesDeAlvo = $trilhas + $provas;   // ids de trilha e de módulo não colidem na exibição (cada regra sabe qual é)

$editando = null;
if (isset($_GET['editar'])) {
    foreach ($conquistas as $c) {
        if ((int) $c['id'] === (int) $_GET['editar']) {
            $editando = $c;
        }
    }
}
$ehFixa = $editando && $editando['regra_tipo'] === 'fixa';

$tituloPagina    = 'Conquistas';
$subtituloPagina = plural(count($conquistas), 'conquista cadastrada', 'conquistas cadastradas') . '.';
require_once __DIR__ . '/includes/header.php';
?>

<?= abasAdmin('admin_conquistas.php', suporteNaoLidas($pdo)) ?>

<?php if ($flash): ?>
    <?= exibeMensagem($flash[0], $flash[1]) ?>
<?php endif; ?>

<div class="colunas">
    <section class="painel coluna-principal">
        <h3 class="painel-titulo"><?= icone('estrela') ?> Catálogo</h3>
        <div class="tabela-admin-envolve">
            <table class="tabela-admin" id="tabelaConquistas">
                <thead><tr><th></th><th>Conquista</th><th>Regra</th><th class="num">Alunos</th><th><span class="sr-only">Ações</span></th></tr></thead>
                <tbody>
                <?php foreach ($conquistas as $c): ?>
                    <tr class="<?= (int) $c['ativa'] === 1 ? '' : 'linha-inativa' ?>">
                        <td class="selo-conquista-mini"><?= seloConquista((string) $c['icone'], $c['raridade'], false) ?></td>
                        <td><strong><?= e($c['titulo']) ?></strong>
                            <?php if ((int) $c['ativa'] !== 1): ?><span class="selo selo-inativo">desativada</span><?php endif; ?>
                            <small class="ajuda-campo" style="display:block"><?= e((string) $c['descricao']) ?> · <?= e(RARIDADES[$c['raridade']] ?? $c['raridade']) ?></small></td>
                        <td><?= e(descreverRegra($c, $c['regra_tipo'] === 'trilha' ? $trilhas : $provas)) ?></td>
                        <td class="num"><?= (int) $c['alunos'] ?> <small class="ajuda-campo">(<?= round($c['alunos'] / $totalAlunos * 100) ?>%)</small></td>
                        <td>
                            <div class="acoes-linha">
                                <a href="admin_conquistas.php?editar=<?= (int) $c['id'] ?>" class="botao botao-secundario botao-icone"
                                   title="Editar" aria-label="Editar <?= e($c['titulo']) ?>"><?= icone('lapis', 14) ?></a>
                                <form method="post">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="acao" value="alternar">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="botao botao-secundario botao-pequeno">
                                        <?= (int) $c['ativa'] === 1 ? 'Desativar' : 'Ativar' ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="rodape-painel">Conquista não se exclui: desativada, ninguém mais ganha, e quem já ganhou continua com ela no perfil.</p>
    </section>

    <aside class="coluna-lateral">
        <section class="painel" id="formConquista">
            <h3 class="painel-titulo"><?= icone($editando ? 'lapis' : 'estrela') ?> <?= $editando ? 'Editar conquista' : 'Nova conquista' ?></h3>
            <form method="post">
                <?= campoCsrf() ?>
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">

                <div class="campo">
                    <label for="titulo">Título</label>
                    <input type="text" id="titulo" name="titulo" required maxlength="100" value="<?= e($editando['titulo'] ?? '') ?>" placeholder="Ex.: Maratonista">
                </div>
                <div class="campo">
                    <label for="descricao">Descrição (o que o aluno lê)</label>
                    <input type="text" id="descricao" name="descricao" maxlength="255" value="<?= e((string) ($editando['descricao'] ?? '')) ?>" placeholder="Ex.: Conclua 25 atividades.">
                </div>
                <div class="grade-form-2">
                    <div class="campo">
                        <label for="icone">Ícone</label>
                        <select id="icone" name="icone">
                            <?php foreach (ICONES_CONQUISTA as $v => $r): ?>
                                <option value="<?= e($v) ?>" <?= ($editando['icone'] ?? 'estrela') === $v ? 'selected' : '' ?>><?= e($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="raridade">Raridade</label>
                        <select id="raridade" name="raridade">
                            <?php foreach (RARIDADES as $v => $r): ?>
                                <option value="<?= e($v) ?>" <?= ($editando['raridade'] ?? 'bronze') === $v ? 'selected' : '' ?>><?= e($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if ($ehFixa): ?>
                    <p class="aviso-suave">A regra desta conquista é uma das originais e está escrita no código:
                        dá para mudar o texto, o ícone e a raridade, mas não a condição.</p>
                <?php else: ?>
                    <div class="campo">
                        <label for="regra_tipo">Quando o aluno ganha</label>
                        <select id="regra_tipo" name="regra_tipo" <?= $editando ? 'disabled' : 'required' ?>>
                            <option value="">Escolha…</option>
                            <?php foreach (tiposDeRegra() as $v => [$rotulo, $tipoAlvo]): ?>
                                <option value="<?= e($v) ?>" data-alvo="<?= e((string) $tipoAlvo) ?>"
                                    <?= ($editando['regra_tipo'] ?? '') === $v ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($editando): ?><small class="ajuda-campo">O tipo não muda depois de criada; o valor e o alvo, sim.</small><?php endif; ?>
                    </div>
                    <div class="campo" id="campoValor">
                        <label for="regra_valor">Valor de N</label>
                        <input type="number" id="regra_valor" name="regra_valor" min="1" value="<?= (int) ($editando['regra_valor'] ?? 10) ?>">
                    </div>
                    <div class="campo" id="campoAlvoTrilha" hidden>
                        <label for="regra_alvo_trilha">Trilha</label>
                        <select id="regra_alvo_trilha" name="regra_alvo_trilha">
                            <?php foreach ($trilhas as $tid => $tt): ?>
                                <option value="<?= (int) $tid ?>" <?= (int) ($editando['regra_alvo'] ?? 0) === (int) $tid ? 'selected' : '' ?>><?= e($tt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo" id="campoAlvoProva" hidden>
                        <label for="regra_alvo_prova">Prova</label>
                        <select id="regra_alvo_prova" name="regra_alvo_prova">
                            <?php foreach ($provas as $pid => $pt): ?>
                                <option value="<?= (int) $pid ?>" <?= (int) ($editando['regra_alvo'] ?? 0) === (int) $pid ? 'selected' : '' ?>><?= e($pt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <button type="submit" class="botao botao-primario botao-bloco"><?= $editando ? 'Salvar alterações' : 'Criar conquista' ?></button>
                <?php if ($editando): ?>
                    <a href="admin_conquistas.php" class="botao botao-secundario botao-bloco" style="margin-top: 10px;">Cancelar</a>
                <?php endif; ?>
            </form>
        </section>
    </aside>
</div>

<script>
// Mostra o campo certo para o tipo de regra (valor, trilha ou prova).
(function () {
    var tipo = document.getElementById('regra_tipo');
    if (!tipo) { return; }
    function atualizar() {
        var opcao = tipo.options[tipo.selectedIndex];
        var alvo = opcao ? opcao.getAttribute('data-alvo') : '';
        document.getElementById('campoAlvoTrilha').hidden = alvo !== 'trilha';
        document.getElementById('campoAlvoProva').hidden = alvo !== 'prova';
        document.getElementById('campoValor').hidden = alvo === 'trilha';
    }
    tipo.addEventListener('change', atualizar);
    atualizar();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
