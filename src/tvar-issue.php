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
