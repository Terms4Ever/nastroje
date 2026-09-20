<?php
/**
 * Spouštěč migrací databáze. Vzor z Terms4Ever/nastroje, kopíruje se do projektu
 * (typicky do src/ nebo app/) a připojení si projekt dodá sám.
 *
 * Co dělá: vezme soubory z db/migrace/*.sql, pustí ty, které v téhle databázi
 * ještě neběžely, a zapíše je do tabulky `migrace`. Pořadí je podle názvu,
 * tedy podle data. Každý soubor proběhne právě jednou.
 *
 * Použití z kódu:
 *
 *     $migrace = new Migrace($pdo, __DIR__ . '/../db/migrace');
 *     $zprava = $migrace->spust();     // pole řádků k vypsání
 *     $migrace->nespustene();          // jen seznam, nic nespouští
 *
 * Použití z příkazové řádky (projekt si doplní připojení):
 *
 *     php db/migrace.php
 *
 * Co vzor neumí: bloky s vlastním oddělovačem (DELIMITER), tedy těla procedur
 * a triggerů. Takovou změnu napiš jako jeden příkaz, nebo ji pusť ručně
 * a zapiš do tabulky `migrace`, ať ji spouštěč znovu nezkouší.
 */
declare(strict_types=1);

final class Migrace
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $slozka,
        private readonly string $tabulka = 'migrace'
    ) {
        if (preg_match('/^[a-z0-9_]+$/', $tabulka) !== 1) {
            throw new InvalidArgumentException('Název tabulky smí mít jen malá písmena, číslice a podtržítko.');
        }
    }

    /** Migrace, které v téhle databázi ještě neběžely. */
    public function nespustene(): array
    {
        $this->zalozTabulku();

        $hotove = $this->pdo->query('SELECT soubor FROM ' . $this->tabulka)->fetchAll(PDO::FETCH_COLUMN);
        $hotove = array_flip(array_map('strval', $hotove));

        $ceka = [];
        foreach ($this->vsechny() as $soubor) {
            if (!isset($hotove[$soubor])) {
                $ceka[] = $soubor;
            }
        }

        return $ceka;
    }

    /**
     * Pustí všechny nespuštěné migrace. Vrací řádky k vypsání.
     *
     * Při chybě skončí výjimkou: nasazení má spadnout hlasitě, ne pokračovat
     * s databází, která neodpovídá kódu.
     */
    public function spust(): array
    {
        $this->zalozTabulku();

        $ceka = $this->nespustene();
        if ($ceka === []) {
            return ['Žádná nová migrace, databáze je aktuální.'];
        }

        // Zámek: dvě nasazení naráz by jinak pustila tutéž migraci dvakrát.
        $zamek = $this->pdo->query("SELECT GET_LOCK('" . $this->tabulka . "', 30)")->fetchColumn();
        if ((int) $zamek !== 1) {
            throw new RuntimeException('Migrace už běží jinde, zkus to za chvíli.');
        }

        $zprava = [];
        try {
            // Seznam se načítá znovu: mezitím mohlo doběhnout jiné nasazení.
            foreach ($this->nespustene() as $soubor) {
                $zacatek = microtime(true);
                $cesta = $this->slozka . '/' . $soubor;
                $sql = (string) file_get_contents($cesta);

                foreach ($this->prikazy($sql) as $poradi => $prikaz) {
                    try {
                        $this->pdo->exec($prikaz);
                    } catch (PDOException $e) {
                        throw new RuntimeException(sprintf(
                            "Migrace %s spadla na %d. příkazu: %s\n%s",
                            $soubor,
                            $poradi + 1,
                            $e->getMessage(),
                            mb_substr(trim($prikaz), 0, 200)
                        ), 0, $e);
                    }
                }

                $trvani = (int) round((microtime(true) - $zacatek) * 1000);
                $zapis = $this->pdo->prepare(
                    'INSERT INTO ' . $this->tabulka . ' (soubor, otisk, trvani_ms) VALUES (?, ?, ?)'
                );
                $zapis->execute([$soubor, hash('sha256', $sql), $trvani]);

                $zprava[] = sprintf('Spuštěno: %s (%d ms)', $soubor, $trvani);
            }
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('" . $this->tabulka . "')");
        }

        return $zprava;
    }

    /** Všechny migrace ve složce, seřazené podle názvu (tedy podle data). */
    private function vsechny(): array
    {
        if (!is_dir($this->slozka)) {
            throw new RuntimeException('Složka s migracemi neexistuje: ' . $this->slozka);
        }

        $soubory = [];
        foreach (scandir($this->slozka) ?: [] as $polozka) {
            if (str_ends_with($polozka, '.sql') && is_file($this->slozka . '/' . $polozka)) {
                $soubory[] = $polozka;
            }
        }
        sort($soubory);

        return $soubory;
    }

    /** Tabulka o tom, co už proběhlo. Vzniká sama, aby se na ni nezapomnělo. */
    private function zalozTabulku(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ' . $this->tabulka . ' ('
            . ' soubor VARCHAR(190) NOT NULL PRIMARY KEY,'
            . ' otisk CHAR(64) NOT NULL,'
            . ' spusteno_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . ' trvani_ms INT NOT NULL DEFAULT 0'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * Rozdělí soubor na jednotlivé příkazy. Středník uvnitř řetězce ani
     * v komentáři příkaz nekončí, jinak by se migrace rozpadla uprostřed.
     *
     * @return array<int, string>
     */
    private function prikazy(string $sql): array
    {
        $prikazy = [];
        $aktualni = '';
        $delka = strlen($sql);
        $uvozovka = null;
        $komentar = null;

        for ($i = 0; $i < $delka; $i++) {
            $znak = $sql[$i];
            $dalsi = $i + 1 < $delka ? $sql[$i + 1] : '';

            if ($komentar !== null) {
                $aktualni .= $znak;
                if ($komentar === 'radkovy' && $znak === "\n") {
                    $komentar = null;
                } elseif ($komentar === 'blokovy' && $znak === '*' && $dalsi === '/') {
                    $aktualni .= $dalsi;
                    $i++;
                    $komentar = null;
                }
                continue;
            }

            if ($uvozovka !== null) {
                $aktualni .= $znak;
                if ($znak === '\\' && $dalsi !== '') {
                    $aktualni .= $dalsi;
                    $i++;
                } elseif ($znak === $uvozovka) {
                    $uvozovka = null;
                }
                continue;
            }

            if ($znak === '-' && $dalsi === '-') {
                $komentar = 'radkovy';
                $aktualni .= $znak;
                continue;
            }
            if ($znak === '#') {
                $komentar = 'radkovy';
                $aktualni .= $znak;
                continue;
            }
            if ($znak === '/' && $dalsi === '*') {
                $komentar = 'blokovy';
                $aktualni .= $znak;
                continue;
            }
            if ($znak === "'" || $znak === '"' || $znak === '`') {
                $uvozovka = $znak;
                $aktualni .= $znak;
                continue;
            }
            if ($znak === ';') {
                $prikazy[] = $aktualni;
                $aktualni = '';
                continue;
            }

            $aktualni .= $znak;
        }

        $prikazy[] = $aktualni;

        // Komentáře zůstávají v příkazu, MySQL je snese. Zahazuje se jen kus,
        // ve kterém po odečtení komentářů nic nezbude: úvodní komentář souboru
        // se totiž slepí s prvním příkazem a bez tohohle rozlišení by celý
        // první příkaz vypadl.
        return array_values(array_filter(
            array_map('trim', $prikazy),
            static fn (string $p): bool => self::bezKomentaru($p) !== ''
        ));
    }

    /** Text příkazu bez komentářů. Slouží jen k rozhodnutí, jestli něco zbylo. */
    private static function bezKomentaru(string $prikaz): string
    {
        $bez = preg_replace('/\/\*.*?\*\//s', ' ', $prikaz) ?? $prikaz;
        $bez = preg_replace('/(^|\s)(--\s.*|#.*)$/m', ' ', $bez) ?? $bez;

        return trim($bez);
    }
}
