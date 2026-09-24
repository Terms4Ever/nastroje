# Ověření

<!-- Vzor z nastroje (sablony/docs/). Vyplň podle skutečnosti projektu,
     nevyplněné sekce smaž. Neprovedený test nikdy nevydávej za úspěšný. -->

Co testy a kontroly v projektu dokazují a co ne.

## Testy

| Sada | Příkaz | Co pokrývá |
|---|---|---|
| jednotkové | `npm test`, `php tests/neco.php` | výpočty, převody, pravidla |

Počty ber ze skutečného běhu, ne z odhadu.

## Kde běží

| Místo | Co se přeskočí a proč |
|---|---|
| CI na GitHubu | testy, které potřebují databázi nebo zařízení |
| pre-push hook | |
| ručně | end-to-end, snímky, mobilní zařízení |

## Co ověřeno není

Co musí posoudit člověk nebo agent: vzhled, texty, chování na zařízení,
produkční data. Přeskočený nebo ruční test se tu jmenuje, aby ho nikdo
nepočítal za automatický.
