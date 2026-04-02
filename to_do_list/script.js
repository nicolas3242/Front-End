let contador = 0;

function formatarData(data) {
    let partes = data.split("-");
    return `${partes[2]}/${partes[1]}/${partes[0]}`;
}

function adicionarTarefa() {
    let tarefaInput = document.getElementById("tarefa");
    let dataInput = document.getElementById("data");

    let texto = tarefaInput.value.trim();
    let data = dataInput.value;

    if (texto === "" || texto.length < 3) {
        alert("Digite uma tarefa com pelo menos 3 caracteres!");
        return;
    }

    if (data === "") {
        alert("Escolha uma data!");
        return;
    }

    let lista = document.getElementById("lista");

    let li = document.createElement("li");

    let dataFormatada = formatarData(data);

    li.innerHTML = `
        <span>${texto} - ${dataFormatada}</span>
        <div class="botoes">
            <button class="btn btn-concluir" onclick="concluirTarefa(this)">Feito</button>
            <button class="btn btn-remover" onclick="removerTarefa(this)">Remover</button>
        </div>
    `;

    lista.appendChild(li);

    contador++;
    atualizarContador();

    tarefaInput.value = "";
    dataInput.value = "";
}

function concluirTarefa(botao) {
    let li = botao.closest("li");
    li.classList.toggle("concluida");
}

function removerTarefa(botao) {
    let li = botao.closest("li");
    li.remove();

    contador--;
    atualizarContador();
}

function atualizarContador() {
    document.getElementById("contador").innerText = "Tarefas: " + contador;
}