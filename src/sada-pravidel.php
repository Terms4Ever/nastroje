<?php
declare(strict_types=1);

namespace Terms4Ever;

use RuntimeException;

/** Jediný primární výběr pravidel. Připnutá knihovna není druhá sada. */
final class SadaPravidel
{
    public const SADY = ['nastroje', 'nastroje-prace'];

    public static function badge(string $sada): string
    {
        self::platnaSada($sada);
        $barva = $sada === 'nastroje' ? '0969da' : '8250df';
        return '[![Pravidla: ' . $sada . '](https://img.shields.io/badge/pravidla-'
            . str_replace('-', '--', $sada) . '-' . $barva . ')](https://github.com/Terms4Ever/' . $sada . ')';
    }

    public static function vyber(string $root): string
    {
        $json = self::cti($root . '/.pravidla.json', 'Chybí .pravidla.json nebo ji nelze přečíst.');
        // Omezené schéma současně odmítne duplicitní klíč, který json_decode přepisuje.
        if (!preg_match('/^\s*\{\s*"sada"\s*:\s*"(nastroje|nastroje-prace)"\s*\}\s*$/D', $json, $m)) {
            throw new RuntimeException('Neplatný výběr v .pravidla.json. Povolena je právě jedna položka sada: nastroje nebo nastroje-prace.');
        }
        return $m[1];
    }

    public static function over(string $root, string $expected): string
    {
        self::platnaSada($expected);
        $sada = self::vyber($root);
        if ($sada !== $expected) {
            throw new RuntimeException("Očekávaná sada je $expected, projekt vybírá $sada. Použij kontrolu vybrané sady.");
        }
        $readme = self::markdown(self::cti($root . '/README.md', 'Chybí README.md.'));
        $hlavicka = preg_split('/^##\s/m', $readme, 2)[0];
        if (substr_count($readme, '[![Pravidla:') !== 1
            || !preg_match('~^' . preg_quote(self::badge($sada), '~') . '\s*$~m', $hlavicka)) {
            throw new RuntimeException("README musí mít právě jeden viditelný odznak Pravidla: $sada před první sekcí.");
        }
        $agents = self::markdown(self::cti($root . '/AGENTS.md', 'Chybí AGENTS.md.'));
        $uvod = preg_split('/^##\s/m', $agents, 2)[0];
        $deklarace = 'Sada pravidel: `' . $sada . '` (určuje `.pravidla.json`).';
        if (substr_count($agents, 'Sada pravidel:') !== 1 || !str_contains($uvod, $deklarace)
            || !str_contains($uvod, 'Zdroj pravidel: https://github.com/Terms4Ever/' . $sada . '.')) {
            throw new RuntimeException('AGENTS.md musí na začátku jednoznačně uvést vybranou sadu, .pravidla.json a její zdroj.');
        }
        $primarni = [];
        $predN36 = [];
        $vsechny = [];
        foreach (self::workflows($root) as $file => $workflow) {
            foreach (['name', 'jobs'] as $key) {
                if (preg_match_all('/^' . $key . ':/m', $workflow) > 1) {
                    throw new RuntimeException('Duplicitní ' . $key . ' ve workflow ' . basename($file));
                }
            }
            if ($sada === 'nastroje-prace' && preg_match('~^\s*uses:\s*[\x27\x22]?Terms4Ever/nastroje/\.github/workflows/(?:readme|issue-tvar)\.yml@~m', $workflow)) {
                throw new RuntimeException('Osobní workflow nastroje nesmí být zapojeno přímo do pracovní sady: ' . basename($file));
            }
            $vsechny[$file] = $workflow;
            if (preg_match('/^name:\s*(.*?)\s*$/m', $workflow, $name)) {
                $nazev = self::scalar($name[1]);
                if ($nazev === self::nazevWorkflow($sada)) {
                    $primarni[] = $workflow;
                } elseif ($sada === 'nastroje' && $nazev === 'Pravidla / nastroje') {
                    $predN36[] = $workflow;
                }
            }
        }
        // Starý název z N35 se od N36 nepřijímá. Přechod, po který projde, trval
        // jen do přejmenování všech zapojených projektů (24. 9. 2026).
        if ($predN36 !== []) {
            throw new RuntimeException('Právě jeden primární workflow musí mít název ' . self::nazevWorkflow($sada)
                . '; starý název Pravidla / nastroje od N36 neplatí.');
        }
        if (count($primarni) !== 1) {
            throw new RuntimeException('Právě jeden primární workflow musí mít název ' . self::nazevWorkflow($sada) . '.');
        }
        $jobs = self::jobs($primarni[0]);
        if ($sada === 'nastroje') {
            $nalezeno = false;
            foreach ($jobs as $job) {
                if (preg_match_all('/^    uses:/m', $job) > 1) { throw new RuntimeException('Duplicitní uses ve workflow.'); }
                if (preg_match('/^    uses:\s*(.*?)\s*$/m', $job, $uses)
                    && self::scalar($uses[1]) === 'Terms4Ever/nastroje/.github/workflows/readme.yml@main') {
                    self::bezPodminky($job);
                    $nalezeno = true;
                }
            }
            if (!$nalezeno) {
                throw new RuntimeException('Chybí skutečné zapojení sdíleného readme.yml@main v primárním workflow.');
            }
            self::nazvySpolecneKontroly($vsechny, $sada);
        } else {
            $central = !is_file($root . '/nastroje-prace.lock.json');
            $path = $central ? 'scripts/ci.php' : '.nastroje-prace/scripts/ci.php';
            foreach (['linux', 'windows'] as $platform) {
                $job = $jobs[$platform] ?? '';
                self::bezPodminky($job);
                // Podporujeme samostatný skalární run ze šablony, ne příkaz ukrytý v echo nebo komentáři.
                $prefix = $platform === 'linux' ? 'php' : ($central ? '.\\.cache\\php\\php.exe' : '.\\.nastroje-prace\\.cache\\php\\php.exe');
                $command = preg_quote($prefix . ' ' . $path, '~');
                if (!preg_match('~^      (?:- |  )run:\s*[\x27\x22]?' . $command . '[\x27\x22]?\s*$~m', $job)) {
                    throw new RuntimeException("Chybí skutečné zapojení $path v úloze $platform pracovního workflow.");
                }
            }
        }
        return $sada;
    }

