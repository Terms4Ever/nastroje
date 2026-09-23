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
 *     $slozka = Migrace::najdiSlozku(ROOT . '/db/migrace', dirname(ROOT) . '/migrace');
 *     $migrace = new Migrace($pdo, $slozka);
 *     $zprava = $migrace->spust();     // pole řádků k vypsání
 *     $migrace->nespustene();          // jen seznam, nic nespouští
 *
 * Použití z příkazové řádky (projekt si doplní připojení):
 *
 *     php db/migrace.php
 *
 * Na server se migrace nahrávají jako `nazev.sql.php` s prvním řádkem
 * `<?php exit; ?>`. Soubor .sql by web poslal jako text a šel by přečíst
 * z internetu; .php se vykoná a nevypíše nic. V repozitáři zůstává čisté .sql,
 * spouštěč zná obě podoby a do tabulky zapisuje jméno bez přípony .php, takže
 * vývoj i produkce mluví o téže migraci.
 *
 * Co vzor neumí: bloky s vlastním oddělovačem (DELIMITER), tedy těla procedur
 * a triggerů. Takovou změnu napiš jako jeden příkaz, nebo ji pusť ručně
 * a zapiš do tabulky `migrace`, ať ji spouštěč znovu nezkouší.
 */
declare(strict_types=1);

final class Migrace
{
    // Bez readonly schválně: jeden z hostingů jede na PHP 8.0, kde readonly
    // vlastnosti ještě nejsou a celý soubor by skončil chybou při načtení.
    private PDO $pdo;
    private string $slozka;
    private string $tabulka;

    public function __construct(PDO $pdo, string $slozka, string $tabulka = 'migrace')
    {
        $this->pdo = $pdo;
        $this->slozka = $slozka;
        $this->tabulka = $tabulka;

        if (preg_match('/^[a-z0-9_]+$/', $tabulka) !== 1) {
            throw new InvalidArgumentException('Název tabulky smí mít jen malá písmena, číslice a podtržítko.');
        }
    }

    /**
     * Najde složku s migracemi: první z kandidátů, která existuje.
     *
     * Ve vývoji leží v repozitáři (db/migrace), na produkci schválně mimo
     * webovou složku. Soubory .sql totiž web pošle jako text, takže by šly
     * z internetu přečíst i s tím, co je v nich napsané.
     */
    public static function najdiSlozku(string ...$kandidati): string
    {
        foreach ($kandidati as $cesta) {
            if (is_dir($cesta)) {
                return $cesta;
            }
        }

        return $kandidati[0] ?? '';
    }

    /**
     * Hotové migrace, jejichž soubor se od spuštění změnil.
     *
     * Otisk se ukládal od začátku, ale nikdy neporovnával: upravená hotová
     * migrace se tiše přeskočila a databáze na produkci se rozešla
     * s repozitářem (audit 23. 9. 2026, nastroje N32).
     *
     * @return string[]
     */
    public function zmenene(): array
    {
        $this->zalozTabulku();

        $otisky = $this->otisky();
        $zmenene = [];
        foreach ($this->vsechny() as $soubor) {
            if (isset($otisky[$soubor]) && !hash_equals($otisky[$soubor], hash('sha256', $this->obsah($soubor)))) {
                $zmenene[] = $soubor;
            }
        }

        return $zmenene;
    }

