<?php
declare(strict_types=1);

namespace MonitoringWebu;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

// N40: další web nebo cesta vyžaduje souhlas vlastníka, změnu zde i v konfiguraci.
const SCHVALENE_URL = [
    'https://zvedavka.cz/',
    'https://onlinefakturuj.cz/',
    'https://vyridimestavbu.cz/',
    'https://tomas.saroun.me/',
];
const DOKUMENT = '/docs/09-monitoring-webu.md';
const ZACATEK = '<!-- monitoring:zacatek -->';
const KONEC = '<!-- monitoring:konec -->';
const POPISY = [
    'http' => 'HTTP není 200', 'obsah' => 'chybí očekávaný obsah',
    'sit' => 'spojení selhalo', 'tls' => 'ověření TLS selhalo',
    'velikost' => 'odpověď přesáhla 1 MiB', 'certifikat' => 'nelze určit platnost certifikátu',
    'certifikat_prosly' => 'certifikát vypršel', 'certifikat_brzy' => 'certifikát vyprší do 14 dnů',
    'odezva' => 'odezva přes 5 sekund',
];

function nactiJson(string $soubor): array
{
    $text = @file_get_contents($soubor);
    if ($text === false) { throw new RuntimeException('Nelze přečíst ' . $soubor); }
    try { $data = json_decode($text, true, 32, JSON_THROW_ON_ERROR); }
    catch (\JsonException $e) { throw new RuntimeException('Neplatný JSON: ' . $soubor, 0, $e); }
    if (!is_array($data)) { throw new RuntimeException('JSON musí obsahovat objekt: ' . $soubor); }
    return $data;
}

function nactiWeby(string $soubor): array
{
    $data = nactiJson($soubor);
    $weby = $data['weby'] ?? null;
    if (array_keys($data) !== ['weby'] || !is_array($weby) || !array_is_list($weby) || count($weby) !== 4) {
        throw new RuntimeException('Konfigurace musí obsahovat právě čtyři schválené weby.');
    }
    $urls = [];
    foreach ($weby as $web) {
        if (!is_array($web) || array_keys($web) !== ['url', 'text']
            || !in_array($web['url'], SCHVALENE_URL, true) || !is_string($web['text'])
            || trim($web['text']) === '' || strlen($web['text']) > 120) {
            throw new RuntimeException('Neschválená adresa nebo neplatný očekávaný text.');
        }
        $urls[] = $web['url'];
    }
    if (count(array_unique($urls)) !== 4) { throw new RuntimeException('Každý schválený web musí být právě jednou.'); }
    return $weby;
}

/** Jediný síťový vstup: GET přes ověřené TLS, bez cookies a bez přesměrování. */
function zmer(array $web): array
{
    if (!in_array($web['url'] ?? '', SCHVALENE_URL, true)) { throw new RuntimeException('Neschválená adresa.'); }
    if (!extension_loaded('curl') || !extension_loaded('openssl')) {
        throw new RuntimeException('Monitoring potřebuje rozšíření PHP curl a openssl.');
    }
    $obsah = '';
    $velke = false;
    $curl = curl_init($web['url']);
    curl_setopt_array($curl, [
        CURLOPT_HTTPGET => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT_MS => 8000,
        CURLOPT_TIMEOUT_MS => 20000,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CERTINFO => true,
        CURLOPT_USERAGENT => 'Terms4Ever-Monitoring/1.0 (+https://github.com/Terms4Ever/nastroje)',
        CURLOPT_HTTPHEADER => ['Accept: text/html'],
        CURLOPT_WRITEFUNCTION => static function ($curl, string $cast) use (&$obsah, &$velke): int {
            if (strlen($obsah) + strlen($cast) > 1048576) { $velke = true; return 0; }
            $obsah .= $cast;
            return strlen($cast);
        },
    ]);
    curl_exec($curl);
    $errno = curl_errno($curl);
    $info = curl_getinfo($curl);
    $certifikaty = curl_getinfo($curl, CURLINFO_CERTINFO);
    $cert = isset($certifikaty[0]['Cert']) ? openssl_x509_parse($certifikaty[0]['Cert']) : false;
    curl_close($curl);
    $chyba = $velke ? 'velikost' : ($errno === 0 ? null : (in_array($errno, [35, 51, 58, 59, 60, 77, 82, 83, 90, 91], true) ? 'tls' : 'sit'));
    return [
        'http' => (int) $info['http_code'],
        'doba_ms' => (int) round($info['total_time'] * 1000),
        'obsah' => $obsah,
        'certifikat_do' => $cert['validTo_time_t'] ?? null,
        'chyba' => $chyba,
    ];
}

