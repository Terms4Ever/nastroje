# 🧰 Nástroje

**Společná pravidla pro repozitáře Terms4Ever**

Jedno místo, kde žijí pravidla pro README osobních projektů, a jeden skript,
který je umí vynutit. Stejný skript pouští pre-push hook na počítači i kontrola
na GitHubu, takže se pravidla mění na jednom místě a platí všude.

![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?logo=php&logoColor=white)
![Bez závislostí](https://img.shields.io/badge/z%C3%A1vislosti-%C5%BE%C3%A1dn%C3%A9-success)
![License](https://img.shields.io/badge/license-proprietary-red)
[![Pravidla: nastroje](https://img.shields.io/badge/pravidla-nastroje-0969da)](https://github.com/Terms4Ever/nastroje)

---

## ✨ Hlavní funkce

Sedm projektů mělo sedm různých README. Tytéž sekce se jmenovaly pokaždé jinak
(`Stack`, `Tech Stack`, `Použité technologie`), dva projekty README neměly
vůbec a jeden popisoval instalaci, která nefungovala.

Kontrola hlídá šest věcí:

- **Kostru** - povinné sekce, jejich názvy a pořadí.
- **Příslušnost pravidel** - jedinou sadu v `.pravidla.json`, shodný odznak,
  pokyny agenta, skutečné zapojení workflow a odpovídající GitHub topic.
- **Pravdivost** - každá cesta a odkaz, o kterých README mluví, musí existovat.
- **Aktualizaci** - dávka, která sáhla na kód, musí sáhnout i na dokumentaci.
- **Migrace databáze** - změna schématu má vlastní soubor ve složce migrací,
  hotová migrace se už nemění a na produkci ji pouští nasazení, ne člověk.
- **Issues** - pevný seznam sekcí, jeden checklist pod „Hotovo, když", tělo do
  40 řádků, odškrtaný checklist u zavřeného issue, komentáře do pěti řádků
  a žádná zmínka o nástroji, kterým se psaly. Syrový nápad zadavatele (tělo bez
  nadpisů) chyba není, jen čeká na přepsání. Snímky odkazují na otisk commitu
  a issue se zavírá přes `zavrit-issue.php`, který chce zelené CI.

Druhá a třetí půlka jsou ty cennější. Rozbitá kostra je nepříjemná,
dokumentace, která lže nebo zůstala pozadu, stojí čas.

---

## 🛠️ Tech Stack

| Vrstva  | Technologie                     |
|---------|---------------------------------|
| Skript  | PHP 8.3, žádné závislosti       |
| Brána před pushem | pre-push hook přes `core.hooksPath` |
| Kontrola po pushi | GitHub Actions, `ubuntu-24.04`; chybu nahlásí, push nezastaví |

Čisté PHP schválně: běhové prostředí `ubuntu-24.04` má PHP předinstalované,
takže kontrola na GitHubu nepotřebuje jediný instalační krok.

---

## 📁 Struktura projektu

```
nastroje/
├── .pravidla.json               # jediná primární sada pro tento projekt
├── kontrola-pravidel.php        # výběr sady a shoda označení, online i topic
├── kontrola-readme.php          # kontrola README
├── kontrola-dokumentace.php     # kontrola složky docs/
├── kontrola-migraci.php         # kontrola migrací databáze
├── kontrola-issues.php          # kontrola tvaru issues na GitHubu
├── kontrola-tvaru-issue.php     # kontrola jednoho těla, než issue vznikne
├── zavrit-issue.php             # zavření issue jen s důkazem ze zeleného CI
├── src/tvar-issue.php           # pravidla tvaru issue na jednom místě
├── src/sada-pravidel.php        # společná kontrola označení obou sad
├── hooky/tvar-issue.ps1         # hook, který špatné issue nepustí vzniknout
├── hooky/tajemstvi.ps1          # trezor přihlašovacích údajů pro agenty
├── hooky/trezor-spravce.ps1     # okno trezoru, spouští se zástupcem z plochy
├── hooky/test-trezor.ps1        # proklikání okna přes UI Automation
├── tests/spust.php              # regresní testy kontrol, každá díra má případ
├── tests/kompatibilita.php      # nové kontroly proti všem projektům před pushem
├── stav-projektu.php            # generátor bloku se skutečnými čísly
├── prehled-migraci.php          # generátor přehledu migrací
├── sablony/readme-plny.md       # vzor k opsání
├── sablony/migrace.php          # vzor spouštěče migrací pro projekt
├── sablony/issue-ukol.md        # vzor šablony issue pro projekt
├── sablony/agents.md            # vzor agentského souboru pro projekt
├── sablony/zadani-novy-projekt.md  # zadání pro přesun FTP projektu na GitHub
├── sablony/docs/                # vzory dokumentů 01, 02 a 04 pro projekt
├── docs/                        # stav projektu a deník rozhodnutí
└── .github/workflows/           # workflow, který volají ostatní projekty
```

---

## 🏷️ Profily

Každý zapojený projekt vybírá v `.pravidla.json` právě jednu sadu. Osobní
projekty mají `nastroje`, topic `pravidla-nastroje` a workflow `Pravidla / nastroje`.
Pracovní projekty mají `nastroje-prace` a její vlastní postup napojení.
Připnutá knihovna nastroje uvnitř pracovní sady není druhá primární sada.
Rozdíl a kontrola jsou popsány v `docs/02-nasazeni.md`.

Každý projekt si v kořeni drží `.readme-kontrola.json`:

```json
{
  "profil": "plny",
  "cesty-bez-kontroly": ["config.local.php", "log/"]
}
```

Jediný profil je `plny`. Zkrácená varianta existovala do 15. 9. 2026, ale
dělila projekty na dvě třídy bez užitku: i drobná aplikace umí říct, co dělá.
Kdo má málo funkcí, napíše krátkou sekci.

Povinné sekce, v tomhle pořadí: Hlavní funkce, Tech Stack, Struktura projektu,
Instalace (lokální vývoj), Nasazení, Licence. Nepovinné sekce smí být jakékoli,
ale s emoji v nadpisu.

Když má projekt složku `docs/`, README ji musí vypsat v sekci Dokumentace,
každý dokument s vlastním popisem. Co v docs/ leží, si řídí každý projekt sám.

Text nesmí obsahovat dlouhou ani polovičnou pomlčku, jen krátkou.

Klíč `cesty-bez-kontroly` je pro soubory, o kterých README mluví, ale
v repozitáři nejsou - typicky `config.local.php` nebo složka s logy.

---

## 🔎 Kontrola dokumentace

Zapíná se přihlášením, aby repozitář s jinak uspořádanou složkou `docs/`
nespadl dřív, než si ji srovná:

```json
{
  "docs-kontrola": true,
  "docs-pomlcky": "blokovat",
  "docs-vymahat-aktualizaci": true
}
```

Co ověřuje:

| Pravidlo | Co dělá |
|---|---|
| generovaný blok | `docs/00-stav-projektu.md` musí nést blok se skutečnými čísly a ten musí sedět na to, co by generátor vypsal teď |
| pomlčky | dlouhá ani polovičná pomlčka v dokumentech; `"varovat"` místo `"blokovat"` hlášku jen vypíše |
| aktualizace | dávka commitů, která změnila kód, musí přidat nebo upravit dokument `.md` v `docs/`; obrázek, příloha ani smazání se nepočítají |

Blok se skutečnými čísly do stavu projektu vloží nebo přegeneruje:

```bash
php stav-projektu.php ../nazev-projektu --zapsat
```

Nese jen údaje, které se mění zřídka: verzi, běhové prostředí a hlavní větev.
Otisk posledního commitu v něm schválně není, ten by byl zastaralý už
v commitu, který ho obsahuje.

Cesty uvnitř dokumentů se **nekontrolují**. V README to smysl dává, tam se
popisuje současný stav. Rozhodovací deník ale musí umět napsat, že se soubor
smazal nebo přejmenoval.

Jestli text ke změně opravdu sedí, kontrola nepozná. Pozná jen, že dávka
s kódem přidala nebo upravila dokument; levná obejití (obrázek mezi
snímky, smazání souboru) zavírá, věcnou správnost nezaručí.

---

## 🧪 Testy kontrol

Každá kontrola má případ, který ji shodí. Bez něj se nepozná, že přestala
fungovat: audit 23. 9. 2026 našel šest děr a žádný test by je nepustil.

```bash
php tests/spust.php              # všechny případy
php tests/spust.php migrace      # jen případy s "migrace" v názvu
php tests/kompatibilita.php      # kontroly z tohohle stromu proti všem projektům
```

Testy stavějí dočasné repozitáře a `gh` nahrazují atrapou, takže nesahají
na síť ani na skutečné issues. V CI běží na Linuxu i na Windows: případy
s hookem Claude Code a cestou z Git Bashe jdou pustit jen na Windows. Spouštěč migrací se zkouší na skutečné
databázi, kterou dodá proměnná `NASTROJE_TEST_MYSQL` ve tvaru
`dsn|uživatel|heslo`; každý případ si založí vlastní databázi a smaže ji. Běží v CI nastroje a pre-push hook je pustí
před každým pushem do nastroje spolu s kontrolou kompatibility.

---

## 📚 Dokumentace

| Dokument | K čemu |
|---|---|
| `docs/00-stav-projektu.md` | živý stav: co je hotové, co se dělá, co je dál, a které repozitáře jsou zapojené |
| `docs/01-postup-prace.md` | jak se mění pravidlo nebo kontrola, pořadí pushů a pasti z Windows |
| `docs/02-nasazeni.md` | jak se pravidla dostanou do projektů: sdílená workflow, nasazení webů, hooky, zapojení projektu |
| `docs/03-rozhodovaci-dennik.md` | co bylo kdy rozhodnuto a proč. Nové rozhodnutí je nový záznam, staré se nepřepisuje |
| `docs/04-overeni.md` | co testy kontrol dokazují a co ne, kde běží, co se přeskočí |
| `docs/05-standard-issues.md` | tvar, zařazení, snímky a zavření issue s důkazem, kde se to vynucuje |
| `docs/06-standard-migraci.md` | migrace, jejich nasazení a spouštěč s otisky |
| `docs/07-trezor-hesel.md` | hesla bez chatu: příkazy pro agenta, okno pro zadavatele, co trezor nechrání |
| `docs/08-soubory-a-dokumentace.md` | uspořádání repozitáře a které dokumenty má projekt mít |

Stav vždy platný je v `docs/00-stav-projektu.md`, ne v tomhle souboru.

---

## 🚀 Instalace (lokální vývoj)

```bash
git clone https://github.com/Terms4Ever/nastroje.git
cd nastroje
php kontrola-readme.php ../nazev-projektu
php kontrola-pravidel.php ../nazev-projektu --online
```

Bez parametru se kontroluje aktuální adresář. Návratový kód 0 znamená
v pořádku, 1 nálezy.

---

## 📦 Nasazení

**Projekt má výslovný výběr sady a společné workflow.** Nastavením README:

```json
{ "profil": "plny" }
```

a workflow, který zavolá kontrolu odsud:

```yaml
name: Pravidla / nastroje
on: [push, pull_request]
permissions:
  contents: read
  issues: read
jobs:
  readme:
    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main
```

Výběr v `.pravidla.json` je `{"sada":"nastroje"}`. V úvodu README je
odznak s odkazem na tuto sadu, začátek AGENTS ji deklaruje a GitHub má topic
`pravidla-nastroje`. Přesné vzory obsahuje složka `sablony/`.

**Projekt, který nasazuje**, volá stejný workflow i jako první job
svého workflow nasazení a nahrává až po jeho úspěchu:

```yaml
jobs:
  kontroly:
    permissions:
      contents: read
      issues: read
    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main
  deploy:
    needs: kontroly
```

**Lokálně** ji pouští pre-push hook ze složky `.git-hooks` v domovském
adresáři. Ohlásí se dřív, než se commity dostanou na GitHub. Když chybí PHP
nebo skript kontroly, push zastaví, místo aby ho tiše pustil.

**Repozitář je veřejný schválně.** Sdílený workflow ze soukromého repozitáře
potřebuje u osobního účtu nastavovat přístup navíc, a tady není co skrývat.

---

## 📄 Licence

Proprietární software. Veškerá práva vyhrazena.
