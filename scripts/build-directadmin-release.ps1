param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot '..\dist')
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$resolvedOutput = if ([IO.Path]::IsPathRooted($OutputDirectory)) {
    [IO.Path]::GetFullPath($OutputDirectory)
} else {
    [IO.Path]::GetFullPath((Join-Path $repositoryRoot $OutputDirectory))
}
$temporaryRoot = Join-Path ([IO.Path]::GetTempPath()) ('timeline-curator-release-' + [Guid]::NewGuid().ToString('N'))
$stagingPath = Join-Path $temporaryRoot 'app'
$frontendPath = Join-Path $temporaryRoot 'frontend'
$archivePath = Join-Path $temporaryRoot 'source.zip'
$releasePath = Join-Path $resolvedOutput 'curator-vumbualabs-directadmin.zip'
$secretsPath = Join-Path $resolvedOutput 'curator-vumbualabs-deployment-secrets.txt'
$utf8WithoutBom = New-Object Text.UTF8Encoding($false)

function New-RandomBytes([int]$ByteCount) {
    $bytes = [byte[]]::new($ByteCount)
    $generator = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($bytes) } finally { $generator.Dispose() }
    return $bytes
}

function New-RandomValue([int]$ByteCount) {
    return [Convert]::ToBase64String((New-RandomBytes $ByteCount)).TrimEnd('=').Replace('+', '-').Replace('/', '_')
}

Push-Location $repositoryRoot
try {
    if ((git status --porcelain).Count -ne 0) {
        throw 'Commit or stash repository changes before building a deployment release.'
    }

    New-Item -ItemType Directory -Path $frontendPath -Force | Out-Null
    Copy-Item -LiteralPath (Join-Path $repositoryRoot 'package.json'), (Join-Path $repositoryRoot 'package-lock.json'), (Join-Path $repositoryRoot 'vite.config.js') -Destination $frontendPath
    Copy-Item -Recurse -LiteralPath (Join-Path $repositoryRoot 'resources') -Destination (Join-Path $frontendPath 'resources')

    Push-Location $frontendPath
    npm ci --no-audit --no-fund
    if ($LASTEXITCODE -ne 0) { throw 'npm ci failed.' }
    npm run build
    if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed.' }
    Pop-Location

    New-Item -ItemType Directory -Path $stagingPath -Force | Out-Null
    git archive --format=zip --output=$archivePath HEAD
    if ($LASTEXITCODE -ne 0) { throw 'Unable to export the committed source.' }
    Expand-Archive -LiteralPath $archivePath -DestinationPath $stagingPath

    composer install --working-dir=$stagingPath --no-dev --classmap-authoritative --no-interaction --prefer-dist
    if ($LASTEXITCODE -ne 0) { throw 'Production Composer install failed.' }

    Copy-Item -Recurse -Force -LiteralPath (Join-Path $frontendPath 'public\build') -Destination (Join-Path $stagingPath 'public\build')

    @('.agents', '.github', 'docs', 'plugins', 'scripts', 'tests') | ForEach-Object {
        $path = Join-Path $stagingPath $_
        if (Test-Path -LiteralPath $path) { Remove-Item -Recurse -Force -LiteralPath $path }
    }
    @('.editorconfig', '.gitattributes', '.gitignore', '.npmrc', 'package.json', 'package-lock.json', 'phpunit.xml', 'vite.config.js') | ForEach-Object {
        $path = Join-Path $stagingPath $_
        if (Test-Path -LiteralPath $path) { Remove-Item -Force -LiteralPath $path }
    }

    $appKey = 'base64:' + [Convert]::ToBase64String((New-RandomBytes 32))
    $cookieSecret = ([BitConverter]::ToString((New-RandomBytes 32))).Replace('-', '').ToLowerInvariant()
    $installerToken = New-RandomValue 32
    $sha256 = [Security.Cryptography.SHA256]::Create()
    try { $installerHashBytes = $sha256.ComputeHash([Text.Encoding]::UTF8.GetBytes($installerToken)) } finally { $sha256.Dispose() }
    $installerHash = ([BitConverter]::ToString($installerHashBytes)).Replace('-', '').ToLowerInvariant()

    $productionEnvironment = @"
APP_NAME="Timeline Curator"
APP_ENV=production
APP_KEY=$appKey
APP_DEBUG=false
APP_URL=https://curator.vumbualabs.com

AUTH0_DOMAIN=REPLACE_WITH_AUTH0_DOMAIN
AUTH0_CLIENT_ID=REPLACE_WITH_AUTH0_CLIENT_ID
AUTH0_CLIENT_SECRET=REPLACE_WITH_AUTH0_CLIENT_SECRET
AUTH0_COOKIE_SECRET=$cookieSecret
AUTH0_AUDIENCE=https://curator.vumbualabs.com/mcp

WEB_INSTALLER_ENABLED=true
WEB_INSTALLER_TOKEN_HASH=$installerHash

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=REPLACE_WITH_DATABASE_NAME
DB_USERNAME=REPLACE_WITH_DATABASE_USER
DB_PASSWORD=REPLACE_WITH_DATABASE_PASSWORD

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=curator.vumbualabs.com
SESSION_SECURE_COOKIE=true

CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
MAIL_MAILER=log
"@
    [IO.File]::WriteAllText((Join-Path $stagingPath '.env'), $productionEnvironment, $utf8WithoutBom)

    New-Item -ItemType Directory -Path $resolvedOutput -Force | Out-Null
    if (Test-Path -LiteralPath $releasePath) { Remove-Item -Force -LiteralPath $releasePath }
    tar.exe -a -c -f $releasePath -C $stagingPath .
    if ($LASTEXITCODE -ne 0) { throw 'Unable to create the release ZIP.' }
    $checksum = (Get-FileHash -Algorithm SHA256 -LiteralPath $releasePath).Hash.ToLowerInvariant()

    $deploymentSecrets = @"
Timeline Curator DirectAdmin deployment

Release: $releasePath
SHA-256: $checksum
Upload directory: /domains/curator.vumbualabs.com/app
Document root: /domains/curator.vumbualabs.com/app/public
Installer URL: https://curator.vumbualabs.com/deployment/install
One-time installer token: $installerToken

Keep this file private. After installation, set WEB_INSTALLER_ENABLED=false and remove WEB_INSTALLER_TOKEN_HASH from the server .env file.
"@
    [IO.File]::WriteAllText($secretsPath, $deploymentSecrets, $utf8WithoutBom)

    Write-Output "Release: $releasePath"
    Write-Output "Secrets: $secretsPath"
    Write-Output "SHA-256: $checksum"
}
finally {
    Pop-Location
    $resolvedTemporary = [IO.Path]::GetFullPath($temporaryRoot)
    if ($resolvedTemporary.StartsWith([IO.Path]::GetFullPath([IO.Path]::GetTempPath())) -and (Test-Path -LiteralPath $resolvedTemporary)) {
        Remove-Item -Recurse -Force -LiteralPath $resolvedTemporary
    }
}
