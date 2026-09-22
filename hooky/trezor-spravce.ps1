# Okenní správce trezoru: přidat, převzít z WinSCP, smazat.
#
# Pouští se zástupcem z plochy, žádná příkazová řádka. Hodnoty se ukládají
# stejně jako v tajemstvi.ps1, tedy přes DPAPI do %USERPROFILE%\.tajemstvi.
#
# Rozhraní je WPF, ne WinForms: kvůli vzhledu (tmavé pozadí, Segoe UI,
# odsazení) a kvůli tomu, že se dá stylovat bez kreslení po pixelech.
#
# Soubor musí zůstat v UTF-8 s BOM, jinak Windows PowerShell 5.1 přečte
# češtinu rozsypaně.

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName PresentationFramework
Add-Type -AssemblyName PresentationCore
Add-Type -AssemblyName WindowsBase

$TREZOR = Join-Path $env:USERPROFILE '.tajemstvi'
$SEZENI_WINSCP = 'HKCU:\Software\Martin Prikryl\WinSCP 2\Sessions'
$NASTROJ = Join-Path $PSScriptRoot 'tajemstvi.ps1'

$xaml = @'
<Window xmlns="http://schemas.microsoft.com/winfx/2006/xaml/presentation"
        xmlns:x="http://schemas.microsoft.com/winfx/2006/xaml"
        Title="Trezor hesel" Height="840" Width="960"
        WindowStartupLocation="CenterScreen" Background="#0F1115"
        FontFamily="Segoe UI" TextOptions.TextFormattingMode="Ideal">
  <Window.Resources>
    <SolidColorBrush x:Key="Plocha" Color="#171A20"/>
    <SolidColorBrush x:Key="Text" Color="#E8EAED"/>
    <SolidColorBrush x:Key="TextSlaby" Color="#8A93A0"/>
    <SolidColorBrush x:Key="Akcent" Color="#00E07A"/>
    <SolidColorBrush x:Key="Obrys" Color="#242833"/>

    <Style TargetType="TextBlock">
      <Setter Property="Foreground" Value="{StaticResource Text}"/>
    </Style>

    <Style x:Key="Nadpis" TargetType="TextBlock">
      <Setter Property="Foreground" Value="{StaticResource Text}"/>
      <Setter Property="FontSize" Value="22"/>
      <Setter Property="FontWeight" Value="SemiBold"/>
    </Style>

    <Style x:Key="Podnadpis" TargetType="TextBlock">
      <Setter Property="Foreground" Value="{StaticResource TextSlaby}"/>
      <Setter Property="FontSize" Value="12.5"/>
      <Setter Property="Margin" Value="0,4,0,0"/>
    </Style>

    <Style x:Key="Popisek" TargetType="TextBlock">
      <Setter Property="Foreground" Value="{StaticResource TextSlaby}"/>
      <Setter Property="FontSize" Value="11.5"/>
      <Setter Property="Margin" Value="2,0,0,3"/>
    </Style>

    <Style TargetType="TextBox">
      <Setter Property="Background" Value="#11141A"/>
      <Setter Property="Foreground" Value="{StaticResource Text}"/>
      <Setter Property="CaretBrush" Value="{StaticResource Akcent}"/>
      <Setter Property="BorderBrush" Value="{StaticResource Obrys}"/>
      <Setter Property="BorderThickness" Value="1"/>
      <Setter Property="Padding" Value="9,7"/>
      <Setter Property="FontSize" Value="13"/>
      <Setter Property="Margin" Value="0,0,0,8"/>
      <Setter Property="Template">
        <Setter.Value>
          <ControlTemplate TargetType="TextBox">
            <Border Background="{TemplateBinding Background}" BorderBrush="{TemplateBinding BorderBrush}"
                    BorderThickness="{TemplateBinding BorderThickness}" CornerRadius="8">
              <ScrollViewer x:Name="PART_ContentHost" Margin="{TemplateBinding Padding}"/>
            </Border>
          </ControlTemplate>
        </Setter.Value>
      </Setter>
    </Style>

    <Style TargetType="PasswordBox">
      <Setter Property="Background" Value="#11141A"/>
      <Setter Property="Foreground" Value="{StaticResource Text}"/>
      <Setter Property="CaretBrush" Value="{StaticResource Akcent}"/>
      <Setter Property="BorderBrush" Value="{StaticResource Obrys}"/>
      <Setter Property="BorderThickness" Value="1"/>
      <Setter Property="Padding" Value="9,7"/>
      <Setter Property="FontSize" Value="13"/>
      <Setter Property="Margin" Value="0,0,0,8"/>
      <Setter Property="Template">
        <Setter.Value>
          <ControlTemplate TargetType="PasswordBox">
            <Border Background="{TemplateBinding Background}" BorderBrush="{TemplateBinding BorderBrush}"
                    BorderThickness="{TemplateBinding BorderThickness}" CornerRadius="8">
              <ScrollViewer x:Name="PART_ContentHost" Margin="{TemplateBinding Padding}"/>
            </Border>
          </ControlTemplate>
        </Setter.Value>
      </Setter>
    </Style>

    <Style x:Key="TlacitkoZaklad" TargetType="Button">
      <Setter Property="Foreground" Value="{StaticResource Text}"/>
      <Setter Property="Background" Value="#1C2029"/>
      <Setter Property="BorderBrush" Value="{StaticResource Obrys}"/>
      <Setter Property="BorderThickness" Value="1"/>
      <Setter Property="Padding" Value="16,9"/>
      <Setter Property="FontSize" Value="13"/>
      <Setter Property="Cursor" Value="Hand"/>
      <Setter Property="Margin" Value="0,0,10,0"/>
      <Setter Property="Template">
        <Setter.Value>
          <ControlTemplate TargetType="Button">
            <Border x:Name="ramecek" Background="{TemplateBinding Background}"
                    BorderBrush="{TemplateBinding BorderBrush}" BorderThickness="{TemplateBinding BorderThickness}"
                    CornerRadius="8" Padding="{TemplateBinding Padding}">
              <ContentPresenter HorizontalAlignment="Center" VerticalAlignment="Center"/>
            </Border>
            <ControlTemplate.Triggers>
              <Trigger Property="IsMouseOver" Value="True">
                <Setter TargetName="ramecek" Property="Background" Value="#242A36"/>
              </Trigger>
            </ControlTemplate.Triggers>
          </ControlTemplate>
        </Setter.Value>
      </Setter>
    </Style>

    <Style x:Key="TlacitkoHlavni" TargetType="Button" BasedOn="{StaticResource TlacitkoZaklad}">
      <Setter Property="Background" Value="{StaticResource Akcent}"/>
      <Setter Property="Foreground" Value="#06231A"/>
      <Setter Property="FontWeight" Value="SemiBold"/>
      <Setter Property="BorderBrush" Value="{StaticResource Akcent}"/>
    </Style>

    <Style TargetType="ComboBox">
      <Setter Property="Background" Value="#11141A"/>
      <Setter Property="Foreground" Value="#1A1D24"/>
      <Setter Property="BorderBrush" Value="{StaticResource Obrys}"/>
      <Setter Property="Padding" Value="8,6"/>
      <Setter Property="FontSize" Value="13"/>
      <Setter Property="Margin" Value="0,0,0,8"/>
      <Setter Property="HorizontalContentAlignment" Value="Left"/>
    </Style>
  </Window.Resources>

  <Grid Margin="26">
    <Grid.RowDefinitions>
      <RowDefinition Height="Auto"/>
      <RowDefinition Height="*"/>
      <RowDefinition Height="Auto"/>
    </Grid.RowDefinitions>
    <Grid.ColumnDefinitions>
      <ColumnDefinition Width="*"/>
      <ColumnDefinition Width="350"/>
    </Grid.ColumnDefinitions>

    <StackPanel Grid.Row="0" Grid.ColumnSpan="2" Margin="0,0,0,18">
      <TextBlock Text="Trezor hesel" Style="{StaticResource Nadpis}"/>
      <TextBlock Style="{StaticResource Podnadpis}"
                 Text="FTP, databáze, tokeny. Zašifrované na tenhle účet a počítač; agent zná jen název cíle."/>
    </StackPanel>

    <Border Grid.Row="1" Grid.Column="0" Background="{StaticResource Plocha}" CornerRadius="12"
            BorderBrush="{StaticResource Obrys}" BorderThickness="1" Padding="6" Margin="0,0,18,0">
      <ListBox x:Name="Seznam" Background="Transparent" BorderThickness="0" Foreground="#E8EAED">
        <ListBox.ItemContainerStyle>
          <Style TargetType="ListBoxItem">
            <Setter Property="Padding" Value="14,12"/>
            <Setter Property="Margin" Value="4,4,4,0"/>
            <Setter Property="Template">
              <Setter.Value>
                <ControlTemplate TargetType="ListBoxItem">
                  <Border x:Name="radek" Background="#11141A" CornerRadius="10" Padding="{TemplateBinding Padding}"
                          BorderBrush="{StaticResource Obrys}" BorderThickness="1">
                    <ContentPresenter/>
                  </Border>
                  <ControlTemplate.Triggers>
                    <Trigger Property="IsSelected" Value="True">
                      <Setter TargetName="radek" Property="BorderBrush" Value="{StaticResource Akcent}"/>
                      <Setter TargetName="radek" Property="Background" Value="#101A16"/>
                    </Trigger>
                    <Trigger Property="IsMouseOver" Value="True">
                      <Setter TargetName="radek" Property="Background" Value="#151922"/>
                    </Trigger>
                  </ControlTemplate.Triggers>
                </ControlTemplate>
              </Setter.Value>
            </Setter>
          </Style>
        </ListBox.ItemContainerStyle>
        <ListBox.ItemTemplate>
          <DataTemplate>
            <StackPanel>
              <StackPanel Orientation="Horizontal">
                <Border Background="#1B2430" CornerRadius="6" Padding="7,2" Margin="0,0,8,0">
                  <TextBlock Text="{Binding Druh}" FontSize="10.5" Foreground="#7FD7AE"/>
                </Border>
                <TextBlock Text="{Binding Cil}" FontSize="14.5" FontWeight="SemiBold" Foreground="#E8EAED"/>
              </StackPanel>
              <TextBlock Text="{Binding Radek}" FontSize="12" Foreground="#8A93A0" Margin="0,4,0,0"/>
            </StackPanel>
          </DataTemplate>
        </ListBox.ItemTemplate>
      </ListBox>
    </Border>

    <Border Grid.Row="1" Grid.Column="1" Background="{StaticResource Plocha}" CornerRadius="12"
            BorderBrush="{StaticResource Obrys}" BorderThickness="1" Padding="16">
      <ScrollViewer VerticalScrollBarVisibility="Auto">
        <StackPanel>
          <TextBlock Text="Nový cíl" FontSize="15" FontWeight="SemiBold" Margin="0,0,0,10"/>

          <TextBlock Text="Druh" Style="{StaticResource Popisek}"/>
          <ComboBox x:Name="DruhBox"/>

          <TextBlock Text="Název cíle" Style="{StaticResource Popisek}"/>
          <TextBox x:Name="Cil"/>

          <StackPanel x:Name="PanelSezeni">
            <TextBlock Text="Převzít ze sezení WinSCP" Style="{StaticResource Popisek}"/>
            <ComboBox x:Name="Sezeni"/>
          </StackPanel>

          <StackPanel x:Name="PanelServer">
            <TextBlock Text="Server" Style="{StaticResource Popisek}"/>
            <TextBox x:Name="Server"/>
          </StackPanel>

          <StackPanel x:Name="PanelUzivatel">
            <TextBlock Text="Uživatel" Style="{StaticResource Popisek}"/>
            <TextBox x:Name="Uzivatel"/>
          </StackPanel>

          <TextBlock x:Name="PopisekHeslo" Text="Heslo" Style="{StaticResource Popisek}"/>
          <PasswordBox x:Name="Heslo"/>

          <StackPanel x:Name="PanelFtp">
            <Grid>
              <Grid.ColumnDefinitions>
                <ColumnDefinition Width="*"/>
                <ColumnDefinition Width="12"/>
                <ColumnDefinition Width="*"/>
              </Grid.ColumnDefinitions>
              <StackPanel Grid.Column="0">
                <TextBlock Text="Protokol" Style="{StaticResource Popisek}"/>
                <TextBox x:Name="Protokol" Text="ftpes"/>
              </StackPanel>
              <StackPanel Grid.Column="2">
                <TextBlock Text="Složka" Style="{StaticResource Popisek}"/>
                <TextBox x:Name="Slozka" Text="/"/>
              </StackPanel>
            </Grid>
          </StackPanel>

          <StackPanel x:Name="PanelDb">
            <Grid>
              <Grid.ColumnDefinitions>
                <ColumnDefinition Width="*"/>
                <ColumnDefinition Width="12"/>
                <ColumnDefinition Width="*"/>
              </Grid.ColumnDefinitions>
              <StackPanel Grid.Column="0">
                <TextBlock Text="Databáze" Style="{StaticResource Popisek}"/>
                <TextBox x:Name="Databaze"/>
              </StackPanel>
              <StackPanel Grid.Column="2">
                <TextBlock Text="Port" Style="{StaticResource Popisek}"/>
                <TextBox x:Name="Port" Text="3306"/>
              </StackPanel>
            </Grid>
          </StackPanel>

          <StackPanel x:Name="PanelPoznamka">
            <TextBlock Text="Poznámka (k čemu to je)" Style="{StaticResource Popisek}"/>
            <TextBox x:Name="Poznamka"/>
          </StackPanel>

          <Button x:Name="Ulozit" Content="Uložit do trezoru" Style="{StaticResource TlacitkoHlavni}"
                  HorizontalAlignment="Stretch" Margin="0,4,0,0"/>
        </StackPanel>
      </ScrollViewer>
    </Border>

    <StackPanel Grid.Row="2" Grid.ColumnSpan="2" Orientation="Horizontal" Margin="0,18,0,0">
      <Button x:Name="Upravit" Content="Upravit vybraný" Style="{StaticResource TlacitkoZaklad}"/>
      <Button x:Name="Ukazat" Content="Zobrazit údaje" Style="{StaticResource TlacitkoZaklad}"/>
      <Button x:Name="Smazat" Content="Smazat vybraný" Style="{StaticResource TlacitkoZaklad}"/>
      <Button x:Name="Zkusit" Content="Vyzkoušet spojení" Style="{StaticResource TlacitkoZaklad}"/>
      <Button x:Name="Zavrit" Content="Zavřít" Style="{StaticResource TlacitkoZaklad}"/>
      <TextBlock x:Name="Stav" VerticalAlignment="Center" Margin="10,0,0,0" Foreground="#8A93A0" FontSize="12.5"/>
    </StackPanel>
  </Grid>
