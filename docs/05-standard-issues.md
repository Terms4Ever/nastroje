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

**Od 29. 9. 2026 stojí snímky v tabulce** (N39). Pod sebou nešlo poznat, co
je před a co po: popis obrázku GitHub neukazuje, takže onlinefakturuj #37
a #44 měly pod sebou šest obrázků bez jediného viditelného slova. Sekce
`## Snímky` má tabulku se záhlavím `| Co | Před | Po |`, každý řádek je jeden
pár: v prvním sloupci slovy, co ukazuje, vlevo stav před, vpravo stav po.
Buňka, pro kterou snímek není, zůstane prázdná (nová obrazovka nemá před).

```text
| Co | Před | Po |
|---|---|---|
| Seznam dobropisů | ![před: bez PDF](.../pred-seznam.png?raw=1) | ![po: s PDF a ISDOC](.../po-seznam.png?raw=1) |
| PDF dobropisu |  | ![po: PDF s odkazem na fakturu](.../po-pdf.png?raw=1) |
```

Ve sloupci Před smí být jen `pred-*.png`, ve sloupci Po jen `po-*.png`,
v buňce nejvýš jeden snímek. Snímek mimo tabulku neprojde a komentář snímky
nevkládá: pár by se rozpadl mezi tělo a komentáře. U zavřeného issue má každý
řádek se snímkem před i snímek po, pokud issue nenese `bez snímku po`.
Tabulka se vyplňuje průběžně: před při založení, po při zavření.

## Zavření s důkazem

```bash
php C:/laragon/www/nastroje/zavrit-issue.php <repozitář> <číslo> --komentar <soubor> --zavrit
```

Zavře, jen když sedí tvar a zařazení, checklist je odškrtaný (nebo komentář
říká, proč bod zůstal schválně, nebo je issue zavřené bez práce se štítkem
`wontfix`, `duplicate` či `invalid`), ke snímku před je snímek po, všechny běhy
commitu na výchozí větvi doběhly úspěšně a závěrečný komentář na ten commit
odkazuje. Bez `--zavrit` jen posoudí, s ním zapíše komentář a zavře (N33).

Za běhy commitu se berou běhy GitHub Actions z výchozí větve, všechny stránky.
Běhy spuštěné událostí issue (workflow Tvar issue) se nepočítají: GitHub je
věší na commit, který je zrovna hlavou výchozí větve, takže o zavíraném commitu
nic neříkají, a zrušený běh jiného issue dřív zavření zablokoval (N38).

**Checklist se odškrtává průběžně** (N39). Vedou-li k issue aspoň dva commity
s číslem issue v závorce, třeba `(#12)`, s odstupem přes pět minut, musí první
křížek přibýt dřív než poslední commit. Nástroj to ověří z historie úprav těla
na GitHubu. U onlinefakturuj se od 21. 9. u 22 z 24 issues odškrtl celý
checklist jedinou úpravou pár sekund před zavřením, i když práce šla ve dvou
commitech s hodinovým odstupem. Když body opravdu splnil až poslední commit
(předchozí jen připravil test), řekne to komentář slovy „až poslední commit".

## Práce po zavření

Commit s číslem issue v závorce, třeba `Faktura hlídá index (#40)`, na issue
pracuje. Přijde-li po jeho zavření, důkaz při zavření ho nepokryl: na
onlinefakturuj #40 přišel unikátní index v databázi čtyři hodiny po zavření.
Od 29. 9. 2026 takový commit zastaví kontrolu při pushi (N39). Práce patří
do znovu otevřeného issue (po novém zavření platí nové datum), nebo do
nového. Zmínka bez závorky („deník u #40 říká pravdu") práci nehlásí.

## Kde se to vynucuje

| Místo | Kdy | Co |
|---|---|---|
| `hooky/tvar-issue.ps1` | před zápisem na GitHub | tělo a komentář souborem, tvar, štítek a odpovědný při zakládání, holé zavření zastaví |
| `issue-tvar.yml` | do minuty po události | tvar, zařazení, snímky včetně tabulky, u zavřeného checklist se stejnými výjimkami (komentáře dostane souborem); označí `tvar nesedí` a napíše, co chybí |
| `kontrola-issues.php` | při každém pushi | všechna issues repozitáře včetně komentářů, snímků v commitu a tabulky snímků; commit, který pracuje na zavřeném issue |
| `zavrit-issue.php` | při zavírání | důkaz ze zeleného CI, tabulka snímků s párem v každém řádku, průběžné odškrtání |
| `~/.git-hooks/commit-msg` | při commitu | žádné „Closes #N", issue by se zavřelo samo |

Issues starší než den zavedení pravidla jen upozorní; každé pravidlo má
vlastní datum, nové obsah zastaví.
