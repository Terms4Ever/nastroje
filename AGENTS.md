# nastroje - pokyny pro agenty

Sdílené kontroly pro repozitáře Terms4Ever: README, dokumentace, migrace
databáze a tvar issues. Pouští je pre-push hook lokálně a workflow `Kontroly`
na GitHubu, takže pravidla žijí na jednom místě a mění se tady.

Obecná pravidla (commity, README, dokumentace, issues, migrace) jsou
v `~/.claude/CLAUDE.md` a nepřepisují se sem.

## Stack a struktura

PHP 8.3 bez jediné závislosti, schválně: běhové prostředí `ubuntu-24.04` má PHP
předinstalované, takže kontrola na GitHubu nepotřebuje instalační krok.

- `kontrola-readme.php`, `kontrola-dokumentace.php`, `kontrola-migraci.php`,
  `kontrola-issues.php` - samostatné kontroly, každá vrací kód 1 při nálezu
- `kontrola-tvaru-issue.php` - jedno tělo issue, volá ji hook i workflow
- `src/tvar-issue.php` - pravidla tvaru issue na jednom místě
- `hooky/tvar-issue.ps1` - hook Claude Code, zastaví špatné issue před založením
- `stav-projektu.php`, `prehled-migraci.php` - generátory bloků do dokumentů
- `tests/spust.php` - regresní testy kontrol, `tests/kompatibilita.php` -
  kontroly z pracovního stromu proti všem projektům
- `sablony/` - vzory k opsání: README, migrace, issue, agents
- `.github/workflows/readme.yml` a `issue-tvar.yml` - volají je ostatní repozitáře

## Doménová pravidla

- **Kontrola nikdy nic nemění.** Jen čte a hlásí. Zapisují jen generátory, a to
  výhradně s přepínačem `--zapsat`.
- **Nález nesmí projít tiše.** Vadné nastavení, nečitelný soubor nebo neznámý
  rozsah commitů se hlásí nahlas; mlčení by znamenalo vypnutou bránu.
- **Kontroluje se to, co se pushuje**, ne to, co leží na disku. Když se pracovní
  strom liší od pushovaného commitu, kontrola to řekne a skončí.
- **Zpětně se netrestá.** Nové pravidlo platí od data zavedení, starší nálezy
  jen upozorní.
- **Pravidlo bez testu neexistuje.** Každá kontrola má případ, který ji shodí,
  jinak se nepozná, že přestala fungovat.

## Brány před commitem

Kontroly se pouští samy na sebe, proto po každé změně:

```bash
php -l <zmeneny-soubor>.php
php tests/spust.php
php kontrola-readme.php .
php kontrola-dokumentace.php .
php kontrola-issues.php .
```

Pre-push hook (`~/.git-hooks/pre-push`) pouští totéž znovu a push zastaví.
Obejít jde jen vědomě přes `git push --no-verify`.

## Nasazení

Nikam se nenasazuje. Repozitář je zdroj pravidel: ostatní projekty na něj
odkazují větví `@main` ve svých workflow a globální hook si ho čte z disku.
Změna pravidla je proto okamžitě všude, což je záměr i riziko. Pre-push hook
proto před každým pushem pustí `tests/kompatibilita.php` a změnu, kvůli které
by některý projekt spadl, nepustí. Projekt se opraví ve stejné dávce; když
ho opravit nejde, dokud nevyjde nová verze kontrol, pushuje se nejdřív
nastroje a hned potom projekt.

Nové pravidlo nebo oprava kontroly začíná testem: případ, který před opravou
padá a po ní projde.

## Jak se domlouváme

Česky, krátké pomlčky. Nové pravidlo nebo jeho zpřísnění je rozhodnutí
zadavatele, ne agenta; zapisuje se do `docs/03-rozhodovaci-dennik.md` pod
značkou N a do stavu projektu. Hotová práce se hlásí s důkazem: výstup
kontroly, ne tvrzení, že prošla.
