# Rozhodovací deník

Co bylo kdy rozhodnuto a proč. Nové rozhodnutí je nový záznam, staré se
nepřepisuje. Když se rozhodnutí obrátí, napíše se nový záznam s odkazem
na ten starý.

---

## N1 - Pravidla pro README žijí na jednom místě (15. 9. 2026)

**Stav.** Sedm repozitářů mělo sedm různých README. Tytéž sekce se jmenovaly
pokaždé jinak (`Stack`, `Tech Stack`, `Použité technologie`), dva projekty
README neměly vůbec a jeden popisoval instalaci, která nefungovala.

**Rozhodnutí.** Založit samostatný repozitář s jedním skriptem a jedním
vzorem. Každý projekt si drží `.readme-kontrola.json` a šestiřádkový workflow,
který kontrolu zavolá.

**Proč ne kopie skriptu v každém repozitáři.** Kopie se rozejdou, což je přesně
ten problém, který se řeší.

---

## N2 - Repozitář je veřejný (15. 9. 2026)

**Rozhodnutí.** `nastroje` jsou veřejné, přestože pět ze sedmi projektů je
soukromých.

**Proč.** Sdílený workflow ze soukromého repozitáře potřebuje u osobního účtu
nastavovat přístup navíc. Validátor a vzor nic neskrývají, takže veřejný
repozitář tu komplikaci odstraní úplně.

---

## N3 - Volá se větví, ne otiskem commitu (15. 9. 2026)

**Rozhodnutí.** Workflow se volá jako `@main`, ne jako `@<otisk>`.

**Proč.** Otisk by znamenal úpravu v sedmi repozitářích při každé změně
pravidla, což je přesně ta údržba, které se tenhle repozitář vyhýbá.

**Cena.** Kontrola commitu na to upozornila jako na dodavatelské riziko,
oprávněně. Pojistkou je, že volající workflow má `permissions: contents: read`
a žádná tajemství se do volaného workflow nepředávají.

---

## N4 - Kontroluje se i pravdivost, ne jen kostra (15. 9. 2026)

**Rozhodnutí.** Každá cesta, odkaz a kotva zmíněná v README musí existovat.

**Proč.** Rozbitá kostra je nepříjemná. README, které popisuje soubor, jenž
v repozitáři není, stojí čas - a přesně to se stalo u onlinefakturuj, kde
návod na import databáze odkazoval na soubory, které mysql odmítal.

**Past.** Kontrolovat se musí čistý export, ne pracovní adresář. Soubory
z `.gitignore` na CI nejsou. Kdo si není jistý: `git archive HEAD`.

---

## N5 - Zkrácený profil zrušen (15. 9. 2026)

**Stav.** Profily byly dva, `plny` a `slim`. Zkrácený nevyžadoval sekci
Hlavní funkce.

**Rozhodnutí.** Zrušit, zůstává jen `plny`.

**Proč.** Dělil projekty na dvě třídy bez užitku. I drobná aplikace umí říct,
co dělá, a kdo má málo funkcí, napíše krátkou sekci. Dva profily navíc
znamenaly rozhodovat u každého nového projektu, do které třídy patří.

---

## N6 - Igris nemá výjimku (15. 9. 2026)

**Stav.** Igris měl vlastní profil `igris` se sadou nadpisů bez emoji,
aby si udržel zavedený styl.

**Rozhodnutí.** Profil zrušen, Igris jede na `plny` jako ostatní. Svoje
vlastní sekce (Dokumentace, Kontroly, Kontrola na GitHubu, Prostředí) si
drží jako nepovinné, jen dostaly emoji.

**Proč.** Zadavatel chtěl sladit, ne vyjmout.

**Co zůstává jeho.** Nad kostrou má Igris vlastní testy na obsah README:
pravdivost tvrzení, čísla, verze v odznacích. Dělba je záměrná - kostru hlídá
kontrola odsud, obsah jeho vlastní sada.

