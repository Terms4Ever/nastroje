# Okenní správce trezoru: přidat, převzít z WinSCP, smazat.
#
# Pouští se zástupcem z plochy, žádná příkazová řádka. Hodnoty se ukládají
# stejně jako v tajemstvi.ps1, tedy přes DPAPI do %USERPROFILE%\.tajemstvi.
#
# Soubor musí zůstat v UTF-8 s BOM, jinak Windows PowerShell 5.1 přečte
# češtinu rozsypaně.

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

$TREZOR = Join-Path $env:USERPROFILE '.tajemstvi'
$SEZENI_WINSCP = 'HKCU:\Software\Martin Prikryl\WinSCP 2\Sessions'
$NASTROJ = Join-Path $PSScriptRoot 'tajemstvi.ps1'

function NactiCile {
    if (-not (Test-Path $TREZOR)) { return @() }
    Get-ChildItem $TREZOR -Filter *.xml | ForEach-Object {
        $z = Import-Clixml $_.FullName
        [pscustomobject]@{
            Cil = $z.Cil
            Popis = '{0,-26} {1}@{2}  ({3}, {4})' -f $z.Cil, $z.Uzivatel, $z.Server, $z.Protokol, $z.Slozka
        }
    }
}

function Hlaska($text, $nadpis) {
    [System.Windows.Forms.MessageBox]::Show($text, $nadpis) | Out-Null
}

function OknoPridat {
    $f = New-Object System.Windows.Forms.Form
    $f.Text = 'Trezor: nový cíl'
    $f.Size = New-Object System.Drawing.Size(500, 400)
    $f.StartPosition = 'CenterParent'

    $popisky = @('Název cíle (bez mezer)', 'Server', 'Uživatel', 'Heslo', 'Protokol', 'Složka na serveru')
    $klice = @('Cil', 'Server', 'Uzivatel', 'Heslo', 'Protokol', 'Slozka')
    $pole = @{}

    for ($i = 0; $i -lt $popisky.Count; $i++) {
        $l = New-Object System.Windows.Forms.Label
        $l.Text = $popisky[$i]
        $l.Location = New-Object System.Drawing.Point(18, (22 + $i * 45))
        $l.Size = New-Object System.Drawing.Size(190, 20)
        $f.Controls.Add($l)

        $t = New-Object System.Windows.Forms.TextBox
        $t.Location = New-Object System.Drawing.Point(215, (20 + $i * 45))
        $t.Size = New-Object System.Drawing.Size(240, 24)
        if ($klice[$i] -eq 'Heslo') { $t.UseSystemPasswordChar = $true }
        if ($klice[$i] -eq 'Protokol') { $t.Text = 'ftpes' }
        if ($klice[$i] -eq 'Slozka') { $t.Text = '/' }
        $f.Controls.Add($t)
        $pole[$klice[$i]] = $t
    }

    $ok = New-Object System.Windows.Forms.Button
    $ok.Text = 'Uložit'
    $ok.Location = New-Object System.Drawing.Point(215, 300)
    $ok.Size = New-Object System.Drawing.Size(110, 30)
    $ok.DialogResult = [System.Windows.Forms.DialogResult]::OK
    $f.Controls.Add($ok)
    $f.AcceptButton = $ok

    $zrus = New-Object System.Windows.Forms.Button
    $zrus.Text = 'Zpět'
    $zrus.Location = New-Object System.Drawing.Point(345, 300)
    $zrus.Size = New-Object System.Drawing.Size(110, 30)
    $zrus.DialogResult = [System.Windows.Forms.DialogResult]::Cancel
    $f.Controls.Add($zrus)
    $f.CancelButton = $zrus

    if ($f.ShowDialog() -ne [System.Windows.Forms.DialogResult]::OK) { return }

    $cil = $pole['Cil'].Text.Trim()
    if ($cil -notmatch '^[a-zA-Z0-9._-]+$') {
        Hlaska 'Název cíle smí mít jen písmena, číslice, tečku, pomlčku a podtržítko.' 'Trezor'
        return
    }
    if (-not $pole['Heslo'].Text) {
        Hlaska 'Heslo je prázdné, nic se neuložilo.' 'Trezor'
        return
    }

    New-Item -ItemType Directory -Path $TREZOR -Force | Out-Null
    $cesta = Join-Path $TREZOR ($cil + '.xml')
    @{
        Cil = $cil
        Server = $pole['Server'].Text.Trim()
        Uzivatel = $pole['Uzivatel'].Text.Trim()
        Heslo = (ConvertTo-SecureString $pole['Heslo'].Text -AsPlainText -Force)
        Protokol = $(if ($pole['Protokol'].Text) { $pole['Protokol'].Text.Trim() } else { 'ftpes' })
        Slozka = $(if ($pole['Slozka'].Text) { $pole['Slozka'].Text.Trim() } else { '/' })
        Otisk = ''
        UlozenoAt = (Get-Date).ToString('s')
    } | Export-Clixml -Path $cesta
    icacls $cesta /inheritance:r /grant:r "${env:USERNAME}:(R,W)" | Out-Null

    Hlaska "Uloženo: $cil (heslo o $($pole['Heslo'].Text.Length) znacích)" 'Trezor'
}

