<?php

declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });



    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello world, ça fonctionne');
        return $response;
    });

    $app->group('/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });


    $app->get('/hello', function (Request $request, Response $response) {
        $payload = json_encode(['message' => 'WITS OK'], JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    });

    # Routes vers table «products», je crois ...
    $app->get('/api/products', function (Request $req, Response $res) {
        /** @var PDO $pdo */
        $pdo = $this->get(\PDO::class);
        $rows = $pdo->query("SELECT product_id, product_name, product_quantity, product_category, created_at, updated_at FROM products ORDER BY product_id DESC")->fetchAll(\PDO::FETCH_ASSOC);
        $res->getBody()->write(json_encode($rows, JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json');
    });


    $app->post('/api/products', function (Request $req, Response $res) {
        /** @var \PDO $pdo */
        $pdo = $this->get(\PDO::class);

        $data = (array)$req->getParsedBody();
        $name   = isset($data['name']) ? trim((string)$data['name']) : '';
        $qty    = isset($data['quantity']) ? (int)$data['quantity'] : 0;
        $cat    = isset($data['product_category']) ? trim((string)$data['product_category']) : null;

        if ($name === '' || $qty < 0) {
            $res->getBody()->write(json_encode(['error' => 'Invalid payload'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(400)->withHeader('Content-Type','application/json');
        }

        try {
            $pdo->beginTransaction();

            // 1) Créer le produit
            $ins = $pdo->prepare("
            INSERT INTO products (product_name, product_quantity, product_category, created_at, updated_at)
            VALUES (:n, :q, :c, NOW(), NOW())
        ");
            $ins->execute([':n'=>$name, ':q'=>$qty, ':c'=>$cat]);
            $productId = (int)$pdo->lastInsertId();

            // 2) Historiser un IN automatique si quantité > 0
            if ($qty > 0) {
                $mov = $pdo->prepare("
                INSERT INTO movements (product_id, movement_type, movement_quantity, movement_at)
                VALUES (:pid, 'IN', :q, NOW())
            ");
                $mov->execute([':pid'=>$productId, ':q'=>$qty]);
            }

            $pdo->commit();

            $payload = [
                'product_id' => $productId,
                'name' => $name,
                'quantity' => $qty,
                'product_category' => $cat,
            ];
            $res->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $res->withHeader('Content-Type','application/json')->withStatus(201);

        } catch (\Throwable $e) {
            $pdo->rollBack();
            $res->getBody()->write(json_encode(['error'=>'Server error'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(500)->withHeader('Content-Type','application/json');
        }
    });



    $app->post('/api/movements', function (Request $req, Response $res) {
        /** @var \PDO $pdo */
        $pdo = $this->get(\PDO::class);

        $data = (array)$req->getParsedBody();
        $type = strtoupper(trim((string)($data['movement_type'] ?? '')));
        $pid  = (int)($data['product_id'] ?? 0);
        $qty  = (int)($data['movement_quantity'] ?? 0);

        if (!in_array($type, ['IN','OUT'], true) || $pid <= 0 || $qty <= 0) {
            $res->getBody()->write(json_encode(['error'=>'Invalid payload'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(400)->withHeader('Content-Type','application/json');
        }

        try {
            $pdo->beginTransaction();

            // --- Patch : adapter la requête au moteur ---
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $forUpdate = ($driver === 'mysql') ? ' FOR UPDATE' : '';

            $sel = $pdo->prepare("
            SELECT product_id, product_quantity
            FROM products
            WHERE product_id = :id{$forUpdate}
        ");
            $sel->execute([':id'=>$pid]);
            $prod = $sel->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                $pdo->rollBack();
                $res->getBody()->write(json_encode(['error'=>'Product not found'], JSON_UNESCAPED_UNICODE));
                return $res->withStatus(404)->withHeader('Content-Type','application/json');
            }

            $current = (int)$prod['product_quantity'];
            $newQty = ($type === 'IN') ? $current + $qty : $current - $qty;

            if ($type === 'OUT' && $newQty < 0) {
                $pdo->rollBack();
                $res->getBody()->write(json_encode(['error'=>'Insufficient stock'], JSON_UNESCAPED_UNICODE));
                return $res->withStatus(400)->withHeader('Content-Type','application/json');
            }

            // 1) update produit
            $upd = $pdo->prepare("
            UPDATE products
            SET product_quantity = :q, updated_at = NOW()
            WHERE product_id = :id
        ");
            $upd->execute([':q'=>$newQty, ':id'=>$pid]);

            // 2) historiser mouvement
            $mov = $pdo->prepare("
            INSERT INTO movements (product_id, movement_type, movement_quantity, movement_at)
            VALUES (:pid, :t, :q, NOW())
        ");
            $mov->execute([':pid'=>$pid, ':t'=>$type, ':q'=>$qty]);

            $pdo->commit();

            $res->getBody()->write(json_encode([
                'status' => ($type === 'IN' ? 'stock_increased' : 'stock_decreased'),
                'product_id' => $pid,
                'old_quantity' => $current,
                'new_quantity' => $newQty
            ], JSON_UNESCAPED_UNICODE));
            return $res->withHeader('Content-Type','application/json');

        } catch (\Throwable $e) {
            $pdo->rollBack();
            $res->getBody()->write(json_encode(['error'=>'Server error'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(500)->withHeader('Content-Type','application/json');
        }
    });


    $app->get('/api/movements', function (Request $req, Response $res) {
        /** @var \PDO $pdo */
        $pdo = $this->get(\PDO::class);
        $params = $req->getQueryParams();
        $pid = isset($params['product_id']) ? (int)$params['product_id'] : null;

        if ($pid) {
            $stmt = $pdo->prepare("
            SELECT movement_id, product_id, movement_type, movement_quantity, movement_at
            FROM movements
            WHERE product_id = :pid
            ORDER BY movement_id DESC
        ");
            $stmt->execute([':pid'=>$pid]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $rows = $pdo->query("
            SELECT movement_id, product_id, movement_type, movement_quantity, movement_at
            FROM movements
            ORDER BY movement_id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);
        }

        $res->getBody()->write(json_encode($rows, JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type','application/json');
    });


};



