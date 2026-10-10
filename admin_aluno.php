<?php
/**
 * admin_aluno.php — ficha de um aluno e ações sobre a conta.
 *
 * Mostra (ADM-76): números, progresso em cada trilha, tentativas de prova
 * (ADM-57), conquistas e as últimas respostas enviadas.
 *
 * Ações, todas POST + CSRF e anotadas em admin_registro:
 *   - promover a admin / tirar o acesso de admin (ADM-04);
 *   - desativar / reativar a conta (ADM-77): desativada não entra, nada some;
 *   - enviar link de nova senha (ADM-78): o mesmo e-mail do "Esqueci minha senha";
 *   - excluir a conta (ADM-79, LGPD), com o e-mail digitado como confirmação.
 *
 * Travas: o admin não rebaixa, desativa nem exclui A SI MESMO por aqui, e o
 * sistema nunca fica sem nenhum admin ativo.
 *
 * O que NÃO existe aqui, de propósito: ver senha (só há o hash) e mexer em XP
 * ou moedas na mão (quebraria o rastro de onde veio cada ponto).
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/progresso.php';   // dadosDasTrilhas()

exigirAdmin();

$alunoId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT u.*, m.slug AS moldura_slug, t.nome AS titulo_loja
     FROM usuarios u
     LEFT JOIN itens_loja m ON m.id = u.moldura_id
     LEFT JOIN itens_loja t ON t.id = u.titulo_id
     WHERE u.id = ?"
);
$stmt->execute([$alunoId]);
$aluno = $stmt->fetch();

if (!$aluno) {
    redirecionarCom('admin_alunos.php', 'Conta não encontrada.', 'erro');
}

$volta   = 'admin_aluno.php?id=' . $alunoId;
$ehEu    = $alunoId === (int) usuarioAtualId();
$rotulo  = $aluno['nome'] . ' (' . $aluno['email'] . ')';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $acao = $_POST['acao'] ?? '';

    $mexeNaPropriaConta = in_array($acao, ['rebaixar', 'desativar', 'excluir'], true) && $ehEu;
    if ($mexeNaPropriaConta) {
        redirecionarCom($volta, 'Você não pode fazer isso com a sua própria conta por aqui. '
            . 'Para sair da plataforma, use Configurações → Excluir conta.', 'erro');
    }

    switch ($acao) {
        case 'promover':
            $pdo->prepare("UPDATE usuarios SET tipo = 'admin' WHERE id = ?")->execute([$alunoId]);
            registrarAcaoAdmin($pdo, 'aluno.promover', $rotulo);
            redirecionarCom($volta, $aluno['nome'] . ' agora é administrador(a).');

        case 'rebaixar':
            if ($aluno['tipo'] === 'admin' && (int) $aluno['ativo'] === 1 && contarAdminsAtivos($pdo) <= 1) {
                redirecionarCom($volta, 'Não dá para tirar o último administrador ativo.', 'erro');
            }
            $pdo->prepare("UPDATE usuarios SET tipo = 'aluno' WHERE id = ?")->execute([$alunoId]);
            registrarAcaoAdmin($pdo, 'aluno.rebaixar', $rotulo);
            redirecionarCom($volta, $aluno['nome'] . ' voltou a ser aluno(a). O acesso de admin cai na próxima página que abrir.');

        case 'desativar':
            if ($aluno['tipo'] === 'admin' && contarAdminsAtivos($pdo) <= 1) {
                redirecionarCom($volta, 'Não dá para desativar o último administrador ativo.', 'erro');
            }
            $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ?")->execute([$alunoId]);
            esquecerTodosOsLembretes($pdo, $alunoId);   // o "Lembrar-me" também para de valer
            registrarAcaoAdmin($pdo, 'aluno.desativar', $rotulo);
            redirecionarCom($volta, 'Conta desativada. A pessoa sai na próxima página que abrir e não consegue mais entrar. Nada foi apagado.');

        case 'reativar':
            $pdo->prepare("UPDATE usuarios SET ativo = 1 WHERE id = ?")->execute([$alunoId]);
            registrarAcaoAdmin($pdo, 'aluno.reativar', $rotulo);
            redirecionarCom($volta, 'Conta reativada.');

        case 'link_senha':
            if ((int) $aluno['ativo'] !== 1) {
                redirecionarCom($volta, 'Reative a conta antes de enviar o link.', 'erro');
            }
            enviarLinkDeSenha($pdo, $aluno);
            registrarAcaoAdmin($pdo, 'aluno.link_senha', $rotulo);
            redirecionarCom($volta, 'Link de nova senha enviado para ' . $aluno['email']
                . (EMAIL_MODO === 'log' ? ' (ambiente de teste: gravado em logs/emails.log).' : '.'));

        case 'excluir':
            // Confirmação digitando o e-mail: exclusão não tem volta.
            if (mb_strtolower(trim(textoPost('confirmacao'))) !== mb_strtolower($aluno['email'])) {
                redirecionarCom($volta, 'Para excluir, digite o e-mail da conta exatamente como aparece.', 'erro');
            }
            if ($erro = excluirConta($pdo, $alunoId)) {
                redirecionarCom($volta, $erro, 'erro');
            }
            registrarAcaoAdmin($pdo, 'aluno.excluir', $rotulo);
            redirecionarCom('admin_alunos.php', 'Conta de ' . $aluno['nome'] . ' excluída, com todo o progresso dela.');
    }

    redirecionarCom($volta, 'Ação inválida.', 'erro');
}

$flash = consumirFlash();

/* ---- dados da ficha ---- */
$dados       = dadosDasTrilhas($pdo, $alunoId);
$concluidas  = (int) $pdo->query("SELECT COUNT(*) FROM atividades_concluidas WHERE aluno_id = " . $alunoId)->fetchColumn();
$envios      = $pdo->query(
    "SELECT COUNT(*) AS total, COALESCE(SUM(correta), 0) AS certas FROM submissoes WHERE aluno_id = " . $alunoId
)->fetch();

