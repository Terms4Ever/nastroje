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
 * --komentar kontroluje komentář místo těla: délku, zmínky a pomlčky.
 * --stitky a --odpovedni (čárkou oddělené seznamy) navíc ověří zařazení:
 * štítek druhu a odpovědného. Ty nejsou v těle, proto se předávají zvlášť;
 * prázdná hodnota znamená, že issue žádné nemá.
 */
declare(strict_types=1);

require_once __DIR__ . '/src/tvar-issue.php';

$soubor = null;
$prisne = false;
$zavrene = false;
$komentar = false;
$titulek = '';
$zarazeni = false;
$stitky = [];
$odpovedni = [];
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
    if ($arg === '--komentar') {
        $komentar = true;
        continue;
    }
    if ($arg === '--titulek') {
        $titulek = $argv[++$i] ?? '';
        continue;
    }
    if ($arg === '--stitky' || $arg === '--odpovedni') {
        $seznam = array_values(array_filter(array_map('trim', explode(',', $argv[++$i] ?? ''))));
        if ($arg === '--stitky') {
            $stitky = $seznam;
        } else {
            $odpovedni = $seznam;
        }
        $zarazeni = true;
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

$problemy = $komentar ? problemyKomentare($telo) : problemyTvaru($telo);

if ($komentar) {
    $zavrene = false;
    $prisne = false;
}
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
if (zminujeNastroj($telo . ' ' . $titulek)) {
    $problemy[] = 'zmiňuje nástroj, kterým se text psal';
}
if ($zarazeni && !$komentar) {
    $problemy = array_merge($problemy, problemyZarazeni($stitky, $odpovedni, $telo));
}

if ($problemy === []) {
    echo ($komentar ? 'Komentář' : 'Tvar issue') . " sedí.\n";
    exit(0);
}

fwrite(STDERR, ($komentar ? 'Komentář' : 'Tělo issue') . " neodpovídá pravidlům:\n\n");
foreach ($problemy as $problem) {
    fwrite(STDERR, "  - $problem\n");
}
if (!$komentar) {
    fwrite(STDERR, "\nSekce, jiné nejsou: " . povoleneSekce() . "\n");
    fwrite(STDERR, "Vzor: nastroje/sablony/issue-ukol.md\n");
}
exit(1);

/**
 * Komentář u issue: krátký, bez zmínky o nástroji a bez dlouhých pomlček.
 * Dlouhý výpis patří do deníku projektu.
 */
function problemyKomentare(string $text): array
{
    $problemy = [];
    $radku = radkyBezPrazdnych($text);
    if ($radku > 5) {
        $problemy[] = "má $radku řádků, limit je 5; dlouhý výpis patří do deníku projektu";
    }

    return $problemy;
}