</Window>
'@

$okno = [Windows.Markup.XamlReader]::Parse($xaml)

# Záhlaví okna kreslí Windows, ne WPF, takže by zůstalo bílé. Tohle mu řekne,
# ať ho vykreslí tmavě: atribut 20 na Windows 11 a novějších desítkách,
# atribut 19 na starších buildech.
Add-Type -Namespace Nativni -Name Dwm -MemberDefinition @'
[DllImport("dwmapi.dll")]
public static extern int DwmSetWindowAttribute(IntPtr hwnd, int attr, ref int value, int size);
'@

$okno.Add_SourceInitialized({
    try {
        $hwnd = (New-Object System.Windows.Interop.WindowInteropHelper($okno)).Handle
        $zapnuto = 1
        if ([Nativni.Dwm]::DwmSetWindowAttribute($hwnd, 20, [ref]$zapnuto, 4) -ne 0) {
            [Nativni.Dwm]::DwmSetWindowAttribute($hwnd, 19, [ref]$zapnuto, 4) | Out-Null
        }
    } catch {
        # Na starším systému se nic nestane, okno jen zůstane se světlým záhlavím.
    }
})

$prvek = {
    param($jmeno)
    $okno.FindName($jmeno)
}

$Seznam = & $prvek 'Seznam'
$DruhBox = & $prvek 'DruhBox'
$Sezeni = & $prvek 'Sezeni'
$Cil = & $prvek 'Cil'
$Server = & $prvek 'Server'
$Uzivatel = & $prvek 'Uzivatel'
$Heslo = & $prvek 'Heslo'
$Protokol = & $prvek 'Protokol'
$Slozka = & $prvek 'Slozka'
$Databaze = & $prvek 'Databaze'
$Port = & $prvek 'Port'
$Poznamka = & $prvek 'Poznamka'
$PopisekHeslo = & $prvek 'PopisekHeslo'
$PanelSezeni = & $prvek 'PanelSezeni'
$PanelServer = & $prvek 'PanelServer'
$PanelUzivatel = & $prvek 'PanelUzivatel'
$PanelFtp = & $prvek 'PanelFtp'
$PanelDb = & $prvek 'PanelDb'
$PanelPoznamka = & $prvek 'PanelPoznamka'
$Ulozit = & $prvek 'Ulozit'
$Upravit = & $prvek 'Upravit'
$Ukazat = & $prvek 'Ukazat'
$Smazat = & $prvek 'Smazat'
$Zkusit = & $prvek 'Zkusit'
$Zavrit = & $prvek 'Zavrit'
$Stav = & $prvek 'Stav'

