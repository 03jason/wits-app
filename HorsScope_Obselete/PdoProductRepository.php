<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\ProductRepositoryInterface;
use PDO;
use RuntimeException;

final class PdoProductRepository implements ProductRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function getAllActive(): array
    {
        $sql = "
            SELECT product_id, product_name, product_quantity, product_category, created_at, updated_at
            FROM products
            WHERE product_available = 1
            ORDER BY product_id DESC
        ";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $name = isset($data['name']) ? trim((string)$data['name']) : '';
        $qty  = isset($data['quantity']) ? (int)$data['quantity'] : 0;
        $cat  = $data['product_category'] ?? null;
        $cat  = $cat !== null ? trim((string)$cat) : null;

        if ($name === '' || $qty < 0) {
            throw new RuntimeException('Invalid payload');
        }

        $this->pdo->beginTransaction();
        try {
            // NB: CURRENT_TIMESTAMP est portable (MySQL/PG/SQLite)
            $ins = $this->pdo->prepare("
                INSERT INTO products (product_name, product_quantity, product_category, product_available, created_at, updated_at)
                VALUES (:n, :q, :c, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $ins->execute([':n' => $name, ':q' => $qty, ':c' => $cat]);
            $id = (int)$this->pdo->lastInsertId();

            if ($qty > 0) {
                $mov = $this->pdo->prepare("
                    INSERT INTO movements (product_id, movement_type, movement_quantity, movement_at)
                    VALUES (:pid, 'IN', :q, CURRENT_TIMESTAMP)
                ");
                $mov->execute([':pid' => $id, ':q' => $qty]);
            }

            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function disable(int $productId): bool
    {
        $upd = $this->pdo->prepare("
            UPDATE products
            SET product_available = 0, updated_at = CURRENT_TIMESTAMP
            WHERE product_id = :id AND product_available = 1
        ");
        $upd->execute([':id' => $productId]);
        return $upd->rowCount() > 0;
    }
}
