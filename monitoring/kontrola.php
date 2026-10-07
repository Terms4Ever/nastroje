<?php
declare(strict_types=1);

require_once __DIR__ . '/funkce.php';

try {
    $volby = array_slice($argv, 1);
    if (array_diff($volby, ['--zapsat', '--pokud-chybi', '--help']) !== []) {
        throw new RuntimeException('Neznámá volba. Použijte --help.');
    }
    if (in_array('--help', $volby, true)) {
        echo "php monitoring/kontrola.php [--zapsat] [--pokud-chybi]\nBez --zapsat jen čte weby a vypisuje výsledek. Záznam stejné UTC hodiny se nepřepisuje.\nKódy: 0 v pořádku, 2 nález na webu (i při opakování), 1 chyba nástroje.\n";
        exit(0);
    }
    $v = \MonitoringWebu\proved(dirname(__DIR__), in_array('--zapsat', $volby, true), in_array('--pokud-chybi', $volby, true), '\MonitoringWebu\zmer', new DateTimeImmutable('now'));
    echo $v['preskoceno'] ? "Platný záznam této hodiny už existuje, weby se nevolají.\n" : "Měření čtyř schválených webů dokončeno.\n";
    echo \MonitoringWebu\prehled($v['zaznam']) . "\n";
    exit($v['kod']);
} catch (Throwable $e) {
    fwrite(STDERR, 'Monitoring neproběhl: ' . $e->getMessage() . "\n");
    exit(1);
}