# Když se něco upravuje, drží se tu název cíle; jinak je prázdný.
$script:UpravovanyCil = ''

function NactiZaznamCile([string]$cil) {
    Import-Clixml (Join-Path $TREZOR ($cil + '.xml'))
}

function TajneJakoText($zaznam) {
    [Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($zaznam.Heslo)
    )
}

$DRUHY = [ordered]@{
    'FTP nebo SFTP' = 'ftp'
    'Databáze' = 'databaze'
    'Token nebo klíč' = 'token'
    'Jiné heslo' = 'jine'
}
$DRUHY.Keys | ForEach-Object { $DruhBox.Items.Add($_) | Out-Null }
$DruhBox.SelectedIndex = 0

function VybranyDruh { $DRUHY[[string]$DruhBox.SelectedItem] }

function PodleDruhu {
    $druh = VybranyDruh
    @($PanelSezeni, $PanelServer, $PanelUzivatel, $PanelFtp, $PanelDb, $PanelPoznamka) |
        ForEach-Object { $_.Visibility = 'Collapsed' }

    switch ($druh) {
        'ftp' {
            $PanelSezeni.Visibility = 'Visible'
            $PanelServer.Visibility = 'Visible'
            $PanelUzivatel.Visibility = 'Visible'
            $PanelFtp.Visibility = 'Visible'
            $PopisekHeslo.Text = 'Heslo'
        }
        'databaze' {
            $PanelServer.Visibility = 'Visible'
            $PanelUzivatel.Visibility = 'Visible'
            $PanelDb.Visibility = 'Visible'
            $PopisekHeslo.Text = 'Heslo k databázi'
        }
        'token' {
            $PanelPoznamka.Visibility = 'Visible'
            $PopisekHeslo.Text = 'Token nebo klíč'
        }
        'jine' {
            $PanelServer.Visibility = 'Visible'
            $PanelUzivatel.Visibility = 'Visible'
            $PanelPoznamka.Visibility = 'Visible'
            $PopisekHeslo.Text = 'Heslo'
        }
    }
}

