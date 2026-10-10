<?php
/**
 * includes/admin.php — apoio comum às telas de Administração.
 *
 * Só o que as três telas (trilha, módulo, atividade) repetiriam: mensagem entre
 * requisições, redirecionamento depois de salvar e os rótulos dos tipos.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

/** Guarda uma mensagem para a próxima página e redireciona (Post/Redirect/Get). */
function redirecionarCom(string $destino, string $texto, string $tipo = 'sucesso'): never
{
    $_SESSION['flash'] = [$texto, $tipo];
    header('Location: ' . $destino);
    exit;
}

/** Lê e limpa a mensagem deixada por redirecionarCom(). */
function consumirFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

/** Rótulo legível dos tipos de atividade. */
function rotuloTipoAtividade(string $tipo): string
{
    return [
        'quiz'          => 'Quiz',
        'completar'     => 'Completar o código',
        'corrigir_erro' => 'Corrigir o erro',
        'codigo_livre'  => 'Código livre',
    ][$tipo] ?? $tipo;
}

/** Tipos de atividade aceitos, na ordem em que aparecem no formulário. */
function tiposDeAtividade(): array
{
    return ['quiz', 'completar', 'corrigir_erro', 'codigo_livre'];
}

/** Um quiz usa alternativas; os demais usam casos de teste e editor de código. */
function ehQuiz(string $tipo): bool
{
    return $tipo === 'quiz';
}

/** Níveis de dificuldade aceitos. */
function dificuldades(): array
{
    return ['facil' => 'Fácil', 'medio' => 'Médio', 'dificil' => 'Difícil'];
}

/**
 * A atividade já foi concluída por alguém?
 *
 * Usado para decidir se dá para apagar. Apagar uma atividade concluída derruba,
 * por cascata, o registro de conclusão e o XP de quem já a fez — progresso de
 * aluno sumindo por causa de uma limpeza de conteúdo.
 */
function atividadeTemProgresso(PDO $pdo, int $atividadeId): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM atividades_concluidas WHERE atividade_id = ?");
    $stmt->execute([$atividadeId]);

    return (int) $stmt->fetchColumn() > 0;
}

