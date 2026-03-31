<?php
$backendRoot = realpath(__DIR__ . '/../../laravel-spedizionefacile-main');
$databasePath = $backendRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite';

if (!$backendRoot || !is_file($databasePath)) {
    fwrite(STDERR, "Database SQLite non trovato in: {$databasePath}\n");
    exit(1);
}

$db = new PDO('sqlite:' . $databasePath);
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='pudo_points'")->fetchAll();
echo "Tabella pudo_points: " . (count($tables) > 0 ? "ESISTE" : "NON ESISTE") . "\n";
if (count($tables) > 0) {
    $count = $db->query("SELECT COUNT(*) as cnt FROM pudo_points")->fetch();
    echo "PUDO inseriti: " . $count['cnt'] . "\n";
    if ($count['cnt'] > 0) {
        $sample = $db->query("SELECT * FROM pudo_points LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        echo "\nEsempi:\n";
        foreach ($sample as $p) {
            echo "- {$p['name']} ({$p['city']}, {$p['postal_code']})\n";
        }
    }
}
