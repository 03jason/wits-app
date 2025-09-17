<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ProductApiTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        // 1) Container Slim avec un PDO SQLite en mémoire pour isoler les tests
        $containerBuilder = new DI\ContainerBuilder();

        // Définition PDO -> SQLite mémoire + petit schéma de test
        $containerBuilder->addDefinitions([
            \PDO::class => function (): \PDO {
                $pdo = new \PDO('sqlite::memory:');
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->exec("
    PRAGMA foreign_keys = ON;

    CREATE TABLE products (
        product_id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_name TEXT NOT NULL,
        product_brand TEXT NULL,
        product_price REAL NULL,
        product_quantity INTEGER NOT NULL DEFAULT 0,
        product_category TEXT NULL,
        product_description TEXT NULL,
        product_location TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        deleted_at TEXT NULL
    );

    CREATE TABLE movements (
        movement_id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        movement_type TEXT NOT NULL CHECK (movement_type IN ('IN','OUT')),
        movement_quantity INTEGER NOT NULL,
        movement_at TEXT NOT NULL,
        movement_note TEXT NULL,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT ON UPDATE CASCADE
    );
");

                // Seed minimal
                $now = '2025-09-02 14:00:00';
                $stmt = $pdo->prepare("INSERT INTO products (product_name, product_quantity, product_category, created_at, updated_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute(['Seed item', 2, 'misc', $now, $now]);
                return $pdo;
            },
        ]);

        $container = $containerBuilder->build();

        // 2) Créer l'appli Slim avec ce container
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();


        // Middlewares/Settings (facultatifs pour le test minimal)
        // require __DIR__ . '/../../app/settings.php';
        // require __DIR__ . '/../../app/middleware.php';

        // 3) Charger tes routes réelles
        $routes = require __DIR__ . '/../../app/routes.php';
        $routes($app);

        $this->app = $app;
    }

    private function runApp(string $method, string $path, array $headers = [], ?string $body = null): Response
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path);
        foreach ($headers as $k => $v) {
            $request = $request->withHeader($k, $v);
        }
        if ($body !== null) {
            $request->getBody()->write($body);
            $request->getBody()->rewind();
        }
        return $this->app->handle($request);
    }

    public function testGetProductsReturns200AndJson(): void
    {
        $resp = $this->runApp('GET', '/api/products');
        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('application/json', $resp->getHeaderLine('Content-Type'));
        $data = json_decode((string)$resp->getBody(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data); // on a le seed
        $this->assertArrayHasKey('product_name', $data[0]);
    }

    public function testOutMovementTooBigReturns400(): void
    {
        // Seed a product with quantity=2 in setUp(); on tente OUT 999
        $payload = json_encode([
            'movement_type' => 'OUT',
            'product_id' => 1,
            'movement_quantity' => 999,
        ], JSON_UNESCAPED_UNICODE);

        $resp = $this->runApp('POST', '/api/movements', ['Content-Type' => 'application/json'], $payload);
        $this->assertSame(400, $resp->getStatusCode());
        $data = json_decode((string)$resp->getBody(), true);
        $this->assertSame('Insufficient stock', $data['error'] ?? null);
    }
}