/** Mesma pergunta, para um módulo inteiro. */
function moduloTemProgresso(PDO $pdo, int $moduloId): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
           FROM atividades_concluidas ac
           JOIN atividades a ON a.id = ac.atividade_id
          WHERE a.modulo_id = ?"
    );
    $stmt->execute([$moduloId]);

    if ((int) $stmt->fetchColumn() > 0) {
        return true;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM modulos_concluidos WHERE modulo_id = ?");
    $stmt->execute([$moduloId]);

    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Algum aluno já tem progresso na trilha? (atividade concluída, módulo
 * concluído ou tentativa de prova). Só trilha SEM progresso pode ser apagada.
 */
function trilhaTemProgresso(PDO $pdo, int $trilhaId): bool
{
    $stmt = $pdo->prepare(
        "SELECT (SELECT COUNT(*) FROM atividades_concluidas ac
                   JOIN atividades a ON a.id = ac.atividade_id
                   JOIN modulos m ON m.id = a.modulo_id WHERE m.trilha_id = ?)
              + (SELECT COUNT(*) FROM modulos_concluidos mc
                   JOIN modulos m ON m.id = mc.modulo_id WHERE m.trilha_id = ?)
              + (SELECT COUNT(*) FROM tentativas_prova tp
                   JOIN modulos m ON m.id = tp.modulo_id WHERE m.trilha_id = ?)"
    );
    $stmt->execute([$trilhaId, $trilhaId, $trilhaId]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Próximo número de ordem livre numa coleção (módulos de uma trilha, etc.). */
function proximaOrdem(PDO $pdo, string $tabela, string $coluna, int $valor): int
{
    // $tabela e $coluna NUNCA vêm do usuário: são literais escritos no código
    // que chama. Entrada de usuário aqui seria injeção de SQL — por isso a
    // lista abaixo, que recusa qualquer coisa fora do previsto.
    $permitido = ['modulos' => 'trilha_id', 'atividades' => 'modulo_id', 'trilhas' => null];

    if (!array_key_exists($tabela, $permitido) || $permitido[$tabela] !== $coluna) {
        throw new InvalidArgumentException('Tabela ou coluna não permitida em proximaOrdem().');
    }

    $stmt = $pdo->prepare("SELECT COALESCE(MAX(ordem), 0) + 1 FROM `$tabela` WHERE `$coluna` = ?");
    $stmt->execute([$valor]);

    return (int) $stmt->fetchColumn();
}

/** Cabeçalho com o caminho percorrido, para não se perder entre as telas. */
function trilhaDeNavegacaoAdmin(array $itens): string
{
    $html = '<nav class="trilha-navegacao"><a href="administracao.php">Administração</a>';

    $ultimo = count($itens) - 1;
    foreach (array_values($itens) as $i => $item) {
        $html .= icone('seta', 14);
        $html .= ($i === $ultimo || empty($item['url']))
            ? '<strong>' . e($item['titulo']) . '</strong>'
            : '<a href="' . e($item['url']) . '">' . e($item['titulo']) . '</a>';
    }

    return $html . '</nav>';
}


/** Ícones de trilha disponíveis (código → rótulo). Ver aparenciaDaTrilha(). */
function iconesDeTrilha(): array
{
    return [
        'logica'   => 'Lógica',
        'html'     => 'HTML',
        'css'      => 'CSS',
        'js'       => 'JavaScript',
        'loop'     => 'Repetição',
        'funcao'   => 'Funções',
        'desafios' => 'Desafios',
        'projeto'  => 'Projeto',
        ''         => 'Padrão',
    ];
}

/**
 * Linguagens de trilha (v6). Define QUAL executor corrige os exercícios:
 * JavaScript/Python → Web Worker; HTML e CSS → iframe isolado com verificações.
 */
function linguagensDeTrilha(): array
{
    return ['javascript' => 'JavaScript', 'html' => 'HTML e CSS', 'python' => 'Python (Pyodide)'];
}



/* -----------------------------------------------------------------------------
 * v10 — área do administrador
 * -------------------------------------------------------------------------- */

/**
 * Anota uma ação do admin em `admin_registro` (ADM-05).
 *
 * Chamada em todo POST da Administração que altera dados. O registro é o que
 * responde "quem publicou/apagou/desativou isso, e quando?".
 *
 * @param string      $acao ex.: 'trilha.publicar', 'aluno.desativar'
 * @param string|null $alvo texto legível: "Trilha: HTML"
 */
function registrarAcaoAdmin(PDO $pdo, string $acao, ?string $alvo = null): void
{
    $pdo->prepare("INSERT INTO admin_registro (admin_id, acao, alvo) VALUES (?, ?, ?)")
        ->execute([usuarioAtualId(), mb_substr($acao, 0, 60), $alvo !== null ? mb_substr($alvo, 0, 160) : null]);
}

/** Rótulo legível das ações gravadas em admin_registro. */
function rotuloAcaoAdmin(string $acao): string
{
    return [
        'trilha.criar'        => 'Criou a trilha',
        'trilha.editar'       => 'Editou a trilha',
        'trilha.publicar'     => 'Publicou a trilha',
        'trilha.despublicar'  => 'Despublicou a trilha',
        'trilha.arquivar'     => 'Arquivou a trilha',
        'trilha.desarquivar'  => 'Desarquivou a trilha',
        'trilha.apagar'       => 'Apagou a trilha',
        'trilha.mover'        => 'Mudou a ordem da trilha',
        'modulo.criar'        => 'Criou o módulo',
        'modulo.editar'       => 'Editou o módulo',
        'modulo.apagar'       => 'Apagou o módulo',
        'modulo.mover'        => 'Mudou a ordem do módulo',
        'atividade.criar'     => 'Criou a atividade',
        'atividade.editar'    => 'Editou a atividade',
        'atividade.apagar'    => 'Apagou a atividade',
        'atividade.mover'     => 'Mudou a ordem da atividade',
        'aluno.promover'      => 'Promoveu a admin',
        'aluno.rebaixar'      => 'Tirou o acesso de admin de',
        'aluno.desativar'     => 'Desativou a conta de',
        'aluno.reativar'      => 'Reativou a conta de',
        'aluno.link_senha'    => 'Enviou link de nova senha para',
        'aluno.excluir'       => 'Excluiu a conta de',
        'conquista.criar'     => 'Criou a conquista',
        'conquista.editar'    => 'Editou a conquista',
        'conquista.ativar'    => 'Ativou a conquista',
        'conquista.desativar' => 'Desativou a conquista',
        'loja.criar'          => 'Criou o item da loja',
        'loja.editar'         => 'Editou o item da loja',
        'loja.ativar'         => 'Colocou à venda',
        'loja.desativar'      => 'Tirou da vitrine',
        'suporte.lida'        => 'Marcou como lida a mensagem de',
        'suporte.nao_lida'    => 'Marcou como não lida a mensagem de',
    ][$acao] ?? $acao;
}

/**
 * Abas da Administração: o mesmo menu em todas as telas de gestão.
 *
 * @param string $atual arquivo da aba ativa (ex.: 'admin_alunos.php')
 */
function abasAdmin(string $atual, int $suporteNaoLidas = 0): string
{
    $abas = [
        'administracao.php'   => ['painel',  'Painel e conteúdo'],
        'admin_alunos.php'    => ['perfil',  'Alunos'],
        'admin_relatorios.php'=> ['ranking', 'Relatórios'],
        'admin_conquistas.php'=> ['estrela', 'Conquistas'],
        'admin_loja.php'      => ['loja',    'Loja'],
        'admin_suporte.php'   => ['email',   'Suporte'],
        'admin_registro.php'  => ['relogio', 'Registro'],
    ];
    $html = '<nav class="abas-admin" aria-label="Seções da Administração">';
    foreach ($abas as $arquivo => [$ic, $rotulo]) {
        $ativa = $arquivo === $atual;
        $html .= '<a href="' . $arquivo . '"' . ($ativa ? ' class="ativa" aria-current="page"' : '') . '>'
               . icone($ic, 16) . '<span>' . e($rotulo) . '</span>'
               . ($arquivo === 'admin_suporte.php' && $suporteNaoLidas > 0
                    ? '<b class="abas-admin-contador">' . $suporteNaoLidas . '</b>' : '')
               . '</a>';
    }
    return $html . '</nav>';
}

/** Mensagens de suporte não lidas (contador da aba). */
function suporteNaoLidas(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM mensagens_suporte WHERE lida = 0")->fetchColumn();
}

/**
 * Período dos relatórios: '7', '30' ou 'tudo' (ADM-85).
 * Devolve [código, rótulo, condição SQL sobre a coluna informada].
 * A condição é montada só com valores fixos daqui — nada do usuário entra no SQL.
 */
function periodoRelatorio(string $coluna): array
{
    $escolha = $_GET['periodo'] ?? '30';
    $opcoes = ['7' => 'Últimos 7 dias', '30' => 'Últimos 30 dias', 'tudo' => 'Desde o início'];
    if (!array_key_exists($escolha, $opcoes)) {
        $escolha = '30';
    }
    $sql = $escolha === 'tudo' ? '1 = 1' : $coluna . ' >= NOW() - INTERVAL ' . (int) $escolha . ' DAY';
    return [$escolha, $opcoes[$escolha], $sql, $opcoes];
}

/**
 * Números de uma atividade para o admin (ADM-48), lidos de `submissoes`
 * (toda tentativa, certa ou errada), `atividades_concluidas` e `dicas_vistas`.
 */
function estatisticasDaAtividade(PDO $pdo, int $atividadeId): array
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS tentativas,
                COUNT(DISTINCT aluno_id) AS alunos,
                COALESCE(SUM(correta), 0) AS certas,
                (SELECT COUNT(*) FROM atividades_concluidas WHERE atividade_id = ?) AS concluiram,
                (SELECT COUNT(*) FROM dicas_vistas WHERE atividade_id = ?) AS dicas,
                (SELECT COUNT(*)
                   FROM (SELECT MIN(id) AS primeira FROM submissoes WHERE atividade_id = ? GROUP BY aluno_id) p
                   JOIN submissoes s ON s.id = p.primeira
                  WHERE s.correta = 1) AS acertaram_de_primeira
         FROM submissoes WHERE atividade_id = ?"
    );
    $stmt->execute([$atividadeId, $atividadeId, $atividadeId, $atividadeId]);
    $n = array_map('intval', $stmt->fetch());

    $n['taxa_acerto']    = $n['tentativas'] > 0 ? (int) round($n['certas'] / $n['tentativas'] * 100) : null;
    $n['taxa_primeira']  = $n['alunos'] > 0 ? (int) round($n['acertaram_de_primeira'] / $n['alunos'] * 100) : null;
    $n['sem_concluir']   = max(0, $n['alunos'] - $n['concluiram']);
    return $n;
}

