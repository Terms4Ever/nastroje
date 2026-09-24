<?php
declare(strict_types=1);

function projektSeSadou(string $sada = 'nastroje'): string
{
    $root = docasna('sady');
    zapis($root, '.pravidla.json', json_encode(['sada' => $sada]));
    $barva = $sada === 'nastroje' ? '0969da' : '8250df';
    $badge = '[![Pravidla: ' . $sada . '](https://img.shields.io/badge/pravidla-'
        . str_replace('-', '--', $sada) . '-' . $barva . ')](https://github.com/Terms4Ever/' . $sada . ')';
    zapis($root, 'README.md', "# Projekt\n\n$badge\n\n## Funkce\n\nObsah projektu.\n");
    zapis($root, 'AGENTS.md', "# Pokyny\n\nSada pravidel: `$sada` (určuje `.pravidla.json`).\n"
        . "Zdroj pravidel: https://github.com/Terms4Ever/$sada.\n");
    // Osobní sada od N36: workflow Kontroly, sada je vidět v názvu společné kontroly.
    $jobs = $sada === 'nastroje'
        ? "  readme:\n    name: Pravidla nastroje\n    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main\n"
        : "  linux:\n    steps:\n      - run: php scripts/ci.php\n  windows:\n    steps:\n      - run: .\\.cache\\php\\php.exe scripts/ci.php\n";
    $nazev = $sada === 'nastroje' ? 'Kontroly' : "Pravidla / $sada";
    zapis($root, '.github/workflows/kontrola.yml', "name: $nazev\non: [push]\njobs:\n$jobs");
    if ($sada === 'nastroje-prace') {
        zapis($root, '.prace.json', json_encode(['repository' => 'Terms4Ever/nastroje-prace']));
    }
    return $root;
}

function vysledekSady(string $root, string $sada = 'nastroje', ?array $topics = null): array
{
    try {
        require_once NASTROJE . '/src/sada-pravidel.php';
        \Terms4Ever\SadaPravidel::over($root, $sada);
        if ($topics !== null) { \Terms4Ever\SadaPravidel::topics($topics, $sada); }
        return ['kod' => 0, 'vystup' => 'Sada pravidel souhlasí.'];
    } catch (Throwable $e) { return ['kod' => 1, 'vystup' => $e->getMessage()]; }
}

pripad('sady: osobní projekt má jednotné označení a skutečné workflow', fn() =>
    ocekavej(vysledekSady(projektSeSadou(), topics: ['php', 'pravidla-nastroje']), 0));
pripad('sady: pracovní centrála není druhá osobní sada', fn() =>
    ocekavej(vysledekSady(projektSeSadou('nastroje-prace'), 'nastroje-prace', ['pravidla-nastroje-prace']), 0));

