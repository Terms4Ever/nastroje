<?php
/**
 * Kontrola issues na GitHubu proti společnému tvaru.
 *
 *     php kontrola-issues.php <koren> [--od RRRR-MM-DD] [--jen-otevrene]
 *
 * Čte issues přes `gh` (na GitHubu i lokálně), takže potřebuje přihlášené
 * `gh` nebo proměnnou GH_TOKEN. Repozitář se bere z remote.origin.url.
 *
 * Co hlídá:
 *   1. tvar: pevný seznam sekcí v pevném pořadí, jeden checklist pod
 *      "Hotovo, když" a tělo do 40 řádků
 *   2. zavřené issue má odškrtnutý celý checklist
 *   3. nikde se nezmiňuje Claude ani že text psal nástroj
 *   4. žádné dlouhé ani poloviční pomlčky
 *   5. komentáře do pěti řádků
 *   6. snímky v docs/snimky/ mají tvar cislo-nazev/pred-*.png a v repozitáři
 *      opravdu leží
 *   7. zavřené issue, které má snímek "před", má i snímek "po"
 *   8. issue ani komentář nevznikl přes aplikaci: GitHub takový záznam označí
 *      jmenovkou "with <aplikace>" vedle autora a smazat to jde jen tak, že se
 *      text napíše znovu
 *
 * Starší issues se berou mírněji: přísné jsou od data, kdy standard vznikl
 * (přepínač --od, výchozí 2026-09-21, tedy den po zavedení standardu).
 * Dřívější porušení jen upozorní: pravidlo nemá trestat zpětně.
 *
 * Nápad od zadavatele (tělo bez jediného nadpisu "## ") není chyba. Je to
 * syrové zadání, které se teprve přepíše do tvaru; skript ho vypíše zvlášť
 * jako "k přepsání", aby se nezapomnělo, a kontrolu tím neshodí.
 *
 * Vedomá výjimka: tenhle skript nikdy nic nemění, jen hlásí.
 */
declare(strict_types=1);

require_once __DIR__ . '/src/tvar-issue.php';

const VERZE_ISSUES = '1.3.0';

/** Snímky: docs/snimky/12-kratky-nazev/pred-neco.png */
const VZOR_SNIMKU = '#^docs/snimky/\d+-[a-z0-9]+(-[a-z0-9]+)*/(pred|po)-[a-z0-9]+(-[a-z0-9]+)*\.(png|jpg|webp)$#';

const MAX_RADKU_KOMENTARE = 5;

$koren = rtrim($argv[1] ?? getcwd(), "/\\");
$od = '2026-09-21';
$jenOtevrene = false;
foreach ($argv as $i => $arg) {
    if ($arg === '--od') {
        $od = $argv[$i + 1] ?? $od;
    }
    if ($arg === '--jen-otevrene') {
        $jenOtevrene = true;
    }
}

$slug = slugRepozitare($koren);
if ($slug === null) {
    echo "  Kontrola issues: repozitář není na github.com/Terms4Ever, přeskakuji.\n";
    exit(0);
}

$issues = nactiIssues($slug, $jenOtevrene);
if ($issues === null) {
    echo "  Kontrola issues: nepodařilo se načíst issues přes gh (chybí přihlášení?).\n";
    exit(0);
}

$chyby = [];
$varovani = [];
$napady = [];