/* -----------------------------------------------------------------------------
 * PAINEL INICIAL DO ADMIN
 *
 * Tudo aqui sai do banco. Nenhum número é de exemplo: um painel bonito com
 * dados inventados é pior do que painel nenhum, porque a banca confere.
 * -------------------------------------------------------------------------- */

/**
 * Números do topo do painel, com a variação em relação ao mês anterior.
 *
 * "Mês" aqui são os últimos 30 dias comparados com os 30 anteriores — e não o
 * mês do calendário, porque no dia 2 a comparação seria com dois dias de dados
 * e a variação daria um número sem sentido.
 */
function numerosDoPainel(PDO $pdo): array
{
    $n = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM usuarios WHERE tipo = 'aluno') AS alunos,
            (SELECT COUNT(*) FROM usuarios WHERE tipo = 'aluno' AND criado_em >= NOW() - INTERVAL 30 DAY) AS novos30,
            (SELECT COUNT(*) FROM usuarios WHERE tipo = 'aluno'
                AND criado_em >= NOW() - INTERVAL 60 DAY AND criado_em < NOW() - INTERVAL 30 DAY) AS novos30ant,
            (SELECT COUNT(DISTINCT aluno_id) FROM submissoes WHERE enviado_em >= NOW() - INTERVAL 30 DAY) AS ativos30,
            (SELECT COUNT(DISTINCT aluno_id) FROM submissoes
                WHERE enviado_em >= NOW() - INTERVAL 60 DAY AND enviado_em < NOW() - INTERVAL 30 DAY) AS ativos30ant,
            (SELECT COUNT(*) FROM atividades_concluidas WHERE concluido_em >= NOW() - INTERVAL 30 DAY) AS concluidas30,
            (SELECT COUNT(*) FROM atividades_concluidas
                WHERE concluido_em >= NOW() - INTERVAL 60 DAY AND concluido_em < NOW() - INTERVAL 30 DAY) AS concluidas30ant,
            (SELECT COUNT(DISTINCT aluno_id) FROM submissoes WHERE enviado_em >= NOW() - INTERVAL 7 DAY) AS ativos7,
            (SELECT COUNT(*) FROM usuarios WHERE tipo = 'aluno' AND criado_em >= NOW() - INTERVAL 7 DAY) AS novos7,
            (SELECT COUNT(*) FROM atividades_concluidas WHERE concluido_em >= NOW() - INTERVAL 7 DAY) AS concluidas7,
            (SELECT COUNT(*) FROM submissoes WHERE enviado_em >= NOW() - INTERVAL 30 DAY) AS envios30,
            (SELECT COALESCE(SUM(correta), 0) FROM submissoes WHERE enviado_em >= NOW() - INTERVAL 30 DAY) AS certas30"
    )->fetch();

    $n = array_map('intval', $n);
    $n['taxa_acerto'] = $n['envios30'] > 0 ? (int) round($n['certas30'] / $n['envios30'] * 100) : null;
    return $n;
}

