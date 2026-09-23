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

---

## N22 - Jeden tvar souborů v repozitáři (20. 9. 2026)

**Podnět.** Zadavatel při úklidu: *„Projekty mají hroznou nekonzistenci v tom
.md souborech atd."* Steelset měl v kořeni `AGENTS.md` i `CLAUDE.md`, trenwise
`PROJECT.md` a `DEPLOY.md`, LabProtocol dvě verze téhož listingu (jednu
s příponou FINAL) a onlinefakturuj pro agenty neměl nic.

**Rozhodnutí zadavatele.** Jeden agentský soubor `AGENTS.md` a `CLAUDE.md` jako
jednořádkový ukazatel, data pryč z `docs/`, vynucení kontrolou při pushi,
rozsah všechny repozitáře kromě Igrisu.

**Pravidla.**

- V kořeni smí být jen `README.md`, `AGENTS.md`, `CLAUDE.md`, `LICENSE.md`
  a `CHANGELOG.md`. Ostatní dokumenty patří do `docs/`.
- `CLAUDE.md` má jediný řádek `@AGENTS.md`. Claude Code čte CLAUDE.md, ostatní
  nástroje AGENTS.md, ale text je jen jeden, takže se nemůže rozejít.
- `docs/` nese dokumenty; data patří jinam, výjimkou jsou `docs/snimky/`
  a `docs/prilohy/`.
- Povinné jsou `docs/00-stav-projektu.md` a `docs/03-rozhodovaci-dennik.md`.
- Názvy se značkou dočasnosti na konci (`-FINAL`, `-new`, `-old`, `kopie`,
  `.bak`, `soubor (1).png`) neprojdou: nikdo nepozná, co platí.

**Vynucení.** Kontrola dokumentace 1.5.0. Značka dočasnosti se hlídá jen
u dokumentů a obsahu `docs/`: ve zdrojovém kódu je `exercise-new.tsx` poctivý
název obrazovky. Zkouška na šesti repozitářích to odhalila hned, stejně jako
`demo-03-new-invoice-modal.png`, kde je „new" uprostřed věty; proto se značka
hledá na konci názvu bez přípony.

**Vzor.** `sablony/agents.md` drží kostru: stack a struktura, doménová pravidla,
brány před commitem, nasazení, jak se domlouváme. Co je v globálních pokynech,
se do projektu nekopíruje.

**Zábrana v `docs/`.** Na hostingu s nginxem před Apachem nezabírá ani
`RewriteRule`, ani `FilesMatch` v kořenovém `.htaccess`: statický soubor
v podsložce posílá server sám. Projekt proto smí mít `docs/.htaccess`
s `Require all denied`, stejný vzor jako u `db/`. Kontrola README bere do
tabulky dokumentů jen `.md` (2.0.1) a kontrola dokumentace `.htaccess` v `docs/`
nepovažuje za data.

---

## N23 - Složka .claude patří do gitu, kromě osobního nastavení (20. 9. 2026)

**Podnět.** Zadavatel: *„a co složka .claude?"* Stav byl rozhozený: steelset
a Igris měly `.claude/launch.json` i `settings.json` v gitu, vyridimestavbu
celou složku v `.gitignore`, zbylé čtyři repozitáře neměly nic a každá session
si spouštění náhledu vymýšlela znovu.

**Rozhodnutí zadavatele.** Sjednotit celý balík.

- `.claude/launch.json` a `.claude/settings.json` do gitu. Je to stejný druh
  informace jako „jak se to spouští" v README, jen strojově čitelná.
- `.claude/settings.local.json` do `.gitignore` všude. Osobní nastavení je věc
  stroje, ne projektu.
- Chybějící `launch.json` doplněn: onlinefakturuj (vestavěný server na 8000),
  trenwise (`php artisan serve` na 8010), LabProtocol (expo web na 8082).
  Porty se schválně neperou se steelsetem (8081) ani s Igrisem (5174).

**Vedlejší práce.** onlinefakturuj neměl router pro vestavěný server, takže
`php -S` vracel u hezkých adres 404. Přibyl `dev-server.php` podle vzoru
z vyridimestavbu; nasazení ho nenahrává.

**Vynucení.** Kontrola dokumentace nově hlásí `.claude/settings.local.json`
v gitu a neplatný JSON v `.claude/*.json` (ten by Claude Code přeskočil bez
hlášky).

---

## N24 - Žádné „with Claude" u autora (20. 9. 2026)

**Podnět.** Zadavatel poslal snímek komentářů ve steelsetu, kde GitHub psal
u autora jmenovku *with Claude*: *„To odeber a nikde to už nechci vidět!"*