# Tajemství se nástroji podává rourou, ne parametrem: nedostane se tím do
# příkazové řádky, kterou vidí každý proces v systému.
function SpustNastroj([string[]]$argumenty, $tajne, [int]$limitVterin = 0) {
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = 'powershell.exe'
    $castiArgumentu = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', ('"' + $NASTROJ + '"'))
    foreach ($a in $argumenty) {
        $castiArgumentu += $(if ($a -match '\s') { '"' + $a + '"' } else { $a })
    }
    $psi.Arguments = $castiArgumentu -join ' '
    $psi.RedirectStandardInput = $true
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError = $true
    $psi.UseShellExecute = $false
    $psi.CreateNoWindow = $true

    $proces = [System.Diagnostics.Process]::Start($psi)
    if ($null -ne $tajne) { $proces.StandardInput.Write($tajne) }
    $proces.StandardInput.Close()

    # Výstup se čte na pozadí, jinak by se čekání na limit zaseklo na plné rouře.
    $cteniVen = $proces.StandardOutput.ReadToEndAsync()
    $cteniChyb = $proces.StandardError.ReadToEndAsync()

    if ($limitVterin -gt 0 -and -not $proces.WaitForExit($limitVterin * 1000)) {
        try { $proces.Kill() } catch { }
        return "Nestihlo se to do $limitVterin vteřin, zkouška zrušena."
    }
    if ($limitVterin -le 0) { $proces.WaitForExit() }

    return ($cteniVen.Result + $cteniChyb.Result).Trim()
}

