# Postup práce

Jak se v nastroje pracuje. Obecná pravidla (commity, issues, dokumentace)
jsou v `~/.claude/CLAUDE.md`; tady je, co je vlastní repozitáři, který ta
pravidla vynucuje ve všech ostatních.

## Změna pravidla nebo kontroly

1. **Rozhodnutí je zadavatele.** Nové pravidlo nebo zpřísnění se předloží
   s důvodem a dopadem, zavádí se až po souhlasu. Oprava chyby v kontrole
   (kontrola tiše propouští, co zakazuje) souhlas nepotřebuje.
2. **Nejdřív test, pak kód.** Do `tests/spust.php` přibude případ, který
   dnešní kontrolu shodí. Pustí se a musí padat; teprve pak se opravuje.
   Případ, který nikdy nepadal, nic nedokazuje.
3. **Oprava**, dokud neprojde celá sada: `php tests/spust.php`.
4. **Kompatibilita:** `php tests/kompatibilita.php` pustí nové kontroly proti
   výchozí větvi všech zapojených projektů. Projekt, který by spadl, se
   opraví ve stejné dávce.
5. **Dokumentace:** záznam N v deníku (proč), řádek ve stavu (co platí)
   a úprava tematického dokumentu (jak to funguje).
6. **Commit a push.** Pre-push hook pustí kontroly README a dokumentace,
   testy a kompatibilitu znovu. Po pushi se ověří běh na GitHubu, a to na
   Linuxu i na Windows.

**Pořadí pushů**, když projekt novou verzi splnit nemůže, dokud nevyjde
(třeba jiný zdroj verze v generátoru): nejdřív nastroje, hned potom projekt.
Hook kompatibility se v takové dávce nasazuje až po obou pushích.

## Issues v nastroje

Stejný tvar jako všude ([05 standard issues](05-standard-issues.md)).
Zakládá se přes `gh issue create --body-file`, se štítkem druhu
a odpovědným; zavírá se přes `zavrit-issue.php`.

## Pasti z praxe

Kontroly se píšou pro Windows (hook Claude Code, pre-push) i pro Linux
(GitHub). Většina chyb vznikla na rozhraní:

- **`escapeshellarg` na Windows** mění `%`, `!` a `"` na mezeru. Z
  `--format=%H` se tak stane `--format= H`. Příkazy s takovými znaky se
  spouštějí polem přes `proc_open`, bez shellu.
- **Stříška v `cmd.exe`** je únikový znak; `^{commit}` musí být v uvozovkách.
- **Cesta z Git Bashe** (`/c/laragon/...`) je pro PHP na Windows neexistující
  složka. Kontroly ji převádějí na `C:/laragon/...` funkcí `proGit()`.
- **PowerShell 5.1 bez BOM** čte skript v systémovém kódování a čeština se
  rozsype. Skripty `.ps1` se ukládají v UTF-8 s BOM.
- **PowerShell nerozlišuje velikost písmen**: `$cil` přepíše `$Cil`. Dvakrát
  kvůli tomu spadlo okno trezoru.
- **`%~dp0` v souboru `.cmd`** ukazuje do aktuální složky, ne ke skriptu, když
  ho `cmd` najde přes PATH a jméno je v uvozovkách. Atrapy v testech proto
  nesou absolutní cestu.
- **Přesměrovaný stderr** nativního programu ve Windows PowerShellu 5.1 je
  v UTF-16LE a při `ErrorActionPreference = Stop` shodí skript dřív, než
  vrátí svůj kód. Hook proto píše stderr do souboru.
