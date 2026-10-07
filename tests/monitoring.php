<?php
declare(strict_types=1);

function monitoringNacti(): void
{
    require_once NASTROJE . '/monitoring/funkce.php';
}

function monitoringRepo(): string
{
    $root = docasna('monitoring');
    mkdir($root . '/monitoring');
    mkdir($root . '/docs');
    copy(NASTROJE . '/monitoring/weby.json', $root . '/monitoring/weby.json');
    file_put_contents($root . '/docs/09-monitoring-webu.md', "# Monitoring\n\nPravidla zůstanou.\n\n<!-- monitoring:zacatek -->\nZatím bez měření.\n<!-- monitoring:konec -->\n");
    return $root;
}

function monitoringOdpoved(array $zmeny = []): array
{
    return array_replace(['http' => 200, 'doba_ms' => 125, 'obsah' => '<title>Zvědavka OnlineFakturuj.cz Vyřídímestavbu.cz Tomáš Šaroun</title>', 'certifikat_do' => strtotime('2027-01-01T12:00:00Z'), 'chyba' => null], $zmeny);
}

pripad('monitoring: schválená konfigurace má právě čtyři weby', function (): array {
    monitoringNacti();
    $weby = \MonitoringWebu\nactiWeby(NASTROJE . '/monitoring/weby.json');
    return [count($weby) === 4, 'Počet schválených webů nesedí.'];
});

foreach (['pátý web', 'jiná doména', 'HTTP', 'dotaz', 'cesta', 'duplicita', 'chybějící web', 'prázdný text'] as $varianta) {
    pripad('monitoring: odmítne ' . $varianta, function () use ($varianta): array {
        monitoringNacti();
        $root = monitoringRepo();
        $data = json_decode(file_get_contents($root . '/monitoring/weby.json'), true);
        switch ($varianta) {
            case 'pátý web': $data['weby'][] = ['url' => 'https://example.com/', 'text' => 'Example']; break;
            case 'jiná doména': $data['weby'][0]['url'] = 'https://example.com/'; break;
            case 'HTTP': $data['weby'][0]['url'] = 'http://zvedavka.cz/'; break;
            case 'dotaz': $data['weby'][0]['url'] .= '?token=nepatri-sem'; break;
            case 'cesta': $data['weby'][0]['url'] .= 'cron.php'; break;
            case 'duplicita': $data['weby'][1] = $data['weby'][0]; break;
            case 'chybějící web': array_pop($data['weby']); break;
            case 'prázdný text': $data['weby'][0]['text'] = ''; break;
        }
        file_put_contents($root . '/monitoring/weby.json', json_encode($data));
        try { \MonitoringWebu\nactiWeby($root . '/monitoring/weby.json'); } catch (RuntimeException) { return [true, '']; }
        return [false, 'Nepovolená konfigurace prošla.'];
    });
}

foreach ([
    'zdravý web' => [[], 'v_poradku', null],
    'HTTP chyba' => [['http' => 503], 'chyba', 'http'],
    'přesměrování není úspěch' => [['http' => 302], 'chyba', 'http'],
    'jiný obsah' => [['obsah' => '<h1>Chyba</h1>'], 'chyba', 'obsah'],
    'výpadek' => [['http' => 0, 'chyba' => 'sit', 'certifikat_do' => null], 'chyba', 'sit'],
    'neplatné TLS' => [['chyba' => 'tls'], 'chyba', 'tls'],
    'chybějící certifikát' => [['certifikat_do' => null], 'chyba', 'certifikat'],
    'prošlý certifikát' => [['certifikat_do' => strtotime('2026-10-06T10:00:00Z')], 'chyba', 'certifikat_prosly'],
    'blížící se expirace' => [['certifikat_do' => strtotime('2026-10-15T10:00:00Z')], 'varovani', 'certifikat_brzy'],
    'pomalý web' => [['doba_ms' => 5500], 'varovani', 'odezva'],
    'příliš velká odpověď' => [['chyba' => 'velikost'], 'chyba', 'velikost'],
] as $nazev => [$zmeny, $stav, $problem]) {
    pripad('monitoring: ' . $nazev, function () use ($zmeny, $stav, $problem): array {
        monitoringNacti();
        $web = ['url' => 'https://zvedavka.cz/', 'text' => 'Zvědavka'];
        $v = \MonitoringWebu\vyhodnot($web, monitoringOdpoved($zmeny), new DateTimeImmutable('2026-10-07T10:17:00+02:00'));
        return [$v['stav'] === $stav && ($problem === null || in_array($problem, $v['problemy'], true)) && !isset($v['obsah']), json_encode($v)];
    });
}

pripad('monitoring: bez --zapsat nezaloží historii ani neupraví dokument', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    $pred = file_get_contents($root . '/docs/09-monitoring-webu.md');
    $v = \MonitoringWebu\proved($root, false, false, fn () => monitoringOdpoved(), new DateTimeImmutable('2026-10-07T10:17:00+02:00'));
    return [$v['kod'] === 0 && !is_dir($root . '/monitoring/vysledky') && file_get_contents($root . '/docs/09-monitoring-webu.md') === $pred, 'Čtení změnilo soubory.'];
});

