@echo off
REM ---------------------------------------------------------------------------
REM testes\rodar-todos.bat — roda as 16 suites automaticas do DevFox, em ordem.
REM
REM Use no Windows, com o XAMPP ligado (Apache + MySQL) e o banco ja importado.
REM   1. abra o Prompt de Comando
REM   2. cd C:\xampp\htdocs\DevFox\testes
REM   3. rodar-todos.bat
REM
REM As duas linhas abaixo dizem ONDE o site esta e COMO falar com o MySQL.
REM Ajuste se a sua instalacao for diferente.
REM ---------------------------------------------------------------------------

set DEVFOX_URL=http://localhost/DevFox
set DEVFOX_MYSQL="C:\xampp\mysql\bin\mysql.exe" -u root devfox

echo.
echo === DevFox: rodando as suites automaticas ===
echo URL:   %DEVFOX_URL%
echo MySQL: %DEVFOX_MYSQL%
echo.

set FALHAS=0

for %%T in (
    teste-worker.js
    teste-prova.js
    teste-conquistas.js
    teste-admin.js
    teste-loja.js
    teste-inicio.js
    teste-trilhas.js
    teste-conta.js
    teste-perfil.js
    teste-graves.js
    teste-medios.js
    teste-menores.js
    teste-admin-gestao.js
    teste-admin-acesso.js
    teste-editor.js
) do (
    echo -------------------------------------------------------------
    echo ^> %%T
    node %%T
    if errorlevel 1 (
        set /a FALHAS=1
        echo *** FALHOU: %%T
    )
)

echo.
echo -------------------------------------------------------------
if "%FALHAS%"=="0" (
    echo TUDO PASSOU.
) else (
    echo ALGUMA SUITE FALHOU. Role a tela para achar a linha com ***.
)
echo.
pause
