$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$backendLog = Join-Path $env:TEMP 'cloudflared-backend.log'
$frontendLog = Join-Path $env:TEMP 'cloudflared-frontend.log'
$onlineUrlFile = Join-Path $root 'URL_ONLINE.txt'
$nuxtPort = 3001
$laravelPort = 8000

function Resolve-ProjectDir([string]$basePath, [string]$preferredName, [string]$markerFile) {
  $preferred = Join-Path $basePath $preferredName
  if (Test-Path (Join-Path $preferred $markerFile)) { return $preferred }

  $candidate = Get-ChildItem -Path $basePath -Directory -ErrorAction SilentlyContinue |
    Where-Object { Test-Path (Join-Path $_.FullName $markerFile) } |
    Select-Object -First 1

  if ($candidate) { return $candidate.FullName }
  throw "Cartella progetto non trovata (marker: $markerFile) in $basePath"
}

function Resolve-ExecutablePath([string]$commandName, [string[]]$candidatePaths = @()) {
  $cmd = Get-Command $commandName -ErrorAction SilentlyContinue
  if ($cmd -and $cmd.Source) { return $cmd.Source }

  foreach ($candidate in $candidatePaths) {
    if (-not $candidate) { continue }
    if (Test-Path $candidate) { return $candidate }
  }

  return $null
}

$nuxtDir = Resolve-ProjectDir -basePath $root -preferredName 'nuxt-spedizionefacile-master' -markerFile 'nuxt.config.ts'
$laravelDir = Resolve-ProjectDir -basePath $root -preferredName 'laravel-spedizionefacile-main' -markerFile 'artisan'
$caddyExe = Resolve-ExecutablePath 'caddy' @(
  (Join-Path ${env:ProgramFiles} 'Caddy\caddy.exe'),
  (Join-Path ${env:LOCALAPPDATA} 'Programs\Caddy\caddy.exe'),
  (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Links\caddy.exe')
)
$hasCaddy = [bool]$caddyExe
$frontendTargetUrl = if ($hasCaddy) { 'http://127.0.0.1:8787' } else { "http://127.0.0.1:$nuxtPort" }

function Get-CloudflaredPath() {
  $cmd = Get-Command cloudflared -ErrorAction SilentlyContinue
  if ($cmd -and (Test-Path $cmd.Source)) { return (Get-Item $cmd.Source).FullName }

  $whereResults = @()
  try {
    $whereResults = @(cmd.exe /c where cloudflared 2>$null) | Where-Object { $_ -and (Test-Path $_) }
  } catch {}
  if ($whereResults.Count -gt 0) {
    return (Get-Item $whereResults[0]).FullName
  }

  $candidates = @(
    'C:\Program Files (x86)\cloudflared\cloudflared.exe',
    'C:\Program Files\cloudflared\cloudflared.exe',
    $(if ($env:ProgramFiles) { Join-Path $env:ProgramFiles 'Cloudflare\Cloudflared\cloudflared.exe' }),
    $(if (${env:ProgramFiles(x86)}) { Join-Path ${env:ProgramFiles(x86)} 'Cloudflare\Cloudflared\cloudflared.exe' }),
    $(if ($env:ProgramFiles) { Join-Path $env:ProgramFiles 'cloudflared\cloudflared.exe' }),
    $(if (${env:ProgramFiles(x86)}) { Join-Path ${env:ProgramFiles(x86)} 'cloudflared\cloudflared.exe' })
  ) | Where-Object { $_ }

  foreach ($candidate in $candidates) {
    if (Test-Path $candidate) {
      return (Get-Item $candidate).FullName
    }
  }

  throw 'cloudflared non trovato nel PATH o nei percorsi standard di installazione.'
}

function Get-TunnelUrl([string]$logFile) {
  $sources = @("$logFile.err", $logFile)
  $lastSeenUrl = $null

  for ($i = 0; $i -lt 60; $i++) {
    foreach ($candidate in $sources) {
      if (-not (Test-Path $candidate)) { continue }

      $matches = Select-String -Path $candidate -Pattern 'https://[a-zA-Z0-9-]+\.trycloudflare\.com' -AllMatches | Select-Object -Last 1
      if (-not $matches -or $matches.Matches.Count -eq 0) { continue }

      $lastSeenUrl = $matches.Matches[0].Value
      if (Test-OnlineUrl $lastSeenUrl) {
        return $lastSeenUrl
      }
    }

    Start-Sleep -Seconds 1
  }

  return $lastSeenUrl
}

function Wait-Http([string]$url, [int]$timeoutSeconds = 180) {
  $deadline = (Get-Date).AddSeconds($timeoutSeconds)
  while ((Get-Date) -lt $deadline) {
    try {
      $response = Invoke-WebRequest -Uri $url -UseBasicParsing -Method Head -TimeoutSec 5 -ErrorAction Stop
      if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 500) {
        return $true
      }
    } catch {
      try {
        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -Method Get -TimeoutSec 5 -ErrorAction Stop
        if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 500) {
          return $true
        }
      } catch {}
    }
    Start-Sleep -Seconds 1
  }
  return $false
}

