<?php
/**
 * suporte.php — "Precisa de ajuda? Fale com o suporte."
 *
 * Funciona com ou sem login: quem esqueceu a senha também precisa de ajuda.
 * Logado, nome e e-mail já vêm preenchidos e a mensagem fica ligada à conta.
 * As mensagens vão para o banco e a equipe lê em Administração → Suporte.
 *
 * Proteções contra abuso, proporcionais ao risco: CSRF, limites de tamanho,
 * espera de 60 s entre envios da mesma sessão e um campo "isca" escondido
 * (robôs preenchem todos os campos; pessoas não veem este).
 */

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/layout_auth.php';

entrarPeloLembrete($pdo);

$assuntos = [
    'conteudo' => 'Dúvida sobre uma aula ou exercício',
    'tecnico'  => 'Problema técnico (algo não funciona)',
    'conta'    => 'Minha conta e acesso',
    'outro'    => 'Outro assunto',
];

$usuario = usuarioLogado() ? usuarioAtual($pdo) : null;
$enviado = false;
$erro    = '';
$valores = [
    'nome'     => $usuario['nome']  ?? '',
    'email'    => $usuario['email'] ?? '',
    'assunto'  => 'outro',
    'mensagem' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();

    foreach ($valores as $campo => $_) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }
    if ($usuario) {                                   // logado: não dá para se passar por outro
        $valores['nome']  = $usuario['nome'];
        $valores['email'] = $usuario['email'];
    }

    if (($_POST['site'] ?? '') !== '') {
        $enviado = true;                              // isca preenchida: finge que deu certo
    } elseif (mb_strlen($valores['nome']) < 2 || mb_strlen($valores['nome']) > 100) {
        $erro = 'Informe seu nome.';
    } elseif (!filter_var($valores['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($valores['email']) > 150) {
        $erro = 'Informe um e-mail válido para podermos responder.';
    } elseif (!isset($assuntos[$valores['assunto']])) {
        $erro = 'Escolha um assunto.';
    } elseif (mb_strlen($valores['mensagem']) < 10 || mb_strlen($valores['mensagem']) > 2000) {
        $erro = 'A mensagem precisa ter entre 10 e 2.000 caracteres.';
    } elseif (($espera = segundosParaNovoEnvio('suporte')) > 0) {
        $erro = 'Aguarde ' . plural($espera, 'segundo', 'segundos') . ' para enviar outra mensagem.';
    } else {
        $pdo->prepare(
            "INSERT INTO mensagens_suporte (usuario_id, nome, email, assunto, mensagem) VALUES (?, ?, ?, ?, ?)"
        )->execute([
            $usuario ? (int) $usuario['id'] : null,
            $valores['nome'], $valores['email'], $valores['assunto'], $valores['mensagem'],
        ]);
        registrarEnvio('suporte');
        $enviado = true;
    }
}

abrirPaginaAuth('Suporte');
?>
        <h1>Fale com o suporte</h1>
        <p class="auth-subtitulo">Conte o que aconteceu. A equipe do DevFox responde no e-mail informado.</p>

        <?php if ($enviado): ?>
            <?= exibeMensagem('Mensagem enviada! Vamos responder em ' . $valores['email'] . '.') ?>
            <a href="<?= $usuario ? 'inicio.php' : 'login.php' ?>" class="botao botao-contorno botao-bloco botao-auth">
                <?= $usuario ? 'Voltar para o início' : 'Voltar para o login' ?>
            </a>
        <?php else: ?>
            <?php if ($erro !== ''): ?>
                <?= exibeMensagem($erro, 'erro') ?>
            <?php endif; ?>

            <form method="POST" novalidate>
                <?= campoCsrf() ?>

                <?php /* Isca para robôs: fora da tela e fora da ordem de tabulação. */ ?>
                <div class="campo-isca" aria-hidden="true">
                    <label for="site">Deixe em branco</label>
                    <input type="text" id="site" name="site" tabindex="-1" autocomplete="off">
                </div>

                <div class="campo">
                    <label for="nome">Nome</label>
                    <div class="campo-icone">
                        <?= icone('perfil', 18) ?>
                        <input type="text" id="nome" name="nome" required maxlength="100" autocomplete="name" placeholder="Digite seu nome"
                               value="<?= e($valores['nome']) ?>" <?= $usuario ? 'readonly' : '' ?>>
                    </div>
                </div>

                <div class="campo">
                    <label for="email">E-mail para resposta</label>
                    <div class="campo-icone">
                        <?= icone('email', 18) ?>
                        <input type="email" id="email" name="email" required maxlength="150" autocomplete="email" placeholder="Digite seu e-mail"
                               value="<?= e($valores['email']) ?>" <?= $usuario ? 'readonly' : '' ?>>
                    </div>
                </div>

                <div class="campo">
                    <label for="assunto">Assunto</label>
                    <select id="assunto" name="assunto">
                        <?php foreach ($assuntos as $codigo => $rotulo): ?>
                            <option value="<?= e($codigo) ?>" <?= $valores['assunto'] === $codigo ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="mensagem">Mensagem</label>
                    <textarea id="mensagem" name="mensagem" rows="5" maxlength="2000" required
                              placeholder="Descreva o problema ou a dúvida. Se for um exercício, diga qual."><?= e($valores['mensagem']) ?></textarea>
                </div>

                <button type="submit" class="botao botao-primario botao-bloco botao-auth">
                    Enviar mensagem <?= icone('seta', 18) ?>
                </button>
            </form>

            <p class="auth-rodape"><a href="<?= $usuario ? 'inicio.php' : 'login.php' ?>"><?= $usuario ? 'Voltar para o início' : 'Voltar para o login' ?></a></p>
        <?php endif; ?>
<?php fecharPaginaAuth(); ?>
