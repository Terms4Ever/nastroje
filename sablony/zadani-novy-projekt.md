# Zadání: dostat FTP projekt na GitHub

Vzor zadání pro agenta, který přebírá web běžící na FTP hostingu a dává ho do
repozitáře Terms4Ever se vším, co k tomu patří. Kdo zadání píše, vyplní hranaté
závorky a zbytek nechá být.

---

## Co dostaneš

- **Projekt:** [název], běží na [adresa], hosting [Webglobe / jiný].
- **Přístup k FTP:** leží v trezoru pod cílem `[nazev-cile]`. Pracuj s ním přes
  `hooky/tajemstvi.ps1` (`seznam`, `ftp <cíl> :: "ls /web"`), heslo neuvidíš
  a nepotřebuješ. Když cíl v trezoru chybí, napiš mi, ať ho uložím; o heslo
  v chatu nežádej.
- **Databáze:** [je / není]. Když je, dostaneš i přístup k phpMyAdminu.
- **Repozitář:** `Terms4Ever/[jmeno]`, [soukromý / veřejný].

## Postup

**1. Stáhni web z FTP** do prázdné složky mimo `C:\laragon\www`, ať se ti
nemíchá s rozdělanou prací. Pak projdi, co tam je, a **nic necommituj dřív,
než to uvidíš**. Do gitu nepatří:

- soubory s hesly (`config.local.php`, `.env`, cokoli s heslem k databázi
  nebo SMTP) - patří do `.gitignore` a na server se nahrávají ručně
- `vendor/`, `node_modules/`, nahrané soubory uživatelů, zálohy databáze
- jednorázové skripty, Adminer, staré kopie souborů (`*-old`, `*-zaloha`)

Co najdeš a nepoznáš, napiš mi a zeptej se, než to smažeš.

**2. Založ repozitář** a první commit udělej ze staženého stavu, ať je vidět,
odkud se začínalo. Nadpis commitu je česká věta o novém stavu, pravidla jsou
v `~/.claude/CLAUDE.md`.

**3. Dej do kořene tyhle soubory** a nic dalšího:

| soubor | co v něm je |
|---|---|
| `README.md` | podle vzoru `sablony/readme-plny.md`, hlavička s řádkem `🌐 **Provoz:**` |
| `AGENTS.md` | podle vzoru `sablony/agents.md`, jen to, co je vlastní projektu |
| `CLAUDE.md` | jediný řádek `@AGENTS.md` |
| `.readme-kontrola.json` | `profil`, `provoz`, `cesty-bez-kontroly`, `docs-kontrola`, `docs-pomlcky`, `docs-vymahat-aktualizaci` |
| `.gitignore` | soubory s hesly, `vendor/`, `.claude/settings.local.json` |

**4. Dokumentace** do `docs/`: `00-stav-projektu.md` (živý stav, generovaný blok
přes `php stav-projektu.php . --zapsat`) a `03-rozhodovaci-dennik.md` (první
záznam je právě tenhle přesun na GitHub). Obojí musí být vypsané v README
v sekci `## 📚 Dokumentace`.

**5. Workflow** do `.github/workflows/`:

- `kontroly.yml` - volá `Terms4Ever/nastroje/.github/workflows/readme.yml@main`,
  práva `contents: read` a `issues: read`
- `tvar-issue.yml` - volá `issue-tvar.yml@main` nad událostí `issues`, práva
  `issues: write`, jen pro autory se vztahem k repozitáři
- `deploy.yml` - kontrola syntaxe PHP, pak FTPS nahrání

K tomu `.github/ISSUE_TEMPLATE/ukol.md` (vzor `sablony/issue-ukol.md`)
a `config.yml` s `blank_issues_enabled: false`. Opiš je z vyridimestavbu.cz,
je to nejmenší projekt s kompletní sadou.

**6. Nasazení nenahrává na web:** `docs/**`, `**/*.md`, `.github/**`, `tests/**`,
`db/**`, `vendor/**`, soubory s hesly, `.readme-kontrola.json` ani router pro
lokální server. Co se jednou nahraje, zůstane na serveru, i když to pak
z nasazení vyřadíš.

**7. Tajemství** (`FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`, případně
`MIGRACE_TOKEN`) nastavím v GitHubu já. Napiš mi, která přesně potřebuješ a jak
se mají jmenovat; ty je nikde nevypisuj. Pro práci z počítače používej trezor
(`hooky/tajemstvi.ps1`), ne údaje v chatu.

**8. Když má projekt databázi**, zaveď migrace podle standardu z nastroje:
`db/migrace/` s čistými SQL soubory, spouštěč podle `sablony/migrace.php`,
routa `/spustit-migrace` (schválně jiný název než složka, jinak ji server sebere
dřív než PHP), zamčené kopie `.sql.php` a krok v nasazení, který migrace spustí.
Zapni `"migrace-kontrola": true`.

**9. Náhled pro vývoj**: `.claude/launch.json` s příkazem, kterým se projekt
pustí lokálně. U čistého PHP bez frameworku přidej `dev-server.php` jako router
pro vestavěný server, jinak vrací hezké adresy 404.

## Ověření, než řekneš hotovo

```bash
php /c/laragon/www/nastroje/kontrola-readme.php .
php /c/laragon/www/nastroje/kontrola-dokumentace.php .
php /c/laragon/www/nastroje/kontrola-issues.php .
php /c/laragon/www/nastroje/kontrola-migraci.php .   # jen s databází
```

Po pushi:

- běh **Kontroly** na GitHubu je zelený (ověř, nepředpokládej)
- běh **Deploy** prošel a web dál funguje: titulní stránka, formulář,
  přihlášení do administrace
- `curl -s -o /dev/null -w '%{http_code}' https://[adresa]/docs/00-stav-projektu.md`
  vrací 404 nebo 403, ne 200
- `curl` na `/README.md` a na soubor s hesly totéž

## Pasti z praxe

- **`.htaccess` na hostingu s nginxem statické soubory neblokuje.** Zjistili
  jsme to třemi pokusy. Jediná cesta je soubor na server nenahrát, a co tam už
  je, smazat.
- **FTP účet nesmí zapisovat nad webovou složku** (`553 Permission denied`),
  takže migrace leží uvnitř webu jako zamčené `.sql.php`.
- **Routa nesmí mít stejný název jako složka na disku**: server pošle
  přesměrování a PHP se ke slovu nedostane. Přesměrování navíc překlopí POST
  na GET a token z těla se ztratí.
- **Kontrola čte pushovaný commit, ne pracovní strom.** Rozdělaná práce
  v jiném klonu ti kontrolu shodí, i když u tebe prochází.
- **Issue zakládej přes `--body-file`**, ne vloženým `--body`; hook to jinak
  zastaví. Snímky se vkládají jako obrázek `![popis](adresa)`.
- **K issue patří štítek druhu i odpovědný** rovnou při zakládání:
  `--label bug` (nebo `enhancement`) a `--assignee Terms4Ever`. Bez nich hook
  příkaz zastaví a kontrola issues spadne.

## Hotovo, když

- [ ] repozitář má v kořeni jen povolené soubory a v `docs/` stav i deník
- [ ] všechny čtyři kontroly lokálně projdou
- [ ] Kontroly i Deploy na GitHubu jsou zelené
- [ ] web po nasazení funguje a dokumentace z něj není čitelná
- [ ] v deníku je záznam o přesunu: odkud, co se nepřeneslo a proč
