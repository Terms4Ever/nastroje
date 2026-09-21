<?php
/**
 * Kontrola README proti společné kostře.
 *
 * Pouští se stejně z pre-push hooku i z GitHub Actions:
 *
 *     php kontrola-readme.php [cesta-k-repozitari]
 *
 * Návratový kód 0 = v pořádku, 1 = nálezy. Nálezy se vypisují česky
 * i s číslem řádku, aby šlo jít rovnou na místo.
 *
 * Nastavení si každý projekt drží v .readme-kontrola.json ve svém kořeni:
 *
 *     {
 *       "profil": "plny",
 *       "cesty-bez-kontroly": ["config.local.php", "vendor/"]
 *     }
 *
 * Profil rozhoduje o povinných nadpisech. Kontroly pravdivosti (existence
 * cest a odkazů) běží ve všech profilech stejně — to je to, co README drží
 * při zemi, když se kód posune a text ne.
 */
declare(strict_types=1);

const VERZE = '2.1.0';

/**
 * Povinné nadpisy podle profilu, v pořadí, v jakém musí v souboru stát.
 *
 * Profil je jen jeden. Zkrácená varianta existovala do 15. 9. 2026, ale
 * dělila projekty na dvě třídy bez užitku: i drobná aplikace umí říct,
 * co dělá. Kdo má málo funkcí, napíše krátkou sekci.
 */
const PROFILY = [
    'plny' => [
        'nadpisy' => [
            '## ✨ Hlavní funkce',
            '## 🛠️ Tech Stack',
            '## 📁 Struktura projektu',
            '## 🚀 Instalace (lokální vývoj)',
            '## 📦 Nasazení',
            '## 📄 Licence',
        ],
        'hlavicka' => true,
    ],
];

/** Nadpis sekce, která vypisuje obsah složky docs/. */
const NADPIS_DOKUMENTACE = '## 📚 Dokumentace';

/** Cesty, které v repozitáři nikdy nejsou a přesto se o nich píše. */
const VZDY_BEZ_KONTROLY = [
    'node_modules/',
    'vendor/',
];

final class Nalez
{
    public function __construct(
        public readonly int $radek,
        public readonly string $text,
    ) {
    }
}

/** @var Nalez[] $nalezy */
$nalezy = [];

$korenRepozitare = rtrim($argv[1] ?? getcwd(), "/\\");

// ---------------------------------------------------------------------------
// Načtení nastavení a README
// ---------------------------------------------------------------------------

$cestaNastaveni = $korenRepozitare . '/.readme-kontrola.json';
$nastaveni = ['profil' => 'plny', 'cesty-bez-kontroly' => []];

if (is_file($cestaNastaveni)) {
    $syrove = json_decode((string) file_get_contents($cestaNastaveni), true);
    if (!is_array($syrove)) {
        fwrite(STDERR, "  .readme-kontrola.json není platný JSON: " . json_last_error_msg() . "\n");
        exit(1);
    }
    $nastaveni = array_merge($nastaveni, $syrove);
}

$profil = (string) $nastaveni['profil'];
if (!isset(PROFILY[$profil])) {
    fwrite(STDERR, sprintf(
        "  Neznámý profil \"%s\". Povolené: %s\n",
        $profil,
        implode(', ', array_keys(PROFILY))
    ));
    exit(1);
}

$cestaReadme = $korenRepozitare . '/README.md';
if (!is_file($cestaReadme)) {
    fwrite(STDERR, "\n  README.md chybí. Vzor je v Terms4Ever/nastroje/sablony/.\n\n");
    exit(1);
}

$obsah = (string) file_get_contents($cestaReadme);
$radky = explode("\n", str_replace("\r\n", "\n", $obsah));

$bezKontroly = array_merge(
    VZDY_BEZ_KONTROLY,
    array_map('strval', (array) ($nastaveni['cesty-bez-kontroly'] ?? []))
);

// ---------------------------------------------------------------------------
// 1. Hlavička: název s emoji, tučný popis, odstavec, odznak, oddělovač
// ---------------------------------------------------------------------------

