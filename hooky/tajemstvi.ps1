# Trezor přihlašovacích údajů pro práci s agenty.
#
#   tajemstvi.ps1 ulozit <cíl>        uloží údaje (ptá se v terminálu)
#                                     -Druh ftp|databaze|token|jine
#                                     volitelně -Otisk <otisk certifikátu>
#   tajemstvi.ps1 okno <cíl>          otevře okno a údaje se vyplní myší
#   tajemstvi.ps1 zwinscp <sezení> <cíl>  převezme uložené sezení z WinSCP
#   tajemstvi.ps1 seznam              vypíše cíle, uživatele a servery
#   tajemstvi.ps1 smazat <cíl>        zahodí uložené údaje
#   tajemstvi.ps1 spustit <cíl> :: <příkaz>   spustí příkaz s údaji v prostředí
#   tajemstvi.ps1 ftp <cíl> :: <řádky winscp> spustí příkazy WinSCP nad cílem
#
# Hodnoty leží v %USERPROFILE%\.tajemstvi\<cíl>.xml, zašifrované DPAPI, tedy
# čitelné jen pod tímhle uživatelským účtem na tomhle počítači. Soubor se dá
# zkopírovat jinam, ale rozšifrovat ne.
#
# Heslo se nikdy nevypisuje: ani do výstupu, ani do příkazové řádky. Agent ho
# nevidí, pracuje jen s názvem cíle.

[CmdletBinding()]
param(
    [Parameter(Position = 0)][string]$Prikaz = 'seznam',
    [Parameter(Position = 1)][string]$Cil,
    # Údaje mimo heslo jdou předat parametrem, ať skript nevyžaduje terminál.
    # Heslo parametrem NE: skončilo by v příkazové řádce a v historii.
    [string]$Server,
    [string]$Uzivatel,
    [string]$Protokol,
    [string]$Slozka,
    # Otisk certifikátu serveru. S ním se spojení ověřuje; bez něj WinSCP
    # u neznámého certifikátu skončí a řekne, jaký otisk má server.
    [string]$Otisk,
    # Druh rozhoduje, co se ukládá a jaké proměnné dostane spuštěný příkaz.
    [ValidateSet('ftp', 'databaze', 'token', 'jine', '')]
    [string]$Druh = '',
    [string]$Port,
    [string]$Databaze,
    [string]$Poznamka,
    [Parameter(ValueFromRemainingArguments = $true)][string[]]$Zbytek
)

$ErrorActionPreference = 'Stop'
$TREZOR = Join-Path $env:USERPROFILE '.tajemstvi'

function Cesta($cil) {
    if (-not $cil) { throw 'Chybí název cíle, třeba: tajemstvi.ps1 ulozit onlinefakturuj-ftp' }
    if ($cil -notmatch '^[a-zA-Z0-9._-]+$') { throw 'Název cíle smí mít jen písmena, číslice, tečku, pomlčku a podtržítko.' }
    Join-Path $TREZOR ($cil + '.xml')
}

function NactiZaznam($cil) {
    $c = Cesta $cil
    if (-not (Test-Path $c)) { throw "Cíl '$cil' v trezoru není. Ulož ho: tajemstvi.ps1 ulozit $cil" }
    Import-Clixml $c
}

function HesloJakoText($zaznam) {
    # Rozšifruje se až tady a nikam se nevypisuje.
    [Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($zaznam.Heslo)
    )
}

function RozsifrujWinScp([string]$ulozene, [string]$uzivatel, [string]$server) {
    # WinSCP heslo neukládá šifrovaně, jen zaobalené. Tohle je jeho vlastní
    # postup pozpátku: dvojice hexů, negace, XOR magickou konstantou.
    $MAGIC = 0xA3
    $PRIZNAK = 0xFF
    $znaky = [System.Collections.ArrayList]@($ulozene.ToCharArray())

    $dalsi = {
        if ($znaky.Count -lt 2) { throw 'Uložené heslo má nečekaný tvar.' }
        $a = [Convert]::ToInt32([string]$znaky[0], 16)
        $b = [Convert]::ToInt32([string]$znaky[1], 16)
        $znaky.RemoveRange(0, 2)
        (((-bnot (($a -shl 4) + $b)) -bxor $MAGIC) -band 0xFF)
    }

    $priznak = & $dalsi
    if ($priznak -eq $PRIZNAK) {
        & $dalsi | Out-Null
        $delka = & $dalsi
    } else {
        $delka = $priznak
    }
    $preskoc = & $dalsi
    if ($preskoc -gt 0) { $znaky.RemoveRange(0, $preskoc * 2) }

    $text = ''
    for ($i = 0; $i -lt $delka; $i++) { $text += [char](& $dalsi) }

    if ($priznak -eq $PRIZNAK) {
        $kotva = $uzivatel + $server
        if (-not $text.StartsWith($kotva)) { throw 'Heslo se nepodařilo přečíst, sezení má jiný tvar.' }
        $text = $text.Substring($kotva.Length)
    }

    return $text
}

