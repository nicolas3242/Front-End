/**
 * assets/js/sino.js — abre e fecha o painel de notificações do topo.
 * -----------------------------------------------------------------------------
 * As notificações vêm do servidor, já dentro do HTML (includes/header.php).
 * Este arquivo só cuida de duas coisas:
 *
 * 1. Abrir/fechar o painel (clique no sino, clique fora, tecla Esc).
 *
 * 2. O PONTO de "novidade". Ele aparece quando o evento mais novo é mais
 *    recente do que a última vez que o sino foi aberto neste navegador.
 *    Essa marca fica no localStorage — é só um lembrete visual de "já vi",
 *    não um dado do sistema. Se o navegador não guardar (aba anônima), o pior
 *    que acontece é o ponto aparecer de novo; nada se perde.
 */

document.addEventListener('DOMContentLoaded', function () {
    var sino = document.querySelector('.sino');
    if (!sino) {
        return;
    }

    var botao  = document.getElementById('botaoSino');
    var painel = document.getElementById('painelSino');
    var ponto  = sino.querySelector('.sino-ponto');

    var chave    = 'devfox-sino-visto-' + sino.dataset.usuario;
    var maisNova = parseInt(sino.dataset.maisNova, 10) || 0;

    function lerVisto() {
        try { return parseInt(localStorage.getItem(chave), 10) || 0; } catch (e) { return 0; }
    }

    function gravarVisto(valor) {
        try { localStorage.setItem(chave, String(valor)); } catch (e) { /* sem armazenamento: tudo bem */ }
    }

    if (maisNova > 0 && maisNova > lerVisto()) {
        ponto.hidden = false;
    }

    function abrir() {
        painel.hidden = false;
        botao.setAttribute('aria-expanded', 'true');
        ponto.hidden = true;
        gravarVisto(maisNova);
    }

    function fechar() {
        painel.hidden = true;
        botao.setAttribute('aria-expanded', 'false');
    }

    botao.addEventListener('click', function (evento) {
        evento.stopPropagation();
        painel.hidden ? abrir() : fechar();
    });

    document.addEventListener('click', function (evento) {
        if (!painel.hidden && !sino.contains(evento.target)) {
            fechar();
        }
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape' && !painel.hidden) {
            fechar();
            botao.focus();
        }
    });
});
