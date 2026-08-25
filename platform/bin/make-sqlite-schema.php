<?php
/**
 * Uretim semasi MySQL'dir. Testler SQLite ile kostugu icin ayni semanin
 * SQLite karsiligi buradan uretilir; iki sema elle guncellenip birbirinden
 * ayri dusmesin diye tek kaynak schema/mysql.sql'dir.
 *
 * Kullanim: php bin/make-sqlite-schema.php
 */
$source = __DIR__ . '/../schema/mysql.sql';
$target = __DIR__ . '/../schema/sqlite.sql';

$sql = file_get_contents($source);

// Once yorumlari at: icindeki noktali virguller ifade bolmeyi bozuyor.
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$sql = str_replace('SET NAMES utf8mb4;', '', $sql);

$replacements = [
    '/ENGINE=InnoDB DEFAULT CHARSET=utf8mb4/' => '',
    '/ENUM\([^)]*\)/'                         => 'TEXT',
    '/VARCHAR\(\d+\)|CHAR\(\d+\)/'            => 'TEXT',
    '/TINYINT\(1\)|TINYINT/'                  => 'INTEGER',
    '/BIGINT UNSIGNED|BIGINT|\bINT\b/'        => 'INTEGER',
    '/DATETIME/'                              => 'TEXT',
];
foreach ($replacements as $pattern => $replacement) {
    $sql = preg_replace($pattern, $replacement, $sql);
}

// AUTO_INCREMENT birincil anahtar SQLite'ta kolon tanimina taşınır.
$sql = preg_replace('/    id\s+INTEGER NOT NULL AUTO_INCREMENT,/', '    id INTEGER PRIMARY KEY AUTOINCREMENT,', $sql);
$sql = preg_replace('/,\s*\n\s*PRIMARY KEY \(id\)/', '', $sql);

$statements = [];
foreach (explode(';', $sql) as $statement) {
    $statement = trim($statement);
    if ($statement === '') {
        continue;
    }

    // Tablo ici KEY/UNIQUE KEY tanimlari SQLite'ta ayri CREATE INDEX olur.
    if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $statement, $m) === 1) {
        $table = $m[1];
        $indexes = [];

        $statement = preg_replace_callback(
            '/,?\s*(UNIQUE )?KEY (\w+) \(([^)]*)\)/',
            function (array $key) use ($table, &$indexes): string {
                $indexes[] = sprintf(
                    'CREATE %sINDEX IF NOT EXISTS %s ON %s (%s)',
                    $key[1] !== '' ? 'UNIQUE ' : '',
                    $key[2],
                    $table,
                    $key[3]
                );
                return '';
            },
            $statement
        );

        $statements[] = $statement;
        array_push($statements, ...$indexes);
        continue;
    }

    $statements[] = $statement;
}

$header = "-- schema/mysql.sql dosyasindan uretildi. Elle duzenlemeyin.\n"
        . "-- Yeniden uretmek icin: php bin/make-sqlite-schema.php\n\n";

file_put_contents($target, $header . implode(";\n\n", $statements) . ";\n");

echo "schema/sqlite.sql uretildi (" . count($statements) . " ifade)\n";
