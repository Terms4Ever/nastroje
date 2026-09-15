# Stav projektu

Živý stav. Co je hotové, co se dělá, co je dál. Přepisuje se, nepřidává.

<!-- generovano nastroji, needitovat -->
```
hlavní větev:     main
```
<!-- konec generovaneho bloku -->

## Co je hotové

- `kontrola-readme.php` ve verzi 2.0.0, čisté PHP bez závislostí
- `kontrola-dokumentace.php` ve verzi 1.0.0, zapíná se přihlášením
- `stav-projektu.php`, generátor bloku se skutečnými čísly
- Kontrola kostry: povinné sekce, jejich názvy, pořadí, hlavička s odznaky
- Kontrola pravdivosti: cesty, odkazy a kotvy zmíněné v README musí existovat
- Zákaz dlouhých pomlček
- Kontrola dohledatelnosti složky `docs/`
- Workflow `readme.yml`, který si ostatní repozitáře volají jedním odkazem
- Vynucení, že dávka s kódem sáhne i na dokumentaci
- Vzor `sablony/readme-plny.md`

## Zapojené repozitáře

| Repozitář | Profil | Poznámka |
|---|---|---|
| `onlinefakturuj.cz` | plny | |
| `vyridimestavbu.cz` | plny | |
| `trenwise.cz` | plny | vývoj pozastaven |
| `steelset` | plny | |
| `LabProtocol` | plny | |
| `Project-Igris` | plny | nad kostrou má vlastní testy na obsah |
| `nastroje` | plny | kontroluje sám sebe |

## Co se dělá

Nic rozdělaného.

## Co je dál

- Rozšířit kontrolu na soulad odznaků se skutečnými verzemi ze
  `package.json` nebo `composer.json`. Igris to už umí ve svých testech,
  stálo by za to posunout to sem, aby to platilo všude.
- Zvážit kontrolu, že `docs/00-stav-projektu.md` není starší než poslední
  commit, který mění kód. Zastaralý stav je horší než žádný.
