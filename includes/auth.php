<?php
/**
 * includes/auth.php
 * -----------------------------------------------------------------------------
 * Sessão, autenticação e regras de gamificação do DevFox.
 *
 * Este arquivo é a ÚNICA fonte dessas funções. Antes da versão atual, várias
 * páginas chamavam `usuarioLogado()` e `exibeMensagem()` sem que elas
 * existissem em lugar nenhum — o que derrubava a página com erro fatal.
 *
 * Como usar em qualquer página:
 *     require_once __DIR__ . '/config/conexao.php';
 *     require_once __DIR__ . '/includes/auth.php';
 *     exigirLogin();
 */

require_once __DIR__ . '/ambiente.php';   // fuso horário e cookie de sessão seguro
iniciarSessao();

/** Quantidade de XP necessária para subir cada nível. */
const XP_POR_NIVEL = 500;

/**
 * As atividades de um módulo aparecem em ordem sorteada para cada aluno?
 *
 * Ligado (true), dois alunos no mesmo módulo recebem os exercícios em ordens
 * diferentes, o que atrapalha a cópia direta ("faz o 3º que a resposta é X").
 * Desligado (false), vale a ordem que o admin definiu.
 *
 * ⚠ Duas coisas que valem saber antes de deixar ligado:
 *   - os exercícios de um módulo foram escritos em ordem crescente de
 *     dificuldade; sorteados, o aluno pode pegar o mais difícil primeiro;
 *   - a teoria do módulo é a mesma para todos, então nenhum exercício fica
 *     sem explicação — o que muda é só a ordem entre eles.
 * Se na hora da apresentação a ordem sorteada atrapalhar, basta trocar para
 * false: nada mais no sistema precisa mudar.
 */
const ATIVIDADES_EMBARALHADAS = true;


/* -----------------------------------------------------------------------------
 * Autenticação
 * -------------------------------------------------------------------------- */

/** Há um usuário autenticado nesta sessão? */
function usuarioLogado(): bool
{
    return isset($_SESSION['usuario_id']);
}

/**
 * O usuário autenticado administra o conteúdo?
 *
 * O DevFox é uma plataforma de conteúdo (como Duolingo e Alura), não um
 * sistema de sala de aula: quem cadastra trilhas e atividades é a equipe do
 * produto. Por isso o papel se chama `admin`, e não `professor` — não existe
 * professor que se cadastra na plataforma para criar conteúdo próprio.
 */
function usuarioEhAdmin(): bool
{
    return usuarioLogado() && ($_SESSION['usuario_tipo'] ?? '') === 'admin';
}

/**
 * É o próprio admin, logado, olhando a conta dele?
 *
 * Existe para separar duas situações que a checagem solta de `usuarioEhAdmin()`
 * confundia: o admin navegando como aluno (aí tudo fica liberado) e o admin
 * lendo a ficha de OUTRA pessoa (aí valem as regras daquela pessoa).
 */
function ehOProprioAdmin(int $usuarioId): bool
{
    return usuarioEhAdmin() && usuarioAtualId() === $usuarioId;
}

/* -----------------------------------------------------------------------------
 * Ordem sorteada, porém ESTÁVEL
 *
 * A ordem é sorteada entre alunos, mas sempre a mesma para o mesmo aluno. Isso
 * é o que torna o sorteio utilizável: se a ordem mudasse a cada F5, a barra
 * "Lição 3 de 16" apontaria uma atividade a cada carregamento e o botão
 * "Próxima atividade" levaria a lugares diferentes — pareceria defeito.
 *
 * O "sorteio" é o hash de (aluno, atividade), usado como chave de ordenação:
 * é imprevisível para quem olha a tela, não guarda nada no banco e dá sempre o
 * mesmo resultado. Não é aleatoriedade criptográfica, e nem precisa ser —
 * ninguém ganha nada adivinhando a ordem dos próprios exercícios.
 * -------------------------------------------------------------------------- */

