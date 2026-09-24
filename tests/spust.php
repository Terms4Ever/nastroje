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

/** Nejmenší platné PNG: podpis a hlavička, odlišná podle posledního bajtu. */
const PNG_PRED = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x02\0\0\0\x01";
const PNG_PO = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x02\0\0\0\x02";

require_once NASTROJE . '/sablony/migrace.php';

$filtr = $argv[1] ?? null;
$docasne = [];
$databaze = [];
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

    // Počet dokumentů v hlášce: s rozsahem se dřív vypsal počet změněných
    // místo všech, protože pravidlo o dávce přepsalo proměnnou.
    return ocekavej(php('kontrola-dokumentace.php', $repo, $zaklad, git($repo, 'rev-parse', 'HEAD')), 0, '(2 dokumentů');
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

pripad('dokumentace: úprava AGENTS.md není změna kódu', function (): array {
    $repo = novyProjekt();
    $zaklad = git($repo, 'rev-parse', 'HEAD');
    pripis($repo, 'AGENTS.md', "\nDokumenty: stav a deník.\n");
    commit($repo, 'Pokyny pro agenty doplněné');

    return ocekavej(php('kontrola-dokumentace.php', $repo, $zaklad, git($repo, 'rev-parse', 'HEAD')), 0, 'v pořádku');
});

pripad('dokumentace: nasazení bez dokumentu o nasazení dostane doporučení', function (): array {
    $repo = novyProjekt([], ['.github/workflows/deploy.yml' => "name: Deploy\n"]);

    return ocekavej(php('kontrola-dokumentace.php', $repo), 0, 'docs/02-nasazeni.md');
});

pripad('dokumentace: s dokumentem o nasazení doporučení mlčí', function (): array {
    $repo = novyProjekt([], [
        '.github/workflows/deploy.yml' => "name: Deploy\n",
        'docs/02-nasazeni.md' => "# Nasazení\n\nWeb se nahrává po pushi do main.\n",
    ]);
    $vysledek = php('kontrola-dokumentace.php', $repo);
    $ok = $vysledek['kod'] === 0 && !str_contains($vysledek['vystup'], 'doporučení');

    return [$ok, $ok ? '' : "kód {$vysledek['kod']}\n{$vysledek['vystup']}"];
});

pripad('dokumentace: testy bez dokumentu o ověření dostanou doporučení', function (): array {
    $repo = novyProjekt([], ['tests/prvni.php' => "<?php\n"]);

    return ocekavej(php('kontrola-dokumentace.php', $repo), 0, 'docs/04-overeni.md');
});

pripad('dokumentace: právě založený dokument doporučení umlčí', function (): array {
    // Agent dokument podle doporučení založí a kontrolu pustí znovu ještě
    // před commitem. Dřív dostal tutéž radu, protože se hledalo jen v gitu.
    $repo = novyProjekt([], ['tests/prvni.php' => "<?php\n"]);
    zapis($repo, 'docs/04-overeni.md', "# Ověření\n\nTesty v tests/.\n");
    $vysledek = php('kontrola-dokumentace.php', $repo);
    $ok = $vysledek['kod'] === 0 && !str_contains($vysledek['vystup'], 'doporučení');

    return [$ok, $ok ? '' : "kód {$vysledek['kod']}\n{$vysledek['vystup']}"];
});

pripad('dokumentace: doporučení nenavrhne obsazené číslo dokumentu', function (): array {
    $repo = novyProjekt([], [
        'tests/prvni.php' => "<?php\n",
        'docs/04-koncept.md' => "# Koncept\n\nOtevřené otázky.\n",
    ]);

    return ocekavej(php('kontrola-dokumentace.php', $repo), 0, 'docs/05-overeni.md');
});

