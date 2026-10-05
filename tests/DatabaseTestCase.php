<?php

declare(strict_types=1);

namespace Example\Tests;

use Example\Auth\ApiKeyService;
use Example\Database\Connection;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;
    protected int $organizationId;
    protected int $otherOrganizationId;
    protected array $apiKey;
    protected string $rawApiKey;

    protected function setUp(): void
    {
        $this->pdo = Connection::sqlite();
        Connection::migrate($this->pdo);

        $this->pdo->exec("INSERT INTO organizations (name) VALUES ('Example Co'), ('Other Co')");
        $this->organizationId = 1;
        $this->otherOrganizationId = 2;

        $this->pdo->exec(
            "INSERT INTO clients (organization_id, public_id, name) VALUES
             (1, 'cli_example', 'Example Client'),
             (2, 'cli_other', 'Other Client')"
        );
        $this->pdo->exec(
            "INSERT INTO invoice_presets (organization_id, public_id, title, unit_price_cents) VALUES
             (1, 'pst_consulting', 'Consulting', 15000),
             (2, 'pst_other', 'Other Work', 5000)"
        );

        $created = (new ApiKeyService($this->pdo))->create($this->organizationId, 'Test key');
        $this->rawApiKey = $created['raw_key'];
        $statement = $this->pdo->prepare('SELECT * FROM api_keys WHERE id = :id');
        $statement->execute(['id' => $created['id']]);
        $this->apiKey = $statement->fetch();
    }

    protected function request(string $title = 'October consulting'): string
    {
        return json_encode([
            'client_public_id' => 'cli_example',
            'title' => $title,
            'lines' => [['preset_public_id' => 'pst_consulting', 'quantity' => 2]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
