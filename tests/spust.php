<?php
/**
 * Regresní testy společných kontrol.
 *
 *     php tests/spust.php            všechny případy
 *     php tests/spust.php migrace    jen případy, v jejichž názvu je "migrace"
 *
 * Každý případ postaví dočasný repozitář, pustí na něj kontrolu a porovná
 * návratový kód a kus výstupu s očekáváním. Pravidlo z AGENTS.md zní, že
 * každá kontrola má případ, který ji shodí; bez něj se nepozná, že přestala
 * fungovat. Audit z 23. 9. 2026 našel šest děr, které by tahle sada chytila
 * (N32).
 *
 * Bez závislostí jako zbytek repozitáře: stačí PHP 8.3 a git. Na GitHubu běží
 * v Linuxu, doma na Windows; případy s hookem Claude Code jsou jen pro Windows.
 */
declare(strict_types=1);

const NASTROJE = __DIR__ . '/..';
const DNES = '2026-09-23';

$filtr = $argv[1] ?? null;
$docasne = [];
$vysledky = [];

// ==========================================================================
// Případy
// ==========================================================================

// --- kontrola dokumentace -------------------------------------------------

pripad('dokumentace: platný projekt projde', function (): array {
    $repo = novyProjekt();

    return ocekavej(php('kontrola-dokumentace.php', $repo), 0, 'v pořádku');
});

pripad('dokumentace: zapnutá kontrola a chybějící docs/ neprojde (A02)', function (): array {
    $repo = novyProjekt();
    smaz($repo . '/docs');

    return ocekavej(php('kontrola-dokumentace.php', $repo), 1, 'docs/ chybí');
});

pripad('dokumentace: vypnutá kontrola a chybějící docs/ projde', function (): array {
    $repo = novyProjekt(['docs-kontrola' => false]);
    smaz($repo . '/docs');

    return ocekavej(php('kontrola-dokumentace.php', $repo), 0, 'není pro tenhle repozitář zapnutá');
});

pripad('dokumentace: neexistující cesta neprojde', function (): array {
    return ocekavej(php('kontrola-dokumentace.php', neexistujici()), 1, 'neexistuje');
});

pripad('dokumentace: dávka s kódem bez docs/ neprojde', function (): array {
    $repo = novyProjekt();
    $zaklad = git($repo, 'rev-parse', 'HEAD');
    zapis($repo, 'app/kod.php', "<?php\nfunction ahoj(): string { return 'svete'; }\n");
    commit($repo, 'Kód bez dokumentace');

    return ocekavej(php('kontrola-dokumentace.php', $repo, $zaklad, git($repo, 'rev-parse', 'HEAD')), 1, 'dávka mění');
});

pripad('dokumentace: dávka s kódem a změněným dokumentem projde', function (): array {
    $repo = novyProjekt();
    $zaklad = git($repo, 'rev-parse', 'HEAD');
    zapis($repo, 'app/kod.php', "<?php\nfunction ahoj(): string { return 'svete'; }\n");
    pripis($repo, 'docs/03-rozhodovaci-dennik.md', "\n## T2 - Pozdrav se změnil (23. 9. 2026)\n\nFunkce vrací jiný text.\n");
    commit($repo, 'Kód i deník');

    return ocekavej(php('kontrola-dokumentace.php', $repo, $zaklad, git($repo, 'rev-parse', 'HEAD')), 0, 'v pořádku');
});

pripad('dokumentace: kód a jen obrázek v docs/snimky neprojde (A04)', function (): array {
    $repo = novyProjekt();
    $zaklad = git($repo, 'rev-parse', 'HEAD');
    zapis($repo, 'app/kod.php', "<?php\nfunction ahoj(): string { return 'svete'; }\n");
    zapis($repo, 'docs/snimky/1-neco/pred-neco.png', "\x89PNG\r\n\x1a\n");
    commit($repo, 'Kód a nesouvisející obrázek');

    return ocekavej(php('kontrola-dokumentace.php', $repo, $zaklad, git($repo, 'rev-parse', 'HEAD')), 1, 'dávka mění');
});