if (PROFILY[$profil]['hlavicka']) {
    $prvni = trim($radky[0] ?? '');

    if (!str_starts_with($prvni, '# ')) {
        $nalezy[] = new Nalez(1, 'první řádek musí být název: "# 🧾 Název projektu"');
    } elseif (!preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}]/u', $prvni)) {
        // Na umístění emoji netrváme, jen na tom, že tam nějaké je.
        $nalezy[] = new Nalez(1, 'název nemá emoji: "' . $prvni . '"');
    }

    /*
     * Třicet řádků, ne dvacet. Igris má oddělovač na sedmnáctém řádku, takže
     * pár odznaků navíc ho posunulo za hranici a kontrola hlásila, že chybí,
     * ačkoli v souboru byl (nález 15. 9. 2026). Hlavička delší než třicet
     * řádků už není hlavička.
     */
    $hlavicka = implode("\n", array_slice($radky, 0, 30));

    if (!preg_match('/^\*\*[^*]+\*\*$/m', $hlavicka)) {
        $nalezy[] = new Nalez(2, 'chybí tučný jednořádkový popis pod názvem');
    }

    if (!str_contains($hlavicka, '![')) {
        $nalezy[] = new Nalez(3, 'chybí odznaky (badges) s technologiemi a licencí');
    }

    if (!preg_match('/^---$/m', $hlavicka)) {
        $nalezy[] = new Nalez(4, 'chybí oddělovač "---" pod hlavičkou');
    }

    // Kde projekt běží, se píše v hlavičce, ne že si to každý najde jinde.
    // Adresa se bere z .readme-kontrola.json ("provoz"), aby se nehádalo,
    // jestli repozitář vůbec někde běží: mobilní appka nebo sada skriptů ne.
    $provoz = $nastaveni['provoz'] ?? null;
    if (is_string($provoz) && $provoz !== '' && !str_contains($hlavicka, $provoz)) {
        $nalezy[] = new Nalez(4, sprintf(
            'hlavička neuvádí, kde projekt běží; doplň řádek 🌐 **Provoz:** [%s](%s)',
            preg_replace('#^https?://#', '', $provoz),
            $provoz
        ));
    }
}

// ---------------------------------------------------------------------------
// 2. Povinné nadpisy: všechny přítomné, ve správném pořadí, právě jednou
// ---------------------------------------------------------------------------

/** @var array<string,int[]> $poziceNadpisu  nadpis => čísla řádků */
$poziceNadpisu = [];
$vsechnyNadpisy = [];

foreach ($radky as $i => $radek) {
    if (str_starts_with($radek, '## ')) {
        $n = rtrim($radek);
        $poziceNadpisu[$n][] = $i + 1;
        $vsechnyNadpisy[] = ['nadpis' => $n, 'radek' => $i + 1];
    }
}

$predchoziRadek = 0;
$predchoziNadpis = '';

/** Nadpisy, o kterých už padlo "má se jmenovat jinak" – ať se nehlásí dvakrát. */
$navrzenaPrejmenovani = [];

foreach (PROFILY[$profil]['nadpisy'] as $povinny) {
    if (!isset($poziceNadpisu[$povinny])) {
        $podobny = najdiPodobny($povinny, array_keys($poziceNadpisu));
        if ($podobny !== null) {
            $navrzenaPrejmenovani[] = $podobny;
        }
        $nalezy[] = new Nalez(
            0,
            $podobny === null
                ? sprintf('chybí sekce "%s"', $povinny)
                : sprintf('sekce "%s" se má jmenovat "%s"', $podobny, $povinny)
        );
        continue;
    }

    if (count($poziceNadpisu[$povinny]) > 1) {
        $nalezy[] = new Nalez(
            $poziceNadpisu[$povinny][1],
            sprintf('sekce "%s" je v souboru dvakrát', $povinny)
        );
    }

    $radek = $poziceNadpisu[$povinny][0];
    if ($radek < $predchoziRadek) {
        $nalezy[] = new Nalez(
            $radek,
            sprintf('sekce "%s" má stát až za "%s"', $povinny, $predchoziNadpis)
        );
    }
    $predchoziRadek = $radek;
    $predchoziNadpis = $povinny;
}

// Nepovinné sekce smí být jakékoli, ale v profilech se společnou kostrou
// musí mít emoji — jinak se seznam sekcí opticky rozpadne.
if (PROFILY[$profil]['hlavicka']) {
    foreach ($vsechnyNadpisy as $z) {
        if (in_array($z['nadpis'], PROFILY[$profil]['nadpisy'], true)) {
            continue;
        }
        if (in_array($z['nadpis'], $navrzenaPrejmenovani, true)) {
            continue;   // už zaznělo, že se má přejmenovat
        }
        if (preg_match('/^## [\p{L}\p{N}]/u', $z['nadpis'])) {
            $nalezy[] = new Nalez(
                $z['radek'],
                sprintf('nepovinná sekce "%s" nemá emoji', trim(substr($z['nadpis'], 3)))
            );
        }
    }
}

