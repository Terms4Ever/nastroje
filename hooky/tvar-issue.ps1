# Hook Claude Code: tvar issue se kontroluje dřív, než issue vznikne.
#
# Čte JSON na vstupu (PreToolUse), zajímá ho zakládání a úprava issue.
# Když tělo neodpovídá šabloně, vrátí kód 2 a příkaz se nespustí.
# Pravidla žijí v kontrola-tvaru-issue.php o složku výš. Soubor musí zůstat
# v UTF-8 s BOM: Windows PowerShell 5.1 bez BOM čte skript v systémovém
# kódování a česká hláška by se rozsypala.

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [Text.Encoding]::UTF8

function Zastav($zprava) {
    # Vlastni zapisovac, protoze [Console]::Error pise v systemovem kodovani
    # a ceska hlaska by dorazila rozsypana.
    $zapis = New-Object System.IO.StreamWriter([Console]::OpenStandardError(), (New-Object System.Text.UTF8Encoding($false)))
    $zapis.AutoFlush = $true
    $zapis.WriteLine($zprava)
    $zapis.Flush()
    exit 2
}

$vstup = [Console]::In.ReadToEnd()
if (-not $vstup) { exit 0 }
try { $data = $vstup | ConvertFrom-Json } catch { exit 0 }

$prikaz = $data.tool_input.command
if (-not $prikaz) { exit 0 }

# Volani musi stat na zacatku prikazu, za rourou, strednikem nebo uvozovkou.
# Bez ukotveni hook zastavil i prikaz, ktery ten retezec jen zminuje v textu
# (zapis dokumentace o hooku), coz se stalo 22. 9. 2026.
$zacatek = '(?m)(^|[;&|(\"''`])\s*'
$jeIssue = $prikaz -match ($zacatek + 'gh\s+issue\s+(create|edit|comment)\b')
$jeApi = ($prikaz -match ($zacatek + 'gh\s+api\b')) -and ($prikaz -match '/issues')
if (-not $jeIssue -and -not $jeApi) { exit 0 }

if ($jeApi -and $prikaz -match '(-X|--method)\s+POST') {
    Zastav "Issue se zakládá příkazem gh issue create --body-file <soubor>, ne přes gh api: tělo se jinak nedá zkontrolovat proti šabloně."
}
if (-not $jeIssue) { exit 0 }

# Telo se predava souborem. Inline --body neni spolehlive precist (uvozovky,
# heredoc, viceradkove retezce), takze se rovnou zakazuje.
if ($prikaz -match '(^|\s)(--body|-b)(\s|=)') {
    Zastav "Tělo předávej souborem: --body-file <soubor>. Vložené --body je zakázané, protože se nedá zkontrolovat proti šabloně (viz Issues v CLAUDE.md)."
}

# Zarazeni se pridava uz pri zakladani. Doplnovat stitek a odpovedneho pozdeji
# znamena, ze to nikdo neudela: 22. 9. 2026 byly ctyri issue bez obojiho.
if ($prikaz -match ($zacatek + 'gh\s+issue\s+create\b')) {
    if ($prikaz -notmatch '(^|\s)(--label|-l)(\s|=)') {
        Zastav "Chybí štítek druhu: přidej --label bug u chyby, --label enhancement u nové funkce (podle sekce Problém/Cíl v těle)."
    }
    if ($prikaz -notmatch '(^|\s)(--assignee|-a)(\s|=)') {
        Zastav "Chybí odpovědný: přidej --assignee Terms4Ever. Bez něj se v seznamu issues nepozná, kdo to má na stole."
    }
}

$shoda = [regex]::Match($prikaz, '(?:--body-file|-F)[\s=]+(?:"([^"]+)"|''([^'']+)''|(\S+))')
if (-not $shoda.Success) { exit 0 }

$cesta = ($shoda.Groups[1].Value + $shoda.Groups[2].Value + $shoda.Groups[3].Value)
if ($cesta -eq '-') { exit 0 }
if (-not [System.IO.Path]::IsPathRooted($cesta)) {
    $zaklad = $data.cwd
    if (-not $zaklad) { $zaklad = (Get-Location).Path }
    $cesta = Join-Path $zaklad $cesta
}
# Cesta z Git Bashe (/c/...) na windowsovou
if ($cesta -match '^[\/]([a-zA-Z])[\/](.*)$') {
    $cesta = $Matches[1].ToUpper() + ':\' + $Matches[2]
}
if (-not (Test-Path $cesta)) { exit 0 }

$php = 'C:\laragon\bin\php\php-8.3\php.exe'
if (-not (Test-Path $php)) {
    $nalezeny = Get-Command php -ErrorAction SilentlyContinue
    if (-not $nalezeny) { exit 0 }
    $php = $nalezeny.Source
}
$skript = Join-Path (Split-Path -Parent $PSScriptRoot) 'kontrola-tvaru-issue.php'
if (-not (Test-Path $skript)) { exit 0 }

$rezim = '--prisne'
if ($prikaz -match ($zacatek + 'gh\s+issue\s+comment\b')) { $rezim = '--komentar' }

# Pozor: `2>&1` u nativniho programu ve Windows PowerShellu 5.1 zabali kazdy
# radek stderr do chyby a pri ErrorActionPreference = Stop skript spadne
# s kodem 1 driv, nez staci vratit 2. Stderr proto jde do souboru.
$ErrorActionPreference = 'Continue'
$chybovy = Join-Path $env:TEMP ('tvar-issue-' + [guid]::NewGuid().ToString() + '.txt')
& $php $skript $cesta $rezim 2> $chybovy | Out-Null
$kod = $LASTEXITCODE
$text = ''
if (Test-Path $chybovy) {
    # PowerShell k vypisu nativniho programu pripise svoje ("php.exe : ",
    # "At C:\...", "+ CategoryInfo"). Do hlasky patri jen text kontroly.
    # PowerShell 5.1 zapisuje presmerovany stderr v UTF-16LE, ne v UTF-8.
    $radky = @(Get-Content -Encoding Unicode $chybovy |
        Where-Object { $_ -notmatch '^(At |\s*\+)' } |
        ForEach-Object { $_ -replace '^php\.exe\s*:\s*', '' })
    $text = ($radky -join [Environment]::NewLine)
    Remove-Item $chybovy -Force
}
if ($kod -ne 0) {
    Zastav $text.Trim()
}
exit 0
