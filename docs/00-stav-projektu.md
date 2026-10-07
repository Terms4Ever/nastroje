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
| `kontrola-readme.php` | 2.2.0 | README a místní označení osobní sady |
| `kontrola-pravidel.php`, `src/sada-pravidel.php` | 1 | výběr sady, README, AGENTS, workflow, online topic (N35), názvy kontrol na GitHubu (N36) |
| `kontrola-dokumentace.php` | 1.7.2 | [08 soubory a dokumentace](08-soubory-a-dokumentace.md) |
| `kontrola-migraci.php` | 1.1.0 | [06 standard migrací](06-standard-migraci.md) |
| `kontrola-issues.php` | 1.12.0 | [05 standard issues](05-standard-issues.md) |
| `kontrola-tvaru-issue.php`, `hooky/tvar-issue.ps1` | - | [05 standard issues](05-standard-issues.md) |
| `zavrit-issue.php` | - | [05 standard issues](05-standard-issues.md) |
| `stav-projektu.php`, `prehled-migraci.php` | - | generátory bloků |
| `sablony/migrace.php` | - | [06 standard migrací](06-standard-migraci.md) |
| `hooky/tajemstvi.ps1`, `hooky/trezor-spravce.ps1` | - | [07 trezor hesel](07-trezor-hesel.md) |
| `tests/spust.php`, `tests/kompatibilita.php` | - | [04 ověření](04-overeni.md) |
| `monitoring/kontrola.php` | 1 | pouze čtyři schválené weby, [09 monitoring](09-monitoring-webu.md), N40 |

Kontroly končí chybou i tehdy, když nemohly proběhnout (chybí `docs/`,
neexistuje cesta, gh nepřečte issues, neznámý základ rozsahu); dřív to byla
zelená (N32). Kontrola dokumentace od 1.7.0 doporučí dokument k nasazení
nebo testům, když ho projekt nemá (N34). Od N39 (29. 9. 2026) stojí snímky
v issues v tabulce `| Co | Před | Po |`, nástroj na zavírání hlídá průběžné
odškrtání checklistu a commit, který pracuje na zavřeném issue, kontrolu při
pushi zastaví. Starší obsah jen upozorní. Šablony issue ve všech projektech
kromě Igrisu mají sekci Snímky podle `sablony/issue-ukol.md` a starší snímky
v jejich issues stojí v tabulce, i ty, které dřív ležely jen v komentáři.
Igris má šablonu zamčenou otiskem ve vlastních testech (R245), změní ji jeho
agent.

## Zapojené repozitáře

| Repozitář | Nasazení čeká na kontroly | Poznámka |
|---|---|---|
| `onlinefakturuj.cz` | ano | testy v CI (14 z 15), migrace |
| `vyridimestavbu.cz` | ano | migrace |
| `tomas.saroun.me` | ano | statický web |
| `zvedavka.cz` | ne | server stahuje main sám; do monitoringu patří jen úvodní stránka |
| `steelset` | - | mobilní aplikace, Jest testy v CI |
| `LabProtocol` | - | mobilní aplikace |
| `trenwise.cz` | - | vývoj pozastaven, FTP zatím není |
| `Project-Igris` | - | vlastní hooky a testy, sdílená kontrola navíc |
| `nastroje` | - | kontroluje sám sebe, testy na Linuxu i Windows |

`nastroje-prace` (osobní nadstavba pro pracovní projekty) společné kontroly
nevolá, má je připnuté zámkem na konkrétní commit.

Tabulka je přehled. Výběr pravidel vždy určuje `.pravidla.json` v konkrétním
projektu. Těchto devět projektů vybírá `nastroje`, samostatné nastroje-prace
vybírají pracovní sadu. Soukromé pracovní aplikace se v tomto úkolu nezapojují.

## Co se dělá

Issue #4: samostatný denní monitoring čtyř schválených webů (N40).
29 nových testů a kompatibilita všech osmi aplikačních projektů prošly.
Celá místní sada: 186 případů, žádná chyba, čtyři databázové případy přeskočeny.
Čeká skutečný běh na GitHubu. Sdílené kontroly se nemění.

## Co je dál

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
- **Rozdělaná změna platí hned i v jiných relacích.** Hook `tvar-issue.ps1`,
  `zavrit-issue.php` i pre-push hook se pouštějí z disku, ne z GitHubu.
  29. 9. 2026 narazil agent zavírající onlinefakturuj #48 na ještě
  necommitnuté pravidlo N39; díky tomu se našla chyba v `sjednotRadky()`,
  ale jinak by ho zastavila polovičatá změna. Změnu pravidla proto dotáhnout
  do commitu hned, nebo ji rozdělat v samostatném klonu.
- **Hooky v `~/.git-hooks/` nejsou v gitu.** Co se v nich změní, zmizí při
  přeinstalaci počítače; popis je v [02 nasazení](02-nasazeni.md).
