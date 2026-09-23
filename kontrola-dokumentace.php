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
 *       "docs-vymahat-aktualizaci": true,
 *       "struktura-vyjimky": ["soubor, ktery smi zustat, kde je"]
 *     }
 *
 * Vedomá výjimka: git push --no-verify
 */
declare(strict_types=1);

const VERZE_DOKUMENTACE = '1.6.0';

/** Jediné dokumenty, které smí ležet v kořeni. Zbytek patří do docs/. */
const SOUBORY_V_KORENI = ['README.md', 'AGENTS.md', 'CLAUDE.md', 'LICENSE.md', 'CHANGELOG.md'];

/** Podsložky docs/, kam smí i jiné soubory než dokumenty. */
const PODSLOZKY_DOCS = ['snimky', 'prilohy'];

/**
 * Názvy, po kterých nikdo nepozná, která verze platí. Slovní značky se hlídají
 * jen u dokumentů a u obsahu docs/: ve zdrojovém kódu je "exercise-new.tsx"
 * poctivý název obrazovky, ne zapomenutá kopie.
 */
const ZAKAZANE_NAZVY_DOKUMENTU = [
    '/-(final|new|old|stary|zaloha|kopie)$/i',
];

/** Značky kopie, které nedávají smysl nikde. */
const ZAKAZANE_NAZVY_VZDY = [
    '/\.bak$/i',
    '/ \(\d+\)\./',
    '/^kopie /i',
];

/** Dokumenty, které musí mít každý projekt. */
const POVINNE_DOKUMENTY = ['docs/00-stav-projektu.md', 'docs/03-rozhodovaci-dennik.md'];

/**
 * Cesty, které skript čte z disku. Musí sedět na pushovaný commit, jinak se
 * kontroluje něco jiného, než co odejde. Poslední čtyři čte generátor bloku.
 */
const CTENE_CESTY = [
    'docs',
    '.readme-kontrola.json',
    'package.json',
    'app.json',
    'config.php',
    'composer.json',
];

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

// Cesta z Git Bashe (/c/laragon/...) je pro PHP na Windows neexistující
// složka. Kontrola se kvůli tomu dřív tiše přeskočila jako projekt bez docs/.
if (PHP_OS_FAMILY === 'Windows') {
    $koren = proGit($koren);
}

// Kontrola, která nemá co zkontrolovat, nesmí skončit úspěchem. Překlep
// v cestě dřív znamenal zelenou (audit 23. 9. 2026, N32).
if (!is_dir($koren)) {
    fwrite(STDERR, "\n  Cesta $koren neexistuje, kontrola dokumentace nemá co ověřit.\n\n");
    exit(1);
}

$nastaveni = [
    'docs-kontrola' => false,
    'docs-pomlcky' => 'blokovat',
    'docs-vymahat-aktualizaci' => true,
    'cesty-bez-kontroly' => [],
];
$cestaNastaveni = $koren . '/.readme-kontrola.json';
if (is_file($cestaNastaveni)) {
    // BOM na zacatku (bezny vystup Poznamkoveho bloku) by json_decode shodil
    // s hlaskou "Syntax error" bez napovedy, ze vinikem je neviditelny znak.
    $text = (string) file_get_contents($cestaNastaveni);
    if (str_starts_with($text, "\xEF\xBB\xBF")) {
        $text = substr($text, 3);
    }

    // Dekoduje se BEZ asociativniho rezimu. json_decode('{}', true) a
    // json_decode('[]', true) jsou v PHP totez prazdne pole, takze pojistka
    // proti "[]" odmitala i platny prazdny objekt "{}" (nalez 1e, kolo 3).
    // Objekt se tak pozna jako stdClass, seznam jako pole.
    $dekodovany = json_decode($text);
    $syrove = $dekodovany instanceof stdClass
        ? json_decode($text, true)
        : null;

    // Vadny JSON se NESMI prejit mlcky. Do 15. 9. 2026 se pri chybe jen
    // nechaly vychozi hodnoty, tedy docs-kontrola => false, a skript ohlasil
    // "neni zapnuta" s navratovym kodem 0. Jeden preklep nebo nedoresenej
    // merge konflikt tim vypnul celou branu a push presel.
    // is_array() je pravda i pro seznam, takze "[]" prvni verzi pojistky
    // proslo, array_merge nechal docs-kontrola na false a brana byla pryc
    // (nalez N18). Nastaveni musi byt objekt, tedy asociativni pole.
    if (!is_array($syrove)) {
        fwrite(STDERR, sprintf(
            "\n  %s neni platny JSON: %s\n"
            . "  -> Dokud se to neopravi, kontrola dokumentace nevi, co ma delat.\n\n",
            $cestaNastaveni,
            // json_decode("null") uspeje, takze json_last_error_msg() by rekl
            // "No error" a hlaska by matla. Vlastni popis je srozumitelnejsi.
            json_last_error() === JSON_ERROR_NONE
                ? 'ceka se objekt se nastavenim, prislo neco jineho'
                : json_last_error_msg()
        ));
        exit(1);
    }

    $nastaveni = array_merge($nastaveni, $syrove);
}

