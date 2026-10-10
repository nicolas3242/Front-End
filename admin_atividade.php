<?php
/**
 * admin_atividade.php — cadastra e edita uma atividade.
 *
 * Última tela da Administração, e a mais delicada: é aqui que entra o conteúdo
 * que o aluno vai resolver e o gabarito contra o qual ele vai ser corrigido.
 *
 * DUAS REGRAS QUE ESTA TELA FAZ CUMPRIR
 * -----------------------------------------------------------------------------
 * 1. Exercício de código só salva depois que a SOLUÇÃO DE REFERÊNCIA passar nos
 *    próprios casos de teste. A conferência roda no mesmo Web Worker que o aluno
 *    usa (assets/js/admin-atividade.js) e é obrigatória.
 *
 *    Isso não é preciosismo. O pipeline de conteúdo (tools/validar_conteudo.js)
 *    já pegou um gabarito errado de verdade — um exercício de matriz cujo bug
 *    produzia o MESMO resultado dos casos escolhidos. Sem essa trava, a tela
 *    seria um jeito mais fácil de cadastrar exercício quebrado do que o
 *    pipeline, e valeria menos que ele.
 *
 * 2. Todo exercício de código precisa de pelo menos um CASO OCULTO. É ele que
 *    impede o aluno de passar chumbando a resposta visível no código.
 *
 * O servidor confere tudo de novo ao salvar, porque validação que só existe no
 * navegador não é validação — é conveniência.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';

exigirAdmin();

$atividadeId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['atividade_id'] ?? 0);
$moduloId    = isset($_GET['modulo']) ? (int) $_GET['modulo'] : (int) ($_POST['modulo_id'] ?? 0);

$atividade = null;

if ($atividadeId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM atividades WHERE id = ?");
    $stmt->execute([$atividadeId]);
    $atividade = $stmt->fetch();

    if (!$atividade) {
        redirecionarCom('administracao.php', 'Atividade não encontrada.', 'erro');
    }
    $moduloId = (int) $atividade['modulo_id'];
}

$stmt = $pdo->prepare(
    "SELECT m.*, t.id AS trilha_id, t.titulo AS trilha_titulo, t.linguagem
     FROM modulos m JOIN trilhas t ON t.id = m.trilha_id
     WHERE m.id = ?"
);
$stmt->execute([$moduloId]);
$modulo = $stmt->fetch();

if (!$modulo) {
    redirecionarCom('administracao.php', 'Módulo não encontrado.', 'erro');
}

$ehProva = $modulo['tipo'] === 'prova';
$volta   = 'admin_modulo.php?id=' . $moduloId;

/* -----------------------------------------------------------------------------
 * POST
 * -------------------------------------------------------------------------- */

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();

    if (($_POST['acao'] ?? '') === 'apagar') {
        if ($atividadeId > 0 && atividadeTemProgresso($pdo, $atividadeId)) {
            redirecionarCom('admin_atividade.php?id=' . $atividadeId,
                'Esta atividade não pode ser apagada: algum aluno já a concluiu.', 'erro');
        }
        $stmt = $pdo->prepare("SELECT titulo FROM atividades WHERE id = ?");
        $stmt->execute([$atividadeId]);
        $tituloApagado = (string) $stmt->fetchColumn();
        $pdo->prepare("DELETE FROM atividades WHERE id = ?")->execute([$atividadeId]);
        registrarAcaoAdmin($pdo, 'atividade.apagar', 'Atividade: ' . $tituloApagado);
        redirecionarCom($volta, 'Atividade apagada.');
    }

    $titulo    = trim($_POST['titulo'] ?? '');
    $enunciado = trim($_POST['enunciado'] ?? '');

    // Numa prova toda questão é quiz: a correção é automática e de uma vez só.
    $tipo = $ehProva ? 'quiz' : ($_POST['tipo'] ?? 'quiz');
    if (!in_array($tipo, tiposDeAtividade(), true)) {
        $tipo = 'quiz';
    }

    $dificuldade = array_key_exists($_POST['dificuldade'] ?? '', dificuldades())
                 ? $_POST['dificuldade'] : 'facil';
    $xp = max(0, min(10000, (int) ($_POST['xp_recompensa'] ?? 100)));

    $codigoInicial = trim($_POST['codigo_inicial'] ?? '');
    $dica          = trim($_POST['dica'] ?? '');
    $solucao       = trim($_POST['solucao_exemplo'] ?? '');

    $referencia = $ehProva && !empty($_POST['modulo_referencia_id'])
                ? (int) $_POST['modulo_referencia_id'] : null;

    if ($titulo === '')    { $erros[] = 'Informe o título da atividade.'; }
    if ($enunciado === '') { $erros[] = 'Informe o enunciado — é o que o aluno lê para saber o que fazer.'; }

    /* ---- alternativas (quiz) ---- */
    $alternativas = [];
    if (ehQuiz($tipo)) {
        $textos  = $_POST['alt_texto'] ?? [];
        $correta = isset($_POST['alt_correta']) ? (int) $_POST['alt_correta'] : -1;

        foreach (array_values((array) $textos) as $i => $texto) {
            $texto = trim((string) $texto);
            if ($texto === '') {
                continue;
            }
            $alternativas[] = ['texto' => $texto, 'correta' => ($i === $correta)];
        }

        if (count($alternativas) < 2) {
            $erros[] = 'Um quiz precisa de pelo menos duas alternativas preenchidas.';
        }

        $marcadas = count(array_filter($alternativas, static fn(array $a): bool => $a['correta']));
        if ($marcadas !== 1) {
            $erros[] = 'Marque exatamente uma alternativa como correta.';
        }

        if ($ehProva && $referencia === null) {
            $erros[] = 'Toda questão de prova precisa dizer qual módulo ela avalia — '
                     . 'é disso que sai a indicação do que revisar quando o aluno reprova.';
        }
    }

    /* ---- casos de teste (exercício de código) ---- */
    $casos = [];
    if (!ehQuiz($tipo)) {
        $entradas = $_POST['caso_entrada'] ?? [];
        $saidas   = $_POST['caso_saida'] ?? [];
        $ocultos  = $_POST['caso_oculto'] ?? [];
        $descrs   = $_POST['caso_descricao'] ?? [];

        foreach (array_values((array) $saidas) as $i => $saida) {
            $saida = (string) $saida;
            if (trim($saida) === '') {
                continue;                       // linha em branco: ignorada
            }
            $entrada = trim((string) ($entradas[$i] ?? ''));
            $casos[] = [
                'descricao' => trim((string) ($descrs[$i] ?? '')),
                'entrada'   => $entrada !== '' ? $entrada : null,
                'saida'     => $saida,
                'oculto'    => !empty($ocultos[$i]),
            ];
        }

        if (empty($casos)) {
            $erros[] = 'Um exercício de código precisa de pelo menos um caso de teste.';
        }

        $qtdOcultos  = count(array_filter($casos, static fn(array $c): bool => $c['oculto']));
        $qtdVisiveis = count($casos) - $qtdOcultos;

        // Entradas distintas: se todos os casos chamam a mesma coisa, não há o
        // que esconder — é exercício de comportamento único (ver tools/conteudo.py).
        $entradasDistintas = count(array_unique(array_map(
            static fn(array $c): string => (string) $c['entrada'], $casos
        )));

        if ($qtdVisiveis === 0 && !empty($casos)) {
            $erros[] = 'Deixe pelo menos um caso visível: é por ele que o aluno entende o que se pede.';
        }

        if ($entradasDistintas > 1 && $qtdOcultos === 0) {
            $erros[] = 'Marque pelo menos um caso como oculto. Sem isso, o aluno passa chumbando '
                     . 'no código as respostas que estão na tela.';
        }

        if ($solucao === '') {
            $erros[] = 'Informe a solução de referência — é ela que prova que o exercício tem resposta.';
        }

        // A conferência de verdade roda no navegador, no mesmo Web Worker do
        // aluno. O servidor não executa JavaScript, então o que ele faz aqui é
        // exigir o comprovante: um campo preenchido pelo JS só depois que todos
        // os casos passaram. Não é à prova de fraude — mas quem cadastra é um
        // admin, e o inimigo aqui é o erro humano, não o adversário.
        if (($_POST['gabarito_conferido'] ?? '') !== 'ok') {
            $erros[] = 'Clique em "Conferir gabarito" antes de salvar. A solução de referência '
                     . 'precisa passar em todos os casos de teste.';
        }
    }

    /* ---- gravação ---- */
    if (empty($erros)) {
        $pdo->beginTransaction();

        try {
            if ($atividadeId > 0) {
                $pdo->prepare(
                    "UPDATE atividades
                        SET titulo = ?, enunciado = ?, tipo = ?, dificuldade = ?,
                            codigo_inicial = ?, dica = ?, solucao_exemplo = ?,
                            xp_recompensa = ?, modulo_referencia_id = ?
                      WHERE id = ?"
                )->execute([
                    $titulo, $enunciado, $tipo, $dificuldade,
                    $codigoInicial !== '' ? $codigoInicial : null,
                    $dica !== ''          ? $dica          : null,
                    $solucao !== ''       ? $solucao       : null,
                    $xp, $referencia, $atividadeId,
                ]);
            } else {
                $pdo->prepare(
                    "INSERT INTO atividades
                        (modulo_id, modulo_referencia_id, titulo, enunciado, tipo, dificuldade,
                         codigo_inicial, dica, solucao_exemplo, xp_recompensa, ordem)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $moduloId, $referencia, $titulo, $enunciado, $tipo, $dificuldade,
                    $codigoInicial !== '' ? $codigoInicial : null,
                    $dica !== ''          ? $dica          : null,
                    $solucao !== ''       ? $solucao       : null,
                    $xp,
                    proximaOrdem($pdo, 'atividades', 'modulo_id', $moduloId),
                ]);
                $atividadeId = (int) $pdo->lastInsertId();
            }

            // Casos de teste são regravados do zero (ninguém guarda o id deles).
            // Alternativas NÃO (ADM-50): o id é o que o formulário da prova e do
            // quiz envia como resposta. Recriar com ids novos fazia a prova que um
            // aluno estava fazendo naquele momento ser corrigida como errada.
            // Então as linhas existentes são reaproveitadas na mesma posição, as
            // que sobram são apagadas e as que faltam, inseridas.
            $pdo->prepare("DELETE FROM casos_teste WHERE atividade_id = ?")->execute([$atividadeId]);

            $stmt = $pdo->prepare("SELECT id FROM alternativas WHERE atividade_id = ? ORDER BY ordem, id");
            $stmt->execute([$atividadeId]);
            $idsAntigos = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (ehQuiz($tipo)) {
                $upd = $pdo->prepare("UPDATE alternativas SET texto = ?, correta = ?, ordem = ? WHERE id = ?");
                $ins = $pdo->prepare(
                    "INSERT INTO alternativas (atividade_id, texto, correta, ordem) VALUES (?, ?, ?, ?)"
                );
                foreach ($alternativas as $i => $alt) {
                    if (isset($idsAntigos[$i])) {
                        $upd->execute([$alt['texto'], $alt['correta'] ? 1 : 0, $i + 1, (int) $idsAntigos[$i]]);
                    } else {
                        $ins->execute([$atividadeId, $alt['texto'], $alt['correta'] ? 1 : 0, $i + 1]);
                    }
                }
                $sobras = array_slice($idsAntigos, count($alternativas));
            } else {
                $sobras = $idsAntigos;          // virou exercício de código: alternativas saem
            }
            if ($sobras) {
                $marcas = implode(',', array_fill(0, count($sobras), '?'));
                $pdo->prepare("DELETE FROM alternativas WHERE id IN ($marcas)")->execute(array_map('intval', $sobras));
            }

            if (!ehQuiz($tipo)) {
                $ins = $pdo->prepare(
                    "INSERT INTO casos_teste (atividade_id, descricao, entrada, saida_esperada, visivel, ordem)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                foreach ($casos as $i => $caso) {
                    $ins->execute([
                        $atividadeId,
                        $caso['descricao'] !== '' ? $caso['descricao'] : null,
                        $caso['entrada'],
                        $caso['saida'],
                        $caso['oculto'] ? 0 : 1,
                        $i + 1,
                    ]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('DevFox — falha ao salvar atividade: ' . $e->getMessage());
            $erros[] = 'Não foi possível salvar. O erro foi registrado no log do servidor.';
        }

        if (empty($erros)) {
            registrarAcaoAdmin($pdo, $atividadeId > 0 ? 'atividade.editar' : 'atividade.criar', 'Atividade: ' . $titulo);
            redirecionarCom($volta, 'Atividade salva.');
        }
    }
}

/* -----------------------------------------------------------------------------
 * Dados para o formulário
 * -------------------------------------------------------------------------- */

// Depois de um POST com erro, o formulário volta com o que a pessoa digitou —
// perder meia hora de digitação por causa de um campo esquecido é inaceitável.
$voltouComErro = !empty($erros);

$valores = [
    'titulo'          => $voltouComErro ? ($_POST['titulo'] ?? '')          : ($atividade['titulo'] ?? ''),
    'enunciado'       => $voltouComErro ? ($_POST['enunciado'] ?? '')       : ($atividade['enunciado'] ?? ''),
    'tipo'            => $voltouComErro ? ($_POST['tipo'] ?? 'quiz')        : ($atividade['tipo'] ?? ($ehProva ? 'quiz' : 'codigo_livre')),
    'dificuldade'     => $voltouComErro ? ($_POST['dificuldade'] ?? 'facil'): ($atividade['dificuldade'] ?? 'facil'),
    'xp_recompensa'   => $voltouComErro ? ($_POST['xp_recompensa'] ?? 100)  : ($atividade['xp_recompensa'] ?? ($ehProva ? 50 : 100)),
    'codigo_inicial'  => $voltouComErro ? ($_POST['codigo_inicial'] ?? '')  : ($atividade['codigo_inicial'] ?? ''),
    'dica'            => $voltouComErro ? ($_POST['dica'] ?? '')            : ($atividade['dica'] ?? ''),
    'solucao_exemplo' => $voltouComErro ? ($_POST['solucao_exemplo'] ?? '') : ($atividade['solucao_exemplo'] ?? ''),
    'referencia'      => $voltouComErro ? ($_POST['modulo_referencia_id'] ?? '') : ($atividade['modulo_referencia_id'] ?? ''),
];

// Alternativas
$listaAlternativas = [];
if ($voltouComErro) {
    $marcada = isset($_POST['alt_correta']) ? (int) $_POST['alt_correta'] : -1;
    foreach (array_values((array) ($_POST['alt_texto'] ?? [])) as $i => $texto) {
        $listaAlternativas[] = ['texto' => (string) $texto, 'correta' => ($i === $marcada)];
    }
} elseif ($atividadeId > 0) {
    $stmt = $pdo->prepare("SELECT texto, correta FROM alternativas WHERE atividade_id = ? ORDER BY ordem, id");
    $stmt->execute([$atividadeId]);
    foreach ($stmt->fetchAll() as $alt) {
        $listaAlternativas[] = ['texto' => $alt['texto'], 'correta' => (int) $alt['correta'] === 1];
    }
}
while (count($listaAlternativas) < 4) {
    $listaAlternativas[] = ['texto' => '', 'correta' => false];
}

// Casos de teste
$listaCasos = [];
if ($voltouComErro) {
    $ocultos = (array) ($_POST['caso_oculto'] ?? []);
    foreach (array_values((array) ($_POST['caso_saida'] ?? [])) as $i => $saida) {
        $listaCasos[] = [
            'descricao' => (string) (($_POST['caso_descricao'] ?? [])[$i] ?? ''),
            'entrada'   => (string) (($_POST['caso_entrada'] ?? [])[$i] ?? ''),
            'saida'     => (string) $saida,
            'oculto'    => !empty($ocultos[$i]),
        ];
    }
} elseif ($atividadeId > 0) {
    $stmt = $pdo->prepare(
        "SELECT descricao, entrada, saida_esperada, visivel FROM casos_teste
         WHERE atividade_id = ? ORDER BY ordem, id"
    );
    $stmt->execute([$atividadeId]);
    foreach ($stmt->fetchAll() as $caso) {
        $listaCasos[] = [
            'descricao' => (string) $caso['descricao'],
            'entrada'   => (string) $caso['entrada'],
            'saida'     => (string) $caso['saida_esperada'],
            'oculto'    => (int) $caso['visivel'] === 0,
        ];
    }
}
while (count($listaCasos) < 3) {
    $listaCasos[] = ['descricao' => '', 'entrada' => '', 'saida' => '', 'oculto' => count($listaCasos) === 2];
}

// Módulos de aula anteriores a esta prova, para o campo "o que a questão avalia".
$modulosAvaliaveis = [];
if ($ehProva) {
    $stmt = $pdo->prepare(
        "SELECT id, titulo FROM modulos
          WHERE trilha_id = ? AND tipo = 'aula' AND ordem < ?
          ORDER BY ordem, id"
    );
    $stmt->execute([(int) $modulo['trilha_id'], (int) $modulo['ordem']]);
    $modulosAvaliaveis = $stmt->fetchAll();
}

$temProgresso = $atividadeId > 0 && atividadeTemProgresso($pdo, $atividadeId);
$estatisticas = $atividadeId > 0 ? estatisticasDaAtividade($pdo, $atividadeId) : null;

$tituloPagina    = $atividadeId > 0 ? 'Editar atividade' : ($ehProva ? 'Nova questão' : 'Nova atividade');
$subtituloPagina = $modulo['titulo'] . ' · ' . $modulo['trilha_titulo'];

require_once __DIR__ . '/includes/header.php';
?>

<?= trilhaDeNavegacaoAdmin([
    ['titulo' => $modulo['trilha_titulo'], 'url' => 'admin_trilha.php?id=' . (int) $modulo['trilha_id']],
    ['titulo' => $modulo['titulo'],        'url' => 'admin_modulo.php?id=' . $moduloId],
    ['titulo' => $tituloPagina],
]) ?>

<?php if (!empty($erros)): ?>
    <div class="aviso aviso-erro">
        <strong>Corrija antes de salvar:</strong>
        <ul class="lista-erros">
            <?php foreach ($erros as $erro): ?>
                <li><?= e($erro) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php $ehHtml = ($modulo['linguagem'] ?? 'javascript') === 'html'; ?>
<form method="POST" id="formAtividade"
      data-ehprova="<?= $ehProva ? '1' : '0' ?>"
      data-linguagem="<?= $ehHtml ? 'html' : 'javascript' ?>">
    <?= campoCsrf() ?>
    <input type="hidden" name="atividade_id" value="<?= $atividadeId ?>">
    <input type="hidden" name="modulo_id" value="<?= $moduloId ?>">
    <input type="hidden" name="gabarito_conferido" id="gabaritoConferido" value="">

<div class="colunas">

    <div class="coluna-principal">

        <section class="painel">
            <h3 class="painel-titulo"><?= icone('livro') ?> Enunciado</h3>

            <div class="campo">
                <label for="titulo">Título</label>
                <input type="text" id="titulo" name="titulo" required maxlength="100"
                       value="<?= e($valores['titulo']) ?>"
                       placeholder="<?= $ehProva ? 'Ex: Questão 1' : 'Ex: Soma de Dois Números' ?>">
            </div>

            <div class="campo">
                <label for="enunciado">O que o aluno precisa fazer</label>
                <textarea id="enunciado" name="enunciado" rows="4" required
                          placeholder="Descreva a tarefa com clareza. Se houver formato de resposta esperado, diga qual."><?= e($valores['enunciado']) ?></textarea>
            </div>

            <?php if (!$ehProva): ?>
                <div class="campo">
                    <label for="tipo">Tipo de atividade</label>
                    <select id="tipo" name="tipo">
                        <?php foreach (tiposDeAtividade() as $t): ?>
                            <option value="<?= e($t) ?>" <?= $valores['tipo'] === $t ? 'selected' : '' ?>>
                                <?= e(rotuloTipoAtividade($t)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </section>

        <!-- ---------------- QUIZ ---------------- -->
        <section class="painel" id="blocoQuiz">
            <h3 class="painel-titulo"><?= icone('alvo') ?> Alternativas</h3>
            <p class="aviso-suave">
                Marque a correta no botão à esquerda. Alternativas em branco são ignoradas.
            </p>

            <div class="alternativas-admin">
                <?php foreach ($listaAlternativas as $i => $alt): ?>
                    <label class="linha-alternativa">
                        <input type="radio" name="alt_correta" value="<?= $i ?>" <?= $alt['correta'] ? 'checked' : '' ?>>
                        <span class="alternativa-letra"><?= chr(65 + $i) ?></span>
                        <input type="text" name="alt_texto[]" maxlength="255"
                               value="<?= e($alt['texto']) ?>" placeholder="Texto da alternativa">
                    </label>
                <?php endforeach; ?>
            </div>

            <?php if ($ehProva): ?>
                <div class="campo" style="margin-top: 18px;">
                    <label for="modulo_referencia_id">Qual módulo esta questão avalia</label>
                    <select id="modulo_referencia_id" name="modulo_referencia_id" required>
                        <option value="">Escolha…</option>
                        <?php foreach ($modulosAvaliaveis as $m): ?>
                            <option value="<?= (int) $m['id'] ?>" <?= (int) $valores['referencia'] === (int) $m['id'] ? 'selected' : '' ?>>
                                <?= e($m['titulo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="ajuda-campo">
                        É daqui que sai a lista de "revise estes módulos" quando o aluno reprova.
                        <?php if (empty($modulosAvaliaveis)): ?>
                            <strong>Não há módulo de aula antes desta prova</strong> — crie os módulos
                            de aula primeiro, ou mova a prova para o fim da trilha.
                        <?php endif; ?>
                    </small>
                </div>
            <?php endif; ?>
        </section>

        <!-- ---------------- CÓDIGO ---------------- -->
        <section class="painel" id="blocoCodigo">
            <h3 class="painel-titulo"><?= icone('codigo') ?> Código</h3>

            <div class="campo">
                <label for="codigo_inicial">Código inicial (o que já aparece no editor do aluno)</label>
                <textarea id="codigo_inicial" name="codigo_inicial" rows="6" class="editor-area" spellcheck="false"
                          placeholder="function somar(a, b) {&#10;  // escreva seu código aqui&#10;}"><?= e($valores['codigo_inicial']) ?></textarea>
            </div>

            <div class="campo">
                <label for="solucao_exemplo">Solução de referência</label>
                <textarea id="solucao_exemplo" name="solucao_exemplo" rows="6" class="editor-area" spellcheck="false"
                          placeholder="function somar(a, b) {&#10;  return a + b;&#10;}"><?= e($valores['solucao_exemplo']) ?></textarea>
                <small class="ajuda-campo">
                    É ela que a conferência executa contra os casos de teste. O aluno também
                    pode vê-la em "Mostrar exemplo de solução".
                </small>
            </div>
        </section>

        <section class="painel" id="blocoCasos">
            <h3 class="painel-titulo"><?= icone('prova') ?> Casos de teste</h3>
            <p class="aviso-suave">
                <?php if ($ehHtml): ?>
                <strong>Entrada</strong> é uma verificação sobre a página do aluno:
                <code>existe("h1")</code>, <code>contar("li")</code>, <code>texto("h1")</code>,
                <code>atributo("img", "alt")</code> ou <code>estilo("h1", "color")</code>
                (valor calculado pelo navegador, ex.: <code>rgb(255, 107, 0)</code>, <code>24px</code>).
                <?php else: ?>
                <strong>Entrada</strong> é a chamada que será avaliada (ex.: <code>somar(10, 5)</code>).
                Deixe em branco se o exercício apenas imprime com <code>console.log</code>.
                <?php endif; ?>
                <strong>Caso oculto</strong> não aparece para o aluno — é o que impede de passar
                chumbando a resposta.
            </p>

            <div id="listaCasos">
                <?php foreach ($listaCasos as $i => $caso): ?>
                    <div class="caso-admin">
                        <input type="text" name="caso_descricao[]" placeholder="Descrição (opcional)"
                               value="<?= e($caso['descricao']) ?>" class="caso-admin-descricao">
                        <input type="text" name="caso_entrada[]" placeholder="<?= $ehHtml ? 'Verificação — texto(&quot;h1&quot;)' : 'Entrada — somar(10, 5)' ?>"
                               value="<?= e($caso['entrada']) ?>" spellcheck="false">
                        <input type="text" name="caso_saida[]" placeholder="Saída esperada — 15"
                               value="<?= e($caso['saida']) ?>" spellcheck="false">
                        <label class="caso-admin-oculto" title="Não aparece para o aluno">
                            <input type="checkbox" name="caso_oculto[<?= $i ?>]" value="1" <?= $caso['oculto'] ? 'checked' : '' ?>>
                            <span>oculto</span>
                        </label>
                        <button type="button" class="botao botao-secundario botao-icone remover-caso"
                                title="Remover este caso">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" id="btnAddCaso" class="botao botao-secundario botao-pequeno">
                + Adicionar caso
            </button>

            <hr class="menu-divisor" style="margin: 20px 0;">

            <button type="button" id="btnConferir" class="botao botao-primario botao-bloco">
                <?= icone('play', 16) ?> Conferir gabarito
            </button>

            <div id="resultadoConferencia" class="aviso" role="status" aria-live="polite"></div>
            <div id="detalheConferencia" class="resultados" hidden></div>
        </section>

    </div>

    <aside class="coluna-lateral">

        <section class="painel">
            <h3 class="painel-titulo"><?= icone('admin') ?> Ajustes</h3>

            <div class="campo">
                <label for="dificuldade">Dificuldade</label>
                <select id="dificuldade" name="dificuldade">
                    <?php foreach (dificuldades() as $codigo => $rotulo): ?>
                        <option value="<?= e($codigo) ?>" <?= $valores['dificuldade'] === $codigo ? 'selected' : '' ?>>
                            <?= e($rotulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label for="xp_recompensa">XP ao concluir</label>
                <input type="number" id="xp_recompensa" name="xp_recompensa" min="0" max="10000" step="10"
                       value="<?= (int) $valores['xp_recompensa'] ?>">
            </div>

            <div class="campo" id="campoDica">
                <label for="dica">Dica</label>
                <textarea id="dica" name="dica" rows="3"
                          placeholder="Uma pista que ajude sem entregar a resposta."><?= e($valores['dica']) ?></textarea>
                <small class="ajuda-campo">
                    Usar a dica é registrado e conta contra a conquista "Por Conta Própria".
                </small>
            </div>

            <button type="submit" class="botao botao-primario botao-bloco">Salvar atividade</button>
            <a href="<?= e($volta) ?>" class="botao botao-secundario botao-bloco" style="margin-top: 10px;">Cancelar</a>
        </section>

        <?php if ($estatisticas !== null): ?>
            <?php // ADM-48: como os alunos estão indo nesta atividade. ?>
            <section class="painel" id="estatisticasAtividade">
                <h3 class="painel-titulo"><?= icone('ranking') ?> Desempenho dos alunos</h3>
                <?php if ($estatisticas['tentativas'] === 0): ?>
                    <p class="aviso-suave">Ninguém tentou esta atividade ainda.</p>
                <?php else: ?>
                    <div class="estatisticas-atividade">
                        <div><strong><?= $estatisticas['alunos'] ?></strong><small>alunos tentaram</small></div>
                        <div><strong><?= $estatisticas['tentativas'] ?></strong><small>tentativas no total</small></div>
                        <div><strong class="<?= $estatisticas['taxa_acerto'] < 40 ? 'taxa-alta' : ($estatisticas['taxa_acerto'] < 70 ? 'taxa-media' : '') ?>"><?= $estatisticas['taxa_acerto'] ?>%</strong><small>das tentativas certas</small></div>
                        <div><strong><?= $estatisticas['taxa_primeira'] ?>%</strong><small>acertaram de primeira</small></div>
                        <div><strong><?= $estatisticas['concluiram'] ?></strong><small>concluíram</small></div>
                        <div><strong><?= $estatisticas['sem_concluir'] ?></strong><small>tentaram e não concluíram</small></div>
                    </div>
                    <p class="rodape-painel">Dica aberta <?= plural($estatisticas['dicas'], 'vez', 'vezes') ?>.
                        Taxa de acerto baixa costuma indicar enunciado confuso ou caso de teste duro demais.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($atividadeId > 0): ?>
            <section class="painel">
                <h3 class="painel-titulo"><?= icone('cadeado') ?> Apagar</h3>
                <?php if ($temProgresso): ?>
                    <p class="aviso-suave">
                        Esta atividade <strong>não pode ser apagada</strong>: alunos já a concluíram,
                        e apagá-la levaria junto o progresso e o XP deles.
                    </p>
                <?php else: ?>
                    <p class="aviso-suave">Ninguém concluiu esta atividade ainda, então dá para apagá-la.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

    </aside>

</div>
</form>

<?php if ($atividadeId > 0 && !$temProgresso): ?>
    <form method="POST" onsubmit="return confirm('Apagar esta atividade? Não dá para desfazer.');"
          style="max-width: 320px;">
        <?= campoCsrf() ?>
        <input type="hidden" name="acao" value="apagar">
        <input type="hidden" name="atividade_id" value="<?= $atividadeId ?>">
        <input type="hidden" name="modulo_id" value="<?= $moduloId ?>">
        <button type="submit" class="botao botao-secundario botao-pequeno">Apagar atividade</button>
    </form>
<?php endif; ?>

<?php if ($ehHtml): ?>
    <script src="<?= asset('js/executor-html.js') ?>"></script>
<?php endif; ?>
<script src="<?= asset('js/admin-atividade.js') ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
