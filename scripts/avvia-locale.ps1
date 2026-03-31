$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot

function Resolve-ProjectDir([string]$basePath, [string]$preferredName, [string]$markerFile) {
  $preferred = Join-Path $basePath $preferredName
  if (Test-Path (Join-Path $preferred $markerFile)) { return $preferred }

  $candidate = Get-ChildItem -Path $basePath -Directory -ErrorAction SilentlyContinue |
    Where-Object { Test-Path (Join-Path $_.FullName $markerFile) } |
    Select-Object -First 1

  if ($candidate) { return $candidate.FullName }
  throw "Cartella progetto non trovata (marker: $markerFile) in $basePath"
}

function Get-LockedPackageVersion([string]$lockPath,[string]$packageName) {
  if (-not (Test-Path $lockPath)) { return $null }
  $content = Get-Content -Path $lockPath -Raw
  $target = [regex]::Escape("node_modules/$packageName")
  $pattern = '"' + $target + '"\s*:\s*\{[\s\S]*?"version"\s*:\s*"([^"]+)"'
  $match = [regex]::Match($content, $pattern)
  if ($match.Success) { return $match.Groups[1].Value }
  return $null
}

function Ensure-NuxtWindowsOptionalDeps([string]$dir) {
  $oxcBinding = Join-Path $dir 'node_modules\@oxc-parser\binding-win32-x64-msvc\package.json'
  $rollupBinding = Join-Path $dir 'node_modules\@rollup\rollup-win32-x64-msvc\package.json'

  if ((Test-Path $oxcBinding) -and (Test-Path $rollupBinding)) { return }

  Write-Output 'WARN Ripristino dipendenze opzionali Windows di Nuxt...'
  Push-Location $dir
  try {
    npm install --include=optional --no-audit | Out-Null

    if ((Test-Path $oxcBinding) -and (Test-Path $rollupBinding)) {
      Write-Output 'OK Binding Windows Nuxt ripristinati.'
      return
    }

    $lockPath = Join-Path $dir 'package-lock.json'
    $oxcVersion = Get-LockedPackageVersion $lockPath '@oxc-parser/binding-win32-x64-msvc'
    $rollupVersion = Get-LockedPackageVersion $lockPath '@rollup/rollup-win32-x64-msvc'
    $packages = @()
    if ($oxcVersion) { $packages += "@oxc-parser/binding-win32-x64-msvc@$oxcVersion" }
    if ($rollupVersion) { $packages += "@rollup/rollup-win32-x64-msvc@$rollupVersion" }

    if ($packages.Count -gt 0) {
      npm install --no-save --no-audit $packages | Out-Null
    }
  } finally {
    Pop-Location
  }

  if (-not ((Test-Path $oxcBinding) -and (Test-Path $rollupBinding))) {
    throw 'Nuxt non puo partire su Windows: binding nativi mancanti.'
  }

  Write-Output 'OK Binding Windows Nuxt ripristinati.'
}