---

## N7 - Jen krátké pomlčky (15. 9. 2026)

**Rozhodnutí.** Dlouhá a polovičná pomlčka jsou v README zakázané.

**Proč.** V terminálu a v jednoduchých fontech vypadají jako chyba a při
kopírování do příkazové řádky rozbijí příkaz. Igris je zakazuje od R177,
steelset to má napsané v `AGENTS.md`. Teď to platí všude.

---

## N8 - Obsah docs/ si řídí každý projekt, dohledatelnost je povinná (15. 9. 2026)

**Rozhodnutí.** Nevynucuje se, jaké dokumenty projekt má. Vynucuje se, že
když složku `docs/` má, README ji vypíše v sekci Dokumentace a každý dokument
dostane vlastní popis.

**Proč.** Zadavatel chtěl dokumentaci po vzoru Igrisu všude, ale s tím, že
každý projekt potřebuje něco jiného. Pevný seznam dokumentů by u menších
projektů vyrobil prázdnou formu. Dohledatelnost dává užitek bez té ceny:
agent i člověk vidí v README, co už je napsané, a nepíšou to znovu.

---

## N9 - Brána na dokumentaci ověřena v ostrém běhu (15. 9. 2026)

**Co se zkoušelo.** Commit, který mění kód a na `docs/` nesahá, byl schválně
pushnut přes `--no-verify`, aby se ukázalo, jestli ho zastaví i kontrola na
GitHubu, nebo jen hook na počítači.

**Výsledek.** Zastavily obě. Hook push nepustil, a po obejití skončil běh
`#34974598711` červeně na kroku Zkontrolovat dokumentaci.

**Proč to stálo za zkoušku.** Při ladění se našly tři vady, které všechny
vedly k tichému průchodu: cesta ve tvaru `/c/...`, kterou git na Windows
nezná, stříška v `^{commit}` jako únikový znak v cmd.exe a selhání gitu
vracející prázdný seznam místo výjimky. Kontrola, která tiše projde, je horší
než žádná, takže samotné "napsal jsem to" nestačilo.

---

## N10 - Pomlčka v obrácených apostrofech se toleruje (15. 9. 2026)

**Stav.** Dokument, který popisuje zákaz dlouhé pomlčky, musí ten znak umět
ukázat. Kontrola ho nahlásila jako porušení.

**Rozhodnutí.** Text uvnitř obrácených apostrofů se z kontroly pomlček
vynechává. Tam se znak cituje, nepoužívá.

**Co zůstává.** Pomlčka v běžné větě i v bloku kódu se hlásí dál. Ověřeno
třemi zkouškami: pomlčka ve větě README zastavena, pomlčka ve větě dokumentu
zastavena, pomlčka v apostrofech prošla.

**Poznámka.** Chybu našla kontrola sama, ne člověk při čtení kódu. To je ten
lepší způsob, jak takovou mezeru objevit.

---

## N11 - Kontrola prochází docs/ rekurzivně (15. 9. 2026)

**Stav.** Skript hledal dokumenty přes `glob('docs/*.md')`, tedy jen v kořeni
složky. Dokument v podsložce se nekontroloval vůbec.

**Jak se to našlo.** Ověřovací agent, kterého si vyžádala brána Igrisu.
Vložil dlouhou pomlčku do `docs/predlohy/sprity-zdroje/README.md`, kontrola
hlásila v pořádku a skončila nulou.

**Rozhodnutí.** Procházet rekurzivně. Název dokumentu v hlášce je nově celá
cesta od kořene repozitáře, ne jen jméno souboru, aby šlo najít i ten
v podsložce.

**Cena.** Igris hlásí 17 dokumentů místo 16. To je správný počet, ten
sedmnáctý se do té doby jen přehlížel.