/**
 * Variação percentual entre dois períodos, ou null quando não dá para comparar.
 *
 * Sem o anterior, QUALQUER porcentagem seria invenção (de 0 para 5 não é
 * "+500%", é "não havia base"). Nesse caso a tela mostra "sem base de
 * comparação" em vez de um número bonito e falso.
 */
function variacao(int $atual, int $anterior): ?int
{
    return $anterior > 0 ? (int) round(($atual - $anterior) / $anterior * 100) : null;
}

/**
 * Série diária dos últimos N dias: cadastros novos e alunos ativos por dia.
 *
 * Os dias são montados em PHP, e não pelo GROUP BY: dia sem nenhum cadastro
 * não volta do banco, e o gráfico ficaria com a linha "pulando" datas.
 *
 * @return array<int,array{dia:string,rotulo:string,cadastros:int,ativos:int}>
 */
function serieDosUltimosDias(PDO $pdo, int $dias = 7): array
{
    $dias = max(2, min(30, $dias));

    $cadastros = $pdo->prepare(
        "SELECT DATE(criado_em) AS dia, COUNT(*) AS total
           FROM usuarios WHERE tipo = 'aluno' AND criado_em >= CURDATE() - INTERVAL ? DAY
          GROUP BY DATE(criado_em)"
    );
    $cadastros->execute([$dias - 1]);
    $porDiaCadastro = array_column($cadastros->fetchAll(), 'total', 'dia');

    $ativos = $pdo->prepare(
        "SELECT DATE(enviado_em) AS dia, COUNT(DISTINCT aluno_id) AS total
           FROM submissoes WHERE enviado_em >= CURDATE() - INTERVAL ? DAY
          GROUP BY DATE(enviado_em)"
    );
    $ativos->execute([$dias - 1]);
    $porDiaAtivo = array_column($ativos->fetchAll(), 'total', 'dia');

    $serie = [];
    for ($i = $dias - 1; $i >= 0; $i--) {
        $dia = date('Y-m-d', strtotime("-$i day"));
        $serie[] = [
            'dia'       => $dia,
            'rotulo'    => date('d/m', strtotime($dia)),
            'cadastros' => (int) ($porDiaCadastro[$dia] ?? 0),
            'ativos'    => (int) ($porDiaAtivo[$dia] ?? 0),
        ];
    }
    return $serie;
}