/** Chave de ordenação de um item para um aluno. */
function chaveDeOrdem(int $alunoId, int $itemId): string
{
    return md5($alunoId . ':' . $itemId);
}

/**
 * Reordena, para este aluno, uma lista de atividades JÁ agrupada por módulo.
 *
 * A ordem dos módulos não muda — a sequência das aulas é conteúdo, não
 * sorteio. O que muda é a ordem das atividades dentro de cada módulo.
 *
 * @param array<int,array> $linhas linhas com um id e o módulo a que pertencem
 */
function embaralharAtividades(array $linhas, int $alunoId, string $chaveId = 'id', string $chaveModulo = 'modulo_id'): array
{
    if (!ATIVIDADES_EMBARALHADAS || count($linhas) < 2) {
        return $linhas;
    }

    $grupos = [];
    foreach ($linhas as $linha) {
        $grupos[(int) ($linha[$chaveModulo] ?? 0)][] = $linha;   // preserva a ordem dos módulos
    }

    $saida = [];
    foreach ($grupos as $grupo) {
        usort($grupo, static fn(array $a, array $b): int => strcmp(
            chaveDeOrdem($alunoId, (int) $a[$chaveId]),
            chaveDeOrdem($alunoId, (int) $b[$chaveId])
        ));
        array_push($saida, ...$grupo);
    }

    return $saida;
}

/** Id do usuário autenticado, ou null se não houver sessão. */
function usuarioAtualId(): ?int
{
    return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
}

/** Interrompe a página e manda para o login se não houver sessão. */
function exigirLogin(): void
{
    // Sem sessão, mas com cookie de "Lembrar-me" válido? Entra direto.
    // (includes/conta.php; $pdo vem de config/conexao.php, incluído antes.)
    if (!usuarioLogado() && isset($GLOBALS['pdo'])) {
        entrarPeloLembrete($GLOBALS['pdo']);
    }

    if (!usuarioLogado()) {
        header('Location: login.php');
        exit;
    }

    // v10: tipo e situação da conta relidos do BANCO a cada página. Antes o
    // tipo vinha só da sessão: um admin rebaixado continuava admin até sair, e
    // uma conta desativada continuava usando o site.
    if (isset($GLOBALS['pdo'])) {
        $stmt = $GLOBALS['pdo']->prepare("SELECT tipo, ativo FROM usuarios WHERE id = ?");
        $stmt->execute([usuarioAtualId()]);
        $conta = $stmt->fetch();

        if (!$conta || (int) $conta['ativo'] !== 1) {
            $_SESSION = [];
            session_regenerate_id(true);
            header('Location: login.php' . ($conta ? '?desativada=1' : ''));
            exit;
        }
        $_SESSION['usuario_tipo'] = $conta['tipo'];
    }
}

/**
 * Interrompe a página se o usuário não administrar o conteúdo.
 *
 * A checagem é feita SEMPRE no servidor. Esconder o link do menu não é
 * controle de acesso — qualquer pessoa pode digitar a URL direto.
 */
function exigirAdmin(): void
{
    exigirLogin();
    if (!usuarioEhAdmin()) {
        header('Location: inicio.php');
        exit;
    }
}

/** Grava os dados do usuário na sessão depois de um login bem-sucedido. */
function iniciarSessaoUsuario(array $usuario): void
{
    session_regenerate_id(true);   // evita fixação de sessão
    $_SESSION['usuario_id']   = (int) $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_tipo'] = $usuario['tipo'];
}

/** Carrega o usuário logado direto do banco (dados sempre atualizados). */
function usuarioAtual(PDO $pdo): ?array
{
    $id = usuarioAtualId();
    if ($id === null) {
        return null;
    }
    // Os JOINs trazem o que o usuário equipou na Loja (v5): o slug da moldura
    // vira classe CSS do avatar; o título comprado substitui o título do nível.
    $stmt = $pdo->prepare(
        "SELECT u.id, u.nome, u.email, u.tipo, u.xp, u.sequencia_dias, u.ultimo_dia_ativo,
                u.foto_avatar, m.slug AS moldura_slug, t.nome AS titulo_loja
         FROM usuarios u
         LEFT JOIN itens_loja m ON m.id = u.moldura_id
         LEFT JOIN itens_loja t ON t.id = u.titulo_id
         WHERE u.id = ?"
    );
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();

    return $usuario ?: null;
}