function OknoZWinscp {
    $sezeni = @()
    if (Test-Path $SEZENI_WINSCP) {
        $sezeni = Get-ChildItem $SEZENI_WINSCP |
            Select-Object -ExpandProperty PSChildName |
            ForEach-Object { $_ -replace '%2F', '/' } |
            Where-Object { $_ -ne 'Default%20Settings' -and $_ -ne 'Default Settings' } |
            Sort-Object
    }
    if (-not $sezeni) {
        Hlaska 'WinSCP tu nemá uložené žádné sezení.' 'Trezor'
        return
    }

    $f = New-Object System.Windows.Forms.Form
    $f.Text = 'Trezor: převzít sezení z WinSCP'
    $f.Size = New-Object System.Drawing.Size(620, 300)
    $f.StartPosition = 'CenterParent'

    $l1 = New-Object System.Windows.Forms.Label
    $l1.Text = 'Sezení ve WinSCP'
    $l1.Location = New-Object System.Drawing.Point(18, 20)
    $l1.Size = New-Object System.Drawing.Size(160, 20)
    $f.Controls.Add($l1)

    $vyber = New-Object System.Windows.Forms.ComboBox
    $vyber.Location = New-Object System.Drawing.Point(185, 18)
    $vyber.Size = New-Object System.Drawing.Size(390, 24)
    $vyber.DropDownStyle = 'DropDownList'
    $sezeni | ForEach-Object { $vyber.Items.Add($_) | Out-Null }
    $vyber.SelectedIndex = 0
    $f.Controls.Add($vyber)

    $l2 = New-Object System.Windows.Forms.Label
    $l2.Text = 'Název cíle v trezoru'
    $l2.Location = New-Object System.Drawing.Point(18, 70)
    $l2.Size = New-Object System.Drawing.Size(160, 20)
    $f.Controls.Add($l2)

    $cilBox = New-Object System.Windows.Forms.TextBox
    $cilBox.Location = New-Object System.Drawing.Point(185, 68)
    $cilBox.Size = New-Object System.Drawing.Size(390, 24)
    $f.Controls.Add($cilBox)

    $l3 = New-Object System.Windows.Forms.Label
    $l3.Text = 'Složka na serveru (nepovinné)'
    $l3.Location = New-Object System.Drawing.Point(18, 120)
    $l3.Size = New-Object System.Drawing.Size(180, 20)
    $f.Controls.Add($l3)

    $slozkaBox = New-Object System.Windows.Forms.TextBox
    $slozkaBox.Location = New-Object System.Drawing.Point(185, 118)
    $slozkaBox.Size = New-Object System.Drawing.Size(390, 24)
    $f.Controls.Add($slozkaBox)

    $ok = New-Object System.Windows.Forms.Button
    $ok.Text = 'Převzít'
    $ok.Location = New-Object System.Drawing.Point(345, 190)
    $ok.Size = New-Object System.Drawing.Size(110, 30)
    $ok.DialogResult = [System.Windows.Forms.DialogResult]::OK
    $f.Controls.Add($ok)
    $f.AcceptButton = $ok

    $zrus = New-Object System.Windows.Forms.Button
    $zrus.Text = 'Zpět'
    $zrus.Location = New-Object System.Drawing.Point(465, 190)
    $zrus.Size = New-Object System.Drawing.Size(110, 30)
    $zrus.DialogResult = [System.Windows.Forms.DialogResult]::Cancel
    $f.Controls.Add($zrus)
    $f.CancelButton = $zrus

    if ($f.ShowDialog() -ne [System.Windows.Forms.DialogResult]::OK) { return }

    $cil = $cilBox.Text.Trim()
    if ($cil -notmatch '^[a-zA-Z0-9._-]+$') {
        Hlaska 'Název cíle smí mít jen písmena, číslice, tečku, pomlčku a podtržítko.' 'Trezor'
        return
    }

    $argumenty = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $NASTROJ, 'zwinscp', $vyber.SelectedItem, $cil)
    if ($slozkaBox.Text.Trim()) { $argumenty += @('-Slozka', $slozkaBox.Text.Trim()) }

    $vystup = & powershell @argumenty 2>&1 | Out-String
    Hlaska $vystup.Trim() 'Trezor'
}