/**
 * Alunos por faixa de nível (o nível vem do XP, ver calcularNivel()).
 *
 * As faixas são as mesmas quatro do wireframe. A conta é feita em PHP porque
 * o nível não existe como coluna — ele é sempre derivado do XP, de propósito.
 *
 * @return array<int,array{rotulo:string,total:int,cor:string}>
 */
function distribuicaoPorNivel(PDO $pdo): array
{
    $faixas = [
        ['rotulo' => 'Iniciante',    'cor' => '#e8690a', 'de' => 1, 'ate' => 1],
        ['rotulo' => 'Intermediário','cor' => '#3f8fdd', 'de' => 2, 'ate' => 3],
        ['rotulo' => 'Avançado',     'cor' => '#1aa862', 'de' => 4, 'ate' => 5],
        ['rotulo' => 'Especialista', 'cor' => '#b85fd0', 'de' => 6, 'ate' => PHP_INT_MAX],
    ];

    $xps = $pdo->query("SELECT xp FROM usuarios WHERE tipo = 'aluno'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($faixas as $i => $faixa) {
        $faixas[$i]['total'] = 0;
    }
    foreach ($xps as $xp) {
        $nivel = calcularNivel((int) $xp);
        foreach ($faixas as $i => $faixa) {
            if ($nivel >= $faixa['de'] && $nivel <= $faixa['ate']) {
                $faixas[$i]['total']++;
                break;
            }
        }
    }
    return $faixas;
}

/**
 * Últimas coisas que aconteceram na plataforma (cadastros, conclusões,
 * compras, conquistas e mensagens de suporte), do mais novo para o mais velho.
 *
 * Um UNION de consultas pequenas, cada uma já limitada: é mais barato do que
 * uma tabela de "eventos" que teria de ser alimentada por todo o sistema — e
 * que ficaria desatualizada no dia em que alguém esquecesse de alimentá-la.
 */
