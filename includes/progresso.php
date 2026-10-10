<?php
/**
 * includes/progresso.php — consultas de progresso usadas por mais de uma tela.
 * -----------------------------------------------------------------------------
 * Trilhas, Início e Desafios mostram o mesmo progresso de ângulos diferentes.
 * Se cada tela calculasse do seu jeito, bastaria uma regra mudar (por exemplo,
 * o que conta como "trilha concluída") para as três telas passarem a discordar
 * entre si na frente da banca. Por isso o cálculo mora aqui, uma vez só.
 *
 * Nada aqui grava no banco: são só leituras.
 */

require_once __DIR__ . '/auth.php';

/**
 * Estado de todas as trilhas publicadas para um aluno.
 *
 * @return array<int,array> cada item: trilha, total, feitas, percentual,
 *                          modulos (estadoDosModulos), proximo, concluida
 */
function dadosDasTrilhas(PDO $pdo, int $alunoId): array
{
    // O admin vê também as trilhas em RASCUNHO (marcadas como tal na tela): é
    // como ele confere o conteúdo do jeito que o aluno vai ver, antes de
    // publicar. Trilha arquivada não entra para ninguém — arquivar é
    // justamente tirá-la de circulação sem apagar o progresso de quem fez.
    $ehAdmin = ehOProprioAdmin($alunoId);
    $filtro  = $ehAdmin ? 't.arquivada = 0' : 't.publicada = 1';

    $stmt = $pdo->prepare(
        "SELECT t.id, t.titulo, t.descricao, t.nivel_dificuldade, t.icone,
                t.linguagem, t.subtitulo, t.destaque, t.publicada,
                (SELECT COUNT(*)
                   FROM atividades a
                   JOIN modulos m ON m.id = a.modulo_id
                  WHERE m.trilha_id = t.id) AS total_atividades,
                (SELECT COUNT(*)
                   FROM atividades_concluidas ac
                   JOIN atividades a ON a.id = ac.atividade_id
                   JOIN modulos m    ON m.id = a.modulo_id
                  WHERE m.trilha_id = t.id AND ac.aluno_id = ?) AS atividades_feitas
         FROM trilhas t
         WHERE $filtro
         ORDER BY t.ordem, t.id"
    );
    $stmt->execute([$alunoId]);

    $dados = [];
    foreach ($stmt->fetchAll() as $trilha) {
        $total  = (int) $trilha['total_atividades'];
        $feitas = (int) $trilha['atividades_feitas'];
        $pendentes = requisitosPendentes($pdo, (int) $trilha['id'], $alunoId);
        $modulos   = estadoDosModulos($pdo, (int) $trilha['id'], $alunoId);
        $concluida = trilhaConcluida($modulos);

        // Concluída = 100%, mesmo que a prova tenha sido aprovada com 80%.
        // Não concluída nunca mostra 100% (no máximo 99%), para o número não
        // contradizer o botão.
        $percentual = $total > 0 ? (int) round($feitas / $total * 100) : 0;
        $percentual = $concluida ? 100 : min(99, $percentual);

        $dados[] = [
            'trilha'     => $trilha,
            'total'      => $total,
            'feitas'     => $feitas,
            'percentual' => $percentual,
            'modulos'    => $modulos,
            'proximo'    => proximoPassoDaTrilha($pdo, (int) $trilha['id'], $alunoId),
            'concluida'  => $concluida,
            'bloqueada'  => $pendentes !== [],
            'rascunho'   => (int) $trilha['publicada'] !== 1,
            'requisitos_pendentes' => $pendentes,
        ];
    }

    return $dados;
}

/** URL do próximo passo (atividade ou prova), ou null. */
function urlDoPasso(?array $passo): ?string
{
    if ($passo === null) {
        return null;
    }

    return $passo['tipo'] === 'prova'
        ? 'prova.php?modulo=' . (int) $passo['id']
        : 'desafio.php?id=' . (int) $passo['id'];
}

/**
 * Módulo em que o aluno está agora: o primeiro liberado e não concluído.
 *
 * @return array{posicao:int,total:int,modulo:?array} posição começa em 1;
 *         se a trilha estiver toda concluída, posição = total e modulo = null.
 */
function moduloAtualDaTrilha(array $modulos): array
{
    $total = count($modulos);

    foreach ($modulos as $i => $modulo) {
        if ($modulo['liberado'] && !$modulo['concluido']) {
            return ['posicao' => $i + 1, 'total' => $total, 'modulo' => $modulo];
        }
    }

    return ['posicao' => $total, 'total' => $total, 'modulo' => null];
}

