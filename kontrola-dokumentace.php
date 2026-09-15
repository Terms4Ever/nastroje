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

const VERZE_DOKUMENTACE = '1.2.0';

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

    // Vadny JSON se NESMI prejit mlcky. Do 15. 9. 2026 se pri chybe jen
    // nechaly vychozi hodnoty, tedy docs-kontrola => false, a skript ohlasil
    // "neni zapnuta" s navratovym kodem 0. Jeden preklep nebo nedoresenej
    // merge konflikt tim vypnul celou branu a push presel.
    if (!is_array($syrove)) {
        fwrite(STDERR, sprintf(
            "\n  %s neni platny JSON: %s\n"
            . "  -> Dokud se to neopravi, kontrola dokumentace nevi, co ma delat.\n\n",
            $cestaNastaveni,
            json_last_error_msg()
        ));
        exit(1);
    }

    $nastaveni = array_merge($nastaveni, $syrove);
}

// Klic musi byt skutecne true, ne retezec "true" nebo cislo 1. Kdyby se
// takova hodnota brala jako vypnuto, tise by to obeslo celou kontrolu.
if (array_key_exists('docs-kontrola', $nastaveni) && !is_bool($nastaveni['docs-kontrola'])) {
    fwrite(STDERR, sprintf(
        "\n  docs-kontrola v %s neni true ani false, ale %s.\n"
        . "  -> Napis true bez uvozovek, jinak neni jasne, jestli ma kontrola bezet.\n\n",
        $cestaNastaveni,
        var_export($nastaveni['docs-kontrola'], true)
    ));
    exit(1);
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
        $cisty = platnyRadek($radek);
        if ($cisty === null) {
            $chyby[] = sprintf('%s:%d  neplatne UTF-8, radek nejde zkontrolovat', $nazev, $i + 1);
            continue;
        }
        if (!preg_match('/[\x{2013}\x{2014}]/u', bezKodu($cisty))) {
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

/**
 * Radek v platnem UTF-8, nebo null.
 *
 * preg_match s modifikatorem /u vrati na neplatnem UTF-8 false, ne 0, takze
 * se podminka "neobsahuje pomlcku" vyhodnoti jako pravda a radek se preskoci.
 * Adversarialni beh 15. 9. 2026 to vyuzil: staci jeden vadny bajt na radku
 * a zakazany znak na temze radku projde.
 */
function platnyRadek(string $radek): ?string
{
    return mb_check_encoding($radek, 'UTF-8') ? $radek : null;
}
