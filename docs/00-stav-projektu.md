# Stav projektu

Živý stav. Co je hotové, co se dělá, co je dál. Přepisuje se, nepřidává.
Stálá pravidla a postupy mají vlastní dokumenty, tabulka je v README.

<!-- generovano nastroji, needitovat -->
```
hlavní větev:     main
```
<!-- konec generovaneho bloku -->

## Co je hotové

| Nástroj | Verze | Podrobně |
|---|---|---|
| `kontrola-readme.php` | 2.1.0 | README |
| `kontrola-dokumentace.php` | 1.7.2 | [08 soubory a dokumentace](08-soubory-a-dokumentace.md) |
| `kontrola-migraci.php` | 1.1.0 | [06 standard migrací](06-standard-migraci.md) |
| `kontrola-issues.php` | 1.10.0 | [05 standard issues](05-standard-issues.md) |
| `kontrola-tvaru-issue.php`, `hooky/tvar-issue.ps1` | - | [05 standard issues](05-standard-issues.md) |
| `zavrit-issue.php` | - | [05 standard issues](05-standard-issues.md) |
| `stav-projektu.php`, `prehled-migraci.php` | - | generátory bloků |
| `sablony/migrace.php` | - | [06 standard migrací](06-standard-migraci.md) |
| `hooky/tajemstvi.ps1`, `hooky/trezor-spravce.ps1` | - | [07 trezor hesel](07-trezor-hesel.md) |
| `tests/spust.php` (74 případů), `tests/kompatibilita.php` | - | [04 ověření](04-overeni.md) |

Kontroly končí chybou i tehdy, když nemohly proběhnout (chybí `docs/`,
neexistuje cesta, gh nepřečte issues, neznámý základ rozsahu); dřív to byla
zelená (N32). Kontrola dokumentace od 1.7.0 doporučí dokument k nasazení
nebo testům, když ho projekt nemá (N34).

## Zapojené repozitáře

| Repozitář | Nasazení čeká na kontroly | Poznámka |
|---|---|---|
| `onlinefakturuj.cz` | ano | testy v CI (14 z 15), migrace |
| `vyridimestavbu.cz` | ano | migrace |
| `tomas.saroun.me` | ano | statický web |
| `steelset` | - | mobilní aplikace, Jest testy v CI |
| `LabProtocol` | - | mobilní aplikace |
| `trenwise.cz` | - | vývoj pozastaven, FTP zatím není |
| `Project-Igris` | - | vlastní hooky a testy, sdílená kontrola navíc |
| `nastroje` | - | kontroluje sám sebe, testy na Linuxu i Windows |

`nastroje-prace` (osobní nadstavba pro pracovní projekty) společné kontroly
nevolá, má je připnuté zámkem na konkrétní commit.

## Co se dělá

Nic rozdělaného.

## Co je dál

- Doplnit dokumenty, které kontrola dokumentace doporučuje, do zapojených
  projektů (nasazení u tří webů, ověření u projektů s testy).
- Rozšířit kontrolu na soulad odznaků se skutečnými verzemi ze
  `package.json` nebo `composer.json`. Igris to umí ve svých testech.
- Pravidla commitů a pre-push hook žijí jen v neverzovaném `~/.git-hooks/`.
  Stálo by za to přesunout je sem, verzovat je a testovat jako ostatní.
- FTP akce v nasazení tří webů není připnutá na otisk commitu, přitom
  dostává hesla. Rozhodnutí zadavatele zatím nepadlo.

## Na co si dát pozor

- **Změna pravidla platí všude hned.** Projekty volají kontroly větví
  `@main`. Pre-push hook proto pustí `tests/kompatibilita.php` a změnu, kvůli
  které by některý projekt spadl, nepustí. Postup je v
  [01 postup práce](01-postup-prace.md).
- **Kontrola na GitHubu push nezastaví**, větve nemají ochranu (N16). Na
  kontroly ale čeká nasazení tří webů (N32), viz [02 nasazení](02-nasazeni.md).
- **Hooky v `~/.git-hooks/` nejsou v gitu.** Co se v nich změní, zmizí při
  přeinstalaci počítače; popis je v [02 nasazení](02-nasazeni.md).