function Reset-NuxtArtifactsForWindows([string]$dir) {
  $markers = @(
    (Join-Path $dir '.nuxt\dist\server\server.mjs'),
    (Join-Path $dir '.nuxt\dev\index.mjs'),
    (Join-Path $dir '.nuxt\app.config.mjs'),
    (Join-Path $dir '.nuxt\ui.css')
  )

  $workspaceDirs = @(
    Get-ChildItem -Path $dir -Directory -ErrorAction SilentlyContinue |
      Where-Object {
        (Test-Path (Join-Path $_.FullName '.nuxt\dev\index.mjs')) -or
        (Test-Path (Join-Path $_.FullName '.nuxt\dist\server\server.mjs')) -or
        (Test-Path (Join-Path $_.FullName 'node_modules\.cache\nuxt\chrome-workspace.json'))
      }
  )

  $needsReset = $false
  foreach ($marker in $markers) {
    if (-not (Test-Path $marker)) { continue }
    try {
      $content = Get-Content -Path $marker -Raw -ErrorAction Stop
      if (
        $content -match 'file:///mnt/' -or
        $content -match '/mnt/c/' -or
        $content -match '@source "/mnt/'
      ) {
        $needsReset = $true
        break
      }
    } catch {}
  }

  if (-not $needsReset) {
    foreach ($workspaceDir in $workspaceDirs) {
      $workspaceMarkers = @(
        (Join-Path $workspaceDir.FullName '.nuxt\dist\server\server.mjs'),
        (Join-Path $workspaceDir.FullName '.nuxt\dev\index.mjs'),
        (Join-Path $workspaceDir.FullName '.nuxt\app.config.mjs')
      )

      foreach ($workspaceMarker in $workspaceMarkers) {
        if (-not (Test-Path $workspaceMarker)) { continue }
        try {
          $content = Get-Content -Path $workspaceMarker -Raw -ErrorAction Stop
          if (
            $content -match 'file:///mnt/' -or
            $content -match '/mnt/c/' -or
            $content -match '@source "/mnt/'
          ) {
            $needsReset = $true
            break
          }
        } catch {}
      }

      if ($needsReset) { break }
    }
  }

  if ((-not $needsReset) -and ($workspaceDirs.Count -eq 0)) { return }

  Write-Output 'WARN Artefatti Nuxt generati da WSL rilevati: pulizia cache Nuxt/host workspace per rigenerazione Windows...'
  foreach ($relative in @('.nuxt', '.output')) {
    $target = Join-Path $dir $relative
    if (Test-Path $target) {
      Remove-Item -Path $target -Recurse -Force -ErrorAction SilentlyContinue
    }
  }

  foreach ($workspaceDir in $workspaceDirs) {
    if (Test-Path $workspaceDir.FullName) {
      Remove-Item -Path $workspaceDir.FullName -Recurse -Force -ErrorAction SilentlyContinue
    }
  }
}

function Kill-PidTree([int]$procId) {
  if ($procId -le 0) { return }
  cmd /c "taskkill /PID $procId /T /F >nul 2>&1" | Out-Null
}

function Get-PidsByPort([int]$port) {
  $pids = @{}
  try {
    $lines = netstat -ano | Select-String (":$port\s")
    foreach ($line in $lines) {
      $normalized = ($line.Line -replace "\s+", " ").Trim()
      if ($normalized -match "\sLISTENING\s(\d+)$") {
        $pids["$($matches[1])"] = $true
      }
    }
  } catch {}
  return @($pids.Keys | ForEach-Object { [int]$_ })
}

function Kill-ByPort([int]$port) {
  foreach ($procId in (Get-PidsByPort $port)) {
    Kill-PidTree $procId
  }
}

function Stop-LocalStackProcesses() {
  foreach ($name in @('php', 'caddy')) {
    Get-Process -Name $name -ErrorAction SilentlyContinue | ForEach-Object {
      try { Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue } catch {}
    }
  }

  $nodeProcesses = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
    Where-Object {
      $_.Name -eq 'node.exe' -and
      (
        $_.CommandLine -match 'nuxi\.mjs dev' -or
        $_.CommandLine -match 'nuxi\.mjs preview' -or
        $_.CommandLine -match '\.output\\server\\index\.mjs'
      )
    }

  foreach ($process in $nodeProcesses) {
    try { Kill-PidTree $process.ProcessId } catch {}
  }
}

function Stop-ProjectRuntimeProcesses([string]$projectPath) {
  $escapedPath = [regex]::Escape($projectPath)
  $escapedTemp = [regex]::Escape($env:TEMP)
  $processes = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
    Where-Object {
      $_.Name -in @('node.exe', 'php.exe', 'caddy.exe') -and
      (
        $_.CommandLine -match $escapedPath -or
        $_.CommandLine -match '127\.0\.0\.1:3001|0\.0\.0\.0:3001|127\.0\.0\.1:8000|0\.0\.0\.0:8000|127\.0\.0\.1:8787|0\.0\.0\.0:8787' -or
        $_.CommandLine -match '\\node_modules\\@nuxt\\cli\\bin\\nuxi\.mjs dev' -or
        $_.CommandLine -match '\.output\\server\\index\.mjs' -or
        $_.CommandLine -match 'artisan serve --host 0\.0\.0\.0 --port 8000' -or
        $_.CommandLine -match 'vendor\\laravel\\framework\\src\\Illuminate\\Foundation\\Console.+server\.php' -or
        $_.CommandLine -match 'caddy(\.exe)?\s+run\s+--config' -or
        $_.CommandLine -match $escapedTemp
      )
    }

  foreach ($process in $processes) {
    try {
      Kill-PidTree $process.ProcessId
    } catch {}
  }
}

