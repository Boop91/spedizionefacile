param([string]$Azione = "MENU")

$ErrorActionPreference = "Stop"
$root    = Split-Path -Parent $MyInvocation.MyCommand.Path
$state   = Join-Path $root "_STATE.json"
$logDir  = Join-Path $root "_LOG"
$urlFile = Join-Path $root "URL_ONLINE.txt"

if(-not (Test-Path $logDir)){ New-Item -ItemType Directory -Path $logDir | Out-Null }

function T([string]$m,[string]$c="Cyan"){
  $ts=(Get-Date).ToString("HH:mm:ss")
  Write-Host "[$ts] $m" -ForegroundColor $c
}

function Get-LogTail([string]$path,[int]$lines=40){
  if(-not (Test-Path $path)){ return "(log non disponibile: $path)" }
  return ((Get-Content -Path $path -Tail $lines -ErrorAction SilentlyContinue) -join "`n")
}

function Read-KeyChoice([string]$prompt = "Scelta"){
  Write-Host -NoNewline ("${prompt}: ") -ForegroundColor Cyan
  $keyInfo = [System.Console]::ReadKey($true)
  $char = $keyInfo.KeyChar
  Write-Host $char -ForegroundColor White
  return ([string]$char).Trim()
}

function Save-State($obj){
  $json = $obj | ConvertTo-Json -Depth 30
  $enc  = New-Object System.Text.UTF8Encoding($false)
  [System.IO.File]::WriteAllText($state, $json, $enc)
}

function Load-State(){
  if(Test-Path $state){
    try { return (Get-Content $state -Raw | ConvertFrom-Json) } catch { return $null }
  }
  return $null
}

function Get-OnlineUrl(){
  if(-not (Test-Path $urlFile)){ return $null }
  $u = (Get-Content $urlFile -Raw).Trim()
  if([string]::IsNullOrWhiteSpace($u)){ return $null }
  return $u
}

function Kill-PidTree([int]$procId){
  if($procId -le 0){ return }
  cmd /c "taskkill /PID $procId /T /F >nul 2>&1" | Out-Null
}

function Get-PidsByPort([int]$port){
  $pids = @{}
  try{
    $lines = netstat -ano | Select-String (":$port\s")
    foreach($l in $lines){
      $s = ($l.Line -replace "\s+"," ").Trim()
      if($s -match "\sLISTENING\s(\d+)$"){
        $id = [int]$matches[1]
        $pids["$id"] = $true
      }
    }
  } catch {}
  return @($pids.Keys | ForEach-Object { [int]$_ })
}

function Kill-ByPort([int]$port){
  foreach($id in (Get-PidsByPort $port)){ Kill-PidTree $id }
}

function Wait-Http([string]$url,[int]$timeoutSec=240){
  $start = Get-Date
  while((Get-Date) - $start -lt [TimeSpan]::FromSeconds($timeoutSec)){
    try{
      Invoke-WebRequest -UseBasicParsing -TimeoutSec 2 -Uri $url | Out-Null
      return $true
    } catch {
      try{ if($_.Exception.Response){ return $true } } catch {}
    }
    Start-Sleep -Milliseconds 400
  }
  return $false
}

function Wait-HttpOrFail([string]$url,[System.Diagnostics.Process]$process,[string]$label,[string]$logPath,[int]$timeoutSec=240){
  $start = Get-Date
  while((Get-Date) - $start -lt [TimeSpan]::FromSeconds($timeoutSec)){
    try{
      Invoke-WebRequest -UseBasicParsing -TimeoutSec 2 -Uri $url | Out-Null
      return $true
    } catch {
      try{ if($_.Exception.Response){ return $true } } catch {}
    }

    try{
      if($process -and $process.HasExited){
        $tail = Get-LogTail $logPath
        throw "${label} terminato prima di rispondere. ExitCode=$($process.ExitCode)`nLog: $logPath`n$tail"
      }
    } catch {
      throw
    }

    Start-Sleep -Milliseconds 400
  }

  $tail = Get-LogTail $logPath
  throw "${label} non risponde su $url entro ${timeoutSec}s.`nLog: $logPath`n$tail"
}

