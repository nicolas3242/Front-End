<?php
/**
 * configuracoes.php — dados da conta: nome e senha.
 *
 * Decisões:
 *   - Trocar a senha EXIGE a senha atual. Sem isso, quem pegasse um computador
 *     com a sessão aberta trocaria a senha e tomaria a conta.
 *   - Depois de trocar a senha, o ID da sessão é renovado.
 *   - O e-mail não é editável aqui: ele é o login, e trocar exigiria confirmar
 *     o endereço novo por e-mail — o sistema não envia e-mail.
 *   - Cada formulário é um POST separado com token CSRF, no padrão
 *     Post/Redirect/Get: F5 depois de salvar não reenvia nada.
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';     // redirecionarCom(), consumirFlash(), CSRF

exigirLogin();
$usuarioId = (int) usuarioAtualId();

const SENHA_MINIMA = 6;           // a mesma regra do cadastro

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'nome') {
        $nome = trim(preg_replace('/\s+/', ' ', (string) ($_POST['nome'] ?? '')));

        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 100) {
            redirecionarCom('configuracoes.php', 'O nome precisa ter entre 2 e 100 caracteres.', 'erro');
        }

        $pdo->prepare("UPDATE usuarios SET nome = ? WHERE id = ?")->execute([$nome, $usuarioId]);
        $_SESSION['usuario_nome'] = $nome;          // a saudação do Início lê daqui

        redirecionarCom('configuracoes.php', 'Nome atualizado.');
    }

    if ($acao === 'senha') {
        $atual    = (string) ($_POST['senha_atual'] ?? '');
        $nova     = (string) ($_POST['senha_nova'] ?? '');
        $confirma = (string) ($_POST['senha_confirmacao'] ?? '');

        $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
        $stmt->execute([$usuarioId]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($atual, $hash)) {
            redirecionarCom('configuracoes.php', 'A senha atual não confere.', 'erro');
        }
        if (mb_strlen($nova) < SENHA_MINIMA) {
            redirecionarCom('configuracoes.php', 'A nova senha precisa ter pelo menos ' . SENHA_MINIMA . ' caracteres.', 'erro');
        }
        if ($nova !== $confirma) {
            redirecionarCom('configuracoes.php', 'A confirmação não é igual à nova senha.', 'erro');
        }
        if ($nova === $atual) {
            redirecionarCom('configuracoes.php', 'A nova senha precisa ser diferente da atual.', 'erro');
        }

        $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")
            ->execute([password_hash($nova, PASSWORD_DEFAULT), $usuarioId]);
        session_regenerate_id(true);
        // Todo navegador "lembrado" precisa da senha nova para voltar a entrar.
        esquecerTodosOsLembretes($pdo, $usuarioId);

        redirecionarCom('configuracoes.php', 'Senha alterada.');
    }

    if ($acao === 'excluir') {
        // LGPD: o próprio aluno apaga a conta e tudo o que é dele. Pede a
        // senha atual, pelo mesmo motivo da troca de senha: uma sessão aberta
        // num computador da escola não pode bastar para apagar a conta de alguém.
        $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
        $stmt->execute([$usuarioId]);
        if (!password_verify(textoPost('senha_exclusao'), (string) $stmt->fetchColumn())) {
            redirecionarCom('configuracoes.php', 'Senha incorreta: a conta não foi excluída.', 'erro');
        }
        if ($erro = excluirConta($pdo, $usuarioId)) {
            redirecionarCom('configuracoes.php', $erro, 'erro');
        }
        esquecerLembrete($pdo);
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: login.php?excluida=1');
        exit;
    }

    redirecionarCom('configuracoes.php', 'Ação inválida.', 'erro');
}

$flash = consumirFlash();

$tituloPagina    = 'Configurações';
$subtituloPagina = 'Gerencie os dados da sua conta.';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
    <?= exibeMensagem($flash[0], $flash[1]) ?>
<?php endif; ?>

<div class="grade-config">

    <section class="painel">
        <h2 class="painel-titulo"><?= icone('perfil', 18) ?> Dados da conta</h2>

        <form method="post" class="form-config">
            <?= campoCsrf() ?>
            <input type="hidden" name="acao" value="nome">

            <div class="campo">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" required minlength="2" maxlength="100"
                       autocomplete="name" value="<?= e($usuarioLogadoDados['nome']) ?>">
            </div>

            <div class="campo">
                <label for="email">E-mail</label>
                <input type="email" id="email" value="<?= e($usuarioLogadoDados['email']) ?>" disabled>
                <small class="ajuda-campo">O e-mail é o seu login e não pode ser alterado.</small>
            </div>

            <button type="submit" class="botao botao-primario">Salvar nome</button>
        </form>
    </section>

    <section class="painel">
        <h2 class="painel-titulo"><?= icone('cadeado', 18) ?> Alterar senha</h2>

        <form method="post" class="form-config" autocomplete="off">
            <?= campoCsrf() ?>
            <input type="hidden" name="acao" value="senha">

            <div class="campo">
                <label for="senha_atual">Senha atual</label>
                <input type="password" id="senha_atual" name="senha_atual" required
                       autocomplete="current-password">
            </div>

            <div class="campo">
                <label for="senha_nova">Nova senha</label>
                <input type="password" id="senha_nova" name="senha_nova" required
                       minlength="<?= SENHA_MINIMA ?>" autocomplete="new-password"
                       placeholder="Pelo menos <?= SENHA_MINIMA ?> caracteres">
            </div>

            <div class="campo">
                <label for="senha_confirmacao">Confirmar nova senha</label>
                <input type="password" id="senha_confirmacao" name="senha_confirmacao" required
                       minlength="<?= SENHA_MINIMA ?>" autocomplete="new-password">
            </div>

            <button type="submit" class="botao botao-primario">Alterar senha</button>
        </form>
    </section>

    <section class="painel zona-perigo" id="excluirConta">
        <h2 class="painel-titulo"><?= icone('lixeira', 18) ?> Excluir minha conta</h2>
        <p class="aviso-suave">
            Apaga sua conta e tudo o que é seu: progresso, XP, conquistas, compras e respostas.
            <strong>Não tem volta.</strong>
        </p>
        <form method="post" class="form-config" autocomplete="off"
              onsubmit="return confirm('Excluir sua conta de vez? Todo o seu progresso será apagado.');">
            <?= campoCsrf() ?>
            <input type="hidden" name="acao" value="excluir">
            <div class="campo">
                <label for="senha_exclusao">Confirme com sua senha</label>
                <input type="password" id="senha_exclusao" name="senha_exclusao" required autocomplete="current-password">
            </div>
            <button type="submit" class="botao botao-perigo">Excluir minha conta</button>
        </form>
    </section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
