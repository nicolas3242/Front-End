<?php
/**
 * includes/conquistas.php — desbloqueio das conquistas.
 * -----------------------------------------------------------------------------
 * O catálogo de conquistas existia no banco desde o início, mas nada as
 * desbloqueava: o perfil mostrava seis cadeados decorativos. Isso contradiz a
 * própria tese do projeto, que é gamificação — daí este arquivo.
 *
 * Três decisões que valem entender antes de mexer aqui:
 *
 * 1. TODA condição é avaliada no SERVIDOR, consultando o banco. Nenhuma
 *    conquista depende de algo que o navegador informe. É a mesma regra do XP.
 *
 * 2. Cada conquista é concedida NO MÁXIMO UMA VEZ. Quem garante isso não é uma
 *    checagem em PHP (que perderia numa corrida entre duas requisições), e sim
 *    a chave única `uq_conquista_usuario` no banco: o INSERT IGNORE simplesmente
 *    não insere a segunda vez, e `rowCount()` conta quantas linhas entraram de
 *    verdade. Mesmo padrão do anti-farm de XP em `concluirAtividade()`.
 *
 * 3. As condições são recalculadas do zero a cada verificação, em vez de
 *    incrementar contadores. É mais lento e é de propósito: um contador
 *    dessincronizado do banco é um bug silencioso que ninguém descobre.
 */

/**
 * As regras, por código de conquista.
 *
 * Cada regra recebe (PDO, id do aluno) e devolve true quando a conquista está
 * merecida. O código é a chave estável: o título pode mudar sem quebrar nada.
 *
 * @return array<string,callable(PDO,int):bool>
 */
function regrasDeConquista(): array
{
    return [

        // "Complete sua primeira aula" — um módulo de aula inteiro concluído.
        // Um módulo conta como concluído quando todas as suas atividades foram
        // feitas OU quando está marcado em `modulos_concluidos` (módulo que é só
        // teoria, sem atividade, entra por aí).
        'primeira_aula' => function (PDO $pdo, int $alunoId): bool {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM (
                    SELECT m.id
                    FROM modulos m
                    JOIN atividades a ON a.modulo_id = m.id
                    LEFT JOIN atividades_concluidas ac
                           ON ac.atividade_id = a.id AND ac.aluno_id = ?
                    WHERE m.tipo = 'aula'
                    GROUP BY m.id
                    HAVING COUNT(a.id) > 0 AND COUNT(a.id) = COUNT(ac.id)
                 ) AS completos"
            );
            $stmt->execute([$alunoId]);

            if ((int) $stmt->fetchColumn() > 0) {
                return true;
            }

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM modulos_concluidos mc
                  JOIN modulos m ON m.id = mc.modulo_id
                 WHERE mc.aluno_id = ? AND m.tipo = 'aula'"
            );
            $stmt->execute([$alunoId]);
            return (int) $stmt->fetchColumn() > 0;
        },

        // "Conclua seu primeiro desafio de código" — qualquer atividade que não
        // seja quiz, ou seja, que exija escrever código.
        'primeiro_desafio' => function (PDO $pdo, int $alunoId): bool {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                   FROM atividades_concluidas ac
                   JOIN atividades a ON a.id = ac.atividade_id
                  WHERE ac.aluno_id = ? AND a.tipo <> 'quiz'"
            );
            $stmt->execute([$alunoId]);
            return (int) $stmt->fetchColumn() > 0;
        },

        // "Mantenha 7 dias seguidos de estudo" — a ofensiva é mantida por
        // atualizarOfensiva(), chamada no login.
        'sequencia_7' => function (PDO $pdo, int $alunoId): bool {
            $stmt = $pdo->prepare("SELECT sequencia_dias FROM usuarios WHERE id = ?");
            $stmt->execute([$alunoId]);
            return (int) $stmt->fetchColumn() >= 7;
        },

        // "Inicie 3 trilhas diferentes" — pelo menos uma atividade concluída em
        // cada uma de três trilhas.
        'explorador' => function (PDO $pdo, int $alunoId): bool {
            $stmt = $pdo->prepare(
                "SELECT COUNT(DISTINCT m.trilha_id)
                   FROM atividades_concluidas ac
                   JOIN atividades a ON a.id = ac.atividade_id
                   JOIN modulos m    ON m.id = a.modulo_id
                  WHERE ac.aluno_id = ?"
            );
            $stmt->execute([$alunoId]);
            return (int) $stmt->fetchColumn() >= 3;
        },

        // "Conclua uma trilha inteira" — a mesma regra da tela de Trilhas
        // (trilhaConcluida: aulas feitas e prova aprovada), para a conquista
        // nunca discordar do que o aluno vê.
        'trilha_completa' => function (PDO $pdo, int $alunoId): bool {
            $ids = $pdo->query("SELECT id FROM trilhas WHERE publicada = 1")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $trilhaId) {
                if (trilhaConcluida(estadoDosModulos($pdo, (int) $trilhaId, $alunoId))) {
                    return true;
                }
            }
            return false;
        },

        // "Acerte 5 desafios sem usar dica" — 5 atividades de código concluídas
        // sem registro em `dicas_vistas`.
        //
        // Quem registra é api/dica.php, no servidor, na hora de entregar o texto
        // da dica. Se isso viesse num campo enviado pelo navegador, o aluno
        // editaria no DevTools e a conquista não valeria nada.
        'sem_dica' => function (PDO $pdo, int $alunoId): bool {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                   FROM atividades_concluidas ac
                   JOIN atividades a ON a.id = ac.atividade_id
                   LEFT JOIN dicas_vistas dv
                          ON dv.atividade_id = a.id AND dv.aluno_id = ac.aluno_id
                  WHERE ac.aluno_id = ? AND a.tipo <> 'quiz' AND dv.id IS NULL"
            );
            $stmt->execute([$alunoId]);
            return (int) $stmt->fetchColumn() >= 5;
        },
    ];
}

