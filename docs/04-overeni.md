# Ověření

Co testy dokazují a co ne. Kontroly jsou brána pro sedm projektů; když
přestanou fungovat, pozná se to jen tady.

## Regresní sada `tests/spust.php`

Každý případ postaví dočasný repozitář, pustí na něj kontrolu a porovná
návratový kód i kus výstupu. Případ vznikne dřív než oprava a musí proti
starému kódu padat (N32).

| Oblast | Případů | Co shodí |
|---|---|---|
| dokumentace | 18 | chybějící `docs/`, úprava `AGENTS.md` není kód, právě založený dokument umlčí doporučení, neexistující cesta, dávka s kódem bez dokumentu, obrázek nebo smazání místo dokumentu, neznámý základ rozsahu, doporučení k nasazení a testům |
| zavření issue | 13 | neodškrtnutý bod, červený nebo běžící běh CI, commit bez běhů, komentář bez odkazu na commit, snímek před bez po |
| snímky | 9 | odkaz na větev místo commitu, snímek, který v commitu není, soubor, který není obrázek, stejný soubor před i po |
| hook | 9 | zakládání bez štítku a odpovědného, vložené `--body`, holé zavření issue |
| tvar issue | 7 | chybějící štítek druhu, druh proti sekci, dva druhy naráz |
| migrace | 6 | přejmenování, změna a smazání hotové migrace, neexistující cesta |
| issues | 6 | gh vrací chybu nebo nesmysl, selhání `gh api`, issue bez štítku |
| spouštěč migrací | 4 | přejmenovaná migrace se nepustí znovu, změněná zastaví nasazení |
| README, stav | 2 | neexistující cesta, verze Expo aplikace z `app.json` |

`gh` v testech nahrazuje atrapa, takže se nic neposílá na GitHub.

```bash
php tests/spust.php              # všechny případy
php tests/spust.php snímky       # jen případy s "snímky" v názvu
```

## Kde to běží

| Místo | Co se přeskočí |
|---|---|
| CI na Linuxu | 10 případů jen pro Windows (hook Claude Code, cesta z Git Bashe) |
| CI na Windows | 4 případy spouštěče migrací, databázi tam nemá |
| pre-push hook v nastroje | 4 případy spouštěče, když není `NASTROJE_TEST_MYSQL` |

Přeskočený případ se vypíše, nepočítá se jako úspěch. Spouštěč migrací běží
na skutečné databázi: v CI MySQL z běhového prostředí, doma dočasná MariaDB
z Laragonu:

```bash
NASTROJE_TEST_MYSQL="mysql:host=127.0.0.1;port=33099|root|heslo" php tests/spust.php spouštěč
```

## Kompatibilita s projekty

`tests/kompatibilita.php` naklonuje výchozí větev každého repozitáře, který
společné kontroly volá (celou historii, snímky se ověřují v commitu), a pustí
na něj kontroly z pracovního stromu. Dokazuje, že změna pravidla žádný projekt
neshodí. Běží v pre-push hooku nastroje, trvá kolem minuty.

## Co ověřeno není

- **Věcná správnost textu.** Kontrola pozná, že dávka s kódem upravila
  dokument, ne že dokument popisuje, co se změnilo.
- **Pravdivost snímků.** Pozná, že je to obrázek z daného commitu, ne že
  ukazuje to, co issue tvrdí.
- **Hooky v `~/.git-hooks/`.** Nejsou v gitu a testy je nepouštějí; pravidla
  commitů se ověřila ručně na šesti zprávách (N33).
- **Workflow na GitHubu nad skutečnými událostmi.** Ověřeno ručně na
  nastroje #2: odebrání a vrácení štítku (N31, N33).
- **Okno trezoru.** `hooky/test-trezor.ps1` ho proklikává přes UI Automation,
  ale jen ručně, v CI neběží.