function OknoHlavni {
    $f = New-Object System.Windows.Forms.Form
    $f.Text = 'Trezor přihlašovacích údajů'
    $f.Size = New-Object System.Drawing.Size(700, 420)
    $f.StartPosition = 'CenterScreen'

    $seznam = New-Object System.Windows.Forms.ListBox
    $seznam.Location = New-Object System.Drawing.Point(18, 18)
    $seznam.Size = New-Object System.Drawing.Size(650, 260)
    $seznam.Font = New-Object System.Drawing.Font('Consolas', 10)
    $f.Controls.Add($seznam)

    $obnov = {
        $seznam.Items.Clear()
        $cile = NactiCile
        if (-not $cile) {
            $seznam.Items.Add('Trezor je zatím prázdný. Přidej cíl tlačítkem níž.') | Out-Null
        } else {
            $cile | ForEach-Object { $seznam.Items.Add($_.Popis) | Out-Null }
        }
    }
    & $obnov

    $tlacitka = @(
        @{ Text = 'Přidat ručně'; X = 18; Akce = { OknoPridat; & $obnov } },
        @{ Text = 'Převzít z WinSCP'; X = 180; Akce = { OknoZWinscp; & $obnov } },
        @{ Text = 'Smazat vybraný'; X = 342; Akce = {
                if ($seznam.SelectedItem) {
                    $cil = ($seznam.SelectedItem -split '\s+')[0]
                    $cesta = Join-Path $TREZOR ($cil + '.xml')
                    if (Test-Path $cesta) {
                        Remove-Item $cesta -Force
                        Hlaska "Smazáno: $cil" 'Trezor'
                    }
                }
                & $obnov
            } },
        @{ Text = 'Zavřít'; X = 504; Akce = { $f.Close() } }
    )

    foreach ($t in $tlacitka) {
        $b = New-Object System.Windows.Forms.Button
        $b.Text = $t.Text
        $b.Location = New-Object System.Drawing.Point($t.X, 300)
        $b.Size = New-Object System.Drawing.Size(150, 34)
        $b.Add_Click($t.Akce)
        $f.Controls.Add($b)
    }

    $pozn = New-Object System.Windows.Forms.Label
    $pozn.Text = 'Hesla leží zašifrovaná v profilu uživatele. Agent zná jen název cíle, hodnotu nevidí.'
    $pozn.Location = New-Object System.Drawing.Point(18, 345)
    $pozn.Size = New-Object System.Drawing.Size(650, 20)
    $f.Controls.Add($pozn)

    $f.ShowDialog() | Out-Null
}

OknoHlavni