// ---------------------------------------------------------------------------
// 3. Jen krátké pomlčky
// ---------------------------------------------------------------------------
//
// Dlouhá pomlčka a polovičná pomlčka se v českém textu pletou s krátkou,
// v terminálu a v jednoduchých fontech vypadají jako chyba a při kopírování
// do příkazové řádky rozbijí příkaz. Igris je zakazuje od R177, teď to
// platí všude.

foreach ($radky as $i => $radek) {
    $cistyRadek = platnyRadek($radek);
    if ($cistyRadek === null) {
        $nalezy[] = new Nalez($i + 1, 'neplatne UTF-8, radek nejde zkontrolovat');
        continue;
    }
    if (preg_match_all('/[\x{2013}\x{2014}]/u', bezKodu($cistyRadek), $shodyPomlcek, PREG_OFFSET_CAPTURE)) {
        $nalezy[] = new Nalez(
            $i + 1,
            sprintf(
                'dlouhá pomlčka (%dx), použij krátkou "-": %s',
                count($shodyPomlcek[0]),
                mb_strimwidth(trim($radek), 0, 60, '…')
            )
        );
    }
}

// ---------------------------------------------------------------------------
// 4. Dokumentace ve složce docs/ musí být z README dohledatelná
// ---------------------------------------------------------------------------
//
// Co v docs/ leží, si řídí každý projekt sám. Povinné je jen to, aby se
// k tomu dalo dostat: README vypisuje každý dokument s vlastním popisem.
// Bez toho agent i člověk hledají naslepo a píšou znovu, co už je napsané.

$slozkaDocs = $korenRepozitare . '/docs';

if (is_dir($slozkaDocs)) {
    $dokumenty = array_values(array_filter(
        scandir($slozkaDocs) ?: [],
        // Dokument je .md. Soubor jako docs/.htaccess je zábrana serveru,
        // ne text ke čtení, a do tabulky dokumentů nepatří.
        static fn (string $s): bool => is_file($slozkaDocs . '/' . $s)
            && strtolower(pathinfo($s, PATHINFO_EXTENSION)) === 'md'
    ));

    if ($dokumenty !== [] && !isset($poziceNadpisu[NADPIS_DOKUMENTACE])) {
        $nalezy[] = new Nalez(
            0,
            sprintf(
                'složka docs/ má %d dokumentů, ale README nemá sekci "%s"',
                count($dokumenty),
                NADPIS_DOKUMENTACE
            )
        );
    } else {
        foreach ($dokumenty as $dokument) {
            $hledany = 'docs/' . $dokument;
            $nalezen = false;

            foreach ($radky as $radek) {
                // Jen řádek tabulky "| `docs/...` | k čemu je |".
                //
                // Volnější pravidlo (stačí zmínka a deset znaků za ní) se
                // neosvědčilo: smazaný řádek tabulky prošel, protože se
                // chytila náhodná zmínka téhož souboru jinde v textu.
                if (!preg_match('/^\s*\|([^|]*)\|(.*)\|\s*$/u', $radek, $bunky)) {
                    continue;
                }
                if (!str_contains($bunky[1], $hledany)) {
                    continue;
                }
                if (mb_strlen(trim($bunky[2])) >= 10) {
                    $nalezen = true;
                    break;
                }
            }

            if (!$nalezen) {
                $nalezy[] = new Nalez(
                    0,
                    sprintf(
                        'dokument "%s" chybí v tabulce sekce Dokumentace (| `%s` | k čemu je |)',
                        $hledany,
                        $hledany
                    )
                );
            }
        }
    }
}

// ---------------------------------------------------------------------------
// 5. Pravdivost: cesty a odkazy, o kterých README mluví, musí existovat
// ---------------------------------------------------------------------------

$kotvy = [];
foreach ($radky as $radek) {
    if (preg_match('/^#{1,6} +(.*)$/u', $radek, $shodaNadpisu)) {
        $kotvy[] = naKotvu($shodaNadpisu[1]);
    }
}

$vPlotu = false;
$kontrolovatBlok = false;