$stmt = $pdo->prepare(
    "SELECT tp.percentual, tp.acertos, tp.total, tp.aprovado, tp.realizada_em, m.titulo AS prova, t.titulo AS trilha
     FROM tentativas_prova tp
     JOIN modulos m ON m.id = tp.modulo_id
     JOIN trilhas t ON t.id = m.trilha_id
     WHERE tp.aluno_id = ?
     ORDER BY tp.realizada_em DESC, tp.id DESC LIMIT 20"
);
$stmt->execute([$alunoId]);
$provas = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT c.titulo, c.raridade, cu.desbloqueada_em
     FROM conquistas_usuarios cu JOIN conquistas c ON c.id = cu.conquista_id
     WHERE cu.usuario_id = ? ORDER BY cu.desbloqueada_em DESC"
);
$stmt->execute([$alunoId]);
$conquistas = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT s.correta, s.enviado_em, s.resposta, a.id AS atividade_id, a.titulo, a.tipo
     FROM submissoes s JOIN atividades a ON a.id = s.atividade_id
     WHERE s.aluno_id = ? ORDER BY s.enviado_em DESC, s.id DESC LIMIT 15"
);
$stmt->execute([$alunoId]);
$respostas = $stmt->fetchAll();

$tituloPagina    = 'Ficha do aluno';
$subtituloPagina = $aluno['nome'];
require_once __DIR__ . '/includes/header.php';
?>

<?= abasAdmin('admin_alunos.php', suporteNaoLidas($pdo)) ?>

<nav class="trilha-navegacao" aria-label="Você está em">
    <a href="admin_alunos.php">Alunos</a><?= icone('seta', 14) ?><strong><?= e($aluno['nome']) ?></strong>
</nav>

<?php if ($flash): ?>
    <?= exibeMensagem($flash[0], $flash[1]) ?>
<?php endif; ?>

<section class="painel">
    <div class="ficha-topo">
        <?= imgAvatar($aluno['foto_avatar'], '', classeMoldura($aluno['moldura_slug'])) ?>
        <div>
            <h2><?= e($aluno['nome']) ?>
                <?php if ($aluno['tipo'] === 'admin'): ?><span class="selo selo-admin">admin</span><?php endif; ?>
                <?php if ((int) $aluno['ativo'] !== 1): ?><span class="selo selo-inativo">desativada</span><?php endif; ?>
            </h2>
            <p><?= e($aluno['email']) ?> · cadastro em <?= e(date('d/m/Y', strtotime($aluno['criado_em']))) ?>
                · <?= e($aluno['titulo_loja'] ?? tituloDoNivel(calcularNivel((int) $aluno['xp']))) ?></p>
        </div>
        <div class="ficha-numeros">
            <div><strong><?= calcularNivel((int) $aluno['xp']) ?></strong><small>Nível</small></div>
            <div><strong><?= number_format((int) $aluno['xp'], 0, ',', '.') ?></strong><small>XP</small></div>
            <div><strong><?= ofensivaVigente((int) $aluno['sequencia_dias'], $aluno['ultimo_dia_ativo']) ?></strong><small>Sequência</small></div>
            <div><strong><?= $concluidas ?></strong><small>Concluídas</small></div>
            <div><strong><?= (int) $envios['total'] > 0 ? round($envios['certas'] / $envios['total'] * 100) . '%' : '—' ?></strong><small>Acerto</small></div>
        </div>
    </div>