/**
 * v10 — tipos de regra que o ADMIN pode usar ao criar uma conquista pela tela.
 *
 * É um catálogo FECHADO, de propósito: o admin escolhe um tipo e preenche um
 * número (e às vezes um alvo). Deixar escrever uma condição livre (SQL, PHP)
 * seria injeção de código pela própria tela de administração.
 *
 * Cada tipo: [rótulo com "N", o que o alvo é (null = sem alvo), valor mínimo, valor máximo].
 *
 * @return array<string,array{0:string,1:?string,2:int,3:int}>
 */
function tiposDeRegra(): array
{
    return [
        'atividades' => ['Concluir N atividades (qualquer tipo)', null, 1, 10000],
        'desafios'   => ['Concluir N desafios de código', null, 1, 10000],
        'xp'         => ['Chegar a N de XP', null, 1, 1000000],
        'sequencia'  => ['Recorde de N dias seguidos de estudo', null, 1, 365],
        'trilhas'    => ['Concluir N trilhas', null, 1, 100],
        'sem_dica'   => ['Concluir N desafios de código sem abrir a dica', null, 1, 10000],
        'trilha'     => ['Concluir a trilha escolhida', 'trilha', 1, 1],
        'prova'      => ['Ser aprovado na prova escolhida com nota de pelo menos N%', 'prova', 1, 100],
    ];
}

/** Frase da regra para mostrar na Administração ("Concluir 10 atividades"). */
function descreverRegra(array $conquista, array $nomesDeAlvo = []): string
{
    $tipo = $conquista['regra_tipo'] ?? 'fixa';
    if ($tipo === 'fixa' || !isset(tiposDeRegra()[$tipo])) {
        return 'Regra original do sistema (escrita no código)';
    }
    $frase = str_replace('N', (string) (int) $conquista['regra_valor'], tiposDeRegra()[$tipo][0]);
    if (tiposDeRegra()[$tipo][1] !== null) {
        $frase .= ': ' . ($nomesDeAlvo[(int) $conquista['regra_alvo']] ?? 'alvo #' . (int) $conquista['regra_alvo']);
    }
    return $frase;
}

/**
 * Avalia uma regra do catálogo para um aluno. Tudo calculado do banco, como as
 * regras fixas; nada vem do navegador.
 */