function Resolve-ProjectDir([string]$preferredName, [string[]]$markerFiles, [string]$projectLabel){
  $preferred = Join-Path $root $preferredName
  foreach($marker in $markerFiles){
    if(Test-Path (Join-Path $preferred $marker)){ return $preferred }
  }

  $dirs = Get-ChildItem -Path $root -Directory -ErrorAction SilentlyContinue
  $matches = @()
  foreach($d in $dirs){
    foreach($marker in $markerFiles){
      if(Test-Path (Join-Path $d.FullName $marker)){
        $matches += $d.FullName
        break
      }
    }
  }

  if($matches.Count -eq 1){ return $matches[0] }
  if($matches.Count -gt 1){
    $list = ($matches | ForEach-Object { " - $_" }) -join "`n"
    throw "Trovate piu cartelle candidate per ${projectLabel}. Rinomina o elimina i duplicati e lascia una sola cartella valida:`n$list"
  }

  throw "Non trovo la cartella ${projectLabel} (marker: $($markerFiles -join ', '))."
}

function Find-Frontend(){
  return Resolve-ProjectDir "nuxt-spedizionefacile-master" @("nuxt.config.ts","nuxt.config.js","nuxt.config.mjs") "Nuxt"
}

function Find-Backend(){
  return Resolve-ProjectDir "laravel-spedizionefacile-main" @("artisan") "Laravel"
}

function Has-Caddyfile(){ return (Test-Path (Join-Path $root "Caddyfile")) }
function Has-Caddy(){ return [bool](Get-Command caddy -ErrorAction SilentlyContinue) }

function Stop-StaleCloudflared(){
  $processes = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
    Where-Object {
      $_.Name -eq 'cloudflared.exe' -and
      $_.CommandLine -match 'trycloudflare|127\.0\.0\.1:8787|127\.0\.0\.1:8000|3001|8000'
    }

  foreach($process in $processes){
    try {
      Stop-Process -Id $process.ProcessId -Force -ErrorAction SilentlyContinue
    } catch {}
  }
}

function Test-OnlineUrl([string]$url){
  if(-not $url){ return $false }

  try {
    $response = Invoke-WebRequest -Uri $url -UseBasicParsing -Method Head -TimeoutSec 8 -ErrorAction Stop
    return ($response.StatusCode -ge 200 -and $response.StatusCode -lt 400)
  } catch {
    try {
      $response = Invoke-WebRequest -Uri $url -UseBasicParsing -Method Get -TimeoutSec 8 -ErrorAction Stop
      return ($response.StatusCode -ge 200 -and $response.StatusCode -lt 400)
    } catch {
      return $false
    }
  }
}

function Stop-All(){
  T "Chiusura totale (spedizionefacile)..." "Yellow"
  $s = Load-State
  if($s){
    if($s.frontend){ Kill-PidTree ([int]$s.frontend) }
    if($s.backend){ Kill-PidTree ([int]$s.backend) }
    if($s.caddy){ Kill-PidTree ([int]$s.caddy) }
    if($s.cloudflared){ Kill-PidTree ([int]$s.cloudflared) }
  }

  # sicurezza porte tipiche (non invade BianchiPro)
  Kill-ByPort 8787
  Kill-ByPort 3001
  Kill-ByPort 8000
  Stop-StaleCloudflared

  Remove-Item $state -Force -ErrorAction SilentlyContinue | Out-Null
  Remove-Item $urlFile -Force -ErrorAction SilentlyContinue | Out-Null
  T "Tutto chiuso." "Green"
}

