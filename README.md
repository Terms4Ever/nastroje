# 🧰 Nástroje

**Společná pravidla pro repozitáře Terms4Ever**

Jedno místo, kde žijí pravidla pro README všech mých projektů, a jeden skript,
který je umí vynutit. Stejný skript pouští pre-push hook na počítači i kontrola
na GitHubu, takže se pravidla mění na jednom místě a platí všude.

![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?logo=php&logoColor=white)
![Bez závislostí](https://img.shields.io/badge/z%C3%A1vislosti-%C5%BE%C3%A1dn%C3%A9-success)
![License](https://img.shields.io/badge/license-proprietary-red)

---

## 🎯 Co to řeší

Sedm projektů mělo sedm různých README. Tytéž sekce se jmenovaly pokaždé jinak
(`Stack`, `Tech Stack`, `Použité technologie`), dva projekty README neměly
vůbec a jeden popisoval instalaci, která nefungovala.

Kontrola hlídá dvě věci:

- **Kostru** — povinné sekce, jejich názvy a pořadí.
- **Pravdivost** — každá cesta a odkaz, o kterých README mluví, musí existovat.

Druhá půlka je ta cennější. Rozbitá kostra je nepříjemná, README, které lže,
stojí čas.

---

## 🛠️ Tech Stack

| Vrstva  | Technologie                     |
|---------|---------------------------------|
| Skript  | PHP 8.3, žádné závislosti       |
| Brána   | GitHub Actions, `ubuntu-24.04`  |
| Brána   | pre-push hook přes `core.hooksPath` |

Čisté PHP schválně: běhové prostředí `ubuntu-24.04` má PHP předinstalované,
takže kontrola na GitHubu nepotřebuje jediný instalační krok.

---

## 📁 Struktura projektu

```
nastroje/
├── kontrola-readme.php          # samotná kontrola
├── sablony/                     # vzory k opsání
└── .github/workflows/           # workflow, který volají ostatní projekty
```

---

## 🏷️ Profily

Každý projekt si v kořeni drží `.readme-kontrola.json`:

```json
{
  "profil": "plny",
  "cesty-bez-kontroly": ["config.local.php", "log/"]
}
```

| Profil   | Pro koho                                   | Povinné sekce |
|----------|--------------------------------------------|---------------|
| `plny`   | onlinefakturuj, vyridimestavbu, trenwise   | Hlavní funkce, Tech Stack, Struktura, Instalace, Nasazení, Licence |
| `slim`   | steelset, LabProtocol, labprotocol-web     | totéž bez Hlavních funkcí |
| `igris`  | Project-Igris                              | vlastní sada: Co je hotové, Kde co je, Dokumentace, Kontroly, Kontrola na GitHubu, Prostředí |

Igris má zavedený vlastní styl bez emoji a nejpřísnější testy ze všech. Do
společné kostry se nesrovnává, ale svoje vlastní nadpisy držet musí.

Klíč `cesty-bez-kontroly` je pro soubory, o kterých README mluví, ale
v repozitáři nejsou — typicky `config.local.php` nebo složka s logy.

---

## 🚀 Instalace (lokální vývoj)

```bash
git clone https://github.com/Terms4Ever/nastroje.git
cd nastroje
php kontrola-readme.php ../nazev-projektu
```

Bez parametru se kontroluje aktuální adresář. Návratový kód 0 znamená
v pořádku, 1 nálezy.

---

## 📦 Nasazení

**Do projektu se to zapojí dvěma soubory.** Nastavením:

```json
{ "profil": "plny" }
```

a workflow, který zavolá kontrolu odsud:

```yaml
name: Kontroly
on: [push, pull_request]
jobs:
  readme:
    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main
```

**Lokálně** ji pouští pre-push hook ze složky `.git-hooks` v domovském
adresáři. Ohlásí se dřív, než se commity dostanou na GitHub.

**Repozitář je veřejný schválně.** Sdílený workflow ze soukromého repozitáře
potřebuje u osobního účtu nastavovat přístup navíc, a tady není co skrývat.

---

## 📄 Licence

Proprietární software. Veškerá práva vyhrazena.
