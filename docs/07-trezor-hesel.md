# Trezor hesel

Jak agent pracuje s hesly a tokeny, aniž by je viděl, a jak je do trezoru
dostane zadavatel. Rozhodnutí je v deníku pod N30.

## K čemu je

Hesla nepatří do chatu, do přepisu session, do příkazové řádky ani do
souborů, které se zálohují a synchronizují. Agent proto zná jen **název
cíle**; hodnotu vloží skript až do spuštěného procesu a po jeho doběhnutí ji
zahodí. O heslo se v chatu nežádá.

## Druhy a uložení

Čtyři druhy: FTP, databáze, token nebo klíč a jiné heslo. Každý cíl leží
v `%USERPROFILE%\.tajemstvi\<cíl>.xml`, šifrovaný přes DPAPI: soubor jde
zkopírovat jinam, ale rozšifrovat jen pod tím účtem na tom počítači.

## Příkazy pro agenta

```bash
powershell -File C:/laragon/www/nastroje/hooky/tajemstvi.ps1 seznam
powershell -File C:/laragon/www/nastroje/hooky/tajemstvi.ps1 ftp <cíl> :: "ls /web"
powershell -File C:/laragon/www/nastroje/hooky/tajemstvi.ps1 spustit <cíl> :: <příkaz>
```

| Příkaz | Co dělá |
|---|---|
| `seznam` | cíle, druhy, servery a uživatelé; heslo nikdy |
| `ftp <cíl> :: <řádky>` | poskládá dočasný skript pro WinSCP, spustí ho a smaže |
| `spustit <cíl> :: <příkaz>` | vloží `TAJ_SERVER`, `TAJ_UZIVATEL`, `TAJ_HESLO` (u databáze i `TAJ_DB_*` a `MYSQL_PWD`, u tokenu `TAJ_TOKEN`) do prostředí příkazu a po doběhnutí je zahodí |
| `okno <cíl>` | otevře okno, do kterého údaje vyplní zadavatel |
| `sezeni` | vypíše sezení uložená ve WinSCP |
| `zwinscp "<sezení>" <cíl>` | převezme sezení z WinSCP, bez psaní hesla |
| `ulozit`, `upravit`, `smazat` | správa cíle z terminálu |

Oddělovač je `::`, protože `--` si bere PowerShell. Příkaz na vypsání hesla
schválně neexistuje. K FTP cíli jde uložit otisk certifikátu (`-Otisk`), bez
něj WinSCP u neznámého certifikátu skončí; certifikát se neobchází.

## Okno pro zadavatele

Zástupce **Trezor hesel** na ploše otevře `hooky/trezor-spravce.ps1`: přidat
cíl, převzít ho z WinSCP, upravit, zobrazit údaje, smazat a vyzkoušet spojení.
Heslo se zobrazí jen v okně, ne ve výpisu. Zkouška spojení se rozhoduje podle
návratového kódu WinSCP, ne podle textu: text chodí v kódování konzole
a hledání slova „Připojeno" selhávalo i u funkčního spojení.

## Co trezor nechrání

- Proces, kterému agent hodnotu předá, ji vidí; kdo řídí ten příkaz, může ji
  vypsat. Trezor chrání před náhodným únikem, ne před úmyslem.
- WinSCP drží svá sezení v registru jen zaobalená, ne zašifrovaná.
- Tajemství pro GitHub Actions (`FTP_PASSWORD`, `MIGRACE_TOKEN`) v trezoru
  nejsou; nastavuje je zadavatel v nastavení repozitáře.

## Ověření

`hooky/test-trezor.ps1` proklikává okno přes UI Automation: vybere cíl,
upraví ho, uloží a vyzkouší spojení. Pouští se ručně, v CI neběží.