**Příčina.** GitHub ukládá u issue a komentáře pole
`performed_via_github_app`. Nastaví se podle tokenu, kterým záznam vznikl,
a úprava textu ho nesmaže. Postižených bylo 16 komentářů ze 23 ve steelsetu,
jinde nic; issues samotné pole nemají.

**Oprava.** Komentář se musel napsat znovu a starý smazat, jiná cesta není.
Nejdřív se zapsal nový, ověřilo se, že razítko nemá, a teprve pak se mazal
starý, aby se text nemohl ztratit. Po průchodu je ve všech šesti repozitářích
razítek nula.

**Vynucení.** Kontrola issues 1.2.0 hlásí každé issue i komentář, které vznikly
přes aplikaci. Čte `gh api` po stránkách a **schválně bez `--jq`**: na Windows
`escapeshellarg()` zahodí uvozovky a ze `!=` udělá ` =`, takže se filtr rozpadl
a kontrola tiše procházela. Selhání volání se teď hlásí jako upozornění, ne
mlčením.

**Prevence.** `gh` musí běžet s osobním tokenem (`GITHUB_TOKEN`, u zadavatele
uživatelská proměnná prostředí). Session, která píše přes aplikaci, razítko
vyrobí znovu a smazat ho jde zase jen přepsáním.

---

## N25 - Snímek „před" bez „po" zavřené issue neprojde (21. 9. 2026)

**Podnět.** Zadavatel čekal u hotových issues snímky před a po, a nenašel je:
*„Proč tam nejsou?"*

**Proč nebyly.** Standard z N19 popisoval, kam snímky patří a jak se odkazují,
ale nic je nevyžadovalo. Kontrola ověřovala jen tvar cesty a to, že odkazovaný
soubor v repozitáři leží. Ve steelsetu tak skončilo čtrnáct zavřených issues,
z toho tři se snímkem „před" od zadavatele a žádné se snímkem „po".

**Druhá příčina je praktická.** Snímek „po" u mobilní aplikace nemá kdo pořídit:
agent na Windows se k iPhonu nedostane a TestFlight je test u zadavatele.
U webových projektů to agent zvládne sám z náhledu v prohlížeči.

**Rozhodnutí.** Kontrola 1.3.0: **zavřené issue, které má v repozitáři snímek
`pred-`, musí mít i `po-`.** Když snímek „před" není, nic se nevyžaduje, takže
backendové issues bez vizuální změny pravidlo netrápí.

**Co z toho plyne pro práci.** Kde agent snímek pořídit umí (web, náhled
v prohlížeči), pořídí ho sám. Kde neumí (iOS, TestFlight), si o něj řekne
zadavateli dřív, než issue zavře.

---

## N26 - Snímek musí být vidět, ne schovaný za odkazem (21. 9. 2026)

**Podnět.** Zadavatel u vyridimestavbu #4: *„Ale ani tady třeba nejsou ty
obrázky v komentářích."* Přitom tam byly: čtyři dvojice snímků, všechny jako
`[před](adresa)`. GitHub odkaz vykreslí jako text, takže v issue nebylo vidět
nic. Pravidlo z N19 říkalo „vkládají se odkazem", což se dalo přečíst obojím
způsobem.

**Rozhodnutí.** Snímek se vkládá jako obrázek `![popis](adresa)`. Pouhý odkaz
kontrola hlásí. Zároveň se **řádek, který je jen vložený obrázek, nepočítá do
limitu pěti řádků komentáře**: snímky jsou důkaz, ne ukecanost, a limit je
nesmí trestat.

**Vedomá výjimka u checklistu.** Pravidlo „zavřené issue nemá neodškrtnutý
bod" nutilo buď lhát, nebo smazat bod, který do zadání patřil. Steelset #8 to
řešil poctivě: bod nechal nezaškrtnutý a do komentáře napsal proč (rolování jde
ověřit až na zařízení). Kontrola proto takový bod uzná, když to komentář
výslovně říká.

**Planý poplach.** Pravidlo „nikde se nepíše, čím se to psalo" hlásilo issue,
které zmiňovalo cestu `.claude/launch.json`. Kontrola teď před hledáním vyřadí
bloky kódu, kód v řádku, cesty s `.claude` a název `claude-mem`.

**Ověřeno.** Po opravě prochází onlinefakturuj (18 issues), vyridimestavbu (4),
trenwise (2). Ve steelsetu zbývá jediný nález: #14 nemá snímek „po", ten musí
přijít ze zařízení.

---

## N27 - Když snímek nemá kdo pořídit, rozhodne štítek (21. 9. 2026)

**Podnět.** Zadavatel po pohledu na GitHub: *„nemůže se stávat, že dáš push
a neprojde to kontrolou a je ti to jedno!"* Měl pravdu. Pravidlo z N25
o snímku „po" jsem pushnul s vědomím, že steelset #14 shodí, protože ten
snímek jde pořídit jen na zařízení. Červená kontrola pak visela hodinu.

