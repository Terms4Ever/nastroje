<?php
/**
 * Kontrola migrací databáze ve složce db/migrace/.
 *
 *     php kontrola-migraci.php <koren> [zaklad] [cil]
 *
 * Bez rozsahu se kontroluje jen tvar migrací a přehled. Když se předá rozsah
 * commitů (zaklad a cil), přibudou dvě pravidla: hotová migrace se už nemění
 * a dávka, která sahá na schéma, musí migraci přidat.
 *
 * Rozsah dodává pre-push hook ze svého vstupu a workflow z údajů o pushi.
 *
 * Nastavení v .readme-kontrola.json:
 *
 *     {
 *       "migrace-kontrola": true
 *     }
 *
 * Projekty s vlastním migračním nástrojem (Laravel a podobně) se nezapínají,
 * pravidla si nese framework sám.
 *
 * Vedomá výjimka: git push --no-verify
 */
declare(strict_types=1);

const VERZE_MIGRACI = '1.0.0';

/** Cesty, které skript čte z disku. Musí sedět na pushovaný commit. */
const CTENE_CESTY_MIGRACI = [
    'db',
    '.readme-kontrola.json',
];

/** Název migrace: datum, nepovinný čas, popis malými písmeny. */
const VZOR_NAZVU = '/^\d{4}-\d{2}-\d{2}(-\d{4})?-[a-z0-9]+(-[a-z0-9]+)*\.sql$/';

/**
 * Soubory spouštěče. Ty mají DDL v sobě (zakládají si tabulku `migrace`),
 * takže by jinak každá jejich změna vypadala jako změna schématu bez migrace.
 */
const SOUBORY_SPOUSTECE = [
    'migrace.php',
    'Migrace.php',
    'migrace-endpoint.php',
];

/** Příkazy, které mění schéma. Když je dávka má mimo migrace, chybí migrace. */
const PRIKAZY_SCHEMATU = [
    'CREATE TABLE',
    'ALTER TABLE',
    'DROP TABLE',
    'CREATE INDEX',
    'DROP INDEX',
    'RENAME TABLE',
];

const ZNACKA_ZACATEK = '<!-- generovano nastroji, needitovat -->';
const ZNACKA_KONEC = '<!-- konec generovaneho bloku -->';

$koren = rtrim($argv[1] ?? getcwd(), "/\\");
$zaklad = $argv[2] ?? null;
$cil = $argv[3] ?? null;

$nastaveni = nactiNastaveni($koren);
$nazevProjektu = basename(realpath($koren) ?: $koren);

if ($nastaveni['migrace-kontrola'] !== true) {
    echo "  Kontrola migrací není pro $nazevProjektu zapnutá"
        . " (.readme-kontrola.json: \"migrace-kontrola\": true).\n";
    exit(0);
}

// --------------------------------------------------------------------------
// 0. Kontroluje se to, co se pushuje
// --------------------------------------------------------------------------
// Skript čte disk. Když zná pushovaný commit, musí se pracovní strom v čtených
// cestách shodovat, jinak by se kontrolovalo něco jiného, než co odejde
// (stejný důvod jako u kontroly dokumentace, záznam N14).
if ($cil !== null) {
    $rozdil = pracovniStromSeLisi($koren, $cil, CTENE_CESTY_MIGRACI);
    if ($rozdil === null) {
        fwrite(STDERR, "  Kontrola migrací nemohla ověřit pracovní strom proti commitu $cil.\n");
        exit(1);
    }
    if ($rozdil !== []) {
        echo "\n  Pracovní strom se liší od pushovaného commitu:\n\n";
        foreach ($rozdil as $cesta) {
            echo "    $cesta\n";
        }
        echo "\n  Zkontroloval bych něco jiného, než co se pushuje. Commitni změny,"
            . " nebo je vrať.\n\n";
        exit(1);
    }
}

$slozka = $koren . '/db/migrace';
$chyby = [];

if (!is_dir($slozka)) {
    echo "\n  Kontrola migrací je zapnutá, ale složka db/migrace/ chybí.\n";
    echo "  Založ ji a dej do ní první migraci, nebo kontrolu vypni.\n\n";
    exit(1);
}

