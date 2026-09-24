# Instala o SnakeCode para o usuário atual (sem precisar de administrador):
#   - copia para %LOCALAPPDATA%\Programs\SnakeCode (troca atômica: nova pasta pronta antes de remover a antiga)
#   - adiciona a pasta ao PATH do usuário (comando "snakecode" em qualquer terminal novo)
#   - cria um atalho no Menu Iniciar (abre no Windows Terminal quando disponível)
$ErrorActionPreference = 'Stop'

$source = Split-Path -Parent $MyInvocation.MyCommand.Path
$target = Join-Path $env:LOCALAPPDATA 'Programs\SnakeCode'
$staging = "$target.new"
$backup = "$target.old"

foreach ($dir in @($staging, $backup)) {
    if (Test-Path $dir) { Remove-Item $dir -Recurse -Force }
}
New-Item -ItemType Directory -Path $staging -Force | Out-Null
Copy-Item -Path (Join-Path $source '*') -Destination $staging -Recurse -Force

# Arquivos extraídos de um zip baixado carregam a "marca da web"; remove para o php.exe não ser bloqueado.
Get-ChildItem $staging -Recurse -File | Unblock-File

if (Test-Path $target) { Move-Item $target $backup }
Move-Item $staging $target
if (Test-Path $backup) { Remove-Item $backup -Recurse -Force }

$userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
if (-not $userPath) { $userPath = '' }
if (-not (($userPath -split ';') -contains $target)) {
    $newPath = (($userPath.TrimEnd(';')), $target | Where-Object { $_ }) -join ';'
    [Environment]::SetEnvironmentVariable('Path', $newPath, 'User')
}

$shortcutPath = Join-Path ([Environment]::GetFolderPath('Programs')) 'SnakeCode.lnk'
$shell = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($shortcutPath)
$terminal = Get-Command wt.exe -ErrorAction SilentlyContinue
if ($terminal) {
    $shortcut.TargetPath = $terminal.Source
    $shortcut.Arguments = "`"$target\snakecode.cmd`""
} else {
    $shortcut.TargetPath = Join-Path $env:SystemRoot 'System32\cmd.exe'
    $shortcut.Arguments = "/k `"`"$target\snakecode.cmd`"`""
}
$shortcut.WorkingDirectory = $env:USERPROFILE
$shortcut.IconLocation = "$target\php\php.exe,0"
$shortcut.Description = 'SnakeCode'
$shortcut.Save()

Write-Host ''
Write-Host "SnakeCode instalado em $target" -ForegroundColor Green
Write-Host 'Abra um terminal NOVO (Windows Terminal, PowerShell ou cmd) e rode:'
Write-Host '    snakecode                      (fundo com o código do próprio jogo)'
Write-Host '    snakecode C:\caminho\projeto   (fundo com o código do seu projeto)'
Write-Host 'Ou use o atalho "SnakeCode" no Menu Iniciar.'
