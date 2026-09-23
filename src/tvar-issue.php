<?php
/**
 * Tvar těla issue. Jedno místo pro pravidla, která používá kontrola issues
 * (kontrola-issues.php), kontrola jednoho těla před založením
 * (kontrola-tvaru-issue.php) i workflow reagující na událost issues.
 *
 * Pravidla jsou v docs/03-rozhodovaci-dennik.md pod N19 a N20.
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
 * souboru LF; bez tohohle by `` zůstal v nadpisu a každá sekce by vypadala
 * jako sekce navíc.
 */
function sjednotRadky(string $text): string
{
    return str_replace(["
", ""], "
", $text);
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