foreach ($issues as $issue) {
    $cislo = (int) ($issue['number'] ?? 0);
    $telo = (string) ($issue['body'] ?? '');
    $stav = (string) ($issue['state'] ?? '');
    $vznik = substr((string) ($issue['createdAt'] ?? ''), 0, 10);
    $prisne = $vznik >= $od;

    $pridej = static function (string $text) use (&$chyby, &$varovani, $prisne): void {
        if ($prisne) {
            $chyby[] = $text;
        } else {
            $varovani[] = $text;
        }
    };

    // 0. Syrový nápad zadavatele: bez jediného nadpisu. Čeká na přepsání.
    if (jeSyroveIssue($telo)) {
        $napady[] = "#$cislo " . trim((string) ($issue['title'] ?? ''));
        $syrove = true;
    } else {
        $syrove = false;
    }

    // 1. Tvar
    foreach (problemyTvaru($telo) as $problem) {
        $pridej("#$cislo $problem");
    }

    $checklist = checklist($telo);

    // 2. Zavřené issue má hotový checklist
    if (strtoupper($stav) === 'CLOSED' && $checklist !== []) {
        $neodskrtnute = count(array_filter($checklist, static fn (bool $h): bool => !$h));
        if ($neodskrtnute > 0) {
            $pridej("#$cislo je zavřené, ale " . bodu($neodskrtnute) . ' v checklistu zůstalo neodškrtnutých');
        }
    }

    // 3. a 4. Zmínky a pomlčky v těle i komentářích
    $texty = $syrove ? [] : [['tělo', $telo]];
    foreach ($issue['comments'] ?? [] as $poradi => $komentar) {
        $texty[] = ['komentář ' . ($poradi + 1), (string) ($komentar['body'] ?? '')];
    }

    foreach ($texty as [$kde, $text]) {
        if (preg_match('/claude|generated with|jako AI\b/i', $text) === 1) {
            $pridej("#$cislo $kde zmiňuje nástroj, kterým se text psal");
        }
        if (str_contains($text, "\u{2013}") || str_contains($text, "\u{2014}")) {
            $pridej("#$cislo $kde obsahuje dlouhou nebo poloviční pomlčku");
        }
    }

    // 5. Délka komentářů
    foreach ($issue['comments'] ?? [] as $poradi => $komentar) {
        $radky = radkyBezPrazdnych((string) ($komentar['body'] ?? ''));
        if ($radky > MAX_RADKU_KOMENTARE) {
            $vznikKomentare = substr((string) ($komentar['createdAt'] ?? ''), 0, 10);
            $text = "#$cislo komentář " . ($poradi + 1) . " má $radky řádků, limit je " . MAX_RADKU_KOMENTARE;
            if ($vznikKomentare >= $od) {
                $chyby[] = $text;
            } else {
                $varovani[] = $text;
            }
        }
    }

    // 6. Snímky
    foreach ($syrove ? [] : snimkyVTextu($telo) as $cesta) {
        if (preg_match(VZOR_SNIMKU, $cesta) !== 1) {
            $pridej("#$cislo odkazuje na snímek $cesta, který nemá tvar docs/snimky/cislo-nazev/pred-neco.png");
            continue;
        }
        if (!is_file($koren . '/' . $cesta)) {
            $pridej("#$cislo odkazuje na snímek $cesta, ten v repozitáři není");
            continue;
        }
        if (!str_starts_with($cesta, "docs/snimky/$cislo-")) {
            $pridej("#$cislo odkazuje na snímek $cesta, ten patří k jinému issue");
        }
    }

    // 7. Když je vidět stav "před", musí být vidět i "po". Jinak zůstane
    //    v issue jen fotka rozbitého stavu a nikdo nepozná, co se změnilo.
    if (strtoupper($stav) === 'CLOSED') {
        $vlastni = array_filter(
            snimkyVRepozitari($koren),
            static fn (string $c): bool => str_starts_with($c, "docs/snimky/$cislo-")
        );
        $pred = array_filter($vlastni, static fn (string $c): bool => str_contains(basename($c), 'pred-'));
        $po = array_filter($vlastni, static fn (string $c): bool => str_contains(basename($c), 'po-'));

        if ($pred !== [] && $po === []) {
            $pridej("#$cislo je zavřené a má snímek před, ale žádný po; bez něj není vidět, co se změnilo");
        }
    }
}

// 7. Razítko aplikace u autora
foreach (razitkaAplikace($slug, $varovani) as $popis) {
    $chyby[] = $popis;
}

// Snímky, ke kterým se nehlásí žádné issue
foreach (snimkyVRepozitari($koren) as $cesta) {
    if (preg_match(VZOR_SNIMKU, $cesta) !== 1) {
        $chyby[] = "$cesta nemá tvar docs/snimky/cislo-nazev/pred-neco.png";
    }
}

// --------------------------------------------------------------------------
// Výsledek
// --------------------------------------------------------------------------
$nazev = basename(realpath($koren) ?: $koren);

if ($napady !== []) {
    echo "\n  Nápady k přepsání do tvaru (" . count($napady) . "):\n\n";
    foreach ($napady as $radek) {
        echo "    $radek\n";
    }
    echo "\n";
}

if ($varovani !== []) {
    echo "\n  Issues $nazev, starší než $od (jen upozornění):\n\n";
    foreach ($varovani as $radek) {
        echo "    $radek\n";
    }
}

if ($chyby !== []) {
    echo "\n  Issues $nazev neprošly:\n\n";
    foreach ($chyby as $radek) {
        echo "    $radek\n";
    }
    echo "\n  Sekce, jiné nejsou: " . povoleneSekce() . "\n";
    echo "  Celý tvar je v šabloně .github/ISSUE_TEMPLATE/ukol.md\n\n";
    exit(1);
}

printf(
    "  Issues %s jsou v pořádku (%d zkontrolovaných, kontrola %s).\n",
    $nazev,
    count($issues),
    VERZE_ISSUES
);
exit(0);

// ==========================================================================
// Funkce
// ==========================================================================

/**
 * Issues a komentáře, které GitHub označuje jmenovkou aplikace u autora.
 * Pole `performed_via_github_app` se nastavuje při vzniku a úprava ho nesmaže;
 * jediná cesta zpět je napsat text znovu a starý smazat.
 *
 * Schválně bez `--jq`: escapeshellarg na Windows zahodí uvozovky a ze `!=`
 * udělá ` =`, takže by se filtr rozpadl a kontrola by mlčky procházela.
 */
