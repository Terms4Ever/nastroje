<?php
/**
 * Tvar těla issue. Jedno místo pro pravidla, která používá kontrola issues
 * (kontrola-issues.php), kontrola jednoho těla před založením
 * (kontrola-tvaru-issue.php) i workflow reagující na událost issues.
 *
 * Pravidla jsou v docs/03-rozhodovaci-dennik.md pod N19, N20, N33 a N39.
 */
declare(strict_types=1);

/** První nadpis: jedno z toho. Issue říká buď co je špatně, nebo čeho chceme dosáhnout. */
const NADPISY_ZADANI = ['## Problém', '## Cíl'];

/** Sekce s checklistem, bez ní se nepozná, kdy je hotovo. */
const NADPIS_HOTOVO = '## Hotovo, když';

/**
 * Jediné povolené sekce, v jediném povoleném pořadí. Volný tvar je přesně to,
 * co se tímhle ruší: dřív měly issues přes dvacet různých nadpisů.
 */
const NADPISY_POVOLENE = [
    '## Problém',
    '## Cíl',
    '## Jak to poznat',
    '## Hotovo, když',
    '## Kde to žije',
    '## Snímky',
];

/** Pořadí sekcí. Problém a Cíl se vylučují, proto mají stejné místo. */
const PORADI_SEKCI = [
    '## Problém' => 0,
    '## Cíl' => 0,
    '## Jak to poznat' => 1,
    '## Hotovo, když' => 2,
    '## Kde to žije' => 3,
    '## Snímky' => 4,
];

/** Delší tělo znamená, že se do issue psal rozbor. Ten patří do docs/. */
const MAX_RADKU_TELA = 40;

/**
 * Štítky druhu. Každé issue nese právě jeden: v seznamu issues se jinak
 * nepozná, co je chyba a co nová funkce, a filtr podle štítku nemá co chytat.
 * Druh se váže na sekci zadání, aby si štítek a tělo neprotiřečily.
 */
const STITKY_DRUHU = ['bug', 'enhancement', 'documentation'];

/**
 * Zavřené bez práce. Issue, které je duplikát nebo se dělat nebude, nemá
 * druh ani odpovědného, protože se na něm nic neudělalo.
 */
const STITKY_BEZ_PRACE = ['duplicate', 'wontfix', 'invalid'];

/**
 * Zavřené bez práce: nic se nedělalo, checklist se proto ani neplní.
 * Platí pro všechny tři kontroly zavřeného issue (workflow Tvar issue,
 * kontrola-issues.php, zavrit-issue.php), ať se pravidlo nerozejde.
 */
function jeBezPrace(array $stitky): bool
{
    return array_intersect(STITKY_BEZ_PRACE, $stitky) !== [];
}

/**
 * Komentář říká, že bod checklistu zůstává nezaškrtnutý schválně (třeba jde
 * ověřit jen na zařízení). Obě pořadí slov: „schválně nezaškrtnutý“
 * i „nezaškrtnutý schválně“.
 */
function vedomaVyjimkaChecklistu(string $text): bool
{
    return preg_match('/schváln[ěe][^.]{0,80}(nezaškrt|neodškrt)|(nezaškrt|neodškrt)[^.]{0,80}schváln[ěe]/iu', $text) === 1;
}

/**
 * Od kdy snímek v issue odkazuje na otisk commitu, ne na větev (N33).
 * Odkaz na blob/main se rozbije, když se soubor přesune nebo přejmenuje,
 * a neřekne, kterou verzi snímek dokládá. Starší obsah jen upozorní.
 */
const OD_SNIMKU_S_OTISKEM = '2026-09-23';

/**
 * Co je na tvaru těla špatně. Prázdné pole znamená, že je tvar v pořádku.
 * Hlášky jsou bez čísla issue, to si připíše volající.
 */
