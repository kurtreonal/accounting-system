$projectRoot = Split-Path -Parent $PSScriptRoot
$herdBin = Join-Path $env:USERPROFILE '.config\herd-lite\bin'
$herdPhp = Join-Path $herdBin 'php.exe'

if (-not (Test-Path -LiteralPath $herdPhp)) {
    throw "Herd Lite PHP was not found at $herdPhp"
}

$env:Path = "$herdBin;$env:Path"
Remove-Item Env:PHP_INI_SCAN_DIR -ErrorAction SilentlyContinue
Remove-Item Env:PHP_BINARY -ErrorAction SilentlyContinue
Remove-Item Env:PHP_PATH -ErrorAction SilentlyContinue

Push-Location $projectRoot

try {
    & npx.cmd concurrently `
        -c '#93c5fd,#fdba74' `
        'php artisan serve' `
        'npm run dev' `
        '--names=server,vite'

    exit $LASTEXITCODE
} finally {
    Pop-Location
}
