# Trezor přihlašovacích údajů pro práci s agenty.
#
#   tajemstvi.ps1 ulozit <cíl>        uloží údaje (ptá se, nic nevypisuje)
#                                     volitelně -Otisk <otisk certifikátu>
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

function PrikazyPoOddelovaci($pole) {
    $i = [Array]::IndexOf($pole, '::')
    if ($i -lt 0) { return @($pole) }
    return @($pole[($i + 1)..($pole.Count - 1)])
}

switch ($Prikaz) {

    'ulozit' {
        $c = Cesta $Cil
        New-Item -ItemType Directory -Path $TREZOR -Force | Out-Null

        $server = if ($Server) { $Server } else { Read-Host 'Server (např. ftp.projekt.cz)' }
        $uzivatel = if ($Uzivatel) { $Uzivatel } else { Read-Host 'Uživatel' }
        if ([Console]::IsInputRedirected) {
            $heslo = ConvertTo-SecureString ([Console]::In.ReadLine()) -AsPlainText -Force
        } else {
            $heslo = Read-Host 'Heslo' -AsSecureString
        }
        $protokol = if ($Protokol) { $Protokol } else { 'ftpes' }
        $slozka = if ($Slozka) { $Slozka } else { '/' }
        $otisk = if ($Otisk) { $Otisk } else { '' }

        @{
            Cil = $Cil
            Server = $server
            Uzivatel = $uzivatel
            Heslo = $heslo
            Protokol = $protokol
            Slozka = $slozka
            Otisk = $otisk
            UlozenoAt = (Get-Date).ToString('s')
        } | Export-Clixml -Path $c

        # Soubor patří jen vlastníkovi; dědičná práva pryč.
        icacls $c /inheritance:r /grant:r "${env:USERNAME}:(R,W)" | Out-Null

        $delka = (HesloJakoText (Import-Clixml $c)).Length
        "Uloženo: $Cil ($uzivatel@$server, protokol $protokol, heslo o $delka znacích)"
    }

    'seznam' {
        if (-not (Test-Path $TREZOR)) { 'Trezor je prázdný.'; break }
        Get-ChildItem $TREZOR -Filter *.xml | ForEach-Object {
            $z = Import-Clixml $_.FullName
            '{0,-28} {1}@{2} ({3}, {4})' -f $z.Cil, $z.Uzivatel, $z.Server, $z.Protokol, $z.Slozka
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

        $env:TAJ_SERVER = $z.Server
        $env:TAJ_UZIVATEL = $z.Uzivatel
        $env:TAJ_HESLO = HesloJakoText $z
        $env:TAJ_SLOZKA = $z.Slozka
        try {
            & $prikazy[0] @($prikazy[1..($prikazy.Count - 1)])
            exit $LASTEXITCODE
        } finally {
            Remove-Item Env:TAJ_HESLO -ErrorAction SilentlyContinue
        }
    }

    'ftp' {
        $z = NactiZaznam $Cil
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

    default {
        throw "Neznámý příkaz '$Prikaz'. Umím: ulozit, seznam, smazat, spustit, ftp."
    }
}
