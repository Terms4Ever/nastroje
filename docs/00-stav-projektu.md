# Stav projektu

Živý stav. Co je hotové, co se dělá, co je dál. Přepisuje se, nepřidává.

<!-- generovano nastroji, needitovat -->
```
hlavní větev:     main
```
<!-- konec generovaneho bloku -->

## Co je hotové

- `kontrola-readme.php` ve verzi 2.1.0, čisté PHP bez závislostí
- `kontrola-dokumentace.php` ve verzi 1.6.1, zapíná se přihlášením,
  složku `docs/` prochází rekurzivně včetně podsložek. Vadné nastavení,
  neplatné UTF-8, chybějící `docs/` u zapnuté kontroly, neexistující cesta
  i neznámý základ rozsahu jsou chyba, ne důvod k přeskočení. Když zná
  pushovaný commit, ověří, že pracovní strom v čtených cestách sedí
- `stav-projektu.php`, generátor bloku se skutečnými čísly. Hlavní větev
  bere z repozitáře, ne z větve, na které se zrovna stojí; u mobilní
  aplikace bere verzi z `app.json`, ne z `package.json`
- `kontrola-migraci.php` ve verzi 1.1.0, zapíná se přihlášením přes
  `"migrace-kontrola": true`. Hlídá tvar migrací, přehled, neměnnost hotové
  migrace (změnu, smazání i přejmenování) a to, že dávka měnící schéma
  migraci přidá
- `kontrola-issues.php` ve verzi 1.9.0. Když issues nepřečte (chybí gh,
  přihlášení nebo `issues: read` v tokenu), neprojde; dřív skončila zeleně
- `tests/spust.php`, 42 regresních případů. Každá díra z auditu 23. 9. 2026
  má případ, který ji shodí; běží v CI nastroje i v pre-push hooku. Čtyři
  případy spouštěče migrací potřebují jednorázovou databázi
  (`NASTROJE_TEST_MYSQL`); bez ní se vypíšou jako přeskočené
- `tests/kompatibilita.php` pustí kontroly z pracovního stromu proti výchozí
  větvi všech projektů, které je volají. Pre-push hook nepustí změnu pravidla,
  kvůli které by některý projekt spadl
- `prehled-migraci.php`, generátor přehledu migrací do `db/prehled.md`
- `sablony/migrace.php`, spouštěč migrací k okopírování do projektu:
  pustí nespuštěné migrace, zapíše je do tabulky `migrace` a při chybě
  spadne, aby nasazení nepokračovalo s rozladěnou databází. Přejmenovanou
  hotovou migraci pozná podle otisku a znovu ji nepustí; změněná hotová
  migrace nasazení zastaví
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

## Uspořádání souborů

V kořeni jen `README.md`, `AGENTS.md`, `CLAUDE.md` (jediný řádek `@AGENTS.md`),
`LICENSE.md` a `CHANGELOG.md`; ostatní dokumenty do `docs/`, data mimo `docs/`
nebo do `docs/prilohy/`. Povinné jsou `docs/00-stav-projektu.md`
a `docs/03-rozhodovaci-dennik.md`. Hlídá to kontrola dokumentace od verze 1.5.0,
vzor agentského souboru je `sablony/agents.md`.

## Přihlašovací údaje pro agenty

Trezor drží čtyři druhy: FTP, databáze, token nebo klíč a jiné heslo.
Hesla a tokeny leží v trezoru: `hooky/tajemstvi.ps1` je uloží přes DPAPI do
`%USERPROFILE%\.tajemstvi\<cíl>.xml`, čitelné jen pod tím účtem na tom
počítači. Agent zná jen název cíle, hodnotu nikdy nevidí: `spustit` ji vloží
do prostředí spuštěného příkazu, `ftp` do dočasného skriptu WinSCP, který po
sobě uklidí.

Zadavatel s trezorem pracuje oknem, ne příkazovou řádkou: zástupce
**Trezor hesel** na ploše otevře `hooky/trezor-spravce.ps1`, kde jde cíl přidat,
převzít z WinSCP nebo smazat.

## Nový projekt na GitHub

