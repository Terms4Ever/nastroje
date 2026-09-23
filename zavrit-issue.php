<?php
/**
 * Zavření issue s důkazem.
 *
 *     php zavrit-issue.php <repozitar> <cislo> --komentar <soubor>            jen posoudí
 *     php zavrit-issue.php <repozitar> <cislo> --komentar <soubor> --zavrit   a zavře
 *     php zavrit-issue.php <repozitar> <cislo> --commit <otisk> ...          jiný commit než hlava main
 *
 * Issue se zavře, jen když:
 *   - má tvar a zařazení podle standardu (src/tvar-issue.php),
 *   - checklist je celý odškrtaný, nebo komentář říká, proč bod zůstal schválně,
 *   - ke snímku před existuje snímek po (výjimka: štítek "bez snímku po")
 *     a snímky odkazují na otisk commitu,
 *   - ověřovaný commit je na výchozí větvi a všechny jeho běhy na GitHubu
 *     doběhly úspěšně (kontroly, testy, nasazení),
 *   - závěrečný komentář splní pravidla komentáře a odkazuje na ten commit.
 *
 * Bez --zavrit nic nemění, jen řekne, jestli by šlo zavřít. Zavírá se proto,
 * aby "hotovo" nešlo říct bez důkazu: 22. 9. 2026 steelset visel s červenou
 * kontrolou a issue vedle něj bylo zavřené (N33). Hook Claude Code holé
 * `gh issue close` zastaví a pošle sem.
 */
declare(strict_types=1);

require_once __DIR__ . '/src/tvar-issue.php';

const STITEK_BEZ_SNIMKU_PO = 'bez snímku po';
const USPESNE_ZAVERY = ['success', 'skipped', 'neutral'];

$koren = null;
$cislo = null;
$otisk = null;
$komentarSoubor = null;
$zavrit = false;
for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if ($arg === '--zavrit') {
        $zavrit = true;
    } elseif ($arg === '--commit') {
        $otisk = strtolower(trim((string) ($argv[++$i] ?? '')));
    } elseif ($arg === '--komentar') {
        $komentarSoubor = (string) ($argv[++$i] ?? '');
    } elseif ($koren === null) {
        $koren = rtrim($arg, "/\\");
    } elseif ($cislo === null) {
        $cislo = (int) $arg;
    }
}

if ($koren === null || $cislo === null || $cislo < 1) {
    fwrite(STDERR, "Použití: php zavrit-issue.php <repozitar> <cislo> --komentar <soubor> [--commit <otisk>] [--zavrit]\n");
    exit(2);
}
if (PHP_OS_FAMILY === 'Windows' && preg_match('#^/([a-z])/(.*)$#i', $koren, $shoda)) {
    $koren = strtoupper($shoda[1]) . ':/' . $shoda[2];
}

// --------------------------------------------------------------------------
// Co se zavírá
// --------------------------------------------------------------------------
if (!is_dir($koren)) {
    konec("Cesta $koren neexistuje.");
}
$url = spust(['git', '-C', $koren, 'config', '--get', 'remote.origin.url']);
if ($url['kod'] !== 0 || preg_match('#github\.com[:/](Terms4Ever/[^/]+?)(\.git)?$#i', trim($url['ven']), $shoda) !== 1) {
    konec("$koren není klon repozitáře Terms4Ever na GitHubu.");
}
$slug = $shoda[1];

$issue = gh(['issue', 'view', (string) $cislo, '--repo', $slug, '--json', 'number,title,body,state,labels,assignees,comments,createdAt']);
if (!is_array($issue) || !isset($issue['state'])) {
    konec("Issue #$cislo se nepodařilo načíst přes gh; bez něj nejde nic ověřit.");
}
if (strtoupper((string) $issue['state']) !== 'OPEN') {
    konec("Issue #$cislo už je zavřené, není co zavírat.");
}

$telo = (string) ($issue['body'] ?? '');
$stitky = array_map(static fn (array $s): string => (string) ($s['name'] ?? ''), (array) ($issue['labels'] ?? []));
$odpovedni = array_map(static fn (array $o): string => (string) ($o['login'] ?? ''), (array) ($issue['assignees'] ?? []));
$komentare = array_map(static fn (array $k): string => (string) ($k['body'] ?? ''), (array) ($issue['comments'] ?? []));

$problemy = [];