function razitkaAplikace(string $slug, array &$varovani): array
{
    $nalezy = [];

    foreach (['issues?state=all&' => 'issue #', 'issues/comments?' => 'komentář u issue #'] as $cesta => $popisek) {
        for ($stranka = 1; $stranka <= 10; $stranka++) {
            $data = ghJson(sprintf('repos/%s/%sper_page=100&page=%d', $slug, $cesta, $stranka));
            if ($data === null) {
                $varovani[] = 'nepodařilo se ověřit, jestli issues nevznikly přes aplikaci (gh api selhalo)';
                break 2;
            }
            if ($data === []) {
                break;
            }

            foreach ($data as $zaznam) {
                $aplikace = $zaznam['performed_via_github_app']['name'] ?? null;
                if ($aplikace === null) {
                    continue;
                }
                $adresa = (string) ($zaznam['issue_url'] ?? $zaznam['html_url'] ?? '');
                $cislo = (int) preg_replace('/^.*?(\d+)$/', '$1', rtrim($adresa, '/'));
                $nalezy[] = sprintf(
                    '%s%d vznikl přes aplikaci %s; GitHub to ukáže u autora a zmizí to jen přepsáním',
                    $popisek,
                    $cislo,
                    $aplikace
                );
            }

            if (count($data) < 100) {
                break;
            }
        }
    }

    return $nalezy;
}

/** Jedno volání gh api, výsledek jako pole. Null znamená, že příkaz selhal. */
function ghJson(string $cesta): ?array
{
    $vystup = [];
    $kod = 0;
    $ticho = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';
    exec(sprintf('gh api %s %s', escapeshellarg($cesta), $ticho), $vystup, $kod);
    if ($kod !== 0) {
        return null;
    }

    $data = json_decode(implode("", $vystup), true);

    return is_array($data) ? $data : null;
}

/** owner/repo z remote.origin.url, nebo null když to není Terms4Ever na GitHubu. */
function slugRepozitare(string $koren): ?string
{
    $url = git($koren, 'config --get remote.origin.url');
    if ($url === null) {
        return null;
    }
    if (preg_match('#github\.com[:/](Terms4Ever/[^/]+?)(\.git)?$#i', $url, $shoda) !== 1) {
        return null;
    }

    return $shoda[1];
}

/** Issues i s komentáři. Null, když gh selže. */
function nactiIssues(string $slug, bool $jenOtevrene): ?array
{
    $stav = $jenOtevrene ? 'open' : 'all';
    $prikaz = sprintf(
        'gh issue list --repo %s --state %s --limit 200 --json number,title,body,state,createdAt,comments',
        escapeshellarg($slug),
        $stav
    );

    $vystup = [];
    $kod = 0;
    exec($prikaz . (PHP_OS_FAMILY === 'Windows' ? ' 2>NUL' : ' 2>/dev/null'), $vystup, $kod);
    if ($kod !== 0) {
        return null;
    }

    $data = json_decode(implode("\n", $vystup), true);

    return is_array($data) ? $data : null;
}

/** Cesty ke snímkům zmíněné v textu. */
function snimkyVTextu(string $text): array
{
    preg_match_all('#docs/snimky/[^\s)"\'\]]+#', $text, $shody);

    return array_values(array_unique(array_map(
        static fn (string $c): string => rtrim(preg_replace('/\?.*$/', '', $c) ?? $c, '.,'),
        $shody[0] ?? []
    )));
}

/** Snímky, které v repozitáři leží. */
function snimkyVRepozitari(string $koren): array
{
    $slozka = $koren . '/docs/snimky';
    if (!is_dir($slozka)) {
        return [];
    }

    $nalezene = [];
    $prochazeni = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($slozka, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($prochazeni as $soubor) {
        if (!$soubor->isFile()) {
            continue;
        }
        $cesta = str_replace('\\', '/', $soubor->getPathname());
        $relativni = substr($cesta, strlen(str_replace('\\', '/', $koren)) + 1);
        if (basename($relativni) === 'README.md') {
            continue;
        }
        $nalezene[] = $relativni;
    }
    sort($nalezene);

    return $nalezene;
}

/** Cesta pro git: /c/... rozumí jen Git Bash, gitu se předává C:/... */
function proGit(string $cesta): string
{
    if (preg_match('#^/([a-zA-Z])/(.*)$#', $cesta, $shoda) === 1) {
        return strtoupper($shoda[1]) . ':/' . $shoda[2];
    }

    return $cesta;
}

function git(string $koren, string $prikaz): ?string
{
    $vystup = [];
    $kod = 0;
    $ticho = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';
    exec(sprintf('git -C %s %s %s', escapeshellarg(proGit($koren)), $prikaz, $ticho), $vystup, $kod);

    if ($kod !== 0 || $vystup === []) {
        return null;
    }

    return trim($vystup[0]);
}