function PrikazyPoOddelovaci($pole) {
    $i = [Array]::IndexOf($pole, '::')
    if ($i -lt 0) { return @($pole) }
    return @($pole[($i + 1)..($pole.Count - 1)])
}

switch ($Prikaz) {

    'ulozit' {
        $c = Cesta $Cil
        New-Item -ItemType Directory -Path $TREZOR -Force | Out-Null

        $druh = if ($Druh) { $Druh } else { 'ftp' }
        $server = if ($Server) { $Server } elseif ($druh -eq 'token') { '' } else { Read-Host 'Server' }
        $uzivatel = if ($Uzivatel) { $Uzivatel } elseif ($druh -eq 'token') { '' } else { Read-Host 'Uživatel' }
        if ([Console]::IsInputRedirected) {
            $heslo = ConvertTo-SecureString ([Console]::In.ReadLine()) -AsPlainText -Force
        } else {
            $vyzva = if ($druh -eq 'token') { 'Token nebo klíč' } else { 'Heslo' }
            $heslo = Read-Host $vyzva -AsSecureString
        }

        @{
            Cil = $Cil
            Druh = $druh
            Server = $server
            Uzivatel = $uzivatel
            Heslo = $heslo
            Protokol = if ($Protokol) { $Protokol } else { 'ftpes' }
            Slozka = if ($Slozka) { $Slozka } else { '/' }
            Port = $Port
            Databaze = $Databaze
            Poznamka = $Poznamka
            Otisk = if ($Otisk) { $Otisk } else { '' }
            UlozenoAt = (Get-Date).ToString('s')
        } | Export-Clixml -Path $c

        # Soubor patří jen vlastníkovi; dědičná práva pryč.
        icacls $c /inheritance:r /grant:r "${env:USERNAME}:(R,W)" | Out-Null

        $delka = (HesloJakoText (Import-Clixml $c)).Length
        "Uloženo: $Cil (druh $druh, hodnota o $delka znacích)"
    }

    'seznam' {
        if (-not (Test-Path $TREZOR)) { 'Trezor je prázdný.'; break }
        Get-ChildItem $TREZOR -Filter *.xml | ForEach-Object {
            $z = Import-Clixml $_.FullName
            $druh = if ($z.Druh) { $z.Druh } else { 'ftp' }
            switch ($druh) {
                'databaze' { '{0,-28} [databáze] {1}@{2} ({3})' -f $z.Cil, $z.Uzivatel, $z.Server, $z.Databaze }
                'token'    { '{0,-28} [token] {1}' -f $z.Cil, $z.Poznamka }
                'jine'     { '{0,-28} [jiné] {1} {2}' -f $z.Cil, $z.Uzivatel, $z.Poznamka }
                default    { '{0,-28} [ftp] {1}@{2} ({3}, {4})' -f $z.Cil, $z.Uzivatel, $z.Server, $z.Protokol, $z.Slozka }
            }
        }
    }

    'smazat' {
        Remove-Item (Cesta $Cil) -Force
        "Smazáno: $Cil"
    }

    'spustit' {
        $z = NactiZaznam $Cil
        $prikazy = PrikazyPoOddelovaci $Zbytek
        if (-not $prikazy) { throw 'Chybí příkaz za oddělovačem ::' }

        $druh = if ($z.Druh) { $z.Druh } else { 'ftp' }
        $tajne = HesloJakoText $z

        # Společné pro všechny druhy, ať se s tím dá pracovat jednotně.
        $env:TAJ_DRUH = $druh
        $env:TAJ_SERVER = $z.Server
        $env:TAJ_UZIVATEL = $z.Uzivatel
        $env:TAJ_HESLO = $tajne
        $env:TAJ_HODNOTA = $tajne

        switch ($druh) {
            'databaze' {
                $env:TAJ_DB_SERVER = $z.Server
                $env:TAJ_DB_PORT = if ($z.Port) { $z.Port } else { '3306' }
                $env:TAJ_DB_NAZEV = $z.Databaze
                $env:TAJ_DB_UZIVATEL = $z.Uzivatel
                $env:TAJ_DB_HESLO = $tajne
                # Klient mysql si heslo vezme odsud, takže nemusí do příkazu.
                $env:MYSQL_PWD = $tajne
            }
            'token' { $env:TAJ_TOKEN = $tajne }
            default { $env:TAJ_SLOZKA = $z.Slozka }
        }

        try {
            & $prikazy[0] @($prikazy[1..($prikazy.Count - 1)])
            exit $LASTEXITCODE
        } finally {
            foreach ($p in 'TAJ_HESLO', 'TAJ_HODNOTA', 'TAJ_TOKEN', 'TAJ_DB_HESLO', 'MYSQL_PWD') {
                Remove-Item "Env:$p" -ErrorAction SilentlyContinue
            }
        }
    }

    'ftp' {
        $z = NactiZaznam $Cil
        $druhCile = if ($z.Druh) { $z.Druh } else { 'ftp' }
        if ($druhCile -ne 'ftp') { throw "Cíl '$Cil' je druhu $druhCile, ne ftp. Použij: spustit $Cil :: <příkaz>" }
        $winscp = 'C:\Program Files (x86)\WinSCP\WinSCP.com'
        if (-not (Test-Path $winscp)) { throw "WinSCP není v $winscp" }

        $radky = PrikazyPoOddelovaci $Zbytek
        if (-not $radky) { throw 'Chybí příkazy WinSCP za oddělovačem ::, třeba: :: "ls /web"' }

        # Skript se skládá do dočasného souboru: heslo se tím nedostane do
        # příkazové řádky, kterou vidí každý proces v systému.
        $heslo = HesloJakoText $z
        $adresa = '{0}://{1}:{2}@{3}/' -f $z.Protokol, [uri]::EscapeDataString($z.Uzivatel), [uri]::EscapeDataString($heslo), $z.Server
        $skript = Join-Path $env:TEMP ('tajemstvi-' + [guid]::NewGuid().ToString() + '.txt')

        $otevri = "open $adresa"
        if ($z.Otisk) {
            $otevri += ' -certificate="' + $z.Otisk + '"'
        }
        $obsah = @("option batch abort", "option confirm off", $otevri) + $radky + @("exit")
        Set-Content -Path $skript -Value $obsah -Encoding UTF8
        icacls $skript /inheritance:r /grant:r "${env:USERNAME}:(R,W)" | Out-Null

        try {
            & $winscp /script=$skript
            $kod = $LASTEXITCODE
        } finally {
            Remove-Item $skript -Force -ErrorAction SilentlyContinue
        }
        exit $kod
    }

    'okno' {
        # Okno se hodí tam, kde není terminál: agent ho otevře, hodnotu vyplní
        # člověk a agent ji nikdy nevidí.
        if (-not $Cil) { throw 'Chybí název cíle: tajemstvi.ps1 okno onlinefakturuj-ftp' }
        Add-Type -AssemblyName System.Windows.Forms
        Add-Type -AssemblyName System.Drawing

        $f = New-Object System.Windows.Forms.Form
        $f.Text = "Trezor: $Cil"
        $f.Size = New-Object System.Drawing.Size(460, 320)
        $f.StartPosition = 'CenterScreen'
        $f.TopMost = $true

        $pole = @{}
        $popisky = @('Server', 'Uživatel', 'Heslo', 'Protokol (ftpes/ftp/sftp)', 'Složka na serveru')
        $klice = @('Server', 'Uzivatel', 'Heslo', 'Protokol', 'Slozka')
        for ($i = 0; $i -lt $popisky.Count; $i++) {
            $l = New-Object System.Windows.Forms.Label
            $l.Text = $popisky[$i]
            $l.Location = New-Object System.Drawing.Point(15, (20 + $i * 45))
            $l.Size = New-Object System.Drawing.Size(180, 20)
            $f.Controls.Add($l)

            $t = New-Object System.Windows.Forms.TextBox
            $t.Location = New-Object System.Drawing.Point(200, (18 + $i * 45))
            $t.Size = New-Object System.Drawing.Size(220, 24)
            if ($klice[$i] -eq 'Heslo') { $t.UseSystemPasswordChar = $true }
            if ($klice[$i] -eq 'Protokol') { $t.Text = 'ftpes' }
            if ($klice[$i] -eq 'Slozka') { $t.Text = '/' }
            $f.Controls.Add($t)
            $pole[$klice[$i]] = $t
        }

        $ok = New-Object System.Windows.Forms.Button
        $ok.Text = 'Uložit'
        $ok.Location = New-Object System.Drawing.Point(200, 245)
        $ok.DialogResult = [System.Windows.Forms.DialogResult]::OK
        $f.Controls.Add($ok)
        $f.AcceptButton = $ok

        if ($f.ShowDialog() -ne [System.Windows.Forms.DialogResult]::OK) {
            'Zrušeno, nic se neuložilo.'
            break
        }

        New-Item -ItemType Directory -Path $TREZOR -Force | Out-Null
        $c = Cesta $Cil
        @{
            Cil = $Cil
            Server = $pole['Server'].Text
            Uzivatel = $pole['Uzivatel'].Text
            Heslo = (ConvertTo-SecureString $pole['Heslo'].Text -AsPlainText -Force)
            Protokol = $pole['Protokol'].Text
            Slozka = $pole['Slozka'].Text
            Otisk = ''
            UlozenoAt = (Get-Date).ToString('s')
        } | Export-Clixml -Path $c
        icacls $c /inheritance:r /grant:r "${env:USERNAME}:(R,W)" | Out-Null
        "Uloženo: $Cil ($($pole['Uzivatel'].Text)@$($pole['Server'].Text), heslo o $($pole['Heslo'].Text.Length) znacích)"
    }

    'zwinscp' {
        # Převzetí sezení, které už v počítači je. Heslo se dešifruje v paměti
        # a rovnou uloží do trezoru; nikam se nevypisuje.
        $sezeni = $Cil
        $novyCil = if ($Zbytek -and $Zbytek.Count -ge 1) { $Zbytek[0] } else { $null }
        if (-not $sezeni -or -not $novyCil) {
            throw 'Použití: tajemstvi.ps1 zwinscp "<sezení WinSCP>" <cíl v trezoru>'
        }

        $koren = 'HKCU:\Software\Martin Prikryl\WinSCP 2\Sessions'
        $klic = Join-Path $koren ($sezeni -replace '/', '%2F')
        if (-not (Test-Path $klic)) { throw "Sezení '$sezeni' ve WinSCP není. Názvy vypíše: tajemstvi.ps1 sezeni" }
        $s = Get-ItemProperty $klic
        if (-not $s.Password) { throw "Sezení '$sezeni' nemá uložené heslo." }

        $heslo = RozsifrujWinScp $s.Password $s.UserName $s.HostName
        $protokoly = @{ 0 = 'sftp'; 1 = 'scp'; 5 = 'ftpes' }
        $protokol = if ($protokoly.ContainsKey([int]$s.FSProtocol)) { $protokoly[[int]$s.FSProtocol] } else { 'ftpes' }

        New-Item -ItemType Directory -Path $TREZOR -Force | Out-Null
        $c = Cesta $novyCil
        @{
            Cil = $novyCil
            Druh = 'ftp'
            Server = $s.HostName
            Uzivatel = $s.UserName
            Heslo = (ConvertTo-SecureString $heslo -AsPlainText -Force)
            Protokol = $protokol
            # -Slozka přebije to, co si pamatuje WinSCP: sezení míří jinam,
            # než kam nasazuje projekt (onlinefakturuj: /public_html, ne /web).
            Slozka = if ($Slozka) { $Slozka } elseif ($s.RemoteDirectory) { $s.RemoteDirectory } else { '/' }
            Otisk = ''
            UlozenoAt = (Get-Date).ToString('s')
        } | Export-Clixml -Path $c
        icacls $c /inheritance:r /grant:r "${env:USERNAME}:(R,W)" | Out-Null
        "Převzato z WinSCP: $novyCil ($($s.UserName)@$($s.HostName), protokol $protokol, heslo o $($heslo.Length) znacích)"
    }

    'sezeni' {
        Get-ChildItem 'HKCU:\Software\Martin Prikryl\WinSCP 2\Sessions' -ErrorAction SilentlyContinue |
            Select-Object -ExpandProperty PSChildName |
            ForEach-Object { $_ -replace '%2F', '/' }
    }

    default {
        throw "Neznámý příkaz '$Prikaz'. Umím: ulozit, okno, zwinscp, sezeni, seznam, smazat, spustit, ftp."
    }
}