// Klic musi byt skutecne true, ne retezec "true" nebo cislo 1. Kdyby se
// takova hodnota brala jako vypnuto, tise by to obeslo celou kontrolu.
foreach (['docs-kontrola', 'docs-vymahat-aktualizaci'] as $klic) {
    if (array_key_exists($klic, $nastaveni) && !is_bool($nastaveni[$klic])) {
        fwrite(STDERR, sprintf(
            "\n  %s v %s neni true ani false, ale %s.\n"
            . "  -> Napis true nebo false bez uvozovek. Cokoli jineho by se tise\n"
            . "     vyhodnotilo jako vypnuto a kontrola by presla bez prace.\n\n",
            $klic,
            $cestaNastaveni,
            var_export($nastaveni[$klic], true)
        ));
        exit(1);
    }
}

// Kontrola se zapíná přihlášením, ne automaticky. Repozitář, který má docs/
// v jiném tvaru, tím nespadne dřív, než si ho srovná. Zapíná se řádkem
// "docs-kontrola": true v .readme-kontrola.json.
if (($nastaveni['docs-kontrola'] ?? false) !== true) {
    echo "  Kontrola dokumentace není pro tenhle repozitář zapnutá.\n";
    exit(0);
}

// Zapnutá kontrola bez složky docs/ je chyba, ne důvod se vzdát. Dřív se
// existence složky testovala dřív než zapnutí, takže projekt, který si
// kontrolu zapnul a docs/ pak smazal nebo přejmenoval, procházel zeleně
// (audit 23. 9. 2026, N32).
$slozka = $koren . '/docs';
if (!is_dir($slozka)) {
    fwrite(STDERR, "\n  Kontrola dokumentace je zapnutá, ale složka docs/ chybí.\n"
        . "  Založ docs/00-stav-projektu.md a docs/03-rozhodovaci-dennik.md, nebo kontrolu vypni.\n\n");
    exit(1);
}

$chyby = [];
$varovani = [];

// ---------------------------------------------------------------------------
// 0. Kontroluje se to, co se pushuje, ne to, co leží na disku
// ---------------------------------------------------------------------------
//
// Obsah dokumentů i generovaný blok se čtou z disku. Když se zná pushovaný
// commit a pracovní strom se od něj v kontrolovaných cestách liší, kontroluje
// se něco jiného, než co odejde. Stačí běžná situace, rozdělaná práce nebo
// ruční oprava bez git add, a kontrola pustí commit se zakázaným znakem,
// nebo naopak zastaví čistý (nález 1b, kolo 3).
//
// Na GitHubu je checkout vždy přesně ten commit, takže tam tohle nikdy
// nevystřelí. Týká se lokálního hooku.

