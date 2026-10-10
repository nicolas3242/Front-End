/**
 * tools/validar_conteudo.js
 * -----------------------------------------------------------------------------
 * Roda a solução de referência de CADA exercício de código contra os próprios
 * casos de teste, usando exatamente a mesma semântica de
 * assets/js/executor.worker.js e a mesma normalização de
 * api/processar_desafio.php.
 *
 * Existe por um motivo só: um gabarito errado no banco é invisível até o dia
 * em que um aluno (ou a banca) tenta resolver o exercício e o sistema diz que
 * a resposta certa está errada.
 */

const fs = require('fs');
const { execFileSync } = require('child_process');
const dados = JSON.parse(fs.readFileSync(__dirname + '/dados.json', 'utf8'));

// Exercícios de HTML/CSS são conferidos num navegador de verdade, por
// validar_html.js (chamado no fim). Aqui, as seções 1 e 2 tratam só de JS.
const codigoJs = dados.codigo.filter(a => a.linguagem !== 'html');

let falhas = 0;
const erro = (msg) => { falhas++; console.log('  ✘ ' + msg); };

/* ---- cópia fiel de executor.worker.js ---- */

function formatarValor(valor) {
    if (valor === undefined) return 'undefined';
    if (valor === null) return 'null';
    if (typeof valor === 'string') return valor;
    if (typeof valor === 'number' || typeof valor === 'boolean') return String(valor);
    try { return JSON.stringify(valor); } catch (e) { return String(valor); }
}

function executarCaso(codigo, entrada) {
    const impressos = [];
    const consoleFalso = {
        log: function () {
            impressos.push(Array.prototype.map.call(arguments, formatarValor).join(' '));
        }
    };
    consoleFalso.info = consoleFalso.warn = consoleFalso.error = consoleFalso.debug = consoleFalso.log;

    const temEntrada = typeof entrada === 'string' && entrada.trim() !== '';
    const corpo = temEntrada
        ? codigo + '\n;return (' + entrada + ');'
        : codigo + '\n;return undefined;';

    const retorno = new Function('console', corpo)(consoleFalso);
    return temEntrada ? formatarValor(retorno) : impressos.join('\n');
}

/* ---- cópia fiel de normalizar() de processar_desafio.php ---- */

function normalizar(valor) {
    return String(valor === null || valor === undefined ? '' : valor)
        .trim()
        .replace(/\s+/g, ' ')
        .toLowerCase();
}

/* =========================================================================
 * 1. Solução de referência tem que passar em TODOS os casos
 * ====================================================================== */

console.log('\nSOLUCAO DE REFERENCIA x CASOS DE TESTE');

codigoJs.forEach(function (a) {
    if (!a.solucao_exemplo || a.solucao_exemplo.trim() === '') {
        erro('#' + a.id + ' "' + a.titulo + '": sem solucao_exemplo');
        return;
    }

    a.casos.forEach(function (caso, i) {
        let obtido;
        try {
            obtido = executarCaso(a.solucao_exemplo, caso.entrada);
        } catch (e) {
            erro('#' + a.id + ' "' + a.titulo + '" caso ' + (i + 1) + ': a solucao QUEBROU — ' + e.message);
            return;
        }

        if (normalizar(obtido) !== normalizar(caso.saida_esperada)) {
            erro('#' + a.id + ' "' + a.titulo + '" caso ' + (i + 1) +
                 ' [' + (caso.entrada || 'console.log') + ']: esperado ' +
                 JSON.stringify(caso.saida_esperada) + ', obtido ' + JSON.stringify(obtido));
        }
    });
});

/* =========================================================================
 * 2. O código INICIAL não pode já passar (senão o exercício não exercita)
 * ====================================================================== */

console.log('\nCODIGO INICIAL NAO PODE JA ESTAR CERTO');

/**
 * O código inicial roda num processo SEPARADO, com limite de tempo: alguns
 * exercícios de "corrigir o erro" têm, de propósito, um laço infinito no
 * código de partida. Executá-lo aqui dentro travaria o validador — é a mesma
 * razão pela qual o navegador usa um Web Worker.
 */
