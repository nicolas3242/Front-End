/**
 * assets/js/conquistas.js — aviso de conquista desbloqueada.
 * -----------------------------------------------------------------------------
 * Antes disto, conquistar algo não produzia nenhum sinal: a medalha simplesmente
 * aparecia acesa no perfil da próxima vez que o aluno entrasse lá. Numa
 * plataforma cuja proposta é gamificação, recompensa sem feedback imediato é
 * recompensa que não existe.
 *
 * O aviso chega por dois caminhos, porque há dois momentos diferentes:
 *
 *   - tela de desafio  → a resposta da API traz as conquistas e este arquivo as
 *                        mostra na hora, sem recarregar a página;
 *   - prova e login    → são POSTs que redirecionam, então o servidor deixa as
 *                        conquistas na sessão e o rodapé as imprime num
 *                        <script type="application/json">, lido aqui no load.
 *
 * Quem DECIDE o desbloqueio é sempre o servidor (includes/conquistas.php).
 * Este arquivo só exibe.
 */

(function () {
    'use strict';

    var SEGUNDOS_NA_TELA = 8;

    /** Cria (uma vez) o canto onde os avisos se empilham. */
    function areaDeAvisos() {
        var area = document.getElementById('avisosConquista');
        if (!area) {
            area = document.createElement('div');
            area.id = 'avisosConquista';
            area.className = 'avisos-conquista';
            area.setAttribute('role', 'status');
            area.setAttribute('aria-live', 'polite');
            document.body.appendChild(area);
        }
        return area;
    }

    function escapar(texto) {
        var d = document.createElement('div');
        d.textContent = (texto === null || texto === undefined) ? '' : String(texto);
        return d.innerHTML;
    }

    function exibirUma(conquista) {
        var aviso = document.createElement('div');
        aviso.className = 'aviso-conquista aviso-conquista-' + (conquista.raridade || 'bronze');
        aviso.innerHTML =
            '<div class="aviso-conquista-medalha">' +
                '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor"' +
                ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z"/>' +
                '</svg>' +
            '</div>' +
            '<div class="aviso-conquista-texto">' +
                '<small>Conquista desbloqueada</small>' +
                '<strong>' + escapar(conquista.titulo) + '</strong>' +
                '<span>' + escapar(conquista.descricao) + '</span>' +
            '</div>' +
            '<button type="button" class="aviso-conquista-fechar" aria-label="Fechar">&times;</button>';

        areaDeAvisos().appendChild(aviso);

        // O reflow força o navegador a aplicar o estado inicial antes da
        // transição — sem isso o aviso aparece pronto, sem animar.
        void aviso.offsetWidth;
        aviso.classList.add('visivel');

        function sair() {
            aviso.classList.remove('visivel');
            setTimeout(function () {
                if (aviso.parentNode) {
                    aviso.parentNode.removeChild(aviso);
                }
            }, 300);
        }

        aviso.querySelector('.aviso-conquista-fechar').addEventListener('click', sair);
        setTimeout(sair, SEGUNDOS_NA_TELA * 1000);
    }

    /**
     * Mostra uma lista de conquistas, uma a cada meio segundo.
     *
     * O intervalo existe porque é comum desbloquear duas de uma vez (concluir o
     * primeiro módulo dá "Código Iniciante" e "Primeiro Desafio" juntas), e três
     * avisos surgindo no mesmo instante viram um borrão.
     */
    function mostrar(conquistas) {
        if (!Array.isArray(conquistas) || conquistas.length === 0) {
            return;
        }

        conquistas.forEach(function (conquista, i) {
            setTimeout(function () { exibirUma(conquista); }, i * 500);
        });
    }

    window.DevFoxConquistas = { mostrar: mostrar };

    // Conquistas deixadas pelo servidor na sessão (prova, login).
    document.addEventListener('DOMContentLoaded', function () {
        var dados = document.getElementById('dadosConquistas');
        if (!dados) {
            return;
        }
        try {
            mostrar(JSON.parse(dados.textContent) || []);
        } catch (e) {
            /* JSON quebrado não pode derrubar a página inteira por causa de um aviso */
        }
    });
})();