function ultimasAtividades(PDO $pdo, int $limite = 6): array
{
    $limite = max(1, min(20, $limite));
    $sql = "
        (SELECT 'cadastro' AS tipo, u.nome AS quem, 'Novo aluno cadastrado' AS texto, u.criado_em AS quando
           FROM usuarios u WHERE u.tipo = 'aluno' ORDER BY u.criado_em DESC LIMIT $limite)
        UNION ALL
        (SELECT 'atividade', u.nome, CONCAT('Concluiu: ', a.titulo), ac.concluido_em
           FROM atividades_concluidas ac
           JOIN usuarios u ON u.id = ac.aluno_id
           JOIN atividades a ON a.id = ac.atividade_id
          ORDER BY ac.concluido_em DESC LIMIT $limite)
        UNION ALL
        (SELECT 'loja', u.nome, CONCAT('Comprou na Loja: ', i.nome), c.comprado_em
           FROM compras c JOIN usuarios u ON u.id = c.usuario_id JOIN itens_loja i ON i.id = c.item_id
          ORDER BY c.comprado_em DESC LIMIT $limite)
        UNION ALL
        (SELECT 'conquista', u.nome, CONCAT('Desbloqueou: ', cq.titulo), cu.desbloqueada_em
           FROM conquistas_usuarios cu JOIN usuarios u ON u.id = cu.usuario_id
           JOIN conquistas cq ON cq.id = cu.conquista_id
          ORDER BY cu.desbloqueada_em DESC LIMIT $limite)
        UNION ALL
        (SELECT 'suporte', s.nome, CONCAT('Mensagem no Suporte (', s.assunto, ')'), s.criado_em
           FROM mensagens_suporte s ORDER BY s.criado_em DESC LIMIT $limite)
        ORDER BY quando DESC LIMIT $limite";

    return $pdo->query($sql)->fetchAll();
}

/**
 * Atividades com mais conclusões — o "Desafios em destaque" do painel.
 * Quiz fica de fora: o bloco fala de desafio de código, como na tela do aluno.
 */
