/**
 * assets/js/perfil.js — abre e fecha a janela "Alterar avatar".
 * Usa o <dialog> nativo: o navegador já cuida do foco, do Esc e do fundo.
 */
document.addEventListener('DOMContentLoaded', function () {
    var botao   = document.getElementById('btnAvatar');
    var dialogo = document.getElementById('dialogoAvatar');
    if (!botao || !dialogo || typeof dialogo.showModal !== 'function') {
        return;
    }

    botao.addEventListener('click', function () { dialogo.showModal(); });

    dialogo.querySelectorAll('[data-fechar]').forEach(function (b) {
        b.addEventListener('click', function () { dialogo.close(); });
    });

    // Clique no fundo escuro (fora do conteúdo) fecha.
    dialogo.addEventListener('click', function (evento) {
        if (evento.target === dialogo) { dialogo.close(); }
    });
});