function Test-OnlineUrl([string]$url) {
  if (-not $url) { return $false }
  return Wait-Http -url $url -timeoutSeconds 20
}

function Test-FrontendPreviewReady([string]$baseUrl, [int]$timeoutSeconds = 120) {
  if (-not $baseUrl) { return $false }

  $deadline = (Get-Date).AddSeconds($timeoutSeconds)
  while ((Get-Date) -lt $deadline) {
    try {
      $response = Invoke-WebRequest -Uri $baseUrl -UseBasicParsing -Method Get -TimeoutSec 8 -ErrorAction Stop
      $html = [string]$response.Content
      if (-not $html) {
        Start-Sleep -Seconds 1
        continue
      }

      $assetMatch = [regex]::Match($html, '"/_nuxt/[^"]+\.(?:js|css)"')
      if (-not $assetMatch.Success) {
        Start-Sleep -Seconds 1
        continue
      }

      $assetPath = $assetMatch.Value.Trim('"')
      $assetUrl = if ($assetPath.StartsWith('http')) { $assetPath } else { "{0}{1}" -f $baseUrl.TrimEnd('/'), $assetPath }
      $assetResponse = Invoke-WebRequest -Uri $assetUrl -UseBasicParsing -Method Head -TimeoutSec 8 -ErrorAction Stop
      if ($assetResponse.StatusCode -ge 200 -and $assetResponse.StatusCode -lt 400) {
        return $true
      }
    } catch {}

    Start-Sleep -Seconds 1
  }

  return $false
}

function Set-Or-AddEnvKey([string]$filePath, [string]$key, [string]$value) {
  $content = if (Test-Path $filePath) { Get-Content -Path $filePath -Raw } else { '' }
  if ($content -match "(?m)^$([regex]::Escape($key))=") {
    $content = [regex]::Replace($content, "(?m)^$([regex]::Escape($key))=.*$", "$key=$value")
  } else {
    if ($content -and -not $content.EndsWith("`n")) {
      $content += "`n"
    }
    $content += "$key=$value`n"
  }
  Set-Content -Path $filePath -Value $content -NoNewline
}

function Start-Tunnel([string]$url, [string]$logFile) {
  $resolvedLogFile = $logFile
  if (Test-Path $resolvedLogFile) {
    try {
      Remove-Item $resolvedLogFile -Force -ErrorAction Stop
    } catch {
      $stamp = Get-Date -Format 'yyyyMMdd-HHmmssfff'
      $resolvedLogFile = Join-Path ([System.IO.Path]::GetDirectoryName($logFile)) ("{0}-{1}{2}" -f [System.IO.Path]::GetFileNameWithoutExtension($logFile), $stamp, [System.IO.Path]::GetExtension($logFile))
    }
  }

  $errorLog = "$resolvedLogFile.err"
  if (Test-Path $errorLog) {
    try {
      Remove-Item $errorLog -Force -ErrorAction Stop
    } catch {
      $stamp = Get-Date -Format 'yyyyMMdd-HHmmssfff'
      $resolvedLogFile = Join-Path ([System.IO.Path]::GetDirectoryName($logFile)) ("{0}-{1}{2}" -f [System.IO.Path]::GetFileNameWithoutExtension($logFile), $stamp, [System.IO.Path]::GetExtension($logFile))
      $errorLog = "$resolvedLogFile.err"
    }
  }

  $cloudflared = Get-CloudflaredPath
  Start-Process -FilePath $cloudflared -ArgumentList 'tunnel','--url',$url,'--no-autoupdate' -RedirectStandardOutput $resolvedLogFile -RedirectStandardError $errorLog -WindowStyle Minimized
  return Get-TunnelUrl $resolvedLogFile
}

