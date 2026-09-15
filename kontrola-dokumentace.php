<?php
/**
 * Kontrola dokumentace ve složce docs/.
 *
 *     php kontrola-dokumentace.php <koren> [zaklad] [cil]
 *
 * Bez rozsahu se kontroluje jen obsah dokumentů. Když se předá rozsah commitů
 * (zaklad a cil), přibude kontrola, že dávka, která sáhla na kód, sáhla i na
 * dokumentaci.
 *
 * Rozsah dodává pre-push hook ze svého vstupu a workflow z údajů o pushi.
 *
 * Nastavení v .readme-kontrola.json:
 *
 *     {
 *       "docs-pomlcky": "blokovat",   nebo "varovat"
 *       "docs-vymahat-aktualizaci": true
 *     }
 *
 * Vedomá výjimka: git push --no-verify
 */
declare(strict_types=1);

const VERZE_DOKUMENTACE = '1.1.0';

/** Soubory, které se při rozhodování "sáhlo se na kód" nepočítají. */
const NENI_KOD = [
    'docs/',
    'README.md',
    '.readme-kontrola.json',
    '.gitignore',
    'LICENSE',
];

$koren = rtrim($argv[1] ?? getcwd(), "/\\");
$zaklad = $argv[2] ?? null;
$cil = $argv[3] ?? null;

$nastaveni = [
    'docs-kontrola' => false,
    'docs-pomlcky' => 'blokovat',
    'docs-vymahat-aktualizaci' => true,
    'cesty-bez-kontroly' => [],
];
$cestaNastaveni = $koren . '/.readme-kontrola.json';
if (is_file($cestaNastaveni)) {
    $syrove = json_decode((string) file_get_contents($cestaNastaveni), true);
    if (is_array($syrove)) {
        $nastaveni = array_merge($nastaveni, $syrove);
    }
}

$slozka = $koren . '/docs';
if (!is_dir($slozka)) {
    echo "  Složka docs/ není, kontrola dokumentace se přeskakuje.\n";
    exit(0);
}

// Kontrola se zapíná přihlášením, ne automaticky. Repozitář, který má docs/
// v jiném tvaru, tím nespadne dřív, než si ho srovná. Zapíná se řádkem
// "docs-kontrola": true v .readme-kontrola.json.
if (($nastaveni['docs-kontrola'] ?? false) !== true) {
    echo "  Kontrola dokumentace není pro tenhle repozitář zapnutá.
";
    exit(0);
}

$chyby = [];
$varovani = [];

// Rekurzivne, ne jen koren docs/. Nerekurzivni glob znamenal, ze dokument
// v podslozce prosel bez kontroly (nalez overovaciho agenta 15. 9. 2026).
$dokumenty = [];
$prochazeni = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($slozka, FilesystemIterator::SKIP_DOTS)
);
foreach ($prochazeni as $polozka) {
    if ($polozka->isFile() && strtolower($polozka->getExtension()) === 'md') {
        $dokumenty[] = $polozka->getPathname();
    }
}
sort($dokumenty);

// ---------------------------------------------------------------------------
// 1. Generovaný blok ve stavu projektu musí odpovídat skutečnosti
// ---------------------------------------------------------------------------

$cestaStavu = $slozka . '/00-stav-projektu.md';

if (!is_file($cestaStavu)) {
    $chyby[] = 'chybí docs/00-stav-projektu.md, živý stav projektu';
} else {
    $stav = (string) file_get_contents($cestaStavu);
    $ocekavany = trim((string) shell_exec(sprintf(
        '%s %s %s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(__DIR__ . '/stav-projektu.php'),
        escapeshellarg($koren)
    )));

    if (!str_contains($stav, '<!-- generovano nastroji, needitovat -->')) {
        $chyby[] = 'docs/00-stav-projektu.md nemá generovaný blok; doplň ho'
            . ' příkazem: php stav-projektu.php <repozitar> --zapsat';
    } else {
        $zacatek = strpos($stav, '<!-- generovano nastroji, needitovat -->');
        $konec = strpos($stav, '<!-- konec generovaneho bloku -->');
        $soucasny = trim(substr($stav, $zacatek, $konec - $zacatek + strlen('<!-- konec generovaneho bloku -->')));

        if (normalizuj($soucasny) !== normalizuj($ocekavany)) {
            $chyby[] = 'generovaný blok v docs/00-stav-projektu.md neodpovídá skutečnosti;'
                . ' přegeneruj: php stav-projektu.php <repozitar> --zapsat';
        }
    }
}

