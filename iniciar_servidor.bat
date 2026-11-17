@echo off
title -Tributo! - Inicializador do Sistema
chcp 65001 > nul
cls
echo ============================================================
echo               INICIANDO PLATAFORMA -TRIBUTO!
echo ============================================================
echo.

:: 1. Verificar/Iniciar Banco de Dados MySQL
echo [1/3] Verificando Banco de Dados MySQL...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] Banco de Dados MySQL ja esta rodando!
) else (
    echo [*] Iniciando MySQL do XAMPP...
    start /B "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --console
    timeout /t 3 /nobreak > nul
    echo [OK] MySQL iniciado com sucesso!
)

:: 2. Iniciar Servidor Web PHP
echo.
echo [2/3] Iniciando Servidor Web PHP na porta 8000...
cd /d "d:\IA\Antigravity\contabilidadeTributaria"
start "Servidor PHP -Tributo!" /MIN "C:\xampp\php\php.exe" -S 0.0.0.0:8000 -t public
echo [OK] Servidor PHP rodando em http://localhost:8000

:: 3. Iniciar Tunel Cloudflare para Celular / Internet
echo.
echo [3/3] Criando link seguro para Celular e Internet...
echo ============================================================
echo  Copie o link HTTPS (.trycloudflare.com) gerado abaixo:
echo ============================================================
echo.
"C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel --protocol http2 --url http://127.0.0.1:8000

pause