function Stop-StaleCloudflared() {
  $processes = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
    Where-Object {
      $_.Name -eq 'cloudflared.exe' -and
      $_.CommandLine -match 'trycloudflare|127\.0\.0\.1:8787|127\.0\.0\.1:8000|3001|8000'
    }

  foreach ($process in $processes) {
    try {
      Stop-Process -Id $process.ProcessId -Force -ErrorAction SilentlyContinue
    } catch {}
  }
}

$null = Get-CloudflaredPath

$backendUrl = $null
$frontendServerApiBase = "http://127.0.0.1:$laravelPort"
 $reuseHealthyLocalStack = $false

Stop-StaleCloudflared

if ((Wait-Http "http://127.0.0.1:$laravelPort" -timeoutSeconds 5) -and (Test-FrontendPreviewReady $frontendTargetUrl -timeoutSeconds 10)) {
  $reuseHealthyLocalStack = $true
}

if (-not $reuseHealthyLocalStack) {
  # Il frontend condiviso deve parlare col backend locale lato server.
  # Nel browser remoto, il plugin client riscrive poi apiBase verso lo stesso
  # origin del tunnel frontend, evitando cross-origin e dipendenze dal tunnel
  # backend per le normali chiamate /api e /sanctum.
  $env:NUXT_PUBLIC_API_BASE = $frontendServerApiBase
  $env:NUXT_PUBLIC_SANCTUM_BASE_URL = $frontendServerApiBase

  $env:SF_NUXT_RUNTIME = 'preview'
  & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'avvia-locale.ps1') > $null
}

if (-not (Wait-Http "http://127.0.0.1:$laravelPort")) {
  throw "Laravel non raggiungibile su http://127.0.0.1:$laravelPort"
}

if (-not (Test-FrontendPreviewReady $frontendTargetUrl)) {
  throw "Frontend non pronto su $frontendTargetUrl"
}

$backendUrl = Start-Tunnel -url "http://127.0.0.1:$laravelPort" -logFile $backendLog
if (-not $backendUrl) { throw "Tunnel backend non disponibile. Log: $backendLog" }

$frontendUrl = Start-Tunnel -url $frontendTargetUrl -logFile $frontendLog
if (-not $frontendUrl) { throw "Tunnel frontend non disponibile. Log: $frontendLog" }
if (-not (Test-FrontendPreviewReady $frontendUrl)) {
  throw "Tunnel frontend non pronto dopo l'avvio. Log: $frontendLog"
}

$envFile = Join-Path $laravelDir '.env'
if (Test-Path $envFile) {
  Set-Or-AddEnvKey $envFile 'APP_FRONTEND_URL' $frontendUrl
  Set-Or-AddEnvKey $envFile 'SANCTUM_STATEFUL_DOMAINS' '127.0.0.1:8787,localhost:8787,127.0.0.1:3001,localhost:3001,127.0.0.1:8000,localhost:8000,*.trycloudflare.com'
}

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($onlineUrlFile, $frontendUrl, $utf8NoBom)

Write-Output "✅ Root progetto: $root"
Write-Output "✅ Frontend dir: $nuxtDir"
Write-Output "✅ Frontend pubblico: $frontendUrl"
Write-Output "✅ Backend pubblico:  $backendUrl"
Write-Output "INFO Frontend server -> backend locale: $frontendServerApiBase"
Write-Output "INFO Tunnel frontend diretto su $frontendTargetUrl"
