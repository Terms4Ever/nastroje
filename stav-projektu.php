<?php
/**
 * Generátor bloku se skutečnými čísly do docs/00-stav-projektu.md.
 *
 *     php stav-projektu.php [cesta-k-repozitari]           vypíše blok
 *     php stav-projektu.php [cesta-k-repozitari] --zapsat  vloží ho do souboru
 *
 * Blok stojí mezi značkami a přepisuje se celý. Ručně psané části souboru
 * zůstávají. Smysl: čísla, která se dají přečíst ze skutečnosti, se nemají
 * psát rukou, protože zastarají a nikdo si toho nevšimne.
 *
 * Kontrola v kontrola-dokumentace.php pak ověří, že blok v souboru odpovídá
 * tomu, co by skript vygeneroval teď. Když se rozejdou, je zastaralý.
 */
declare(strict_types=1);

const ZNACKA_ZACATEK = '<!-- generovano nastroji, needitovat -->';
const ZNACKA_KONEC = '<!-- konec generovaneho bloku -->';

$koren = rtrim($argv[1] ?? getcwd(), "/\\");
$zapsat = in_array('--zapsat', $argv, true);

$blok = sestavBlok($koren);

if (!$zapsat) {
    echo $blok;
    exit(0);
}

$cesta = $koren . '/docs/00-stav-projektu.md';
if (!is_file($cesta)) {
    fwrite(STDERR, "  Soubor $cesta neexistuje.\n");
    exit(1);
}

$obsah = (string) file_get_contents($cesta);
$novy = vlozBlok($obsah, $blok);

if ($novy === $obsah) {
    echo "  Blok je aktuální, nic se nemění.\n";
    exit(0);
}

file_put_contents($cesta, $novy);
echo "  Blok zapsán do docs/00-stav-projektu.md\n";
exit(0);

// ---------------------------------------------------------------------------

/** Poskládá blok z toho, co jde přečíst z repozitáře. */
function sestavBlok(string $koren): string
{
    $radky = [];

    $verze = zjistiVerzi($koren);
    if ($verze !== null) {
        $radky[] = popisek('verze:') . $verze;
    }

    $beh = zjistiBehoveProstredi($koren);
    if ($beh !== null) {
        $radky[] = popisek('běhové prostředí:') . $beh;
    }

    $vetev = git($koren, 'rev-parse --abbrev-ref HEAD');
    if ($vetev !== null) {
        $radky[] = popisek('hlavní větev:') . $vetev;
    }

    // Otisk posledního commitu ani jejich počet se sem schválně nepíšou.
    // Kontrola porovnává blok s tím, co by skript vygeneroval teď, a tyhle
    // dvě hodnoty se mění každým commitem, takže by blok byl zastaralý
    // už v commitu, který ho obsahuje.

    return ZNACKA_ZACATEK . "\n```\n" . implode("\n", $radky) . "\n```\n" . ZNACKA_KONEC;
}

/**
 * Popisek zarovnaný na pevnou šířku.
 *
 * sprintf s %-16s tady nestačí: počítá bajty, ne znaky, takže každé písmeno
 * s diakritikou zkrátí odsazení o jeden sloupec a tabulka se rozjede.
 */
function popisek(string $text): string
{
    return mb_str_pad($text, 18);
}

/** Vloží blok do textu, nebo ho nahradí, když už tam je. */
function vlozBlok(string $obsah, string $blok): string
{
    $zacatek = strpos($obsah, ZNACKA_ZACATEK);
    $konec = strpos($obsah, ZNACKA_KONEC);

    if ($zacatek !== false && $konec !== false && $konec > $zacatek) {
        return substr($obsah, 0, $zacatek)
            . $blok
            . substr($obsah, $konec + strlen(ZNACKA_KONEC));
    }

    // Blok ještě není: vloží se PŘED první nadpis druhé úrovně, tedy hned
    // za úvod. Za nadpis se vkládat nemá, tam by rozdělil sekci vejpůl.
    if (preg_match('/^## .*$/mu', $obsah, $shoda, PREG_OFFSET_CAPTURE)) {
        $pred = $shoda[0][1];

        return substr($obsah, 0, $pred) . $blok . "

" . substr($obsah, $pred);
    }

    return rtrim($obsah) . "\n\n" . $blok . "\n";
}

function zjistiVerzi(string $koren): ?string
{
    $balicek = $koren . '/package.json';
    if (is_file($balicek)) {
        $data = json_decode((string) file_get_contents($balicek), true);
        if (is_array($data) && isset($data['version'])) {
            return (string) $data['version'];
        }
    }

    $app = $koren . '/app.json';
    if (is_file($app)) {
        $data = json_decode((string) file_get_contents($app), true);
        if (is_array($data) && isset($data['expo']['version'])) {
            return (string) $data['expo']['version'];
        }
    }

    $config = $koren . '/config.php';
    if (is_file($config)) {
        $text = (string) file_get_contents($config);
        if (preg_match("/define\(\s*'APP_VERSION'\s*,\s*'([^']+)'/", $text, $shoda)) {
            return $shoda[1];
        }
    }

    return null;
}

function zjistiBehoveProstredi(string $koren): ?string
{
    $composer = $koren . '/composer.json';
    if (is_file($composer)) {
        $data = json_decode((string) file_get_contents($composer), true);
        if (is_array($data) && isset($data['require']['php'])) {
            return 'PHP ' . $data['require']['php'];
        }
    }

    $balicek = $koren . '/package.json';
    if (is_file($balicek)) {
        $data = json_decode((string) file_get_contents($balicek), true);
        if (is_array($data) && isset($data['engines']['node'])) {
            return 'Node ' . $data['engines']['node'];
        }
        if (is_array($data) && isset($data['dependencies']['expo'])) {
            return 'Expo ' . $data['dependencies']['expo'];
        }
    }

    return null;
}

/**
 * Cesta ve tvaru, kterému rozumí git.
 *
 * PHP na Windows dostane z Git Bashe cestu jako /c/laragon/..., ale git.exe
 * jí nerozumí a skončí chybou 128.
 */
function proGit(string $cesta): string
{
    if (preg_match('#^/([a-z])/(.*)$#i', $cesta, $shoda)) {
        return strtoupper($shoda[1]) . ':/' . $shoda[2];
    }

    return $cesta;
}

/** Spustí git v daném repozitáři a vrátí první řádek výstupu. */
function git(string $koren, string $prikaz): ?string
{
    $vystup = [];
    $kod = 0;
    // Potlaceni chyb se na Windows a na Linuxu pise jinak. CI bezi na Linuxu,
    // vyvoj na Windows, takze to musi umet obojí.
    $ticho = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';
    exec(sprintf('git -C %s %s %s', escapeshellarg(proGit($koren)), $prikaz, $ticho), $vystup, $kod);

    if ($kod !== 0 || $vystup === []) {
        return null;
    }

    return trim($vystup[0]);
}