pripad('dokumentace: kód a jen smazaný dokument neprojde (A04)', function (): array {
    $repo = novyProjekt([], ['docs/04-poznamky.md' => "# Poznámky\n\nNěco k zapamatování.\n"]);
    $zaklad = git($repo, 'rev-parse', 'HEAD');
    zapis($repo, 'app/kod.php', "<?php\nfunction ahoj(): string { return 'svete'; }\n");
    smaz($repo . '/docs/04-poznamky.md');
    commit($repo, 'Kód a smazaný dokument');

    return ocekavej(php('kontrola-dokumentace.php', $repo, $zaklad, git($repo, 'rev-parse', 'HEAD')), 1, 'dávka mění');
});

pripad('dokumentace: neexistující základ rozsahu neprojde (A03)', function (): array {
    $repo = novyProjekt();

    return ocekavej(
        php('kontrola-dokumentace.php', $repo, str_repeat('1', 40), git($repo, 'rev-parse', 'HEAD')),
        1,
        'základ rozsahu'
    );
});

pripad('dokumentace: nulový základ se ohlásí, ne zamlčí', function (): array {
    $repo = novyProjekt();

    return ocekavej(
        php('kontrola-dokumentace.php', $repo, str_repeat('0', 40), git($repo, 'rev-parse', 'HEAD')),
        0,
        'nová větev'
    );
});

pripad('dokumentace: cesta z Git Bashe (/c/...) funguje', function (): array {
    if (PHP_OS_FAMILY !== 'Windows') {
        return [true, 'přeskočeno, jen pro Windows'];
    }
    $repo = novyProjekt();
    $bash = preg_replace_callback('#^([A-Za-z]):[\\\\/]#', static fn ($m) => '/' . strtolower($m[1]) . '/', $repo);
    $bash = str_replace('\\', '/', (string) $bash);

    return ocekavej(php('kontrola-dokumentace.php', $bash), 0, 'v pořádku');
});

// --- kontrola migrací -----------------------------------------------------

pripad('migrace: platné migrace projdou', function (): array {
    $repo = projektSMigraci();

    return ocekavej(php('kontrola-migraci.php', $repo), 0, 'v pořádku');
});

pripad('migrace: přejmenování hotové migrace neprojde (A07)', function (): array {
    $repo = projektSMigraci();
    $zaklad = git($repo, 'rev-parse', 'HEAD');
    git($repo, 'mv', 'db/migrace/2026-01-10-zaklad-uzivatelu.sql', 'db/migrace/2026-01-10-zaklad-uctu.sql');
    php('prehled-migraci.php', $repo, '--zapsat');
    commit($repo, 'Migrace přejmenovaná');

    return ocekavej(php('kontrola-migraci.php', $repo, $zaklad, git($repo, 'rev-parse', 'HEAD')), 1, 'přejmenovává');
});

pripad('migrace: změna obsahu hotové migrace neprojde', function (): array {
    $repo = projektSMigraci();
    $zaklad = git($repo, 'rev-parse', 'HEAD');
    zapis($repo, 'db/migrace/2026-01-10-zaklad-uzivatelu.sql', "-- Základ tabulky uživatelů\nCREATE TABLE uzivatel (id INT PRIMARY KEY, email TEXT);\n");
    php('prehled-migraci.php', $repo, '--zapsat');
    commit($repo, 'Migrace upravená');

    return ocekavej(php('kontrola-migraci.php', $repo, $zaklad, git($repo, 'rev-parse', 'HEAD')), 1, 'mění');
});

pripad('migrace: smazání hotové migrace neprojde', function (): array {
    $repo = projektSMigraci();
    $zaklad = git($repo, 'rev-parse', 'HEAD');
    zapis($repo, 'db/migrace/2026-02-01-dalsi.sql', "-- Tabulka poznámek\nCREATE TABLE poznamka (id INT PRIMARY KEY);\n");
    smaz($repo . '/db/migrace/2026-01-10-zaklad-uzivatelu.sql');
    php('prehled-migraci.php', $repo, '--zapsat');
    commit($repo, 'Migrace smazaná');

    return ocekavej(php('kontrola-migraci.php', $repo, $zaklad, git($repo, 'rev-parse', 'HEAD')), 1, 'maže');
});