/** Z odpovědi ukládá pouze měření, nikdy HTML, hlavičky, IP adresy nebo cookies. */
function vyhodnot(array $web, array $odpoved, DateTimeImmutable $ted): array
{
    $problemy = [];
    if ($odpoved['chyba'] !== null) { $problemy[] = $odpoved['chyba']; }
    if ($odpoved['http'] !== 200) { $problemy[] = 'http'; }
    $text = html_entity_decode(strip_tags($odpoved['obsah']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $obsahSedi = str_contains($text, $web['text']);
    if (!$obsahSedi) { $problemy[] = 'obsah'; }
    $expirace = $odpoved['certifikat_do'];
    $dny = $expirace === null ? null : (int) floor(($expirace - $ted->getTimestamp()) / 86400);
    if ($dny === null) { $problemy[] = 'certifikat'; }
    elseif ($expirace <= $ted->getTimestamp()) { $problemy[] = 'certifikat_prosly'; }
    elseif ($dny <= 14) { $problemy[] = 'certifikat_brzy'; }
    if ($odpoved['doba_ms'] > 5000) { $problemy[] = 'odezva'; }
    $chyby = array_diff($problemy, ['certifikat_brzy', 'odezva']);
    return [
        'url' => $web['url'],
        'stav' => $chyby !== [] ? 'chyba' : ($problemy !== [] ? 'varovani' : 'v_poradku'),
        'http' => $odpoved['http'], 'doba_ms' => $odpoved['doba_ms'],
        'obsah_ocekavany' => $obsahSedi,
        'certifikat_do' => $expirace === null ? null : gmdate('Y-m-d\TH:i:s\Z', $expirace),
        'dni_do_konce' => $dny,
        'problemy' => $problemy,
    ];
}

/** UTC rozliší i dvě podzimní 02:00; český čas zůstává uvnitř záznamu. */
function hodina(DateTimeImmutable $cas): string
{
    return $cas->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH\Z');
}

function cestaZaznamu(array $data): string
{
    $nazev = $data['schema'] === 1 ? $data['datum'] : hodina(new DateTimeImmutable($data['mereno']));
    return '/monitoring/vysledky/' . substr($nazev, 0, 7) . '/' . $nazev . '.json';
}

function kodZaznamu(array $data): int
{
    return count(array_filter($data['weby'], static fn ($w) => $w['stav'] !== 'v_poradku')) > 0 ? 2 : 0;
}

function overZaznam(array $data, string $den, ?string $hodina = null): void
{
    $weby = $data['weby'] ?? null;
    if (array_keys($data) !== ['schema', 'datum', 'mereno', 'weby'] || !in_array($data['schema'], [1, 2], true)
        || $data['datum'] !== $den || !is_string($data['mereno'])
        || !preg_match('/^' . preg_quote($den, '/') . 'T\d{2}:\d{2}:\d{2}\+0[12]:00$/', $data['mereno'])
        || !is_array($weby) || count($weby) !== 4 || !array_is_list($weby)) {
        throw new RuntimeException('Neplatný záznam měření.');
    }
    $cas = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $data['mereno']);
    if ($cas === false || $cas->setTimezone(new DateTimeZone('Europe/Prague'))->format(DATE_ATOM) !== $data['mereno']
        || ($hodina !== null && ($data['schema'] !== 2 || hodina($cas) !== $hodina))) {
        throw new RuntimeException('Záznam má neplatný čas nebo patří do jiné hodiny.');
    }
    $urls = [];
    foreach ($weby as $web) {
        if (!is_array($web) || array_keys($web) !== ['url', 'stav', 'http', 'doba_ms', 'obsah_ocekavany', 'certifikat_do', 'dni_do_konce', 'problemy']
            || !in_array($web['url'], SCHVALENE_URL, true)
            || !in_array($web['stav'], ['v_poradku', 'varovani', 'chyba'], true)
            || !is_int($web['http']) || $web['http'] < 0 || $web['http'] > 599
            || !is_int($web['doba_ms']) || $web['doba_ms'] < 0
            || !is_bool($web['obsah_ocekavany'])
            || !($web['dni_do_konce'] === null || is_int($web['dni_do_konce']))
            || !($web['certifikat_do'] === null || (is_string($web['certifikat_do']) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $web['certifikat_do'])))
            || !is_array($web['problemy']) || !array_is_list($web['problemy'])
            || array_diff($web['problemy'], array_keys(POPISY)) !== []) {
            throw new RuntimeException('Neplatný web v záznamu měření.');
        }
        $chyby = array_diff($web['problemy'], ['certifikat_brzy', 'odezva']);
        $stav = $chyby !== [] ? 'chyba' : ($web['problemy'] !== [] ? 'varovani' : 'v_poradku');
        if ($web['stav'] !== $stav
            || (($web['http'] !== 200) !== in_array('http', $web['problemy'], true))
            || ((!$web['obsah_ocekavany']) !== in_array('obsah', $web['problemy'], true))
            || (($web['certifikat_do'] === null) !== in_array('certifikat', $web['problemy'], true))
            || (($web['certifikat_do'] === null) !== ($web['dni_do_konce'] === null))) {
            throw new RuntimeException('Záznam měření si odporuje.');
        }
        $urls[] = $web['url'];
    }
    if (count(array_unique($urls)) !== 4) { throw new RuntimeException('Duplicitní web v záznamu měření.'); }
}

