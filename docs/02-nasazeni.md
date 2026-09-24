# Nasazení: jak se pravidla dostanou do projektů

Nastroje se nikam nenahrávají. Pravidla se k projektům dostávají čtyřmi
cestami a každá hlídá něco jiného: GitHub po pushi, pre-push hook před ním,
hook Claude Code ještě před zápisem na GitHub a nasazení webů na konci.

## 1. Sdílená workflow na GitHubu

Projekty je volají větví `@main`, takže změna pravidla platí hned všude.

| Workflow | Kdo ho volá | Co dělá | Práva |
|---|---|---|---|
| `readme.yml` | `kontroly.yml` v projektu, u webů i `deploy.yml` | README, dokumentace, migrace a issues; rozsah změn dopočítá i u pull requestu a nové větve | `contents: read`, `issues: read` |
| `issue-tvar.yml` | `tvar-issue.yml` v projektu | nad událostí issue ověří tvar, zařazení a snímky, označí štítkem `tvar nesedí` a napíše, co chybí | `issues: write` |

Volající `tvar-issue.yml` reaguje na `opened, edited, closed, reopened,
labeled, unlabeled, assigned, unassigned` a jen u autorů se vztahem
k repozitáři: repozitáře jsou zčásti veřejné a běh se zápisem do issues nemá
jít spustit zvenčí.

**Akce jsou připnuté na otisk commitu** (N33), ne na značku, kterou jde
přesunout na jiný kód. Aktualizace se dělá vědomě:

```bash
gh api repos/actions/checkout/commits/v7.0.1 --jq .sha
```

Otisk se zapíše do `uses:` a za `#` vydání, ke kterému patří. Stažený kód si
nenechává přihlašovací údaje (`persist-credentials: false`).

## 2. Nasazení webů čeká na kontroly

`deploy.yml` u onlinefakturuj, vyridimestavbu a tomas.saroun.me volá kontroly
jako první job a nahrává přes `needs` až po jejich úspěchu (N32). Druhý push
počká, až první doběhne (`cancel-in-progress: false`). Nouzově jde nasadit
bez kontrol jen ručně:

```bash
gh workflow run deploy.yml -f bez_kontrol=true
```

A to jen tehdy, když kontrola padá na něčem, co web nerozbije.

## 3. Lokální hooky v `~/.git-hooks/`

`core.hooksPath` je globální a míří do `~/.git-hooks/`. Hooky tam **nejsou
v gitu**, takže se nedají zkontrolovat ani obnovit odsud.

| Hook | Co dělá |
|---|---|
| `commit-msg` | autor Terms4Ever, česká věta bez prefixu, žádný `Co-Authored-By`, žádné „Closes #N" |
| `pre-push` | znovu pravidla commitů u každého pushovaného commitu, pak README, dokumentace a migrace z `C:/laragon/www/nastroje`; u nastroje navíc testy a kompatibilitu |
| ostatní | jen předávají řízení místnímu hooku repozitáře, jinak by ho globální cesta vypnula |

Když chybí PHP, skript kontroly nebo pravidla commitů, push se zastaví
(od N32; dřív hook jen vypsal poznámku). Vědomá výjimka je `--no-verify`.

Projekty s vlastní cestou hooků (Igris, nastroje-prace) globální hooky
nespouštějí; mají vlastní, které kontroly volají samy.

## 4. Hook Claude Code

`hooky/tvar-issue.ps1` je zapsaný v `~/.claude/settings.json` jako
`PreToolUse` s matcherem `Bash|PowerShell`. Zastaví zakládání issue bez
souborového těla, štítku a odpovědného, špatné tělo nebo komentář a holé
zavírání issue. Volání musí stát na začátku příkazu, za rourou, středníkem
nebo uvozovkou; text, který ho jen zmiňuje, nezastaví.

## Zapojení nového projektu

1. `.pravidla.json` podle `sablony/pravidla.json`, jediná sada `nastroje`.
   Úvod README má odznak ze vzoru a AGENTS deklaraci stejné sady se zdrojem.
2. `.readme-kontrola.json` s profilem `plny` a zapnutými kontrolami, které
   projekt splní (`docs-kontrola`, případně `migrace-kontrola`, `provoz`).
3. `kontroly.yml` a `tvar-issue.yml` opsané z vyridimestavbu, u webu
   `deploy.yml` s jobem `kontroly`. Primární workflow se jmenuje `Kontroly`,
   soubor může mít dosavadní jméno (Igris `kontrola.yml`). Každý job, který volá
   `readme.yml`, se jmenuje `Pravidla nastroje`, i v nasazení (N36).
4. GitHub topic `pravidla-nastroje`, ostatní topics zachovat. Topic pracovní sady sem nepatří.
5. `.github/ISSUE_TEMPLATE/ukol.md` a `config.yml` z `sablony/`.
6. Dokumenty podle [08 soubory a dokumentace](08-soubory-a-dokumentace.md).
7. `php kontrola-pravidel.php <projekt> --online` a `php tests/kompatibilita.php`
   v nastroje: projekt se objeví v seznamu
   a musí projít.

Celé zadání pro agenta, který přebírá web z FTP, je v
`sablony/zadani-novy-projekt.md`.

## Jedna primární sada a její závislosti

Zdroj výběru je verzovaný `.pravidla.json`, nikoli odhad podle jména projektu.
README, začátek AGENTS, název společné kontroly a GitHub topic tento výběr
ukazují. Na GitHubu se společná kontrola jmenuje `Pravidla nastroje`, takže je
sada vidět v seznamu kontrol u každého commitu (N36).
Kontrola vyžaduje jejich shodu a skutečně zapojený kontrolní job.
Osobní projekty stále volají sdílené workflow větví `@main`.

Pracovní projekty vybírají `nastroje-prace` a používají její postup napojení
s přesnou kopií kontrol. `upstream.lock.json` uvnitř pracovní sady připíná
knihovnu nastroje; neznamená přihlášení k druhé primární sadě.
Přímé osobní workflow v pracovní sadě kontrola odmítne.

Online kontrola čte skutečné GitHub topics a při nedostupném API končí chybou.
Samotná změna topicu workflow nespustí: ověří se při dalším běhu kontroly
nebo ručně příkazem s `--online`. Žádný denní plán se nezavádí.
Lokální kontrola README ověřuje označení a zapojení osobních projektů;
téma na GitHubu ověřuje samostatný online příkaz.

Kontrola rozpoznává běžné dvoumezerové YAML šablony. Komentáře, blokové
skaláry a text příkazu uvnitř echo nepočítá jako zapojení. Nejde o úplný
interpret GitHub Actions ani ochranu proti vlastníkovi, který kontrolu odstraní.
Výjimky v pravidlech a schvalování se tímto úkolem nemění.