function Resolve-ExecutablePath([string]$commandName, [string[]]$candidatePaths = @()) {
  $cmd = Get-Command $commandName -ErrorAction SilentlyContinue
  if ($cmd) { return $cmd.Source }

  foreach ($candidate in $candidatePaths) {
    if (-not $candidate) { continue }
    if (Test-Path $candidate) { return $candidate }
  }

  return $null
}

$laravelDir = Resolve-ProjectDir -basePath $root -preferredName 'laravel-spedizionefacile-main' -markerFile 'artisan'
$nuxtDir = Resolve-ProjectDir -basePath $root -preferredName 'nuxt-spedizionefacile-master' -markerFile 'nuxt.config.ts'
$nuxtPort = 3001
$laravelPort = 8000
$listenHost = '127.0.0.1'
$phpExe = Resolve-ExecutablePath 'php' @(
  (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe')
)
$npmExe = Resolve-ExecutablePath 'npm' @(
  (Join-Path ${env:ProgramFiles} 'nodejs\npm.cmd'),
  (Join-Path ${env:ProgramFiles(x86)} 'nodejs\npm.cmd')
)
$composerExe = Resolve-ExecutablePath 'composer' @(
  (Join-Path $env:ProgramData 'ComposerSetup\bin\composer.bat'),
  (Join-Path $env:APPDATA 'Composer\vendor\bin\composer.bat'),
  (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Links\composer.bat')
)
$caddyExe = Resolve-ExecutablePath 'caddy' @(
  (Join-Path ${env:ProgramFiles} 'Caddy\caddy.exe'),
  (Join-Path ${env:LOCALAPPDATA} 'Programs\Caddy\caddy.exe'),
  (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Links\caddy.exe')
)

if (-not $phpExe) { throw 'PHP non trovato nel PATH o nei percorsi Windows noti.' }
if (-not $npmExe) { throw 'npm non trovato nel PATH o nei percorsi Windows noti.' }

$nodeDir = Split-Path $npmExe -Parent
$npxExe = Resolve-ExecutablePath 'npx' @(
  (Join-Path $nodeDir 'npx.cmd')
)
$nodeExe = Resolve-ExecutablePath 'node' @(
  (Join-Path $nodeDir 'node.exe')
)

$script:ResolvedPhpExe = $phpExe
$script:ResolvedNpmExe = $npmExe
$script:ResolvedComposerExe = $composerExe
$script:ResolvedCaddyExe = $caddyExe

function php { & $script:ResolvedPhpExe @args }
function npm { & $script:ResolvedNpmExe @args }
if ($composerExe) { function composer { & $script:ResolvedComposerExe @args } }
if ($caddyExe) { function caddy { & $script:ResolvedCaddyExe @args } }

$hasCaddy = [bool]$caddyExe
$nuxtRuntimeMode = if ($env:SF_NUXT_RUNTIME) { $env:SF_NUXT_RUNTIME.ToLowerInvariant() } else { 'dev' }

$env:NUXT_PUBLIC_API_BASE = if ($env:NUXT_PUBLIC_API_BASE) {
  $env:NUXT_PUBLIC_API_BASE
} elseif ($hasCaddy) {
  'http://127.0.0.1:8787'
} else {
  "http://127.0.0.1:$laravelPort"
}

$env:NUXT_PUBLIC_SANCTUM_BASE_URL = if ($env:NUXT_PUBLIC_SANCTUM_BASE_URL) {
  $env:NUXT_PUBLIC_SANCTUM_BASE_URL
} else {
  $env:NUXT_PUBLIC_API_BASE
}

if (-not (Test-Path (Join-Path $laravelDir 'vendor\autoload.php'))) {
  if (-not $composerExe) { throw 'Composer non trovato e vendor assente: impossibile completare l’avvio Laravel.' }
  Push-Location $laravelDir
  try {
    composer install --no-interaction --prefer-dist --no-dev
  } catch {
    composer install --no-interaction --prefer-dist --no-dev --ignore-platform-req=php
  }
  Pop-Location
}

$envFile = Join-Path $laravelDir '.env'
if (-not (Test-Path $envFile) -and (Test-Path (Join-Path $laravelDir '.env.example'))) {
  Copy-Item (Join-Path $laravelDir '.env.example') $envFile -Force
}

$dbPath = Join-Path $laravelDir 'database\database.sqlite'
if (-not (Test-Path $dbPath)) { New-Item -ItemType File -Path $dbPath -Force | Out-Null }

if (Test-Path $envFile) {
  $envContent = Get-Content $envFile -Raw
  if ($envContent -match '(?m)^DB_CONNECTION=') {
    $envContent = [regex]::Replace($envContent, '(?m)^DB_CONNECTION=.*$', 'DB_CONNECTION=sqlite')
  } else {
    $envContent += "`nDB_CONNECTION=sqlite"
  }

  if ($envContent -match '(?m)^DB_DATABASE=') {
    $envContent = [regex]::Replace($envContent, '(?m)^DB_DATABASE=.*$', "DB_DATABASE=$dbPath")
  } else {
    $envContent += "`nDB_DATABASE=$dbPath"
  }

  if ($envContent -match '(?m)^SESSION_DRIVER=') {
    $envContent = [regex]::Replace($envContent, '(?m)^SESSION_DRIVER=.*$', 'SESSION_DRIVER=file')
  } else {
    $envContent += "`nSESSION_DRIVER=file"
  }

  if ($envContent -match '(?m)^QUEUE_CONNECTION=') {
    $envContent = [regex]::Replace($envContent, '(?m)^QUEUE_CONNECTION=.*$', 'QUEUE_CONNECTION=sync')
  } else {
    $envContent += "`nQUEUE_CONNECTION=sync"
  }

  if ($envContent -match '(?m)^CACHE_STORE=') {
    $envContent = [regex]::Replace($envContent, '(?m)^CACHE_STORE=.*$', 'CACHE_STORE=file')
  } else {
    $envContent += "`nCACHE_STORE=file"
  }

  $statefulDomains = "127.0.0.1:8787,localhost:8787,127.0.0.1:$nuxtPort,localhost:$nuxtPort,127.0.0.1:$laravelPort,localhost:$laravelPort,*.trycloudflare.com"
  $corsOrigins = "http://127.0.0.1:8787,http://localhost:8787,http://127.0.0.1:$nuxtPort,http://localhost:$nuxtPort,http://127.0.0.1:$laravelPort,http://localhost:$laravelPort"

  if ($envContent -match '(?m)^SANCTUM_STATEFUL_DOMAINS=') {
    $envContent = [regex]::Replace($envContent, '(?m)^SANCTUM_STATEFUL_DOMAINS=.*$', "SANCTUM_STATEFUL_DOMAINS=$statefulDomains")
  } else {
    $envContent += "`nSANCTUM_STATEFUL_DOMAINS=$statefulDomains"
  }

  if ($envContent -match '(?m)^CORS_ALLOWED_ORIGINS=') {
    $envContent = [regex]::Replace($envContent, '(?m)^CORS_ALLOWED_ORIGINS=.*$', "CORS_ALLOWED_ORIGINS=$corsOrigins")
  } else {
    $envContent += "`nCORS_ALLOWED_ORIGINS=$corsOrigins"
  }

  $frontendUrl = if ($hasCaddy) { 'http://127.0.0.1:8787' } else { "http://127.0.0.1:$nuxtPort" }

  if ($envContent -match '(?m)^APP_FRONTEND_URL=') {
    $envContent = [regex]::Replace($envContent, '(?m)^APP_FRONTEND_URL=.*$', "APP_FRONTEND_URL=$frontendUrl")
  } else {
    $envContent += "`nAPP_FRONTEND_URL=$frontendUrl"
  }

  Set-Content -Path $envFile -Value $envContent -NoNewline

  Push-Location $laravelDir
  php artisan key:generate --force | Out-Null
  try { php artisan migrate --force | Out-Null } catch {}
  try { php artisan db:seed --class=Database\Seeders\DatabaseSeeder --force | Out-Null } catch {}
  try { php artisan locations:import --if-empty --country=IT | Out-Null } catch {}
  try { php artisan storage:link | Out-Null } catch {}
  Pop-Location
}

if (-not (Test-Path (Join-Path $nuxtDir 'node_modules'))) {
  Push-Location $nuxtDir
  npm install
  Pop-Location
}

Ensure-NuxtWindowsOptionalDeps $nuxtDir
Reset-NuxtArtifactsForWindows $nuxtDir
Stop-LocalStackProcesses
Kill-ByPort 8787
Kill-ByPort 3001
Kill-ByPort 8000
Stop-ProjectRuntimeProcesses $root

Get-Process | Where-Object { $_.ProcessName -in @('php','node','caddy') } | ForEach-Object {
  try { if ($_.Path -match 'php|node|caddy') { } } catch {}
}

Start-Process -FilePath powershell -ArgumentList '-NoProfile','-Command',"Set-Location '$laravelDir'; & '$phpExe' artisan serve --host $listenHost --port $laravelPort *> $env:TEMP\\laravel.log" -WindowStyle Minimized

$nuxtCommand = if ($nuxtRuntimeMode -eq 'preview') {
  @(
    '/c',
    "cd /d ""$nuxtDir"" && set ""PATH=$nodeDir;%PATH%"" && set ""NUXT_PUBLIC_API_BASE=$($env:NUXT_PUBLIC_API_BASE)"" && set ""NUXT_PUBLIC_SANCTUM_BASE_URL=$($env:NUXT_PUBLIC_SANCTUM_BASE_URL)"" && ""$nodeExe"" --max-old-space-size=6144 .\node_modules\@nuxt\cli\bin\nuxi.mjs prepare > ""$env:TEMP\nuxt-prepare.log"" 2>&1 && ""$nodeExe"" --max-old-space-size=6144 .\node_modules\@nuxt\cli\bin\nuxi.mjs build > ""$env:TEMP\nuxt-build.log"" 2>&1 && set PORT=$nuxtPort && set NITRO_PORT=$nuxtPort && set HOST=$listenHost && set NITRO_HOST=$listenHost && ""$nodeExe"" .output\server\index.mjs > ""$env:TEMP\nuxt.log"" 2>&1"
  )
} else {
  @(
    '/c',
    "cd /d ""$nuxtDir"" && set ""PATH=$nodeDir;%PATH%"" && set ""NUXT_PUBLIC_API_BASE=$($env:NUXT_PUBLIC_API_BASE)"" && set ""NUXT_PUBLIC_SANCTUM_BASE_URL=$($env:NUXT_PUBLIC_SANCTUM_BASE_URL)"" && ""$nodeExe"" --max-old-space-size=6144 .\node_modules\@nuxt\cli\bin\nuxi.mjs dev --host $listenHost --port $nuxtPort > ""$env:TEMP\nuxt.log"" 2>&1"
  )
}

Start-Process -FilePath cmd.exe -ArgumentList $nuxtCommand -WindowStyle Minimized

if ($hasCaddy) {
  $caddyFile = Join-Path $root 'Caddyfile'
  if (-not (Test-Path $caddyFile)) { $caddyFile = Join-Path $root 'Caddyfile.example' }
  Start-Process -FilePath powershell -ArgumentList '-NoProfile','-Command',"Set-Location '$root'; & '$caddyExe' run --config '$caddyFile' *> $env:TEMP\\caddy.log" -WindowStyle Minimized
  Write-Output 'OK Apri: http://127.0.0.1:8787'
} else {
  Write-Output 'WARN Caddy non trovato. Apri: http://127.0.0.1:3001 (Nuxt)'
  Write-Output "INFO API base frontend: $($env:NUXT_PUBLIC_API_BASE)"
}

Write-Output "INFO Root progetto: $root"
Write-Output "INFO Frontend dir: $nuxtDir"
Write-Output "INFO Backend dir: $laravelDir"
Write-Output "INFO Modalita frontend: $nuxtRuntimeMode"
Write-Output "INFO API base frontend: $($env:NUXT_PUBLIC_API_BASE)"
Write-Output "INFO Sanctum base frontend: $($env:NUXT_PUBLIC_SANCTUM_BASE_URL)"
Write-Output "INFO Log: $env:TEMP\\nuxt.log, $env:TEMP\\laravel.log"
