# Monitoring čtyř webů

## Přesný rozsah

Schváleno vlastníkem 7. 10. 2026 v issue #4 (N40). Monitoring platí **pouze**
pro tyto čtyři veřejné úvodní stránky:

| Adresa | Očekávaný obsah |
|---|---|
| https://zvedavka.cz/ | Zvědavka |
| https://onlinefakturuj.cz/ | OnlineFakturuj.cz |
| https://vyridimestavbu.cz/ | Vyřídímestavbu.cz |
| https://tomas.saroun.me/ | Tomáš Šaroun |

Seznam není seznam všech projektů podle pravidel nastroje. Další projekty,
pracovní SVN aplikace, jiné adresy a jiné cesty se automaticky nepřidávají.
Rozšíření schvaluje vlastník a zapisuje se do deníku, konfigurace, pevného
seznamu ve funkce.php a testů. Samotné zapojení pravidel monitoring nezapíná.

## Poslední měření

<!-- monitoring:zacatek -->

**Poslední měření:** 2026-10-10T15:26:43+02:00 (Europe/Prague).

| Web | HTTP | Odezva | Certifikát do (UTC) | Výsledek |
|---|---|---|---|---|
| [zvedavka.cz](https://zvedavka.cz/) | 200 | 527 ms | 2027-01-01 | V pořádku |
| [onlinefakturuj.cz](https://onlinefakturuj.cz/) | 200 | 792 ms | 2026-12-11 | V pořádku |
| [vyridimestavbu.cz](https://vyridimestavbu.cz/) | 200 | 725 ms | 2026-12-11 | V pořádku |
| [tomas.saroun.me](https://tomas.saroun.me/) | 200 | 615 ms | 2026-11-17 | V pořádku |

[Hodinový záznam](../monitoring/vysledky/2026-10/2026-10-10T13Z.json). Jednorázové měření, nikoli nepřetržitá dostupnost.

<!-- monitoring:konec -->

## Provoz

Od N41 (issue #5, pokyn vlastníka 7. 10. 2026) běží workflow **Monitoring čtyř
webů** každou hodinu v **XX:17**, časové pásmo **Europe/Prague**.
Každá skutečná hodina má vlastní měření všech čtyř webů a jeden společný
commit, obvykle tedy 24 za den. Opakování ve stejné UTC hodině ověří záznam,
nevolá weby a nepřidá další commit. Nález v existujícím záznamu stále vrátí
neúspěch. Totéž platí pro ruční spuštění; další hodina už weby změří znovu.

GitHub může plánované běhy opozdit nebo vynechat, nepřerušenou řadu contributions
proto nelze zaručit. Veřejné neaktivní repozitáře mají také limit 60 dnů bez
aktivity pro plánované workflow. Při dlouhém přerušení zkontrolovat, že workflow
není vypnuté. Počítač vlastníka může být vypnutý.

Nastroje je veřejné a používá standardní **ubuntu-24.04**. Tyto běhy
nečerpají zahrnuté minuty pro soukromé repozitáře. Podmínka jobu navíc
nepřidělí runner, pokud se repozitář změní na soukromý. Workflow nepoužívá
placený větší runner, cache ani nahrávání artefaktů.

## Co se měří

- Pouze HTTP GET kořenové stránky přes ověřené HTTPS; přesměrování se nenásleduje.
- HTTP 200, očekávaný text v odpovědi, doba odpovědi a expirace certifikátu.
- Odezva nad 5 sekund a certifikát s nejvýš 14 zbývajícími dny jsou upozornění.
- Výpadek, neplatné TLS, jiný obsah, chybějící certifikát nebo jiný HTTP stav jsou chyba.
- Nejvýš 20 sekund na web (připojení 8 sekund), odpověď nejvýš 1 MiB.

Ukládají se i chyby, potom workflow nález oznámí neúspěšným během.
Čas ukazuje odezvu z běhového prostředí GitHubu, nikoli rychlost v telefonu.
U SPA se kontroluje úvodní HTML včetně titulku, JavaScript se nespouští.
Zelený výsledek nedokazuje funkčnost přihlášení, databáze, fakturace nebo plateb.

## E-mail při problému

E-mail posílá přímo GitHub při neúspěšném workflow. Selhání způsobí nález
na webu (včetně pomalé odezvy a blížící se expirace), chyba nástroje i zápisu.
Zpráva obsahuje odkaz na běh; jeho přehled a uložený JSON ukazují konkrétní
web a nález. Vlastní SMTP, placená služba ani další token nejsou potřeba.

V [nastavení oznámení GitHubu](https://github.com/settings/notifications)
musí být **System > Actions > Email** a **Only notify for failed workflows**.
Obě volby byly 7. 10. 2026 ověřeny už zapnuté a zůstaly beze změny.
Cílem je výchozí oznamovací e-mail účtu; adresa se do veřejného repozitáře
neukládá. Plánovaná oznámení dostává uživatel, který naposledy změnil cron,
proto se změna plánu posílá pod účtem vlastníka Terms4Ever.

Při přetrvávajícím problému mohou přicházet další hodinová upozornění.
Zdravý běh e-mail nevytváří. Doručení do schránky závisí také na jejích
filtrech; přijetí zprávy může potvrdit pouze příjemce.

Ruční **Run workflow** s volbou **test_upozorneni** spustí zřetelně označenou
**Zkoušku e-mailového upozornění**. Ta záměrně skončí chybou, nevolá weby
a nemění historii ani přehled. Tím lze ověřit oznámení bez předstírání
výpadku. Po zkoušce spustit běžný workflow bez této volby.

## Zápis a oddělení od ostatních projektů

PHP 8.3 s rozšířeními curl a openssl. Bez --zapsat skript jen čte a vypisuje
výsledek. Se zápisem vytváří hodinový JSON se schématem 2 pod
monitoring/vysledky/RRRR-MM/RRRR-MM-DDTHHZ.json a aktualizuje výhradně tento
generovaný blok. Název a složka používají UTC, uvnitř je skutečný český čas
včetně posunu. Dvě podzimní hodiny 02:00 tak mají různé soubory; při jarním
posunu se neexistující hodina nevymýšlí. Starší denní JSON se schématem 1
zůstávají zachované a neblokují nové měření. Historie se nepřepisuje.
Poškozený existující záznam je chyba, nikoli důvod tiše přeskočit měření.

Jeden hodinový commit nese skutečný čas, autora Tomáš Šaroun s jeho GitHub noreply
adresou a zprávu začínající Automatické měření čtyř webů. Zřetelně jde o automatický
záznam. Nezapisuje se umělé rozhodnutí ani nové issue. Implementační issue
se nepřipisuje k automatickým commitům po jeho zavření.

Vestavěný GITHUB_TOKEN má contents: write jen v tomto jobu a jen pro nastroje.
Token není při měření v prostředí skriptu. Jeho push nespouští další push workflow,
takže nevzniká smyčka ani zbytečné spouštění ostatních kontrol. Běžné změny kódu
dál procházejí původními kontrolami. Sdílená workflow ostatních projektů se nemění.
Souběžný lidský commit se zachová; konflikt při rebase běh zastaví bez force push.

Výsledky jsou **veřejné**, protože nastroje jsou veřejné. Neukládá se HTML,
cookies, hlavičky, IP adresy ani zákaznická data. Skript nemá přihlášení k webům,
neodesílá formuláře, nevolá fakturační cron, neprovádí platby a nic nenasazuje.

## Spuštění a ověření

```bash
php monitoring/kontrola.php                         # jen měření, bez zápisu
php monitoring/kontrola.php --zapsat --pokud-chybi  # nejvýš jeden záznam v UTC hodině
php tests/spust.php monitoring                     # testy bez skutečné sítě
```

Návratové kódy: 0 bez nálezu, 2 nález na webu i při opakování, 1 chyba nástroje.
Workflow ukládá důkaz i při kódu 2. Chybějící PHP rozšíření nebo poškozená
konfigurace jej zastaví před zápisem. Celá sada testů běží v původním CI na Linuxu
i Windows. Skutečné provozní ověření a jeho meze shrnuje dokument 04.

Zdroje: [plánování GitHub Actions](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#schedule),
[e-mailová oznámení](https://docs.github.com/en/subscriptions-and-notifications/how-tos/managing-github-actions-notifications),
[účtování veřejných runnerů](https://docs.github.com/en/actions/concepts/billing-and-usage),
[chování GITHUB_TOKEN](https://docs.github.com/en/actions/how-tos/writing-workflows/choosing-when-your-workflow-runs/triggering-a-workflow),
[započítávání contributions](https://docs.github.com/en/account-and-profile/reference/profile-contributions-reference).