**Rozhodnutí.** Issue, u kterého snímek „po" nemá kdo pořídit, dostane štítek
`bez snímku po` a kontrola ho přeskočí. Štítek je vidět na první pohled,
na rozdíl od věty schované v komentáři, a je to vědomé rozhodnutí člověka,
ne tichá výjimka.

**Druhá oprava.** Pravidlo o razítku aplikace hlásilo komentáře vlastního
workflow. Bot píše pod svým jménem a je to zjevné; pravidlo míří na záznamy,
které vypadají jako od člověka, ale vznikly přes aplikaci. Autoři končící
`[bot]` se proto přeskakují.

**Úklid.** Pět komentářů „Tvar issue nesedí" ve steelsetu už neplatilo
(checklisty jsou odškrtané), tak jsou pryč.

**Pravidlo pro mě.** Push, po kterém kontrola spadne, se nenechává. Buď se
opraví data, nebo pravidlo, a teprve pak se pushuje; po každém pushi se běh
ověřuje.

---

## N28 - Kde projekt běží, stojí v hlavičce README (21. 9. 2026)

**Podnět.** Zadavatel porovnal dvě README vedle sebe: vyridimestavbu má
v hlavičce řádek „Provoz: vyridimestavbu.cz", onlinefakturuj nic. *„Tady vidím
další nesrovnalost."*

**Rozhodnutí.** Adresa provozu patří do hlavičky, hned nad oddělovač, ve tvaru
`🌐 **Provoz:** [adresa](adresa)`. Hlídá to kontrola README 2.1.0, ale jen
tehdy, když je adresa v `.readme-kontrola.json` pod klíčem `provoz`. Bez klíče
se nevyžaduje nic: sada skriptů ani mobilní aplikace veřejnou adresu nemá
a vymýšlet ji by bylo horší než mlčet.

**Zapnuto u dvou projektů:** onlinefakturuj.cz a vyridimestavbu.cz, oba
ověřené odpovědí 200.

**Nález vedle.** trenwise.cz neodpovídá vůbec (curl vrací 000), takže se u něj
provoz netvrdí. Steelset a LabProtocol jdou přes App Store; jestli tam má být
odkaz na obchod, rozhodne zadavatel.

---

## N29 - Zadání pro přesun FTP projektu na GitHub (22. 9. 2026)

**Podnět.** Zadavatel chce dostat na GitHub další web z FTP hostingu a ptá se,
jaké zadání dát novému agentovi, aby vyšlo všechno napoprvé.

**Rozhodnutí.** Zadání není jednorázová zpráva, ale vzor v repozitáři:
`sablony/zadani-novy-projekt.md`. Postup od stažení z FTP po ověření, že web
běží a dokumentace z něj není čitelná, včetně seznamu souborů, které do kořene
patří, a tajemství, která nastavuje zadavatel.

**Proč vzor a ne odpověď v chatu.** Stejné kroky jsme letos dělali u tří
projektů a pokaždé se zapomnělo na něco jiného: jednou docs na webu, jednou
kolize routy a složky, jednou chybějící šablona issue. Vzor to drží na jednom
místě a mění se s pravidly.

**Obsahuje i pasti z praxe**: `.htaccess` na nginxu statické soubory
neblokuje, FTP účet nesmí zapisovat nad webovou složku (553), routa se nesmí
jmenovat jako složka, kontrola čte pushovaný commit a ne pracovní strom, issue
se zakládá přes `--body-file`.

**Odkaz na provoz u mobilních aplikací.** Steelset a LabProtocol dostanou
v hlavičce README řádek `🌐 **Provoz:**` s odkazem na App Store, až odkaz
vznikne; klíč `provoz` v `.readme-kontrola.json` to pak začne hlídat. Trenwise
má pauzu, provoz se u něj netvrdí schválně.

---

## N30 - Trezor místo hesel v chatu (22. 9. 2026)

**Podnět.** Zadavatel: *„jak udělat, abych do PC zapsal heslo k FTP a pak to
nemusel agentům psát dokola a zároveň nekřičeli, že heslo prošlo chatem?"*

**Co na počítači už bylo.** WinSCP má 48 uložených sezení včetně
onlinefakturuj i vyridimestavbu, bez hlavního hesla, takže `open <sezení>`
funguje bez jediného údaje v příkazu. Ověřeno výpisem `/web`. Hesla ale leží
v registru jen zaobalená, ne zašifrovaná.

**Rozhodnutí zadavatele.** Trezor pro všechno, tedy i pro FTP, ne jen pro
tokeny.

**Jak to funguje.** `hooky/tajemstvi.ps1`:

