<?php
/**
 * config/conexao.exemplo.php — MODELO de configuração.
 *
 * COMO USAR: copie este arquivo para `config/conexao.php` e ajuste os valores
 * conforme a sua máquina. O `conexao.php` fica fora do Git (veja o
 * .gitignore), porque é ele que carrega as credenciais reais.
 *
 *     copy config\conexao.exemplo.php config\conexao.php
 *
 * Os valores abaixo já são os padrões do XAMPP e costumam funcionar sem
 * nenhuma alteração num ambiente local.
 */

$host = getenv('DEVFOX_DB_HOST') ?: 'localhost';
$db   = getenv('DEVFOX_DB_NAME') ?: 'devfox';
$user = getenv('DEVFOX_DB_USER') ?: 'root';
$pass = getenv('DEVFOX_DB_PASS') ?: '';   // senha padrão do XAMPP é vazia

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // Prepared statements de verdade, executados pelo banco em vez de
        // montados como texto pelo PHP. É a proteção mais sólida contra SQL
        // injection. Atenção: com esta opção ligada, o MySQL não aceita o
        // mesmo parâmetro nomeado repetido na mesma consulta — use um
        // placeholder para cada ocorrência.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('DevFox — falha na conexão com o banco: ' . $e->getMessage());
    die('Não foi possível conectar ao banco de dados. Verifique se o MySQL do XAMPP está ligado.');
}
