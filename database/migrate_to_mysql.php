<?php
// Migrate SQLite -> MySQL langsung
$sqlite = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$mysql = new PDO('mysql:host=127.0.0.1;port=3306;dbname=juki_tools;charset=utf8mb4', 'root', '');
$mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$mysql->exec("SET FOREIGN_KEY_CHECKS = 0");

foreach ($tables as $table) {
    echo "Migrating: $table... ";
    $rows = $sqlite->query("SELECT * FROM \"{$table}\"")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) { echo "0 rows\n"; continue; }

    $columns = array_keys($rows[0]);
    $colList = '`' . implode('`, `', $columns) . '`';
    $placeholders = ':' . implode(', :', $columns);

    $mysql->exec("TRUNCATE TABLE `{$table}`");
    $stmt = $mysql->prepare("INSERT INTO `{$table}` ({$colList}) VALUES ({$placeholders})");

    $count = 0;
    foreach ($rows as $row) {
        try {
            $stmt->execute($row);
            $count++;
        } catch (Exception $e) {
            echo "\n  ERROR on $table id=" . ($row['id'] ?? '?') . ": " . $e->getMessage() . "\n";
        }
    }
    echo "$count rows\n";
}

$mysql->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "Done!\n";