if ($cil !== null) {
    $rozdil = pracovniStromSeLisi($koren, $cil, CTENE_CESTY);
    if ($rozdil === null) {
        $chyby[] = sprintf('commit "%s" v repozitáři není, nejde ověřit, co se kontroluje', $cil);
    } elseif ($rozdil !== []) {
        fwrite(STDERR, sprintf(
            "\n  Pracovní strom se liší od kontrolovaného commitu %s v:\n\n%s\n\n"
            . "  Kontrola čte soubory z disku, takže by ověřila něco jiného, než co\n"
            . "  se pushuje. Commitni nebo odlož změny (git stash -u) a zkus znovu.\n\n",
            substr($cil, 0, 7),
            implode("\n", array_map(static fn (string $s): string => '    ' . $s, $rozdil))
        ));
        exit(1);
    }
}

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
    if (jeNovaVetev($zaklad)) {
        // Volající má předat základ (workflow i hook ho u nové větve dopočítají
        // ze společného předka s main). Když ho nemá, řekne se to nahlas.
        echo "  pozn.: nová větev bez základu, pravidlo o dávce se neuplatnilo;"
            . " ověřil se jen obsah dokumentů.\n";
    } else {
        $rozsah = overRozsah($koren, $zaklad, $cil);

        // Neznámý základ znamená mělký klon nebo zastaralý origin. Dřív to bylo
        // jen upozornění a kontrola prošla, aniž dávku posoudila (nález N12,
        // audit 23. 9. 2026). Kontrola, která nemohla proběhnout, neprojde.
        if ($rozsah === null) {
            $chyby[] = sprintf(
                'základ rozsahu "%s" nebo cíl "%s" v repozitáři není; nejde ověřit,'
                . ' jestli dávka sáhla na dokumentaci (mělký klon nebo zastaralý origin?)',
                $zaklad,
                $cil
            );
        } else {
            $zmeny = zmeneneSoubory($koren, $rozsah);
            $kod = array_filter(array_keys($zmeny), static fn (string $s): bool => jeKod($s));
            $dokumenty = array_filter(
                $zmeny,
                static fn (string $stav, string $cesta): bool => jeZmenenyDokument($cesta, $stav),
                ARRAY_FILTER_USE_BOTH
            );

            if ($kod !== [] && $dokumenty === []) {
                $chyby[] = sprintf(
                    'dávka mění %d souborů s kódem, ale žádný dokument v docs/ nepřibyl'
                    . ' ani se nezměnil (obrázky, přílohy a mazání se nepočítají).'
                    . ' Zapiš, co se změnilo, do docs/00-stav-projektu.md,'
                    . ' a proč, do docs/03-rozhodovaci-dennik.md',
                    count($kod)
                );
            }
        }
    }
}

// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// 4. Uspořádání: kořen, agentský soubor, názvy
// ---------------------------------------------------------------------------
//
// Repozitáře se v tomhle rozcházely: jeden měl v kořeni PROJECT.md a DEPLOY.md,
// jiný dvě verze téhož textu (jednu s příponou FINAL), další neměl pro agenty
// nic. Pravidla a důvody jsou v deníku nastroje pod N22.

$vyjimky = (array) ($nastaveni['struktura-vyjimky'] ?? []);

foreach (souboryVGitu($koren) as $cesta) {
    if (in_array($cesta, $vyjimky, true)) {
        continue;
    }
    $nazev = basename($cesta);
    $vKoreni = !str_contains($cesta, '/');

    if ($vKoreni && str_ends_with(strtolower($nazev), '.md')
        && !in_array($nazev, SOUBORY_V_KORENI, true)) {
        $chyby[] = sprintf(
            '%s patří do docs/; v kořeni smí být jen %s',
            $cesta,
            implode(', ', SOUBORY_V_KORENI)
        );
    }

    // Značka se hledá na konci názvu bez přípony: "listing-FINAL.md" ano,
    // "demo-03-new-invoice-modal.png" ne, tam je "new" uprostřed věty.
    $zaklad = pathinfo($nazev, PATHINFO_FILENAME);
    $jeDokument = str_ends_with(strtolower($nazev), '.md') || str_starts_with($cesta, 'docs/');
    $vzory = $jeDokument
        ? array_merge(ZAKAZANE_NAZVY_DOKUMENTU, ZAKAZANE_NAZVY_VZDY)
        : ZAKAZANE_NAZVY_VZDY;

    foreach ($vzory as $vzor) {
        $proti = str_starts_with($vzor, '/-') ? $zaklad : $nazev;
        if (preg_match($vzor, $proti) === 1) {
            $chyby[] = sprintf(
                '%s má v názvu značku dočasnosti; ať je jasné, co platí, zůstane jeden soubor',
                $cesta
            );
            break;
        }
    }

    // .htaccess v docs/ je zábrana serveru, ne data; na hostingu s nginxem je
    // to jediné, co statický soubor zakáže (F21 v onlinefakturuj).
    if (str_starts_with($cesta, 'docs/') && !str_ends_with(strtolower($nazev), '.md')
        && $nazev !== '.htaccess') {
        $zbytek = substr($cesta, strlen('docs/'));
        $podslozka = str_contains($zbytek, '/') ? explode('/', $zbytek)[0] : '';
        if (!in_array($podslozka, PODSLOZKY_DOCS, true)) {
            $chyby[] = sprintf(
                '%s není dokument; data patří mimo docs/, nebo do docs/prilohy/',
                $cesta
            );
        }
    }
}

