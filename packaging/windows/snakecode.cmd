@echo off
rem SnakeCode - lancador para Windows.
rem Usa o PHP portatil desta pasta, com php.ini proprio: nao interfere em outro PHP instalado.
setlocal
set "HERE=%~dp0"
"%HERE%php\php.exe" -c "%HERE%php\php.ini" -d extension_dir="%HERE%php\ext" "%HERE%snakecode.phar" %*
set "CODE=%ERRORLEVEL%"
if "%CODE%"=="-1073741515" (
    echo.
    echo O PHP embarcado precisa do Microsoft Visual C++ Redistributable 2015-2022 x64:
    echo https://aka.ms/vs/17/release/vc_redist.x64.exe
)
exit /b %CODE%
