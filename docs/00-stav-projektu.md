# Stav projektu

Živý stav. Co je hotové, co se dělá, co je dál. Přepisuje se, nepřidává.

<!-- generovano nastroji, needitovat -->
```
hlavní větev:     main
```
<!-- konec generovaneho bloku -->

## Co je hotové

- `kontrola-readme.php` ve verzi 2.0.0, čisté PHP bez závislostí
- `kontrola-dokumentace.php` ve verzi 1.4.1, zapíná se přihlášením,
  složku `docs/` prochází rekurzivně včetně podsložek. Vadné nastavení
  a neplatné UTF-8 hlásí jako chybu, ne jako důvod k přeskočení. Když zná
  pushovaný commit, ověří, že pracovní strom v čtených cestách sedí
- `stav-projektu.php`, generátor bloku se skutečnými čísly. Hlavní větev
  bere z repozitáře, ne z větve, na které se zrovna stojí
- `kontrola-migraci.php` ve verzi 1.0.0, zapíná se přihlášením přes
  `"migrace-kontrola": true`. Hlídá tvar migrací, přehled, neměnnost hotové
  migrace a to, že dávka měnící schéma migraci přidá
- `prehled-migraci.php`, generátor přehledu migrací do `db/prehled.md`
- `sablony/migrace.php`, spouštěč migrací k okopírování do projektu:
  pustí nespuštěné migrace, zapíše je do tabulky `migrace` a při chybě
  spadne, aby nasazení nepokračovalo s rozladěnou databází
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
- Pravidla commitů žijí jen v neverzovaném `~/.git-hooks/commit-msg`. Stálo by
  za to přesunout je sem a pouštět je i na GitHubu, jako README a dokumentaci.

## Standard issues

Jeden tvar pro chybu i funkci: `## Problém` (nebo `## Cíl`) a `## Hotovo, když`
s odškrtávacím seznamem. Zavřené issue má checklist odškrtaný, komentáře mají
do pěti řádků, nikde se nepíše, čím se text psal, a snímky před a po leží
v `docs/snimky/<číslo>-<název>/`. Hlídá to `kontrola-issues.php`, která běží
v kontrolách na GitHubu při pushi a jednou denně.

## Standard migrací

Jedna změna schématu je jeden soubor `db/migrace/rrrr-mm-dd-popis.sql`
s komentářem na začátku. Hotová migrace se už nemění, oprava je nová
migrace. Přehled v `db/prehled.md` generuje `prehled-migraci.php`, kdy
která migrace proběhla na produkci, drží tabulka `migrace` v databázi.
Na produkci je pouští nasazení, ne člověk, a nahrává je jako `nazev.sql.php`
se zámkem `<?php exit; ?>` na prvním řádku: soubor `.sql` by web poslal jako
text, `.php` se vykoná a nevypíše nic.

Projekty s vlastním migračním nástrojem (Laravel) se do standardu
nezapojují, pravidla si nese framework.

## Na co si dát pozor

- **Kontrola na GitHubu push nezastaví.** Větve nemají ochranu, takže běh
  chybu jen nahlásí po faktu. Zastavit push umí jen pre-push hook. Ochranu
  větví zadavatel 16. 9. 2026 odmítl, pull requesty schvalovat nechce (N16),
  takže to tak zůstane.
