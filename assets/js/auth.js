/**
 * assets/js/auth.js — pequenas interações das telas de login e cadastro.
 * Mostrar/ocultar senha, conforme previsto no documento do projeto.
 */

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.botao-olho').forEach(function (botao) {
        botao.addEventListener('click', function () {
            var campo = document.getElementById(botao.dataset.alvo);
            if (!campo) {
                return;
            }

            var oculto = campo.type === 'password';
            campo.type = oculto ? 'text' : 'password';

            // Os dois ícones já estão no HTML: trocar o textContent aqui
            // apagaria o SVG e deixaria o botão vazio.
            var verIcone     = botao.querySelector('.olho-ver');
            var ocultarIcone = botao.querySelector('.olho-ocultar');
            if (verIcone && ocultarIcone) {
                verIcone.hidden     = oculto;
                ocultarIcone.hidden = !oculto;
            }

            botao.setAttribute('aria-label', oculto ? 'Ocultar a senha' : 'Mostrar a senha');
        });
    });
});
