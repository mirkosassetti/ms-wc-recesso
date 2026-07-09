<#
.SYNOPSIS
    Windows wrapper around bin/build.php.

.DESCRIPTION
    The actual build logic lives in bin/build.php (cross-platform, ZipArchive)
    so the produced ZIP always uses forward-slash entry names. This wrapper just
    finds PHP on the PATH and runs it.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File bin\build.ps1
#>

$ErrorActionPreference = 'Stop'

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Error "PHP non trovato nel PATH. Esegui manualmente: php bin\build.php"
    exit 1
}

& php (Join-Path $PSScriptRoot 'build.php')
