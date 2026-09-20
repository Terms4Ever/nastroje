<?php
/**
 * Kontrola tvaru jednoho těla issue, ještě než issue vznikne.
 *
 *     php kontrola-tvaru-issue.php <soubor s tělem> [--prisne] [--titulek "..."]
 *     php kontrola-tvaru-issue.php - [--prisne]        # tělo na vstupu
 *
 * Volá to hook Claude Code před `gh issue create` a workflow reagující na
 * událost issues. Pravidla žijí v src/tvar-issue.php, tenhle soubor je jen
 * obal: přečti tělo, vypiš, co nesedí, vrať kód 1.
 *
 * --prisne navíc zakáže syrové tělo bez jediného nadpisu. Syrový nápad smí
 * napsat zadavatel, agent ne: ten tvar zná a má ho dodat rovnou.
 * --zavrene hlídá, že zavírané issue nemá neodškrtnutý bod.
 */
declare(strict_types=1);

require_once __DIR__ . '/src/tvar-issue.php';

$soubor = null;
$prisne = false;
$zavrene = false;
$titulek = '';
for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if ($arg === '--prisne') {
        $prisne = true;
        continue;
    }
    if ($arg === '--zavrene') {
        $zavrene = true;
        continue;
    }
    if ($arg === '--titulek') {
        $titulek = $argv[++$i] ?? '';
        continue;
    }
    $soubor ??= $arg;
}

if ($soubor === null) {
    fwrite(STDERR, "Použití: php kontrola-tvaru-issue.php <soubor s tělem> [--prisne]\n");
    exit(2);
}

$telo = $soubor === '-' ? (string) stream_get_contents(STDIN) : @file_get_contents($soubor);
if ($telo === false) {
    fwrite(STDERR, "Tělo issue se nepodařilo přečíst: $soubor\n");
    exit(2);
}

$problemy = problemyTvaru($telo);

if ($zavrene) {
    $neodskrtnute = count(array_filter(checklist($telo), static fn (bool $h): bool => !$h));
    if ($neodskrtnute > 0) {
        $problemy[] = 'se zavírá, ale ' . bodu($neodskrtnute) . ' v checklistu zůstalo neodškrtnutých';
    }
}
if ($prisne && jeSyroveIssue($telo)) {
    $problemy[] = 'nemá jedinou sekci; syrový nápad smí napsat zadavatel, ne agent';
}
if (str_contains($telo, "\u{2013}") || str_contains($telo, "\u{2014}")) {
    $problemy[] = 'obsahuje dlouhou nebo poloviční pomlčku, píše se jen krátká';
}
if (preg_match('/claude|generated with|jako AI\b/i', $telo . ' ' . $titulek) === 1) {
    $problemy[] = 'zmiňuje nástroj, kterým se text psal';
}

if ($problemy === []) {
    echo "Tvar issue sedí.\n";
    exit(0);
}

fwrite(STDERR, "Tělo issue neodpovídá tvaru:\n\n");
foreach ($problemy as $problem) {
    fwrite(STDERR, "  - $problem\n");
}
fwrite(STDERR, "\nSekce, jiné nejsou: " . povoleneSekce() . "\n");
fwrite(STDERR, "Vzor: nastroje/sablony/issue-ukol.md\n");
exit(1);