**Poučení.** Zkoušel jsem tu kontrolu pěti sabotážemi a všechny mířily na
soubory v kořeni `docs/`. Zkouška, která nesahá do podsložky, o podsložce nic
neřekne.

---

## N12 - Kontrola nesmí tiše projít, když neví, co má dělat (15. 9. 2026)

**Jak se to našlo.** Adversariální běh nad Igrisem. Jeho zadání znělo rozbít
to, ne ověřit, že to funguje, a rozbil tři věci naráz.

**Vadné nastavení se přecházelo mlčky.** Při chybě v `.readme-kontrola.json`
se jen nechaly výchozí hodnoty, tedy `docs-kontrola` nastavené na false,
a skript ohlásil „kontrola není zapnutá" s návratovým kódem 0. Jeden překlep
nebo nedořešený merge konflikt tím vypnul naráz pomlčky, generovaný blok
i pravidlo o dávce, lokálně i na GitHubu, a push prošel.

**Rozhodnutí.** Vadný JSON je chyba, ne důvod k přeskočení. Stejně tak hodnota
`docs-kontrola`, která není `true` ani `false`: řetězec `"true"` se dřív
choval jako vypnuto.

**Neplatné UTF-8 skrylo zakázaný znak.** `preg_match` s modifikátorem `/u`
vrátí na neplatném vstupu `false`, ne `0`, takže podmínka „řádek neobsahuje
pomlčku" vyšla jako pravda. Stačil jeden vadný bajt na řádku a pomlčka na
témže řádku prošla. Nově se takový řádek hlásí jako nález.

**Smazán mrtvý kód.** Funkce `vypadaJakoCesta()` a `existuje()` zůstaly
v souboru poté, co se kontrola cest uvnitř dokumentů zrušila jako N8. Čtyřicet
pět řádků, které nikdo nevolal.

**Poučení.** Všechny tři vady patří do téže rodiny jako nálezy u N9: kontrola
oznámí úspěch, aniž cokoli ověřila. Je to nejnebezpečnější druh chyby, protože
zvenčí vypadá stejně jako úspěch. Zkoušky naprázdno je hledat musí cíleně,
samy od sebe nevyplavou.

---

## N13 - Druhé kolo tichých průchodů (15. 9. 2026)

**Jak se to našlo.** Druhý běh adversariálního agenta, poté co se opravila
první vlna. Našel, že pojistka z N12 nestačí.

**Prázdné pole projde jako nastavení.** `is_array()` je u `json_decode` pravda
i pro seznam, takže `[]` novou pojistkou prošlo, `array_merge` nechal
`docs-kontrola` na false a brána byla pryč. Nastavení musí být objekt, tedy
asociativní pole.

**Hláška u `null` lhala.** `json_decode("null")` uspěje, takže
`json_last_error_msg()` řekl „No error". Výsledek byl správný, vysvětlení
matoucí.

**Typová kontrola byla jen na jednom klíči.** `docs-vymahat-aktualizaci`
nastavené na `0` tiše vyplo pravidlo o dávce, přesně jako předtím
`docs-kontrola` jako řetězec. Kontrola teď platí na oba.

**Neznámý základ rozsahu mlčel.** Když základ v repozitáři není, pravidlo
o dávce se neuplatní. To se stane u mělkého klonu nebo zastaralého `origin`
a výstup u toho hlásil „v pořádku". Nově se to říká nahlas jako poznámka.
Nová větev, kde základ jsou samé nuly, se hlásit nemá a nehlásí.

**Poučení podruhé.** Pojistka proti tiché chybě sama potřebuje zkoušku
naprázdno. První verze guardu vypadala správně a přitom měla díru hned
v prvním řádku.

---

## N14 - Kontroluje se pushovaný commit, ne disk (16. 9. 2026)

**Jak se to našlo.** Třetí kolo adversariálního běhu nad Igrisem, poprvé na
modelu Sonnet. Obsah Igrisu označil za čistý, ale našel tři díry v branách,
z toho dvě v tomhle repozitáři.