pripad('migrace: nová migrace projde', function (): array {
    $repo = projektSMigraci();
    $zaklad = git($repo, 'rev-parse', 'HEAD');
    zapis($repo, 'db/migrace/2026-02-01-poznamky.sql', "-- Tabulka poznámek\nCREATE TABLE poznamka (id INT PRIMARY KEY);\n");
    php('prehled-migraci.php', $repo, '--zapsat');
    commit($repo, 'Nová migrace');

    return ocekavej(php('kontrola-migraci.php', $repo, $zaklad, git($repo, 'rev-parse', 'HEAD')), 0, 'v pořádku');
});

pripad('migrace: neexistující cesta neprojde', function (): array {
    return ocekavej(php('kontrola-migraci.php', neexistujici()), 1, 'neexistuje');
});

// --- kontrola issues (gh nahrazený atrapou) -------------------------------

pripad('issues: platné issue projde', function (): array {
    return ocekavej(issuesSAtrapou('ok', [issue()]), 0, 'v pořádku');
});

pripad('issues: gh vrací chybu, kontrola neprojde (A05)', function (): array {
    return ocekavej(issuesSAtrapou('chyba', [issue()]), 1, 'neproběhla');
});

pripad('issues: gh vrací nesmysl, kontrola neprojde (A05)', function (): array {
    return ocekavej(issuesSAtrapou('nesmysl', [issue()]), 1, 'neproběhla');
});

pripad('issues: selhání gh api u razítek neprojde (A05)', function (): array {
    return ocekavej(issuesSAtrapou('api-chyba', [issue()]), 1, 'přes aplikaci');
});

pripad('issues: issue bez štítku druhu neprojde', function (): array {
    return ocekavej(issuesSAtrapou('ok', [issue(['labels' => []])]), 1, 'štítek druhu');
});

pripad('issues: neexistující cesta neprojde', function (): array {
    return ocekavej(php('kontrola-issues.php', neexistujici()), 1, 'neexistuje');
});

// --- tvar jednoho těla issue ----------------------------------------------

$teloChyby = "## Problém\n\nTlačítko Uložit nic neudělá.\n\n## Hotovo, když\n\n- [ ] tlačítko uloží\n";
$teloSyrove = "Chtělo by to tmavý režim.\n";

foreach ([
    ['bez štítku a odpovědného', $teloChyby, '', '', 1],
    ['bug a odpovědný projde', $teloChyby, 'bug', 'Terms4Ever', 0],
    ['sekce Problém se štítkem enhancement', $teloChyby, 'enhancement', 'Terms4Ever', 1],
    ['dva štítky druhu naráz', $teloChyby, 'bug,enhancement', 'Terms4Ever', 1],
    ['duplicate druh nepotřebuje', $teloChyby, 'duplicate', '', 0],
    ['doménový štítek navíc projde', $teloChyby, 'bug,export', 'Terms4Ever', 0],
    ['syrový nápad zadavatele projde', $teloSyrove, '', '', 0],
] as [$nazev, $telo, $stitky, $odpovedni, $kod]) {
    pripad('tvar issue: ' . $nazev, function () use ($telo, $stitky, $odpovedni, $kod): array {
        $soubor = docasna('telo') . '/telo.md';
        file_put_contents($soubor, $telo);

        return ocekavej(php('kontrola-tvaru-issue.php', $soubor, '--stitky', $stitky, '--odpovedni', $odpovedni), $kod);
    });
}

// --- README a generátor stavu ---------------------------------------------

pripad('readme: neexistující cesta neprojde', function (): array {
    return ocekavej(php('kontrola-readme.php', neexistujici()), 1);
});

pripad('stav projektu: u Expo aplikace je verze z app.json (A13)', function (): array {
    $repo = novyProjekt([], [
        'package.json' => "{\n  \"name\": \"aplikace\",\n  \"version\": \"1.0.0\"\n}\n",
        'app.json' => "{\n  \"expo\": {\n    \"name\": \"Aplikace\",\n    \"version\": \"1.0.2\"\n  }\n}\n",
    ]);

    return ocekavej(php('stav-projektu.php', $repo), 0, '1.0.2');
});

