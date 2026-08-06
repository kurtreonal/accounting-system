$projectRoot = $PSScriptRoot
$publicRoot = Join-Path $projectRoot 'public'
$phpConfig = Join-Path $projectRoot '.php-local.ini'
$router = Join-Path $projectRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'

if (-not (Test-Path $phpConfig)) {
    throw "Missing $phpConfig. Create it with the required PHP extensions before starting the server."
}

Push-Location $publicRoot
try {
    & php -c $phpConfig -S 127.0.0.1:8000 $router
}
finally {
    Pop-Location
}
