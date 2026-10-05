#!/usr/bin/env php
<?php

declare(strict_types=1);

use Example\Auth\ApiKeyService;
use Example\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';

$directory = dirname(__DIR__) . '/var';
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    throw new RuntimeException('Unable to create var directory.');
}

$path = $directory . '/sample.sqlite';
if (is_file($path)) {
    fwrite(STDERR, "Database already exists at {$path}. Remove it to rebuild the sample.\n");
    exit(1);
}

$pdo = Connection::sqlite($path);
Connection::migrate($pdo);
$pdo->exec("INSERT INTO organizations (name) VALUES ('Example Co')");
$pdo->exec("INSERT INTO clients (organization_id, public_id, name) VALUES (1, 'cli_example', 'Example Client')");
$pdo->exec(
    "INSERT INTO invoice_presets
     (organization_id, public_id, title, unit_price_cents)
     VALUES (1, 'pst_consulting', 'Consulting', 15000)"
);

$key = (new ApiKeyService($pdo))->create(1, 'Local development key');

echo "Created {$path}\n\n";
echo "Development API key (shown once):\n{$key['raw_key']}\n";