**Kontrolovalo se něco jiného, než co se pushuje.** Obsah dokumentů i generovaný
blok se čtou z disku, pravidlo o dávce z gitu. Stačila běžná situace, ruční
oprava bez `git add`, a kontrola pustila commit se zakázaným znakem, protože
na disku už byl čistý soubor. Opačně zastavila čistý commit kvůli rozdělané
práci.

**Rozhodnutí.** Když skript dostane pushovaný commit, nejdřív ověří, že se
pracovní strom od něj v čtených cestách neliší, a to včetně nesledovaných
souborů. Když se liší, skončí s chybou a řekne proč. Čtené cesty jsou
`docs`, `.readme-kontrola.json` a čtyři soubory, ze kterých bere verzi
generátor bloku.

**Proč ne číst obsah rovnou z commitu.** Generátor bloku zjišťuje hlavní větev
z gitu, a ten ve vyexportovaném stromu není. Ověřit shodu je jednodušší
a stejně spolehlivé. Na GitHubu je checkout vždy přesně ten commit, takže
tam kontrola nikdy nevystřelí.

**Prázdný objekt se hlásil jako neplatný JSON.** Moje oprava z N13 odmítala
`[]` přes `array_is_list`. Jenže `json_decode('{}', true)` a
`json_decode('[]', true)` jsou v PHP totéž prázdné pole, takže pojistka
odmítla i platný prázdný objekt `{}`. Nově se dekóduje bez asociativního
režimu a objekt se pozná jako `stdClass`. Zároveň se odstraní BOM, se kterým
padal JSON uložený Poznámkovým blokem.

**Za kód se nepočítal `LICENSE.php`.** Seznam souborů, které kódem nejsou,
se porovnával přes předponu. To dává smysl jen u složky `docs/`, u ostatních
položek vyřadilo i `LICENSE.php` nebo `README.md-old.js`. Nově předpona jen
u složky, jinak přesná shoda. `kontrola-readme.php` to měl správně od začátku.

**Poučení.** N13 byla oprava tiché chyby a sama vyrobila regresi o kolo dál.
Pojistka proti chybnému vstupu musí mít zkoušku i na platném vstupu, nejen
na tom chybném.

---

## N15 - Hlavní větev z repozitáře a GitHub, který push nezastaví (16. 9. 2026)

**Jak se to našlo.** Čtvrté kolo adversariálního běhu nad Igrisem ukázalo, že
commit-msg nespustí cherry-pick ani rebase, takže globální pre-push nově
prochází pravidly commitu každý pushovaný commit. Při zkoušce té kontroly na
vedlejší větvi spadla kontrola dokumentace, přestože commity byly prázdné.

**Hlavní větev byla aktuální větev.** `stav-projektu.php` psal do bloku
`rev-parse --abbrev-ref HEAD`. Blok vygenerovaný na `main` proto neseděl
na žádné jiné větvi a push z ní se zastavil hláškou, že je blok zastaralý.
Na GitHubu totéž u každé vedlejší větve a u pull requestu, který stojí na
odpojeném HEAD. Nikdo na to nenarazil jen proto, že se zatím pushovalo jen
do `main`.

**Rozhodnutí.** Hlavní větev se bere z `origin/HEAD`, ale jen když jeho cíl
existuje: u `steelset` zůstal po přejmenování ukazovat na neexistující
`origin/master`. Pak `main`, pak `master`, až nakonec aktuální větev. Všechny
repozitáře mají na GitHubu výchozí větev `main` a bloky píšou `main`, takže
se žádný blok nemění. Verze kontroly 1.4.1, protože se mění její chování.

