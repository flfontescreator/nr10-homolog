$ErrorActionPreference = 'Stop'

# ============================================================
#  Deploy GreenJob (NR-10) - Homologacao Hostinger
#  Uso:  powershell -ExecutionPolicy Bypass -File deploy.ps1
#  Reflete 100% dos arquivos locais no servidor (exceto itens
#  preservados automaticamente: .env, .env.* e storage/prod).
#  Roda migrations pendentes e limpa caches ao final.
# ============================================================

$HostAddr   = 'u983733811@185.211.7.209'
$Port       = 65002
$Pass       = 'OiMeuAmor@4649'
$LocalRoot  = 'C:\Users\Fabio\Documents\Default Project\nr10-app'
$RemoteDir  = '/home/u983733811/domains/legacyit.com.br/public_html/clientes/greenjob/app/gestaonr10'
$Plink      = 'C:\Program Files\PuTTY\plink.exe'
$Pscp       = 'C:\Program Files\PuTTY\pscp.exe'
$PhpRemote  = '/opt/alt/php83/usr/bin/php'

$Payload    = Join-Path $env:TEMP 'nr10-deploy.tgz'
$RemoteTmp  = '/tmp/nr10-deploy.tgz'

Write-Host '>>> 1/5 Empacotando projeto (sem node_modules, .env, storage/prod)...'
Push-Location $LocalRoot
try {
    tar -a -c -z -f $Payload `
        --exclude='vendor' `
        --exclude='node_modules' `
        --exclude='.git' `
        --exclude='.env' `
        --exclude='.env.*' `
        --exclude='storage/app/private' `
        --exclude='storage/logs' `
        --exclude='storage/framework/cache' `
        --exclude='storage/framework/sessions' `
        --exclude='storage/framework/views' `
        --exclude='storage/framework/testing' `
        --exclude='storage/debugbar' `
        --exclude='probe_status.php' `
        --exclude='deploy.ps1' `
        .
    if ($LASTEXITCODE -ne 0) { throw "tar falhou (exit $LASTEXITCODE)" }
} finally {
    Pop-Location
}
$size = (Get-Item $Payload).Length
Write-Host "    Payload: $([math]::Round($size/1MB,1)) MB"

Write-Host '>>> 2/5 Enviando pacote via SCP...'
& $Pscp -pw $Pass -P $Port $Payload "$HostAddr`:$RemoteTmp"
if ($LASTEXITCODE -ne 0) { throw 'SCP falhou' }

Write-Host '>>> 3/5 Extraindo no servidor...'
& $Plink -ssh $HostAddr -P $Port -pw $Pass -batch "export PATH=/usr/bin:/bin:/usr/local/bin; cd $RemoteDir && tar -xzf $RemoteTmp && rm -f $RemoteTmp"
if ($LASTEXITCODE -ne 0) { throw 'Extracao falhou' }

Write-Host '>>> 4/5 Aplicando migrations e limpeza de caches...'
& $Plink -ssh $HostAddr -P $Port -pw $Pass -batch "export PATH=/usr/bin:/bin:/usr/local/bin; cd $RemoteDir && $PhpRemote artisan migrate --force 2>&1 && $PhpRemote artisan optimize:clear 2>&1"
if ($LASTEXITCODE -ne 0) { throw 'Passo de manutencao falhou' }

Write-Host '>>> 5/5 Validando paginas publicas...'
$base = 'https://legacyit.com.br/clientes/greenjob/app/gestaonr10'
foreach ($p in @('/login', '/forgot-password')) {
    try {
        $r = Invoke-WebRequest -Uri "$base$p" -UseBasicParsing -MaximumRedirection 0 -TimeoutSec 30 -ErrorAction Stop
        Write-Host "    $p -> $($r.StatusCode)"
    } catch {
        if ($_.Exception.Response) {
            Write-Host "    $p -> $([int]$_.Exception.Response.StatusCode) (redirect)"
        } else {
            Write-Host "    $p -> ERRO: $($_.Exception.Message)"
        }
    }
}

Write-Host '>>> Deploy concluido.'
Remove-Item -LiteralPath $Payload -ErrorAction SilentlyContinue