foreach (POVINNE_DOKUMENTY as $povinny) {
    if (!is_file($koren . '/' . $povinny)) {
        $chyby[] = sprintf('chybí %s, ten má mít každý projekt', $povinny);
    }
}

// Složka .claude: sdílené patří do gitu, osobní ne.
foreach (souboryVGitu($koren) as $cesta) {
    if ($cesta === '.claude/settings.local.json') {
        $chyby[] = '.claude/settings.local.json je v gitu; osobní nastavení patří do .gitignore';
    }
    if (str_starts_with($cesta, '.claude/') && str_ends_with($cesta, '.json')
        && is_file($koren . '/' . $cesta)
        && json_decode((string) file_get_contents($koren . '/' . $cesta)) === null) {
        $chyby[] = sprintf('%s není platný JSON, Claude Code ho přeskočí bez hlášky', $cesta);
    }
}

// Agentský soubor: jeden text, dvě jména. Claude Code čte CLAUDE.md, ostatní
// nástroje AGENTS.md; ukazatel drží obojí v jednom souboru, takže se nemůžou
// rozejít.
if (!is_file($koren . '/AGENTS.md')) {
    $chyby[] = 'chybí AGENTS.md v kořeni; pravidla pro agenty patří do něj (vzor: sablony/agents.md v nastroje)';
}
$cestaClaude = $koren . '/CLAUDE.md';
if (is_file($cestaClaude) && trim((string) file_get_contents($cestaClaude)) !== '@AGENTS.md') {
    $chyby[] = 'CLAUDE.md má mít jediný řádek @AGENTS.md, jinak se text rozejde s AGENTS.md';
}

// ---------------------------------------------------------------------------
// Výsledek
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

/** Soubory sledované gitem, cesty relativní ke kořeni repozitáře. */
function souboryVGitu(string $koren): array
{
    $vystup = [];
    $kod = 0;
    $ticho = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';
    exec(sprintf('git -C %s ls-files %s', escapeshellarg(proGit($koren)), $ticho), $vystup, $kod);

    if ($kod !== 0) {
        return [];
    }

    return array_values(array_filter(array_map('trim', $vystup)));
}

/** Srovná blok na porovnatelný tvar: bez bílých znaků na koncích řádků. */
function normalizuj(string $text): string
{
    $radky = array_map('rtrim', explode("\n", str_replace("\r\n", "\n", $text)));

    return implode("\n", $radky);
}