function problemyTvaru(string $telo): array
{
    if (jeSyroveIssue($telo)) {
        return [];
    }

    $problemy = [];
    $videne = [];
    $posledni = -1;

    foreach (nadpisy($telo) as $nadpis) {
        if (!in_array($nadpis, NADPISY_POVOLENE, true)) {
            $problemy[] = 'má sekci navíc: ' . sekce($nadpis);
            continue;
        }
        if (isset($videne[$nadpis])) {
            $problemy[] = 'má sekci ' . sekce($nadpis) . ' dvakrát';
            continue;
        }
        $videne[$nadpis] = true;

        $misto = PORADI_SEKCI[$nadpis];
        if ($misto < $posledni) {
            $problemy[] = 'má sekci ' . sekce($nadpis) . ' na špatném místě';
        }
        $posledni = max($posledni, $misto);
    }

    if (isset($videne['## Problém'], $videne['## Cíl'])) {
        $problemy[] = 'má Problém i Cíl, patří tam jen jedno';
    }
    if (!isset($videne['## Problém']) && !isset($videne['## Cíl'])) {
        $problemy[] = 'nemá úvodní sekci ' . implode(' ani ', NADPISY_ZADANI);
    }

    if (!isset($videne[NADPIS_HOTOVO])) {
        $problemy[] = 'nemá sekci ' . sekce(NADPIS_HOTOVO);
    } elseif (checklist($telo) === []) {
        $problemy[] = 'má sekci ' . sekce(NADPIS_HOTOVO) . ', ale bez odškrtávacího seznamu';
    } elseif (($mimo = bodyMimoHotovo($telo)) > 0) {
        $problemy[] = 'má ' . bodu($mimo) . ' checklistu mimo sekci ' . sekce(NADPIS_HOTOVO);
    }

    $radku = radkyBezPrazdnych($telo);
    if ($radku > MAX_RADKU_TELA) {
        $problemy[] = "má tělo o $radku řádcích, limit je " . MAX_RADKU_TELA . '; rozbor patří do docs/';
    }

    return $problemy;
}

/**
 * Syrový nápad zadavatele: tělo bez jediného nadpisu. Není to chyba, je to
 * zadání čekající na přepsání do tvaru.
 */
function jeSyroveIssue(string $telo): bool
{
    return nadpisy($telo) === [];
}

/**
 * Konce řádků na jeden tvar. Tělo z webového formuláře má CRLF, tělo ze
 * souboru LF; bez tohohle by `\r` zůstal v nadpisu a každá sekce by vypadala
 * jako sekce navíc.
 */
function sjednotRadky(string $text): string
{
    return str_replace(["\r\n", "\r"], "\n", $text);
}

/** Nadpisy druhé úrovně v pořadí, jak jsou v těle. */
function nadpisy(string $telo): array
{
    $telo = sjednotRadky($telo);

    preg_match_all('/^##[ \t]+(\S.*?)[ \t]*$/m', $telo, $shody);

    return array_map(static fn (string $n): string => '## ' . $n, $shody[1] ?? []);
}

/** Nadpis bez mřížek, do hlášky. */
function sekce(string $nadpis): string
{
    return trim($nadpis, '# ');
}

/** Povolené sekce v pořadí, jedním řádkem. */
function povoleneSekce(): string
{
    return implode(', ', array_map('sekce', NADPISY_POVOLENE));
}

/** Kolik bodů checklistu leží mimo sekci "Hotovo, když". */
function bodyMimoHotovo(string $telo): int
{
    $telo = sjednotRadky($telo);
    $sekce = '';
    $mimo = 0;
    foreach (preg_split('/\R/', $telo) ?: [] as $radek) {
        if (preg_match('/^##[ \t]+(\S.*?)[ \t]*$/', $radek, $shoda) === 1) {
            $sekce = '## ' . $shoda[1];
            continue;
        }
        if (preg_match('/^\s*[-*]\s+\[( |x|X)\]/', $radek) === 1 && $sekce !== NADPIS_HOTOVO) {
            $mimo++;
        }
    }

    return $mimo;
}

/** Stav jednotlivých bodů checklistu: true = odškrtnuto. */
function checklist(string $telo): array
{
    $telo = sjednotRadky($telo);
    preg_match_all('/^\s*[-*]\s+\[( |x|X)\]/m', $telo, $shody);

    return array_map(static fn (string $z): bool => strtolower($z) === 'x', $shody[1] ?? []);
}