function Ensure-NpmInstall([string]$dir){
  if(-not (Test-Path (Join-Path $dir "package.json"))){
    throw "In $dir non trovo package.json: Nuxt non sembra valido."
  }
  if(-not (Test-Path (Join-Path $dir "node_modules"))){
    T "Manca node_modules (Nuxt): npm install..." "Yellow"
    $o = Join-Path $logDir "nuxt_npm_out.log"
    $e = Join-Path $logDir "nuxt_npm_err.log"
    Remove-Item $o,$e -Force -ErrorAction SilentlyContinue | Out-Null
    $p = Start-Process -FilePath "cmd.exe" -WorkingDirectory $dir -PassThru -WindowStyle Hidden `
      -ArgumentList "/c","npm install" -RedirectStandardOutput $o -RedirectStandardError $e
    $p.WaitForExit()
    if($p.ExitCode -ne 0){ T "ERRORE npm install Nuxt. Log: $e" "Red"; throw "npm install Nuxt fallito" }
    T "npm install Nuxt completato." "Green"
  }

  Ensure-NuxtWindowsOptionalDeps $dir
}

function Get-LockedPackageVersion([string]$lockPath,[string]$packageName){
  if(-not (Test-Path $lockPath)){ return $null }

  $content = Get-Content -Path $lockPath -Raw
  $target = [regex]::Escape("node_modules/$packageName")
  $pattern = '"' + $target + '"\s*:\s*\{[\s\S]*?"version"\s*:\s*"([^"]+)"'
  $match = [regex]::Match($content, $pattern)
  if($match.Success){ return $match.Groups[1].Value }
  return $null
}

function Ensure-NuxtWindowsOptionalDeps([string]$dir){
  $oxcBinding = Join-Path $dir "node_modules\@oxc-parser\binding-win32-x64-msvc\package.json"
  $rollupBinding = Join-Path $dir "node_modules\@rollup\rollup-win32-x64-msvc\package.json"

  if((Test-Path $oxcBinding) -and (Test-Path $rollupBinding)){ return }

  T "Ripristino dipendenze opzionali Windows di Nuxt..." "Yellow"
  $installOut = Join-Path $logDir "nuxt_optionaldeps_out.log"
  $installErr = Join-Path $logDir "nuxt_optionaldeps_err.log"
  Remove-Item $installOut,$installErr -Force -ErrorAction SilentlyContinue | Out-Null

  $repair = Start-Process -FilePath "cmd.exe" -WorkingDirectory $dir -PassThru -WindowStyle Hidden `
    -ArgumentList "/c","npm install --include=optional --no-audit" `
    -RedirectStandardOutput $installOut -RedirectStandardError $installErr
  $repair.WaitForExit()

  if((Test-Path $oxcBinding) -and (Test-Path $rollupBinding)){
    T "Dipendenze opzionali Windows Nuxt ripristinate." "Green"
    return
  }

  $lockPath = Join-Path $dir "package-lock.json"
  $oxcVersion = Get-LockedPackageVersion $lockPath "@oxc-parser/binding-win32-x64-msvc"
  $rollupVersion = Get-LockedPackageVersion $lockPath "@rollup/rollup-win32-x64-msvc"
  $packages = @()
  if($oxcVersion){ $packages += "@oxc-parser/binding-win32-x64-msvc@$oxcVersion" }
  if($rollupVersion){ $packages += "@rollup/rollup-win32-x64-msvc@$rollupVersion" }

  if($packages.Count -gt 0){
    T "Ripristino mirato binding nativi Windows..." "Yellow"
    $cmd = "npm install --no-save --no-audit " + ($packages -join " ")
    $repair = Start-Process -FilePath "cmd.exe" -WorkingDirectory $dir -PassThru -WindowStyle Hidden `
      -ArgumentList "/c",$cmd `
      -RedirectStandardOutput $installOut -RedirectStandardError $installErr
    $repair.WaitForExit()
  }

  if(-not ((Test-Path $oxcBinding) -and (Test-Path $rollupBinding))){
    $tail = Get-LogTail $installErr
    throw "Nuxt non puo' partire su Windows: binding nativi mancanti.`nLog: $installErr`n$tail"
  }

  T "Dipendenze opzionali Windows Nuxt ripristinate." "Green"
}

function Reset-NuxtArtifactsForWindows([string]$dir){
  $markers = @(
    (Join-Path $dir ".nuxt\dist\server\server.mjs"),
    (Join-Path $dir ".nuxt\dev\index.mjs"),
    (Join-Path $dir ".nuxt\app.config.mjs"),
    (Join-Path $dir ".nuxt\ui.css")
  )

  $workspaceDirs = @(
    Get-ChildItem -Path $dir -Directory -ErrorAction SilentlyContinue |
      Where-Object {
        (Test-Path (Join-Path $_.FullName ".nuxt\dev\index.mjs")) -or
        (Test-Path (Join-Path $_.FullName ".nuxt\dist\server\server.mjs")) -or
        (Test-Path (Join-Path $_.FullName "node_modules\.cache\nuxt\chrome-workspace.json"))
      }
  )

  $needsReset = $false
  foreach($marker in $markers){
    if(-not (Test-Path $marker)){ continue }
    try{
      $content = Get-Content -Path $marker -Raw -ErrorAction Stop
      if(
        $content -match 'file:///mnt/' -or
        $content -match '/mnt/c/' -or
        $content -match '@source "/mnt/'
      ){
        $needsReset = $true
        break
      }
    } catch {}
  }

  if(-not $needsReset){
    foreach($workspaceDir in $workspaceDirs){
      $workspaceMarkers = @(
        (Join-Path $workspaceDir.FullName ".nuxt\dist\server\server.mjs"),
        (Join-Path $workspaceDir.FullName ".nuxt\dev\index.mjs"),
        (Join-Path $workspaceDir.FullName ".nuxt\app.config.mjs")
      )

      foreach($workspaceMarker in $workspaceMarkers){
        if(-not (Test-Path $workspaceMarker)){ continue }
        try{
          $content = Get-Content -Path $workspaceMarker -Raw -ErrorAction Stop
          if($content -match 'file:///mnt/' -or $content -match '/mnt/c/' -or $content -match '@source "/mnt/'){
            $needsReset = $true
            break
          }
        } catch {}
      }

      if($needsReset){ break }
    }
  }

  if((-not $needsReset) -and ($workspaceDirs.Count -eq 0)){ return }

  T "Artefatti Nuxt generati da WSL rilevati: pulizia cache Nuxt/host workspace per rigenerazione Windows..." "Yellow"
  foreach($relative in @(".nuxt", ".output")){
    $target = Join-Path $dir $relative
    if(Test-Path $target){
      Remove-Item -Path $target -Recurse -Force -ErrorAction SilentlyContinue
    }
  }

  foreach($workspaceDir in $workspaceDirs){
    if(Test-Path $workspaceDir.FullName){
      Remove-Item -Path $workspaceDir.FullName -Recurse -Force -ErrorAction SilentlyContinue
    }
  }
}


function Set-Or-AddEnvKey([string]$envPath,[string]$key,[string]$value){
  $content = ''
  if(Test-Path $envPath){ $content = Get-Content $envPath -Raw }
  if($content -match "(?m)^$key="){
    $content = [regex]::Replace($content, "(?m)^$key=.*$", "$key=$value")
  } else {
    if($content.Length -gt 0 -and -not $content.EndsWith("`n")){ $content += "`n" }
    $content += "$key=$value`n"
  }
  Set-Content -Path $envPath -Value $content -NoNewline
}