/**
 * Trilha que o bloco "Continue aprendendo" deve mostrar.
 *
 * Ordem de preferência:
 *   1. a trilha em que o aluno mexeu por último, se ainda não terminou;
 *   2. a primeira trilha não concluída (aluno novo cai aqui);
 *   3. null — concluiu tudo.
 *
 * "Mexeu por último" usa a data de conclusão, e não a ordem das trilhas:
 * quem está na trilha 3 não quer ser mandado de volta para a 1.
 */
function trilhaParaContinuar(PDO $pdo, int $alunoId, array $dados): ?array
{
    $stmt = $pdo->prepare(
        "SELECT m.trilha_id
         FROM atividades_concluidas ac
         JOIN atividades a ON a.id = ac.atividade_id
         JOIN modulos m    ON m.id = a.modulo_id
         WHERE ac.aluno_id = ?
         ORDER BY ac.concluido_em DESC, ac.id DESC
         LIMIT 1"
    );
    $stmt->execute([$alunoId]);
    $ultima = (int) $stmt->fetchColumn();

    foreach ($dados as $item) {
        if ((int) $item['trilha']['id'] === $ultima && !$item['concluida'] && !$item['bloqueada']) {
            return $item;
        }
    }

    foreach ($dados as $item) {
        if (!$item['concluida'] && !$item['bloqueada'] && $item['total'] > 0) {
            return $item;
        }
    }

    return null;
}

/**
 * Todos os desafios de código (quiz e questão de prova ficam de fora) com o
 * estado para o aluno: 'concluido', 'disponivel' ou 'bloqueado'.
 *
 * O bloqueio vem de estadoDosModulos(): a lista de desafios NÃO é um atalho
 * para pular a ordem da trilha. Um desafio de módulo bloqueado aparece, mas
 * não abre — e desafio.php também barra o acesso direto pela URL.
 */
function desafiosDoAluno(PDO $pdo, int $alunoId, array $dados): array
{
    // Mesma regra da lista de trilhas: o admin também vê o que está em
    // rascunho, porque para ele a tela serve para conferir o conteúdo.
    $filtro = ehOProprioAdmin($alunoId) ? 't.arquivada = 0' : 't.publicada = 1';

    $liberados = [];
    foreach ($dados as $item) {
        foreach ($item['modulos'] as $modulo) {
            if ($modulo['liberado']) {
                $liberados[(int) $modulo['id']] = true;
            }
        }
    }

    $stmt = $pdo->prepare(
        "SELECT a.id, a.titulo, a.tipo, a.dificuldade, a.xp_recompensa,
                m.id AS modulo_id, m.titulo AS modulo_titulo,
                t.id AS trilha_id, t.titulo AS trilha_titulo, t.icone AS trilha_icone,
                (ac.id IS NOT NULL) AS concluida
         FROM atividades a
         JOIN modulos m  ON m.id = a.modulo_id
         JOIN trilhas t  ON t.id = m.trilha_id
         LEFT JOIN atividades_concluidas ac
                ON ac.atividade_id = a.id AND ac.aluno_id = ?
         WHERE $filtro
           AND m.tipo = 'aula'
           AND a.tipo <> 'quiz'
         ORDER BY t.ordem, t.id, m.ordem, m.id, a.ordem, a.id"
    );
    $stmt->execute([$alunoId]);

    $desafios = [];
    // Dentro de cada módulo, a ordem é a deste aluno (includes/auth.php), a
    // mesma da tela de desafio — senão a lista de Desafios contaria uma
    // história e a trilha, outra.
    foreach (embaralharAtividades($stmt->fetchAll(), $alunoId) as $d) {
        if ((int) $d['concluida'] === 1) {
            $d['estado'] = 'concluido';
        } elseif (isset($liberados[(int) $d['modulo_id']])) {
            $d['estado'] = 'disponivel';
        } else {
            $d['estado'] = 'bloqueado';
        }
        $desafios[] = $d;
    }

    return $desafios;
}

/** Ícone de cada tipo de desafio, para as listas. */
function iconeDoTipo(string $tipo): string
{
    return [
        'completar'     => 'terminal',
        'corrigir_erro' => 'bug',
        'codigo_livre'  => 'codigo',
    ][$tipo] ?? 'codigo';
}

