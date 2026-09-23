<?php
/**
 * Projdou projekty kontrolami z tohohle pracovního stromu?
 *
 *     php tests/kompatibilita.php
 *
 * Projekty volají společné kontroly větví @main, takže každá změna pravidla
 * platí okamžitě všude. Dřív se to poznalo až při nejbližším pushi projektu:
 * Igris měl zelený běh z 20. 9. ve 13:35 a pravidlo o AGENTS.md přibylo
 * v 20:28 téhož dne, takže nesl zelenou podle pravidel, která už neplatila
 * (audit 23. 9. 2026, N32).
 *
 * Skript naklonuje výchozí větev každého repozitáře Terms4Ever, který společné
 * kontroly volá, a pustí na něj README, dokumentaci, migrace i issues z tohohle
 * stromu. Pouští ho pre-push hook při pushi do nastroje: změna pravidla, kvůli
 * které by některý projekt spadl, neodejde, dokud se projekt neopraví.
 *
 * Potřebuje gh přihlášené k účtu, který vidí i privátní repozitáře.
 */
declare(strict_types=1);

const NASTROJE = __DIR__ . '/..';
const ODKAZ = 'Terms4Ever/nastroje/.github/workflows/readme.yml';
const KONTROLY = ['kontrola-readme.php', 'kontrola-dokumentace.php', 'kontrola-migraci.php', 'kontrola-issues.php'];

$seznam = spust(['gh', 'repo', 'list', 'Terms4Ever', '--limit', '100', '--json', 'name', '--jq', '.[].name']);
if ($seznam['kod'] !== 0) {
    fwrite(STDERR, "\n  Seznam repozitářů se nepodařilo načíst přes gh, kompatibilita neověřena:\n  "
        . trim($seznam['vystup']) . "\n\n");
    exit(1);
}
$repozitare = array_values(array_filter(array_map('trim', explode("\n", $seznam['vystup']))));
sort($repozitare);

$zaklad = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nastroje-kompatibilita-' . bin2hex(random_bytes(4));
mkdir($zaklad, 0777, true);

$spadlo = [];
$overeno = 0;
echo "\n";

foreach ($repozitare as $nazev) {
    // Nastroje se kontrolují přímo hookem nad pracovním stromem.
    if ($nazev === 'nastroje') {
        continue;
    }

    $cil = $zaklad . DIRECTORY_SEPARATOR . $nazev;
    // Úplný klon, ne mělký: kontrola issues ověřuje snímky v commitu, na který
    // odkaz míří, a ten v mělkém klonu není (N33).
    $klon = spust(['gh', 'repo', 'clone', "Terms4Ever/$nazev", $cil, '--', '--quiet']);
    if ($klon['kod'] !== 0) {
        $spadlo[] = $nazev;
        printf("  %-20s NEPROŠLO  klon se nepodařil: %s\n", $nazev, strtok(trim($klon['vystup']), "\n"));
        continue;
    }

    if (!volaSpolecneKontroly($cil)) {
        printf("  %-20s -         společné kontroly nevolá, přeskakuji\n", $nazev);
        continue;
    }

    $overeno++;
    $nalezy = [];
    foreach (KONTROLY as $kontrola) {
        $vysledek = spust([PHP_BINARY, NASTROJE . '/' . $kontrola, $cil]);
        if ($vysledek['kod'] !== 0) {
            $nalezy[$kontrola] = $vysledek['vystup'];
        }
    }

    if ($nalezy === []) {
        printf("  %-20s ok\n", $nazev);
        continue;
    }

    $spadlo[] = $nazev;
    printf("  %-20s NEPROŠLO  %s\n", $nazev, implode(', ', array_keys($nalezy)));
    foreach ($nalezy as $vystup) {
        foreach (array_slice(array_filter(array_map('rtrim', explode("\n", $vystup))), 0, 8) as $radek) {
            echo "                         $radek\n";
        }
    }
}

smaz($zaklad);

if ($overeno === 0) {
    fwrite(STDERR, "\n  Žádný repozitář nevolá společné kontroly, nebylo co ověřit.\n\n");
    exit(1);
}
if ($spadlo !== []) {
    printf("\n  Novými kontrolami neprojde %d z %d projektů: %s.\n", count($spadlo), $overeno, implode(', ', $spadlo));
    echo "  Oprav projekty ve stejné dávce, nebo pushni vědomě: git push --no-verify\n\n";
    exit(1);
}
printf("\n  Všech %d projektů projde kontrolami z tohohle stromu.\n\n", $overeno);
exit(0);

// ==========================================================================

function volaSpolecneKontroly(string $repo): bool
{
    foreach (glob($repo . '/.github/workflows/*.{yml,yaml}', GLOB_BRACE) ?: [] as $soubor) {
        if (str_contains((string) file_get_contents($soubor), ODKAZ)) {
            return true;
        }
    }

    return false;
}

/** @return array{kod: int, vystup: string} */
function spust(array $prikaz): array
{
    $proces = proc_open($prikaz, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $roury);
    if (!is_resource($proces)) {
        return ['kod' => 1, 'vystup' => 'nepodařilo se spustit ' . $prikaz[0]];
    }
    fclose($roury[0]);
    $vystup = (string) stream_get_contents($roury[1]);
    fclose($roury[1]);

    return ['kod' => proc_close($proces), 'vystup' => $vystup];
}

/** Smaže složku i se soubory gitu, které jsou na Windows jen ke čtení. */
function smaz(string $cesta): void
{
    if (is_link($cesta) || is_file($cesta)) {
        @chmod($cesta, 0666);
        unlink($cesta);

        return;
    }
    if (!is_dir($cesta)) {
        return;
    }
    foreach (scandir($cesta) ?: [] as $polozka) {
        if ($polozka !== '.' && $polozka !== '..') {
            smaz($cesta . '/' . $polozka);
        }
    }
    rmdir($cesta);
}