function Normalize-LaravelEnv([string]$backDir,[string]$frontendUrl){
  $envFile = Join-Path $backDir '.env'
  $envExample = Join-Path $backDir '.env.example'
  if(-not (Test-Path $envFile) -and (Test-Path $envExample)){
    Copy-Item $envExample $envFile -Force
  }

  $dbPath = Join-Path $backDir 'database\database.sqlite'
  if(-not (Test-Path $dbPath)){ New-Item -ItemType File -Path $dbPath -Force | Out-Null }

  Set-Or-AddEnvKey $envFile 'DB_CONNECTION' 'sqlite'
  Set-Or-AddEnvKey $envFile 'DB_DATABASE' $dbPath
  Set-Or-AddEnvKey $envFile 'SESSION_DRIVER' 'file'
  Set-Or-AddEnvKey $envFile 'QUEUE_CONNECTION' 'sync'
  Set-Or-AddEnvKey $envFile 'CACHE_STORE' 'file'
  Set-Or-AddEnvKey $envFile 'SANCTUM_STATEFUL_DOMAINS' '127.0.0.1:8787,localhost:8787,127.0.0.1:3001,localhost:3001,127.0.0.1:8000,localhost:8000,*.trycloudflare.com'
  Set-Or-AddEnvKey $envFile 'CORS_ALLOWED_ORIGINS' 'http://127.0.0.1:8787,http://localhost:8787,http://127.0.0.1:3001,http://localhost:3001,http://127.0.0.1:8000,http://localhost:8000'
  Set-Or-AddEnvKey $envFile 'APP_FRONTEND_URL' $frontendUrl

  if(Test-Path (Join-Path $backDir 'artisan')){
    $keyOut = Join-Path $logDir 'keygen_out.log'
    $keyErr = Join-Path $logDir 'keygen_err.log'
    Start-Process -FilePath 'cmd.exe' -WorkingDirectory $backDir -WindowStyle Hidden -Wait `
      -ArgumentList '/c','php artisan key:generate --force' -RedirectStandardOutput $keyOut -RedirectStandardError $keyErr | Out-Null

    $migOut = Join-Path $logDir 'migrate_out.log'
    $migErr = Join-Path $logDir 'migrate_err.log'
    Start-Process -FilePath 'cmd.exe' -WorkingDirectory $backDir -WindowStyle Hidden -Wait `
      -ArgumentList '/c','php artisan migrate --force' -RedirectStandardOutput $migOut -RedirectStandardError $migErr | Out-Null

    $seedOut = Join-Path $logDir 'seed_out.log'
    $seedErr = Join-Path $logDir 'seed_err.log'
    Start-Process -FilePath 'cmd.exe' -WorkingDirectory $backDir -WindowStyle Hidden -Wait `
      -ArgumentList '/c','php artisan db:seed --class=Database\Seeders\DatabaseSeeder --force' -RedirectStandardOutput $seedOut -RedirectStandardError $seedErr | Out-Null

    $locationsOut = Join-Path $logDir 'locations_import_out.log'
    $locationsErr = Join-Path $logDir 'locations_import_err.log'
    Start-Process -FilePath 'cmd.exe' -WorkingDirectory $backDir -WindowStyle Hidden -Wait `
      -ArgumentList '/c','php artisan locations:import --if-empty --country=IT' -RedirectStandardOutput $locationsOut -RedirectStandardError $locationsErr | Out-Null

    # Crea il symlink storage per rendere accessibili le immagini caricate (es. homepage)
    $storageLink = Join-Path $backDir 'public\storage'
    if(-not (Test-Path $storageLink)){
      $linkOut = Join-Path $logDir 'storagelink_out.log'
      $linkErr = Join-Path $logDir 'storagelink_err.log'
      Start-Process -FilePath 'cmd.exe' -WorkingDirectory $backDir -WindowStyle Hidden -Wait `
        -ArgumentList '/c','php artisan storage:link' -RedirectStandardOutput $linkOut -RedirectStandardError $linkErr | Out-Null
    }
  }
}

function Ensure-ComposerInstall([string]$dir){
  if(-not (Test-Path (Join-Path $dir "artisan"))){
    throw "In $dir non trovo artisan: Laravel non sembra valido."
  }
  if(-not (Test-Path (Join-Path $dir "vendor"))){
    $cmd = Get-Command composer -ErrorAction SilentlyContinue
    if(-not $cmd){
      T "Manca vendor ma non trovo 'composer'. Installare Composer oppure farlo installare allo sviluppatore." "Red"
      throw "Composer assente"
    }
    T "Manca vendor (Laravel): composer install..." "Yellow"
    $o = Join-Path $logDir "composer_out.log"
    $e = Join-Path $logDir "composer_err.log"
    Remove-Item $o,$e -Force -ErrorAction SilentlyContinue | Out-Null
    $p = Start-Process -FilePath "cmd.exe" -WorkingDirectory $dir -PassThru -WindowStyle Hidden `
      -ArgumentList "/c","composer install" -RedirectStandardOutput $o -RedirectStandardError $e
    $p.WaitForExit()
    if($p.ExitCode -ne 0){ T "ERRORE composer install. Log: $e" "Red"; throw "composer install fallito" }
    T "composer install completato." "Green"
  }
}

function Start-Local([switch]$NonAprireBrowser, [ValidateSet('dev','preview')][string]$FrontendMode = 'dev'){
  Stop-All

  $frontDir = Find-Frontend
  $backDir  = Find-Backend

  T "Frontend selezionato: $frontDir" "DarkCyan"
  T "Backend selezionato:  $backDir" "DarkCyan"

  # porte dedicate (se vuoi cambiarle, si cambia qui, ma NON tocca BianchiPro)
  $frontPort = 3001
  $backPort  = 8000
  $proxyPort = 8787
  $useCaddy = (Has-Caddyfile) -and (Has-Caddy)
  $apiBase = if($useCaddy){ "http://127.0.0.1:$proxyPort" } else { "http://127.0.0.1:$backPort" }
  $frontendUrl = if($useCaddy){ "http://127.0.0.1:$proxyPort" } else { "http://127.0.0.1:$frontPort" }

  Ensure-NpmInstall $frontDir
  Reset-NuxtArtifactsForWindows $frontDir
  Ensure-ComposerInstall $backDir
  Normalize-LaravelEnv $backDir $frontendUrl

  # log
  $nuxtOut  = Join-Path $logDir "nuxt_out.log"
  $nuxtErr  = Join-Path $logDir "nuxt_err.log"
  $phpOut   = Join-Path $logDir "laravel_out.log"
  $phpErr   = Join-Path $logDir "laravel_err.log"
  $cadOut   = Join-Path $logDir "caddy_out.log"
  $cadErr   = Join-Path $logDir "caddy_err.log"

  Remove-Item $nuxtOut,$nuxtErr,$phpOut,$phpErr,$cadOut,$cadErr -Force -ErrorAction SilentlyContinue | Out-Null

  # Avvio Laravel
  T "Avvio Laravel: http://127.0.0.1:$backPort" "Cyan"
  $pBack = Start-Process -FilePath "cmd.exe" -WorkingDirectory $backDir -PassThru -WindowStyle Hidden `
    -ArgumentList "/c","php artisan serve --host 127.0.0.1 --port $backPort" `
    -RedirectStandardOutput $phpOut -RedirectStandardError $phpErr

  # Avvio Nuxt
  $nuxtCommand = if($FrontendMode -eq 'preview'){
    "set ""NUXT_PUBLIC_API_BASE=$apiBase"" && set ""NUXT_PUBLIC_SANCTUM_BASE_URL=$apiBase"" && set ""NODE_OPTIONS=--max-old-space-size=6144"" && npx nuxi prepare && npm run build && set ""PORT=$frontPort"" && set ""NITRO_PORT=$frontPort"" && set ""HOST=127.0.0.1"" && set ""NITRO_HOST=127.0.0.1"" && node .output\server\index.mjs"
  } else {
    "set ""NUXT_PUBLIC_API_BASE=$apiBase"" && set ""NUXT_PUBLIC_SANCTUM_BASE_URL=$apiBase"" && set ""NODE_OPTIONS=--max-old-space-size=6144"" && npm run dev -- --host 127.0.0.1 --port $frontPort"
  }

  T "Avvio Nuxt ($FrontendMode): http://127.0.0.1:$frontPort" "Cyan"
  $pFront = Start-Process -FilePath "cmd.exe" -WorkingDirectory $frontDir -PassThru -WindowStyle Hidden `
    -ArgumentList "/c",$nuxtCommand `
    -RedirectStandardOutput $nuxtOut -RedirectStandardError $nuxtErr

  # Avvio Caddy se possibile
  $pCaddy = 0
  $base   = "http://127.0.0.1:$frontPort"

  if(Has-Caddyfile){
    if(Has-Caddy){
      T "Avvio Caddy (proxy): http://127.0.0.1:$proxyPort" "Cyan"
      $p = Start-Process -FilePath "cmd.exe" -WorkingDirectory $root -PassThru -WindowStyle Hidden `
        -ArgumentList "/c","caddy run --config Caddyfile --adapter caddyfile" `
        -RedirectStandardOutput $cadOut -RedirectStandardError $cadErr
      $pCaddy = $p.Id
      $base   = "http://127.0.0.1:$proxyPort"
    } else {
      T "Caddyfile presente ma Caddy non trovato: uso Nuxt diretto." "Yellow"
    }
  } else {
    T "Caddyfile non presente: uso Nuxt diretto." "Yellow"
  }

  Save-State @{
    frontend   = $pFront.Id
    backend    = $pBack.Id
    caddy      = $pCaddy
    cloudflared= 0
    frontendMode = $FrontendMode
    frontPort  = $frontPort
    backPort   = $backPort
    proxyPort  = $proxyPort
    base       = $base
  }

  # attese: prima Nuxt e Laravel, poi base finale
  T "Attendere avvio Nuxt..." "DarkCyan"
  [void](Wait-HttpOrFail ("http://127.0.0.1:$frontPort") $pFront "Nuxt" $nuxtErr 240)

  T "Attendere avvio Laravel..." "DarkCyan"
  [void](Wait-HttpOrFail ("http://127.0.0.1:$backPort") $pBack "Laravel" $phpErr 240)

  T "Attendere avvio base finale..." "DarkCyan"
  if(-not (Wait-Http $base 240)){
    T "ERRORE: base non risponde: $base" "Red"
    throw "Base non risponde"
  }

  T "PRONTO (locale/$FrontendMode): $base" "Green"
  if(-not $NonAprireBrowser){ Start-Process $base | Out-Null }
}

function Get-CloudflaredPath(){
  $cmd = Get-Command cloudflared -ErrorAction SilentlyContinue
  if($cmd){ return $cmd.Source }

  $p1 = Join-Path $env:ProgramFiles "Cloudflare\Cloudflared\cloudflared.exe"
  $p2 = Join-Path ${env:ProgramFiles(x86)} "Cloudflare\Cloudflared\cloudflared.exe"
  if(Test-Path $p1){ return $p1 }
  if(Test-Path $p2){ return $p2 }

  throw "cloudflared non trovato. Installare Cloudflare Tunnel."
}

function Share-Online(){
  $existingOnline = Get-OnlineUrl
  if($existingOnline -and (Test-OnlineUrl $existingOnline)){
    T "Link pubblico gia' attivo: $existingOnline" "Green"
    Start-Process $existingOnline | Out-Null
    return
  }

  $shareScript = Join-Path $root "scripts\avvia-cloudflare.ps1"
  if(-not (Test-Path $shareScript)){
    throw "Script condividi online non trovato: $shareScript"
  }

  T "Avvio link pubblico con script coordinato..." "Cyan"
  & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $shareScript

  $pub = Get-OnlineUrl
  if(-not $pub){
    throw "URL_ONLINE.txt non aggiornato dopo avvio condividi online."
  }

  if(-not (Test-OnlineUrl $pub)){
    T "ERRORE: il link pubblico non risponde: $pub" "Red"
    throw "Link pubblico non raggiungibile"
  }

  T "PRONTO (online): $pub" "Green"
  T "Link salvato in: $urlFile" "DarkGreen"
  Start-Process $pub | Out-Null
}

function Open-Local(){
  $s = Load-State
  $base = "http://127.0.0.1:8787"
  if($s -and $s.base){ $base = $s.base }
  Start-Process $base | Out-Null
}

function Open-Online(){
  $u = Get-OnlineUrl
  if($u){
    if(Test-OnlineUrl $u){
      Start-Process $u | Out-Null
      return
    }

    Remove-Item $urlFile -Force -ErrorAction SilentlyContinue | Out-Null
    T "Link online non piu raggiungibile. Rifai 'Condividi online'." "Yellow"
    return
  }
  T "Non trovo URL_ONLINE.txt (prima fare 'Condividi online')." "Yellow"
}

function Tail-Log(){
  T "Scegli log: 1=Nuxt OUT | 2=Nuxt ERR | 3=Laravel OUT | 4=Laravel ERR | 5=Caddy OUT | 6=Caddy ERR | 7=Cloudflared OUT | 8=Cloudflared ERR" "Yellow"
  $c = Read-KeyChoice "Log"
  if($c -eq $null){ $c = "" }
  $c = $c.Trim()

  $map = @{
    "1" = (Join-Path $logDir "nuxt_out.log")
    "2" = (Join-Path $logDir "nuxt_err.log")
    "3" = (Join-Path $logDir "laravel_out.log")
    "4" = (Join-Path $logDir "laravel_err.log")
    "5" = (Join-Path $logDir "caddy_out.log")
    "6" = (Join-Path $logDir "caddy_err.log")
    "7" = (Join-Path $logDir "cloudflared_out.log")
    "8" = (Join-Path $logDir "cloudflared_err.log")
  }

  if(-not $map.ContainsKey($c)){
    T "Scelta non valida." "Yellow"
    return
  }

  $p = $map[$c]
  if(-not (Test-Path $p)){
    T "Log non trovato: $p" "Yellow"
    return
  }

  T "Apro log (Ctrl+C per tornare al menu)..." "Cyan"
  Get-Content -Path $p -Tail 200 -Wait
}

function Show-Status(){
  $s = Load-State
  Write-Host ""
  Write-Host "============================================" -ForegroundColor DarkCyan
  Write-Host "      SPEDIZIONEFACILE CONTROL PANEL       " -ForegroundColor Cyan
  Write-Host "============================================" -ForegroundColor DarkCyan
  Write-Host "Cartella: $root" -ForegroundColor DarkGray

  $base = "http://127.0.0.1:8787"
  if($s -and $s.base){ $base = $s.base }
  Write-Host "Locale : $base" -ForegroundColor Cyan

  $u = Get-OnlineUrl
  if($u){
    if(Test-OnlineUrl $u){
      Write-Host "Online : $u" -ForegroundColor Green
    } else {
      Write-Host "Online : $u (non raggiungibile)" -ForegroundColor Yellow
    }
  } else {
    Write-Host "Online : (non attivo)" -ForegroundColor DarkGray
  }

  if($s){
    Write-Host "PID Nuxt      : $($s.frontend)" -ForegroundColor DarkGray
    Write-Host "PID Laravel   : $($s.backend)" -ForegroundColor DarkGray
    Write-Host "PID Caddy     : $($s.caddy)" -ForegroundColor DarkGray
    Write-Host "PID Cloudflared: $($s.cloudflared)" -ForegroundColor DarkGray
  } else {
    Write-Host "Stato: (nessuno)" -ForegroundColor DarkGray
  }

  Write-Host "--------------------------------------------" -ForegroundColor DarkCyan
  Write-Host "Legenda colori menu: [Verde=Avvio] [Cyan=Online] [Rosso=Stop] [Magenta=Apri] [Giallo=Log]" -ForegroundColor DarkGray
  Write-Host ""
  Write-Host "1 = Avvia locale" -ForegroundColor Green
  Write-Host "2 = Condividi online (link pubblico)" -ForegroundColor Cyan
  Write-Host "3 = Chiudi tutto" -ForegroundColor Red
  Write-Host "4 = Apri locale nel browser" -ForegroundColor Magenta
  Write-Host "5 = Vedi log" -ForegroundColor Yellow
  Write-Host "Q = Esci" -ForegroundColor DarkGray
  Write-Host ""
}

function Menu(){
  while($true){
    Show-Status
    $k = (Read-KeyChoice "Menu").ToUpper()

    if($k -eq "1"){ Start-Local; continue }
    if($k -eq "2"){ Share-Online; continue }
    if($k -eq "3"){ Stop-All; continue }
    if($k -eq "4"){ Open-Local; continue }
    if($k -eq "5"){ Tail-Log; continue }
    if($k -eq "Q"){ break }

    T "Scelta non valida." "Yellow"
  }
}

# Azione diretta da .bat
$act = $Azione
if($act -eq $null){ $act = "MENU" }
$act = $act.Trim().ToUpper()

if($act -eq "AVVIA_LOCALE"){ Start-Local; Menu; exit }
if($act -eq "CONDIVIDI_ONLINE"){ Share-Online; Menu; exit }
if($act -eq "CHIUDI_TUTTO"){ Stop-All; Menu; exit }
if($act -eq "APRI_LOG"){ Tail-Log; Menu; exit }

Menu