/** Rótulo curto de cada tipo de desafio. */
function rotuloDoTipo(string $tipo): string
{
    return [
        'completar'     => 'Completar o código',
        'corrigir_erro' => 'Corrigir o erro',
        'codigo_livre'  => 'Código livre',
    ][$tipo] ?? 'Desafio';
}

/**
 * Topo do ranking + a linha do próprio aluno.
 *
 * @return array{topo:array,eu:?array} `eu` só vem preenchido quando o aluno
 *         está FORA do topo (senão ele já aparece destacado na lista).
 */
function resumoDoRanking(PDO $pdo, int $usuarioId, int $quantos = 3): array
{
    $stmt = $pdo->prepare(
        "SELECT u.id, u.nome, u.xp, u.foto_avatar, m.slug AS moldura_slug
         FROM usuarios u
         LEFT JOIN itens_loja m ON m.id = u.moldura_id
         WHERE u.tipo = 'aluno'
         ORDER BY u.xp DESC, u.nome ASC
         LIMIT " . max(1, (int) $quantos)
    );
    $stmt->execute();
    $topo = $stmt->fetchAll();

    // Numera TODAS as linhas antes de procurar o aluno. (Antes o return saía
    // do laço no meio: se o aluno fosse o 1º, as linhas de baixo ficavam sem
    // 'posicao' e o Início mostrava "Warning: Undefined array key".)
    foreach ($topo as $i => $linha) {
        $topo[$i]['posicao'] = $i + 1;
    }
    foreach ($topo as $linha) {
        if ((int) $linha['id'] === $usuarioId) {
            return ['topo' => $topo, 'eu' => null];
        }
    }

    $stmt = $pdo->prepare(
        "SELECT u.id, u.nome, u.xp, u.foto_avatar, m.slug AS moldura_slug,
                (SELECT COUNT(*) + 1 FROM usuarios o
                  WHERE o.tipo = 'aluno' AND o.xp > u.xp) AS posicao
         FROM usuarios u
         LEFT JOIN itens_loja m ON m.id = u.moldura_id
         WHERE u.id = ? AND u.tipo = 'aluno'"
    );
    $stmt->execute([$usuarioId]);
    $eu = $stmt->fetch() ?: null;

    return ['topo' => $topo, 'eu' => $eu];
}

/**
 * Últimos acontecimentos do usuário, para o sino de notificações.
 *
 * Não existe tabela de notificações: os eventos JÁ estão no banco (conquista
 * desbloqueada, atividade concluída, prova, compra). Uma tabela à parte seria
 * uma cópia desses dados que precisaria ser mantida em dia em cada ponto do
 * código que gera um evento — e ficaria fora de sincronia no primeiro esquecido.
 *
 * @return array<int,array{tipo:string,texto:string,detalhe:string,quando:string}>
 */
function notificacoesRecentes(PDO $pdo, int $usuarioId, int $limite = 8): array
{
    $stmt = $pdo->prepare(
        "(SELECT 'conquista' AS tipo, c.titulo AS texto, c.descricao AS detalhe,
                 cu.desbloqueada_em AS quando
            FROM conquistas_usuarios cu
            JOIN conquistas c ON c.id = cu.conquista_id
           WHERE cu.usuario_id = ?)
         UNION ALL
         (SELECT 'atividade', a.titulo, CONCAT('+', ac.xp_concedido, ' XP'), ac.concluido_em
            FROM atividades_concluidas ac
            JOIN atividades a ON a.id = ac.atividade_id
           WHERE ac.aluno_id = ?)
         UNION ALL
         (SELECT IF(tp.aprovado = 1, 'prova_ok', 'prova_falhou'), m.titulo,
                 CONCAT('Nota ', tp.percentual, '%'), tp.realizada_em
            FROM tentativas_prova tp
            JOIN modulos m ON m.id = tp.modulo_id
           WHERE tp.aluno_id = ?)
         UNION ALL
         (SELECT 'compra', i.nome, CONCAT('-', co.preco_pago, ' moedas'), co.comprado_em
            FROM compras co
            JOIN itens_loja i ON i.id = co.item_id
           WHERE co.usuario_id = ?)
         ORDER BY quando DESC
         LIMIT " . max(1, (int) $limite)
    );
    $stmt->execute([$usuarioId, $usuarioId, $usuarioId, $usuarioId]);

    return $stmt->fetchAll();
}

