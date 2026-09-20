<?php
/**
 * Generátor přehledu migrací do db/prehled.md.
 *
 *     php prehled-migraci.php [cesta-k-repozitari]           vypíše blok
 *     php prehled-migraci.php [cesta-k-repozitari] --zapsat  vloží ho do souboru
 *
 * Blok stojí mezi značkami a přepisuje se celý, ručně psané části souboru
 * zůstávají. Datum a čas nese název migrace, popis se bere z prvního řádku
 * komentáře v ní. Kdy která migrace doopravdy proběhla na produkci, říká
 * tabulka `migrace` v databázi, tu do gitu psát nejde.
 *
 * Kontrola v kontrola-migraci.php pak ověří, že blok odpovídá složce.
 */
declare(strict_types=1);

const ZNACKA_ZACATEK = '<!-- generovano nastroji, needitovat -->';
const ZNACKA_KONEC = '<!-- konec generovaneho bloku -->';

$koren = rtrim($argv[1] ?? getcwd(), "/\\");
$zapsat = in_array('--zapsat', $argv, true);

$slozka = $koren . '/db/migrace';
if (!is_dir($slozka)) {
    fwrite(STDERR, "  Složka $slozka neexistuje.\n");
    exit(1);
}

$migrace = [];
foreach (scandir($slozka) ?: [] as $polozka) {
    if (is_file($slozka . '/' . $polozka) && str_ends_with($polozka, '.sql')) {
        $migrace[] = $polozka;
    }
}
sort($migrace);

$blok = sestavBlok($slozka, $migrace);

if (!$zapsat) {
    echo $blok;
    exit(0);
}

$cesta = $koren . '/db/prehled.md';
$obsah = is_file($cesta) ? (string) file_get_contents($cesta) : sablona(basename($koren));
$novy = vlozBlok($obsah, $blok);

if ($novy === $obsah) {
    echo "  Přehled je aktuální, nic se nemění.\n";
    exit(0);
}

file_put_contents($cesta, $novy);
printf("  Přehled migrací zapsán do %s (%d migrací).\n", $cesta, count($migrace));
exit(0);

/** Tabulka migrací mezi značkami. */
function sestavBlok(string $slozka, array $migrace): string
{
    $radky = ['| migrace | co mění |', '|---|---|'];
    foreach ($migrace as $soubor) {
        $obsah = (string) file_get_contents($slozka . '/' . $soubor);
        $popis = popisMigrace($obsah) ?? '(bez popisu)';
        $radky[] = sprintf('| `%s` | %s |', $soubor, str_replace('|', ' ', $popis));
    }

    return ZNACKA_ZACATEK . "\n" . implode("\n", $radky) . "\n" . ZNACKA_KONEC;
}

/** První řádek komentáře v migraci. */
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

/** Nový soubor, když přehled ještě neexistuje. */
function sablona(string $projekt): string
{
    return "# Přehled migrací\n\n"
        . "Co se v databázi projektu $projekt měnilo a kdy. Jeden soubor je jedna\n"
        . "změna, datum je v názvu a hotová migrace se už neupravuje. Tabulku níž\n"
        . "generují nástroje, ručně se do ní nepíše.\n\n"
        . "Kdy která migrace doopravdy proběhla na produkci, drží tabulka `migrace`\n"
        . "v databázi. Spouští je nasazení, ne člověk.\n\n"
        . ZNACKA_ZACATEK . "\n" . ZNACKA_KONEC . "\n";
}

/** Vloží nebo nahradí blok. */
function vlozBlok(string $obsah, string $blok): string
{
    $od = strpos($obsah, ZNACKA_ZACATEK);
    $do = strpos($obsah, ZNACKA_KONEC);

    if ($od !== false && $do !== false && $do > $od) {
        return substr($obsah, 0, $od) . $blok . substr($obsah, $do + strlen(ZNACKA_KONEC));
    }

    return rtrim($obsah) . "\n\n" . $blok . "\n";
}