Zadání pro agenta, který přebírá web z FTP hostingu, je
v `sablony/zadani-novy-projekt.md`: co se nesmí commitnout, které soubory patří
do kořene, jaká workflow, jak se ověřuje, že dokumentace není čitelná z webu,
a pasti z praxe (nginx a `.htaccess`, FTP nad webovou složkou, kolize routy
a složky).

## Snímky u issues

Zavřené issue, které má snímek `pred-`, musí mít i `po-`; jinak kontrola issues
(1.3.0) neprojde. Bez snímku „před" se nic nevyžaduje, aby backendové issues
nebyly obtěžované.

## Autorství issues

Issue ani komentář nesmí vzniknout přes aplikaci: GitHub pak u autora píše
jmenovku „with <aplikace>" a nejde to odstranit jinak než napsat text znovu.
Hlídá to kontrola issues od 1.2.0, `gh` má běžet s osobním tokenem.

## Složka .claude

Do gitu patří `.claude/launch.json` a `.claude/settings.json`, osobní
`.claude/settings.local.json` do `.gitignore`. Kontrola dokumentace hlásí, když
se osobní nastavení dostane do gitu nebo když některý `.claude/*.json` není
platný JSON.

## Standard issues

Jeden tvar pro chybu i funkci: sekce `## Problém` (nebo `## Cíl`),
`## Jak to poznat`, `## Hotovo, když`, `## Kde to žije`, `## Snímky`, v tomhle
pořadí a žádné jiné. Jeden checklist pod „Hotovo, když", tělo do 40 řádků.
Syrový nápad zadavatele bez nadpisů kontrolu neshodí, čeká na přepsání.
K tělu patří zařazení: štítek druhu (`bug`, `enhancement`, `documentation`,
právě jeden) a odpovědný. Druh musí sedět se sekcí zadání, aby si štítek
a text neprotiřečily; zavřené bez práce (`duplicate`, `wontfix`, `invalid`)
druh nepotřebuje.

Kontrola běží ve třech místech: při zakládání a změně issue (workflow
`tvar-issue.yml` nad událostí `issues`, označí štítkem a napíše, co chybí),
při pushi nad všemi issues repozitáře a lokálně při zakládání issue, kde
hook Claude Code (`hooky/tvar-issue.ps1`) špatné tělo rovnou zastaví. Hook se
zapíná jednou: v `~/.claude/settings.json` do `hooks.PreToolUse` s matcherem
`Bash|PowerShell`. Tělo se proto předává souborem, vložený text hook odmítne. Stejně se kontroluje
komentář: do pěti řádků, bez zmínky o nástroji a bez dlouhých pomlček. Workflow nad událostí běží jen pro issue
od vlastníka a spolupracovníků: repozitáře jsou zčásti veřejné a běh se zápisem
do issues nemá jít spustit zvenčí. Zavřené issue má checklist odškrtaný, komentáře mají
do pěti řádků, nikde se nepíše, čím se text psal, a snímky před a po leží
v `docs/snimky/<číslo>-<název>/`. Hlídá to `kontrola-issues.php`, která běží
v kontrolách na GitHubu při pushi. Denní běh zrušila 20. 9. 2026 N21,
workflow nad událostmi `issues` teď reaguje i na změnu štítků, odpovědného
a znovuotevření a přísnost bere ze skutečného stavu issue, ne z události.

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
- **Nasazení ale na kontroly čeká.** Od 23. 9. 2026 volá `deploy.yml` ve
  třech webech stejné kontroly jako svůj první job a nahrává až po jejich
  úspěchu (N32). Nouzové nasazení bez kontrol jde jen ručním spuštěním
  s volbou `bez_kontrol`.
- **Změna pravidla platí všude hned.** Před pushem do nastroje proto hook
  pustí `tests/kompatibilita.php`; když by projekt spadl, opraví se ve stejné
  dávce. Změna, kterou projekt splnit nemůže, dokud nevyjde nová verze
  kontrol (třeba jiný zdroj verze v generátoru), se pushuje v pořadí:
  nejdřív nastroje, hned potom projekt.