/**
 * Délka komentáře v řádcích. Řádek, který je jen vložený obrázek, se nepočítá:
 * snímky před a po jsou důkaz, ne ukecanost, a limit je nemá trestat.
 */
function radkyKomentare(string $text): int
{
    $pocet = 0;
    foreach (preg_split('/\R/', trim(sjednotRadky($text))) ?: [] as $radek) {
        $radek = trim($radek);
        if ($radek === '' || jeVlozenyObrazek($radek)) {
            continue;
        }
        $pocet++;
    }

    return $pocet;
}

/**
 * Zmiňuje text nástroj, kterým se psal? Cesty a názvy souborů se nepočítají:
 * `.claude/launch.json` je součást projektu, ne podpis pod prací (nález
 * u steelsetu #10).
 */
function zminujeNastroj(string $text): bool
{
    $text = sjednotRadky($text);
    $text = preg_replace('/```.*?```/s', ' ', $text) ?? $text;      // bloky kódu
    $text = preg_replace('/`[^`]*`/', ' ', $text) ?? $text;          // kód v řádku
    $text = preg_replace('#[\w./-]*[.]claude[\w./-]*#i', ' ', $text) ?? $text;
    $text = preg_replace('/claude-mem/i', ' ', $text) ?? $text;

    return preg_match('/claude|generated with|jako AI/i', $text) === 1;
}

/** Řádek je jen vložený obrázek: ![popis](adresa) */
function jeVlozenyObrazek(string $radek): bool
{
    return preg_match('/^!\[[^\]]*\]\([^)]+\)$/', trim($radek)) === 1;
}

/**
 * Snímky zmíněné jen odkazem, ne vloženým obrázkem. GitHub odkaz vykreslí jako
 * text, takže v issue není vidět nic; 21. 9. 2026 tak skončily čtyři dvojice
 * snímků u vyridimestavbu #4.
 */
function snimkyJenOdkazem(string $text): array
{
    // Oddělovač #, protože ve vzoru je lomítko z cesty docs/snimky.
    preg_match_all('#(?<!!)\[[^\]]*\]\(([^)]*docs/snimky/[^)]*)\)#', sjednotRadky($text), $shody);

    return array_values(array_unique($shody[1] ?? []));
}

/** Počet řádků s textem. Prázdné řádky se nepočítají. */
function radkyBezPrazdnych(string $text): int
{
    $text = sjednotRadky($text);
    $radky = preg_split('/\R/', trim($text)) ?: [];

    return count(array_filter($radky, static fn (string $r): bool => trim($r) !== ''));
}

/** Skloňování: 1 bod, 2 body, 5 bodů. */
function bodu(int $pocet): string
{
    if ($pocet === 1) {
        return '1 bod';
    }

    return $pocet < 5 ? "$pocet body" : "$pocet bodů";
}

/**
 * Zařazení issue: štítek druhu a odpovědný. Bez nich je seznam issues jen
 * hromada nadpisů - nepozná se, co je chyba, ani kdo to má na stole.
 *
 * Syrový nápad zadavatele se netrestá: zadavatel píše větu, ne štítky.
 * Zařadí se, až ho agent přepíše do tvaru.
 */
function problemyZarazeni(array $stitky, array $odpovedni, string $telo): array
{
    if (jeSyroveIssue($telo)) {
        return [];
    }
    if (array_intersect(STITKY_BEZ_PRACE, $stitky) !== []) {
        return [];
    }

    $problemy = [];
    $druhy = array_values(array_intersect(STITKY_DRUHU, $stitky));

    if ($druhy === []) {
        $problemy[] = 'nemá štítek druhu, jeden z: ' . implode(', ', STITKY_DRUHU);
    } elseif (count($druhy) > 1) {
        $problemy[] = 'má víc štítků druhu (' . implode(', ', $druhy) . '), platí právě jeden';
    } else {
        $sekce = sekceZadani($telo);
        if ($sekce === '## Problém' && $druhy[0] === 'enhancement') {
            $problemy[] = 'má sekci Problém, ale štítek enhancement; chyba se štítkuje bug';
        }
        if ($sekce === '## Cíl' && $druhy[0] === 'bug') {
            $problemy[] = 'má sekci Cíl, ale štítek bug; nová funkce se štítkuje enhancement';
        }
    }

    if ($odpovedni === []) {
        $problemy[] = 'nemá odpovědného';
    }

    return $problemy;
}

