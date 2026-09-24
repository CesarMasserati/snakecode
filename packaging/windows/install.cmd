@echo off
rem Duplo clique para instalar o SnakeCode (executa o install.ps1 sem alterar a politica do sistema).
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install.ps1"
pause