- `ulozit <cíl>` se zeptá na server, uživatele a heslo a uloží je přes DPAPI
  do `%USERPROFILE%\.tajemstvi\<cíl>.xml`. Soubor jde zkopírovat jinam, ale
  rozšifrovat ne: klíč drží účet a počítač.
- `seznam` vypíše cíle, uživatele a servery, nikdy heslo.
- `spustit <cíl> :: <příkaz>` vloží údaje do prostředí spuštěného příkazu
  (`TAJ_SERVER`, `TAJ_UZIVATEL`, `TAJ_HESLO`) a po doběhnutí je zahodí.
- `ftp <cíl> :: <příkazy>` poskládá dočasný skript pro WinSCP, spustí ho
  a smaže. Heslo se tím nedostane do příkazové řádky, kterou vidí každý proces.

**Pasti při stavbě.** PowerShell 5.1 bez BOM čte skript jako ANSI, takže se
rozsypala čeština. `--` si PowerShell bere pro sebe, oddělovač je proto `::`.
A protože se nerozlišuje velikost písmen, proměnná `$SLOZKA` přepisovala
parametr `$Slozka`; trezor se jmenuje `$TREZOR`.

**Certifikát se neobchází.** K cíli jde uložit otisk certifikátu (`-Otisk`),
pak se spojení ověřuje. Bez otisku WinSCP u neznámého certifikátu skončí
a řekne, jaký otisk server má.

**Ověřeno.** Uložení, výpis, vložení do prostředí (příkaz viděl uživatele,
server a délku hesla, samotné heslo ne) i spuštění WinSCP, které se zastavilo
přesně na ověření certifikátu. Po běhu nezůstal jediný dočasný soubor.

**Jak se do trezoru vkládá.** Tři cesty, protože `Read-Host` v nástroji agenta
nefunguje, tam terminál není:

1. `okno <cíl>` otevře okno, do kterého údaje vyplní člověk. Agent ho může
   otevřít a hodnotu stejně nevidí.
2. `ulozit <cíl>` se ptá v terminálu, když si ho zadavatel otevře sám.
3. `zwinscp "<sezení>" <cíl>` převezme sezení, které už v počítači je. WinSCP
   heslo neukládá šifrovaně, jen zaobalené, takže ho skript přečte a uloží do
   trezoru zašifrovaně. Volitelné `-Slozka` přebije složku ze sezení.

**Převzato a ověřeno.** `vyridimestavbu-ftp` a `onlinefakturuj-ftp`: obojí se
připojí a vypíše obsah webové složky, takže hesla sedí. U onlinefakturuj si
sezení pamatovalo `/web`, ale aplikace leží v `/public_html`; proto to
přepsání složky. Zadavatel už nemusel napsat jediný znak.

**Okno místo příkazové řádky.** Zadavatel: *„nechci to složitě dělat přes
cmd"*. Přibyl `hooky/trezor-spravce.ps1` a zástupce **Trezor hesel** na ploše:
seznam uložených cílů a tři tlačítka (přidat ručně, převzít z WinSCP, smazat).
Příkazy v `tajemstvi.ps1` zůstávají, ale jsou pro agenty, ne pro člověka.
Ověřeno spuštěním: okno naběhne a drží, zástupce vede na správný skript.

**Vzhled.** První okno bylo ve WinForms a vypadalo podle toho. Přepsáno do WPF:
tmavé pozadí, Segoe UI, karty s cíli, zelený akcent jako ve steelsetu. Formulář
umí i převzetí sezení z WinSCP, takže se heslo nepíše vůbec: výběr ze seznamu
předvyplní server i uživatele a heslo si skript vezme sám. Tlačítko Vyzkoušet
spojení ověří cíl proti serveru. Ověřeno snímkem okna po každé úpravě, protože
u rozhraní nestačí, že skript nespadne.

**Záhlaví okna.** Kreslí ho Windows, ne WPF, takže zůstávalo bílé i v tmavém
okně. Přepíná se `DwmSetWindowAttribute` s atributem 20 (na starších buildech
19); když ho systém nezná, okno jen zůstane se světlým záhlavím a nic nespadne.

**Trezor není jen na FTP.** Zadavatel: *„já tam chci dávat vše, např. údaje
k DB"*. Záznam má proto druh a podle něj se mění pole i proměnné, které dostane
spuštěný příkaz:

| druh | co se uloží | co dostane příkaz |
|---|---|---|
| ftp | server, uživatel, heslo, protokol, složka | `TAJ_SERVER`, `TAJ_UZIVATEL`, `TAJ_HESLO`, `TAJ_SLOZKA` |
| databaze | server, port, databáze, uživatel, heslo | `TAJ_DB_*` a `MYSQL_PWD`, aby heslo nešlo do příkazu |
| token | hodnota a poznámka, k čemu je | `TAJ_TOKEN` |
| jine | uživatel, heslo, poznámka | `TAJ_HESLO` |

