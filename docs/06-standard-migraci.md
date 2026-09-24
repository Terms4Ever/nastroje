# Standard migrací

Jak se mění schéma databáze v projektech s vlastním PHP (onlinefakturuj,
vyridimestavbu) a kde se to hlídá. Projekty s migračním nástrojem frameworku
(Laravel) se nezapojují, pravidla si nese framework (N17).

## Migrace

Jedna změna schématu je jeden soubor `db/migrace/rrrr-mm-dd-popis.sql`
(volitelně `rrrr-mm-dd-hhmm-popis.sql`), v UTF-8, začíná komentářem, co mění,
a má aspoň jeden příkaz zakončený středníkem. Přehled `db/prehled.md` se
generuje, rukou se needituje:

```bash
php C:/laragon/www/nastroje/prehled-migraci.php <repozitář> --zapsat
```

**Hotová migrace se nemění, nemaže ani nepřejmenovává.** Oprava je nová
migrace. Přejmenování hlídá kontrola od 1.1.0; git ho hlásí jako `R100`
a dřív prošlo (N32).

## Nasazení migrací

Na produkci migrace pouští nasazení, ne člověk (N18):

1. Nasazení vytvoří zamčené kopie `nazev.sql.php` s prvním řádkem
   `<?php exit; ?>`. Soubor `.sql` by web poslal jako text, `.php` se vykoná
   a nevypíše nic. Mimo web je nahrát nejde, FTP účet tam nesmí zapisovat.
2. Nahraje je do `db-migrace/` na serveru.
3. Zavolá `/spustit-migrace` s tokenem `MIGRACE_TOKEN` z tajemství GitHubu.
   Adresa se schválně jmenuje jinak než složka: u stejného jména odpoví nginx
   přesměrováním, POST se změní na GET a token se ztratí.

Další akce téže adresy: `/spustit-migrace/stav` (verze databáze a co čeká)
a `/spustit-migrace/oznacit` (zapíše migraci jako hotovou bez spuštění, jen
při zavádění standardu do projektu, kde změna na produkci dávno běží).

## Spouštěč `sablony/migrace.php`

Kopie leží v `app/migrace.php` (vyridimestavbu) a `src/Migrace.php`
(onlinefakturuj) a musí být shodná se vzorem.

- Spuštěné migrace eviduje tabulka `migrace` (soubor, otisk obsahu, čas).
- Zámek `GET_LOCK` brání dvěma nasazením pustit tutéž migraci dvakrát.
- **Přejmenovaná hotová migrace se znovu nepustí**: spouštěč ji pozná podle
  otisku, který zná pod starým jménem. Dřív by ji pustil znovu; `CREATE TABLE`
  by shodil nasazení a `INSERT` zdvojil data (N32, ověřeno na jednorázové
  databázi).
- **Změněná hotová migrace nasazení zastaví.** Otisk se ukládal od začátku,
  ale nikdy neporovnával. Zastavování se zapnulo až poté, co výpisy nasazení
  obou webů ukázaly, že starý nesoulad na produkci není.

## Kde se to hlídá

| Místo | Co |
|---|---|
| `kontrola-migraci.php` | tvar souborů, přehled, a s rozsahem commitů: žádná změna, smazání ani přejmenování hotové migrace, dávka se změnou schématu mimo migrace musí migraci přidat |
| spouštěč na produkci | přejmenovaná nebo změněná hotová migrace nasazení zastaví |
| `tests/spust.php` | 6 případů kontroly a 4 případy spouštěče nad skutečnou databází |

Kontrola se zapíná v `.readme-kontrola.json` klíčem `"migrace-kontrola": true`.
