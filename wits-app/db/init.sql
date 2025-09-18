-- init SQL (WITS)
CREATE DATABASE IF NOT EXISTS wits
  CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE wits;

-- Table products
CREATE TABLE IF NOT EXISTS products (
                                        product_id          INT AUTO_INCREMENT PRIMARY KEY,
                                        product_name        VARCHAR(255)      NOT NULL,
    product_brand       VARCHAR(100)      NULL,
    product_price       DECIMAL(10,2)     NULL,
    product_quantity    INT               NOT NULL DEFAULT 0,
    product_category    VARCHAR(100)      NULL,            -- <— ajoutée car utilisée par l’API et les seeds
    product_description TEXT              NULL,
    product_location    VARCHAR(120)      NULL,
    product_available TINYINT(1)        NOT NULL DEFAULT 1,
    created_at          TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_name  (product_name),
    INDEX idx_products_brand (product_brand)
    ) ENGINE=InnoDB;

-- Table movements
CREATE TABLE IF NOT EXISTS movements (
                                         movement_id       INT AUTO_INCREMENT PRIMARY KEY,
                                         product_id        INT               NOT NULL,
                                         movement_type     ENUM('IN','OUT')  NOT NULL,
    movement_quantity INT               NOT NULL,
    movement_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,  -- <— standardisé: movement_at
    movement_note     VARCHAR(255)      NULL,

    CONSTRAINT fk_mov_product
    FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

    CHECK (movement_quantity > 0),
    INDEX idx_movements_product_time (product_id, movement_at)
    ) ENGINE=InnoDB;

-- Seeds products (utiliser NULL quand pas de valeur)
INSERT INTO products
(product_name, product_brand, product_price, product_quantity, product_category, product_description, product_location, created_at, updated_at)
VALUES
    ('Hazelnut cookie', NULL, NULL, 20, 'food',      NULL, NULL, '2025-09-02 14:00:00', '2025-09-02 14:00:00'),
    ('Socks',           NULL, NULL,  4, 'clothes',   NULL, NULL, '2025-09-02 14:00:00', '2025-09-02 14:00:00'),
    ('Chair',           NULL, NULL, 12, 'furniture', NULL, NULL, '2025-09-02 14:00:00', '2025-09-02 14:00:00');

-- Seeds movements (utiliser movement_at)
INSERT INTO movements (product_id, movement_type, movement_quantity, movement_at) VALUES
    (1, 'IN',  12, '2025-09-04 11:28:00'),
    (2, 'IN',  12, '2025-09-04 11:29:00'),
    (1, 'OUT',  1, '2025-09-05 08:46:00'),
    (3, 'IN',  12, '2025-09-06 17:21:00');