function jeKod(string $cesta): bool
{
    foreach (NENI_KOD as $vyjimka) {
        // Predpona jen u slozky (konci lomitkem). U souboru presna shoda:
        // str_starts_with pro vsechno vyradilo i LICENSE.php nebo
        // README.md-old.js, takze skutecny kod prosel bez zasahu do docs
        // (nalez 1c, kolo 3). kontrola-readme.php to ma spravne od zacatku.
        $jeSlozka = str_ends_with($vyjimka, '/');
        if ($jeSlozka ? str_starts_with($cesta, $vyjimka) : $cesta === $vyjimka) {
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

/**
 * Změněné soubory v rozsahu jako cesta => stav z git diff --name-status
 * (A, M, D, R100, R087...). U přejmenování dostane starý název stav D.
 *
 * @return array<string, string>
 */
function zmeneneSoubory(string $koren, string $rozsah): array
{
    $vystup = [];
    $kod = 0;
    exec(sprintf(
        'git -C %s diff --name-status %s %s',
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

    $soubory = [];
    foreach ($vystup as $radek) {
        $casti = explode("\t", trim($radek));
        if (count($casti) < 2 || $casti[0] === '') {
            continue;
        }
        $stav = $casti[0];
        if (($stav[0] === 'R' || $stav[0] === 'C') && count($casti) >= 3) {
            if ($stav[0] === 'R') {
                $soubory[$casti[1]] = 'D';
            }
            $soubory[$casti[2]] = $stav;
            continue;
        }
        $soubory[$casti[1]] = $stav;
    }

    return $soubory;
}

/**
 * Změnil se v dávce dokument? Počítá se jen přidaný nebo upravený soubor .md
 * v docs/ mimo snímky a přílohy. Obrázek, příloha ani smazání nejsou zápis
 * toho, co se změnilo: dřív stačilo do docs/ přidat nesouvisející obrázek
 * (audit 23. 9. 2026, N32). Přejmenování se počítá, jen když se změnil i obsah.
 *
 * Jestli text ke změně opravdu sedí, žádná kontrola cestou souboru nepozná.
 * Tohle zavírá jen levná obejití.
 */
function jeZmenenyDokument(string $cesta, string $stav): bool
{
    if (!str_starts_with($cesta, 'docs/') || !str_ends_with(strtolower($cesta), '.md')) {
        return false;
    }
    if (str_starts_with($cesta, 'docs/snimky/') || str_starts_with($cesta, 'docs/prilohy/')) {
        return false;
    }
    if ($stav === 'D') {
        return false;
    }
    if ($stav[0] === 'R') {
        return (int) substr($stav, 1) < 100;
    }

    return true;
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

/** Je to zaklad, ktery znamena "nova vetev, neni proti cemu merit"? */
function jeNovaVetev(string $zaklad): bool
{
    return $zaklad === '' || $zaklad === str_repeat('0', 40);
}

/**
 * Seznam cest, ve kterých se pracovní strom liší od commitu, včetně souborů,
 * které git nesleduje. Prázdné pole znamená shodu, null že commit neexistuje.
 *
 * @param string[] $cesty
 * @return string[]|null
 */
function pracovniStromSeLisi(string $koren, string $commit, array $cesty): ?array
{
    $ticho = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';
    $repo = escapeshellarg(proGit($koren));
    $argumenty = implode(' ', array_map('escapeshellarg', $cesty));

    $kod = 0;
    $vystup = [];
    exec(sprintf('git -C %s cat-file -e %s %s', $repo, escapeshellarg($commit . '^{commit}'), $ticho), $vystup, $kod);
    if ($kod !== 0) {
        return null;
    }

    $zmenene = [];
    exec(sprintf('git -C %s diff --name-only %s -- %s %s', $repo, escapeshellarg($commit), $argumenty, $ticho), $zmenene, $kod);
    if ($kod !== 0) {
        throw new RuntimeException('git diff proti commitu ' . $commit . ' selhal (kód ' . $kod . ')');
    }

    $nesledovane = [];
    exec(sprintf('git -C %s ls-files --others --exclude-standard -- %s %s', $repo, $argumenty, $ticho), $nesledovane, $kod);
    if ($kod !== 0) {
        throw new RuntimeException('git ls-files selhal (kód ' . $kod . ')');
    }

    $vse = array_merge(
        array_map(static fn (string $s): string => trim($s), $zmenene),
        array_map(static fn (string $s): string => trim($s) . '  (nesledovaný)', $nesledovane)
    );

    return array_values(array_filter($vse, static fn (string $s): bool => $s !== ''));
}