Společné jsou `TAJ_DRUH` a `TAJ_HODNOTA`, takže se dá psát i obecně. Staré
záznamy bez druhu se berou jako `ftp`. Okno mění pole podle druhu a ukládá přes
`tajemstvi.ps1`, kterému tajemství podává rourou; příkazová řádka ho tím pádem
nevidí. Ověřeno uložením databáze i tokenu, vložením do prostředí (příkaz viděl
`MYSQL_PWD` nastavené a délku hesla, hodnotu ne) a odmítnutím `ftp` nad cílem
druhu databáze.

**Úprava a zobrazení.** Zadavatel chtěl umět záznam změnit a taky se podívat,
co v něm je, včetně hesla. Přibyl příkaz `upravit`, který mění jen vyplněná
pole a heslo nechá být, dokud nepřijde nové (a to jen s přepínačem `-ZeVstupu`;
čekání na rouru, do které nikdo nic nepošle, skript jinak zaseklo).

**Zobrazení je jen v okně, ne v příkazu.** Tlačítko Zobrazit údaje otevře detail
s poli, heslo je zakryté hvězdičkami a odkryje se tlačítkem; vedle je kopírování
do schránky. Příkaz na vypsání hesla schválně neexistuje: to, co agent spustí,
by skončilo v jeho výpisu a v přepisu session. Detail si proto okno čte samo.

**Zkouška spojení má limit.** Dřív mohla okno zaseknout, když server neodpovídal.
Nově se po čtyřiceti vteřinách vzdá a řekne to; výstup se čte na pozadí, aby se
čekání nezaseklo na plné rouře.

**Ověřeno.** Uložení cíle druhu jiné, úprava poznámky i hesla (nové heslo má
patnáct znaků, staré mělo devatenáct), výpis a smazání.

**Okno spadlo při kliknutí na Upravit a já to poslal jako hotové.** Otestoval
jsem příkazy pod tím, ale ne samotné kliknutí; okno jsem jen spustil
a vyfotil. Zadavatel na to narazil první.

**Příčina.** V obsluze tlačítka `$cil = $Seznam.SelectedItem.Cil` přepsalo
ovládací prvek `$Cil`, protože PowerShell nerozlišuje velikost písmen. Stejná
past jako u `$SLOZKA` při stavbě trezoru. Prvek se jmenuje `$PoleCil`, takže
to už nejde splést, a stejná chyba by shodila i ukládání a mazání.

**Aby se to nestalo znovu.**

- Obsluhy tlačítek běží v `Bezpecne`: chyba skončí ve stavovém řádku i v logu
  (`%TEMP%	rezor-chyby.log`), okno běží dál.
- Přibyl `hooky/test-trezor.ps1`: přes UI Automation vybere cíl, zmáčkne
  Upravit, přečte vyplněná pole a uloží změny. Tím se testuje to, co dělá
  člověk myší, ne jen příkazy pod tím.

**Ověřeno tímhle testem**: formulář se vyplní vybraným cílem, tlačítko se
přepne na Uložit změny a po uložení stav hlásí `Upraveno: tomas-saroun-me-ftp
(Server, Uzivatel, Protokol, Slozka, Druh)`. Log chyb prázdný.

**Zkouška spojení soudí podle návratového kódu.** Tlačítko *Vyzkoušet spojení*
hlásilo `spojení selhalo. Drwxrwx` u cíle, který ve WinSCP i z příkazové řádky
fungoval. Příčina nebyla v spojení, ale ve vyhodnocení: okno hledalo ve výpisu
slovo „Připojeno", jenže podřízený PowerShell píše v kódování konzole (na
českých Windows cp852), takže diakritika dorazila rozsypaná a porovnání nikdy
nesedělo. Do stavu se pak dostal poslední řádek výpisu, tedy řádek adresáře.

Opraveno dvakrát: `SpustNastroj` nastavuje `StandardOutputEncoding`
i `StandardErrorEncoding` na kódování konzole a vrací objekt `Vystup` + `Kod`,
a o výsledku zkoušky rozhoduje návratový kód WinSCP (`option batch abort` vrací
1 při chybě), ne text. Hlášení nově říká i počet vypsaných položek.

**Ověřeno proklikáním** (`hooky/test-trezor.ps1`, nový krok 4):
`onlinefakturuj-ftp : spojení funguje, vypsáno 8 položek.` a u nahlášeného cíle
`tomas-saroun-me-ftp : spojení funguje, vypsáno 6 položek.` Test si seznam po
uložení načítá znovu, protože překreslení zneplatní staré prvky stromu.