pripad('dokumentace: testovací skript v package.json dostane doporučení', function (): array {
    $repo = novyProjekt([], ['package.json' => "{\n  \"name\": \"x\",\n  \"scripts\": { \"test\": \"jest\" }\n}\n"]);

    return ocekavej(php('kontrola-dokumentace.php', $repo), 0, 'docs/04-overeni.md');
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

// --- spouštěč migrací (sablony/migrace.php) -------------------------------
//
// Potřebuje jednorázovou databázi: NASTROJE_TEST_MYSQL="dsn|uživatel|heslo".
// Každý případ si založí vlastní databázi a na konci ji smaže. Na GitHubu ji
// dává MySQL z běhového prostředí, doma dočasná instance MariaDB z Laragonu.

pripad('spouštěč: první běh migraci pustí, druhý už ne', function (): array {
    if (($pdo = testovaciDatabaze()) === null) {
        return bezDatabaze();
    }
    $slozka = docasna('migrace');
    file_put_contents("$slozka/2026-01-10-zaklad.sql", "-- Základ\nCREATE TABLE a (id INT);\n");
    $prvni = implode("\n", (new Migrace($pdo, $slozka))->spust());
    $druhy = implode("\n", (new Migrace($pdo, $slozka))->spust());

    $ok = str_contains($prvni, 'Spuštěno: 2026-01-10-zaklad.sql') && str_contains($druhy, 'databáze je aktuální');

    return [$ok, $ok ? '' : "první: $prvni\ndruhý: $druhy"];
});

pripad('spouštěč: změněná hotová migrace nasazení zastaví (A07)', function (): array {
    if (($pdo = testovaciDatabaze()) === null) {
        return bezDatabaze();
    }
    $slozka = docasna('migrace');
    file_put_contents("$slozka/2026-01-10-zaklad.sql", "-- Základ\nCREATE TABLE a (id INT);\n");
    (new Migrace($pdo, $slozka))->spust();
    file_put_contents("$slozka/2026-01-10-zaklad.sql", "-- Základ\nCREATE TABLE a (id INT, b INT);\n");
    try {
        $zprava = implode("\n", (new Migrace($pdo, $slozka))->spust());

        return [false, "nezastavila se: $zprava"];
    } catch (RuntimeException $e) {
        return [str_contains($e->getMessage(), 'otisk nesedí'), $e->getMessage()];
    }
});

pripad('spouštěč: přejmenovaná hotová migrace se znovu nepustí (A07)', function (): array {
    if (($pdo = testovaciDatabaze()) === null) {
        return bezDatabaze();
    }
    $slozka = docasna('migrace');
    file_put_contents("$slozka/2026-01-10-zaklad.sql", "-- Základ\nCREATE TABLE a (id INT);\n");
    (new Migrace($pdo, $slozka))->spust();
    rename("$slozka/2026-01-10-zaklad.sql", "$slozka/2026-01-10-zaklad-uctu.sql");
    try {
        $zprava = implode("\n", (new Migrace($pdo, $slozka))->spust());

        return [false, "spustila se znovu: $zprava"];
    } catch (RuntimeException $e) {
        return [str_contains($e->getMessage(), 'přejmenovaná'), $e->getMessage()];
    }
});

pripad('spouštěč: zamčené kopie jako na produkci nic nehlásí', function (): array {
    if (($pdo = testovaciDatabaze()) === null) {
        return bezDatabaze();
    }
    $slozka = docasna('migrace');
    file_put_contents("$slozka/2026-01-10-zaklad.sql.php", Migrace::ZAMEK . "\n-- Základ\nCREATE TABLE a (id INT);\n");
    file_put_contents("$slozka/2026-01-11-dalsi.sql.php", Migrace::ZAMEK . "\n-- Další\nCREATE TABLE b (id INT);\n");
    (new Migrace($pdo, $slozka))->spust();
    $zprava = implode("\n", (new Migrace($pdo, $slozka))->spust());

    return [str_contains($zprava, 'databáze je aktuální') && !str_contains($zprava, 'Pozor'), $zprava];
});

// --- snímky odkazem na commit (N33) --------------------------------------
//
// Odkaz na větev (blob/main) se rozbije, když se soubor přesune nebo
// přejmenuje, a neřekne, kterou verzi snímek dokládá. Nový obsah proto
// odkazuje na otisk commitu; starší issues jen upozorní.

$otiskPriklad = str_repeat('a1b2c3d4', 5);
$teloSeSnimkem = static fn (string $vetev): string => "## Problém\n\nTlačítko Uložit nic neudělá.\n\n"
    . "## Hotovo, když\n\n- [ ] tlačítko uloží\n\n## Snímky\n\n"
    . "![Tlačítko, před](https://github.com/Terms4Ever/x/blob/$vetev/docs/snimky/1-ulozit/pred-tlacitko.png?raw=1)\n";

pripad('snímky: odkaz na větev main v novém těle neprojde', function () use ($teloSeSnimkem): array {
    $soubor = docasna('telo') . '/telo.md';
    file_put_contents($soubor, $teloSeSnimkem('main'));

    return ocekavej(php('kontrola-tvaru-issue.php', $soubor), 1, 'otisk commitu');
});

pripad('snímky: odkaz na otisk commitu projde', function () use ($teloSeSnimkem, $otiskPriklad): array {
    $soubor = docasna('telo') . '/telo.md';
    file_put_contents($soubor, $teloSeSnimkem($otiskPriklad));

    return ocekavej(php('kontrola-tvaru-issue.php', $soubor), 0);
});

pripad('snímky: staré issue s odkazem na main projde', function () use ($teloSeSnimkem): array {
    $soubor = docasna('telo') . '/telo.md';
    file_put_contents($soubor, $teloSeSnimkem('main'));

    return ocekavej(php('kontrola-tvaru-issue.php', $soubor, '--vznik', '2026-09-20T10:00:00Z'), 0);
});

pripad('snímky: komentář s odkazem na main neprojde', function (): array {
    $soubor = docasna('komentar') . '/komentar.md';
    file_put_contents($soubor, "Opraveno v app/kod.php, ověřeno testem.\n\n"
        . "![Po](https://github.com/Terms4Ever/x/blob/main/docs/snimky/1-ulozit/po-tlacitko.png?raw=1)\n");

    return ocekavej(php('kontrola-tvaru-issue.php', $soubor, '--komentar'), 1, 'otisk commitu');
});

pripad('snímky: issue s platnými snímky v commitu projde', function (): array {
    [$repo, $otisk] = projektSeSnimky();
    $komentar = ['body' => "Opraveno, ověřeno.\n\n" . obrazek($otisk, 'pred') . "\n" . obrazek($otisk, 'po') . "\n",
        'createdAt' => DNES . 'T12:00:00Z'];

    return ocekavej(issuesSAtrapou('ok', [issue(['comments' => [$komentar]])], $repo), 0, 'v pořádku');
});

pripad('snímky: snímek, který v odkazovaném commitu není, neprojde', function (): array {
    [$repo, $otisk] = projektSeSnimky();
    $prvni = git($repo, 'rev-list', '--max-parents=0', 'HEAD');
    $komentar = ['body' => "Opraveno.\n\n" . obrazek($prvni, 'pred') . "\n" . obrazek($otisk, 'po') . "\n",
        'createdAt' => DNES . 'T12:00:00Z'];

    return ocekavej(issuesSAtrapou('ok', [issue(['comments' => [$komentar]])], $repo), 1, 'není');
});

pripad('snímky: soubor, který není obrázek, neprojde', function (): array {
    [$repo, $otisk] = projektSeSnimky(['po' => "tohle neni obrazek\n"]);
    $komentar = ['body' => "Opraveno.\n\n" . obrazek($otisk, 'pred') . "\n" . obrazek($otisk, 'po') . "\n",
        'createdAt' => DNES . 'T12:00:00Z'];

    return ocekavej(issuesSAtrapou('ok', [issue(['comments' => [$komentar]])], $repo), 1, 'není obrázek');
});

pripad('snímky: stejný soubor jako před i po neprojde', function (): array {
    [$repo, $otisk] = projektSeSnimky(['po' => PNG_PRED]);
    $komentar = ['body' => "Opraveno.\n\n" . obrazek($otisk, 'pred') . "\n" . obrazek($otisk, 'po') . "\n",
        'createdAt' => DNES . 'T12:00:00Z'];

    return ocekavej(issuesSAtrapou('ok', [issue(['comments' => [$komentar]])], $repo), 1, 'tentýž');
});

pripad('snímky: starý komentář s odkazem na main jen upozorní', function (): array {
    [$repo] = projektSeSnimky();
    $odkaz = '![Před](https://github.com/Terms4Ever/zkusebni/blob/main/docs/snimky/1-ulozit/pred-tlacitko.png?raw=1)';
    $komentar = ['body' => "Opraveno.\n\n$odkaz\n", 'createdAt' => '2026-09-20T12:00:00Z'];
    $stare = issue(['createdAt' => '2026-09-20T10:00:00Z', 'comments' => [$komentar]]);

    return ocekavej(issuesSAtrapou('ok', [$stare], $repo), 0, 'upozornění');
});

// --- zavření issue s důkazem (N33) ---------------------------------------
//
// gh nahrazuje atrapa v PHP: vrací issue, výchozí větev a běhy commitu podle
// scénáře a zapisuje, co se volalo. Nic se neposílá na GitHub.

$hotovyChecklist = "## Problém\n\nTlačítko Uložit nic neudělá.\n\n## Hotovo, když\n\n- [x] tlačítko uloží\n";

pripad('zavření: bez --zavrit jen posoudí a nic nezapíše', function () use ($hotovyChecklist): array {
    [$vysledek, $log] = zavreni(['body' => $hotovyChecklist]);
    $ok = $vysledek['kod'] === 0 && str_contains($vysledek['vystup'], 'lze zavřít') && !str_contains($log, 'issue close');

    return [$ok, $ok ? '' : "kód {$vysledek['kod']}\n{$vysledek['vystup']}\n$log"];
});

pripad('zavření: s --zavrit zapíše komentář a zavře', function () use ($hotovyChecklist): array {
    [$vysledek, $log] = zavreni(['body' => $hotovyChecklist], [], ['--zavrit']);
    $ok = $vysledek['kod'] === 0 && str_contains($log, 'issue comment') && str_contains($log, 'issue close');

    return [$ok, $ok ? '' : "kód {$vysledek['kod']}\n{$vysledek['vystup']}\n$log"];
});

pripad('zavření: neodškrtnutý bod neprojde', function (): array {
    [$vysledek] = zavreni([]);

    return ocekavej($vysledek, 1, 'neodškrtnutých');
});

pripad('zavření: bod schválně nezaškrtnutý s vysvětlením projde', function (): array {
    [$vysledek] = zavreni([], [], [], 'Bod o telefonu zůstává schválně nezaškrtnutý, ověřit jde jen na zařízení.');

    return ocekavej($vysledek, 0, 'lze zavřít');
});

pripad('zavření: červený běh commitu neprojde', function () use ($hotovyChecklist): array {
    [$vysledek] = zavreni(['body' => $hotovyChecklist], ['behy' => behy('completed', 'failure')]);

    return ocekavej($vysledek, 1, 'skončil failure');
});

pripad('zavření: běh, který ještě běží, neprojde', function () use ($hotovyChecklist): array {
    [$vysledek] = zavreni(['body' => $hotovyChecklist], ['behy' => behy('in_progress', null)]);

    return ocekavej($vysledek, 1, 'ještě běží');
});

pripad('zavření: commit bez jediného běhu neprojde', function () use ($hotovyChecklist): array {
    [$vysledek] = zavreni(['body' => $hotovyChecklist], ['behy' => ['total_count' => 0, 'check_runs' => []]]);

    return ocekavej($vysledek, 1, 'žádný běh');
});

pripad('zavření: komentář bez odkazu na commit neprojde', function () use ($hotovyChecklist): array {
    [$vysledek] = zavreni(['body' => $hotovyChecklist], [], [], null, 'Opraveno a ověřeno testem.');

    return ocekavej($vysledek, 1, 'odkazovat na ověřený commit');
});

pripad('zavření: bez závěrečného komentáře neprojde', function () use ($hotovyChecklist): array {
    [$vysledek] = zavreni(['body' => $hotovyChecklist], [], ['--bez-komentare']);

    return ocekavej($vysledek, 1, 'chybí závěrečný komentář');
});

pripad('zavření: snímek před bez snímku po neprojde', function () use ($hotovyChecklist): array {
    $repo = novyProjekt();
    git($repo, 'remote', 'add', 'origin', 'https://github.com/Terms4Ever/zkusebni.git');
    zapis($repo, 'docs/snimky/1-ulozit/pred-tlacitko.png', PNG_PRED);
    commit($repo, 'Snímek před');
    [$vysledek] = zavreni(['body' => $hotovyChecklist], [], [], null, null, $repo);

    return ocekavej($vysledek, 1, 'snímek po');
});

pripad('zavření: issue, které nejde načíst, neprojde', function () use ($hotovyChecklist): array {
    [$vysledek] = zavreni(['body' => $hotovyChecklist], ['selhat' => ['issue view']]);

    return ocekavej($vysledek, 1, 'nepodařilo načíst');
});

pripad('zavření: už zavřené issue se znovu nezavírá', function () use ($hotovyChecklist): array {
    [$vysledek] = zavreni(['body' => $hotovyChecklist, 'state' => 'CLOSED']);

    return ocekavej($vysledek, 1, 'už je zavřené');
});

pripad('zavření: commit mimo výchozí větev neprojde', function () use ($hotovyChecklist): array {
    [$vysledek] = zavreni(['body' => $hotovyChecklist], ['srovnani' => 'diverged'], ['--commit', str_repeat('b', 40)]);

    return ocekavej($vysledek, 1, 'není');
});

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
    ['holé zavření issue neprojde', 'gh issue ' . 'close 5 -R Terms4Ever/x', 2],
    ['zavření přes gh api neprojde', 'gh api -X PATCH repos/Terms4Ever/x/' . 'issues/5 -f state=closed', 2],
    ['zavření přes zavrit-issue.php projde', 'php C:/laragon/www/nastroje/zavrit-issue.php . 5 --komentar k.md --zavrit', 0],
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
require __DIR__ . '/sady.php';

// Výsledek
// ==========================================================================

foreach ($docasne as $slozka) {
    smaz($slozka);
}
foreach ($databaze as [$pdo, $nazev]) {
    $pdo->exec("DROP DATABASE IF EXISTS `$nazev`");
}

$chyb = count(array_filter($vysledky, static fn (array $v): bool => !$v[1]));
echo "\n";
foreach ($vysledky as [$nazev, $ok, $detail]) {
    printf("  %s  %s%s\n", $ok ? 'ok   ' : 'CHYBA', $nazev, $ok ? ($detail !== '' ? "  ($detail)" : '') : '');
    if (!$ok) {
        echo '         ' . str_replace("\n", "\n         ", rtrim($detail)) . "\n";
    }
}
$preskoceno = count(array_filter($vysledky, static fn (array $v): bool => str_starts_with($v[2], 'přeskočeno')));
printf("\n  %d případů, %d neprošlo, %d přeskočeno.\n\n", count($vysledky), $chyb, $preskoceno);
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

/** Vlastní jednorázová databáze pro případ, nebo null, když žádná není. */
function testovaciDatabaze(): ?PDO
{
    global $databaze;
    $nastaveni = (string) getenv('NASTROJE_TEST_MYSQL');
    if ($nastaveni === '') {
        return null;
    }
    [$dsn, $uzivatel, $heslo] = array_pad(explode('|', $nastaveni), 3, '');
    $pdo = new PDO($dsn, $uzivatel, $heslo, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $nazev = 'nastroje_test_' . bin2hex(random_bytes(4));
    $pdo->exec("CREATE DATABASE `$nazev` CHARACTER SET utf8mb4");
    $pdo->exec("USE `$nazev`");
    $databaze[] = [$pdo, $nazev];

    return $pdo;
}

/** Bez databáze se případ nepředstírá: je vidět, že neproběhl. */
function bezDatabaze(): array
{
    global $bezDatabaze;
    $bezDatabaze = true;

    return [true, 'přeskočeno, chybí jednorázová databáze (NASTROJE_TEST_MYSQL)'];
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

/**
 * Projekt se snímky k issue #1 v commitu. Vrací cestu a otisk commitu,
 * ve kterém snímky leží.
 *
 * @return array{0: string, 1: string}
 */
function projektSeSnimky(array $obsah = []): array
{
    $repo = novyProjekt();
    git($repo, 'remote', 'add', 'origin', 'https://github.com/Terms4Ever/zkusebni.git');
    zapis($repo, 'docs/snimky/1-ulozit/pred-tlacitko.png', $obsah['pred'] ?? PNG_PRED);
    zapis($repo, 'docs/snimky/1-ulozit/po-tlacitko.png', $obsah['po'] ?? PNG_PO);
    commit($repo, 'Snímky k issue 1');

    return [$repo, git($repo, 'rev-parse', 'HEAD')];
}

function obrazek(string $otisk, string $druh): string
{
    return sprintf(
        '![%s](https://github.com/Terms4Ever/zkusebni/blob/%s/docs/snimky/1-ulozit/%s-tlacitko.png?raw=1)',
        $druh === 'pred' ? 'Před' : 'Po',
        $otisk,
        $druh
    );
}

/** Běhy commitu v odpovědi GitHubu: kontrola a nasazení. */
function behy(string $stav, ?string $zaver): array
{
    return ['total_count' => 2, 'check_runs' => [
        ['name' => 'readme / kontrola', 'status' => 'completed', 'conclusion' => 'success'],
        ['name' => 'FTP Deploy', 'status' => $stav, 'conclusion' => $zaver],
    ]];
}

/**
 * Pustí zavrit-issue.php proti atrapě gh. Vrací výsledek a záznam volání gh.
 *
 * @return array{0: array{kod: int, vystup: string}, 1: string}
 */
function zavreni(
    array $zmenyIssue,
    array $zmenyScenare = [],
    array $prepinace = [],
    ?string $dodatek = null,
    ?string $komentarText = null,
    ?string $repo = null
): array {
    if ($repo === null) {
        $repo = novyProjekt();
        git($repo, 'remote', 'add', 'origin', 'https://github.com/Terms4Ever/zkusebni.git');
    }
    $hlava = git($repo, 'rev-parse', 'HEAD');
    $atrapa = docasna('gh-zavreni');

    $scenar = array_merge([
        'issue' => issue($zmenyIssue),
        'hlava' => $hlava,
        'behy' => behy('completed', 'success'),
    ], $zmenyScenare);
    file_put_contents($atrapa . '/scenar.json', json_encode($scenar, JSON_UNESCAPED_UNICODE));
    file_put_contents($atrapa . '/gh.php', <<<'PHP'
        <?php
        $scenar = json_decode((string) file_get_contents((string) getenv('STUB_SCENAR')), true);
        $a = array_slice($argv, 1);
        file_put_contents((string) getenv('STUB_LOG'), implode(' ', $a) . "\n", FILE_APPEND);
        if (in_array(($a[0] ?? '') . ' ' . ($a[1] ?? ''), (array) ($scenar['selhat'] ?? []), true)) {
            fwrite(STDERR, "gh: chyba (atrapa)\n");
            exit(1);
        }
        if (($a[0] ?? '') === 'issue' && ($a[1] ?? '') === 'view') {
            echo json_encode($scenar['issue']);
            exit(0);
        }
        if (($a[0] ?? '') === 'issue') {
            exit(0);
        }
        $cesta = $a[1] ?? '';
        if (($a[0] ?? '') === 'api') {
            if (str_contains($cesta, '/check-runs')) {
                echo json_encode($scenar['behy']);
            } elseif (str_contains($cesta, '/compare/')) {
                echo json_encode(['status' => $scenar['srovnani'] ?? 'identical']);
            } elseif (preg_match('#^repos/[^/]+/[^/]+/commits/#', $cesta)) {
                echo json_encode(['sha' => $scenar['hlava']]);
            } elseif (preg_match('#^repos/[^/]+/[^/]+$#', $cesta)) {
                echo json_encode(['default_branch' => 'main']);
            } else {
                exit(1);
            }
            exit(0);
        }
        exit(1);
        PHP);
    // Absolutní cesta, ne %~dp0: když cmd najde gh.cmd přes PATH a jméno je
    // v uvozovkách, ukazuje %~dp0 do aktuální složky, ne ke skriptu.
    file_put_contents($atrapa . '/gh.cmd', '@"' . PHP_BINARY . '" "' . $atrapa . DIRECTORY_SEPARATOR . 'gh.php" %*' . "\r\n");
    file_put_contents($atrapa . '/gh', "#!/bin/sh\nexec \"" . PHP_BINARY . "\" \"\$(dirname \"\$0\")/gh.php\" \"\$@\"\n");
    chmod($atrapa . '/gh', 0755);

    $komentar = $atrapa . DIRECTORY_SEPARATOR . 'komentar.md';
    file_put_contents($komentar, ($komentarText ?? 'Opraveno v app/kod.php, ověřeno testem, commit ' . substr($hlava, 0, 7) . '.')
        . ($dodatek !== null ? "\n$dodatek" : '') . "\n");

    $argumenty = [PHP_BINARY, NASTROJE . '/zavrit-issue.php', $repo, '1'];
    if (!in_array('--bez-komentare', $prepinace, true)) {
        array_push($argumenty, '--komentar', $komentar);
    }
    foreach ($prepinace as $prepinac) {
        if ($prepinac !== '--bez-komentare') {
            $argumenty[] = $prepinac;
        }
    }
    $log = $atrapa . DIRECTORY_SEPARATOR . 'volani.log';
    $vysledek = spust($argumenty, [
        'PATH' => $atrapa . PATH_SEPARATOR . (string) getenv('PATH'),
        'STUB_SCENAR' => $atrapa . DIRECTORY_SEPARATOR . 'scenar.json',
        'STUB_LOG' => $log,
    ]);

    return [$vysledek, (string) @file_get_contents($log)];
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
function issuesSAtrapou(string $rezim, array $issues, ?string $repo = null): array
{
    if ($repo === null) {
        $repo = novyProjekt();
        git($repo, 'remote', 'add', 'origin', 'https://github.com/Terms4Ever/zkusebni.git');
    }

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
