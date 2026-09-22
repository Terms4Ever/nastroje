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
        Title="Trezor hesel" Height="800" Width="920"
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
      <ColumnDefinition Width="330"/>
    </Grid.ColumnDefinitions>

    <StackPanel Grid.Row="0" Grid.ColumnSpan="2" Margin="0,0,0,18">
      <TextBlock Text="Trezor hesel" Style="{StaticResource Nadpis}"/>
      <TextBlock Style="{StaticResource Podnadpis}"
                 Text="Údaje jsou zašifrované na tenhle účet a počítač. Agent zná jen název cíle, heslo nikdy nevidí."/>
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
              <TextBlock Text="{Binding Cil}" FontSize="14.5" FontWeight="SemiBold" Foreground="#E8EAED"/>
              <TextBlock Text="{Binding Radek}" FontSize="12" Foreground="#8A93A0" Margin="0,3,0,0"/>
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

          <TextBlock Text="Převzít ze sezení WinSCP" Style="{StaticResource Popisek}"/>
          <ComboBox x:Name="Sezeni"/>

          <TextBlock Text="Název cíle" Style="{StaticResource Popisek}"/>
          <TextBox x:Name="Cil"/>

          <TextBlock Text="Server" Style="{StaticResource Popisek}"/>
          <TextBox x:Name="Server"/>

          <TextBlock Text="Uživatel" Style="{StaticResource Popisek}"/>
          <TextBox x:Name="Uzivatel"/>

          <TextBlock Text="Heslo" Style="{StaticResource Popisek}"/>
          <PasswordBox x:Name="Heslo"/>

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

          <Button x:Name="Ulozit" Content="Uložit do trezoru" Style="{StaticResource TlacitkoHlavni}"
                  HorizontalAlignment="Stretch" Margin="0,4,0,0"/>
        </StackPanel>
      </ScrollViewer>
    </Border>

    <StackPanel Grid.Row="2" Grid.ColumnSpan="2" Orientation="Horizontal" Margin="0,18,0,0">
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
$Sezeni = & $prvek 'Sezeni'
$Cil = & $prvek 'Cil'
$Server = & $prvek 'Server'
$Uzivatel = & $prvek 'Uzivatel'
$Heslo = & $prvek 'Heslo'
$Protokol = & $prvek 'Protokol'
$Slozka = & $prvek 'Slozka'
$Ulozit = & $prvek 'Ulozit'
$Smazat = & $prvek 'Smazat'
$Zkusit = & $prvek 'Zkusit'
$Zavrit = & $prvek 'Zavrit'
$Stav = & $prvek 'Stav'

function NactiCile {
    if (-not (Test-Path $TREZOR)) { return @() }
    Get-ChildItem $TREZOR -Filter *.xml | ForEach-Object {
        $z = Import-Clixml $_.FullName
        [pscustomobject]@{
            Cil = $z.Cil
            Radek = '{0}@{1}   ·   {2}   ·   {3}' -f $z.Uzivatel, $z.Server, $z.Protokol, $z.Slozka
        }
    }
}

function Obnov {
    $Seznam.ItemsSource = @(NactiCile)
    $pocet = @(NactiCile).Count
    $slovo = switch ($pocet) { 1 { 'cíl' } { $_ -ge 2 -and $_ -le 4 } { 'cíle' } default { 'cílů' } }
    $Stav.Text = if ($pocet) { "V trezoru $(if ($pocet -eq 1) { 'je' } else { 'jsou' }) $pocet $slovo." } else { 'Trezor je zatím prázdný.' }
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

    $prebrat = $Sezeni.SelectedIndex -gt 0 -and -not $Heslo.Password
    $argumenty = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $NASTROJ)

    if ($prebrat) {
        $argumenty += @('zwinscp', [string]$Sezeni.SelectedItem, $cil)
        if ($Slozka.Text.Trim()) { $argumenty += @('-Slozka', $Slozka.Text.Trim()) }
        $vystup = & powershell @argumenty 2>&1 | Out-String
        $Stav.Text = $vystup.Trim()
    } else {
        if (-not $Heslo.Password) {
            $Stav.Text = 'Vyplň heslo, nebo vyber sezení WinSCP, ze kterého se převezme.'
            return
        }
        New-Item -ItemType Directory -Path $TREZOR -Force | Out-Null
        $cesta = Join-Path $TREZOR ($cil + '.xml')
        @{
            Cil = $cil
            Server = $Server.Text.Trim()
            Uzivatel = $Uzivatel.Text.Trim()
            Heslo = (ConvertTo-SecureString $Heslo.Password -AsPlainText -Force)
            Protokol = $(if ($Protokol.Text.Trim()) { $Protokol.Text.Trim() } else { 'ftpes' })
            Slozka = $(if ($Slozka.Text.Trim()) { $Slozka.Text.Trim() } else { '/' })
            Otisk = ''
            UlozenoAt = (Get-Date).ToString('s')
        } | Export-Clixml -Path $cesta
        icacls $cesta /inheritance:r /grant:r "${env:USERNAME}:(R,W)" | Out-Null
        $Stav.Text = "Uloženo: $cil (heslo o $($Heslo.Password.Length) znacích)"
    }

    $Heslo.Clear()
    $Cil.Text = ''
    $Sezeni.SelectedIndex = 0
    Obnov
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
    $cil = $Seznam.SelectedItem.Cil
    $Stav.Text = "Zkouším spojení s $cil ..."
    $okno.Dispatcher.Invoke([action] {}, 'Background')
    $vystup = & powershell -NoProfile -ExecutionPolicy Bypass -File $NASTROJ ftp $cil '::' 'ls' 2>&1 | Out-String
    if ($vystup -match 'Připojeno|Connected') {
        $Stav.Text = "$cil : spojení funguje."
    } else {
        $radek = ($vystup -split "`n" | Where-Object { $_.Trim() } | Select-Object -Last 1)
        $Stav.Text = "$cil : spojení selhalo. $radek"
    }
})

$Zavrit.Add_Click({ $okno.Close() })

$okno.ShowDialog() | Out-Null
