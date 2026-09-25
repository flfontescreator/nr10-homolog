$ErrorActionPreference = 'Stop'
$files = @(
    "C:\Users\Fabio\Documents\Default Project\nr10-app\app\Http\Controllers\CronogramaController.php",
    "C:\Users\Fabio\Documents\Default Project\nr10-app\app\Http\Controllers\ProntuarioController.php"
)
$utf8 = New-Object System.Text.UTF8Encoding $false, $true
$latin1 = [System.Text.Encoding]::GetEncoding(1252)
$matched = 0
foreach ($f in $files) {
    $bytes = [System.IO.File]::ReadAllBytes($f)
    if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
        $bytes = $bytes[3..($bytes.Length-1)]
    }
    $s = $utf8.GetString($bytes)
    if (-not $s.Contains('Ã')) { continue }
    $fixed = $latin1.GetString($utf8.GetBytes($s)) # undo double-encode: current UTF8 -> mojibake str -> 1252 -> original bytes text
    # now $fixed is plain text in UTF-8 encoding terms; write original-corrected UTF-8 bytes
    [System.IO.File]::WriteAllText($f, $fixed, $utf8)
    $matched++
    "fixed: $f"
}
"done fixed=$matched"