// --------------------------------------------------------------------------
// Ověřovaný commit: hlava výchozí větve, nebo zadaný, když na ní leží
// --------------------------------------------------------------------------
$repozitar = gh(['api', "repos/$slug"]);
$vychozi = is_array($repozitar) ? (string) ($repozitar['default_branch'] ?? '') : '';
if ($vychozi === '') {
    konec("Výchozí větev repozitáře $slug se nepodařilo zjistit.");
}
$hlava = gh(['api', "repos/$slug/commits/$vychozi"]);
$hlavaOtisk = is_array($hlava) ? strtolower((string) ($hlava['sha'] ?? '')) : '';
if (!jeOtiskCommitu($hlavaOtisk)) {
    konec("Hlavu větve $vychozi se nepodařilo zjistit.");
}
if ($otisk === null) {
    $otisk = $hlavaOtisk;
} elseif (!jeOtiskCommitu($otisk)) {
    konec('--commit chce celý otisk commitu (40 znaků).');
} elseif ($otisk !== $hlavaOtisk) {
    $srovnani = gh(['api', "repos/$slug/compare/$otisk...$vychozi"]);
    $stav = is_array($srovnani) ? (string) ($srovnani['status'] ?? '') : '';
    if (!in_array($stav, ['ahead', 'identical'], true)) {
        $problemy[] = sprintf('commit %s na větvi %s není (%s); důkaz musí být z toho, co je nasazené a sdílené', substr($otisk, 0, 7), $vychozi, $stav === '' ? 'nepodařilo se porovnat' : $stav);
    }
}
$kratky = substr($otisk, 0, 7);

// --------------------------------------------------------------------------
// 1. Tvar a zařazení
// --------------------------------------------------------------------------
if (jeSyroveIssue($telo)) {
    $problemy[] = 'issue je syrový nápad bez sekcí; nejdřív ho přepiš do tvaru';
} else {
    foreach (problemyTvaru($telo) as $problem) {
        $problemy[] = $problem;
    }
    foreach (problemyZarazeni($stitky, $odpovedni, $telo) as $problem) {
        $problemy[] = $problem;
    }
}

// --------------------------------------------------------------------------
// 2. Závěrečný komentář
// --------------------------------------------------------------------------
$komentar = null;
if ($komentarSoubor === null || $komentarSoubor === '') {
    $problemy[] = 'chybí závěrečný komentář (--komentar <soubor>): co se změnilo, kde, čím je to ověřeno a odkaz na commit';
} elseif (!is_file($komentarSoubor)) {
    $problemy[] = "soubor s komentářem $komentarSoubor neexistuje";
} else {
    $komentar = (string) file_get_contents($komentarSoubor);
    $radku = radkyKomentare($komentar);
    if ($radku > 5) {
        $problemy[] = "závěrečný komentář má $radku řádků, limit je 5";
    }
    if (zminujeNastroj($komentar)) {
        $problemy[] = 'závěrečný komentář zmiňuje nástroj, kterým se text psal';
    }
    if (str_contains($komentar, "\u{2013}") || str_contains($komentar, "\u{2014}")) {
        $problemy[] = 'závěrečný komentář obsahuje dlouhou nebo poloviční pomlčku';
    }
    foreach (problemySnimkuVTextu($komentar) as $problem) {
        $problemy[] = "závěrečný komentář: $problem";
    }
    preg_match_all('/\b[0-9a-f]{7,40}\b/i', $komentar, $nalezy);
    $odkazuje = array_filter($nalezy[0], static fn (string $h): bool => str_starts_with($otisk, strtolower($h)));
    if ($odkazuje === []) {
        $problemy[] = "závěrečný komentář musí odkazovat na ověřený commit $kratky";
    }
    $komentare[] = $komentar;
}

// --------------------------------------------------------------------------
// 3. Checklist
// --------------------------------------------------------------------------
$vyjimka = false;
foreach ($komentare as $text) {
    if (preg_match('/schváln[ěe][^.]{0,80}(nezaškrt|neodškrt)|(nezaškrt|neodškrt)[^.]{0,80}schváln[ěe]/iu', $text) === 1) {
        $vyjimka = true;
    }
}
$neodskrtnute = count(array_filter(checklist($telo), static fn (bool $hotovo): bool => !$hotovo));
if ($neodskrtnute > 0 && !$vyjimka) {
    $problemy[] = bodu($neodskrtnute) . ' v checklistu zůstalo neodškrtnutých; buď je dodělej, nebo v komentáři napiš, proč zůstávají schválně';
}

// --------------------------------------------------------------------------
// 4. Snímky: k před patří po, odkazy na otisk commitu
// --------------------------------------------------------------------------
$soubory = spust(['git', '-C', $koren, 'ls-tree', '-r', '--name-only', $otisk, '--', 'docs/snimky']);
if ($soubory['kod'] !== 0) {
    $problemy[] = "commit $kratky v místním repozitáři není; udělej git fetch";
} elseif (!in_array(STITEK_BEZ_SNIMKU_PO, $stitky, true)) {
    $vlastni = array_filter(
        array_map('trim', explode("\n", $soubory['ven'])),
        static fn (string $c): bool => str_starts_with($c, "docs/snimky/$cislo-")
    );
    $pred = array_filter($vlastni, static fn (string $c): bool => str_starts_with(basename($c), 'pred-'));
    $po = array_filter($vlastni, static fn (string $c): bool => str_starts_with(basename($c), 'po-'));
    if ($pred !== [] && $po === []) {
        $problemy[] = 'ke snímku před chybí snímek po; bez něj není vidět, co se změnilo'
            . ' (když ho nemá kdo pořídit, dej issue štítek "' . STITEK_BEZ_SNIMKU_PO . '")';
    }
}
foreach (problemySnimkuVTextu($telo) as $problem) {
    $vznik = substr((string) ($issue['createdAt'] ?? ''), 0, 10);
    if ($vznik >= OD_SNIMKU_S_OTISKEM) {
        $problemy[] = $problem;
    }
}

