# Proklikání trezoru přes UI Automation.
#
# Spouští okno, vybere cíl, zmáčkne Upravit, ověří vyplněný formulář, uloží
# a nakonec vyzkouší spojení u prvního FTP cíle.
# Existuje proto, že 22. 9. 2026 spadlo okno hned při kliknutí na Upravit:
# místní proměnná $cil přepsala ovládací prvek $Cil (PowerShell nerozlišuje
# velikost písmen). Skript s tím nespadl, spadlo až okno u zadavatele.
#
#   powershell -File hooky/test-trezor.ps1
#
# Soubor musí zůstat v UTF-8 s BOM, jinak se česká jména tlačítek nenajdou.
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName UIAutomationClient
Add-Type -AssemblyName UIAutomationTypes

$AE = [System.Windows.Automation.AutomationElement]
$TS = [System.Windows.Automation.TreeScope]::Descendants

$chyby = Join-Path $env:TEMP 'trezor-klik-chyby.txt'
Remove-Item $chyby, (Join-Path $env:TEMP 'trezor-chyby.log') -ErrorAction SilentlyContinue

$proces = Start-Process powershell -ArgumentList '-NoProfile','-ExecutionPolicy','Bypass','-File','C:\laragon\www\nastroje\hooky\trezor-spravce.ps1' -PassThru -RedirectStandardError $chyby
for ($i = 0; $i -lt 25; $i++) { Start-Sleep -Milliseconds 600; $proces.Refresh(); if ($proces.MainWindowHandle -ne 0) { break } }
$okno = $AE::FromHandle($proces.MainWindowHandle)

function Najdi($jmeno) {
    $okno.FindFirst($TS, (New-Object System.Windows.Automation.PropertyCondition($AE::NameProperty, $jmeno)))
}
function Klikni($jmeno) {
    $t = Najdi $jmeno
    if ($null -eq $t) { throw "tlacitko '$jmeno' nenalezeno" }
    $t.GetCurrentPattern([System.Windows.Automation.InvokePattern]::Pattern).Invoke()
    Start-Sleep -Milliseconds 900
}
function TextPolicka($automationId) {
    $p = $okno.FindFirst($TS, (New-Object System.Windows.Automation.PropertyCondition($AE::AutomationIdProperty, $automationId)))
    if ($null -eq $p) { return '(pole nenalezeno)' }
    $vzor = $p.GetCurrentPattern([System.Windows.Automation.ValuePattern]::Pattern)
    $vzor.Current.Value
}
function StavovyRadek {
    $texty = $okno.FindAll($TS, (New-Object System.Windows.Automation.PropertyCondition(
        $AE::ControlTypeProperty, [System.Windows.Automation.ControlType]::Text)))
    $posledni = ''
    for ($i = 0; $i -lt $texty.Count; $i++) {
        $n = $texty.Item($i).Current.Name
        if ($n -match 'Upravuješ|Upraveno|Uloženo|Chyba|V trezoru|spojení') { $posledni = $n }
    }
    $posledni
}

# 1. vybrat druhý cíl v seznamu (ať se pozná, že se vyplní správný)
$polozky = $okno.FindAll($TS, (New-Object System.Windows.Automation.PropertyCondition(
    $AE::ControlTypeProperty, [System.Windows.Automation.ControlType]::ListItem)))
$polozky.Item(1).GetCurrentPattern([System.Windows.Automation.SelectionItemPattern]::Pattern).Select()
Start-Sleep -Milliseconds 500

# 2. upravit
Klikni 'Upravit vybraný'
'po kliknuti Upravit:'
'  nazev cile v poli = ' + (TextPolicka 'Cil')
'  server = ' + (TextPolicka 'Server')
'  slozka = ' + (TextPolicka 'Slozka')
'  stav = ' + (StavovyRadek)
$tlacitkoUlozit = Najdi 'Uložit změny'
'  tlacitko prepnute na Ulozit zmeny: ' + ($null -ne $tlacitkoUlozit)

# 3. uložit změny (bez hesla, mění se jen vyplněná pole)
if ($null -ne $tlacitkoUlozit) {
    Klikni 'Uložit změny'
    Start-Sleep -Seconds 1
    '  stav po ulozeni = ' + (StavovyRadek)
}

# 4. zkouška spojení u prvního FTP cíle: hlásit se musí podle návratového kódu,
# ne podle textu ve výpisu. Dřív to u funkčního spojení psalo „spojení selhalo".
# Seznam se po uložení překreslí, staré prvky už neplatí - najít znovu.
$polozky = $okno.FindAll($TS, (New-Object System.Windows.Automation.PropertyCondition(
    $AE::ControlTypeProperty, [System.Windows.Automation.ControlType]::ListItem)))
$ftp = $null
for ($i = 0; $i -lt $polozky.Count; $i++) {
    if ($polozky.Item($i).Current.Name -match 'Druh=ftp') { $ftp = $polozky.Item($i); break }
}
if ($null -ne $ftp) {
    $ftp.GetCurrentPattern([System.Windows.Automation.SelectionItemPattern]::Pattern).Select()
    Start-Sleep -Milliseconds 400
    Klikni 'Vyzkoušet spojení'
    Start-Sleep -Seconds 12
    $stavSpojeni = StavovyRadek
    'po zkousce spojeni:'
    '  stav = ' + $stavSpojeni
    if ($stavSpojeni -notmatch 'spojení funguje') { '  CHYBA: zkouska spojeni nehlasi uspech' }
} else {
    'v trezoru neni zadny FTP cil, zkouska spojeni preskocena'
}

$proces.Refresh()
if ($proces.HasExited) {
    'APLIKACE SPADLA'
    Get-Content $chyby -Tail 20 | Out-String
} else {
    'aplikace bezi dal'
    Stop-Process -Id $proces.Id -Force
}
$log = Join-Path $env:TEMP 'trezor-chyby.log'
if (Test-Path $log) { 'chyby v logu:'; Get-Content $log | Out-String } else { 'log chyb je prazdny' }