function NactiCile {
    if (-not (Test-Path $TREZOR)) { return @() }
    Get-ChildItem $TREZOR -Filter *.xml | ForEach-Object {
        $z = Import-Clixml $_.FullName
        $druh = if ($z.Druh) { $z.Druh } else { 'ftp' }
        $radek = switch ($druh) {
            'databaze' { '{0}@{1}:{2}   ·   {3}' -f $z.Uzivatel, $z.Server, $(if ($z.Port) { $z.Port } else { '3306' }), $z.Databaze }
            'token'    { if ($z.Poznamka) { $z.Poznamka } else { 'token bez poznámky' } }
            'jine'     { '{0}   ·   {1}' -f $z.Uzivatel, $z.Poznamka }
            default    { '{0}@{1}   ·   {2}   ·   {3}' -f $z.Uzivatel, $z.Server, $z.Protokol, $z.Slozka }
        }
        [pscustomobject]@{ Cil = $z.Cil; Druh = $druh; Radek = $radek }
    }
}

function Obnov {
    $Seznam.ItemsSource = @(NactiCile)
    $pocet = @(NactiCile).Count
    # Čeština: 1 cíl je, 2 až 4 cíle jsou, 5 a víc cílů je.
    $slovo = if ($pocet -eq 1) { 'cíl' } elseif ($pocet -ge 2 -and $pocet -le 4) { 'cíle' } else { 'cílů' }
    $sloveso = if ($pocet -ge 2 -and $pocet -le 4) { 'jsou' } else { 'je' }
    $Stav.Text = if ($pocet) { "V trezoru $sloveso $pocet $slovo." } else { 'Trezor je zatím prázdný.' }
}

