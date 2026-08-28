@echo off
REM Imprime un ticket de prueba y sale.
title Prueba de impresora - CLIMACO POS
cd /d "%~dp0"
set PHP=php
"%PHP%" agente.php --test
echo.
pause