// --------------------------------------------------------------------------
// 5. Důkaz z GitHubu: všechny běhy ověřovaného commitu doběhly úspěšně
// --------------------------------------------------------------------------
$behy = gh(['api', "repos/$slug/commits/$otisk/check-runs?per_page=100"]);
if (!is_array($behy) || !isset($behy['check_runs'])) {
    $problemy[] = "běhy commitu $kratky se nepodařilo načíst; bez nich není důkaz";
} elseif ($behy['check_runs'] === []) {
    $problemy[] = "commit $kratky nemá na GitHubu žádný běh kontrol; není čím dokázat, že prošel";
} else {
    foreach ($behy['check_runs'] as $beh) {
        $nazev = (string) ($beh['name'] ?? '?');
        if (($beh['status'] ?? '') !== 'completed') {
            $problemy[] = "běh \"$nazev\" u commitu $kratky ještě běží; počkej, až doběhne";
        } elseif (!in_array((string) ($beh['conclusion'] ?? ''), USPESNE_ZAVERY, true)) {
            $problemy[] = sprintf('běh "%s" u commitu %s skončil %s', $nazev, $kratky, (string) ($beh['conclusion'] ?? 'bez výsledku'));
        }
    }
}

// --------------------------------------------------------------------------
// Výsledek
// --------------------------------------------------------------------------
if ($problemy !== []) {
    fwrite(STDERR, "\n  Issue #$cislo se zavřít nedá:\n\n");
    foreach ($problemy as $problem) {
        fwrite(STDERR, "    - $problem\n");
    }
    fwrite(STDERR, "\n");
    exit(1);
}

if (!$zavrit) {
    echo "  Issue #$cislo lze zavřít: checklist, snímky i běhy commitu $kratky sedí.\n"
        . "  Zavře se se stejnými argumenty a přepínačem --zavrit.\n";
    exit(0);
}

$zapis = spust(['gh', 'issue', 'comment', (string) $cislo, '--repo', $slug, '--body-file', $komentarSoubor]);
if ($zapis['kod'] !== 0) {
    konec("Komentář se nepodařilo zapsat, issue zůstává otevřené: " . strtok(trim($zapis['chyba']) ?: 'bez hlášky', "\n"));
}
$zavreni = spust(['gh', 'issue', 'close', (string) $cislo, '--repo', $slug]);
if ($zavreni['kod'] !== 0) {
    konec("Komentář je zapsaný, ale zavření selhalo: " . strtok(trim($zavreni['chyba']) ?: 'bez hlášky', "\n"));
}
echo "  Issue #$cislo zavřené s důkazem: commit $kratky, všechny běhy úspěšné.\n";
exit(0);

// ==========================================================================

function konec(string $zprava): never
{
    fwrite(STDERR, "\n  $zprava\n\n");
    exit(1);
}

/**
 * Spustí příkaz přes shell (kvůli gh.cmd na Windows) a vrátí výstupy zvlášť.
 *
 * @return array{kod: int, ven: string, chyba: string}
 */
function spust(array $prikaz): array
{
    $radek = implode(' ', array_map('escapeshellarg', $prikaz));
    $chyba = tempnam(sys_get_temp_dir(), 'zavrit');
    $proces = proc_open($radek, [1 => ['pipe', 'w'], 2 => ['file', $chyba, 'w']], $roury);
    if (!is_resource($proces)) {
        @unlink($chyba);

        return ['kod' => 1, 'ven' => '', 'chyba' => 'příkaz nejde spustit'];
    }
    $ven = (string) stream_get_contents($roury[1]);
    fclose($roury[1]);
    $kod = proc_close($proces);
    $chybovy = (string) @file_get_contents($chyba);
    @unlink($chyba);

    return ['kod' => $kod, 'ven' => $ven, 'chyba' => $chybovy];
}

/** Výstup gh jako JSON, nebo null, když gh selže nebo vrátí něco jiného. */
function gh(array $argumenty): ?array
{
    $vysledek = spust(array_merge(['gh'], $argumenty));
    if ($vysledek['kod'] !== 0) {
        return null;
    }
    $data = json_decode($vysledek['ven'], true);

    return is_array($data) ? $data : null;
}