foreach ([
    'chybějící výběr' => [null, 'Chybí .pravidla.json'],
    'neznámá sada' => ['{"sada":"cizi"}', 'Neplatný výběr'],
    'dvě hodnoty' => ['{"sada":"nastroje","sada":"nastroje-prace"}', 'Neplatný výběr'],
    'seznam místo objektu' => ['["nastroje"]', 'Neplatný výběr'],
    'jiná hlavní sada' => ['{"sada":"nastroje-prace"}', 'Očekávaná sada'],
] as $nazev => [$manifest, $chyba]) {
    pripad('sady: ' . $nazev . ' se odmítne', function () use ($manifest, $chyba): array {
        $root = projektSeSadou();
        if ($manifest === null) { unlink($root . '/.pravidla.json'); }
        else { zapis($root, '.pravidla.json', $manifest); }
        return ocekavej(vysledekSady($root), 1, $chyba);
    });
}
foreach (['README.md' => 'odznak', 'AGENTS.md' => 'AGENTS', '.github/workflows/kontrola.yml' => 'workflow'] as $path => $chyba) {
    pripad('sady: záměna v ' . $path . ' se odmítne', function () use ($path, $chyba): array {
        $root = projektSeSadou();
        zapis($root, $path, str_replace('nastroje', 'nastroje-prace', (string) file_get_contents($root . '/' . $path)));
        return ocekavej(vysledekSady($root), 1, $chyba);
    });
}
pripad('sady: odznak ukrytý v kódu není viditelné označení', function (): array {
    $root = projektSeSadou();
    zapis($root, 'README.md', "```md\n" . file_get_contents($root . '/README.md') . "\n```\n");
    return ocekavej(vysledekSady($root), 1, 'odznak');
});
foreach (['`%s`', '<pre>%s</pre>', '<code>%s</code>'] as $i => $mask) {
    pripad('sady: neviditelný odznak v inline kódu nebo HTML ' . $i, function () use ($mask): array {
        $root = projektSeSadou();
        $readme = (string) file_get_contents($root . '/README.md');
        preg_match('/^\[!\[Pravidla:.*$/m', $readme, $badge);
        zapis($root, 'README.md', str_replace($badge[0], sprintf($mask, $badge[0]), $readme));
        return ocekavej(vysledekSady($root), 1, 'odznak');
    });
}
foreach ([
    "name: Kontroly\njobs:\n  readme:\n    name: Pravidla nastroje\n    'if': false\n    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main\n",
    "name: Kontroly\njobs:\n  readme:\n    name: \"text\n    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main\n    \"\n",
    "name: Kontroly\nname: Jiný\njobs:\n  readme:\n    name: Pravidla nastroje\n    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main\n",
    "name: Kontroly\njobs:\n  readme:\n    name: Pravidla nastroje\n    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main\n    uses: nekdo/jine/.github/workflows/test.yml@main\n",
    "name: Kontroly\njobs:\n  readme:\n    name: Pravidla nastroje\n    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main\njobs:\n  jiny:\n    runs-on: ubuntu-latest\n",
] as $i => $workflow) {
    pripad('sady: nejednoznačné YAML zapojení ' . $i . ' neprojde', function () use ($workflow): array {
        $root = projektSeSadou();
        zapis($root, '.github/workflows/kontrola.yml', $workflow);
        return ocekavej(vysledekSady($root), 1);
    });
}
pripad('sady: komentář nenahrazuje zapojenou kontrolu', function (): array {
    $root = projektSeSadou();
    $path = '.github/workflows/kontrola.yml';
    zapis($root, $path, str_replace('    uses:', '    # uses:', (string) file_get_contents($root . '/' . $path)));
    return ocekavej(vysledekSady($root), 1, 'zapojení');
});
pripad('sady: vypnutý job nenahrazuje zapojenou kontrolu', function (): array {
    $root = projektSeSadou();
    $path = '.github/workflows/kontrola.yml';
    zapis($root, $path, str_replace('  readme:', "  readme:\n    if: false", (string) file_get_contents($root . '/' . $path)));
    return ocekavej(vysledekSady($root), 1, 'podmín');
});
pripad('sady: přímé osobní workflow v pracovní sadě se odmítne', function (): array {
    $root = projektSeSadou('nastroje-prace');
    zapis($root, '.github/workflows/cizi.yml', "name: Další\njobs:\n  readme:\n    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main\n");
    return ocekavej(vysledekSady($root, 'nastroje-prace'), 1, 'Osobní workflow');
});
pripad('sady: druhý odznak nesmí tvrdit jinou hlavní sadu', function (): array {
    $root = projektSeSadou();
    $original = file_get_contents($root . '/README.md');
    zapis($root, 'README.md', str_replace('## Funkce', '[![Pravidla: nastroje-prace](https://img.shields.io/badge/pravidla-nastroje--prace-8250df)](https://github.com/Terms4Ever/nastroje-prace)' . "\n\n## Funkce", $original));
    return ocekavej(vysledekSady($root), 1, 'odznak');
});
foreach ([[], ['pravidla-nastroje-prace'], ['pravidla-nastroje', 'pravidla-nastroje-prace']] as $i => $topics) {
    pripad('sady: chybné GitHub topics ' . $i . ' se odmítnou', fn() =>
        ocekavej(vysledekSady(projektSeSadou(), topics: $topics), 1, 'topic'));
}

pripad('sady: aplikace používá vlastní připnutý pracovní vstup', function (): array {
    $root = projektSeSadou('nastroje-prace');
    zapis($root, 'nastroje-prace.lock.json', '{}');
    $path = '.github/workflows/kontrola.yml';
    $wf = (string) file_get_contents($root . '/' . $path);
    zapis($root, $path, str_replace(['scripts/ci.php', '.\\.cache\\'], ['.nastroje-prace/scripts/ci.php', '.\\.nastroje-prace\\.cache\\'], $wf));
    return ocekavej(vysledekSady($root, 'nastroje-prace'), 0);
});
pripad('sady: pracovní aplikace nesmí spustit jinou místní kopii', function (): array {
    $root = projektSeSadou('nastroje-prace');
    zapis($root, 'nastroje-prace.lock.json', '{}');
    return ocekavej(vysledekSady($root, 'nastroje-prace'), 1, 'zapojení');
});
pripad('sady: run s výpisem příkazu kontrolu nespouští', function (): array {
    $root = projektSeSadou('nastroje-prace');
    $path = '.github/workflows/kontrola.yml';
    zapis($root, $path, str_replace('run: php ', 'run: echo php ', (string) file_get_contents($root . '/' . $path)));
    return ocekavej(vysledekSady($root, 'nastroje-prace'), 1, 'zapojení');
});
pripad('sady: text ve skaláru YAML není job', function (): array {
    $root = projektSeSadou();
    zapis($root, '.github/workflows/kontrola.yml', "name: Kontroly\non: [push]\njobs:\n  poznamka: |\n    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main\n");
    return ocekavej(vysledekSady($root), 1, 'zapojení');
});
// --- názvy na GitHubu (N36) -------------------------------------------------

/** Projekt ve tvaru před N36: workflow Pravidla / nastroje, společná kontrola bez názvu. */
function projektSeStarymNazvem(): string
{
    $root = projektSeSadou();
    zapis($root, '.github/workflows/kontrola.yml', "name: Pravidla / nastroje\non: [push]\njobs:\n"
        . "  readme:\n    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main\n");
    return $root;
}

