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
Zatím bez uloženého měření. První ověřený běh tento blok doplní.
<!-- monitoring:konec -->

## Provoz

Workflow **Monitoring čtyř webů** v nastroje běží denně v **10:17** a záložně
v **16:17**, časové pásmo **Europe/Prague**, tedy i po změně letního času.
Druhý běh zkontroluje platnost dnešního záznamu; pokud existuje, nevolá weby
a nevytváří další commit. Totéž platí pro ruční spuštění v Actions.
Nejde o nepřetržitý dohled, ale o jeden snímek stavu za den.

GitHub může plánované běhy opozdit nebo vynechat, nepřerušenou řadu contributions
proto nelze zaručit. Veřejné neaktivní repozitáře mají také limit 60 dnů bez
aktivity pro plánované workflow. Při dlouhém přerušení zkontrolovat, že workflow
není vypnuté. Počítač vlastníka může být vypnutý.

## Co se měří

- Pouze HTTP GET kořenové stránky přes ověřené HTTPS; přesměrování se nenásleduje.
- HTTP 200, očekávaný text v odpovědi, doba odpovědi a expirace certifikátu.
- Odezva nad 5 sekund a certifikát s nejvýš 14 zbývajícími dny jsou upozornění.
- Výpadek, neplatné TLS, jiný obsah, chybějící certifikát nebo jiný HTTP stav jsou chyba.
- Nejvýš 20 sekund na web (připojení 8 sekund), odpověď nejvýš 1 MiB.

Ukládají se i chyby, potom workflow nález oznámí neúspěšným během. Oznámení
GitHubu se řídí nastavením účtu; monitoring neposílá vlastní e-maily ani issues.
Čas ukazuje odezvu z běhového prostředí GitHubu, nikoli rychlost v telefonu.
U SPA se kontroluje úvodní HTML včetně titulku, JavaScript se nespouští.
Zelený výsledek nedokazuje funkčnost přihlášení, databáze, fakturace nebo plateb.

## Zápis a oddělení od ostatních projektů

PHP 8.3 s rozšířeními curl a openssl. Bez --zapsat skript jen čte a vypisuje
výsledek. Se zápisem vytváří denní JSON pod monitoring/vysledky/RRRR-MM/
a aktualizuje výhradně tento generovaný blok. Historii stejného dne nepřepisuje.
Poškozený existující záznam je chyba, nikoli důvod tiše přeskočit měření.

Jeden denní commit nese skutečný čas, autora Tomáš Šaroun s jeho GitHub noreply
adresou a zprávu začínající Automatické měření čtyř webů. Zřetelně jde o automatický
záznam. Nezapisuje se denní umělé rozhodnutí ani nové issue. Implementační issue
se nepřipisuje k denním commitům po jeho zavření.

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
php monitoring/kontrola.php --zapsat --pokud-chybi  # nejvýš jeden záznam denně
php tests/spust.php monitoring                     # testy bez skutečné sítě
```

Návratové kódy: 0 bez nálezu nebo již změřeno, 2 nález na webu, 1 chyba nástroje.
Workflow ukládá důkaz i při kódu 2. Chybějící PHP rozšíření nebo poškozená
konfigurace jej zastaví před zápisem. Celá sada testů běží v původním CI na Linuxu
i Windows. Skutečné provozní ověření a jeho meze shrnuje dokument 04.

Zdroje: [plánování GitHub Actions](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#schedule),
[chování GITHUB_TOKEN](https://docs.github.com/en/actions/how-tos/writing-workflows/choosing-when-your-workflow-runs/triggering-a-workflow),
[započítávání contributions](https://docs.github.com/en/account-and-profile/reference/profile-contributions-reference).
