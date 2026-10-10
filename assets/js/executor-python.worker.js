/* Executor isolado de Python via Pyodide. O código roda num Web Worker. */
var pyodidePromise = null;
function obterPyodide() {
    if (!pyodidePromise) {
        importScripts('https://cdn.jsdelivr.net/pyodide/v0.27.7/full/pyodide.js');
        pyodidePromise = loadPyodide();
    }
    return pyodidePromise;
}

/* Converte o traceback técnico do Pyodide em uma mensagem curta, parecida
 * com a que um editor Python apresenta, sem esconder o tipo do erro nem a linha. */
function formatarErroPython(erro, codigo) {
    var texto = String((erro && erro.message) || erro || 'Erro ao executar o código.');
    var linhasCodigo = String(codigo || '').split('\n');
    var linhaErro = null;
    var correspondenciaLinha = texto.match(/File ["']<codigo-aluno>["'], line (\d+)/);

    if (correspondenciaLinha) {
        linhaErro = parseInt(correspondenciaLinha[1], 10);
    }

    // A última linha do traceback contém normalmente o tipo e a descrição úteis.
    var linhasErro = texto.split('\n').map(function (linha) { return linha.trim(); }).filter(Boolean);
    var mensagem = '';
    for (var i = linhasErro.length - 1; i >= 0; i--) {
        var encontrada = linhasErro[i].match(/(?:^|: )(SyntaxError|IndentationError|TabError|NameError|TypeError|ValueError|ZeroDivisionError|IndexError|KeyError|AttributeError|ImportError|ModuleNotFoundError|RuntimeError|OverflowError|AssertionError|EOFError):?\s*(.*)$/);
        if (encontrada) {
            mensagem = encontrada[1] + (encontrada[2] ? ': ' + encontrada[2] : '');
            break;
        }
    }

    // SyntaxError pode informar a linha no próprio texto, sem o frame usual.
    if (!linhaErro) {
        var linhaSintaxe = texto.match(/line (\d+)/i);
        if (linhaSintaxe) linhaErro = parseInt(linhaSintaxe[1], 10);
    }

    if (!mensagem) {
        mensagem = 'Erro ao executar o código. Verifique a sintaxe e os valores utilizados.';
    }

    if (linhaErro && linhaErro > linhasCodigo.length) linhaErro = null;
    return { mensagem: mensagem, linha: linhaErro };
}

self.onmessage = async function (evento) {
    var dados = evento.data || {};
    var codigo = String(dados.codigo || '');
    var casos = Array.isArray(dados.casos) ? dados.casos : [];
    try {
        var pyodide = await obterPyodide();
        var resultados = [];
        for (var i = 0; i < casos.length; i++) {
            var caso = casos[i];
            try {
                pyodide.globals.set('_codigo_aluno', codigo);
                pyodide.globals.set('_entrada_caso', caso.entrada || '');
                // Monta o código Python linha a linha. Assim evitamos que escapes
                // como \n em uma template string JavaScript virem quebras de linha
                // dentro de literais Python (o que causava SyntaxError).
                var codigoExecutorPython = [
                    'import io, sys, contextlib',
                    '_saida_devfox = io.StringIO()',
                    "_ambiente_devfox = {'__name__': '__main__'}",
                    'with contextlib.redirect_stdout(_saida_devfox):',
                    "    exec(compile(_codigo_aluno, '<codigo-aluno>', 'exec'), _ambiente_devfox)",
                    '    if _entrada_caso.strip():',
                    "        _valor_devfox = eval(compile(_entrada_caso, '<caso>', 'eval'), _ambiente_devfox)",
                    '        if _valor_devfox is not None:',
                    '            print(_valor_devfox)',
                    '_saida_devfox.getvalue()'
                ].join('\n');
                var saida = await pyodide.runPythonAsync(codigoExecutorPython);
                // Remove apenas a última quebra de linha gerada por print(),
                // preservando as quebras internas da saída do aluno.
                var textoSaida = String(saida).replace(/\n$/, '');
                resultados.push({ caso_id: caso.id, saida: textoSaida, erro: null, linha: null });
            } catch (erro) {
                var detalheErro = formatarErroPython(erro, codigo);
                resultados.push({ caso_id: caso.id, saida: null, erro: detalheErro.mensagem, linha: detalheErro.linha });
            }
        }
        self.postMessage({ resultados: resultados });
    } catch (erroInicializacao) {
        self.postMessage({ erro: 'Não foi possível carregar o Python. Verifique sua conexão e tente novamente.', resultados: [] });
    }
};