    public static function topics(array $topics, string $sada): void
    {
        self::platnaSada($sada);
        $vybrane = array_values(array_filter($topics, static fn($t) => is_string($t) && str_starts_with($t, 'pravidla-')));
        if ($vybrane !== ['pravidla-' . $sada]) {
            throw new RuntimeException('GitHub topic musí jednoznačně odpovídat sadě: pravidla-' . $sada . '.');
        }
    }

    /**
     * Název workflow s kontrolami po pushi. Osobní sada má od N36 krátké
     * Kontroly, sadu ukazuje název společné kontroly; pracovní sada si název
     * zatím nechává.
     */
    public static function nazevWorkflow(string $sada): string
    {
        self::platnaSada($sada);
        return $sada === 'nastroje' ? 'Kontroly' : 'Pravidla / ' . $sada;
    }

    /** Název jobu se společnou kontrolou; z něj je v seznamu kontrol na GitHubu vidět sada (N36). */
    public static function nazevKontroly(string $sada): string
    {
        self::platnaSada($sada);
        return 'Pravidla ' . $sada;
    }

    /**
     * Každý job, který volá společnou kontrolu, nese v názvu sadu, i v nasazení.
     * Jinak by se kontrola na GitHubu ukázala jako holé ID jobu (N36).
     */
    private static function nazvySpolecneKontroly(array $workflows, string $sada): void
    {
        foreach ($workflows as $file => $workflow) {
            foreach (self::jobs($workflow) as $id => $job) {
                if (!preg_match('~^    uses:\s*[\x27\x22]?Terms4Ever/nastroje/\.github/workflows/readme\.yml@~m', $job)) {
                    continue;
                }
                if (preg_match_all('/^    name:/m', $job) > 1) {
                    throw new RuntimeException('Duplicitní name v jobu ' . $id . ' ve workflow ' . basename($file));
                }
                $nazev = preg_match('/^    name:\s*(.*?)\s*$/m', $job, $shoda) ? self::scalar($shoda[1]) : '';
                if ($nazev !== self::nazevKontroly($sada)) {
                    throw new RuntimeException(sprintf(
                        'Job %s ve workflow %s volá společnou kontrolu, musí se proto jmenovat %s.',
                        $id,
                        basename($file),
                        self::nazevKontroly($sada)
                    ));
                }
            }
        }
    }

    /** Zapojení osobních kontrol pro místní kontrolu README; pracovní adaptér volá over samostatně. */
    public static function osobni(string $root): bool
    {
        foreach (self::workflows($root) as $workflow) {
            if (preg_match('~^\s*uses:\s*[\x27\x22]?Terms4Ever/nastroje/\.github/workflows/readme\.yml@~m', $workflow)) { return true; }
        }
        return is_file($root . '/.pravidla.json') && self::vyber($root) === 'nastroje';
    }

