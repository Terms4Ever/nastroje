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