pripad('monitoring: opakovaný běh dnes neměří ani nepřepisuje historii', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    $ted = new DateTimeImmutable('2026-10-07T10:17:00+02:00');
    \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(), $ted);
    $soubor = $root . '/monitoring/vysledky/2026-10/2026-10-07.json';
    $pred = file_get_contents($soubor);
    $doc = file_get_contents($root . '/docs/09-monitoring-webu.md');
    $v = \MonitoringWebu\proved($root, true, true, fn () => throw new RuntimeException('Druhý síťový požadavek'), $ted->modify('+6 hours'));
    return [$v['preskoceno'] && file_get_contents($soubor) === $pred && file_get_contents($root . '/docs/09-monitoring-webu.md') === $doc && str_contains($doc, 'Pravidla zůstanou.'), 'Duplicitní běh změnil záznam nebo pravidla.'];
});

pripad('monitoring: výpadek se uloží a vrátí chybu, další den má vlastní soubor', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    $ted = new DateTimeImmutable('2026-10-07T10:17:00+02:00');
    $v = \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(['http' => 503]), $ted);
    \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(), $ted->modify('+1 day'));
    $data = json_decode(file_get_contents($root . '/monitoring/vysledky/2026-10/2026-10-07.json'), true);
    return [$v['kod'] === 2 && count(glob($root . '/monitoring/vysledky/2026-10/*.json')) === 2 && $data['weby'][0]['stav'] === 'chyba', 'Výpadek zmizel nebo nevznikl nový den.'];
});

pripad('monitoring: poškozený dnešní soubor není důvod tiše přeskočit', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    mkdir($root . '/monitoring/vysledky/2026-10', 0777, true);
    file_put_contents($root . '/monitoring/vysledky/2026-10/2026-10-07.json', '{}');
    try { \MonitoringWebu\proved($root, true, true, fn () => throw new RuntimeException('síť'), new DateTimeImmutable('2026-10-07T10:17:00+02:00')); }
    catch (RuntimeException $e) { return [str_contains($e->getMessage(), 'záznam'), $e->getMessage()]; }
    return [false, 'Poškozená historie prošla.'];
});

pripad('monitoring: datum se řídí Prahou i v létě a zimě', function (): array {
    monitoringNacti();
    foreach (['2026-07-01T22:30:00Z' => '2026-07-02', '2026-12-01T23:30:00Z' => '2026-12-02'] as $cas => $den) {
        $v = \MonitoringWebu\proved(monitoringRepo(), false, false, fn () => monitoringOdpoved(), new DateTimeImmutable($cas));
        if ($v['zaznam']['datum'] !== $den) { return [false, 'Chybná hranice dne.']; }
    }
    return [true, ''];
});

pripad('monitoring: chybějící značka dokumentu zastaví zápis', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    file_put_contents($root . '/docs/09-monitoring-webu.md', '# Bez značek');
    try { \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(), new DateTimeImmutable('2026-10-07T10:17:00+02:00')); }
    catch (RuntimeException) { return [!is_dir($root . '/monitoring/vysledky'), 'Vznikla poloviční historie.']; }
    return [false, 'Poškozený dokument prošel.'];
});

pripad('monitoring: nepravdivý zelený záznam se odmítne', function (): array {
    monitoringNacti();
    $v = \MonitoringWebu\proved(monitoringRepo(), false, false, fn () => monitoringOdpoved(['http' => 503]), new DateTimeImmutable('2026-10-07T10:17:00+02:00'));
    $v['zaznam']['weby'][0]['stav'] = 'v_poradku';
    $v['zaznam']['weby'][0]['problemy'] = [];
    try { \MonitoringWebu\overZaznam($v['zaznam'], '2026-10-07'); } catch (RuntimeException) { return [true, '']; }
    return [false, 'HTTP 503 bez nálezu prošlo jako platná historie.'];
});

pripad('monitoring: odmítne neplatný JSON a neznámou volbu CLI', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    file_put_contents($root . '/monitoring/weby.json', '{');
    try { \MonitoringWebu\nactiWeby($root . '/monitoring/weby.json'); } catch (RuntimeException) {
        return ocekavej(php('monitoring/kontrola.php', '--neexistuje'), 1, 'Neznámá volba');
    }
    return [false, 'Neplatný JSON prošel.'];
});

pripad('monitoring: obnovení přehledu po přerušeném zápisu neměří znovu', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    $pred = file_get_contents($root . '/docs/09-monitoring-webu.md');
    $ted = new DateTimeImmutable('2026-10-07T10:17:00+02:00');
    \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(), $ted);
    $po = file_get_contents($root . '/docs/09-monitoring-webu.md');
    file_put_contents($root . '/docs/09-monitoring-webu.md', $pred);
    \MonitoringWebu\proved($root, true, true, fn () => throw new RuntimeException('síť'), $ted);
    return [file_get_contents($root . '/docs/09-monitoring-webu.md') === $po, 'Přerušený zápis nebyl opraven.'];
});