/**
 * Kterým nadpisem issue začíná: `## Problém` u chyby, `## Cíl` u nové funkce.
 * Vrací prázdný řetězec, když tam není ani jeden.
 */
function sekceZadani(string $telo): string
{
    foreach (nadpisy($telo) as $nadpis) {
        if (in_array($nadpis, NADPISY_ZADANI, true)) {
            return $nadpis;
        }
    }

    return '';
}

/**
 * Snímky vložené jako obrázek z repozitáře na GitHubu.
 *
 * @return list<array{adresa: string, ref: string, cesta: string, druh: ?string, pripona: ?string}>
 */
function snimkyObrazky(string $text): array
{
    preg_match_all(
        '#!\[[^\]]*\]\((https://github\.com/[^/\s)]+/[^/\s)]+/blob/([^/\s)]+)/(docs/snimky/[^\s)?\#]+)[^\s)]*)\)#',
        sjednotRadky($text),
        $shody,
        PREG_SET_ORDER
    );

    $snimky = [];
    foreach ($shody as $shoda) {
        $cesta = rawurldecode($shoda[3]);
        $druh = preg_match('#/(pred|po)-([^/]+)$#', $cesta, $casti) === 1 ? $casti : [null, null, null];
        $snimky[] = [
            'adresa' => $shoda[1],
            'ref' => $shoda[2],
            'cesta' => $cesta,
            'druh' => $druh[1],
            'pripona' => $druh[2],
        ];
    }

    return $snimky;
}

/** Je odkaz na snímek připnutý na otisk commitu (40 znaků hex)? */
function jeOtiskCommitu(string $ref): bool
{
    return preg_match('/^[0-9a-f]{40}$/', $ref) === 1;
}

/**
 * Problémy se snímky v jednom textu, které jdou poznat bez repozitáře:
 * odkaz na větev místo otisku commitu.
 *
 * @return string[]
 */
function problemySnimkuVTextu(string $text): array
{
    $problemy = [];
    foreach (snimkyObrazky($text) as $snimek) {
        if (!jeOtiskCommitu($snimek['ref'])) {
            $problemy[] = sprintf(
                'snímek %s odkazuje na větev %s, ne na otisk commitu; použij adresu'
                . ' .../blob/<otisk>/%s?raw=1 (otisk dá git log -1 --format=%%H -- %s)',
                $snimek['cesta'],
                $snimek['ref'],
                $snimek['cesta'],
                dirname($snimek['cesta'])
            );
        }
    }

    return $problemy;
}

/** Začíná obsah podpisem PNG, JPEG nebo WebP? */
function jeObrazek(string $obsah): bool
{
    return str_starts_with($obsah, "\x89PNG\r\n\x1a\n")
        || str_starts_with($obsah, "\xff\xd8\xff")
        || (substr($obsah, 0, 4) === 'RIFF' && substr($obsah, 8, 4) === 'WEBP');
}

// --------------------------------------------------------------------------
// N39: snímky v tabulce, průběžné odškrtání, práce po zavření
// --------------------------------------------------------------------------

/**
 * Od kdy snímky v issue stojí v tabulce | Co | Před | Po | (N39). Pod sebou
 * nešlo poznat, co je před a co po: popis obrázku GitHub neukazuje, takže
 * onlinefakturuj #37 a #44 měly pod sebou šest obrázků bez jediného
 * viditelného slova. Starší obsah jen upozorní.
 */
const OD_TABULKY_SNIMKU = '2026-09-29';

/** Záhlaví tabulky snímků: co řádek ukazuje, stav před a stav po. */
const ZAHLAVI_SNIMKU = ['Co', 'Před', 'Po'];