pripad('sady: starý název Pravidla / nastroje projde jen během přechodu (N36)', fn() =>
    ocekavej(vysledekSady(projektSeStarymNazvem()), 0));
pripad('sady: společná kontrola bez názvu Pravidla nastroje neprojde (N36)', function (): array {
    $root = projektSeSadou();
    $path = '.github/workflows/kontrola.yml';
    zapis($root, $path, str_replace("    name: Pravidla nastroje\n", '', (string) file_get_contents($root . '/' . $path)));
    return ocekavej(vysledekSady($root), 1, 'Pravidla nastroje');
});
pripad('sady: společná kontrola pod názvem cizí sady neprojde (N36)', function (): array {
    $root = projektSeSadou();
    $path = '.github/workflows/kontrola.yml';
    zapis($root, $path, str_replace('name: Pravidla nastroje', 'name: Pravidla nastroje-prace', (string) file_get_contents($root . '/' . $path)));
    return ocekavej(vysledekSady($root), 1, 'Pravidla nastroje');
});
pripad('sady: nasazení volá společnou kontrolu jen pod názvem Pravidla nastroje (N36)', function (): array {
    $root = projektSeSadou();
    zapis($root, '.github/workflows/deploy.yml', "name: Nasazení\non: [push]\njobs:\n"
        . "  kontroly:\n    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main\n"
        . "  deploy:\n    name: Nahrání na web (FTPS)\n    needs: kontroly\n    runs-on: ubuntu-latest\n");
    return ocekavej(vysledekSady($root), 1, 'Pravidla nastroje');
});
pripad('sady: nasazení se společnou kontrolou pod správným názvem projde (N36)', function (): array {
    $root = projektSeSadou();
    zapis($root, '.github/workflows/deploy.yml', "name: Nasazení\non: [push]\njobs:\n"
        . "  kontroly:\n    name: Pravidla nastroje\n    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main\n"
        . "  deploy:\n    name: Nahrání na web (FTPS)\n    needs: kontroly\n    runs-on: ubuntu-latest\n");
    return ocekavej(vysledekSady($root), 0);
});
pripad('sady: Kontroly a starý název zároveň neprojdou (N36)', function (): array {
    $root = projektSeSadou();
    zapis($root, '.github/workflows/stary.yml', "name: Pravidla / nastroje\non: [push]\njobs:\n"
        . "  readme:\n    uses: Terms4Ever/nastroje/.github/workflows/readme.yml@main\n");
    return ocekavej(vysledekSady($root), 1, 'primární workflow');
});

pripad('sady: místní README kontrola najde chybějící manifest zapojeného projektu', function (): array {
    $root = projektSeSadou();
    unlink($root . '/.pravidla.json');
    return ocekavej(php('kontrola-readme.php', $root), 1, 'Chybí .pravidla.json');
});
pripad('sady: CLI ověří lokální označení a přizná chybějící online ověření', fn() =>
    ocekavej(php('kontrola-pravidel.php', projektSeSadou()), 0, 'topic nebyl ověřen'));
pripad('sady: osobní CLI nepřepne očekávání podle cizího manifestu', fn() =>
    ocekavej(php('kontrola-pravidel.php', projektSeSadou('nastroje-prace')), 1, 'Očekávaná sada'));

foreach ([
    ['{"names":["php","pravidla-nastroje"]}', 0, 0, 'topic ověřen'],
    ['{"names":["pravidla-nastroje-prace"]}', 0, 1, 'topic'],
    ['{"names":[]}', 0, 1, 'topic'],
    ['neplatny-json', 0, 1, 'Syntax error'],
    ['{"jine":[]}', 0, 1, 'seznam topics'],
    ['{}', 1, 1, 'selhal'],
] as $i => [$response, $exitCode, $expectedCode, $expectedText]) {
    pripad('sady: online CLI ' . $i . ' ověřuje skutečnou odpověď API', function () use ($response, $exitCode, $expectedCode, $expectedText): array {
        $root = projektSeSadou();
        git($root, 'init', '-q', '-b', 'main');
        git($root, 'remote', 'add', 'origin', 'https://github.com/Terms4Ever/projekt.git');
        $stub = docasna('sady-api');
        zapis($stub, 'gh.php', '<?php if (array_slice($argv, 1) !== ["api", "repos/Terms4Ever/projekt/topics"]) { exit(9); } echo '
            . var_export($response, true) . '; exit(' . $exitCode . ');');
        zapis($stub, 'gh.cmd', '@"' . PHP_BINARY . '" "' . $stub . DIRECTORY_SEPARATOR . 'gh.php" %*' . "\r\n");
        zapis($stub, 'gh', "#!/bin/sh\nexec \"" . PHP_BINARY . "\" \"\$(dirname \"\$0\")/gh.php\" \"\$@\"\n");
        chmod($stub . '/gh', 0755);
        return ocekavej(spust([PHP_BINARY, NASTROJE . '/kontrola-pravidel.php', $root, '--online'], [
            'PATH' => $stub . PATH_SEPARATOR . getenv('PATH'),
        ]), $expectedCode, $expectedText);
    });
}