</section>

<div class="colunas">
    <div class="coluna-principal">

        <section class="painel">
            <h3 class="painel-titulo"><?= icone('trilhas') ?> Progresso nas trilhas</h3>
            <div class="tabela-admin-envolve">
                <table class="tabela-admin">
                    <thead><tr><th>Trilha</th><th>Progresso</th><th class="num">Atividades</th><th>Situação</th></tr></thead>
                    <tbody>
                        <?php foreach ($dados as $d): ?>
                            <tr>
                                <td><?= e($d['trilha']['titulo']) ?></td>
                                <td><div class="mini-barra"><div class="barra-progresso"><div class="barra-progresso-preenchida" style="width: <?= $d['percentual'] ?>%"></div></div><span><?= $d['percentual'] ?>%</span></div></td>
                                <td class="num"><?= (int) $d['feitas'] ?>/<?= (int) $d['total'] ?></td>
                                <td><?= $d['concluida'] ? '<span class="selo selo-concluida">concluída</span>'
                                      : ($d['bloqueada'] ? '<span class="selo">bloqueada</span>'
                                      : ($d['feitas'] > 0 ? '<span class="selo">em andamento</span>' : '<span class="aviso-suave">não começou</span>')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="painel">
            <h3 class="painel-titulo"><?= icone('prova') ?> Tentativas de prova</h3>
            <?php if (empty($provas)): ?>
                <p class="aviso-suave">Nenhuma prova feita ainda.</p>
            <?php else: ?>
                <div class="tabela-admin-envolve">
                    <table class="tabela-admin">
                        <thead><tr><th>Prova</th><th class="num">Nota</th><th>Resultado</th><th>Quando</th></tr></thead>
                        <tbody>
                            <?php foreach ($provas as $p): ?>
                                <tr>
                                    <td><?= e($p['prova']) ?><small class="ajuda-campo"> · <?= e($p['trilha']) ?></small></td>
                                    <td class="num"><?= (int) $p['percentual'] ?>% <small class="ajuda-campo">(<?= (int) $p['acertos'] ?>/<?= (int) $p['total'] ?>)</small></td>
                                    <td><?= (int) $p['aprovado'] === 1 ? '<span class="selo selo-concluida">aprovado</span>' : '<span class="selo selo-inativo">reprovado</span>' ?></td>
                                    <td><?= e(date('d/m/Y H:i', strtotime($p['realizada_em']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="painel">
            <h3 class="painel-titulo"><?= icone('codigo') ?> Últimas respostas</h3>
            <?php if (empty($respostas)): ?>
                <p class="aviso-suave">Nenhuma resposta enviada ainda.</p>
            <?php else: ?>
                <div class="tabela-admin-envolve">
                    <table class="tabela-admin">
                        <thead><tr><th>Atividade</th><th>Resultado</th><th>Resposta</th><th>Quando</th></tr></thead>
                        <tbody>
                            <?php foreach ($respostas as $r): ?>
                                <tr>
                                    <td><a href="admin_atividade.php?id=<?= (int) $r['atividade_id'] ?>"><?= e($r['titulo']) ?></a></td>
                                    <td><?= (int) $r['correta'] === 1 ? '<span class="selo selo-concluida">certa</span>' : '<span class="selo selo-inativo">errada</span>' ?></td>
                                    <td><?php if ($r['tipo'] !== 'quiz' && $r['resposta'] !== null): ?>
                                            <?php // Gravado como "CÓDIGO:\n...\n---\nSAÍDAS:..." (api/processar_desafio.php): mostra só o código.
                                                  $codigo = preg_replace('/^CÓDIGO:\n|\n---\nSAÍDAS:.*$/su', '', (string) $r['resposta']); ?>
                                            <pre class="bloco-codigo resposta-codigo"><?= e(mb_substr((string) $codigo, 0, 400)) ?></pre>
                                        <?php else: ?><span class="aviso-suave"><?= e((string) $r['resposta']) ?></span><?php endif; ?></td>
                                    <td><?= e(tempoRelativo($r['enviado_em'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <aside class="coluna-lateral">
        <section class="painel">
            <h3 class="painel-titulo"><?= icone('estrela') ?> Conquistas <small class="contador"><?= count($conquistas) ?></small></h3>
            <?php if (empty($conquistas)): ?>
                <p class="aviso-suave">Nenhuma ainda.</p>
            <?php else: ?>
                <ul class="lista-eventos">
                    <?php foreach ($conquistas as $c): ?>
                        <li><span class="evento-icone evento-laranja"><?= icone('estrela', 16) ?></span>
                            <div><strong><?= e($c['titulo']) ?></strong><small><?= e(ucfirst($c['raridade'])) ?></small></div>
                            <time><?= e(date('d/m/Y', strtotime($c['desbloqueada_em']))) ?></time></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="painel">
            <h3 class="painel-titulo"><?= icone('admin') ?> Conta</h3>
            <div class="acoes-conta">
                <?php if ($ehEu): ?>
                    <p class="aviso-suave">Esta é a sua conta: as ações de tipo e situação ficam desligadas aqui.</p>
                <?php else: ?>
                    <form method="post">
                        <?= campoCsrf() ?><input type="hidden" name="id" value="<?= $alunoId ?>">
                        <?php if ($aluno['tipo'] === 'admin'): ?>
                            <input type="hidden" name="acao" value="rebaixar">
                            <button type="submit" class="botao botao-secundario botao-pequeno" id="btnTipo">Tirar acesso de admin</button>
                        <?php else: ?>
                            <input type="hidden" name="acao" value="promover">
                            <button type="submit" class="botao botao-secundario botao-pequeno" id="btnTipo"
                                    onclick="return confirm('Dar acesso total à Administração para esta pessoa?');">Promover a admin</button>
                        <?php endif; ?>
                    </form>
                    <form method="post">
                        <?= campoCsrf() ?><input type="hidden" name="id" value="<?= $alunoId ?>">
                        <input type="hidden" name="acao" value="<?= (int) $aluno['ativo'] === 1 ? 'desativar' : 'reativar' ?>">
                        <button type="submit" class="botao botao-secundario botao-pequeno" id="btnSituacao">
                            <?= (int) $aluno['ativo'] === 1 ? 'Desativar conta' : 'Reativar conta' ?></button>
                        <small>Desativada não entra; nada é apagado.</small>
                    </form>
                <?php endif; ?>
                <form method="post">
                    <?= campoCsrf() ?><input type="hidden" name="id" value="<?= $alunoId ?>">
                    <input type="hidden" name="acao" value="link_senha">
                    <button type="submit" class="botao botao-secundario botao-pequeno" id="btnLinkSenha"><?= icone('email', 14) ?> Enviar link de nova senha</button>
                    <small>O admin nunca vê a senha: só há o hash no banco.</small>
                </form>
            </div>
        </section>

        <?php if (!$ehEu): ?>
            <section class="painel zona-perigo">
                <h3 class="painel-titulo"><?= icone('lixeira') ?> Excluir conta</h3>
                <p class="aviso-suave">Apaga a conta e todo o progresso, respostas, conquistas e compras (LGPD).
                    <strong>Não tem volta.</strong> Para tirar alguém sem apagar, desative a conta.</p>
                <form method="post" id="formExcluir">
                    <?= campoCsrf() ?><input type="hidden" name="id" value="<?= $alunoId ?>">
                    <input type="hidden" name="acao" value="excluir">
                    <div class="campo">
                        <label for="confirmacao">Digite <strong><?= e($aluno['email']) ?></strong> para confirmar</label>
                        <input type="text" id="confirmacao" name="confirmacao" autocomplete="off" required>
                    </div>
                    <button type="submit" class="botao botao-perigo botao-bloco">Excluir conta de vez</button>
                </form>
            </section>
        <?php endif; ?>
    </aside>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
