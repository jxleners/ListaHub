-- ============================================================
--  listahub.sql  –  Full Database Schema
--  Requirements covered:
--   ✅ Prepared Statements (enforced in PHP)
--   ✅ SQL Trigger (auto-update stock on sale)
--   ✅ Stored Procedure (multi-table insert for new sale)
--   ✅ Transactions (used in PHP; also inside procedure)
--   ✅ SQL View (manager dashboard joining 3+ tables)
--   ✅ Index (on frequently searched column)
--   ✅ COUNT(), SUM(), JOIN used in view & queries
-- ============================================================

CREATE DATABASE IF NOT EXISTS listahub
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE listahub;

-- ============================================================
--  TABLES
-- ============================================================

-- Users (store owners)
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(60)  NOT NULL UNIQUE,
    email      VARCHAR(120) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,               -- password_hash() output
    created_at DATETIME     NOT NULL DEFAULT NOW()
) ENGINE=InnoDB;

-- Stores (one store per user in this system)
CREATE TABLE IF NOT EXISTS stores (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    store_name VARCHAR(120) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Product categories
CREATE TABLE IF NOT EXISTS categories (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id      INT UNSIGNED NOT NULL,
    category_name VARCHAR(80)  NOT NULL,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Products / Inventory
CREATE TABLE IF NOT EXISTS products (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id       INT UNSIGNED   NOT NULL,
    category_id    INT UNSIGNED   NULL,
    product_name   VARCHAR(150)   NOT NULL,
    sku            VARCHAR(60)    NOT NULL,
    stock_quantity INT            NOT NULL DEFAULT 0,
    cost_price     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    selling_price  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    expiry_date    DATE           NULL,
    notes          TEXT           NULL,
    image_path     VARCHAR(255)   NULL,
    created_at     DATETIME       NOT NULL DEFAULT NOW(),
    updated_at     DATETIME       NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    FOREIGN KEY (store_id)   REFERENCES stores(id)     ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── INDEX: Requirement – index a frequently searched column ──────────
-- product_name is searched in every inventory query; SKU is used for lookups
CREATE INDEX idx_products_name    ON products (product_name);
CREATE INDEX idx_products_sku     ON products (sku);
CREATE INDEX idx_products_store   ON products (store_id);   -- JOIN performance

-- Sales header (one row per transaction)
CREATE TABLE IF NOT EXISTS sales (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id       INT UNSIGNED  NOT NULL,
    total_amount   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cash','gcash','credit') NOT NULL DEFAULT 'cash',
    sale_date      DATETIME      NOT NULL DEFAULT NOW(),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sale line items
CREATE TABLE IF NOT EXISTS sale_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id     INT UNSIGNED  NOT NULL,
    product_id  INT UNSIGNED  NOT NULL,
    quantity    INT           NOT NULL,
    unit_price  DECIMAL(10,2) NOT NULL,
    subtotal    DECIMAL(10,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    FOREIGN KEY (sale_id)    REFERENCES sales(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Customer credit / utang
CREATE TABLE IF NOT EXISTS customers (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id      INT UNSIGNED  NOT NULL,
    customer_name VARCHAR(120)  NOT NULL,
    created_at    DATETIME      NOT NULL DEFAULT NOW(),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_credits (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT UNSIGNED  NOT NULL,
    amount_owed     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    settlement_date DATE          NULL,
    status          ENUM('pending','settled','overdue') NOT NULL DEFAULT 'pending',
    created_at      DATETIME      NOT NULL DEFAULT NOW(),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Restock log
CREATE TABLE IF NOT EXISTS restock_log (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id    INT UNSIGNED NOT NULL,
    quantity_added INT         NOT NULL,
    restock_date  DATETIME     NOT NULL DEFAULT NOW(),
    notes         TEXT         NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
--  TRIGGER
--  Requirement: automatically update stock when a sale item
--  is inserted (i.e., when an order is placed).
-- ============================================================
DELIMITER $$

CREATE TRIGGER trg_reduce_stock_after_sale
AFTER INSERT ON sale_items
FOR EACH ROW
BEGIN
    UPDATE products
    SET    stock_quantity = stock_quantity - NEW.quantity
    WHERE  id = NEW.product_id;
END$$

-- Trigger: restore stock if a sale item is deleted (refund/cancel)
CREATE TRIGGER trg_restore_stock_after_delete
AFTER DELETE ON sale_items
FOR EACH ROW
BEGIN
    UPDATE products
    SET    stock_quantity = stock_quantity + OLD.quantity
    WHERE  id = OLD.product_id;
END$$

DELIMITER ;


-- ============================================================
--  STORED PROCEDURE
--  Requirement: handle complex multi-table insert.
--  This procedure creates a full sale (header + line items)
--  inside a Transaction with ROLLBACK on any error.
-- ============================================================
DELIMITER $$

CREATE PROCEDURE sp_create_sale(
    IN  p_store_id       INT UNSIGNED,
    IN  p_payment_method VARCHAR(10),
    IN  p_product_ids    TEXT,        -- comma-separated  e.g. "3,7,12"
    IN  p_quantities     TEXT,        -- comma-separated  e.g. "2,1,5"
    OUT p_sale_id        INT,
    OUT p_message        VARCHAR(255)
)
-- FIX: label the BEGIN block as "proc" so LEAVE proc; works correctly
-- Without the label, MySQL throws #1308 - LEAVE with no matching label
proc: BEGIN
    DECLARE v_sale_id      INT DEFAULT 0;
    DECLARE v_total        DECIMAL(10,2) DEFAULT 0.00;
    DECLARE v_price        DECIMAL(10,2);
    DECLARE v_stock        INT;
    DECLARE v_product_id   INT;
    DECLARE v_qty          INT;
    DECLARE v_index        INT DEFAULT 1;
    DECLARE v_count        INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_sale_id = 0;
        SET p_message = 'Sale failed: database error occurred.';
    END;

    -- Count how many products were passed
    SET v_count = 1 + LENGTH(p_product_ids) - LENGTH(REPLACE(p_product_ids, ',', ''));

    START TRANSACTION;

    -- Insert the sale header with a placeholder total
    INSERT INTO sales (store_id, total_amount, payment_method, sale_date)
    VALUES (p_store_id, 0.00, p_payment_method, NOW());

    SET v_sale_id = LAST_INSERT_ID();

    -- Loop through each product
    WHILE v_index <= v_count DO

        SET v_product_id = CAST(
            TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(p_product_ids, ',', v_index), ',', -1))
            AS UNSIGNED
        );
        SET v_qty = CAST(
            TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(p_quantities,  ',', v_index), ',', -1))
            AS UNSIGNED
        );

        -- Read current price and stock (lock row for update)
        SELECT selling_price, stock_quantity
        INTO   v_price, v_stock
        FROM   products
        WHERE  id = v_product_id
        FOR UPDATE;

        -- Abort if insufficient stock — ROLLBACK and exit cleanly
        IF v_stock < v_qty THEN
            ROLLBACK;
            SET p_sale_id = 0;
            SET p_message = CONCAT('Insufficient stock for product ID ', v_product_id);
            LEAVE proc; -- exit the labeled BEGIN block
        END IF;

        -- Insert line item (trigger will deduct stock automatically)
        INSERT INTO sale_items (sale_id, product_id, quantity, unit_price)
        VALUES (v_sale_id, v_product_id, v_qty, v_price);

        SET v_total = v_total + (v_price * v_qty);
        SET v_index = v_index + 1;
    END WHILE;

    -- Update the sale header with the real total
    UPDATE sales SET total_amount = v_total WHERE id = v_sale_id;

    COMMIT;

    SET p_sale_id = v_sale_id;
    SET p_message = 'Sale created successfully.';

END proc$$

DELIMITER ;


-- ============================================================
--  SQL VIEW  (Manager Dashboard)
--  Requirement: join 3+ tables, use COUNT() SUM() for reporting
-- ============================================================
CREATE OR REPLACE VIEW vw_manager_dashboard AS
SELECT
    s.id                                        AS store_id,
    s.store_name,
    u.username                                  AS owner,

    -- Inventory stats
    COUNT(DISTINCT p.id)                        AS total_products,
    SUM(p.stock_quantity)                       AS total_stock_units,
    SUM(p.stock_quantity * p.cost_price)        AS total_cost_value,
    SUM(p.stock_quantity * p.selling_price)     AS total_retail_value,

    -- Low / out-of-stock counts
    SUM(CASE WHEN p.stock_quantity = 0  THEN 1 ELSE 0 END) AS out_of_stock_count,
    SUM(CASE WHEN p.stock_quantity > 0
              AND p.stock_quantity <= 5 THEN 1 ELSE 0 END) AS low_stock_count,

    -- Expiry alerts
    SUM(CASE WHEN p.expiry_date < CURDATE()                          THEN 1 ELSE 0 END) AS expired_count,
    SUM(CASE WHEN p.expiry_date BETWEEN CURDATE()
                                    AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                                                                     THEN 1 ELSE 0 END) AS near_expiry_count,

    -- Sales stats
    COUNT(DISTINCT sl.id)                       AS total_transactions,
    COALESCE(SUM(sl.total_amount), 0)           AS total_revenue,
    COALESCE(SUM(CASE WHEN sl.payment_method = 'cash'   THEN sl.total_amount END), 0) AS cash_revenue,
    COALESCE(SUM(CASE WHEN sl.payment_method = 'gcash'  THEN sl.total_amount END), 0) AS gcash_revenue,

    -- Today's revenue
    COALESCE(SUM(CASE WHEN DATE(sl.sale_date) = CURDATE() THEN sl.total_amount END), 0) AS todays_revenue,

    -- Customer credit
    COALESCE(SUM(cc.amount_owed), 0)            AS total_credit_outstanding

FROM stores       s
JOIN users        u  ON u.id  = s.user_id
LEFT JOIN products        p  ON p.store_id   = s.id
LEFT JOIN sales           sl ON sl.store_id  = s.id
LEFT JOIN customers       c  ON c.store_id   = s.id
LEFT JOIN customer_credits cc ON cc.customer_id = c.id
                              AND cc.status = 'pending'
GROUP BY s.id, s.store_name, u.username;