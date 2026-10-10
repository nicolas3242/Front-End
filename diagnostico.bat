@echo off
REM ---------------------------------------------------------------------------
REM diagnostico.bat — junta num arquivo so tudo que eu preciso saber para te
REM ajudar sem ficar adivinhando: versao do PHP, arquivos que existem (e a data
REM deles), estado do banco e do Git, e o fim dos logs de erro.
REM
REM COMO USAR
REM   1. coloque este arquivo em C:\xampp\htdocs\DevFox
REM   2. de dois cliques nele (ou rode pelo Prompt)
REM   3. me mande o arquivo diagnostico.txt que aparecer na mesma pasta
REM
REM O QUE ELE NAO FAZ: nao abre o config\conexao.php nem mostra senha nenhuma.
REM So diz se o arquivo existe. Nada daqui expoe credencial.
REM ---------------------------------------------------------------------------

setlocal
set RAIZ=%~dp0
set SAIDA=%RAIZ%diagnostico.txt
set MYSQL="C:\xampp\mysql\bin\mysql.exe"
set PHP="C:\xampp\php\php.exe"

echo Gerando diagnostico... aguarde.

> "%SAIDA%" echo === DIAGNOSTICO DEVFOX ===
>>"%SAIDA%" echo Data: %DATE% %TIME%
>>"%SAIDA%" echo Pasta: %RAIZ%
>>"%SAIDA%" echo.

>>"%SAIDA%" echo --- 1. PHP ---
>>"%SAIDA%" %PHP% -v 2>&1
>>"%SAIDA%" echo.

>>"%SAIDA%" echo --- 2. Arquivos principais (tamanho e data) ---
>>"%SAIDA%" dir /b /a-d "%RAIZ%*.php" 2>&1
>>"%SAIDA%" echo.
>>"%SAIDA%" echo [config]
>>"%SAIDA%" dir "%RAIZ%config" 2>&1
>>"%SAIDA%" echo [assets\js]
>>"%SAIDA%" dir "%RAIZ%assets\js" 2>&1
>>"%SAIDA%" echo [assets\vendor\codemirror]
>>"%SAIDA%" dir "%RAIZ%assets\vendor\codemirror" 2>&1
>>"%SAIDA%" echo [includes]
>>"%SAIDA%" dir "%RAIZ%includes" 2>&1
>>"%SAIDA%" echo.

>>"%SAIDA%" echo --- 3. Erros de sintaxe no PHP ---
>>"%SAIDA%" echo (silencio aqui = nenhum erro)
for %%F in ("%RAIZ%*.php") do @%PHP% -l "%%F" 2>&1 | find /v "No syntax errors" >> "%SAIDA%"
for %%F in ("%RAIZ%includes\*.php") do @%PHP% -l "%%F" 2>&1 | find /v "No syntax errors" >> "%SAIDA%"
>>"%SAIDA%" echo.

>>"%SAIDA%" echo --- 4. Banco de dados ---
>>"%SAIDA%" %MYSQL% -u root -N -B -e "SELECT CONCAT('tabelas=', COUNT(*)) FROM information_schema.tables WHERE table_schema='devfox';" 2>&1
>>"%SAIDA%" %MYSQL% -u root -N -B -e "SELECT CONCAT('colunas da v10 encontradas=', COUNT(*), ' de 3') FROM information_schema.columns WHERE table_schema='devfox' AND ((table_name='usuarios' AND column_name='ativo') OR (table_name='trilhas' AND column_name='arquivada') OR (table_name='conquistas' AND column_name='regra_tipo'));" 2>&1
>>"%SAIDA%" %MYSQL% -u root -N -B -e "SELECT CONCAT('admin_registro existe=', COUNT(*)) FROM information_schema.tables WHERE table_schema='devfox' AND table_name='admin_registro';" 2>&1
>>"%SAIDA%" %MYSQL% -u root devfox -N -B -e "SELECT CONCAT('trilhas=',(SELECT COUNT(*) FROM trilhas),' modulos=',(SELECT COUNT(*) FROM modulos),' atividades=',(SELECT COUNT(*) FROM atividades),' alunos=',(SELECT COUNT(*) FROM usuarios WHERE tipo='aluno'),' admins=',(SELECT COUNT(*) FROM usuarios WHERE tipo='admin'),' submissoes=',(SELECT COUNT(*) FROM submissoes),' concluidas=',(SELECT COUNT(*) FROM atividades_concluidas));" 2>&1
>>"%SAIDA%" echo.

>>"%SAIDA%" echo --- 5. Git ---
>>"%SAIDA%" git -C "%RAIZ%." remote -v 2>&1
>>"%SAIDA%" git -C "%RAIZ%." status -sb 2>&1
>>"%SAIDA%" git -C "%RAIZ%." log --oneline -5 2>&1
>>"%SAIDA%" echo.

>>"%SAIDA%" echo --- 6. Ultimas linhas do log de erro do Apache ---
>>"%SAIDA%" powershell -NoProfile -Command "if (Test-Path 'C:\xampp\apache\logs\error.log') { Get-Content 'C:\xampp\apache\logs\error.log' -Tail 25 } else { 'sem log em C:\xampp\apache\logs\error.log' }" 2>&1
>>"%SAIDA%" echo.

>>"%SAIDA%" echo --- 7. Ultimas linhas do logs\emails.log do projeto ---
>>"%SAIDA%" powershell -NoProfile -Command "if (Test-Path '%RAIZ%logs\emails.log') { Get-Content '%RAIZ%logs\emails.log' -Tail 10 } else { 'nenhum e-mail registrado ainda' }" 2>&1

echo.
echo Pronto: %SAIDA%
echo Abra o arquivo, confira que nao tem nada pessoal e me mande.
echo.
pause