function NactiSezeni {
    $Sezeni.Items.Clear()
    $Sezeni.Items.Add('(nepřebírat, vyplním ručně)') | Out-Null
    if (Test-Path $SEZENI_WINSCP) {
        Get-ChildItem $SEZENI_WINSCP |
            Select-Object -ExpandProperty PSChildName |
            ForEach-Object { $_ -replace '%2F', '/' } |
            Where-Object { $_ -notmatch '^Default' } |
            Sort-Object |
            ForEach-Object { $Sezeni.Items.Add($_) | Out-Null }
    }
    $Sezeni.SelectedIndex = 0
}

Obnov
NactiSezeni
PodleDruhu

$DruhBox.Add_SelectionChanged({ PodleDruhu })

$Sezeni.Add_SelectionChanged({
    if ($Sezeni.SelectedIndex -le 0) { return }
    $nazev = [string]$Sezeni.SelectedItem
    $klic = Join-Path $SEZENI_WINSCP ($nazev -replace '/', '%2F')
    if (-not (Test-Path $klic)) { return }
    $s = Get-ItemProperty $klic
    $Server.Text = [string]$s.HostName
    $Uzivatel.Text = [string]$s.UserName
    if (-not $Cil.Text) {
        $Cil.Text = (($s.UserName -replace '[^a-zA-Z0-9]', '-') + '-ftp').ToLower()
    }
    $protokoly = @{ 0 = 'sftp'; 1 = 'scp'; 5 = 'ftpes' }
    if ($protokoly.ContainsKey([int]$s.FSProtocol)) { $Protokol.Text = $protokoly[[int]$s.FSProtocol] }
    if ($s.RemoteDirectory) { $Slozka.Text = [string]$s.RemoteDirectory }
    $Stav.Text = 'Heslo se převezme ze sezení, psát ho nemusíš.'
})

$Ulozit.Add_Click({
    $cil = $Cil.Text.Trim()
    if ($cil -notmatch '^[a-zA-Z0-9._-]+$') {
        $Stav.Text = 'Název cíle smí mít jen písmena, číslice, tečku, pomlčku a podtržítko.'
        return
    }
    $druh = VybranyDruh

    if ($script:UpravovanyCil) {
        # Úprava mění jen vyplněná pole; prázdné heslo znamená nechat staré.
        $argumenty = @('upravit', $script:UpravovanyCil, '-Druh', $druh)
        if ($Server.Text.Trim()) { $argumenty += @('-Server', $Server.Text.Trim()) }
        if ($Uzivatel.Text.Trim()) { $argumenty += @('-Uzivatel', $Uzivatel.Text.Trim()) }
        if ($druh -eq 'ftp') {
            if ($Protokol.Text.Trim()) { $argumenty += @('-Protokol', $Protokol.Text.Trim()) }
            if ($Slozka.Text.Trim()) { $argumenty += @('-Slozka', $Slozka.Text.Trim()) }
        }
        if ($druh -eq 'databaze') {
            if ($Databaze.Text.Trim()) { $argumenty += @('-Databaze', $Databaze.Text.Trim()) }
            if ($Port.Text.Trim()) { $argumenty += @('-Port', $Port.Text.Trim()) }
        }
        if ($Poznamka.Text.Trim()) { $argumenty += @('-Poznamka', $Poznamka.Text.Trim()) }

        if ($Heslo.Password) {
            $argumenty += '-ZeVstupu'
            $Stav.Text = SpustNastroj $argumenty ($Heslo.Password + "`n")
        } else {
            $Stav.Text = SpustNastroj $argumenty $null
        }

        $script:UpravovanyCil = ''
        $Ulozit.Content = 'Uložit do trezoru'
        $Heslo.Clear(); $Cil.Text = ''; $Poznamka.Text = ''
        Obnov
        return
    }

    if ($druh -eq 'ftp' -and $Sezeni.SelectedIndex -gt 0 -and -not $Heslo.Password) {
        $argumenty = @('zwinscp', [string]$Sezeni.SelectedItem, $cil)
        if ($Slozka.Text.Trim()) { $argumenty += @('-Slozka', $Slozka.Text.Trim()) }
        $Stav.Text = SpustNastroj $argumenty $null
    } else {
        if (-not $Heslo.Password) { $Stav.Text = 'Vyplň heslo nebo token.'; return }

        $argumenty = @('ulozit', $cil, '-Druh', $druh)
        if ($Server.Text.Trim()) { $argumenty += @('-Server', $Server.Text.Trim()) }
        if ($Uzivatel.Text.Trim()) { $argumenty += @('-Uzivatel', $Uzivatel.Text.Trim()) }
        if ($druh -eq 'ftp') {
            $argumenty += @('-Protokol', $(if ($Protokol.Text.Trim()) { $Protokol.Text.Trim() } else { 'ftpes' }))
            $argumenty += @('-Slozka', $(if ($Slozka.Text.Trim()) { $Slozka.Text.Trim() } else { '/' }))
        }
        if ($druh -eq 'databaze') {
            if ($Databaze.Text.Trim()) { $argumenty += @('-Databaze', $Databaze.Text.Trim()) }
            if ($Port.Text.Trim()) { $argumenty += @('-Port', $Port.Text.Trim()) }
        }
        if ($Poznamka.Text.Trim()) { $argumenty += @('-Poznamka', $Poznamka.Text.Trim()) }

        $Stav.Text = SpustNastroj $argumenty $Heslo.Password
    }

    $Heslo.Clear()
    $Cil.Text = ''
    $Poznamka.Text = ''
    $Sezeni.SelectedIndex = 0
    Obnov
})