/** Tabulka do hlášek, ať je hned vidět, jak má vypadat. */
const VZOR_TABULKY_SNIMKU = '| Co | Před | Po |';

/** Štítek, který issue vymaní z pravidla o snímku po: nemá ho kdo pořídit. */
const STITEK_BEZ_PO = 'bez snímku po';

/**
 * Od kdy commit, který pracuje na zavřeném issue, zastaví kontrolu (N39).
 * Na onlinefakturuj #40 přišel unikátní index čtyři hodiny po zavření
 * a důkaz při zavření ho nepokryl. Starší commity jen upozorní.
 */
const OD_PRACE_PO_ZAVRENI = '2026-09-29';

/**
 * Commity bližší než pět minut jsou jeden krok práce (kód a hned zápis do
 * deníku), mezi nimi se odškrtávat nemá kdy.
 */
const MIN_ODSTUP_COMMITU = 300;

/** Snímek z docs/snimky vložený jako obrázek, jakoukoli adresou. */
const VZOR_VLOZENEHO_SNIMKU = '#!\[[^\]]*\]\(([^)\s]*docs/snimky/[^)\s]*)\)#';

/**
 * Vložené snímky v textu jako cesty od docs/snimky, bez ?raw=1.
 *
 * @return list<string>
 */
function vlozeneSnimky(string $text): array
{
    preg_match_all(VZOR_VLOZENEHO_SNIMKU, sjednotRadky($text), $shody);

    return array_map(static function (string $adresa): string {
        $cesta = substr($adresa, (int) strpos($adresa, 'docs/snimky/'));

        return rawurldecode((string) preg_replace('/[?#].*$/', '', $cesta));
    }, $shody[1] ?? []);
}

/** Stav na snímku podle názvu souboru: pred, po, nebo null. */
function druhSnimku(string $cesta): ?string
{
    return preg_match('#/(pred|po)-[^/]+$#', $cesta, $shoda) === 1 ? $shoda[1] : null;
}

/** Buňky řádku markdownové tabulky, oříznuté. */
function bunkyTabulky(string $radek): array
{
    $radek = trim($radek);
    if (str_starts_with($radek, '|')) {
        $radek = substr($radek, 1);
    }
    if (str_ends_with($radek, '|')) {
        $radek = substr($radek, 0, -1);
    }

    return array_map('trim', explode('|', $radek));
}

/** Oddělovač pod záhlavím tabulky: |---|:---:|---| */
function jeOddelovacTabulky(array $bunky): bool
{
    foreach ($bunky as $bunka) {
        if (preg_match('/^:?-{3,}:?$/', $bunka) !== 1) {
            return false;
        }
    }

    return $bunky !== [];
}

/**
 * Snímky v těle stojí v tabulce | Co | Před | Po | v sekci Snímky (N39).
 * Řádek je jeden pár: v prvním sloupci, co ukazuje, vlevo stav před, vpravo
 * stav po. Buňka bez snímku zůstane prázdná. $parovat hlídá zavírané issue:
 * každý řádek se snímkem před má i snímek po.
 *
 * @return string[]
 */