**GitHub push nezastaví.** README tu mělo v Tech Stacku dvakrát „Brána",
jednou pro GitHub Actions. Větve ale nemají ochranu ani ruleset (ověřeno
přes `gh api` u Igrisu), takže workflow běží až po pushi a chybu jen nahlásí.
Zastavit push umí jen hook. Opraveno v README a zapsáno do stavu, ochranu
větví s povinnými kontrolami rozhodne zadavatel.

---

## N16 - Ochrana větví se nezapíná (16. 9. 2026)

**Otázka z N15.** Workflow na GitHubu běží až po pushi a chybu jen nahlásí,
protože větve nemají ochranu. Zapnout ochranu s povinnými kontrolami by znamenalo
práci přes pull requesty.

**Rozhodnutí zadavatele.** *„Nechci schvalovat pull requesty. Takže tohle
zrušíme."* Ochrana se nezapíná. Zastavit push umí jen pre-push hook, kontrola na
GitHubu zůstává druhým okem, které chybu nahlásí.

---

## N17 - Standard migrací databáze (20. 9. 2026)

**Zadání.** Zadavatel: *„Pro všechny projekty bych chtěl nějak sjednotit, že
když šahají do DB, ať udělají migrace v složce db i na gitu a je tam migrační
soubor co vložili např do mysql a máme přehled co a kdy se měnilo."*

**Proč zrovna teď.** Ráno 20. 9. vyjelo vydání s novými sloupci u výdajů dřív,
než někdo pustil migraci na produkci. Aplikace na produkci spadla na chybějících
sloupcích a muselo se vracet. Sekvence nasazení stála na tom, že si člověk
vzpomene.

**Stav před tím.** Tři různé světy: `onlinefakturuj` měl `db/migrate_*.php`
bez datumů, `vyridimestavbu.cz` datované `.sql` v `db/migrace/`, `trenwise.cz`
nativní migrace Laravelu. Kdy co proběhlo na produkci, nevěděl nikdo.

**Rozhodnutí zadavatele.** Čisté SQL soubory, spouštění automaticky při
nasazení, staré migrace se nepřevádějí a zůstávají, jak jsou.

**Standard.**

- Jedna změna schématu je jeden soubor `db/migrace/rrrr-mm-dd-popis.sql`,
  volitelně s časem (`rrrr-mm-dd-hhmm-popis.sql`). V souboru je přesně to, co
  se pustí do MySQL, takže jde v nouzi zkopírovat do phpMyAdmin.
- První řádek je komentář, který říká, co migrace mění. Z něj se skládá přehled.
- Hotová migrace se nemění ani nemaže. Oprava je nová migrace, protože ta
  původní už někde běží.
- `db/prehled.md` generuje `prehled-migraci.php`, kontrola porovnává blok se
  složkou, stejně jako u stavu projektu.
- Na produkci pouští migrace nasazení přes chráněný odkaz. Tabulka `migrace`
  v databázi drží, co a kdy proběhlo, a spouštěč zamkne běh, aby dvě nasazení
  nepustila tutéž migraci dvakrát.
- Projekty s vlastním migračním nástrojem se nezapojují.

**Co to vynucuje.** `kontrola-migraci.php` (verze 1.0.0) běží v pre-push hooku
i ve workflow: tvar názvů, komentář, zákaz dlouhých pomlček, soulad přehledu,
neměnnost hotových migrací a pravidlo, že dávka s `CREATE TABLE` nebo
`ALTER TABLE` mimo migrace musí migraci přidat.

**Ověření.** Vzor spouštěče proběhl proti vývojové databázi: pořadí podle data,
středník v textu i v komentáři příkaz neukončí, druhý běh nedělá nic, rozbitá
migrace skončí výjimkou a nezapíše se, po opravě doběhne. Kontrola má vlastní
zkoušku s jedenácti situacemi, každé pravidlo se v ní schválně poruší.

---

## N18 - Migrace se na produkci nenahrávají do webové složky (20. 9. 2026)

