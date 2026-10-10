<?php
/**
 * admin_trilha.php — edita uma trilha e organiza os módulos dela.
 *
 * Segunda tela da Administração:
 *     administracao.php → ESTA → admin_modulo.php → admin_atividade.php
 *
 * Aqui se decide a SEQUÊNCIA da trilha, que é o que o aluno percorre: os
 * módulos abrem um de cada vez, na ordem definida nesta página, e a prova de
 * fim de nível trava o avanço até ser aprovada.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';

exigirAdmin();

$trilhaId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['trilha_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM trilhas WHERE id = ?");
$stmt->execute([$trilhaId]);
$trilha = $stmt->fetch();

if (!$trilha) {
    redirecionarCom('administracao.php', 'Trilha não encontrada.', 'erro');
}

$dificuldadesValidas = ['iniciante', 'intermediario', 'avancado'];

/* -----------------------------------------------------------------------------
 * POST
 * -------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();

    $acao   = $_POST['acao'] ?? '';
    $volta  = 'admin_trilha.php?id=' . $trilhaId;

    /* ---- salvar os dados da trilha ---- */
    if ($acao === 'salvar_trilha') {
        $titulo      = trim($_POST['titulo'] ?? '');
        $descricao   = trim($_POST['descricao'] ?? '');
        $dificuldade = in_array($_POST['nivel_dificuldade'] ?? '', $dificuldadesValidas, true)
                       ? $_POST['nivel_dificuldade'] : 'iniciante';
        $icone       = trim($_POST['icone'] ?? '');
        $subtitulo   = trim($_POST['subtitulo'] ?? '');
        $destaque    = isset($_POST['destaque']) ? 1 : 0;
        $linguagem   = array_key_exists($_POST['linguagem'] ?? '', linguagensDeTrilha())
                       ? $_POST['linguagem'] : $trilha['linguagem'];
        $requisitos  = array_map('intval', (array) ($_POST['requisitos'] ?? []));

        if (!array_key_exists($icone, iconesDeTrilha())) {
            $icone = '';
        }
        if ($titulo === '') {
            redirecionarCom($volta, 'O título da trilha não pode ficar vazio.', 'erro');
        }

        // Os casos de teste de JavaScript e de HTML têm formatos diferentes:
        // trocar a linguagem de uma trilha que já tem exercícios quebraria todos.
        if ($linguagem !== $trilha['linguagem']) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM atividades a JOIN modulos m ON m.id = a.modulo_id WHERE m.trilha_id = ?"
            );
            $stmt->execute([$trilhaId]);
            if ((int) $stmt->fetchColumn() > 0) {
                redirecionarCom($volta, 'A linguagem não pode mudar: a trilha já tem atividades escritas para ' .
                    linguagensDeTrilha()[$trilha['linguagem']] . '.', 'erro');
            }
        }

        // Requisito circular: se a trilha A exige B e B (direta ou indiretamente)
        // exige A, nenhuma das duas abre nunca. Anda pelo grafo de requisitos a
        // partir de cada trilha escolhida; se chegar nesta, recusa.
        $arestas = [];
        foreach ($pdo->query("SELECT trilha_id, requisito_id FROM trilha_requisitos")->fetchAll() as $linha) {
            if ((int) $linha['trilha_id'] !== $trilhaId) {          // as desta trilha serão regravadas
                $arestas[(int) $linha['trilha_id']][] = (int) $linha['requisito_id'];
            }
        }
        foreach (array_unique($requisitos) as $req) {
            $fila = [(int) $req];
            $vistos = [];
            while ($fila) {
                $atual = array_shift($fila);
                if ($atual === $trilhaId) {
                    $stmt = $pdo->prepare("SELECT titulo FROM trilhas WHERE id = ?");
                    $stmt->execute([(int) $req]);
                    redirecionarCom($volta, 'Requisito circular: "' . $stmt->fetchColumn()
                        . '" já depende desta trilha. As duas ficariam bloqueadas para sempre.', 'erro');
                }
                if (isset($vistos[$atual])) {
                    continue;
                }
                $vistos[$atual] = true;
                foreach ($arestas[$atual] ?? [] as $proxima) {
                    $fila[] = $proxima;
                }
            }
        }

        $pdo->beginTransaction();
        $pdo->prepare(
            "UPDATE trilhas SET titulo = ?, descricao = ?, nivel_dificuldade = ?, icone = ?,
                    linguagem = ?, subtitulo = ?, destaque = ?
             WHERE id = ?"
        )->execute([
            $titulo,
            $descricao !== '' ? $descricao : null,
            $dificuldade,
            $icone !== '' ? $icone : null,
            $linguagem,
            $subtitulo !== '' ? mb_substr($subtitulo, 0, 80) : null,
            $destaque,
            $trilhaId,
        ]);

        // Requisitos: regrava a lista inteira. Ignora a própria trilha e ids
        // que não existem (o INSERT ... SELECT só insere o que existir).
        $pdo->prepare("DELETE FROM trilha_requisitos WHERE trilha_id = ?")->execute([$trilhaId]);
        $inserir = $pdo->prepare(
            "INSERT INTO trilha_requisitos (trilha_id, requisito_id)
             SELECT ?, id FROM trilhas WHERE id = ? AND id <> ?"
        );
        foreach (array_unique($requisitos) as $req) {
            $inserir->execute([$trilhaId, $req, $trilhaId]);
        }
        $pdo->commit();

        registrarAcaoAdmin($pdo, 'trilha.editar', 'Trilha: ' . $titulo);
        redirecionarCom($volta, 'Trilha atualizada.');
    }

    /* ---- criar módulo ---- */
    if ($acao === 'criar_modulo') {
        $titulo    = trim($_POST['modulo_titulo'] ?? '');
        $tipo      = ($_POST['modulo_tipo'] ?? 'aula') === 'prova' ? 'prova' : 'aula';
        $descricao = trim($_POST['modulo_descricao'] ?? '');

        if ($titulo === '') {
            redirecionarCom($volta, 'Informe o título do módulo.', 'erro');
        }

        // Nota mínima só faz sentido em prova; num módulo de aula o valor fica
        // no padrão e é ignorado pelo sistema.
        $notaMinima = $tipo === 'prova'
            ? max(1, min(100, (int) ($_POST['modulo_nota'] ?? 70)))
            : 70;

        $stmt = $pdo->prepare(
            "INSERT INTO modulos (trilha_id, titulo, tipo, nota_minima, descricao, ordem)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $trilhaId,
            $titulo,
            $tipo,
            $notaMinima,
            $descricao !== '' ? $descricao : null,
            proximaOrdem($pdo, 'modulos', 'trilha_id', $trilhaId),
        ]);

        $novoModulo = (int) $pdo->lastInsertId();
        registrarAcaoAdmin($pdo, 'modulo.criar', 'Módulo: ' . $titulo . ' (' . $trilha['titulo'] . ')');
        redirecionarCom('admin_modulo.php?id=' . $novoModulo,
            'Módulo criado. Agora cadastre as atividades dele.');
    }

    /* ---- mover módulo para cima ou para baixo ---- */
    if ($acao === 'mover') {
        $moduloId = (int) ($_POST['modulo_id'] ?? 0);
        $direcao  = ($_POST['direcao'] ?? '') === 'cima' ? 'cima' : 'baixo';

        $stmt = $pdo->prepare("SELECT id, ordem FROM modulos WHERE id = ? AND trilha_id = ?");
        $stmt->execute([$moduloId, $trilhaId]);
        $atual = $stmt->fetch();

        if ($atual) {
            // Troca a ordem com o vizinho. Comparar por `ordem` e desempatar por
            // `id` cobre o caso de dois módulos terem ficado com a mesma ordem.
            $comparador = $direcao === 'cima' ? '<' : '>';
            $sentido    = $direcao === 'cima' ? 'DESC' : 'ASC';

            $stmt = $pdo->prepare(
                "SELECT id, ordem FROM modulos
                  WHERE trilha_id = ? AND (ordem $comparador ? OR (ordem = ? AND id $comparador ?))
                  ORDER BY ordem $sentido, id $sentido
                  LIMIT 1"
            );
            $stmt->execute([$trilhaId, (int) $atual['ordem'], (int) $atual['ordem'], $moduloId]);
            $vizinho = $stmt->fetch();

            if ($vizinho) {
                $troca = $pdo->prepare("UPDATE modulos SET ordem = ? WHERE id = ?");
                $troca->execute([(int) $vizinho['ordem'], $moduloId]);
                $troca->execute([(int) $atual['ordem'], (int) $vizinho['id']]);
                registrarAcaoAdmin($pdo, 'modulo.mover', 'Módulo #' . $moduloId . ' (' . $trilha['titulo'] . ')');
            }
        }

        header('Location: ' . $volta);
        exit;
    }

    /* ---- apagar módulo ---- */
    if ($acao === 'apagar_modulo') {
        $moduloId = (int) ($_POST['modulo_id'] ?? 0);

        // Apagar em cascata levaria junto as conclusões e o XP de quem já fez —
        // progresso de aluno sumindo por causa de uma limpeza de conteúdo. Se
        // alguém já usou o módulo, ele não some: some só o que ninguém tocou.
        if (moduloTemProgresso($pdo, $moduloId)) {
            redirecionarCom($volta,
                'Este módulo não pode ser apagado: algum aluno já concluiu atividades dele. '
              . 'Despublique a trilha se quiser tirá-la do ar.', 'erro');
        }

        $stmt = $pdo->prepare("SELECT titulo FROM modulos WHERE id = ? AND trilha_id = ?");
        $stmt->execute([$moduloId, $trilhaId]);
        $tituloApagado = (string) $stmt->fetchColumn();
        $pdo->prepare("DELETE FROM modulos WHERE id = ? AND trilha_id = ?")
            ->execute([$moduloId, $trilhaId]);
        registrarAcaoAdmin($pdo, 'modulo.apagar', 'Módulo: ' . $tituloApagado . ' (' . $trilha['titulo'] . ')');

        redirecionarCom($volta, 'Módulo apagado.');
    }

    header('Location: ' . $volta);
    exit;
}