/** "há 5 min", "há 2 h", "ontem", "há 3 dias" ou a data. */
function tempoRelativo(string $dataHora): string
{
    $quando = new DateTimeImmutable($dataHora);
    $segundos = time() - $quando->getTimestamp();

    if ($segundos < 60)     { return 'agora'; }
    if ($segundos < 3600)   { return 'há ' . intdiv($segundos, 60) . ' min'; }
    if ($segundos < 86400)  { return 'há ' . intdiv($segundos, 3600) . ' h'; }
    if ($segundos < 172800) { return 'ontem'; }
    if ($segundos < 604800) { return 'há ' . intdiv($segundos, 86400) . ' dias'; }

    return $quando->format('d/m/Y');
}


/**
 * Os três cartões de "Recomendadas para você" da tela de Trilhas.
 *
 *   proxima  → a próxima lição da trilha em andamento (módulo atual);
 *   pratica  → um desafio liberado, de preferência de OUTRA trilha;
 *   sugestao → o 1º módulo de uma trilha que o aluno ainda não começou.
 *
 * Cada um pode vir null (aluno que já fez tudo, por exemplo) — a tela só
 * mostra o que existir, em vez de inventar recomendação.
 */
function recomendacoes(PDO $pdo, int $alunoId, array $dados, ?array $continuar, array $desafios): array
{
    $rec = ['proxima' => null, 'pratica' => null, 'sugestao' => null];

    if ($continuar !== null) {
        $atual = moduloAtualDaTrilha($continuar['modulos']);
        if ($atual['modulo'] !== null) {
            $m = $atual['modulo'];
            $rec['proxima'] = [
                'titulo' => $m['titulo'],
                'meta'   => $continuar['trilha']['titulo'] . ' • Módulo ' . $atual['posicao'],
                'url'    => $m['tipo'] === 'prova'
                    ? 'prova.php?modulo=' . (int) $m['id']
                    : 'desafio.php?modulo=' . (int) $m['id'],
            ];
        }
    }

    $idContinuar = $continuar ? (int) $continuar['trilha']['id'] : 0;
    $escolhido = null;
    foreach ($desafios as $d) {
        if ($d['estado'] !== 'disponivel') { continue; }
        if ($escolhido === null) { $escolhido = $d; }
        if ((int) $d['trilha_id'] !== $idContinuar) { $escolhido = $d; break; }
    }
    if ($escolhido !== null) {
        $rec['pratica'] = [
            'titulo' => 'Desafio: ' . $escolhido['titulo'],
            'meta'   => $escolhido['trilha_titulo'] . ' • ' . (int) $escolhido['xp_recompensa'] . ' XP',
            'url'    => 'desafio.php?id=' . (int) $escolhido['id'],
        ];
    }

    foreach ($dados as $item) {
        if ($item['feitas'] > 0 || $item['bloqueada'] || empty($item['modulos'])
            || (int) $item['trilha']['id'] === $idContinuar) {
            continue;
        }
        $m = $item['modulos'][0];
        $rec['sugestao'] = [
            'titulo' => $m['titulo'],
            'meta'   => $item['trilha']['titulo'] . ' • Módulo 1',
            'url'    => 'desafio.php?modulo=' . (int) $m['id'],
            'icone'  => $item['trilha']['icone'],
        ];
        break;
    }

    return $rec;
}


/**
 * Números da tela de Perfil, com a variação dos últimos 7 dias.
 *
 * "Desafio" aqui é o mesmo de desafiosDoAluno(): exercício de código de módulo
 * de aula — quiz e questão de prova não contam. "Trilha concluída" é a mesma
 * regra de dadosDasTrilhas() (todas as atividades feitas).
 */