**Jak se to našlo.** Při zavádění standardu (N17) jsem chtěl nahrávat
`db/migrace/` spolu s kódem. Sousední session, která dělá na vyridimestavbu.cz,
to přečetla a změřila chování hostingu: `.php` se vykoná, ale statický soubor
se pošle tak, jak je, a `.htaccess` tam neplatí. Migrace ve webové složce by
tedy šly přečíst z internetu.

**První pokus nevyšel.** Nahrát migrace mimo webovou složku FTP účet
nedovolí, nasazení spadlo na `553 Can't open that file: Permission denied`.
Nad web se zapisovat nedá.

**Rozhodnutí.** Nasazení vyrobí z každé migrace kopii `nazev.sql.php`, která má
na prvním řádku `<?php exit; ?>`, a nahraje ji do `db-migrace` ve webu. Kdo si
adresu otevře, dostane prázdnou odpověď: `.php` se vykoná, nevypisuje se nic.
V repozitáři zůstává čisté `.sql`, které jde zkopírovat do phpMyAdmin.
Spouštěč zná obě podoby, zámek před spuštěním odřízne a do tabulky zapisuje
jméno bez `.php`, takže vývoj i produkce mluví o téže migraci. Složku si najde
sám přes `Migrace::najdiSlozku()`.

**Proč ne jinak.** Nechat migrace veřejné a jen psát do pravidel, že v nich
nesmí být nic citlivého, je pravidlo, které jednou někdo poruší. Zákaz u
hostingu by platil jen pro jeden web a neplatil by pro nový.

**Dnešní obsah migrací citlivý není** (tabulky a text stránky), riziko je až
v migraci, která bude zakládat účet nebo měnit heslo.

**Doplněk 20. 9. 2026.** Hledání DDL přeskakuje soubory spouštěče
(`migrace.php`, `Migrace.php`, `migrace-endpoint.php`). Spouštěč si zakládá
vlastní tabulku `migrace`, takže jeho úprava vypadala jako změna schématu bez
migrace a zastavila push ve vyridimestavbu.cz.

**Koncový bod na vyridimestavbu.cz vracel prázdno, a nebylo to PHP.** Nejdřív
jsem to hodil na starší PHP a `readonly` vlastnosti ve vzoru, které zná až
PHP 8.1. Ověřit jsem to ale nešel podle výstupu, jen podle dohadu, a nasazení
po té změně vracelo pořád totéž. Skutečná příčina byla jinde: `index.php`
načítal `app/db.php` podruhé (`require` místo `require_once`), takže PHP
skončilo chybou "Cannot redeclare db()" ještě před jakýmkoli výpisem. Změřeno
simulací požadavku v příkazové řádce, ne odhadem.

Vzor zůstává bez `readonly`, aby stačil i s PHP 8.0, ale příčina to nebyla.

---

## N19 - Standard issues (20. 9. 2026)

**Zadání.** Zadavatel: *„Teď mám další věc co se špatně dělá v celém gitu a to
jsou issues. Můžeme nějak vyřešit ať se globálně zapisují správně?"* K tomu
čtyři body: odškrtávat checklisty při splnění, nahrávat snímky před a po bez
nové větve, psát kratší komentáře bez zmínky o nástroji a držet jeden tvar.

**Stav před tím.** Šablonu neměl žádný repozitář, tvar se lišil projekt od
projektu (`## Problém` versus `## Cíl`, různé sekce), zavřená issues měla
neodškrtnuté checklisty, komentáře měly i přes dvacet řádků a v několika se
psalo, že je psal nástroj.

**Rozhodnutí zadavatele.** Jedna šablona pro všechno, snímky do `docs/snimky/`
pojmenované podle issue, komentáře do pěti řádků, vynucení šablonou a kontrolou
z nastroje.

**Standard.**

- Povinné sekce `## Problém` (nebo `## Cíl`) a `## Hotovo, když` s odškrtávacím
  seznamem; dál `## Jak to poznat`, `## Kde to žije` a `## Snímky`.