// --------------------------------------------------------------------------
// 1. Tvar jednotlivých migrací
// --------------------------------------------------------------------------
$migrace = [];
foreach (scandir($slozka) ?: [] as $polozka) {
    if ($polozka === '.' || $polozka === '..') {
        continue;
    }
    $cesta = $slozka . '/' . $polozka;
    if (is_dir($cesta)) {
        $chyby[] = "db/migrace/$polozka je složka; migrace leží v jedné rovině";
        continue;
    }
    if (!preg_match(VZOR_NAZVU, $polozka)) {
        $chyby[] = "db/migrace/$polozka má jiný název, než chce pravidlo"
            . " (rrrr-mm-dd-popis.sql, volitelně rrrr-mm-dd-hhmm-popis.sql)";
        continue;
    }

    $obsah = (string) file_get_contents($cesta);
    if (!mb_check_encoding($obsah, 'UTF-8')) {
        $chyby[] = "db/migrace/$polozka není v UTF-8";
        continue;
    }
    if (str_contains($obsah, "\u{2013}") || str_contains($obsah, "\u{2014}")) {
        $chyby[] = "db/migrace/$polozka obsahuje dlouhou nebo poloviční pomlčku";
    }
    if (popisMigrace($obsah) === null) {
        $chyby[] = "db/migrace/$polozka nezačíná komentářem, který říká, co mění"
            . " (první řádek s textem musí začínat --)";
    }
    if (!str_contains($obsah, ';')) {
        $chyby[] = "db/migrace/$polozka nemá jediný příkaz zakončený středníkem";
    }

    $migrace[] = $polozka;
}

sort($migrace);

if ($migrace === []) {
    $chyby[] = 'db/migrace/ je prázdná; kontrolu zapni až s první migrací';
}

// --------------------------------------------------------------------------
// 2. Přehled db/prehled.md
// --------------------------------------------------------------------------
$cestaPrehledu = $koren . '/db/prehled.md';
if (!is_file($cestaPrehledu)) {
    $chyby[] = 'chybí db/prehled.md; vygeneruj ho: php prehled-migraci.php <repozitar> --zapsat';
} else {
    $prehled = (string) file_get_contents($cestaPrehledu);
    $blok = blokPrehledu($slozka, $migrace);
    if (!str_contains($prehled, ZNACKA_ZACATEK) || !str_contains($prehled, ZNACKA_KONEC)) {
        $chyby[] = 'db/prehled.md nemá generovaný blok;'
            . ' doplň ho: php prehled-migraci.php <repozitar> --zapsat';
    } elseif (normalizuj(vyrizniBlok($prehled)) !== normalizuj($blok)) {
        $chyby[] = 'generovaný blok v db/prehled.md neodpovídá složce db/migrace/;'
            . ' přegeneruj: php prehled-migraci.php <repozitar> --zapsat';
    }
}

// --------------------------------------------------------------------------
// 3. Pravidla na pushovanou dávku
// --------------------------------------------------------------------------
if ($zaklad !== null && $cil !== null) {
    $rozsah = overRozsah($koren, $zaklad, $cil);
    if ($rozsah === null) {
        fwrite(STDERR, "  Kontrola migrací nepoznala rozsah commitů $zaklad..$cil.\n");
        exit(1);
    }

    $zmeny = zmeneneSoubory($koren, $rozsah);

    // 3a. Hotová migrace se nemění. Kdo ji upraví, změní historii databáze,
    // která na produkci dávno běží; oprava patří do nové migrace.
    foreach ($zmeny as $cesta => $stav) {
        if (!str_starts_with($cesta, 'db/migrace/')) {
            continue;
        }
        if ($stav === 'M') {
            $chyby[] = "$cesta se v dávce mění; hotová migrace se neupravuje,"
                . ' oprava patří do nové migrace';
        }
        if ($stav === 'D') {
            $chyby[] = "$cesta se v dávce maže; hotová migrace zůstává,"
                . ' i když se její efekt ruší novou migrací';
        }
    }

    // 3b. Dávka, která sahá na schéma, musí přidat migraci.
    $noveMigrace = array_filter(
        array_keys($zmeny),
        static fn (string $c): bool => str_starts_with($c, 'db/migrace/') && $zmeny[$c] === 'A'
    );
    if ($noveMigrace === []) {
        $schema = zmenySchematu($koren, $rozsah);
        if ($schema !== []) {
            $chyby[] = 'dávka mění schéma databáze (' . implode(', ', array_slice($schema, 0, 3))
                . '), ale nepřidává migraci do db/migrace/';
        }
    }
}