/* -----------------------------------------------------------------------------
 * Gamificação
 * -------------------------------------------------------------------------- */

/**
 * Nível a partir do XP.
 *
 * O nível NÃO é guardado no banco: é sempre derivado do XP. Guardar os dois
 * criaria duas fontes para a mesma verdade, que mais cedo ou mais tarde
 * divergem.
 */
function calcularNivel(int $xp): int
{
    return intdiv(max(0, $xp), XP_POR_NIVEL) + 1;
}

/** Quanto XP o usuário já acumulou dentro do nível atual. */
function xpNoNivel(int $xp): int
{
    return max(0, $xp) % XP_POR_NIVEL;
}

/** Percentual (0 a 100) da barra de progresso do nível atual. */
function percentualDoNivel(int $xp): int
{
    return (int) round(xpNoNivel($xp) / XP_POR_NIVEL * 100);
}

/**
 * Atualiza a ofensiva (dias seguidos de ESTUDO) e devolve o valor novo.
 * Chamada ao enviar resposta de atividade (registrarSubmissao) e ao entregar prova.
 *
 * Regra:
 *   - já contou hoje          → não faz nada
 *   - último acesso foi ontem → soma 1
 *   - qualquer outro caso     → volta para 1
 */
function atualizarOfensiva(PDO $pdo, int $usuarioId): int
{
    $stmt = $pdo->prepare("SELECT sequencia_dias, ultimo_dia_ativo FROM usuarios WHERE id = ?");
    $stmt->execute([$usuarioId]);
    $dados = $stmt->fetch();

    if (!$dados) {
        return 0;
    }

    $hoje      = new DateTimeImmutable('today');
    $ontem     = $hoje->modify('-1 day');
    $sequencia = (int) $dados['sequencia_dias'];
    $ultimo    = $dados['ultimo_dia_ativo']
        ? new DateTimeImmutable($dados['ultimo_dia_ativo'])
        : null;

    if ($ultimo !== null && $ultimo->format('Y-m-d') === $hoje->format('Y-m-d')) {
        return $sequencia;                                   // já contabilizado hoje
    }

    if ($ultimo !== null && $ultimo->format('Y-m-d') === $ontem->format('Y-m-d')) {
        $sequencia++;                                        // manteve a sequência
    } else {
        $sequencia = 1;                                      // primeira vez ou sequência perdida
    }

    // melhor_sequencia (v8) guarda o recorde — é o "Melhor: 12 dias" do Perfil.
    $pdo->prepare(
        "UPDATE usuarios SET sequencia_dias = ?, ultimo_dia_ativo = ?,
                melhor_sequencia = GREATEST(melhor_sequencia, ?)
         WHERE id = ?"
    )->execute([$sequencia, $hoje->format('Y-m-d'), $sequencia, $usuarioId]);

    return $sequencia;
}

/**
 * Sequência que vale AGORA. O número gravado só muda quando o aluno estuda;
 * se ele parou, o banco continua com o valor antigo. Quem não estudou nem
 * hoje nem ontem já perdeu a sequência — mostrar o número velho no ranking
 * seria mentir. (O banco é corrigido na próxima vez que ele estudar.)
 */
function ofensivaVigente(int $sequencia, ?string $ultimoDia): int
{
    if ($ultimoDia === null || $ultimoDia === '') {
        return 0;
    }
    $ontem = (new DateTimeImmutable('today'))->modify('-1 day')->format('Y-m-d');
    return substr($ultimoDia, 0, 10) >= $ontem ? $sequencia : 0;
}