function problemyTabulkySnimku(string $telo, bool $parovat = false): array
{
    if (vlozeneSnimky($telo) === []) {
        return [];
    }

    $problemy = [];
    $mimo = [];
    $sekce = '';
    $vTabulce = false;
    foreach (explode("\n", sjednotRadky($telo)) as $radek) {
        if (preg_match('/^##[ \t]+(\S.*?)[ \t]*$/', $radek, $shoda) === 1) {
            $sekce = '## ' . $shoda[1];
            $vTabulce = false;
            continue;
        }
        if (!str_starts_with(trim($radek), '|')) {
            $vTabulce = false;
            array_push($mimo, ...vlozeneSnimky($radek));
            continue;
        }

        $bunky = bunkyTabulky($radek);
        if ($bunky === ZAHLAVI_SNIMKU) {
            $vTabulce = $sekce === '## Snímky';
            continue;
        }
        if (jeOddelovacTabulky($bunky)) {
            continue;
        }
        if (!$vTabulce) {
            array_push($mimo, ...vlozeneSnimky($radek));
            continue;
        }
        if (count($bunky) !== 3) {
            $problemy[] = 'řádek tabulky snímků má ' . count($bunky) . ' sloupce, patří tam tři: ' . VZOR_TABULKY_SNIMKU;
            continue;
        }

        [$co, $vlevo, $vpravo] = $bunky;
        $popis = $co === '' ? '(bez popisu)' : $co;
        if ($co === '' || vlozeneSnimky($co) !== []) {
            $problemy[] = 'řádek tabulky snímků nemá v prvním sloupci (Co) slovy, co ukazuje';
        }
        $pred = vlozeneSnimky($vlevo);
        $po = vlozeneSnimky($vpravo);
        foreach ($pred as $cesta) {
            if (druhSnimku($cesta) !== 'pred') {
                $problemy[] = sprintf('snímek %s je ve sloupci Před, patří tam jen pred-*.png', basename($cesta));
            }
        }
        foreach ($po as $cesta) {
            if (druhSnimku($cesta) !== 'po') {
                $problemy[] = sprintf('snímek %s je ve sloupci Po, patří tam jen po-*.png', basename($cesta));
            }
        }
        if (count($pred) > 1 || count($po) > 1) {
            $problemy[] = sprintf('řádek „%s“ má v jedné buňce víc snímků; každý pár patří na vlastní řádek', $popis);
        }
        if ($pred === [] && $po === []) {
            $problemy[] = sprintf('řádek „%s“ nemá žádný snímek', $popis);
        }
        if ($parovat && $pred !== [] && $po === []) {
            $problemy[] = sprintf(
                'řádek „%s“ má snímek před, ale ne po; bez něj není vidět, co se změnilo (když ho nemá kdo pořídit, dej issue štítek "%s")',
                $popis,
                STITEK_BEZ_PO
            );
        }
    }

    if ($mimo !== []) {
        $problemy[] = sprintf(
            'snímky stojí mimo tabulku (%s); v sekci Snímky patří do tabulky %s, každý pár na jeden řádek: popis, před vlevo, po vpravo',
            implode(', ', array_map('basename', $mimo)),
            VZOR_TABULKY_SNIMKU
        );
    }

    return $problemy;
}

/**
 * Komentář snímek nevkládá. Snímky mají jedno místo, tabulku v těle issue;
 * rozdělené mezi tělo a komentáře se pár před a po nedá porovnat (N39).
 *
 * @return string[]
 */
function problemySnimkuVKomentari(string $text): array
{
    $snimky = vlozeneSnimky($text);
    if ($snimky === []) {
        return [];
    }

    return [sprintf(
        'vkládá snímek (%s); snímky patří do tabulky %s v sekci Snímky těla issue, komentář na ni jen odkáže',
        implode(', ', array_map('basename', $snimky)),
        VZOR_TABULKY_SNIMKU
    )];
}

/**
 * Issues, na kterých commit pracuje: číslo v závorce v předmětu, "(#12)" nebo
 * "(#12, #13)". Zmínka bez závorky ("deník u #12") práci nehlásí (N39).
 *
 * @return list<int>
 */
function praceNaIssues(string $predmet): array
{
    preg_match_all('/\(([^()]*#\d+[^()]*)\)/', $predmet, $zavorky);
    $cisla = [];
    foreach ($zavorky[1] ?? [] as $obsah) {
        preg_match_all('/#(\d+)\b/', $obsah, $shody);
        foreach ($shody[1] as $cislo) {
            $cisla[] = (int) $cislo;
        }
    }

    return array_values(array_unique($cisla));
}

/**
 * Komentář vysvětluje, že body checklistu splnil až poslední commit
 * a předchozí jen připravil (třeba test). Pak se na konci odškrtává právem.
 */
function vedomaVyjimkaOdskrtani(string $text): bool
{
    return preg_match('/až\s+poslední\s+commit/iu', $text) === 1;
}