$Upravit.Add_Click({
    if (-not $Seznam.SelectedItem) { $Stav.Text = 'Vyber cíl v seznamu.'; return }
    $cil = $Seznam.SelectedItem.Cil
    $z = NactiZaznamCile $cil

    $druh = if ($z.Druh) { $z.Druh } else { 'ftp' }
    $nazevDruhu = ($DRUHY.GetEnumerator() | Where-Object { $_.Value -eq $druh } | Select-Object -First 1).Key
    if ($nazevDruhu) { $DruhBox.SelectedItem = $nazevDruhu }
    PodleDruhu

    $Cil.Text = $z.Cil
    $Server.Text = [string]$z.Server
    $Uzivatel.Text = [string]$z.Uzivatel
    $Protokol.Text = [string]$z.Protokol
    $Slozka.Text = [string]$z.Slozka
    $Databaze.Text = [string]$z.Databaze
    $Port.Text = [string]$z.Port
    $Poznamka.Text = [string]$z.Poznamka
    $Heslo.Clear()

    $script:UpravovanyCil = $cil
    $Ulozit.Content = 'Uložit změny'
    $Stav.Text = "Upravuješ $cil. Heslo nech prázdné, pokud ho měnit nechceš."
})

$Ukazat.Add_Click({
    if (-not $Seznam.SelectedItem) { $Stav.Text = 'Vyber cíl v seznamu.'; return }
    $cil = $Seznam.SelectedItem.Cil
    $z = NactiZaznamCile $cil
    $tajne = TajneJakoText $z

    # Detail se čte přímo tady, ne přes příkaz: heslo se tím nedostane do
    # výpisu, který by si mohl uložit agent.
    $detail = New-Object System.Windows.Window
    $detail.Title = "Trezor: $cil"
    $detail.Width = 560
    $detail.Height = 420
    $detail.WindowStartupLocation = 'CenterOwner'
    $detail.Owner = $okno
    $detail.Background = $okno.Background
    $detail.FontFamily = $okno.FontFamily

    $panel = New-Object System.Windows.Controls.StackPanel
    $panel.Margin = '22'

    function PridejRadek($popis, $hodnota) {
        if (-not $hodnota) { return }
        $p = New-Object System.Windows.Controls.TextBlock
        $p.Text = $popis
        $p.Foreground = '#8A93A0'
        $p.FontSize = 11.5
        $p.Margin = '0,8,0,2'
        $panel.Children.Add($p) | Out-Null

        $h = New-Object System.Windows.Controls.TextBox
        $h.Text = [string]$hodnota
        $h.IsReadOnly = $true
        $h.Background = '#11141A'
        $h.Foreground = '#E8EAED'
        $h.BorderBrush = '#242833'
        $h.Padding = '8,6'
        $h.FontSize = 13
        $panel.Children.Add($h) | Out-Null
    }

    $druh = if ($z.Druh) { $z.Druh } else { 'ftp' }
    PridejRadek 'Druh' $druh
    PridejRadek 'Server' $z.Server
    PridejRadek 'Uživatel' $z.Uzivatel
    if ($druh -eq 'databaze') {
        PridejRadek 'Databáze' $z.Databaze
        PridejRadek 'Port' $z.Port
    }
    if ($druh -eq 'ftp') {
        PridejRadek 'Protokol' $z.Protokol
        PridejRadek 'Složka' $z.Slozka
    }
    PridejRadek 'Poznámka' $z.Poznamka

    $popisTajne = New-Object System.Windows.Controls.TextBlock
    $popisTajne.Text = if ($druh -eq 'token') { 'Token nebo klíč' } else { 'Heslo' }
    $popisTajne.Foreground = '#8A93A0'
    $popisTajne.FontSize = 11.5
    $popisTajne.Margin = '0,12,0,2'
    $panel.Children.Add($popisTajne) | Out-Null

    $poleTajne = New-Object System.Windows.Controls.TextBox
    $poleTajne.Text = ('*' * $tajne.Length)
    $poleTajne.IsReadOnly = $true
    $poleTajne.Background = '#11141A'
    $poleTajne.Foreground = '#E8EAED'
    $poleTajne.BorderBrush = '#242833'
    $poleTajne.Padding = '8,6'
    $poleTajne.FontSize = 13
    $panel.Children.Add($poleTajne) | Out-Null

    $radekTlacitek = New-Object System.Windows.Controls.StackPanel
    $radekTlacitek.Orientation = 'Horizontal'
    $radekTlacitek.Margin = '0,14,0,0'

    $prepnout = New-Object System.Windows.Controls.Button
    $prepnout.Content = 'Zobrazit heslo'
    $prepnout.Padding = '14,7'
    $prepnout.Margin = '0,0,10,0'
    $prepnout.Add_Click({
        if ($poleTajne.Text -match '^\*+$') {
            $poleTajne.Text = $tajne
            $prepnout.Content = 'Skrýt heslo'
        } else {
            $poleTajne.Text = ('*' * $tajne.Length)
            $prepnout.Content = 'Zobrazit heslo'
        }
    }.GetNewClosure())
    $radekTlacitek.Children.Add($prepnout) | Out-Null

    $kopirovat = New-Object System.Windows.Controls.Button
    $kopirovat.Content = 'Kopírovat heslo'
    $kopirovat.Padding = '14,7'
    $kopirovat.Margin = '0,0,10,0'
    $kopirovat.Add_Click({
        [System.Windows.Clipboard]::SetText($tajne)
        $kopirovat.Content = 'Zkopírováno'
    }.GetNewClosure())
    $radekTlacitek.Children.Add($kopirovat) | Out-Null

    $zavrit = New-Object System.Windows.Controls.Button
    $zavrit.Content = 'Zavřít'
    $zavrit.Padding = '14,7'
    $zavrit.Add_Click({ $detail.Close() }.GetNewClosure())
    $radekTlacitek.Children.Add($zavrit) | Out-Null

    $panel.Children.Add($radekTlacitek) | Out-Null

    $rolovani = New-Object System.Windows.Controls.ScrollViewer
    $rolovani.VerticalScrollBarVisibility = 'Auto'
    $rolovani.Content = $panel
    $detail.Content = $rolovani
    $detail.ShowDialog() | Out-Null
    $Stav.Text = "Detail $cil zavřen."
})