// --------------------------------------------------------------------------
// Výsledek
// --------------------------------------------------------------------------
if ($chyby !== []) {
    echo "\n  Migrace $nazevProjektu neprošly:\n\n";
    foreach ($chyby as $chyba) {
        echo "    $chyba\n";
    }
    echo "\n  Vědomá výjimka při pushi: git push --no-verify\n\n";
    exit(1);
}

printf(
    "  Migrace %s jsou v pořádku (%d migrací, kontrola %s).\n",
    $nazevProjektu,
    count($migrace),
    VERZE_MIGRACI
);
exit(0);

// ==========================================================================
// Funkce
// ==========================================================================

/**
 * Nastavení projektu. Vadný soubor je chyba, ne důvod kontrolu přeskočit.
 */
function nactiNastaveni(string $koren): array
{
    $vychozi = ['migrace-kontrola' => false];
    $cesta = $koren . '/.readme-kontrola.json';
    if (!is_file($cesta)) {
        return $vychozi;
    }

    $syrove = (string) file_get_contents($cesta);
    $syrove = preg_replace('/^\xEF\xBB\xBF/', '', $syrove) ?? $syrove;

    $data = json_decode($syrove);
    if (!$data instanceof stdClass) {
        fwrite(STDERR, "  Nastavení $cesta není platný objekt JSON.\n");
        exit(1);
    }
    if (property_exists($data, 'migrace-kontrola')) {
        $hodnota = $data->{'migrace-kontrola'};
        if (!is_bool($hodnota)) {
            fwrite(STDERR, "  Klíč migrace-kontrola v $cesta musí být true nebo false.\n");
            exit(1);
        }
        $vychozi['migrace-kontrola'] = $hodnota;
    }

    return $vychozi;
}

/** První řádek komentáře, tedy popis migrace. Null, když chybí. */
function popisMigrace(string $obsah): ?string
{
    foreach (preg_split('/\R/', $obsah) ?: [] as $radek) {
        $radek = trim($radek);
        if ($radek === '') {
            continue;
        }
        if (!str_starts_with($radek, '--')) {
            return null;
        }
        $text = trim(ltrim($radek, '- ='));
        if ($text !== '') {
            return $text;
        }
    }

    return null;
}

/** Tabulka migrací pro db/prehled.md. */
function blokPrehledu(string $slozka, array $migrace): string
{
    $radky = ['| migrace | co mění |', '|---|---|'];
    foreach ($migrace as $soubor) {
        $obsah = (string) file_get_contents($slozka . '/' . $soubor);
        $popis = popisMigrace($obsah) ?? '(bez popisu)';
        $radky[] = sprintf('| `%s` | %s |', $soubor, str_replace('|', ' ', $popis));
    }

    return ZNACKA_ZACATEK . "\n" . implode("\n", $radky) . "\n" . ZNACKA_KONEC;
}

function vyrizniBlok(string $text): string
{
    $od = strpos($text, ZNACKA_ZACATEK);
    $do = strpos($text, ZNACKA_KONEC);
    if ($od === false || $do === false) {
        return '';
    }

    return substr($text, $od, $do - $od + strlen(ZNACKA_KONEC));
}

function normalizuj(string $text): string
{
    return trim(str_replace("\r\n", "\n", $text));
}