$flash = consumirFlash();

$tituloPagina    = $trilha['titulo'];
$subtituloPagina = 'Organize os módulos desta trilha.';

require_once __DIR__ . '/includes/header.php';

$stmt = $pdo->prepare(
    "SELECT m.*,
            (SELECT COUNT(*) FROM atividades a WHERE a.modulo_id = m.id) AS atividades
     FROM modulos m
     WHERE m.trilha_id = ?
     ORDER BY m.ordem, m.id"
);
$stmt->execute([$trilhaId]);
$modulos = $stmt->fetchAll();

$ultimo = count($modulos) - 1;
?>

<?= trilhaDeNavegacaoAdmin([['titulo' => $trilha['titulo']]]) ?>

<?php if ($flash): ?>
    <?= exibeMensagem($flash[0], $flash[1]) ?>
<?php endif; ?>

<?php if ((int) $trilha['publicada'] === 0): ?>
    <p class="aviso aviso-info">
        Esta trilha está como <strong>rascunho</strong>: os alunos ainda não a veem.
        Publique pela <a href="administracao.php">central de Administração</a> quando estiver pronta.
    </p>
<?php endif; ?>

<div class="colunas">

    <div class="coluna-principal">

        <section class="painel">
            <h3 class="painel-titulo">
                <?= icone('camadas') ?> Módulos
                <small class="contador"><?= count($modulos) ?></small>
            </h3>

            <?php if (empty($modulos)): ?>
                <p class="aviso-suave">
                    Nenhum módulo ainda. Crie o primeiro ao lado — normalmente uma aula com a
                    explicação do assunto, seguida das atividades que aplicam o conteúdo.
                </p>
            <?php else: ?>
                <ol class="lista-admin lista-admin-numerada">
                    <?php foreach ($modulos as $i => $modulo): ?>
                        <li class="linha-admin">
                            <span class="linha-admin-ordem"><?= $i + 1 ?></span>

                            <div class="linha-admin-info">
                                <strong><?= e($modulo['titulo']) ?></strong>
                                <small>
                                    <?php if ($modulo['tipo'] === 'prova'): ?>
                                        Prova &middot; nota mínima <?= (int) $modulo['nota_minima'] ?>%
                                        &middot; <?= plural((int) $modulo['atividades'], 'questão', 'questões') ?>
                                    <?php else: ?>
                                        Aula &middot; <?= plural((int) $modulo['atividades'], 'atividade', 'atividades') ?>
                                        <?php if (empty($modulo['teoria_texto'])): ?>
                                            &middot; <span class="alerta-suave">sem explicação escrita</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </small>
                            </div>

                            <?php if ($modulo['tipo'] === 'prova'): ?>
                                <span class="selo selo-prova">prova</span>
                            <?php endif; ?>

                            <?php if ((int) $modulo['atividades'] === 0): ?>
                                <span class="selo selo-modulo-vazio" title="<?= $modulo['tipo'] === 'prova' ? 'Prova sem questões' : 'Módulo sem atividades' ?>: o aluno pula direto para o próximo">vazio</span>
                            <?php endif; ?>

                            <div class="linha-admin-acoes">
                                <form method="POST" class="formulario-embutido">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="trilha_id" value="<?= $trilhaId ?>">
                                    <input type="hidden" name="acao" value="mover">
                                    <input type="hidden" name="modulo_id" value="<?= (int) $modulo['id'] ?>">
                                    <button type="submit" name="direcao" value="cima"
                                            class="botao botao-secundario botao-icone"
                                            title="Mover para cima" <?= $i === 0 ? 'disabled' : '' ?>>&uarr;</button>
                                    <button type="submit" name="direcao" value="baixo"
                                            class="botao botao-secundario botao-icone"
                                            title="Mover para baixo" <?= $i === $ultimo ? 'disabled' : '' ?>>&darr;</button>
                                </form>

                                <?php if ((int) $modulo['atividades'] > 0): ?>
                                    <?php // ADM-03: abre o módulo como o aluno vê (o admin passa pelos bloqueios). ?>
                                    <a href="<?= $modulo['tipo'] === 'prova' ? 'prova.php' : 'desafio.php' ?>?modulo=<?= (int) $modulo['id'] ?>"
                                       class="botao botao-secundario botao-icone" title="Ver como aluno"
                                       aria-label="Ver <?= e($modulo['titulo']) ?> como aluno"><?= icone('olho', 16) ?></a>
                                <?php endif; ?>
                                <a href="admin_modulo.php?id=<?= (int) $modulo['id'] ?>"
                                   class="botao botao-primario botao-pequeno">Abrir</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <p class="rodape-painel">
                A ordem acima é a ordem que o aluno percorre: cada módulo só libera depois do
                anterior ser concluído. Um módulo de prova trava o avanço até ser aprovado.
            </p>
        </section>

    </div>

    <aside class="coluna-lateral">

        <section class="painel">
            <h3 class="painel-titulo"><?= icone('livro') ?> Novo módulo</h3>

            <form method="POST" id="formModulo">
                <?= campoCsrf() ?>
                <input type="hidden" name="trilha_id" value="<?= $trilhaId ?>">
                <input type="hidden" name="acao" value="criar_modulo">

                <div class="campo">
                    <label for="modulo_titulo">Título</label>
                    <input type="text" id="modulo_titulo" name="modulo_titulo" required maxlength="100"
                           placeholder="Ex: Operadores Aritméticos">
                </div>

                <div class="campo">
                    <label for="modulo_tipo">Tipo</label>
                    <select id="modulo_tipo" name="modulo_tipo">
                        <option value="aula">Aula</option>
                        <option value="prova">Prova de fim de nível</option>
                    </select>
                </div>

                <div class="campo" id="campoNota" hidden>
                    <label for="modulo_nota">Nota mínima para aprovar (%)</label>
                    <input type="number" id="modulo_nota" name="modulo_nota" value="70" min="1" max="100">
                </div>

                <div class="campo">
                    <label for="modulo_descricao">Descrição curta</label>
                    <textarea id="modulo_descricao" name="modulo_descricao" rows="2"
                              placeholder="Uma frase sobre o que o módulo cobre"></textarea>
                </div>

                <button type="submit" class="botao botao-primario botao-bloco">Criar módulo</button>
            </form>
        </section>

        <section class="painel">
            <h3 class="painel-titulo"><?= icone('admin') ?> Dados da trilha</h3>

            <form method="POST">
                <?= campoCsrf() ?>
                <input type="hidden" name="trilha_id" value="<?= $trilhaId ?>">
                <input type="hidden" name="acao" value="salvar_trilha">

                <div class="campo">
                    <label for="titulo">Título</label>
                    <input type="text" id="titulo" name="titulo" required maxlength="100"
                           value="<?= e($trilha['titulo']) ?>">
                </div>

                <div class="campo">
                    <label for="descricao">Descrição</label>
                    <textarea id="descricao" name="descricao" rows="3"><?= e($trilha['descricao']) ?></textarea>
                </div>

                <div class="campo">
                    <label for="nivel_dificuldade">Dificuldade</label>
                    <select id="nivel_dificuldade" name="nivel_dificuldade">
                        <?php foreach ($dificuldadesValidas as $codigo): ?>
                            <option value="<?= e($codigo) ?>" <?= $trilha['nivel_dificuldade'] === $codigo ? 'selected' : '' ?>>
                                <?= e(rotuloDificuldade($codigo)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="icone">Ícone</label>
                    <select id="icone" name="icone">
                        <?php foreach (iconesDeTrilha() as $valor => $rotulo): ?>
                            <option value="<?= e($valor) ?>" <?= (string) $trilha['icone'] === (string) $valor ? 'selected' : '' ?>>
                                <?= e($rotulo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="linguagem">Linguagem dos exercícios</label>
                    <select id="linguagem" name="linguagem">
                        <?php foreach (linguagensDeTrilha() as $valor => $rotulo): ?>
                            <option value="<?= e($valor) ?>" <?= $trilha['linguagem'] === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="subtitulo">Chamada no destaque</label>
                    <input type="text" id="subtitulo" name="subtitulo" maxlength="80"
                           placeholder="Ex: Estrutura e Conteúdo" value="<?= e($trilha['subtitulo'] ?? '') ?>">
                </div>

                <label class="linha-check">
                    <input type="checkbox" name="destaque" value="1" <?= (int) $trilha['destaque'] === 1 ? 'checked' : '' ?>>
                    Mostrar no painel de destaque da tela de Trilhas
                </label>

                <?php
                    $outras = $pdo->prepare("SELECT id, titulo FROM trilhas WHERE id <> ? ORDER BY ordem, id");
                    $outras->execute([$trilhaId]);
                    $outras = $outras->fetchAll();
                    $atuais = $pdo->prepare("SELECT requisito_id FROM trilha_requisitos WHERE trilha_id = ?");
                    $atuais->execute([$trilhaId]);
                    $atuais = array_map('intval', $atuais->fetchAll(PDO::FETCH_COLUMN));
                ?>
                <?php if ($outras): ?>
                    <fieldset class="campo campo-requisitos">
                        <legend>Só libera depois de concluir</legend>
                        <?php foreach ($outras as $o): ?>
                            <label class="linha-check">
                                <input type="checkbox" name="requisitos[]" value="<?= (int) $o['id'] ?>"
                                       <?= in_array((int) $o['id'], $atuais, true) ? 'checked' : '' ?>>
                                <?= e($o['titulo']) ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endif; ?>

                <button type="submit" class="botao botao-secundario botao-bloco">Salvar alterações</button>
            </form>
        </section>

    </aside>

</div>

<script>
// A nota mínima só aparece quando o módulo é uma prova: num módulo de aula o
// campo não significa nada e só confunde quem está cadastrando.
(function () {
    var tipo  = document.getElementById('modulo_tipo');
    var campo = document.getElementById('campoNota');
    if (!tipo || !campo) { return; }

    function alternar() { campo.hidden = (tipo.value !== 'prova'); }
    tipo.addEventListener('change', alternar);
    alternar();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
