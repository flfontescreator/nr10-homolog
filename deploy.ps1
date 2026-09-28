$ErrorActionPreference = 'Stop'

# ============================================================
#  Deploy GreenJob (NR-10) - Homologacao Hostinger
#  Uso:  powershell -ExecutionPolicy Bypass -File deploy.ps1
#
#  Deploy incremental via git local (sem git no servidor):
#   - 1o run: sobe TODOS os arquivos rastreados (sincronizacao).
#   - runs seguintes: sobe SOMENTE o delta (git diff) desde a tag
#     "deployed" (que aponta o ultimo estado enviado), aplica
#     delecoes de arquivos rastreados, faz backup do banco, roda
#     migrations e limpa cache.
#  Nunca apaga .env, storage (uploads/logs/cache), vendor e dados do banco.
# ============================================================

$HostAddr   = 'u983733811@185.211.7.209'
$Port       = 65002
$Pass       = 'OiMeuAmor@4649'
$LocalRoot  = 'C:\Users\Fabio\Documents\Default Project\nr10-app'
$RemoteDir  = '/home/u983733811/domains/legacyit.com.br/public_html/clientes/greenjob/app/gestaonr10'
$Plink      = 'C:\Program Files\PuTTY\plink.exe'
$Pscp       = 'C:\Program Files\PuTTY\pscp.exe'
$PhpRemote  = '/opt/alt/php83/usr/bin/php'

$TmpDir     = Join-Path $env:TEMP 'nr10-deploy-delta'
New-Item -ItemType Directory -Path $TmpDir -Force | Out-Null
$Payload    = Join-Path $TmpDir 'nr10-deploy.tgz'
$Deletions  = Join-Path $TmpDir 'nr10-deletions.list'
$FileList   = Join-Path $TmpDir 'nr10-files.txt'
$RemoteTgz  = '/tmp/nr10-deploy.tgz'
$RemoteDel  = '/tmp/nr10-deletions.list'

function Write-ListFile([string]$path, [string[]]$lines) {
    $enc = New-Object System.Text.UTF8Encoding($false)
    $content = (($lines | Where-Object { $_ } ) -join "`n")
    if ($content.Length -gt 0) { $content += "`n" }
    [System.IO.File]::WriteAllText($path, $content, $enc)
}

function Write-Phase([string]$msg) { Write-Host ">>> $msg" }

Push-Location $LocalRoot
try {
    Write-Phase '1/6 Snapshot do trabalho (git commit automatico)...'
    & git add -A
    if ($LASTEXITCODE -ne 0) { throw 'git add falhou' }
    & git -c user.name="Deploy Snapshot" -c user.email="deploy@snapshot.local" commit -m "deploy snapshot $(Get-Date -Format 'yyyy-MM-dd HH:mm')" --no-verify 2>$null

    Write-Phase '2/6 Calculando delta (git diff desde a tag deployed)...'
    & git rev-parse -q --verify refs/tags/deployed
    if ($LASTEXITCODE -eq 0) {
        $mode = 'delta'
        $oldSha = (& git rev-parse refs/tags/deployed).Trim()
        $changed = @(& git diff --name-only --diff-filter=ACMRTUB "$oldSha" HEAD)
        $deleted = @(& git diff --name-only --diff-filter=D "$oldSha" HEAD)
    } else {
        $mode = 'full (sincronizacao inicial)'
        $changed = @(& git ls-files) | Where-Object { $_ }
        $deleted = @()
    }

    $changed = @($changed | Where-Object { $_ -ne 'deploy.ps1' })
    $deleted = @($deleted | Where-Object { $_ -ne 'deploy.ps1' })
    Write-ListFile $FileList $changed
    if ($deleted.Count -gt 0) { Write-ListFile $Deletions $deleted }

    Write-Host "    Modo: $mode | arquivos a subir: $($changed.Count) | a remover: $($deleted.Count)"

    Write-Phase '3/6 Empacotando somente os arquivos alterados...'
    tar -a -c -z -f $Payload -T $FileList
    if ($LASTEXITCODE -ne 0) { throw 'tar (delta) falhou' }
    $size = (Get-Item $Payload).Length
    Write-Host "    Payload: $([math]::Round($size/1KB,1)) KB"

    Write-Phase '4/6 Enviando pacote via SCP...'
    & $Pscp -pw $Pass -P $Port -q $Payload "$HostAddr`:$RemoteTgz"
    if ($LASTEXITCODE -ne 0) { throw 'SCP do pacote falhou' }
    if ($deleted.Count -gt 0) {
        & $Pscp -pw $Pass -P $Port -q $Deletions "$HostAddr`:$RemoteDel"
        if ($LASTEXITCODE -ne 0) { throw 'SCP da lista de delecoes falhou' }
    }

    Write-Phase '5/6 Extraindo, backup do banco, migrations e cache...'
    # Backup via command deploy:db-backup (mysqldump) antes do migrate; se
    # falhar, o deploy aborta sem tocar no banco (fail-closed).
    $remote = "export PATH=/usr/bin:/bin:/usr/local/bin; cd $RemoteDir && tar -xzf $RemoteTgz"
    if ($deleted.Count -gt 0) {
        $remote += " && xargs -r rm -f < $RemoteDel"
    }
    $remote += " && $PhpRemote artisan deploy:db-backup --dir=`$HOME/deploy-backups 2>&1 && $PhpRemote artisan migrate --force 2>&1 && $PhpRemote artisan optimize:clear 2>&1 && rm -f $RemoteTgz $RemoteDel"
    & $Plink -ssh $HostAddr -P $Port -pw $Pass -batch $remote
    if ($LASTEXITCODE -ne 0) { throw 'Extracao/migracao falhou no servidor' }

    Write-Phase '6/6 Marcando estado enviado (tag deployed) e validando paginas...'
    & git tag -f deployed HEAD
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao atualizar a tag deployed' }

    $base = 'https://legacyit.com.br/clientes/greenjob/app/gestaonr10'
    # Paginas autenticadas respondem com redirect (302) para /login; um 500
    # (ex.: ViteException ou view quebrada apos o deploy) sai como status 5xx.
    foreach ($p in @('/login', '/forgot-password', '/cronograma', '/checklist/documentos', '/documentos')) {
        try {
            $r = Invoke-WebRequest -Uri "$base$p" -UseBasicParsing -MaximumRedirection 0 -TimeoutSec 30 -ErrorAction Stop
            Write-Host "    $p -> $($r.StatusCode)"
        } catch {
            $ex = $_.Exception
            if ($ex.Response) {
                Write-Host "    $p -> $([int]$ex.Response.StatusCode) ($($ex.Response.StatusCode))"
            } elseif ($ex -is [System.InvalidOperationException]) {
                Write-Host "    $p -> 302 (redirect)"
            } else {
                Write-Host "    $p -> ERRO: $($ex.Message)"
            }
        }
    }

    Write-Host '>>> Deploy concluido.'
} finally {
    Pop-Location
    Remove-Item -Recurse -Force $TmpDir -ErrorAction SilentlyContinue
}