- Zavřené issue nesmí mít neodškrtnutý bod.
- Komentář nejvýš pět řádků, bez zmínky o nástroji, bez dlouhých pomlček.
- Snímky v `docs/snimky/<číslo>-<název>/pred-*.png` a `po-*.png`, odkazované
  z issue. Žádná větev jen pro média a žádné externí úložiště.

**Proč ne přílohy releasu.** Nabízel jsem je jako způsob, jak nezvětšovat
repozitář, ale zadavatel chce mít snímky v repozitáři a přehledně pojmenované.
Cena je velikost repozitáře, proto pravidlo o oříznutém snímku.

**Vynucení.** `kontrola-issues.php` (verze 1.0.0) čte issues přes `gh` a hlásí
odchylky. Běží ve sdíleném workflow při každém pushi a nově i jednou denně,
protože issues se mění i bez pushe; volající repozitáře proto dostaly
`issues: read` a plán běhu. Issues starší než 21. 9. 2026 jen upozorní:
pravidlo nemá trestat zpětně.

**Práva v CI.** Sdílené workflow mělo `permissions: contents: read`, čímž
volanému tokenu sebralo `issues: read`, které mu volající dal. Krok pak jen
oznámil, že issues nenačetl, a kontrola skončila zeleně, aniž by cokoli
zkontrolovala. Omezení je proto pryč: práva určuje volající workflow. Kdyby si
je sdílený soubor vynutil, spadl by každý repozitář, který `issues: read` nedává.

**Ověřeno.** Kontrola nad skutečnými issues našla to, co zadavatel vytýkal:
neodškrtnuté checklisty u zavřených issues, komentáře o 18 až 26 řádcích,
zmínky o nástroji ve steelsetu a dlouhé pomlčky. Po nastavení data zavedení
projde všech sedm repozitářů.

---

## N20 - Tvar issues je pevný, nápad zadavatele ne (20. 9. 2026)

**Podnět.** Zadavatel na issue #21 v onlinefakturuj: *„To mi nepřijde jako styl
co jsme chtěli na 100 %."* Měl pravdu. Kontrola z N19 hlídala jen to, že
v těle existují dvě sekce, takže pustila issue s nadpisy `Současný stav`,
`Co udělat`, `Akceptační kritéria` a `Poznámka`, dvěma checklisty, výpisy SQL
a padesáti řádky. Napříč repozitáři bylo přes dvacet různých nadpisů.

**Druhá půlka zadání.** *„Issues co dělám já chci právě když mě napadnou udělat
v špatném jednovětovém formátu, aby jsi je pak předělal."* Zadavatel tedy
píše syrově a tvar dodávám já. Kontrola to musí rozlišit, jinak by trestala
právě ten způsob práce, který si přeje.

**Rozhodnutí.**

- Sekce jsou dané a jiné se nepřidávají: `Problém` (nebo `Cíl`, ne obojí),
  `Jak to poznat`, `Hotovo, když`, `Kde to žije`, `Snímky`, v tomhle pořadí.
- Jeden checklist, celý pod `Hotovo, když`.
- Tělo do 40 řádků. Rozbor a výpisy patří do `docs/`.
- Issue bez jediného nadpisu `## ` je syrový nápad zadavatele. Není to chyba,
  kontrola ho vypíše jako „k přepsání". Přepsat ho do tvaru je práce agenta,
  hned jak na issue sáhne; původní věta zůstane jako `Problém`.

**Proč tvar a ne jen doporučení.** Volný tvar je přesně to, co selhalo: N19
pravidla popsala, ale vynutila jen existenci dvou nadpisů, takže se nic
nezměnilo a issues dál vypadaly každé jinak.