/**
 * Registra a conclusão de uma atividade e concede XP UMA ÚNICA VEZ.
 *
 * Esta função é o que impede o farm de XP. A chave única
 * (aluno_id, atividade_id) em `atividades_concluidas` garante que a segunda
 * chamada não insere nada — e, sem inserção, nenhum XP é somado. Antes disso,
 * bastava o aluno reenviar a mesma atividade para subir no ranking sem limite.
 *
 * @return int XP concedido agora (0 se a atividade já havia sido concluída).
 */
function concluirAtividade(PDO $pdo, int $alunoId, int $atividadeId): int
{
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO atividades_concluidas (aluno_id, atividade_id, xp_concedido)
         SELECT ?, id, xp_recompensa FROM atividades WHERE id = ?"
    );
    $stmt->execute([$alunoId, $atividadeId]);

    if ($stmt->rowCount() !== 1) {
        return 0;                       // já estava concluída: nada de XP de novo
    }

    $xp = (int) $pdo->query(
        "SELECT xp_concedido FROM atividades_concluidas
         WHERE aluno_id = " . (int) $alunoId . " AND atividade_id = " . (int) $atividadeId
    )->fetchColumn();

    $pdo->prepare("UPDATE usuarios SET xp = xp + ? WHERE id = ?")
        ->execute([$xp, $alunoId]);

    return $xp;
}

/** Guarda a tentativa no histórico (todas as tentativas, certas e erradas). */
function registrarSubmissao(PDO $pdo, int $alunoId, int $atividadeId, ?string $resposta, bool $correta): void
{
    $pdo->prepare(
        "INSERT INTO submissoes (aluno_id, atividade_id, resposta, correta) VALUES (?, ?, ?, ?)"
    )->execute([$alunoId, $atividadeId, $resposta, $correta ? 1 : 0]);

    // A sequência de dias conta ESTUDO: enviar uma resposta (certa ou errada)
    // conta o dia. Antes contava o login, e entrar e sair mantinha a sequência.
    atualizarOfensiva($pdo, $alunoId);
}


/* -----------------------------------------------------------------------------
 * Progresso
 * -------------------------------------------------------------------------- */

/** Percentual de atividades da trilha que o aluno já concluiu (0 a 100). */
function progressoDaTrilha(PDO $pdo, int $trilhaId, int $alunoId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(a.id) AS total,
                COUNT(ac.id) AS feitas
         FROM atividades a
         JOIN modulos m ON m.id = a.modulo_id
         LEFT JOIN atividades_concluidas ac
                ON ac.atividade_id = a.id AND ac.aluno_id = ?
         WHERE m.trilha_id = ?"
    );
    $stmt->execute([$alunoId, $trilhaId]);
    $r = $stmt->fetch();

    if (!$r || (int) $r['total'] === 0) {
        return 0;
    }

    return (int) round((int) $r['feitas'] / (int) $r['total'] * 100);
}

/**
 * Estado de cada módulo da trilha para um aluno: concluído? liberado?
 *
 * Esta é a função que implementa a progressão sequencial. Um módulo só libera
 * quando o anterior foi concluído — é isso que dá à trilha a forma de caminho,
 * em vez de uma lista solta de exercícios.
 *
 * Regras de conclusão:
 *   - módulo de aula  → todas as atividades feitas, OU marcado manualmente
 *                       (necessário para módulos só de teoria, sem exercício)
 *   - módulo de prova → somente quando aprovado, o que grava a marcação.
 *                       Responder as questões não basta: tem que passar.
 *
 * @return array<int,array> módulos na ordem da trilha, cada um com as chaves
 *                          extras `concluido` e `liberado`.
 */
