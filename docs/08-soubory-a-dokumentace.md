# Soubory a dokumentace v repozitáři

Jak je uspořádaný repozitář Terms4Ever a které dokumenty má mít. Hlídá to
`kontrola-dokumentace.php`, zapíná se v `.readme-kontrola.json` klíčem
`"docs-kontrola": true`.

## Kořen a pokyny pro agenty

V kořeni jen `README.md`, `AGENTS.md`, `CLAUDE.md`, `LICENSE.md`
a `CHANGELOG.md` (N22). `AGENTS.md` nese pokyny pro agenty vlastní projektu,
`CLAUDE.md` má jediný řádek `@AGENTS.md`, aby se jeden text pod dvěma jmény
nemohl rozejít. Vzor je `sablony/agents.md`.

`.claude/launch.json` a `.claude/settings.json` patří do gitu, osobní
`.claude/settings.local.json` ne (N23). Názvy, které říkají, že je něco
dočasné (`-FINAL`, `-new`, `-old`, `kopie`, `.bak`, `soubor (1).png`),
neprojdou. `docs/` nese dokumenty; data do `docs/prilohy/`, snímky k issues
do `docs/snimky/`. Dokumentace se na web nenahrává.

## Které dokumenty má projekt mít

Dokument se zakládá, **jakmile téma v projektu je**, bez vyzvání (N34).
Stav nese stav, ne pravidla: co se nemění s každou dávkou, patří do
vlastního dokumentu.

| Dokument | Kdy | Co v něm je |
|---|---|---|
| `00-stav-projektu.md` | vždy | živý stav: co je hotové, co se dělá, co je dál, na co si dát pozor; přepisuje se |
| `01-postup-prace.md` | projekt má vlastní postup nad rámec globálních pokynů (hooky, skripty, pasti) | jak se v projektu pracuje a čeho se vyvarovat |
| `02-nasazeni.md` | projekt se někam nasazuje (web, obchod s aplikacemi, cizí server) | kam, čím, jaká tajemství, co se nenahrává, jak ověřit výsledek, nouzová cesta |
| `03-rozhodovaci-dennik.md` | vždy | co bylo kdy rozhodnuto a proč; nový záznam, starý se nepřepisuje |
| `04-overeni.md` | projekt má testy nebo kontroly | co testy dokazují a co ne, kde běží, co se přeskočí a proč |
| `05` a výš | téma, které se v kódu nepozná na první pohled: standard, integrace, schéma databáze, bezpečnost | jak to funguje a proč |

Čísla mají význam; když je v projektu číslo obsazené jiným tématem, vezme se
první volné. Každý dokument musí být v README v tabulce `## 📚 Dokumentace`.
Vzory pro 01, 02 a 04 jsou v `sablony/docs/`.

## Co kontrola hlídá

| Pravidlo | Chyba nebo doporučení |
|---|---|
| `00-stav-projektu.md` a `03-rozhodovaci-dennik.md` existují | chyba |
| generovaný blok ve stavu odpovídá skutečnosti | chyba |
| dávka, která sáhla na kód, přidala nebo upravila dokument `.md` v `docs/` | chyba |
| jen krátké pomlčky | chyba nebo upozornění podle `docs-pomlcky` |
| uspořádání kořene, `AGENTS.md`, `CLAUDE.md`, `.claude/`, názvy | chyba |
| projekt se nasazuje (`.github/workflows/deploy.yml`, `deploy/`) a nemá dokument o nasazení | doporučení |
| projekt má testy (`tests/`, `phpunit.xml`, skript `test` v `package.json`) a nemá dokument o ověření | doporučení |

Doporučení se vypíše při každém pushi a nezastaví ho: co přesně do dokumentu
patří, kontrola nepozná, a zastavit všechny projekty naráz by nepomohlo.
Dokument podle pokynů založí agent, který v projektu pracuje.

Kontrola pozná, že dávka s kódem upravila dokument, ne že dokument popisuje,
co se změnilo. Levná obejití (obrázek do `docs/snimky/`, smazání souboru)
zavírá, věcnou správnost nezaručí.