// --- hook Claude Code (jen Windows) ---------------------------------------

$akce = 'cre' . 'ate';   // složené, jinak by hook zastavil i příkaz, který tenhle soubor jen zmiňuje
foreach ([
    ['zakládání bez štítku', "gh issue $akce -R Terms4Ever/x -t T --body-file %s", 2],
    ['zakládání bez odpovědného', "gh issue $akce -R Terms4Ever/x -t T --label bug --body-file %s", 2],
    ['zakládání se vším projde', "gh issue $akce -R Terms4Ever/x -t T --label bug --assignee Terms4Ever --body-file %s", 0],
    ['zkratky -l a -a projdou', "gh issue $akce -R Terms4Ever/x -t T -l bug -a Terms4Ever --body-file %s", 0],
    ['vložené --body neprojde', "gh issue $akce -R Terms4Ever/x -t T -l bug -a Terms4Ever --body \"text\"", 2],
    ['jen zmínka v textu projde', "echo \"napsat, že gh issue $akce potřebuje --label\"", 0],
] as [$nazev, $vzor, $kod]) {
    pripad('hook: ' . $nazev, function () use ($vzor, $kod, $teloChyby): array {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [true, 'přeskočeno, hook je jen pro Windows'];
        }
        $soubor = docasna('hook') . DIRECTORY_SEPARATOR . 'telo.md';
        file_put_contents($soubor, $teloChyby);
        $vstup = json_encode(['tool_input' => ['command' => sprintf($vzor, $soubor)], 'cwd' => dirname($soubor)]);

        return ocekavej(spust(
            ['powershell', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', NASTROJE . '/hooky/tvar-issue.ps1'],
            [],
            null,
            (string) $vstup
        ), $kod);
    });
}

// ==========================================================================
// Výsledek
// ==========================================================================

foreach ($docasne as $slozka) {
    smaz($slozka);
}

$chyb = count(array_filter($vysledky, static fn (array $v): bool => !$v[1]));
echo "\n";
foreach ($vysledky as [$nazev, $ok, $detail]) {
    printf("  %s  %s%s\n", $ok ? 'ok   ' : 'CHYBA', $nazev, $ok ? ($detail !== '' ? "  ($detail)" : '') : '');
    if (!$ok) {
        echo '         ' . str_replace("\n", "\n         ", rtrim($detail)) . "\n";
    }
}
printf("\n  %d případů, %d neprošlo.\n\n", count($vysledky), $chyb);
exit($chyb === 0 ? 0 : 1);

// ==========================================================================
// Pomocné
// ==========================================================================

function pripad(string $nazev, callable $telo): void
{
    global $filtr, $vysledky;
    if ($filtr !== null && !str_contains($nazev, $filtr)) {
        return;
    }
    try {
        [$ok, $detail] = $telo();
    } catch (Throwable $e) {
        [$ok, $detail] = [false, 'výjimka: ' . $e->getMessage()];
    }
    $vysledky[] = [$nazev, $ok, $detail];
}

/** @return array{0: bool, 1: string} */
function ocekavej(array $vysledek, int $kod, ?string $text = null): array
{
    $sedi = $vysledek['kod'] === $kod && ($text === null || str_contains($vysledek['vystup'], $text));
    if ($sedi) {
        return [true, str_contains($vysledek['vystup'], 'přeskočeno') ? 'přeskočeno' : ''];
    }

    $ukazka = implode("\n", array_slice(array_filter(array_map('rtrim', explode("\n", $vysledek['vystup']))), 0, 6));

    return [false, sprintf(
        "kód %d, čekán %d%s\n%s",
        $vysledek['kod'],
        $kod,
        $text !== null ? sprintf(', ve výstupu měl být text "%s"', $text) : '',
        $ukazka
    )];
}

