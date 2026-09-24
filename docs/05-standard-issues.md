# Standard issues

Jak vypadá issue ve všech repozitářích Terms4Ever a kde se to hlídá. Znění
pravidel pro agenty je v `~/.claude/CLAUDE.md`; tady je, jak je nastroje
vynucují a proč vypadají, jak vypadají.

## Tělo

Pevné sekce v pevném pořadí: `## Problém` (nebo `## Cíl`), `## Jak to poznat`,
`## Hotovo, když`, `## Kde to žije`, `## Snímky`. Povinné jsou první
a „Hotovo, když". Jeden checklist, celý pod „Hotovo, když", tělo do 40 řádků
(N19, N20). Dřív měly issues přes dvacet různých nadpisů a dva seznamy
znamenaly dvě pravdy o tom, kdy je hotovo.

**Syrový nápad zadavatele** (tělo bez jediného nadpisu) chyba není. Kontrola
ho vypíše jako „k přepsání" a přepsat ho do tvaru je práce agenta, hned jak na
issue sáhne. Agent sám syrové issue založit nesmí.

## Zařazení

Právě jeden štítek druhu (`bug`, `enhancement`, `documentation`) a odpovědný
(N31). Druh musí sedět se sekcí: `## Problém` je `bug`, `## Cíl` je
`enhancement`. Doménové štítky se přidávají navíc. Výjimku má issue zavřené
bez práce (`duplicate`, `wontfix`, `invalid`).

## Komentáře a autorství

Komentář do pěti řádků, řádek jen s obrázkem se nepočítá. Nikde se nepíše,
čím se text psal. Issue ani komentář nesmí vzniknout přes aplikaci: GitHub
pak u autora píše „with <aplikace>" a úprava textu to nesmaže (N24).

## Snímky

Leží v `docs/snimky/<číslo>-<název>/` jako `pred-neco.png` a `po-neco.png`
a vkládají se jako obrázek, ne jako odkaz. Od 23. 9. 2026 odkazují na otisk
commitu (N33):

```text
![popis](https://github.com/Terms4Ever/<repo>/blob/<otisk>/docs/snimky/12-neco/po-neco.png?raw=1)
```

Otisk dá `git log -1 --format=%H -- docs/snimky/12-neco/`. Odkaz na větev
`main` se rozbije, když se soubor přesune, a neřekne, kterou verzi snímek
dokládá. Kontrola ověří, že v odkazovaném commitu snímek je, že je to obrázek
(PNG, JPEG, WebP) a že před a po nejsou tentýž soubor. Starší obsah jen
upozorní; všech 39 starších odkazů bylo převedeno.

Kde je snímek před, musí být i po. Když ho nemá kdo pořídit (stav jde vidět
jen na zařízení), dostane issue štítek `bez snímku po`.

## Zavření s důkazem

```bash
php C:/laragon/www/nastroje/zavrit-issue.php <repozitář> <číslo> --komentar <soubor> --zavrit
```

Zavře, jen když sedí tvar a zařazení, checklist je odškrtaný (nebo komentář
říká, proč bod zůstal schválně), ke snímku před je snímek po, všechny běhy
commitu na výchozí větvi doběhly úspěšně a závěrečný komentář na ten commit
odkazuje. Bez `--zavrit` jen posoudí, s ním zapíše komentář a zavře (N33).

## Kde se to vynucuje

| Místo | Kdy | Co |
|---|---|---|
| `hooky/tvar-issue.ps1` | před zápisem na GitHub | tělo a komentář souborem, tvar, štítek a odpovědný při zakládání, holé zavření zastaví |
| `issue-tvar.yml` | do minuty po události | tvar, zařazení, snímky; označí `tvar nesedí` a napíše, co chybí |
| `kontrola-issues.php` | při každém pushi | všechna issues repozitáře včetně komentářů a snímků v commitu |
| `zavrit-issue.php` | při zavírání | důkaz ze zeleného CI |
| `~/.git-hooks/commit-msg` | při commitu | žádné „Closes #N", issue by se zavřelo samo |

Issues starší než den zavedení pravidla jen upozorní; každé pravidlo má
vlastní datum, nové obsah zastaví.
