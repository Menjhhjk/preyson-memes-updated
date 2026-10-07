param(
    [string]$Php = '',
    [string]$MySql = 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqld.exe',
    [string]$Xampp = 'C:\xampp'
)
$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
$mysqlData = Join-Path $projectRoot 'storage\mysql\data'
$logDirectory = Join-Path $projectRoot 'storage\logs'

if (-not $Php) {
    foreach ($candidate in @("$env:USERPROFILE\.config\herd\bin\php84\php.exe", "$env:LOCALAPPDATA\Herd\bin\php84\php.exe")) {
        if (Test-Path -LiteralPath $candidate) { $Php = $candidate; break }
    }
    if (-not $Php) {
        $onPath = Get-Command php -ErrorAction SilentlyContinue
        if ($onPath) { $Php = $onPath.Source }
    }
}
if (-not $Php -or -not (Test-Path -LiteralPath $Php)) {
    throw 'PHP not found. Install Laravel Herd, add php to PATH, or pass -Php "C:\path\to\php.exe".'
}

function Read-EnvValue([string]$Name, [string]$Default = '') {
    $envFile = Join-Path $projectRoot '.env'
    if (-not (Test-Path -LiteralPath $envFile)) { return $Default }
    foreach ($line in Get-Content -LiteralPath $envFile) {
        if ($line -match '^\s*#') { continue }
        if ($line -match ('^\s*' + [regex]::Escape($Name) + '\s*=\s*(.*)$')) {
            return $matches[1].Trim().Trim('"').Trim("'")
        }
    }
    return $Default
}

$dbHost = Read-EnvValue 'DB_HOST' '127.0.0.1'
$localDb = $dbHost -in @('127.0.0.1', 'localhost', '::1')
$localStorage = (Read-EnvValue 'STORAGE_DRIVER' 'local') -ne 's3'

if ($localDb) {
    if (-not (Test-Path -LiteralPath $MySql)) { throw "Missing executable: $MySql" }
    if (-not (Test-Path -LiteralPath (Join-Path $Xampp 'php\php.exe'))) { throw "Missing executable: $Xampp\php\php.exe" }
    if (-not (Test-Path -LiteralPath $mysqlData)) {
        throw 'Project MySQL has not been initialized. See README.md for setup.'
    }
}
function Test-LocalPort([int]$Port) {
    $client = New-Object Net.Sockets.TcpClient
    try { $client.Connect('127.0.0.1', $Port); return $true } catch { return $false } finally { $client.Dispose() }
}
function Wait-LocalPort([int]$Port) {
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        if (Test-LocalPort $Port) { return }
        Start-Sleep -Milliseconds 300
    }
    throw "Service on port $Port failed to start. Check storage/logs."
}
if ($localDb -and -not (Test-LocalPort 3308)) {
    $mysqlBase = Split-Path (Split-Path $MySql -Parent) -Parent
    $mysqlArguments = @('--no-defaults', ('--basedir="' + $mysqlBase + '"'), ('--datadir="' + $mysqlData + '"'), '--port=3308', '--bind-address=127.0.0.1', '--mysqlx=0', '--innodb-buffer-pool-size=64M', '--console')
    Start-Process -FilePath $MySql -ArgumentList $mysqlArguments -WindowStyle Hidden -WorkingDirectory $projectRoot -RedirectStandardOutput (Join-Path $logDirectory 'mysql-output.log') -RedirectStandardError (Join-Path $logDirectory 'mysql-error.log')
    Wait-LocalPort 3308
}
Push-Location $projectRoot
try {
    & $Php artisan config:clear
    & $Php artisan migrate --force
    if ($LASTEXITCODE -ne 0) { throw 'Database migration failed. Check .env.' }
    if ($localStorage) {
        & $Php artisan preyson:protect-media
        if ($LASTEXITCODE -ne 0) { throw 'Post media could not be protected. Check storage permissions and available space.' }
        if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'public\storage'))) { & $Php artisan storage:link }
    }
} finally { Pop-Location }
if (-not (Test-LocalPort 8000)) {
    Start-Process -FilePath $Php -ArgumentList @('-d', 'upload_max_filesize=100M', '-d', 'post_max_size=110M', '-S', '127.0.0.1:8000', '-t', 'public') -WindowStyle Hidden -WorkingDirectory $projectRoot -RedirectStandardOutput (Join-Path $logDirectory 'app-output.log') -RedirectStandardError (Join-Path $logDirectory 'app-error.log')
    Wait-LocalPort 8000
}
if ($localDb -and -not (Test-LocalPort 8081)) {
    $pmaDirectory = Join-Path $Xampp 'phpMyAdmin'
    Start-Process -FilePath (Join-Path $Xampp 'php\php.exe') -ArgumentList @('-S', '127.0.0.1:8081', '-t', ('"' + $pmaDirectory + '"')) -WindowStyle Hidden -WorkingDirectory $projectRoot -RedirectStandardOutput (Join-Path $logDirectory 'phpmyadmin-output.log') -RedirectStandardError (Join-Path $logDirectory 'phpmyadmin-error.log')
    Wait-LocalPort 8081
}
Write-Host 'PreySON:    http://127.0.0.1:8000'
if ($localDb) {
    Write-Host 'phpMyAdmin: http://127.0.0.1:8081'
    Write-Host 'MySQL:      127.0.0.1:3308 / preyson_memes'
} else {
    Write-Host "Database:   hosted ($dbHost)"
}
if (-not $localStorage) {
    Write-Host 'Storage:    Cloudflare R2'
}