function regraParametrizadaCumprida(PDO $pdo, int $alunoId, string $tipo, int $valor, ?int $alvo): bool
{
    $contar = static function (string $sql, array $params) use ($pdo): int {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    };

    return match ($tipo) {
        'atividades' => $contar("SELECT COUNT(*) FROM atividades_concluidas WHERE aluno_id = ?", [$alunoId]) >= $valor,
        'desafios'   => $contar("SELECT COUNT(*) FROM atividades_concluidas ac JOIN atividades a ON a.id = ac.atividade_id
                                  JOIN modulos m ON m.id = a.modulo_id
                                 WHERE ac.aluno_id = ? AND a.tipo <> 'quiz' AND m.tipo = 'aula'", [$alunoId]) >= $valor,
        'xp'         => $contar("SELECT xp FROM usuarios WHERE id = ?", [$alunoId]) >= $valor,
        'sequencia'  => $contar("SELECT GREATEST(melhor_sequencia, sequencia_dias) FROM usuarios WHERE id = ?", [$alunoId]) >= $valor,
        'sem_dica'   => $contar("SELECT COUNT(*) FROM atividades_concluidas ac JOIN atividades a ON a.id = ac.atividade_id
                                  LEFT JOIN dicas_vistas dv ON dv.atividade_id = ac.atividade_id AND dv.aluno_id = ac.aluno_id
                                 WHERE ac.aluno_id = ? AND a.tipo <> 'quiz' AND dv.id IS NULL", [$alunoId]) >= $valor,
        'trilhas'    => count(array_filter(
                            $pdo->query("SELECT id FROM trilhas WHERE publicada = 1")->fetchAll(PDO::FETCH_COLUMN),
                            static fn($id) => trilhaConcluida(estadoDosModulos($pdo, (int) $id, $alunoId))
                        )) >= $valor,
        'trilha'     => $alvo !== null && trilhaConcluida(estadoDosModulos($pdo, $alvo, $alunoId)),
        'prova'      => $alvo !== null && $contar("SELECT COUNT(*) FROM tentativas_prova
                                 WHERE aluno_id = ? AND modulo_id = ? AND aprovado = 1 AND percentual >= ?",
                                [$alunoId, $alvo, $valor]) > 0,
        default      => false,
    };
}

/**
 * Avalia todas as conquistas pendentes e concede as merecidas.
 *
 * @return array<int,array> as conquistas desbloqueadas AGORA (vazio no caso
 *                          comum). Cada item traz titulo, descricao, icone e
 *                          raridade, prontos para exibir.
 */
function verificarConquistas(PDO $pdo, int $alunoId): array
{
    $regras = regrasDeConquista();

    // Só as que ainda faltam: não adianta reavaliar o que já foi concedido.
    $stmt = $pdo->prepare(
        "SELECT c.id, c.codigo, c.titulo, c.descricao, c.icone, c.raridade,
                c.regra_tipo, c.regra_valor, c.regra_alvo
           FROM conquistas c
          WHERE c.ativa = 1          -- v10: desativada pelo admin não é mais concedida
            AND NOT EXISTS (
                SELECT 1 FROM conquistas_usuarios cu
                 WHERE cu.conquista_id = c.id AND cu.usuario_id = ?
          )"
    );
    $stmt->execute([$alunoId]);
    $pendentes = $stmt->fetchAll();

    $novas = [];

    foreach ($pendentes as $conquista) {
        if (($conquista['regra_tipo'] ?? 'fixa') !== 'fixa') {
            // v10: criada pelo admin — regra do catálogo fechado (tiposDeRegra()).
            $merecida = regraParametrizadaCumprida($pdo, $alunoId, (string) $conquista['regra_tipo'],
                (int) $conquista['regra_valor'], $conquista['regra_alvo'] !== null ? (int) $conquista['regra_alvo'] : null);
        } else {
            // Conquista 'fixa' sem regra no código: ignorada de propósito, em vez
            // de quebrar a tela.
            $regra = $regras[$conquista['codigo']] ?? null;
            $merecida = $regra !== null && $regra($pdo, $alunoId);
        }
        if (!$merecida) {
            continue;
        }

        $insercao = $pdo->prepare(
            "INSERT IGNORE INTO conquistas_usuarios (usuario_id, conquista_id) VALUES (?, ?)"
        );
        $insercao->execute([$alunoId, (int) $conquista['id']]);

        // rowCount() === 1 significa que a linha entrou AGORA. Se outra
        // requisição concedeu a mesma conquista no mesmo instante, a chave única
        // barra e aqui vem 0 — e o aluno não vê o aviso duas vezes.
        if ($insercao->rowCount() === 1) {
            $novas[] = $conquista;
        }
    }

    return $novas;
}

/**
 * Guarda conquistas para serem exibidas no próximo carregamento de página.
 *
 * Usado onde a conclusão acontece num POST que redireciona (a prova) ou no
 * login. Na tela de desafio não se usa isto: lá a resposta da API já devolve as
 * conquistas e o JavaScript mostra o aviso na hora, sem recarregar.
 */
function guardarConquistasNaSessao(array $novas): void
{
    if (empty($novas)) {
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $atuais = $_SESSION['conquistas_novas'] ?? [];
    $_SESSION['conquistas_novas'] = array_merge($atuais, $novas);
}

/** Lê e limpa as conquistas pendentes de exibição. */
function consumirConquistasDaSessao(): array
{
    $novas = $_SESSION['conquistas_novas'] ?? [];
    unset($_SESSION['conquistas_novas']);

    return is_array($novas) ? $novas : [];
}