function rodarComLimite(codigo, entrada) {
    try {
        const saida = execFileSync('node', [__dirname + '/rodar_isolado.js',
            JSON.stringify({ codigo: codigo, entrada: entrada })],
            { timeout: 3000, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
        return JSON.parse(saida);
    } catch (e) {
        return { saida: null, erro: 'travou ou nao executou' };
    }
}

codigoJs.forEach(function (a) {
    if (!a.codigo_inicial || a.codigo_inicial.trim() === '') return;

    let todosPassam = true;
    for (const caso of a.casos) {
        const r = rodarComLimite(a.codigo_inicial, caso.entrada);
        if (r.erro !== null || normalizar(r.saida) !== normalizar(caso.saida_esperada)) {
            todosPassam = false;   // não passa: ótimo, é um exercício de fato
            break;
        }
    }

    if (todosPassam) {
        erro('#' + a.id + ' "' + a.titulo + '": o codigo inicial JA passa em todos os casos');
    }
});

/* =========================================================================
 * 3. Um caso oculto tem que realmente distinguir
 *    (nao adianta o caso oculto ser identico a um visivel)
 * ====================================================================== */

console.log('\nCASOS OCULTOS');

dados.codigo.forEach(function (a) {
    const ocultos = a.casos.filter(c => Number(c.visivel) === 0);
    const visiveis = a.casos.filter(c => Number(c.visivel) === 1);

    /**
     * Exercício de comportamento único — uma função sem parâmetro, ou um
     * console.log fixo — não tem caso oculto a esconder: a resposta certa É o
     * literal, e "chumbar" é justamente o que se pede. Exigir caso oculto aí
     * só produziria um caso repetido, que não protege de nada.
     */
    const entradas = new Set(a.casos.map(c => normalizar(c.entrada)));
    const comportamentoUnico = entradas.size === 1;

    if (comportamentoUnico) {
        if (a.casos.length !== 1) {
            erro('#' + a.id + ' "' + a.titulo + '": todos os casos usam a mesma entrada — ' +
                 'ou varie as entradas, ou deixe um caso só');
        }
        return;
    }

    if (ocultos.length === 0) {
        erro('#' + a.id + ' "' + a.titulo + '": nenhum caso oculto');
    }
    if (visiveis.length === 0) {
        erro('#' + a.id + ' "' + a.titulo + '": nenhum caso visivel (o aluno nao ve o que se pede)');
    }
    ocultos.forEach(function (o) {
        if (visiveis.some(v => normalizar(v.entrada) === normalizar(o.entrada))) {
            erro('#' + a.id + ' "' + a.titulo + '": caso oculto repete a entrada de um visivel');
        }
    });
});

/* =========================================================================
 * 4. Quizzes: exatamente UMA alternativa correta
 * ====================================================================== */

console.log('\nQUIZZES');

dados.quiz.forEach(function (a) {
    const certas = a.alternativas.filter(alt => Number(alt.correta) === 1).length;
    if (certas !== 1) {
        erro('#' + a.id + ' "' + a.titulo + '": ' + certas + ' alternativas corretas (tem que ser 1)');
    }
    if (a.alternativas.length < 3) {
        erro('#' + a.id + ' "' + a.titulo + '": so ' + a.alternativas.length + ' alternativas');
    }
    const textos = a.alternativas.map(alt => normalizar(alt.texto));
    if (new Set(textos).size !== textos.length) {
        erro('#' + a.id + ' "' + a.titulo + '": alternativas repetidas');
    }
});

/* =========================================================================
 * 5. Provas: toda questao aponta um modulo de AULA da mesma trilha
 * ====================================================================== */

console.log('\nPROVAS');

const porId = {};
dados.modulos.forEach(m => { porId[m.id] = m; });

dados.quiz.forEach(function (a) {
    const modulo = porId[a.modulo_id];
    if (!modulo || modulo.tipo !== 'prova') return;

    if (!a.modulo_referencia_id) {
        erro('#' + a.id + ' "' + a.titulo + '": questao de prova sem modulo_referencia_id');
        return;
    }
    const ref = porId[a.modulo_referencia_id];
    if (!ref) {
        erro('#' + a.id + ': modulo_referencia_id ' + a.modulo_referencia_id + ' nao existe');
    } else if (ref.tipo !== 'aula') {
        erro('#' + a.id + ': aponta para um modulo do tipo "' + ref.tipo + '", deveria ser aula');
    } else if (ref.trilha_id !== modulo.trilha_id) {
        erro('#' + a.id + ': aponta para modulo de OUTRA trilha');
    } else if (ref.ordem >= modulo.ordem) {
        erro('#' + a.id + ': aponta para um modulo que vem DEPOIS da prova');
    }
});

/* =========================================================================
 * 6. Modulos: ordem sequencial e prova por ultimo
 * ====================================================================== */

console.log('\nESTRUTURA DAS TRILHAS');

const porTrilha = {};
dados.modulos.forEach(m => {
    (porTrilha[m.trilha_id] = porTrilha[m.trilha_id] || []).push(m);
});

Object.keys(porTrilha).forEach(function (tid) {
    const mods = porTrilha[tid].sort((a, b) => a.ordem - b.ordem);

    mods.forEach(function (m, i) {
        if (m.ordem !== i + 1) {
            erro('trilha ' + tid + ': modulo "' + m.titulo + '" com ordem ' + m.ordem + ', esperado ' + (i + 1));
        }
        if (Number(m.atividades) === 0) {
            erro('trilha ' + tid + ': modulo "' + m.titulo + '" esta vazio');
        }
    });

    const provas = mods.filter(m => m.tipo === 'prova');
    if (provas.length !== 1) {
        erro('trilha ' + tid + ': ' + provas.length + ' provas (esperado 1)');
    } else if (provas[0].ordem !== mods.length) {
        erro('trilha ' + tid + ': a prova nao e o ultimo modulo');
    }
});

/* =========================================================================
 * 7. HTML e CSS: solução x casos e código inicial, num navegador de verdade
 * ====================================================================== */

if (dados.codigo.some(a => a.linguagem === 'html')) {
    try {
        execFileSync('node', [__dirname + '/validar_html.js'], { stdio: 'inherit' });
    } catch (e) {
        falhas++;
    }
}

/* ---------------------------------------------------------------------- */

console.log('\n' + (falhas === 0
    ? '>>> CONTEUDO VALIDADO: ' + dados.codigo.length + ' exercicios de codigo e ' +
      dados.quiz.length + ' quizzes, sem nenhum problema'
    : '>>> ' + falhas + ' PROBLEMA(S) ENCONTRADO(S)'));

process.exit(falhas === 0 ? 0 : 1);