function estatisticasDoPerfil(PDO $pdo, int $alunoId, array $dados): array
{
    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN ac.concluido_em >= NOW() - INTERVAL 7 DAY THEN ac.xp_concedido END), 0) AS xp_semana,
            COUNT(CASE WHEN a.tipo <> 'quiz' AND m.tipo = 'aula' THEN 1 END)                                AS desafios,
            COUNT(CASE WHEN a.tipo <> 'quiz' AND m.tipo = 'aula'
                        AND ac.concluido_em >= NOW() - INTERVAL 7 DAY THEN 1 END)                            AS desafios_semana
         FROM atividades_concluidas ac
         JOIN atividades a ON a.id = ac.atividade_id
         JOIN modulos m    ON m.id = a.modulo_id
         WHERE ac.aluno_id = ?"
    );
    $stmt->execute([$alunoId]);
    $n = $stmt->fetch();

    // Trilha concluída "nesta semana" = a última atividade dela foi nos últimos 7 dias.
    $concluidas = array_filter($dados, static fn(array $d): bool => $d['concluida']);
    $semana = 0;
    if ($concluidas) {
        $ids = implode(',', array_map(static fn(array $d): int => (int) $d['trilha']['id'], $concluidas));
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM (
                SELECT m.trilha_id, MAX(ac.concluido_em) AS ultima
                FROM atividades_concluidas ac
                JOIN atividades a ON a.id = ac.atividade_id
                JOIN modulos m    ON m.id = a.modulo_id
                WHERE ac.aluno_id = ? AND m.trilha_id IN ($ids)
                GROUP BY m.trilha_id
             ) t WHERE t.ultima >= NOW() - INTERVAL 7 DAY"
        );
        $stmt->execute([$alunoId]);
        $semana = (int) $stmt->fetchColumn();
    }

    return [
        'xp_semana'       => (int) $n['xp_semana'],
        'desafios'        => (int) $n['desafios'],
        'desafios_semana' => (int) $n['desafios_semana'],
        'trilhas'         => count($concluidas),
        'trilhas_semana'  => $semana,
    ];
}

/**
 * Histórico do aluno, do mais novo para o mais antigo (Perfil e atividades.php).
 *
 * Mesmos eventos do sino, mais "Iniciou nova trilha" (a 1ª atividade feita em
 * cada trilha). Nada é gravado à parte: tudo é lido das tabelas que já existem.
 */
function historicoDoUsuario(PDO $pdo, int $usuarioId, int $limite = 20, int $deslocamento = 0): array
{
    $limite = max(1, min(100, $limite));
    $deslocamento = max(0, $deslocamento);

    $stmt = $pdo->prepare(
        "(SELECT 'atividade' AS tipo, a.titulo AS texto, CONCAT('+', ac.xp_concedido, ' XP') AS detalhe,
                 ac.concluido_em AS quando
            FROM atividades_concluidas ac JOIN atividades a ON a.id = ac.atividade_id
           WHERE ac.aluno_id = ?)
         UNION ALL
         (SELECT 'conquista', c.titulo, c.descricao, cu.desbloqueada_em
            FROM conquistas_usuarios cu JOIN conquistas c ON c.id = cu.conquista_id
           WHERE cu.usuario_id = ?)
         UNION ALL
         (SELECT IF(tp.aprovado = 1, 'prova_ok', 'prova_falhou'), m.titulo,
                 CONCAT('Nota ', tp.percentual, '%'), tp.realizada_em
            FROM tentativas_prova tp JOIN modulos m ON m.id = tp.modulo_id
           WHERE tp.aluno_id = ?)
         UNION ALL
         (SELECT 'compra', i.nome, CONCAT('-', co.preco_pago, ' moedas'), co.comprado_em
            FROM compras co JOIN itens_loja i ON i.id = co.item_id
           WHERE co.usuario_id = ?)
         UNION ALL
         (SELECT 'trilha', t.titulo, 'Primeira atividade da trilha', MIN(ac.concluido_em)
            FROM atividades_concluidas ac
            JOIN atividades a ON a.id = ac.atividade_id
            JOIN modulos m    ON m.id = a.modulo_id
            JOIN trilhas t    ON t.id = m.trilha_id
           WHERE ac.aluno_id = ?
           GROUP BY t.id, t.titulo)
         ORDER BY quando DESC
         LIMIT {$limite} OFFSET {$deslocamento}"
    );
    $stmt->execute([$usuarioId, $usuarioId, $usuarioId, $usuarioId, $usuarioId]);

    return $stmt->fetchAll();
}

/** Ícone, rótulo e cor de cada tipo de evento do histórico. */
function aparenciaDoEvento(string $tipo): array
{
    return [
        'atividade'    => ['check',   'Concluiu a atividade', 'verde'],
        'conquista'    => ['estrela', 'Desbloqueou conquista', 'roxo'],
        'prova_ok'     => ['prova',   'Aprovado na prova', 'verde'],
        'prova_falhou' => ['prova',   'Prova não aprovada', 'vermelho'],
        'compra'       => ['loja',    'Comprou na Loja', 'laranja'],
        'trilha'       => ['trilhas', 'Iniciou nova trilha', 'azul'],
    ][$tipo] ?? ['sino', 'Novidade', 'laranja'];
}

