# Remove o SnakeCode instalado pelo install.ps1 (pasta, entrada do PATH e atalho do Menu Iniciar).
# O recorde e as preferências ficam em %LOCALAPPDATA%\snakecode e são mantidos.
$ErrorActionPreference = 'Stop'

$target = Join-Path $env:LOCALAPPDATA 'Programs\SnakeCode'
Set-Location $env:TEMP

$userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
if ($userPath) {
    $kept = ($userPath -split ';') | Where-Object { $_ -and ($_ -ne $target) }
    [Environment]::SetEnvironmentVariable('Path', ($kept -join ';'), 'User')
}

$shortcut = Join-Path ([Environment]::GetFolderPath('Programs')) 'SnakeCode.lnk'
if (Test-Path $shortcut) { Remove-Item $shortcut -Force }
if (Test-Path $target) { Remove-Item $target -Recurse -Force }

Write-Host 'SnakeCode removido.' -ForegroundColor Green
Write-Host "Recorde e preferências continuam em $env:LOCALAPPDATA\snakecode (apague a pasta se quiser)."
