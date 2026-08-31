@echo off
setlocal
cd /d "%~dp0"

echo ========================================
echo   Agendamento AGEHAB - Seed de teste
echo ========================================
echo.

where php >nul 2>nul
if errorlevel 1 (
    echo ERRO: PHP nao foi encontrado no PATH do Windows.
    echo.
    echo Teste no Prompt de Comando:
    echo   php -v
    echo.
    echo Se voce usa XAMPP, pode executar diretamente:
    echo   C:\xampp\php\php.exe database\seeds\seed_cpfs.php
    echo.
    pause
    exit /b 1
)

if not exist ".env" (
    echo ERRO: O arquivo .env nao existe.
    echo.
    echo Crie-o a partir do exemplo:
    echo   copy .env.example .env
    echo.
    echo Depois confira DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME e DB_PASSWORD.
    pause
    exit /b 1
)

echo Executando seed...
echo.
php database\seeds\seed_cpfs.php

if errorlevel 1 (
    echo.
    echo O seed terminou com erro. Leia a mensagem acima.
    pause
    exit /b 1
)

echo.
echo Seed concluido com sucesso.
pause