## N31 - Issue má štítek druhu a odpovědného (22. 9. 2026)

**Podnět.** Zadavatel nad seznamem issues ve vyridimestavbu: *„musím nahlásit
špatné fungování kontroly. Issues nemají Assignees a ani labels!"*

**Nález.** Nešlo o chybu kontroly, ale o pravidlo, které nikdy neexistovalo:
standard z N19 popisoval jen tělo. Čtyři issue založené 22. 9. (vyridimestavbu
#4 až #7) proto neměly štítek ani odpovědného a nic to nechytlo. Zároveň se
ukázal opačný nesoulad: onlinefakturuj #11 a #17 a steelset #6 mají sekci
`## Problém`, ale štítek `enhancement`, takže seznam tvrdil něco jiného než
tělo.

**Rozhodnutí.** Zařazení je součást tvaru issue:

- **Štítek druhu je právě jeden** z `bug`, `enhancement`, `documentation`.
  Doménové štítky (`export`, `bez snímku po`) se přidávají navíc.
- **Druh se váže na sekci zadání**: `## Problém` je `bug`, `## Cíl` je
  `enhancement`. `documentation` projde u obojího.
- **Odpovědný je povinný**, u těchhle repozitářů `Terms4Ever`.
- **Výjimky dvě**: issue zavřené bez práce (`duplicate`, `wontfix`, `invalid`)
  a syrový nápad zadavatele, který se zařadí až při přepsání do tvaru.

**Kde se to vynucuje.** Pravidlo žije v `src/tvar-issue.php`
(`problemyZarazeni()`), takže všechna tři místa soudí stejně: hook Claude Code
zastaví zakládání bez `--label` a `--assignee`, workflow `issue-tvar.yml`
předá kontrole štítky a odpovědné z události a `kontrola-issues.php` (1.8.0)
projde při pushi všechny issues repozitáře.

**Opraveno v datech.** vyridimestavbu #4 až #7 dostalo `bug` a odpovědného,
onlinefakturuj #11 a #17 a steelset #6 přeštítkováno na `bug` podle vlastního
těla, steelset #8 a #10 zbaveno zbytkového štítku `tvar nesedí`, který tam
zůstal po opravě těla.

**Dvě pasti při psaní.** Python bez `r''` udělal z `` v regexu hooku
doslovný znak backspace, takže se podmínka nikdy netrefila a hook mlčel;
poznalo se to jen tím, že test vracel kód 0 tam, kde měl vracet 2. A hook
hledal volání kdekoli v příkazu, takže zastavil i zápis téhle dokumentace,
která ten příkaz jen zmiňuje. Nově musí volání stát na začátku příkazu, za
rourou, středníkem nebo uvozovkou.

**Ověřeno.** Osm případů kontroly (bez štítku, správný, druh proti sekci, dva
druhy naráz, `duplicate`, doménový štítek navíc, syrový nápad, volání bez
přepínačů) a šest případů hooku sedí. Všech 50 issues v sedmi repozitářích
prochází kontrolou 1.8.0.

**Ověřeno i v běhu**, ne jen lokálně: nastroje #2 bez štítku druhu workflow
označilo štítkem `tvar nesedí` a napsalo přesný nález, po doplnění štítku
označení samo zmizelo, běh nad zavřením prošel. Workflow čte stav issue přes
API, ne z těla události, takže vidí i štítek přidaný o vteřinu později.

## N32 - Kontrola, která nemohla proběhnout, neprojde (23. 9. 2026)

**Podnět.** Nezávislý audit od jiného agenta (16 tvrzení A01 až A16). Zadavatel:
*„analyzuj a zjisti jestli to je pravdivé"*, pak *„Takže co z toho plánuješ
opravit?"* Ověřeno proti dnešnímu stavu reprodukcemi v dočasných
repozitářích; výsledek v `C:\laragon\www\audit-2026-09-23\`. Nepravdivé nebylo
ani jedno tvrzení, dvě byla nadhodnocená, tři ve skutečnosti horší.

**Rozhodnutí zadavatele.** Opravit celý plán. Nasazení tří webů bude čekat na
kontroly, s nouzovou výjimkou. Profil prohlížeče v LabProtocolu se jen vyřadí
ze sledování, historie se nepřepisuje.

**Co bylo špatně v kontrolách a proč.** Společný kořen: kontrola, která neměla
co zkontrolovat, skončila kódem 0. Přímý rozpor s pravidlem „nález nesmí
projít tiše" z `AGENTS.md`.

- **Zapnutá kontrola bez `docs/` prošla.** Existence složky se testovala dřív
  než zapnutí.
- **Neexistující cesta prošla** u dokumentace i migrací, u dokumentace i cesta
  z Git Bashe (`/c/...`), kterou PHP na Windows nezná. Při ověřování auditu jsem
  na to sám naletěl a málem vydal falešný nález.
- **Nepřečtené issues prošly.** Chyba `gh`, nesmysl místo JSON i zamítnutý
  přístup skončily zeleně. U Igrisu to běželo v provozu: log hlásil
  „nepodařilo se načíst issues" a běh byl zelený, protože workflow nedávalo
  tokenu `issues: read`.
- **Neznámý základ rozsahu byl jen upozornění.** Pull request a nová větev
  pravidla o dávce neuplatnily vůbec.
- **Do `docs/` stačilo přidat obrázek**, nebo dokonce něco smazat, a pravidlo
  o aktualizaci dokumentace bylo spokojené.
- **Přejmenování hotové migrace prošlo.** Git ho hlásí jako `R100`, kontrola
  znala jen `M` a `D`. Spouštěč přitom pozná hotovou migraci jen podle názvu,
  otisk sice ukládá, ale nikdy neporovná.
- **Generátor bral verzi z `package.json` dřív než z `app.json`**, u LabProtocolu
  tak stav tvrdil 1.0.0 místo 1.0.2.
- **Workflow issues reagovalo jen na text a zavření**, odebrání štítku druhu
  (pravidlo N31) se nepoznalo, a přísnost bralo z události, ne ze stavu.
- **Tenhle stav projektu tvrdil „jednou denně"**, přestože denní běh zrušila
  N21. Pravidlo o pravdivé dokumentaci jsem porušil vlastním textem.

**Proč to nikdo nechytil.** Kontroly neměly jediný test. CI je pouštělo samy na
sebe, což ověří, že na čistém repozitáři projdou, ne že chybu chytí. Pravidlo
„pravidlo bez testu neexistuje" stálo v `AGENTS.md` a nic ho nevynucovalo.

**Co se změnilo.**

- `tests/spust.php`: 38 případů, napřed napsaných tak, aby dnešní kontroly
  shodily (15 padalo), teprve pak opravy. Běží v CI a v pre-push hooku.
- Dokumentace 1.6.0, migrace 1.1.0, issues 1.9.0: neexistující cesta, chybějící
  `docs/`, nepřečtené issues i neznámý základ jsou chyba. Za změnu dokumentace
  se počítá jen přidaný nebo upravený `.md` mimo `snimky/` a `prilohy/`.
- `readme.yml` určí rozsah i u pull requestu (základ PR) a nové větve
  (společný předek s výchozí větví). Ověřeno nasucho na šesti situacích.
- `issue-tvar.yml` bere přísnost ze stavu issue a souběžné běhy téhož issue
  ruší; volající reagují i na štítky, odpovědného a znovuotevření.
- Pre-push hook se při chybějícím PHP, skriptu nebo pravidlech commitů zastaví,
  u nové větve měří od společného předka a u nastroje pustí testy
  a `tests/kompatibilita.php`.

**Kompatibilita našla dva projekty hned.** LabProtocol (blok verze po změně
generátoru) a Igris, který by novými ani starými pravidly neprošel: chyběl
`AGENTS.md` a v `docs/` leželo 25 obrázků. Igris to nesl od 20. 9., zelený běh
byl ze 13:35 a pravidlo přibylo ve 20:28. Opraveno v projektech (R234 v Igrisu).

**Co jsem vědomě nechal.** Ochrana větví (N16). Atomické přepnutí verze při
nasazení: FTP účet nesmí zapisovat nad složku webu, takže vedle nejde nahrát
nic. Červený běh workflow u issue s nálezem: signálem je štítek, červený běh
by posílal e-mail při každé úpravě.

**Doplněno téhož dne: spouštěč migrací.** Statická kontrola přejmenování
nezastaví, když se na ni zapomene, proto se to ověřilo i za běhu na
jednorázové MariaDB z Laragonu: změněná hotová migrace se tiše přeskočila
(„Žádná nová migrace") a přejmenovaná se pustila znovu a spadla na
`Table 'a' already exists`; s `INSERT` by zdvojila data. Spouštěč teď
porovnává otisky: přejmenovanou migraci pozná podle otisku, který zná pod
starým jménem, a nepustí ji; změněnou zatím jen ohlásí. Zastavovat začne, až
výpis z nasazení ukáže, že na produkci žádný starý nesoulad není. Čtyři nové
případy v `tests/spust.php` běží v CI nad MySQL z běhového prostředí.

**Doplněno téhož dne: změněná hotová migrace nasazení zastaví.** Výpisy
z nasazení onlinefakturuj (15:54) a vyridimestavbu (15:50) po zavedení
porovnávání ukázaly „Žádná nová migrace, databáze je aktuální" a ani jedno
hlášení o nesouladu otisku. Starý nesoulad na produkci tedy není a spouštěč
teď při změněné hotové migraci nasazení zastaví, místo aby ji jen ohlásil.

**Doplněno téhož dne: vlastní chyba v opravě.** Při první ostré zkoušce
nového pre-push hooku hlásila kontrola dokumentace „0 dokumentů" místo dvou.
Pravidlo o dávce v 1.6.0 použilo pro změněné dokumenty stejnou proměnnou jako
seznam všech dokumentů a přepsalo ji. Za pravidlem se už nic nekontrolovalo,
takže šlo jen o číslo ve výsledné hlášce, ne o přeskočenou kontrolu. Opraveno
v 1.6.1 a případ „dávka s kódem a změněným dokumentem" nově hlídá i počet;
proti 1.6.0 padá, proti 1.6.1 projde.

## N33 - Z pracovní nadstavby: Windows v CI, snímky na commit, zavření s důkazem (23. 9. 2026)

**Podnět.** Zadavatel nechal prověřit `nastroje-prace` (osobní nadstavba pro
pracovní projekty se SVN a Mantis, vznikla 23. 9. na jiném počítači) a z návrhů
vybral tři věci k převzetí. Kontrolu citlivých souborů nevybral.

**Windows v CI, akce na otisk.** Kontroly se pouštějí hlavně na Windows (hook
Claude Code, pre-push), ale CI je zkoušelo jen na Linuxu; sedm případů (hook,
cesta `/c/...`) tam vůbec neběželo, přitom chyby specifické pro Windows byly
nejčastější. CI nastroje má job `testy-windows` (PHP 8.3 přes setup-php).
Akce ve sdílených workflow jsou připnuté na otisk commitu místo značky
(`actions/checkout` na otisk vydání 7.0.1) a stažený kód si nenechává
přihlašovací údaje: značku jde přesunout na jiný kód, otisk ne, a `issue-tvar`
smí zapisovat do issues.

**Snímky odkazem na commit.** Odkaz `blob/main/...` se rozbije, když se soubor
přesune, a neřekne, kterou verzi snímek dokládá. Od 23. 9. musí nový obsah
odkazovat na otisk commitu; kontrola issues navíc ověří, že v tom commitu
snímek je, že je to obrázek (PNG, JPEG, WebP) a že před a po nejsou tentýž
soubor. Starší obsah jen upozorní, hook i workflow dostávají datum vzniku issue.
Nadpis upozornění už netvrdí jediné datum, které u pozdějších pravidel neplatilo.

**Zavření s důkazem.** `zavrit-issue.php` zavře issue, jen když sedí tvar
a zařazení, checklist je odškrtaný (nebo komentář říká, proč bod zůstal
schválně), ke snímku před je snímek po, všechny běhy commitu na výchozí větvi
doběhly úspěšně (kontroly, testy i nasazení) a závěrečný komentář odkazuje na
ten commit. Bez `--zavrit` jen posoudí. Obejít to jde dvěma cestami a obě jsou
zavřené: holé `gh issue close` a zavření přes `gh api` zastaví hook, zprávu
commitu s „Closes #N" nebo „Fixes owner/repo#N" zastaví pravidla commitu.

**Past při testech.** Atrapa `gh.cmd` s `%~dp0` na Windows ukazovala do
aktuální složky, ne ke skriptu, když ji cmd našel přes PATH a jméno bylo
v uvozovkách. Atrapa teď nese absolutní cestu.

**Ověřeno.** 67 případů: 9 na snímky, 13 na zavírání, 3 nové na hook; proti
starým kontrolám nové případy padaly. Pravidla commitu vyzkoušená na šesti
zprávách (odkaz „(#12)" projde, „Closes #12", „Fixes: repo#3" a „Resolved #4"
ne, slova jako „prefix" a „fixture" nevadí). Kompatibilita: všech 7 projektů
projde; kontrola kompatibility teď klonuje celou historii, protože snímek se
ověřuje v commitu, na který odkaz míří.

**Doplněno téhož dne: staré odkazy převedené.** Aby upozornění nezahlcovala
každý výpis, převedlo se všech 39 odkazů na `blob/main` v 18 textech
(steelset, onlinefakturuj, vyridimestavbu) na otisk commitu, ve kterém snímek
do repozitáře přibyl; před zápisem se ověřilo, že v něm soubor leží, a mění
se jen adresa. Kontrola issues 1.10.0 ve všech třech repozitářích prošla bez
jediného upozornění a 15 běhů workflow, které úpravy vyvolaly, je zelených.
První pokus narazil na známou past: `escapeshellarg` na Windows mění `%` na
mezeru, takže `--format=%H` nefungovalo; spouští se proto polem bez shellu.
