# Nasazení

<!-- Vzor z nastroje (sablony/docs/). Vyplň podle skutečnosti projektu,
     nevyplněné sekce smaž. Dokument nesmí tvrdit nic, co v projektu není. -->

Kam a jak se projekt dostane k uživatelům. Jedna věta: web na hostingu,
aplikace v obchodě, balíček v registru.

## Kam a čím

| Co | Hodnota |
|---|---|
| cíl | adresa webu, obchod, server |
| spouští | push do `main`, ruční spuštění, ruční skript |
| workflow nebo skript | `.github/workflows/deploy.yml` |
| čeká na | kontroly (job `kontroly`), testy |

## Tajemství

Která tajemství nasazení potřebuje a kde je nastavuje zadavatel. Hodnoty sem
nepatří, jen názvy (`FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`, `MIGRACE_TOKEN`).

## Co se nenahrává

`docs/`, `*.md`, `.github/`, `.claude/`, testy, soubory s hesly. Na hostingu
s nginxem `.htaccess` statické soubory neblokuje: co se jednou nahraje, jde
odstranit jen smazáním ze serveru.

## Ověření po nasazení

Čím se pozná, že nasazení prošlo: zelený běh, titulní stránka vrací 200,
`/docs/00-stav-projektu.md` vrací 404, migrace hlásí „databáze je aktuální".

## Nouzová cesta a pasti

Jak nasadit, když kontrola padá na něčem, co web nerozbije
(`gh workflow run deploy.yml -f bez_kontrol=true`), a co se v tomhle projektu
už jednou pokazilo.