/** Cesta pro git: /c/... rozumí jen Git Bash, gitu se předává C:/... */
function proGit(string $cesta): string
{
    if (preg_match('#^/([a-zA-Z])/(.*)$#', $cesta, $shoda) === 1) {
        return strtoupper($shoda[1]) . ':/' . $shoda[2];
    }

    return $cesta;
}

/** Vrátí rozsah pro git, nebo null, když ho nejde ověřit. */
function overRozsah(string $koren, string $zaklad, string $cil): ?string
{
    if (jeNovaVetev($zaklad)) {
        return null;
    }
    foreach ([$zaklad, $cil] as $commit) {
        if (git($koren, 'cat-file -e ' . escapeshellarg($commit . '^{commit}')) === null) {
            return null;
        }
    }

    return $zaklad . '..' . $cil;
}

function jeNovaVetev(string $zaklad): bool
{
    return $zaklad === '' || trim($zaklad, '0') === '';
}

/** Změněné soubory v rozsahu: cesta => A/M/D. */
function zmeneneSoubory(string $koren, string $rozsah): array
{
    $vystup = git($koren, 'diff --name-status ' . escapeshellarg($rozsah), true);
    if ($vystup === null) {
        throw new RuntimeException("git diff pro rozsah $rozsah selhal");
    }

    $soubory = [];
    foreach ($vystup as $radek) {
        $casti = preg_split('/\t/', trim($radek));
        if ($casti === false || count($casti) < 2) {
            continue;
        }
        $stav = substr($casti[0], 0, 1);
        $soubory[$casti[count($casti) - 1]] = $stav;
    }

    return $soubory;
}

/** Přidané řádky dávky, které mění schéma, mimo složku migrací. */
function zmenySchematu(string $koren, string $rozsah): array
{
    $vystup = git(
        $koren,
        'diff --unified=0 ' . escapeshellarg($rozsah) . ' -- . ":(exclude)db/migrace"',
        true
    );
    if ($vystup === null) {
        throw new RuntimeException("git diff pro rozsah $rozsah selhal");
    }

    $nalezy = [];
    $soubor = '';
    $preskocit = false;
    foreach ($vystup as $radek) {
        if (str_starts_with($radek, '+++ b/')) {
            $soubor = substr($radek, 6);
            $preskocit = in_array(basename($soubor), SOUBORY_SPOUSTECE, true);
            continue;
        }
        if ($preskocit) {
            continue;
        }
        if (!str_starts_with($radek, '+') || str_starts_with($radek, '+++')) {
            continue;
        }
        $velke = strtoupper($radek);
        foreach (PRIKAZY_SCHEMATU as $prikaz) {
            if (str_contains($velke, $prikaz)) {
                $nalezy[$soubor . ': ' . $prikaz] = true;
            }
        }
    }

    return array_keys($nalezy);
}

/** Liší se pracovní strom od commitu v daných cestách? Null při selhání gitu. */
function pracovniStromSeLisi(string $koren, string $commit, array $cesty): ?array
{
    $argumenty = implode(' ', array_map('escapeshellarg', $cesty));

    $zmenene = git($koren, 'diff --name-only ' . escapeshellarg($commit) . ' -- ' . $argumenty, true);
    if ($zmenene === null) {
        return null;
    }

    $nesledovane = git($koren, 'ls-files --others --exclude-standard -- ' . $argumenty, true);
    if ($nesledovane === null) {
        return null;
    }

    $vse = array_filter(array_map('trim', array_merge($zmenene, $nesledovane)));
    sort($vse);

    return array_values(array_unique($vse));
}

/**
 * Spustí git. Vrací poslední řádek, pole řádků, nebo null při nenulovém kódu.
 *
 * @return string|array<int, string>|null
 */
function git(string $koren, string $prikaz, bool $vsechnyRadky = false)
{
    $vystup = [];
    $kod = 0;
    $ticho = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';
    exec(sprintf('git -C %s %s %s', escapeshellarg(proGit($koren)), $prikaz, $ticho), $vystup, $kod);

    if ($kod !== 0) {
        return null;
    }

    return $vsechnyRadky ? $vystup : trim((string) ($vystup[0] ?? ''));
}