function estadoDosModulos(PDO $pdo, int $trilhaId, int $alunoId): array
{
    $stmt = $pdo->prepare(
        "SELECT m.id, m.titulo, m.tipo, m.nota_minima, m.ordem, m.descricao,
                (SELECT COUNT(*) FROM atividades a WHERE a.modulo_id = m.id)
                    AS total_atividades,
                (SELECT COUNT(*)
                   FROM atividades a
                   JOIN atividades_concluidas ac
                     ON ac.atividade_id = a.id AND ac.aluno_id = ?
                  WHERE a.modulo_id = m.id)
                    AS atividades_feitas,
                (SELECT COUNT(*) FROM modulos_concluidos mc
                  WHERE mc.modulo_id = m.id AND mc.aluno_id = ?)
                    AS marcado
         FROM modulos m
         WHERE m.trilha_id = ?
         ORDER BY m.ordem, m.id"
    );
    $stmt->execute([$alunoId, $alunoId, $trilhaId]);
    $modulos = $stmt->fetchAll();

    // O primeiro módulo abre sozinho — a não ser que a trilha exija outras
    // trilhas concluídas antes (v6, ex.: Projeto Final). Aí a trilha inteira
    // fica fechada, e como desafio.php e prova.php perguntam a esta função,
    // o bloqueio vale também para quem tenta abrir pela URL.
    $anteriorConcluido = requisitosPendentes($pdo, $trilhaId, $alunoId) === [];

    // Admin não é aluno: a sequência existe para o aluno aprender na ordem, e
    // prendê-lo nela só atrapalharia quem precisa conferir o conteúdo. Ele vê
    // tudo aberto — mas o `concluido` continua sendo o progresso real dele,
    // então nada de XP ou conquista aparece sem ter sido feito.
    $ehAdmin = ehOProprioAdmin($alunoId);

    foreach ($modulos as &$modulo) {
        $total   = (int) $modulo['total_atividades'];
        $feitas  = (int) $modulo['atividades_feitas'];
        $marcado = (int) $modulo['marcado'] > 0;

        // Módulo sem conteúdo (aula sem atividades ou prova sem questões) conta
        // como concluído: não há o que fazer nele, e deixá-lo "pendente" travava
        // a trilha inteira — e o Projeto Final — para sempre. Acontecia no fluxo
        // normal do admin, que cria o módulo antes de cadastrar as atividades.
        $modulo['concluido'] = ($modulo['tipo'] === 'prova')
            ? ($marcado || $total === 0)
            : ($marcado || $feitas === $total);

        // Liberado = TODOS os anteriores concluídos (antes olhava só o
        // imediatamente anterior: se o admin acrescentasse uma atividade no
        // módulo 1 de quem já terminou, o 2 travava mas o 3 e a prova
        // continuavam abertos). Módulo que o aluno já concluiu continua
        // aberto para revisão.
        $modulo['liberado'] = $ehAdmin || $anteriorConcluido || $modulo['concluido'];

        $anteriorConcluido = $anteriorConcluido && $modulo['concluido'];
    }
    unset($modulo);

    return $modulos;
}

/**
 * Trilhas exigidas por esta que o aluno ainda NÃO concluiu (títulos).
 *
 * "Concluída" usa a mesma regra de estadoDosModulos(): módulo de aula com todas
 * as atividades feitas (ou marcado), e prova aprovada. A conta é feita em SQL,
 * e não chamando estadoDosModulos() da outra trilha, para não encadear
 * consultas trilha a trilha.
 *
 * @return string[] vazio = trilha liberada
 */