// ---------------------------------------------------------------------------
// 2. Jen krátké pomlčky
// ---------------------------------------------------------------------------

$rezimPomlcek = (string) $nastaveni['docs-pomlcky'];

foreach ($dokumenty as $dokument) {
    $nazev = nazevDokumentu($koren, $dokument);
    $radky = explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($dokument)));

    foreach ($radky as $i => $radek) {
        if (!preg_match('/[\x{2013}\x{2014}]/u', bezKodu($radek))) {
            continue;
        }
        $hlaska = sprintf('%s:%d  dlouhá pomlčka, použij krátkou "-"', $nazev, $i + 1);
        if ($rezimPomlcek === 'varovat') {
            $varovani[] = $hlaska;
        } else {
            $chyby[] = $hlaska;
        }
    }
}

// ---------------------------------------------------------------------------
// Poznámka: cesty uvnitř dokumentů se schválně nekontrolují
// ---------------------------------------------------------------------------
//
// V README to smysl dává, tam se popisuje současný stav. V dokumentech ne.
// Rozhodovací deník musí umět napsat, že se soubor smazal nebo přejmenoval,
// a stav projektu věty typu "jmenuje se dev-server.php, ne router.php".
// Zkouška 15. 9. 2026 na šesti repozitářích: skoro každý nález byl planý
// poplach přesně tohohle druhu.

// ---------------------------------------------------------------------------
// 3. Dávka, která sáhla na kód, musí sáhnout i na dokumentaci
// ---------------------------------------------------------------------------

if ($nastaveni['docs-vymahat-aktualizaci'] && $zaklad !== null && $cil !== null) {
    $rozsah = overRozsah($koren, $zaklad, $cil);

    if ($rozsah !== null) {
        $zmenene = zmeneneSoubory($koren, $rozsah);
        $kod = array_filter($zmenene, static fn (string $s): bool => jeKod($s));
        $docs = array_filter($zmenene, static fn (string $s): bool => str_starts_with($s, 'docs/'));

        if ($kod !== [] && $docs === []) {
            $chyby[] = sprintf(
                'dávka mění %d souborů s kódem, ale na docs/ nesáhla.'
                . ' Zapiš, co se změnilo, do docs/00-stav-projektu.md,'
                . ' a proč, do docs/03-rozhodovaci-dennik.md',
                count($kod)
            );
        }
    }
}

// ---------------------------------------------------------------------------

foreach ($varovani as $v) {
    echo "  pozn.: $v\n";
}

if ($chyby === []) {
    printf(
        "  Dokumentace %s je v pořádku (%d dokumentů, kontrola %s).\n",
        basename(realpath($koren) ?: $koren),
        count($dokumenty),
        VERZE_DOKUMENTACE
    );
    exit(0);
}

printf("\n  Dokumentace %s neprošla:\n\n", basename(realpath($koren) ?: $koren));
foreach ($chyby as $c) {
    echo "    $c\n";
}
echo "\n  Vědomá výjimka při pushi: git push --no-verify\n\n";

exit(1);

// ---------------------------------------------------------------------------

/** Srovná blok na porovnatelný tvar: bez bílých znaků na koncích řádků. */
function normalizuj(string $text): string
{
    $radky = array_map('rtrim', explode("\n", str_replace("\r\n", "\n", $text)));

    return implode("\n", $radky);
}

function jeKod(string $cesta): bool
{
    foreach (NENI_KOD as $vyjimka) {
        if ($cesta === $vyjimka || str_starts_with($cesta, $vyjimka)) {
            return false;
        }
    }

    return true;
}

/**
 * Cesta ve tvaru, kterému rozumí git.
 *
 * PHP na Windows dostane z Git Bashe cestu jako /c/laragon/..., ale git.exe
 * jí nerozumí a skončí chybou 128. Bez tohohle převodu kontrola tiše prošla,
 * aniž cokoli ověřila (nález 15. 9. 2026).
 */
