<?php
declare(strict_types=1);

require_once __DIR__ . '/src/sada-pravidel.php';

// Stejná kontrola lokálně a v CI. Online režim je pouze čtecí a nikdy neopravuje topics sám.
try {
    $args = array_slice($argv, 1);
    $online = in_array('--online', $args, true);
    $paths = array_values(array_filter($args, static fn($arg) => $arg !== '--online'));
    if (count($paths) > 1 || isset($paths[0]) && str_starts_with($paths[0], '--')) {
        throw new RuntimeException('Použití: php kontrola-pravidel.php [kořen] [--online]');
    }
    $root = $paths[0] ?? getcwd();
    \Terms4Ever\SadaPravidel::over($root, 'nastroje');
    if ($online) {
        $origin = pravidlaSpust(['git', '-C', $root, 'remote', 'get-url', 'origin']);
        if (!preg_match('~^(?:https://github\.com/|git@github\.com:)(Terms4Ever/[A-Za-z0-9_.-]+?)(?:\.git)?$~D', trim($origin), $m)) {
            throw new RuntimeException('Origin není jednoznačný repozitář Terms4Ever na GitHubu.');
        }
        $metadata = json_decode(pravidlaSpust(['gh', 'api', 'repos/' . $m[1] . '/topics']), true, 512, JSON_THROW_ON_ERROR);
        if (!isset($metadata['names']) || !is_array($metadata['names']) || !array_is_list($metadata['names'])) {
            throw new RuntimeException('GitHub nevrátil platný seznam topics.');
        }
        \Terms4Ever\SadaPravidel::topics($metadata['names'], 'nastroje');
    }
    echo 'Sada pravidel nastroje: výběr, README, AGENTS a workflow souhlasí'
        . ($online ? ', GitHub topic ověřen.' : '. GitHub topic nebyl ověřen (přidej --online).') . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Kontrola sady pravidel: ' . $e->getMessage() . "\n");
    exit(1);
}

function pravidlaSpust(array $command): string
{
    $invocation = $command;
    if (PHP_OS_FAMILY === 'Windows' && $command[0] === 'gh') {
        // Windows v poli argv neumí spustit gh.cmd (např. testovací adaptér).
        // Shell dostane jen whitelistované konstanty a ověřenou cestu API, nikdy volný text.
        foreach ($command as $arg) {
            if (!preg_match('~^[A-Za-z0-9_./-]+$~D', $arg)) { throw new RuntimeException('Neplatný argument GitHub API.'); }
        }
        $invocation = implode(' ', $command);
    }
    $process = proc_open($invocation, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Nelze spustit ' . $command[0]); }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
        // Nepřeposíláme chybovou odpověď externího programu, může obsahovat citlivé URL.
        throw new RuntimeException('Příkaz ' . $command[0] . ' selhal, online příslušnost není ověřena.');
    }
    return $output;
}