function requisitosPendentes(PDO $pdo, int $trilhaId, int $alunoId): array
{
    // Pré-requisito é regra de aprendizagem, não de permissão: para o admin
    // olhando a PRÓPRIA conta, que precisa abrir qualquer trilha para
    // conferir, nunca há pendência. Olhando a ficha de um aluno (admin_aluno),
    // a regra normal vale — senão a ficha mostraria um progresso que não é o
    // daquele aluno.
    if (ehOProprioAdmin($alunoId)) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT t.titulo
         FROM trilha_requisitos r
         JOIN trilhas t ON t.id = r.requisito_id
         WHERE r.trilha_id = ?
           -- Trilha exigida em rascunho não prende ninguém: o aluno não tem
           -- como fazê-la, e ficaria trancado sem nem ver o que falta.
           AND t.publicada = 1
           AND EXISTS (
               -- existe algum módulo PENDENTE na trilha exigida? Pendente =
               -- não marcado E (aula com atividade por fazer, ou prova com
               -- questões). Módulo vazio não prende ninguém (igual a estadoDosModulos).
               SELECT 1 FROM modulos m
               WHERE m.trilha_id = r.requisito_id
                 AND NOT EXISTS (SELECT 1 FROM modulos_concluidos mc
                                  WHERE mc.modulo_id = m.id AND mc.aluno_id = ?)
                 AND (
                       (m.tipo = 'aula' AND EXISTS (
                            SELECT 1 FROM atividades a
                            WHERE a.modulo_id = m.id
                              AND NOT EXISTS (SELECT 1 FROM atividades_concluidas ac
                                               WHERE ac.atividade_id = a.id AND ac.aluno_id = ?)))
                    OR (m.tipo = 'prova' AND EXISTS (SELECT 1 FROM atividades a WHERE a.modulo_id = m.id))
                 )
           )
         ORDER BY t.ordem, t.id"
    );
    $stmt->execute([$trilhaId, $alunoId, $alunoId]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * A trilha está concluída? UMA regra para o sistema inteiro: todos os módulos
 * concluídos segundo estadoDosModulos() — aulas feitas e prova APROVADA com a
 * nota mínima. Antes, a lista e a conquista exigiam 100% das questões da prova,
 * e o aluno aprovado com 80% ficava preso em "95%" e sem a conquista.
 *
 * @param array $modulos resultado de estadoDosModulos()
 */
function trilhaConcluida(array $modulos): bool
{
    if ($modulos === []) {
        return false;
    }
    foreach ($modulos as $modulo) {
        if (!$modulo['concluido']) {
            return false;
        }
    }
    return true;
}

/** O aluno pode abrir este módulo? Usado para barrar acesso pela URL. */
function moduloLiberado(PDO $pdo, int $moduloId, int $alunoId): bool
{
    $stmt = $pdo->prepare(
        "SELECT m.trilha_id, t.publicada
         FROM modulos m JOIN trilhas t ON t.id = m.trilha_id
         WHERE m.id = ?"
    );
    $stmt->execute([$moduloId]);
    $linha = $stmt->fetch();

    if (!$linha) {
        return false;
    }

    // Trilha em rascunho não abre para aluno nem pela URL. O admin pode abrir,
    // para conferir o conteúdo antes de publicar.
    if ((int) $linha['publicada'] !== 1 && !usuarioEhAdmin()) {
        return false;
    }

    // O admin abre qualquer módulo, na ordem que quiser, para conferir o
    // conteúdo como o aluno vê (ADM-03). Para aluno a regra de sequência vale.
    if (usuarioEhAdmin()) {
        return true;
    }

    $trilhaId = (int) $linha['trilha_id'];

    foreach (estadoDosModulos($pdo, (int) $trilhaId, $alunoId) as $modulo) {
        if ((int) $modulo['id'] === $moduloId) {
            return (bool) $modulo['liberado'];
        }
    }

    return false;
}

/**
 * Próximo passo do aluno na trilha: uma atividade ou uma prova.
 *
 * Devolve null quando não há nada disponível (trilha sem conteúdo). Se o aluno
 * já concluiu tudo, devolve a primeira atividade, para revisar.
 *
 * @return array{tipo:string,id:int}|null  tipo é 'atividade' ou 'prova'
 */
function proximoPassoDaTrilha(PDO $pdo, int $trilhaId, int $alunoId): ?array
{
    $modulos = estadoDosModulos($pdo, $trilhaId, $alunoId);

    foreach ($modulos as $modulo) {
        if ($modulo['concluido'] || !$modulo['liberado']) {
            continue;
        }

        if ($modulo['tipo'] === 'prova') {
            return ['tipo' => 'prova', 'id' => (int) $modulo['id']];
        }

        // Pendentes do módulo NA ORDEM DESTE ALUNO (ver embaralharAtividades):
        // pegar "a primeira pelo campo ordem" mandaria o aluno para uma
        // atividade diferente da que a tela mostra como próxima.
        $stmt = $pdo->prepare(
            "SELECT a.id, a.modulo_id
             FROM atividades a
             LEFT JOIN atividades_concluidas ac
                    ON ac.atividade_id = a.id AND ac.aluno_id = ?
             WHERE a.modulo_id = ? AND ac.id IS NULL
             ORDER BY a.ordem, a.id"
        );
        $stmt->execute([$alunoId, (int) $modulo['id']]);
        $pendentes = embaralharAtividades($stmt->fetchAll(), $alunoId);

        if ($pendentes !== []) {
            return ['tipo' => 'atividade', 'id' => (int) $pendentes[0]['id']];
        }
    }

    // Nada pendente: devolve a primeira atividade da trilha, para revisão.
    $stmt = $pdo->prepare(
        "SELECT a.id, a.modulo_id
         FROM atividades a
         JOIN modulos m ON m.id = a.modulo_id
         WHERE m.trilha_id = ? AND m.tipo = 'aula'
         ORDER BY m.ordem, m.id, a.ordem, a.id"
    );
    $stmt->execute([$trilhaId]);
    $todas = embaralharAtividades($stmt->fetchAll(), $alunoId);

    return $todas !== [] ? ['tipo' => 'atividade', 'id' => (int) $todas[0]['id']] : null;
}

/** Marca um módulo como concluído para o aluno (idempotente). */
function concluirModulo(PDO $pdo, int $alunoId, int $moduloId): void
{
    $pdo->prepare(
        "INSERT IGNORE INTO modulos_concluidos (aluno_id, modulo_id) VALUES (?, ?)"
    )->execute([$alunoId, $moduloId]);
}

/** Última tentativa de prova do aluno neste módulo, ou null. */
function ultimaTentativaProva(PDO $pdo, int $alunoId, int $moduloId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT acertos, total, percentual, aprovado, realizada_em
         FROM tentativas_prova
         WHERE aluno_id = ? AND modulo_id = ?
         ORDER BY realizada_em DESC, id DESC
         LIMIT 1"
    );
    $stmt->execute([$alunoId, $moduloId]);
    $tentativa = $stmt->fetch();

    return $tentativa ?: null;
}