/** @return array{kod: int, vystup: string} */
function spust(array $prikaz, array $prostredi = [], ?string $slozka = null, ?string $vstup = null): array
{
    $env = null;
    if ($prostredi !== []) {
        $env = getenv();
        foreach ($prostredi as $klic => $hodnota) {
            // Windows nerozlišuje velikost písmen v názvech proměnných, PHP ano:
            // Path a PATH by vedle sebe udělaly dvě různé cesty.
            foreach (array_keys($env) as $existujici) {
                if (strcasecmp((string) $existujici, $klic) === 0) {
                    unset($env[$existujici]);
                }
            }
            $env[$klic] = $hodnota;
        }
    }

    $proces = proc_open($prikaz, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $roury, $slozka, $env);
    if (!is_resource($proces)) {
        throw new RuntimeException('nepodařilo se spustit ' . implode(' ', $prikaz));
    }
    if ($vstup !== null) {
        fwrite($roury[0], $vstup);
    }
    fclose($roury[0]);
    $vystup = (string) stream_get_contents($roury[1]);
    fclose($roury[1]);

    return ['kod' => proc_close($proces), 'vystup' => $vystup];
}

function php(string $skript, string ...$argumenty): array
{
    return spust(array_merge([PHP_BINARY, NASTROJE . '/' . $skript], $argumenty));
}

function git(string $repo, string ...$argumenty): string
{
    static $bezHooku = null;
    $bezHooku ??= docasna('bez-hooku');

    $vysledek = spust(array_merge([
        'git', '-C', $repo,
        '-c', 'user.name=Test',
        '-c', 'user.email=test@example.invalid',
        '-c', 'core.autocrlf=false',
        '-c', 'core.hooksPath=' . $bezHooku,
        '-c', 'commit.gpgsign=false',
        '-c', 'init.defaultBranch=main',
    ], $argumenty));
    if ($vysledek['kod'] !== 0) {
        throw new RuntimeException('git ' . implode(' ', $argumenty) . ' selhal: ' . trim($vysledek['vystup']));
    }

    return trim($vysledek['vystup']);
}

function commit(string $repo, string $zprava): void
{
    git($repo, 'add', '-A');
    git($repo, 'commit', '--no-verify', '-q', '-m', $zprava);
}

function zapis(string $repo, string $cesta, string $obsah): void
{
    $plna = $repo . '/' . $cesta;
    if (!is_dir(dirname($plna))) {
        mkdir(dirname($plna), 0777, true);
    }
    file_put_contents($plna, $obsah);
}

function pripis(string $repo, string $cesta, string $obsah): void
{
    file_put_contents($repo . '/' . $cesta, $obsah, FILE_APPEND);
}

function docasna(string $nazev): string
{
    global $docasne;
    $slozka = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nastroje-test-' . $nazev . '-' . bin2hex(random_bytes(4));
    mkdir($slozka, 0777, true);
    $docasne[] = $slozka;

    return $slozka;
}

function neexistujici(): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nastroje-test-neexistuje-' . bin2hex(random_bytes(4));
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

/**
 * Projekt, který projde kontrolou dokumentace: pokyny pro agenty, stav
 * s generovaným blokem a deník.
 */
