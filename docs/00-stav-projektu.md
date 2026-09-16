# Stav projektu

Živý stav. Co je hotové, co se dělá, co je dál. Přepisuje se, nepřidává.

<!-- generovano nastroji, needitovat -->
```
hlavní větev:     main
```
<!-- konec generovaneho bloku -->

## Co je hotové

- `kontrola-readme.php` ve verzi 2.0.0, čisté PHP bez závislostí
- `kontrola-dokumentace.php` ve verzi 1.4.1, zapíná se přihlášením,
  složku `docs/` prochází rekurzivně včetně podsložek. Vadné nastavení
  a neplatné UTF-8 hlásí jako chybu, ne jako důvod k přeskočení. Když zná
  pushovaný commit, ověří, že pracovní strom v čtených cestách sedí
- `stav-projektu.php`, generátor bloku se skutečnými čísly. Hlavní větev
  bere z repozitáře, ne z větve, na které se zrovna stojí
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

## Na co si dát pozor

- **Kontrola na GitHubu push nezastaví.** Větve nemají ochranu, takže běh
  chybu jen nahlásí po faktu. Zastavit push umí jen pre-push hook. Ochranu
  větví zadavatel 16. 9. 2026 odmítl, pull requesty schvalovat nechce (N16),
  takže to tak zůstane.