**Ověřeno.** Atrapou `gh` v laboratorním repozitáři, osm případů: syrový nápad
projde a vypíše se jako k přepsání, správné issue projde, sekce navíc, chybějící
`Hotovo, když`, dlouhé tělo, checklist mimo sekci, sekce dvakrát, špatné pořadí,
`Problém` i `Cíl` naráz, zavřené s neodškrtnutým bodem, komentář o sedmi řádcích
a zmínka o nástroji spadnou. Návratový kód 1. Na ostrých issues onlinefakturuj
kontrola najde 117 odchylek proti 44 před změnou.

---

## N21 - Tvar issue se hlídá při zakládání, ne ráno (20. 9. 2026)

**Podnět.** Zadavatel: *„Seš si jistý, že teď když dám příkaz jinému agentovi
udělat issue, tak to udělá správně?"* Nebyl jsem. Šablona platí jen ve webovém
formuláři, agent zakládá issue přes `gh issue create --body`, kontrola běžela
při pushi a v 6:00 a nic nezastavila. K tomu: *„Kontrola v 6 ráno je blbost,
já to potřebuji při vytváření."*

**Rozhodnutí.** Tři místa místo slibu v pokynech.

1. Workflow `tvar-issue.yml` nad událostí `issues` (opened, edited, closed).
   Špatné issue dostane štítek `tvar nesedí` a komentář do pěti řádků s tím,
   co chybí. Když se tvar spraví, štítek zmizí. U zavírání se navíc hlídá
   neodškrtnutý checklist.
2. Hook Claude Code před `gh issue create`, který tělo prohlédne a špatné
   zastaví, takže se špatné issue nezaloží.
3. Kontrola všech issues při pushi zůstává jako síť.

**Plán v 6:00 zrušen.** Byl to kompromis z N19 a zadavatel ho odmítl: hlásil
by odchylky až za den. Událost `issues` je nahrazuje ve vteřinách.

**Sdílená pravidla.** Tvar těla se posunul do `src/tvar-issue.php`, aby ho
kontrola issues, kontrola jednoho těla i workflow braly ze stejného místa.

**Kdo běh spustí.** Workflow nad událostí `issues` má právo zapisovat do issues
a repozitáře jsou zčásti veřejné, takže ho zvenčí dokázal spustit kdokoliv
založením issue. Běží proto jen pro autory se vztahem k repozitáři (OWNER,
MEMBER, COLLABORATOR). Cizí hlášení se neštítkuje ani nekomentuje.

**Hook.** Leží v repozitáři (`hooky/tvar-issue.ps1`), aby se verzoval spolu
s pravidly; `settings.json` na něj jen ukazuje. Musí zůstat v UTF-8 s BOM,
jinak Windows PowerShell 5.1 přečte český text rozsypaný. Přesměrovaný výstup
kontroly se čte jako UTF-16LE, protože tak ho PowerShell zapisuje, a hláška
se posílá vlastním zapisovačem v UTF-8. Tělo se předává souborem: vložený text
v příkazu nejde spolehlivě přečíst (uvozovky, heredoc), proto ho hook odmítá.

**Ověřeno.** Osm případů proti hooku: správné tělo a nesouvisející příkaz
projdou, syrové tělo od agenta, sekce navíc, vložené tělo, dlouhý komentář
a zakládání přes `gh api` skončí kódem 2. Na GitHubu: špatné issue dostalo do
30 vteřin štítek `tvar nesedí` a komentář o třech řádcích, po opravě těla
štítek zmizel.

**Oprava po prvním ostrém běhu.** Tělo napsané ve webovém formuláři má konce
řádků CRLF. Kontrola nechávala `` na konci nadpisu, takže `## Problém`
neodpovídalo povolené sekci a každé takové issue hlásilo „sekci navíc" u všech
sekcí. Konce řádků se teď srovnají na jeden tvar (`sjednotRadky`). Našlo se to
při přepisování starých issues: dvě issues zadavatele z webu vypadala jako
špatná, přitom byla v pořádku.