$Seznam.Add_SelectionChanged({
    if ($script:UpravovanyCil -and $Seznam.SelectedItem -and $Seznam.SelectedItem.Cil -ne $script:UpravovanyCil) {
        $script:UpravovanyCil = ''
        $Ulozit.Content = 'Uložit do trezoru'
    }
})

$Smazat.Add_Click({
    if (-not $Seznam.SelectedItem) { $Stav.Text = 'Vyber cíl v seznamu.'; return }
    $cil = $Seznam.SelectedItem.Cil
    $cesta = Join-Path $TREZOR ($cil + '.xml')
    if (Test-Path $cesta) { Remove-Item $cesta -Force }
    $Stav.Text = "Smazáno: $cil"
    Obnov
})

$Zkusit.Add_Click({
    if (-not $Seznam.SelectedItem) { $Stav.Text = 'Vyber cíl v seznamu.'; return }
    $polozka = $Seznam.SelectedItem
    if ($polozka.Druh -ne 'ftp') {
        $Stav.Text = "Zkouška spojení umí zatím jen FTP, $($polozka.Cil) je druhu $($polozka.Druh)."
        return
    }
    $Stav.Text = "Zkouším spojení s $($polozka.Cil) ..."
    $okno.Dispatcher.Invoke([action] {}, 'Background')
    $vystup = SpustNastroj @('ftp', $polozka.Cil, '::', 'ls') $null 40
    if ($vystup -match 'Připojeno|Connected') {
        $Stav.Text = "$($polozka.Cil) : spojení funguje."
    } else {
        $radek = ($vystup -split "`n" | Where-Object { $_.Trim() } | Select-Object -Last 1)
        $Stav.Text = "$($polozka.Cil) : spojení selhalo. $radek"
    }
})

$Zavrit.Add_Click({ $okno.Close() })

$okno.ShowDialog() | Out-Null