function desafiosEmDestaque(PDO $pdo, int $limite = 5): array
{
    $limite = max(1, min(20, $limite));
    $stmt = $pdo->prepare(
        "SELECT a.id, a.titulo, a.tipo, a.dificuldade, t.linguagem, t.titulo AS trilha,
                (SELECT COUNT(*) FROM atividades_concluidas ac WHERE ac.atividade_id = a.id) AS concluiram
           FROM atividades a
           JOIN modulos m ON m.id = a.modulo_id
           JOIN trilhas t ON t.id = m.trilha_id
          WHERE a.tipo <> 'quiz' AND t.arquivada = 0
          ORDER BY concluiram DESC, a.id
          LIMIT $limite"
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

/* -----------------------------------------------------------------------------
 * GRÁFICOS
 *
 * Desenhados em SVG aqui mesmo, sem biblioteca. Dois motivos: o DevFox roda em
 * XAMPP local, onde uma biblioteca de CDN simplesmente não carrega sem
 * internet; e um gráfico de 7 pontos não justifica 300 KB de JavaScript.
 *
 * As cores foram escolhidas para continuarem distinguíveis por quem tem
 * daltonismo (testadas em protanopia e deuteranopia) e ainda assim terem
 * contraste suficiente sobre o fundo escuro do painel. Além da cor, cada série
 * tem legenda e uma tabela equivalente logo abaixo — quem não distingue as
 * cores, e o leitor de tela, leem os números do mesmo jeito.
 * -------------------------------------------------------------------------- */

const COR_CADASTROS = '#e8690a';
const COR_ATIVOS    = '#3f8fdd';

/**
 * Gráfico de linhas dos últimos dias: cadastros e alunos ativos.
 * Uma escala só para as duas séries (dois eixos Y no mesmo gráfico deixam
 * comparar o que não é comparável).
 *
 * @param array $serie resultado de serieDosUltimosDias()
 */
function graficoDeLinhas(array $serie): string
{
    if (count($serie) < 2) {
        return '<p class="aviso-suave">Ainda não há dias suficientes para desenhar o gráfico.</p>';
    }

    $larg = 680; $alt = 280;
    $mE = 42; $mD = 12; $mT = 14; $mB = 28;         // margens (esquerda, direita, topo, base)
    $areaL = $larg - $mE - $mD;
    $areaA = $alt - $mT - $mB;

    $maior = 0;
    foreach ($serie as $d) {
        $maior = max($maior, (int) $d['cadastros'], (int) $d['ativos']);
    }
    $topo = max(4, (int) (ceil($maior / 4) * 4));   // escala redonda, nunca 0

    $x = static fn(int $i): float => $mE + ($areaL * $i / (count($serie) - 1));
    $y = static fn(int $v): float => $mT + $areaA - ($areaA * $v / $topo);

    $svg  = '<svg class="grafico" viewBox="0 0 ' . $larg . ' ' . $alt . '" role="img" '
          . 'aria-label="Cadastros e alunos ativos por dia nos últimos ' . count($serie) . ' dias">';

    // grade e eixo Y (4 divisões)
    for ($i = 0; $i <= 4; $i++) {
        $valor = (int) round($topo * $i / 4);
        $py    = $y($valor);
        $svg .= '<line class="grafico-grade" x1="' . $mE . '" y1="' . $py . '" x2="' . ($larg - $mD) . '" y2="' . $py . '" />';
        $svg .= '<text class="grafico-eixo" x="' . ($mE - 8) . '" y="' . ($py + 4) . '" text-anchor="end">' . $valor . '</text>';
    }

    foreach ([['cadastros', COR_CADASTROS], ['ativos', COR_ATIVOS]] as [$chave, $cor]) {
        $pontos = [];
        foreach ($serie as $i => $d) {
            $pontos[] = round($x($i), 1) . ',' . round($y((int) $d[$chave]), 1);
        }
        $svg .= '<polyline fill="none" stroke="' . $cor . '" stroke-width="2" stroke-linejoin="round" '
              . 'stroke-linecap="round" points="' . implode(' ', $pontos) . '" />';

        foreach ($serie as $i => $d) {
            // O <title> vira a dica ao passar o mouse, sem uma linha de JavaScript.
            $svg .= '<circle cx="' . round($x($i), 1) . '" cy="' . round($y((int) $d[$chave]), 1) . '" r="4" '
                  . 'fill="' . $cor . '" stroke="var(--fundo-painel)" stroke-width="2"><title>'
                  . e($d['rotulo'] . ' — ' . ($chave === 'cadastros' ? 'cadastros: ' : 'ativos: ') . (int) $d[$chave])
                  . '</title></circle>';
        }
    }

    foreach ($serie as $i => $d) {
        $svg .= '<text class="grafico-eixo" x="' . round($x($i), 1) . '" y="' . ($alt - 8) . '" text-anchor="middle">'
              . e($d['rotulo']) . '</text>';
    }

    return $svg . '</svg>';
}

/**
 * Rosca da distribuição por nível. Cada fatia leva rótulo e número na legenda,
 * então a cor nunca é a única informação.
 *
 * @param array $fatias resultado de distribuicaoPorNivel()
 */
function graficoDeRosca(array $fatias): string
{
    $total = array_sum(array_column($fatias, 'total'));
    if ($total === 0) {
        return '<p class="aviso-suave">Nenhum aluno cadastrado ainda.</p>';
    }

    $raio = 54; $espessura = 18; $centro = 70;
    $circunferencia = 2 * M_PI * $raio;
    $percorrido = 0.0;

    $svg = '<svg class="rosca" viewBox="0 0 140 140" role="img" aria-label="Alunos por faixa de nível">';
    foreach ($fatias as $fatia) {
        if ((int) $fatia['total'] === 0) {
            continue;
        }
        $parte = $fatia['total'] / $total;
        // 2px de respiro entre as fatias, para elas não se colarem.
        $traco = max(0, $parte * $circunferencia - 2);
        $svg .= '<circle cx="' . $centro . '" cy="' . $centro . '" r="' . $raio . '" fill="none" '
              . 'stroke="' . $fatia['cor'] . '" stroke-width="' . $espessura . '" '
              . 'stroke-dasharray="' . round($traco, 2) . ' ' . round($circunferencia - $traco, 2) . '" '
              . 'stroke-dashoffset="' . round(-$percorrido * $circunferencia, 2) . '" '
              . 'transform="rotate(-90 ' . $centro . ' ' . $centro . ')"><title>'
              . e($fatia['rotulo'] . ': ' . $fatia['total'] . ' (' . round($parte * 100) . '%)')
              . '</title></circle>';
        $percorrido += $parte;
    }

    $svg .= '<text class="rosca-total" x="70" y="66" text-anchor="middle">' . $total . '</text>'
          . '<text class="rosca-rotulo" x="70" y="84" text-anchor="middle">alunos</text></svg>';

    return $svg;
}