    private static function platnaSada(string $sada): void
    {
        if (!in_array($sada, self::SADY, true)) { throw new RuntimeException('Neznámá očekávaná sada pravidel.'); }
    }

    private static function cti(string $file, string $message): string
    {
        $data = is_file($file) ? file_get_contents($file) : false;
        if ($data === false) { throw new RuntimeException($message); }
        return str_replace("\r\n", "\n", $data);
    }

    /** Vynechá komentáře, oplocené a odsazené bloky kódu, které nejsou viditelným označením. */
    private static function markdown(string $text): string
    {
        $text = preg_replace('/<!--.*?(?:-->|$)/s', '', $text);
        $text = preg_replace('~<(pre|code)\b[^>]*>.*?(?:</\1\s*>|$)~is', '', $text);
        $lines = []; $fence = null;
        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $m)) {
                if ($fence === null) { $fence = $m[1]; }
                elseif ($m[1][0] === $fence[0] && strlen($m[1]) >= strlen($fence)) { $fence = null; }
                continue;
            }
            if ($fence === null && !preg_match('/^( {4}|\t)/', $line)) { $lines[] = $line; }
        }
        return implode("\n", $lines);
    }

    /** Jen aktivní YAML řádky; obsah blokových skalárů nesmí předstírat klíče uses/name. */
    private static function workflows(string $root): array
    {
        $result = [];
        foreach (glob($root . '/.github/workflows/*.{yml,yaml}', GLOB_BRACE) ?: [] as $file) {
            $lines = []; $blockIndent = null;
            foreach (explode("\n", self::cti($file, 'Nelze číst workflow.')) as $line) {
                if (trim($line) === '' || preg_match('/^\s*#/', $line)) { continue; }
                $indent = strlen($line) - strlen(ltrim($line, ' '));
                if ($blockIndent !== null && $indent > $blockIndent) { continue; }
                $blockIndent = null;
                // Komentář za oddělující mezerou je v podporovaných hodnotách mimo uvozovky.
                $line = preg_replace('/\s+#.*$/', '', $line);
                if (preg_match('/^\s*(?:- )?[\x27\x22][^\x27\x22]+[\x27\x22]\s*:/', $line)) {
                    throw new RuntimeException('Workflow má nepodporovaný uvozovaný klíč; použij běžný tvar ze šablony.');
                }
                if (preg_match('/^\s*(?:- )?[\w-]+:\s*([\x27\x22])(.*)$/', $line, $quoted)
                    && !str_ends_with(rtrim($quoted[2]), $quoted[1])) {
                    throw new RuntimeException('Workflow má nepodporovaný víceřádkový uvozovaný skalár.');
                }
                if (preg_match('/^\s*(?:- )?(?:[\w-]+|<<):\s*[*&]/', $line)) {
                    throw new RuntimeException('Workflow má nepodporovaný YAML alias nebo kotvu.');
                }
                $lines[] = $line;
                if (preg_match('/:\s*[|>][+-]?\s*$/', $line)) { $blockIndent = $indent; }
            }
            $result[$file] = implode("\n", $lines);
        }
        return $result;
    }

    /** Běžná dvoumezerová mapa jobs používaná sdílenými šablonami. Neznámý tvar se neuzná jako zapojení. */
    private static function jobs(string $workflow): array
    {
        if (!preg_match('/^jobs:\s*\n((?:[ \t]+[^\n]*(?:\n|$))*)/m', $workflow, $m)) { return []; }
        $jobs = []; $name = null;
        foreach (explode("\n", $m[1]) as $line) {
            if (preg_match('/^  ([\w-]+):\s*$/', $line, $key)) {
                $name = $key[1];
                if (isset($jobs[$name])) { throw new RuntimeException('Duplicitní úloha ve workflow.'); }
                $jobs[$name] = '';
            } elseif ($name !== null) { $jobs[$name] .= $line . "\n"; }
        }
        return $jobs;
    }

    private static function bezPodminky(string $job): void
    {
        if (preg_match('/^\s*(?:- )?(?:if|continue-on-error):/m', $job)) {
            throw new RuntimeException('Zapojená kontrola nesmí být vypnutá podmínkou ani ignorovat chybu.');
        }
    }

    private static function scalar(string $value): string { return trim(trim($value), "\"'"); }
}