foreach ($radky as $i => $radek) {
    $cislo = $i + 1;

    // Bloky kódu se kontrolují taky, jen přísněji. Právě v nich bydlí
    // instalační návod, a README, které popisuje neexistující soubor,
    // je horší než README žádné.
    if (preg_match('/^\s*```(\w*)/', $radek, $shodaPlotu)) {
        if ($vPlotu) {
            $vPlotu = false;
            $kontrolovatBlok = false;
        } else {
            $vPlotu = true;
            // Kontrolují se jen bloky s příkazy a stromy složek. Ukázka
            // konfigurace v JSON nebo YAML mluví o hodnotách, ne o tom,
            // co v repozitáři leží.
            $jazyk = strtolower($shodaPlotu[1]);
            $kontrolovatBlok = in_array($jazyk, ['', 'bash', 'sh', 'shell', 'console', 'zsh', 'text'], true);
        }
        continue;
    }

    if ($vPlotu && !$kontrolovatBlok) {
        continue;   // uvnitř ukázky konfigurace se nekontroluje nic
    }

    if ($vPlotu) {
        foreach (preg_split('/\s+/', trim($radek)) ?: [] as $slovo) {
            $slovo = trim($slovo, "\"'`,;()<>");
            // Uvnitř kódu vyžadujeme lomítko i příponu, jinak by se chytaly
            // přepínače, názvy tabulek a kusy příkazů.
            if (!str_contains($slovo, '/') || !preg_match('/\.[a-z0-9]{1,5}$/i', $slovo)) {
                continue;
            }
            if (!vypadaJakoCesta($slovo)) {
                continue;
            }
            if (existujeCesta($korenRepozitare, $slovo, $bezKontroly)) {
                continue;
            }
            // Stromy složek se píšou se zanořením: pod "assets/" stojí
            // "js/app.js", což je ve skutečnosti "assets/js/app.js".
            // Řádek sám o sobě nic o rodiči neví, tak uznáme i cestu,
            // která v repozitáři existuje jako zakončení delší cesty.
            if (konciNejakaCesta($korenRepozitare, $slovo)) {
                continue;
            }
            $nalezy[] = new Nalez($cislo, sprintf('příkaz zmiňuje "%s", ten v repozitáři není', $slovo));
        }
        continue;
    }

    // Odkazy [text](cíl)
    if (preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/', $radek, $shodyOdkazu)) {
        foreach ($shodyOdkazu[1] as $cil) {
            if (preg_match('#^(https?:|mailto:|tel:)#i', $cil)) {
                continue;
            }
            if (str_starts_with($cil, '#')) {
                if (!in_array(ltrim($cil, '#'), $kotvy, true)) {
                    $nalezy[] = new Nalez($cislo, sprintf('odkaz "%s" nevede na žádný nadpis', $cil));
                }
                continue;
            }
            $cesta = strtok($cil, '#');
            if (!existujeCesta($korenRepozitare, $cesta, $bezKontroly)) {
                $nalezy[] = new Nalez($cislo, sprintf('odkaz vede na "%s", ten soubor neexistuje', $cesta));
            }
        }
    }

    // Cesty v jednořádkovém kódu `neco/jineho.php`
    if (preg_match_all('/`([^`]+)`/u', $radek, $shodyKodu)) {
        foreach ($shodyKodu[1] as $kus) {
            if (!vypadaJakoCesta($kus)) {
                continue;
            }
            if (!existujeCesta($korenRepozitare, $kus, $bezKontroly)) {
                $nalezy[] = new Nalez($cislo, sprintf('README zmiňuje "%s", ten v repozitáři není', $kus));
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Výpis
// ---------------------------------------------------------------------------

$jmenoRepozitare = basename(realpath($korenRepozitare) ?: $korenRepozitare);

if ($nalezy === []) {
    printf("  README %s je v pořádku (profil %s, kontrola %s).\n", $jmenoRepozitare, $profil, VERZE);
    exit(0);
}

printf("\n  README %s neprošel (profil %s):\n\n", $jmenoRepozitare, $profil);
foreach ($nalezy as $n) {
    if ($n->radek > 0) {
        printf("    README.md:%d  %s\n", $n->radek, $n->text);
    } else {
        printf("    %s\n", $n->text);
    }
}
printf("\n  Vzory: https://github.com/Terms4Ever/nastroje/tree/main/sablony\n");
printf("  Vědomá výjimka při pushi: git push --no-verify\n\n");

exit(1);

// ---------------------------------------------------------------------------
// Pomocné funkce
// ---------------------------------------------------------------------------

/**
 * Pozná, jestli kus textu v obráceném apostrofu je cesta v repozitáři.
 * Schválně opatrná: radši cestu přeskočí, než aby hlásila planý poplach.
 */
function vypadaJakoCesta(string $kus): bool
{
    $kus = trim($kus);

    if ($kus === '' || str_contains($kus, ' ')) {
        return false;   // příkazy, věty
    }
    if (preg_match('/^[$\-<#]/', $kus)) {
        return false;   // proměnné, přepínače, kotvy
    }
    if (str_starts_with($kus, '/')) {
        return false;   // adresa na webu (/sprava, /api/invoices), ne soubor
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $kus)) {
        return false;   // URL
    }
    if (preg_match('/[?=@]/', $kus)) {
        return false;   // dotazy v adrese a e-maily
    }
    if (str_contains($kus, '*') || str_contains($kus, '…')) {
        return false;   // vzory a výpustky
    }
    if (preg_match('/^\d+(\.\d+)+$/', $kus)) {
        return false;   // IP adresy a čísla verzí
    }
    if (str_starts_with($kus, '.git/')) {
        return false;   // vnitřek gitu, v repozitáři není
    }

    if (str_contains($kus, '/')) {
        return true;
    }

    // Bez lomítka bereme jen známé přípony zdrojáků. Jinak by se chytaly
    // identifikátory jako cz.setly.app nebo zkratky typu .env.
    return preg_match('/(?<=.)\.(php|js|mjs|cjs|ts|tsx|jsx|json|sql|md|ya?ml|css|scss|html?|sh|lock|toml|xml|txt|svg|png|ico|webp)$/i', $kus) === 1;
}

/**
 * @param string[] $bezKontroly
 */
function existujeCesta(string $koren, string $cesta, array $bezKontroly): bool
{
    $cesta = trim($cesta);

    // Pozor: ltrim($cesta, './') by ořezalo i tečku u .github/, a ta je
    // součástí názvu. Odebíráme jen skutečnou předponu "./".
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
 * Slug nadpisu pro kotvu, po vzoru GitHubu: malá písmena, mezery na pomlčky,
 * interpunkce pryč. Diakritika zůstává, GitHub ji v kotvách zachovává.
 */
function naKotvu(string $nadpis): string
{
    $s = mb_strtolower(trim($nadpis));
    $s = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $s) ?? $s;
    $s = preg_replace('/\s+/u', '-', trim($s)) ?? $s;

    return $s;
}

/**
 * Najde nadpis, který je nejspíš přejmenovanou verzí hledaného — aby hláška
 * uměla říct "sekce Stack se má jmenovat Tech Stack" místo holého "chybí".
 *
 * @param string[] $kandidati
 */
function najdiPodobny(string $hledany, array $kandidati): ?string
{
    $cil = klicNadpisu($hledany);
    if ($cil === '') {
        return null;
    }

    $nejlepsi = null;
    $nejvyssi = 0.0;

    foreach ($kandidati as $k) {
        $porovnavany = klicNadpisu($k);
        if ($porovnavany === '') {
            continue;
        }

        similar_text($cil, $porovnavany, $shoda);
        if ($shoda > $nejvyssi) {
            $nejvyssi = $shoda;
            $nejlepsi = $k;
        }
    }

    return $nejvyssi >= 45.0 ? $nejlepsi : null;
}

/** Nadpis bez "## ", bez emoji a bez diakritiky, pro hrubé porovnání. */
function klicNadpisu(string $nadpis): string
{
    $s = preg_replace('/^#+\s*/u', '', trim($nadpis)) ?? $nadpis;
    $s = preg_replace('/[^\p{L}\p{N}\s]/u', '', $s) ?? $s;

    return mb_strtolower(trim($s));
}

/**
 * Existuje v repozitáři soubor, jehož cesta končí zadaným kusem?
 *
 * Slouží jen pro stromy složek v blocích kódu, kde se zanoření píše
 * odsazením a samotný řádek o svém rodiči nic neví. Seznam souborů se
 * sestaví jednou a pak se drží.
 */
function konciNejakaCesta(string $koren, string $kus): bool
{
    static $seznam = null;

    $kus = trim($kus, '/');
    if ($kus === '') {
        return false;
    }

    if ($seznam === null) {
        $seznam = [];
        $prochazeni = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($koren, FilesystemIterator::SKIP_DOTS),
                static function (SplFileInfo $polozka): bool {
                    return !in_array($polozka->getFilename(), ['node_modules', 'vendor', '.git'], true);
                }
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $delkaKorene = strlen($koren) + 1;
        foreach ($prochazeni as $polozka) {
            $cesta = substr((string) $polozka->getPathname(), $delkaKorene);
            $seznam[] = str_replace(DIRECTORY_SEPARATOR, '/', $cesta);
        }
    }

    foreach ($seznam as $cesta) {
        if ($cesta === $kus || str_ends_with($cesta, '/' . $kus)) {
            return true;
        }
    }

    return false;
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
