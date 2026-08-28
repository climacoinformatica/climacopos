@echo off
REM =====================================================================
REM  AGENTE CLIMACO POS
REM
REM  Arranca el agente. Deja esta ventana abierta mientras el salon
REM  este trabajando, o instalalo como servicio (ver LEEME.md).
REM =====================================================================

title Agente CLIMACO POS

cd /d "%~dp0"

REM Si tienes PHP portable en esta misma carpeta, descomenta la linea
REM siguiente y comenta la de despues.
REM set PHP=%~dp0php\php.exe
set PHP=php

:inicio
echo.
echo Iniciando Agente CLIMACO POS...
echo.

"%PHP%" agente.php

echo.
echo El agente se ha detenido. Reintentando en 10 segundos...
echo Cierra esta ventana para pararlo del todo.
timeout /t 10 /nobreak > nul
goto inicio
