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

pripad('monitoring: opakovaný běh téže hodiny neměří ani nepřepisuje historii', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    $ted = new DateTimeImmutable('2026-10-07T10:17:00+02:00');
    \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(), $ted);
    $soubor = $root . '/monitoring/vysledky/2026-10/2026-10-07T08Z.json';
    $pred = file_get_contents($soubor);
    $doc = file_get_contents($root . '/docs/09-monitoring-webu.md');
    $v = \MonitoringWebu\proved($root, true, true, fn () => throw new RuntimeException('Druhý síťový požadavek'), $ted->modify('+20 minutes'));
    return [$v['preskoceno'] && file_get_contents($soubor) === $pred && file_get_contents($root . '/docs/09-monitoring-webu.md') === $doc && str_contains($doc, 'Pravidla zůstanou.'), 'Duplicitní běh změnil záznam nebo pravidla.'];
});

pripad('monitoring: výpadek se uloží a vrátí chybu, další den má vlastní soubor', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    $ted = new DateTimeImmutable('2026-10-07T10:17:00+02:00');
    $v = \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(['http' => 503]), $ted);
    \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(), $ted->modify('+1 day'));
    $data = json_decode(file_get_contents($root . '/monitoring/vysledky/2026-10/2026-10-07T08Z.json'), true);
    return [$v['kod'] === 2 && count(glob($root . '/monitoring/vysledky/2026-10/*.json')) === 2 && $data['weby'][0]['stav'] === 'chyba', 'Výpadek zmizel nebo nevznikl nový den.'];
});

pripad('monitoring: poškozený hodinový soubor není důvod tiše přeskočit', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    mkdir($root . '/monitoring/vysledky/2026-10', 0777, true);
    file_put_contents($root . '/monitoring/vysledky/2026-10/2026-10-07T08Z.json', '{}');
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

pripad('monitoring: další hodina téhož dne skutečně změří a zachová předchozí výpadek', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    $ted = new DateTimeImmutable('2026-10-07T10:17:00+02:00');
    \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(['http' => 503]), $ted);
    $volani = 0;
    $v = \MonitoringWebu\proved($root, true, true, function () use (&$volani) { $volani++; return monitoringOdpoved(); }, $ted->modify('+1 hour'));
    $pred = json_decode((string) @file_get_contents($root . '/monitoring/vysledky/2026-10/2026-10-07T08Z.json'), true);
    $doc = file_get_contents($root . '/docs/09-monitoring-webu.md');
    return [$volani === 4 && !$v['preskoceno'] && $v['kod'] === 0 && ($pred['weby'][0]['http'] ?? null) === 503
        && count(glob($root . '/monitoring/vysledky/2026-10/*.json')) === 2
        && str_contains($doc, '2026-10-07T09Z.json'), 'Nová hodina se přeskočila, změnila historii nebo odkazuje na jiný záznam.'];
});

pripad('monitoring: opakování chybové hodiny zachová neúspěch pro upozornění', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    $ted = new DateTimeImmutable('2026-10-07T10:17:00+02:00');
    \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(['http' => 503]), $ted);
    $v = \MonitoringWebu\proved($root, true, true, fn () => throw new RuntimeException('síť'), $ted->modify('+10 minutes'));
    return [$v['preskoceno'] && $v['kod'] === 2, 'Opakování změnilo výpadek na zelený běh.'];
});

pripad('monitoring: podzimní opakovaná hodina a jarní změna času se neslijí', function (): array {
    monitoringNacti();
    foreach ([['2026-10-25T00:17:00Z', '2026-10-25T01:17:00Z'], ['2026-03-29T00:17:00Z', '2026-03-29T01:17:00Z']] as [$prvni, $druhy]) {
        $root = monitoringRepo();
        $a = \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(), new DateTimeImmutable($prvni));
        $b = \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(), new DateTimeImmutable($druhy));
        if ($b['preskoceno'] || $a['zaznam']['mereno'] === $b['zaznam']['mereno']
            || count(glob($root . '/monitoring/vysledky/*/*.json')) !== 2) { return [false, 'Změna času slila dvě měření.']; }
    }
    return [true, ''];
});

pripad('monitoring: starý denní záznam zůstane čitelný a neblokuje hodinové měření', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    $ted = new DateTimeImmutable('2026-10-07T10:17:00+02:00');
    $v = \MonitoringWebu\proved($root, false, false, fn () => monitoringOdpoved(), $ted);
    $data = $v['zaznam'];
    $data['schema'] = 1;
    \MonitoringWebu\overZaznam($data, '2026-10-07');
    mkdir($root . '/monitoring/vysledky/2026-10', 0777, true);
    $cesta = $root . '/monitoring/vysledky/2026-10/2026-10-07.json';
    $puvodni = json_encode($data);
    file_put_contents($cesta, $puvodni);
    $v = \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(), $ted);
    return [!$v['preskoceno'] && $v['zaznam']['schema'] === 2 && file_get_contents($cesta) === $puvodni
        && str_contains(\MonitoringWebu\prehled($data), '2026-10-07.json'), 'Denní historie se změnila nebo přeskočila hodinový běh.'];
});

pripad('monitoring: cizí hodina pod dnešním názvem se odmítne', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    $ted = new DateTimeImmutable('2026-10-07T10:17:00+02:00');
    $v = \MonitoringWebu\proved($root, false, false, fn () => monitoringOdpoved(), $ted->modify('-1 hour'));
    mkdir($root . '/monitoring/vysledky/2026-10', 0777, true);
    file_put_contents($root . '/monitoring/vysledky/2026-10/2026-10-07T08Z.json', json_encode($v['zaznam']));
    try { \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(), $ted); }
    catch (RuntimeException) { return [true, '']; }
    return [false, 'Záznam z jiné hodiny byl přijat nebo přepsán.'];
});

pripad('monitoring: nesmyslný čas v záznamu se odmítne', function (): array {
    monitoringNacti();
    $v = \MonitoringWebu\proved(monitoringRepo(), false, false, fn () => monitoringOdpoved(), new DateTimeImmutable('2026-10-07T10:17:00+02:00'));
    $v['zaznam']['mereno'] = '2026-10-07T99:17:00+02:00';
    try { \MonitoringWebu\overZaznam($v['zaznam'], '2026-10-07'); } catch (RuntimeException) { return [true, '']; }
    return [false, 'Neplatná hodina prošla.'];
});

pripad('monitoring: přelom měsíce ukládá podle UTC a zobrazuje český čas', function (): array {
    monitoringNacti();
    $root = monitoringRepo();
    $v = \MonitoringWebu\proved($root, true, true, fn () => monitoringOdpoved(), new DateTimeImmutable('2026-10-31T23:17:00Z'));
    return [$v['zaznam']['datum'] === '2026-11-01' && is_file($root . '/monitoring/vysledky/2026-10/2026-10-31T23Z.json')
        && str_contains(file_get_contents($root . '/docs/09-monitoring-webu.md'), '2026-10/2026-10-31T23Z.json'), 'Cesta neodpovídá UTC hodině.'];
});