function proGit(string $cesta): string
{
    if (preg_match('#^/([a-z])/(.*)$#i', $cesta, $shoda)) {
        return strtoupper($shoda[1]) . ':/' . $shoda[2];
    }

    return $cesta;
}

/** Vrátí rozsah "zaklad..cil", pokud oba konce v repozitáři existují. */
function overRozsah(string $koren, string $zaklad, string $cil): ?string
{
    $nuly = '0000000000000000000000000000000000000000';
    if ($zaklad === $nuly || $zaklad === '') {
        return null;   // nová větev, není proti čemu měřit
    }

    foreach ([$zaklad, $cil] as $konec) {
        $kod = 0;
        $vystup = [];
        exec(sprintf(
            // Stříška je v cmd.exe únikový znak, takže ^{commit} musí být
            // uvnitř uvozovek, jinak se cestou rozpadne (nález 15. 9. 2026).
            'git -C %s cat-file -e %s %s',
            escapeshellarg(proGit($koren)),
            escapeshellarg($konec . '^{commit}'),
            PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null'
        ), $vystup, $kod);

        if ($kod !== 0) {
            return null;
        }
    }

    return $zaklad . '..' . $cil;
}

/** @return string[] */
function zmeneneSoubory(string $koren, string $rozsah): array
{
    $vystup = [];
    $kod = 0;
    exec(sprintf(
        'git -C %s diff --name-only %s %s',
        escapeshellarg(proGit($koren)),
        escapeshellarg($rozsah),
        PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null'
    ), $vystup, $kod);

    if ($kod !== 0) {
        // Prázdný seznam by znamenal "žádný kód se nezměnil", takže by
        // kontrola tiše prošla, aniž cokoli ověřila. Radši ať spadne hlasitě.
        throw new RuntimeException(
            'git diff selhal pro rozsah ' . $rozsah . ' (návratový kód ' . $kod . ')'
        );
    }

    return array_values(array_filter(array_map('trim', $vystup)));
}

function vypadaJakoCesta(string $kus): bool
{
    $kus = trim($kus);

    if ($kus === '' || str_contains($kus, ' ')) {
        return false;
    }
    if (preg_match('/^[$\-<#]/', $kus) || str_starts_with($kus, '/')) {
        return false;
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $kus) || preg_match('/[?=@]/', $kus)) {
        return false;
    }
    if (str_contains($kus, '*') || str_contains($kus, '…')) {
        return false;
    }
    if (preg_match('/^\d+(\.\d+)+$/', $kus) || str_starts_with($kus, '.git/')) {
        return false;
    }
    if (str_contains($kus, '/')) {
        return true;
    }

    return preg_match('/(?<=.)\.(php|js|mjs|cjs|ts|tsx|jsx|json|sql|md|ya?ml|css|scss|html?|sh|lock|toml|xml|txt|svg|png|ico|webp)$/i', $kus) === 1;
}

/** @param string[] $bezKontroly */
function existuje(string $koren, string $cesta, array $bezKontroly): bool
{
    $cesta = trim($cesta);
    while (str_starts_with($cesta, './')) {
        $cesta = substr($cesta, 2);
    }
    $cesta = rtrim($cesta, '/');

    if ($cesta === '') {
        return true;
    }

    foreach ($bezKontroly as $vyjimka) {
        $vyjimka = rtrim(trim($vyjimka), '/');
        if ($vyjimka !== '' && ($cesta === $vyjimka || str_starts_with($cesta . '/', $vyjimka . '/'))) {
            return true;
        }
    }

    return file_exists($koren . '/' . $cesta);
}

/**
 * Text bez vnitrnich kousku kodu.
 *
 * Uvnitr obracenych apostrofu se znak cituje, nepouziva. Dokument, ktery
 * popisuje zakaz dlouhe pomlcky, ji musi umet ukazat (nalez 15. 9. 2026,
 * kontrola spadla na vlastnim zadani pro testovaciho agenta).
 */
function bezKodu(string $radek): string
{
    return preg_replace('/`[^`]*`/u', '', $radek) ?? $radek;
}

/** Cesta dokumentu relativne ke koreni repozitare, s lomitky dopredu. */
function nazevDokumentu(string $koren, string $cesta): string
{
    $relativni = substr($cesta, strlen($koren) + 1);

    return str_replace(DIRECTORY_SEPARATOR, '/', $relativni);
}