    /** @return array<string, string> soubor => otisk hotových migrací */
    private function otisky(): array
    {
        $radky = $this->pdo->query('SELECT soubor, otisk FROM ' . $this->tabulka)->fetchAll(PDO::FETCH_KEY_PAIR);
        $otisky = [];
        foreach ($radky as $soubor => $otisk) {
            $otisky[(string) $soubor] = (string) $otisk;
        }

        return $otisky;
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

        // Změněná hotová migrace se zatím jen hlásí. Zastavovat nasazení
        // začne, až se ukáže, že na produkci žádný starý nesoulad není
        // (nastroje N32).
        $zprava = [];
        foreach ($this->zmenene() as $soubor) {
            $zprava[] = 'Pozor: hotová migrace ' . $soubor . ' se od spuštění změnila (otisk nesedí).';
        }

        $ceka = $this->nespustene();
        if ($ceka === []) {
            $zprava[] = is_dir($this->slozka)
                ? 'Žádná nová migrace, databáze je aktuální.'
                : 'Složka ' . $this->slozka . ' tu není, nic se nespouští.';

            return $zprava;
        }

        // Přejmenovaná hotová migrace vypadá jako nová a pustila by se znovu:
        // CREATE TABLE by shodil nasazení, INSERT by zdvojil data. Pozná se
        // podle otisku, který zná pod starým jménem (nastroje N32).
        $hotoveOtisky = array_flip($this->otisky());
        foreach ($ceka as $soubor) {
            $otisk = hash('sha256', $this->obsah($soubor));
            if (isset($hotoveOtisky[$otisk])) {
                throw new RuntimeException(sprintf(
                    'Migrace %s má stejný obsah jako hotová %s. Vypadá jako přejmenovaná,'
                    . ' znovu se nepustí; hotová migrace si nechává název.',
                    $soubor,
                    $hotoveOtisky[$otisk]
                ));
            }
        }

        // Zámek: dvě nasazení naráz by jinak pustila tutéž migraci dvakrát.
        $zamek = $this->pdo->query("SELECT GET_LOCK('" . $this->tabulka . "', 30)")->fetchColumn();
        if ((int) $zamek !== 1) {
            throw new RuntimeException('Migrace už běží jinde, zkus to za chvíli.');
        }

        try {
            // Seznam se načítá znovu: mezitím mohlo doběhnout jiné nasazení.
            foreach ($this->nespustene() as $soubor) {
                $zacatek = microtime(true);
                $sql = $this->obsah($soubor);

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

    /**
     * Zapíše migraci jako hotovou, aniž by ji pustil.
     *
     * Na jedinou věc: zavedení standardu do projektu, kde ta změna schématu
     * na produkci dávno běží. Jinak je to podvod na sobě samém.
     */
    public function oznac(string $soubor): string
    {
        $this->zalozTabulku();

        if (!in_array($soubor, $this->vsechny(), true)) {
            throw new RuntimeException('Taková migrace ve složce není: ' . $soubor);
        }
        if (!in_array($soubor, $this->nespustene(), true)) {
            return 'Migrace ' . $soubor . ' už je zapsaná, nic se nemění.';
        }

        $sql = $this->obsah($soubor);
        $zapis = $this->pdo->prepare(
            'INSERT INTO ' . $this->tabulka . ' (soubor, otisk, trvani_ms) VALUES (?, ?, 0)'
        );
        $zapis->execute([$soubor, hash('sha256', $sql)]);

        return 'Zapsáno jako hotové bez spuštění: ' . $soubor;
    }

    /** Zámek, kterým začínají migrace nahrané na server. */
    public const ZAMEK = '<?php exit; ?>';

    /** Všechny migrace ve složce, seřazené podle názvu (tedy podle data). */
    private function vsechny(): array
    {
        // Chybějící složka není chyba: projekt ji nemusí mít, nebo se na
        // server ještě nedostala. Že se nic nespustilo, je vidět ve výpisu.
        if (!is_dir($this->slozka)) {
            return [];
        }

        $soubory = [];
        foreach (scandir($this->slozka) ?: [] as $polozka) {
            if (!is_file($this->slozka . '/' . $polozka)) {
                continue;
            }
            if (str_ends_with($polozka, '.sql')) {
                $soubory[] = $polozka;
            } elseif (str_ends_with($polozka, '.sql.php')) {
                $soubory[] = substr($polozka, 0, -4);
            }
        }
        sort($soubory);

        return $soubory;
    }

    /**
     * Obsah migrace. Na serveru má soubor příponu .sql.php a na prvním řádku
     * zámek, který se před spuštěním odřízne.
     */
    private function obsah(string $soubor): string
    {
        $cesta = $this->slozka . '/' . $soubor;
        if (!is_file($cesta)) {
            $cesta .= '.php';
        }

        $sql = (string) file_get_contents($cesta);
        if (str_starts_with(ltrim($sql), self::ZAMEK)) {
            $sql = substr(ltrim($sql), strlen(self::ZAMEK));
        }

        return $sql;
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