function novyProjekt(array $nastaveni = [], array $soubory = []): string
{
    $repo = docasna('projekt');
    git($repo, 'init', '-q', '-b', 'main');

    $nastaveni = array_merge([
        'profil' => 'plny',
        'docs-kontrola' => true,
        'docs-pomlcky' => 'blokovat',
        'docs-vymahat-aktualizaci' => true,
    ], $nastaveni);
    zapis($repo, '.readme-kontrola.json', json_encode($nastaveni, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    zapis($repo, 'AGENTS.md', "# Zkušební projekt\n\nSlouží jen testům kontrol.\n");
    zapis($repo, 'CLAUDE.md', "@AGENTS.md\n");
    zapis($repo, 'docs/00-stav-projektu.md', "# Stav projektu\n\nZkušební projekt pro testy kontrol.\n");
    zapis($repo, 'docs/03-rozhodovaci-dennik.md', "# Rozhodovací deník\n\n## T1 - Založení (" . DNES . ")\n\nZkušební projekt.\n");
    zapis($repo, 'app/kod.php', "<?php\nfunction ahoj(): string { return 'ahoj'; }\n");
    foreach ($soubory as $cesta => $obsah) {
        zapis($repo, $cesta, $obsah);
    }
    commit($repo, 'Zkušební projekt');

    $blok = php('stav-projektu.php', $repo, '--zapsat');
    if ($blok['kod'] !== 0) {
        throw new RuntimeException('stav-projektu.php selhal: ' . trim($blok['vystup']));
    }
    commit($repo, 'Stav projektu má generovaný blok');

    return $repo;
}

function projektSMigraci(): string
{
    $repo = novyProjekt(['migrace-kontrola' => true], [
        'db/migrace/2026-01-10-zaklad-uzivatelu.sql' => "-- Základ tabulky uživatelů\nCREATE TABLE uzivatel (id INT PRIMARY KEY);\n",
    ]);
    $prehled = php('prehled-migraci.php', $repo, '--zapsat');
    if ($prehled['kod'] !== 0) {
        throw new RuntimeException('prehled-migraci.php selhal: ' . trim($prehled['vystup']));
    }
    commit($repo, 'Přehled migrací');

    return $repo;
}

function issue(array $zmeny = []): array
{
    return array_merge([
        'number' => 1,
        'title' => 'Tlačítko Uložit nic neudělá',
        'state' => 'OPEN',
        'createdAt' => DNES . 'T10:00:00Z',
        'comments' => [],
        'labels' => [['name' => 'bug']],
        'assignees' => [['login' => 'Terms4Ever']],
        'body' => "## Problém\n\nTlačítko Uložit nic neudělá.\n\n## Hotovo, když\n\n- [ ] tlačítko uloží\n",
    ], $zmeny);
}

/**
 * Pustí kontrolu issues na repozitář, kde místo gh odpovídá atrapa.
 * Režimy: ok, chyba (gh skončí kódem 1), nesmysl (kód 0, ale ne JSON),
 * api-chyba (seznam issues projde, gh api selže).
 */
function issuesSAtrapou(string $rezim, array $issues): array
{
    $repo = novyProjekt();
    git($repo, 'remote', 'add', 'origin', 'https://github.com/Terms4Ever/zkusebni.git');

    $atrapa = docasna('gh');
    file_put_contents($atrapa . '/data.json', json_encode($issues, JSON_UNESCAPED_UNICODE));
    file_put_contents($atrapa . '/gh', <<<'SH'
        #!/bin/sh
        case "$STUB_REZIM" in
          chyba) echo "gh: pristup zamitnut (atrapa)" >&2; exit 1 ;;
          nesmysl) echo "tohle neni JSON"; exit 0 ;;
        esac
        if [ "$1" = "api" ]; then
          if [ "$STUB_REZIM" = "api-chyba" ]; then echo "gh api: chyba (atrapa)" >&2; exit 1; fi
          echo "[]"
          exit 0
        fi
        cat "$STUB_DATA"

        SH);
    chmod($atrapa . '/gh', 0755);
    file_put_contents($atrapa . '/gh.cmd', implode("\r\n", [
        '@echo off',
        'if "%STUB_REZIM%"=="chyba" (echo gh: pristup zamitnut 1>&2 & exit /b 1)',
        'if "%STUB_REZIM%"=="nesmysl" (echo tohle neni JSON & exit /b 0)',
        'if "%1"=="api" goto api',
        'type "%STUB_DATA%"',
        'exit /b 0',
        ':api',
        'if "%STUB_REZIM%"=="api-chyba" (echo gh api: chyba 1>&2 & exit /b 1)',
        'echo []',
        'exit /b 0',
        '',
    ]));

    return spust([PHP_BINARY, NASTROJE . '/kontrola-issues.php', $repo], [
        'PATH' => $atrapa . PATH_SEPARATOR . (string) getenv('PATH'),
        'STUB_REZIM' => $rezim,
        'STUB_DATA' => $atrapa . DIRECTORY_SEPARATOR . 'data.json',
    ]);
}