/* -----------------------------------------------------------------------------
 * Apresentação
 * -------------------------------------------------------------------------- */

/**
 * Monta o HTML de uma mensagem de sucesso ou erro.
 * O texto é sempre escapado: nunca passe HTML pronto para esta função.
 */
function exibeMensagem(string $texto, string $tipo = 'sucesso'): string
{
    $classe = ($tipo === 'erro') ? 'aviso-erro' : 'aviso-sucesso';

    return '<div class="aviso ' . $classe . '">' . htmlspecialchars($texto) . '</div>';
}

/**
 * Campo de texto do POST, sempre string. `nome[]=x` no lugar de `nome=x` chega
 * como ARRAY, e trim(array) derrubava a página com TypeError (erro 500).
 */
function textoPost(string $campo): string
{
    $valor = $_POST[$campo] ?? '';
    return is_string($valor) ? $valor : '';
}

/** Rótulo legível para os códigos de dificuldade guardados no banco. */
function rotuloDificuldade(string $codigo): string
{
    return [
        'iniciante'     => 'Iniciante',
        'intermediario' => 'Intermediário',
        'avancado'      => 'Avançado',
        'facil'         => 'Fácil',
        'medio'         => 'Médio',
        'dificil'       => 'Difícil',
    ][$codigo] ?? $codigo;
}

/** Concorda o substantivo com o número: 1 dia / 2 dias. */
function plural(int $quantidade, string $singular, string $plural): string
{
    return $quantidade . ' ' . ($quantidade === 1 ? $singular : $plural);
}

/** Atalho para escapar texto na saída HTML. */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

// Lembrar-me e redefinição de senha (v7). Fica no fim porque conta.php
// usa as funções deste arquivo.
require_once __DIR__ . '/conta.php';
