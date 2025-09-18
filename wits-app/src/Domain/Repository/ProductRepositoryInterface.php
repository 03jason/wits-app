<?php
declare(strict_types=1);

namespace App\Domain\Repository;

interface ProductRepositoryInterface {
    /**
     * Retourne la liste des produits actifs (product_available = 1).
     */
    public function getAllActive(): array;

    /**
     * Crée un produit et, si quantity > 0, crée un mouvement IN automatique.
     * @param array{name:string, quantity:int, product_category?:?string} $data
     * @return int product_id créé
     */
    public function create(array $data): int;

    /**
     * Soft delete: désactive le produit (product_available = 0).
     */
    public function disable(int $productId): bool;
}