function prehled(array $data): string
{
    $radky = [ZACATEK, '', '**Poslední měření:** ' . $data['mereno'] . ' (Europe/Prague).', '',
        '| Web | HTTP | Odezva | Certifikát do (UTC) | Výsledek |', '|---|---|---|---|---|'];
    foreach ($data['weby'] as $web) {
        $stav = match ($web['stav']) { 'v_poradku' => 'V pořádku', 'varovani' => 'Upozornění', default => 'Chyba' };
        $popis = implode(', ', array_map(static fn ($p) => POPISY[$p], $web['problemy']));
        $host = parse_url($web['url'], PHP_URL_HOST);
        $radky[] = "| [$host]({$web['url']}) | {$web['http']} | {$web['doba_ms']} ms | "
            . ($web['certifikat_do'] === null ? 'Nezjištěno' : substr($web['certifikat_do'], 0, 10))
            . " | $stav" . ($popis === '' ? '' : ': ' . $popis) . ' |';
    }
    $radky[] = '';
    $nazev = $data['schema'] === 1 ? 'Původní denní záznam' : 'Hodinový záznam';
    $radky[] = '[' . $nazev . '](..' . cestaZaznamu($data) . '). Jednorázové měření, nikoli nepřetržitá dostupnost.';
    $radky[] = '';
    $radky[] = KONEC;
    return implode("\n", $radky);
}

function dokument(string $root, array $data): string
{
    $text = @file_get_contents($root . DOKUMENT);
    if ($text === false || substr_count($text, ZACATEK) !== 1 || substr_count($text, KONEC) !== 1 || strpos($text, ZACATEK) >= strpos($text, KONEC)) {
        throw new RuntimeException('Dokument monitoringu nemá právě jednu dvojici značek.');
    }
    return substr($text, 0, strpos($text, ZACATEK)) . prehled($data) . substr($text, strpos($text, KONEC) + strlen(KONEC));
}

function zapisAtomicky(string $cesta, string $text): void
{
    if (is_file($cesta) && file_get_contents($cesta) === $text) { return; }
    $slozka = dirname($cesta);
    if (!is_dir($slozka) && !mkdir($slozka, 0777, true)) { throw new RuntimeException('Nelze vytvořit složku výsledků.'); }
    $docasny = tempnam($slozka, '.monitoring-');
    if ($docasny === false) { throw new RuntimeException('Nelze připravit zápis.'); }
    try {
        if (file_put_contents($docasny, $text) !== strlen($text) || !rename($docasny, $cesta)) {
            throw new RuntimeException('Nelze zapsat výsledek.');
        }
    } finally { if (is_file($docasny)) { unlink($docasny); } }
}

/** Síť je v testech nahrazená funkcí. CLI vždy používá zmer() a tento repozitář. */
function proved(string $root, bool $zapsat, bool $pokudChybi, callable $mereni, DateTimeImmutable $ted): array
{
    $weby = nactiWeby($root . '/monitoring/weby.json');
    $ted = $ted->setTimezone(new DateTimeZone('Europe/Prague'));
    $den = $ted->format('Y-m-d');
    $data = ['schema' => 2, 'datum' => $den, 'mereno' => $ted->format(DATE_ATOM), 'weby' => []];
    $cesta = $root . cestaZaznamu($data);
    $zamek = null;
    if ($zapsat) {
        $zamek = fopen(sys_get_temp_dir() . '/nastroje-monitoring-' . hash('sha256', (string) realpath($root)) . '.lock', 'c');
        if ($zamek === false || !flock($zamek, LOCK_EX)) { throw new RuntimeException('Nelze zamknout zápis.'); }
    }
    try {
        if (is_file($cesta) && ($pokudChybi || $zapsat)) {
            $data = nactiJson($cesta);
            overZaznam($data, $den, hodina($ted));
            if ($zapsat) { zapisAtomicky($root . DOKUMENT, dokument($root, $data)); }
            return ['kod' => kodZaznamu($data), 'preskoceno' => true, 'zaznam' => $data];
        }
        foreach ($weby as $web) { $data['weby'][] = vyhodnot($web, $mereni($web), $ted); }
        overZaznam($data, $den, hodina($ted));
        if ($zapsat) {
            $doc = dokument($root, $data); // Ověřit před prvním zápisem, poloviční opravu lze bezpečně zopakovat.
            zapisAtomicky($cesta, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
            zapisAtomicky($root . DOKUMENT, $doc);
        }
        return ['kod' => kodZaznamu($data), 'preskoceno' => false, 'zaznam' => $data];
    } finally { if (is_resource($zamek)) { flock($zamek, LOCK_UN); fclose($zamek); } }
}
