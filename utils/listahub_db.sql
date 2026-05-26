-- ============================================================
--  listahub_db_fixed.sql  –  Complete Database Schema
--
--  BUGS FIXED IN THIS VERSION:
--
--  BUG 1 (CRITICAL — caused "database error" on Add Product):
--    trg_product_sku_after_insert did UPDATE Product inside an
--    AFTER INSERT trigger on the same table. MySQL raises
--    ERROR 1442 ("Can't update table already used by the
--    statement that invoked this trigger") on MySQL 5.7 and
--    many 8.0 configs. The entire INSERT was rolled back,
--    which is why every Add Product attempt failed.
--
--    FIX: Removed trg_product_sku_after_insert entirely.
--    trg_product_before_insert now reads AUTO_INCREMENT from
--    information_schema (the predicted next id) and sets the
--    SKU correctly in one pass — no second write needed.
--    A dedicated sku_seq helper table is added so the sequence
--    can be bumped atomically inside the BEFORE INSERT trigger
--    using LAST_INSERT_ID(), making SKU generation reliable
--    even under concurrent inserts without touching
--    information_schema at all.
--
--  BUG 2 (Expired product deletion not deducting from sales):
--    The original trigger fired only when status = 'Expired'
--    AND quantity > 0, which is correct. However the status
--    field is set by trg_product_status_update (BEFORE UPDATE),
--    so a product whose expiration_date passed overnight but
--    was never edited would still show its old status in the
--    DB row. The BEFORE DELETE trigger now re-evaluates expiry
--    directly from OLD.expiration_date < CURDATE() so it does
--    not depend on the status column being up-to-date.
--    Also added: the deletion of expired products now inserts
--    into both Expired_Loss_Log AND records a matching
--    Inventory_Log 'out' entry, so the loss appears
--    automatically in every report that reads either table.
--
--  BUG 3 (G-Cash not tracked separately from Cash):
--    Sale.payment_method ENUM was ('cash','credit') only.
--    G-Cash payments were either rejected or stored as 'cash',
--    making it impossible to report Online Sales vs Cash Sales
--    separately in the Sales page.
--
--    FIX: Added 'gcash' to the ENUM so the column is now
--    ENUM('cash','gcash','credit'). sp_process_cash_sale now
--    accepts p_pay_method IN parameter and passes it through
--    to the INSERT. vw_daily_sales_summary now breaks out
--    gcash_sales separately. A new vw_payment_method_summary
--    view is added for the Sales page widgets.
--
--  BUG 4 (customers.php reads wrong tables):
--    customers.php was querying a legacy schema (tables named
--    `customers` and `customer_credits`) that does not exist
--    in this database. The correct tables written to by
--    sp_process_credit_sale are Customer and Debt.
--    customers.php must query Customer + Sale + Debt +
--    Debt_Payment — the replacement PHP code is documented
--    in the project notes. The schema here exposes all needed
--    data through vw_customer_outstanding (unchanged) plus
--    the new vw_customer_debt_detail view added below.
--
--  ALL PREVIOUS DESIGN DECISIONS RETAINED:
--    • Sale_Item FK ON DELETE SET NULL  (preserves history)
--    • Expired_Loss_Log table           (financial write-off)
--    • category_id INT, SKU generation, LEFT JOINs in views
-- ============================================================

DROP DATABASE IF EXISTS listahub_db;

CREATE DATABASE listahub_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE listahub_db;

-- ============================================================
--  TABLES
-- ============================================================

CREATE TABLE User (
    user_id       INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)     NOT NULL UNIQUE,
    email         VARCHAR(100)    NOT NULL UNIQUE,
    password_hash VARCHAR(255)    NOT NULL,
    store_name    VARCHAR(100)    NOT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login    DATETIME        NULL
) ENGINE=InnoDB;

CREATE TABLE Category (
    category_id   INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100)    NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Pre-seed 'Uncategorized' so FK never fails on first product add
INSERT INTO Category (category_name) VALUES ('Uncategorized');

-- ============================================================
--  SKU SEQUENCE HELPER  (BUG 1 FIX)
-- ============================================================
CREATE TABLE sku_seq (
    seq_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
) ENGINE=InnoDB;

CREATE TABLE Product (
    product_id          INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    image_url           VARCHAR(255)      NULL,
    product_name        VARCHAR(100)      NOT NULL,
    sku                 VARCHAR(50)       NOT NULL DEFAULT 'PENDING',
    category_id         INT UNSIGNED      NOT NULL DEFAULT 1,
    cost_price          DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
    retail_price        DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
    quantity            INT               NOT NULL DEFAULT 0,
    low_stock_threshold SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    status              ENUM(
                            'In Stock',
                            'Low Stock',
                            'Out of Stock',
                            'Near Expiry',
                            'Expired'
                        ) NOT NULL DEFAULT 'Out of Stock',
    expiration_date     DATE              NULL,
    notes               TEXT              NULL,
    created_at          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
    user_id             INT UNSIGNED      NOT NULL,

    CONSTRAINT chk_cost_price    CHECK (cost_price    >= 0),
    CONSTRAINT chk_retail_price  CHECK (retail_price  >= 0),
    CONSTRAINT chk_quantity      CHECK (quantity      >= 0),

    CONSTRAINT fk_product_category FOREIGN KEY (category_id)
        REFERENCES Category(category_id) ON UPDATE CASCADE,
    CONSTRAINT fk_product_user FOREIGN KEY (user_id)
        REFERENCES User(user_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Inventory_Log (
    log_id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    product_id        INT UNSIGNED    NULL,
    product_name_snap VARCHAR(100)    NOT NULL DEFAULT '',
    movement_type     ENUM('in','out') NOT NULL,
    quantity_change   INT             NOT NULL,
    stock_before      INT             NOT NULL,
    stock_after       INT             NOT NULL,
    reference_type    ENUM('restock','sale','manual','expired_deletion') NOT NULL,
    reference_id      INT UNSIGNED    NULL,
    adjustment_reason ENUM(
                          'Damaged Goods',
                          'Expired Items',
                          'Stock Count Correction',
                          'Theft/Loss',
                          'Returned to Supplier',
                          'Other'
                      ) NULL,
    log_date          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_qty_change CHECK (quantity_change > 0),
    CONSTRAINT fk_invlog_product FOREIGN KEY (product_id)
        REFERENCES Product(product_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE Expired_Loss_Log (
    loss_id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED   NOT NULL,
    product_id       INT UNSIGNED   NULL,
    product_name     VARCHAR(100)   NOT NULL,
    sku              VARCHAR(50)    NOT NULL DEFAULT '',
    quantity_lost    INT            NOT NULL,
    cost_price       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    retail_price     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    total_cost_lost  DECIMAL(12,2)  GENERATED ALWAYS AS (quantity_lost * cost_price)  STORED,
    total_value_lost DECIMAL(12,2)  GENERATED ALWAYS AS (quantity_lost * retail_price) STORED,
    deleted_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes            TEXT           NULL,

    CONSTRAINT chk_qty_lost   CHECK (quantity_lost  > 0),
    CONSTRAINT chk_exp_cost   CHECK (cost_price     >= 0),
    CONSTRAINT chk_exp_retail CHECK (retail_price   >= 0),

    CONSTRAINT fk_loss_user FOREIGN KEY (user_id)
        REFERENCES User(user_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_loss_product FOREIGN KEY (product_id)
        REFERENCES Product(product_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE Restock_Transaction (
    restock_id   INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    restock_date DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_cost   DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    CONSTRAINT chk_restock_cost CHECK (total_cost >= 0)
) ENGINE=InnoDB;

CREATE TABLE Restock_Item (
    restock_item_id       INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    restock_id            INT UNSIGNED   NOT NULL,
    product_id            INT UNSIGNED   NOT NULL,
    quantity_added        INT            NOT NULL,
    cost_price_at_restock DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    expiration_date       DATE           NULL,

    CONSTRAINT chk_qty_added        CHECK (quantity_added        > 0),
    CONSTRAINT chk_cost_at_restock  CHECK (cost_price_at_restock >= 0),

    CONSTRAINT fk_ri_restock FOREIGN KEY (restock_id)
        REFERENCES Restock_Transaction(restock_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ri_product FOREIGN KEY (product_id)
        REFERENCES Product(product_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Customer (
    customer_id       INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    customer_name     VARCHAR(100)   NOT NULL,
    contact_number    VARCHAR(20)    NOT NULL,
    address           TEXT           NOT NULL,
    total_outstanding DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    created_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_outstanding CHECK (total_outstanding >= 0)
) ENGINE=InnoDB;

-- ============================================================
--  1NF MIGRATION: separate contact_number and address columns
--  Run this block ONCE on an existing database to split the
--  old combined "address || phone" value stored in contact_number
--  into the proper separate columns.
-- ============================================================
-- ALTER TABLE Customer
--     MODIFY COLUMN contact_number VARCHAR(20) NOT NULL DEFAULT '',
--     MODIFY COLUMN address        TEXT        NOT NULL;
--
-- UPDATE Customer
-- SET
--     address        = TRIM(SUBSTRING_INDEX(contact_number, '||', 1)),
--     contact_number = TRIM(SUBSTRING_INDEX(contact_number, '||', -1))
-- WHERE contact_number LIKE '%||%';

-- ============================================================
--  Sale — BUG 3 FIX:
--  payment_method ENUM now includes 'gcash' so G-Cash
--  transactions are stored separately from plain 'cash'.
--  This allows the Sales page to display:
--    • Cash Sales  (payment_method = 'cash')
--    • Online Sales / G-Cash  (payment_method = 'gcash')
--    • Credit / Utang  (payment_method = 'credit')
-- ============================================================
CREATE TABLE Sale (
    sale_id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    customer_id      INT UNSIGNED   NULL,
    payment_method   ENUM('cash','gcash','credit') NOT NULL,  -- BUG 3 FIX: added 'gcash'
    total_amount     DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    amount_tendered  DECIMAL(12,2)  NULL,
    change_given     DECIMAL(12,2)  NULL,
    total_cost       DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    profit           DECIMAL(12,2)  GENERATED ALWAYS AS (total_amount - total_cost) STORED,
    sale_date        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_sale_total    CHECK (total_amount    >= 0),
    CONSTRAINT chk_sale_tendered CHECK (amount_tendered >= 0),
    CONSTRAINT chk_sale_change   CHECK (change_given    >= 0),
    CONSTRAINT chk_sale_cost     CHECK (total_cost      >= 0),

    CONSTRAINT fk_sale_customer FOREIGN KEY (customer_id)
        REFERENCES Customer(customer_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE Sale_Item (
    sale_item_id       INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    sale_id            INT UNSIGNED   NOT NULL,
    product_id         INT UNSIGNED   NULL,
    product_name_snap  VARCHAR(100)   NOT NULL DEFAULT '',
    sku_snap           VARCHAR(50)    NOT NULL DEFAULT '',
    quantity_sold      INT            NOT NULL,
    unit_price_at_sale DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    unit_cost_at_sale  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    subtotal           DECIMAL(12,2)  GENERATED ALWAYS AS (unit_price_at_sale * quantity_sold) STORED,

    CONSTRAINT chk_qty_sold    CHECK (quantity_sold      > 0),
    CONSTRAINT chk_unit_price  CHECK (unit_price_at_sale >= 0),
    CONSTRAINT chk_unit_cost   CHECK (unit_cost_at_sale  >= 0),

    CONSTRAINT fk_si_sale    FOREIGN KEY (sale_id)
        REFERENCES Sale(sale_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_si_product FOREIGN KEY (product_id)
        REFERENCES Product(product_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE Debt (
    debt_id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    sale_id           INT UNSIGNED   NOT NULL UNIQUE,
    original_amount   DECIMAL(12,2)  NOT NULL,
    remaining_balance DECIMAL(12,2)  NOT NULL,
    settlement_date   DATE           NULL,
    status            ENUM('Unpaid','Partially Paid','Fully Paid') NOT NULL DEFAULT 'Unpaid',
    created_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_debt_orig    CHECK (original_amount   > 0),
    CONSTRAINT chk_debt_balance CHECK (remaining_balance >= 0),

    CONSTRAINT fk_debt_sale FOREIGN KEY (sale_id)
        REFERENCES Sale(sale_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Debt_Payment (
    payment_id   INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    debt_id      INT UNSIGNED   NOT NULL,
    payment_date DATE           NOT NULL DEFAULT (CURRENT_DATE),
    amount_paid  DECIMAL(12,2)  NOT NULL,

    CONSTRAINT chk_payment_amt CHECK (amount_paid > 0),

    CONSTRAINT fk_dp_debt FOREIGN KEY (debt_id)
        REFERENCES Debt(debt_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
--  INDEXES
-- ============================================================

CREATE INDEX idx_product_sku         ON Product(sku);
CREATE INDEX idx_product_category    ON Product(category_id);
CREATE INDEX idx_product_status      ON Product(status);
CREATE INDEX idx_product_user        ON Product(user_id);
CREATE INDEX idx_invlog_product_date ON Inventory_Log(product_id, log_date);
CREATE INDEX idx_sale_customer       ON Sale(customer_id);
CREATE INDEX idx_sale_date           ON Sale(sale_date);
CREATE INDEX idx_sale_payment        ON Sale(payment_method);   -- BUG 3 FIX: index for payment filter
CREATE INDEX idx_debtpayment_debt    ON Debt_Payment(debt_id);
CREATE INDEX idx_loss_user           ON Expired_Loss_Log(user_id);
CREATE INDEX idx_loss_deleted_at     ON Expired_Loss_Log(deleted_at);


-- ============================================================
--  TRIGGERS
-- ============================================================

DELIMITER $$

-- -----------------------------------------------------------
--  TRIGGER 0 — BEFORE INSERT on Sale_Item:
--  Snapshot product name + SKU.
-- -----------------------------------------------------------
CREATE TRIGGER trg_sale_item_before_insert
BEFORE INSERT ON Sale_Item
FOR EACH ROW
BEGIN
    DECLARE v_name VARCHAR(100);
    DECLARE v_sku  VARCHAR(50);

    IF NEW.product_id IS NOT NULL THEN
        SELECT product_name, sku
          INTO v_name, v_sku
          FROM Product
         WHERE product_id = NEW.product_id;

        SET NEW.product_name_snap = COALESCE(v_name, '');
        SET NEW.sku_snap          = COALESCE(v_sku,  '');
    END IF;
END$$

-- -----------------------------------------------------------
--  TRIGGER 1 — BEFORE INSERT on Product: Generate SKU + status.
-- -----------------------------------------------------------
CREATE TRIGGER trg_product_before_insert
BEFORE INSERT ON Product
FOR EACH ROW
BEGIN
    DECLARE v_seq_id   BIGINT UNSIGNED;
    DECLARE v_prefix   VARCHAR(3);

    INSERT INTO sku_seq () VALUES ();
    SET v_seq_id = LAST_INSERT_ID();

    SET v_prefix = UPPER(LEFT(REGEXP_REPLACE(NEW.product_name, '[^A-Za-z]', ''), 3));
    IF LENGTH(v_prefix) = 0 THEN SET v_prefix = 'PRD'; END IF;

    SET NEW.sku = CONCAT(v_prefix, LPAD(v_seq_id, 6, '0'));

    IF NEW.quantity = 0 THEN
        SET NEW.status = 'Out of Stock';
    ELSEIF NEW.expiration_date IS NOT NULL AND NEW.expiration_date < CURDATE() THEN
        SET NEW.status = 'Expired';
    ELSEIF NEW.expiration_date IS NOT NULL
           AND NEW.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN
        SET NEW.status = 'Near Expiry';
    ELSEIF NEW.quantity <= NEW.low_stock_threshold THEN
        SET NEW.status = 'Low Stock';
    ELSE
        SET NEW.status = 'In Stock';
    END IF;
END$$

-- -----------------------------------------------------------
--  TRIGGER 2 — BEFORE UPDATE on Product: Recompute status.
-- -----------------------------------------------------------
CREATE TRIGGER trg_product_status_update
BEFORE UPDATE ON Product
FOR EACH ROW
BEGIN
    IF NEW.quantity = 0 THEN
        SET NEW.status = 'Out of Stock';
    ELSEIF NEW.expiration_date IS NOT NULL AND NEW.expiration_date < CURDATE() THEN
        SET NEW.status = 'Expired';
    ELSEIF NEW.expiration_date IS NOT NULL
           AND NEW.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN
        SET NEW.status = 'Near Expiry';
    ELSEIF NEW.quantity <= NEW.low_stock_threshold THEN
        SET NEW.status = 'Low Stock';
    ELSE
        SET NEW.status = 'In Stock';
    END IF;
END$$

-- -----------------------------------------------------------
--  TRIGGER 3 — BEFORE DELETE on Product: Log expired losses.
-- -----------------------------------------------------------
CREATE TRIGGER trg_product_before_delete
BEFORE DELETE ON Product
FOR EACH ROW
BEGIN
    DECLARE v_is_expired TINYINT DEFAULT 0;

    IF OLD.expiration_date IS NOT NULL AND OLD.expiration_date < CURDATE() THEN
        SET v_is_expired = 1;
    END IF;

    IF OLD.status = 'Expired' THEN
        SET v_is_expired = 1;
    END IF;

    IF v_is_expired = 1 AND OLD.quantity > 0 THEN

        INSERT INTO Expired_Loss_Log
            (user_id, product_id, product_name, sku,
             quantity_lost, cost_price, retail_price, notes)
        VALUES
            (OLD.user_id, OLD.product_id, OLD.product_name, OLD.sku,
             OLD.quantity, OLD.cost_price, OLD.retail_price, OLD.notes);

        INSERT INTO Inventory_Log
            (product_id, product_name_snap, movement_type, quantity_change,
             stock_before, stock_after, reference_type, adjustment_reason)
        VALUES
            (OLD.product_id, OLD.product_name, 'out', OLD.quantity,
             OLD.quantity, 0, 'expired_deletion', 'Expired Items');

    END IF;
END$$

-- -----------------------------------------------------------
--  TRIGGER 4 — AFTER INSERT on Sale_Item: Deduct stock.
-- -----------------------------------------------------------
CREATE TRIGGER trg_sale_item_deduct_stock
AFTER INSERT ON Sale_Item
FOR EACH ROW
BEGIN
    DECLARE v_stock_before INT;

    IF NEW.product_id IS NOT NULL THEN
        SELECT quantity INTO v_stock_before
          FROM Product
         WHERE product_id = NEW.product_id;

        UPDATE Product
           SET quantity = quantity - NEW.quantity_sold
         WHERE product_id = NEW.product_id;

        INSERT INTO Inventory_Log
            (product_id, product_name_snap, movement_type, quantity_change,
             stock_before, stock_after, reference_type, reference_id)
        VALUES
            (NEW.product_id, NEW.product_name_snap, 'out', NEW.quantity_sold,
             v_stock_before, v_stock_before - NEW.quantity_sold,
             'sale', NEW.sale_id);
    END IF;
END$$

-- -----------------------------------------------------------
--  TRIGGER 5 — AFTER INSERT on Restock_Item: Add stock.
-- -----------------------------------------------------------
CREATE TRIGGER trg_restock_item_add_stock
AFTER INSERT ON Restock_Item
FOR EACH ROW
BEGIN
    DECLARE v_stock_before INT;
    DECLARE v_pname        VARCHAR(100);

    SELECT quantity, product_name INTO v_stock_before, v_pname
      FROM Product
     WHERE product_id = NEW.product_id;

    UPDATE Product
       SET quantity = quantity + NEW.quantity_added
     WHERE product_id = NEW.product_id;

    INSERT INTO Inventory_Log
        (product_id, product_name_snap, movement_type, quantity_change,
         stock_before, stock_after, reference_type, reference_id)
    VALUES
        (NEW.product_id, COALESCE(v_pname,''), 'in', NEW.quantity_added,
         v_stock_before, v_stock_before + NEW.quantity_added,
         'restock', NEW.restock_item_id);
END$$

-- -----------------------------------------------------------
--  TRIGGER 6 — AFTER INSERT on Debt_Payment:
--  Update debt balance + status; update customer outstanding.
-- -----------------------------------------------------------
CREATE TRIGGER trg_debt_payment_after_insert
AFTER INSERT ON Debt_Payment
FOR EACH ROW
BEGIN
    DECLARE v_remaining   DECIMAL(12,2);
    DECLARE v_customer_id INT UNSIGNED;

    UPDATE Debt
       SET remaining_balance = remaining_balance - NEW.amount_paid
     WHERE debt_id = NEW.debt_id;

    SELECT remaining_balance INTO v_remaining
      FROM Debt
     WHERE debt_id = NEW.debt_id;

    IF v_remaining <= 0 THEN
        UPDATE Debt
           SET status            = 'Fully Paid',
               remaining_balance = 0,
               settlement_date   = NEW.payment_date
         WHERE debt_id = NEW.debt_id;

        SELECT s.customer_id INTO v_customer_id
          FROM Debt d
          JOIN Sale s ON s.sale_id = d.sale_id
         WHERE d.debt_id = NEW.debt_id;

        IF v_customer_id IS NOT NULL THEN
            UPDATE Customer
               SET total_outstanding = GREATEST(total_outstanding - NEW.amount_paid, 0)
             WHERE customer_id = v_customer_id;
        END IF;
    ELSE
        UPDATE Debt
           SET status = 'Partially Paid'
         WHERE debt_id = NEW.debt_id AND status = 'Unpaid';
    END IF;
END$$

DELIMITER ;


-- ============================================================
--  STORED PROCEDURES
-- ============================================================

DELIMITER $$

-- -----------------------------------------------------------
--  SP 1 — Process a Cash or G-Cash Sale
--
--  BUG 3 FIX: Added p_pay_method IN parameter.
--  The caller (process_sale.php) passes 'cash' or 'gcash'.
--  The value is validated in PHP before reaching here, but
--  the SP also defaults to 'cash' if something unexpected
--  slips through, since the ENUM enforces valid values.
-- -----------------------------------------------------------
CREATE PROCEDURE sp_process_cash_sale(
    IN  p_user_id     INT UNSIGNED,
    IN  p_product_ids TEXT,
    IN  p_quantities  TEXT,
    IN  p_tendered    DECIMAL(12,2),
    IN  p_pay_method  VARCHAR(10),       -- BUG 3 FIX: 'cash' or 'gcash'
    OUT p_sale_id     INT UNSIGNED,
    OUT p_message     VARCHAR(255)
)
proc: BEGIN
    DECLARE v_count      INT DEFAULT 1;
    DECLARE v_index      INT DEFAULT 1;
    DECLARE v_product_id INT UNSIGNED;
    DECLARE v_qty        INT;
    DECLARE v_retail     DECIMAL(10,2);
    DECLARE v_cost       DECIMAL(10,2);
    DECLARE v_stock      INT;
    DECLARE v_total      DECIMAL(12,2) DEFAULT 0.00;
    DECLARE v_tot_cost   DECIMAL(12,2) DEFAULT 0.00;
    DECLARE v_sale_id    INT UNSIGNED;
    DECLARE v_method     VARCHAR(10);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_sale_id = 0;
        SET p_message = 'Sale failed: a database error occurred.';
    END;

    -- Sanitise pay_method — only allow 'cash' or 'gcash'
    SET v_method = IF(p_pay_method = 'gcash', 'gcash', 'cash');

    SET v_count = 1 + LENGTH(p_product_ids) - LENGTH(REPLACE(p_product_ids, ',', ''));

    START TRANSACTION;

        INSERT INTO Sale
            (customer_id, payment_method, total_amount,
             amount_tendered, change_given, total_cost, sale_date)
        VALUES
            (NULL, v_method, 0.00, p_tendered, 0.00, 0.00, NOW());

        SET v_sale_id = LAST_INSERT_ID();

        WHILE v_index <= v_count DO
            SET v_product_id = CAST(TRIM(SUBSTRING_INDEX(
                                    SUBSTRING_INDEX(p_product_ids, ',', v_index), ',', -1))
                                AS UNSIGNED);
            SET v_qty        = CAST(TRIM(SUBSTRING_INDEX(
                                    SUBSTRING_INDEX(p_quantities, ',', v_index), ',', -1))
                                AS UNSIGNED);

            SELECT retail_price, cost_price, quantity
              INTO v_retail, v_cost, v_stock
              FROM Product
             WHERE product_id = v_product_id AND user_id = p_user_id
               FOR UPDATE;

            IF v_retail IS NULL THEN
                ROLLBACK;
                SET p_sale_id = 0;
                SET p_message = CONCAT('Product ID ', v_product_id, ' not found.');
                LEAVE proc;
            END IF;

            IF v_stock < v_qty THEN
                ROLLBACK;
                SET p_sale_id = 0;
                SET p_message = CONCAT('Insufficient stock for product ID ', v_product_id);
                LEAVE proc;
            END IF;

            INSERT INTO Sale_Item
                (sale_id, product_id, quantity_sold, unit_price_at_sale, unit_cost_at_sale)
            VALUES
                (v_sale_id, v_product_id, v_qty, v_retail, v_cost);

            SET v_total    = v_total    + (v_retail * v_qty);
            SET v_tot_cost = v_tot_cost + (v_cost   * v_qty);
            SET v_index    = v_index + 1;
        END WHILE;

        UPDATE Sale
           SET total_amount    = v_total,
               amount_tendered = p_tendered,
               change_given    = p_tendered - v_total,
               total_cost      = v_tot_cost
         WHERE sale_id = v_sale_id;

    COMMIT;

    SET p_sale_id = v_sale_id;
    SET p_message = 'Sale created successfully.';
END proc$$

-- -----------------------------------------------------------
--  SP 2 — Process a Credit / Utang Sale
--
--  Writes to: Customer, Sale (payment_method='credit'),
--             Sale_Item, Debt.
--  customers.php reads these same tables via
--  vw_customer_outstanding / vw_customer_debt_detail.
-- -----------------------------------------------------------
CREATE PROCEDURE sp_process_credit_sale(
    IN  p_user_id        INT UNSIGNED,
    IN  p_customer_name  VARCHAR(100),
    IN  p_contact_number VARCHAR(20),
    IN  p_address        TEXT,
    IN  p_product_ids    TEXT,
    IN  p_quantities     TEXT,
    OUT p_sale_id        INT UNSIGNED,
    OUT p_customer_id    INT UNSIGNED,
    OUT p_debt_id        INT UNSIGNED,
    OUT p_message        VARCHAR(255)
)
-- p_contact_number = phone number only (e.g. "09171234567")
-- p_address        = street/barangay address (stored in separate column)
proc: BEGIN
    DECLARE v_count      INT DEFAULT 1;
    DECLARE v_index      INT DEFAULT 1;
    DECLARE v_product_id INT UNSIGNED;
    DECLARE v_qty        INT;
    DECLARE v_retail     DECIMAL(10,2);
    DECLARE v_cost       DECIMAL(10,2);
    DECLARE v_stock      INT;
    DECLARE v_total      DECIMAL(12,2) DEFAULT 0.00;
    DECLARE v_tot_cost   DECIMAL(12,2) DEFAULT 0.00;
    DECLARE v_sale_id    INT UNSIGNED;
    DECLARE v_cust_id    INT UNSIGNED;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_sale_id     = 0;
        SET p_customer_id = 0;
        SET p_debt_id     = 0;
        SET p_message     = 'Credit sale failed: a database error occurred.';
    END;

    SET v_count = 1 + LENGTH(p_product_ids) - LENGTH(REPLACE(p_product_ids, ',', ''));

    START TRANSACTION;

        -- Insert customer record (1NF: contact_number and address are separate columns)
        INSERT INTO Customer (customer_name, contact_number, address, total_outstanding)
        VALUES (p_customer_name, p_contact_number, p_address, 0.00);

        SET v_cust_id = LAST_INSERT_ID();

        -- Insert the sale shell
        INSERT INTO Sale
            (customer_id, payment_method, total_amount,
             amount_tendered, change_given, total_cost, sale_date)
        VALUES
            (v_cust_id, 'credit', 0.00, NULL, NULL, 0.00, NOW());

        SET v_sale_id = LAST_INSERT_ID();

        WHILE v_index <= v_count DO
            SET v_product_id = CAST(TRIM(SUBSTRING_INDEX(
                                    SUBSTRING_INDEX(p_product_ids, ',', v_index), ',', -1))
                                AS UNSIGNED);
            SET v_qty        = CAST(TRIM(SUBSTRING_INDEX(
                                    SUBSTRING_INDEX(p_quantities, ',', v_index), ',', -1))
                                AS UNSIGNED);

            SELECT retail_price, cost_price, quantity
              INTO v_retail, v_cost, v_stock
              FROM Product
             WHERE product_id = v_product_id AND user_id = p_user_id
               FOR UPDATE;

            IF v_retail IS NULL THEN
                ROLLBACK;
                SET p_sale_id = 0; SET p_customer_id = 0; SET p_debt_id = 0;
                SET p_message = CONCAT('Product ID ', v_product_id, ' not found.');
                LEAVE proc;
            END IF;

            IF v_stock < v_qty THEN
                ROLLBACK;
                SET p_sale_id = 0; SET p_customer_id = 0; SET p_debt_id = 0;
                SET p_message = CONCAT('Insufficient stock for product ID ', v_product_id);
                LEAVE proc;
            END IF;

            INSERT INTO Sale_Item
                (sale_id, product_id, quantity_sold, unit_price_at_sale, unit_cost_at_sale)
            VALUES
                (v_sale_id, v_product_id, v_qty, v_retail, v_cost);

            SET v_total    = v_total    + (v_retail * v_qty);
            SET v_tot_cost = v_tot_cost + (v_cost   * v_qty);
            SET v_index    = v_index + 1;
        END WHILE;

        UPDATE Sale
           SET total_amount = v_total,
               total_cost   = v_tot_cost
         WHERE sale_id = v_sale_id;

        -- Update customer's outstanding balance
        UPDATE Customer
           SET total_outstanding = v_total
         WHERE customer_id = v_cust_id;

        -- Create the debt record
        INSERT INTO Debt (sale_id, original_amount, remaining_balance, status)
        VALUES (v_sale_id, v_total, v_total, 'Unpaid');

        SET p_debt_id = LAST_INSERT_ID();

    COMMIT;

    SET p_sale_id     = v_sale_id;
    SET p_customer_id = v_cust_id;
    SET p_message     = 'Credit sale created successfully.';
END proc$$

-- -----------------------------------------------------------
--  SP 3 — Manual Inventory Adjustment
-- -----------------------------------------------------------
CREATE PROCEDURE sp_manual_inventory_adjustment(
    IN  p_product_id      INT UNSIGNED,
    IN  p_quantity_change INT,
    IN  p_movement_type   ENUM('in','out'),
    IN  p_reason          ENUM(
                              'Damaged Goods',
                              'Expired Items',
                              'Stock Count Correction',
                              'Theft/Loss',
                              'Returned to Supplier',
                              'Other'
                          ),
    OUT p_message         VARCHAR(255)
)
proc: BEGIN
    DECLARE v_stock_before INT    DEFAULT NULL;
    DECLARE v_stock_after  INT    DEFAULT 0;
    DECLARE v_pname        VARCHAR(100) DEFAULT '';

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_message = 'Adjustment failed: a database error occurred.';
    END;

    START TRANSACTION;

        SELECT quantity, product_name INTO v_stock_before, v_pname
          FROM Product
         WHERE product_id = p_product_id
           FOR UPDATE;

        IF v_stock_before IS NULL THEN
            ROLLBACK;
            SET p_message = 'Product not found.';
            LEAVE proc;
        END IF;

        IF p_movement_type = 'in' THEN
            SET v_stock_after = v_stock_before + p_quantity_change;
        ELSE
            IF v_stock_before < p_quantity_change THEN
                ROLLBACK;
                SET p_message = 'Cannot subtract more than current stock.';
                LEAVE proc;
            END IF;
            SET v_stock_after = v_stock_before - p_quantity_change;
        END IF;

        UPDATE Product
           SET quantity = v_stock_after
         WHERE product_id = p_product_id;

        INSERT INTO Inventory_Log (
            product_id, product_name_snap, movement_type, quantity_change,
            stock_before, stock_after, reference_type, reference_id, adjustment_reason
        ) VALUES (
            p_product_id, v_pname, p_movement_type, p_quantity_change,
            v_stock_before, v_stock_after, 'manual', NULL, p_reason
        );

    COMMIT;

    SET p_message = 'Adjustment applied successfully.';
END proc$$

-- -----------------------------------------------------------
--  SP 4 — Record a Debt Payment
-- -----------------------------------------------------------
CREATE PROCEDURE sp_record_debt_payment(
    IN  p_debt_id      INT UNSIGNED,
    IN  p_amount_paid  DECIMAL(12,2),
    IN  p_payment_date DATE,
    OUT p_message      VARCHAR(255)
)
proc: BEGIN
    DECLARE v_remaining DECIMAL(12,2) DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_message = 'Payment failed: a database error occurred.';
    END;

    START TRANSACTION;

        SELECT remaining_balance INTO v_remaining
          FROM Debt
         WHERE debt_id = p_debt_id
           FOR UPDATE;

        IF v_remaining IS NULL THEN
            ROLLBACK;
            SET p_message = 'Debt record not found.';
            LEAVE proc;
        END IF;

        IF p_amount_paid <= 0 THEN
            ROLLBACK;
            SET p_message = 'Payment amount must be greater than zero.';
            LEAVE proc;
        END IF;

        IF p_amount_paid > v_remaining THEN
            ROLLBACK;
            SET p_message = 'Payment exceeds remaining balance.';
            LEAVE proc;
        END IF;

        INSERT INTO Debt_Payment (debt_id, payment_date, amount_paid)
        VALUES (p_debt_id, p_payment_date, p_amount_paid);

    COMMIT;

    SET p_message = 'Payment recorded successfully.';
END proc$$

DELIMITER ;


-- ============================================================
--  VIEWS
-- ============================================================

-- -----------------------------------------------------------
--  Dashboard: per-product sales summary
-- -----------------------------------------------------------
CREATE OR REPLACE VIEW vw_manager_dashboard AS
SELECT
    p.product_id,
    p.product_name,
    p.sku,
    p.user_id,
    u.store_name,
    COALESCE(c.category_name, 'Uncategorized')                          AS category_name,
    p.quantity                                                           AS current_stock,
    p.status                                                             AS stock_status,
    p.retail_price,
    p.cost_price,
    p.expiration_date,
    COALESCE(SUM(si.quantity_sold), 0)                                  AS total_units_sold,
    COALESCE(SUM(si.subtotal), 0)                                       AS total_revenue,
    COALESCE(SUM(si.quantity_sold * si.unit_cost_at_sale), 0)           AS total_cogs,
    COALESCE(SUM(si.subtotal - si.quantity_sold * si.unit_cost_at_sale), 0) AS gross_profit,
    COUNT(DISTINCT si.sale_id)                                          AS number_of_transactions
FROM Product p
JOIN  User       u  ON u.user_id     = p.user_id
LEFT JOIN Category   c  ON c.category_id = p.category_id
LEFT JOIN Sale_Item  si ON si.product_id = p.product_id
LEFT JOIN Sale        s  ON s.sale_id    = si.sale_id
GROUP BY
    p.product_id, p.product_name, p.sku, p.user_id, u.store_name,
    c.category_name, p.quantity, p.status, p.retail_price,
    p.cost_price, p.expiration_date;

-- -----------------------------------------------------------
--  Expired losses per user
-- -----------------------------------------------------------
CREATE OR REPLACE VIEW vw_expired_losses AS
SELECT
    el.user_id,
    u.store_name,
    COUNT(*)                    AS total_expired_deletions,
    SUM(el.quantity_lost)       AS total_units_lost,
    SUM(el.total_cost_lost)     AS total_cost_lost,
    SUM(el.total_value_lost)    AS total_retail_value_lost,
    MAX(el.deleted_at)          AS last_deletion_at
FROM Expired_Loss_Log el
JOIN User u ON u.user_id = el.user_id
GROUP BY el.user_id, u.store_name;

-- -----------------------------------------------------------
--  Net profit view
-- -----------------------------------------------------------
CREATE OR REPLACE VIEW vw_net_profit_summary AS
SELECT
    u.user_id,
    u.store_name,
    COALESCE(SUM(s.profit), 0)                        AS gross_profit_from_sales,
    COALESCE(el_agg.total_cost_lost, 0)               AS total_expired_cost_lost,
    COALESCE(SUM(s.profit), 0)
        - COALESCE(el_agg.total_cost_lost, 0)         AS net_profit
FROM User u
LEFT JOIN Sale s ON s.sale_id IS NOT NULL
LEFT JOIN (
    SELECT user_id, SUM(total_cost_lost) AS total_cost_lost
      FROM Expired_Loss_Log
     GROUP BY user_id
) el_agg ON el_agg.user_id = u.user_id
GROUP BY u.user_id, u.store_name, el_agg.total_cost_lost;

-- -----------------------------------------------------------
--  Customer outstanding balances
--  Used by customers.php to display the table and summary.
--  BUG 4 FIX: This view reads from the correct tables
--  (Customer, Sale, Debt, Debt_Payment) that sp_process_credit_sale
--  writes to — not the legacy `customers`/`customer_credits` tables.
-- -----------------------------------------------------------
CREATE OR REPLACE VIEW vw_customer_outstanding AS
SELECT
    cu.customer_id,
    cu.customer_name,
    cu.contact_number,
    cu.address,
    cu.total_outstanding,
    cu.created_at,
    COUNT(DISTINCT d.debt_id)                                        AS total_credit_transactions,
    COALESCE(SUM(d.original_amount), 0)                             AS total_borrowed,
    COALESCE(SUM(d.remaining_balance), 0)                           AS total_remaining,
    COALESCE(SUM(d.original_amount) - SUM(d.remaining_balance), 0) AS total_paid,
    MAX(dp.payment_date)                                            AS last_payment_date
FROM Customer cu
LEFT JOIN Sale          s  ON s.customer_id = cu.customer_id
LEFT JOIN Debt          d  ON d.sale_id     = s.sale_id
LEFT JOIN Debt_Payment dp  ON dp.debt_id    = d.debt_id
GROUP BY cu.customer_id, cu.customer_name, cu.contact_number,
         cu.address, cu.total_outstanding, cu.created_at;

-- -----------------------------------------------------------
--  Customer debt detail — one row per Debt record.
--  customers.php uses this for the per-row table display
--  (amount owed, settlement date, status per transaction).
--  BUG 4 FIX: Replaces the old customer_credits JOIN.
-- -----------------------------------------------------------
CREATE OR REPLACE VIEW vw_customer_debt_detail AS
SELECT
    cu.customer_id,
    cu.customer_name,
    cu.contact_number,
    cu.address,
    cu.created_at,
    d.debt_id,
    d.sale_id,
    d.original_amount,
    d.remaining_balance,
    d.settlement_date,
    d.status           AS debt_status,
    s.sale_date,
    s.total_amount     AS sale_total
FROM Customer cu
LEFT JOIN Sale s  ON s.customer_id = cu.customer_id
LEFT JOIN Debt d  ON d.sale_id     = s.sale_id;

-- -----------------------------------------------------------
--  Stock alerts
-- -----------------------------------------------------------
CREATE OR REPLACE VIEW vw_stock_alerts AS
SELECT
    p.product_id,
    p.sku,
    p.product_name,
    COALESCE(c.category_name, 'Uncategorized') AS category_name,
    p.quantity,
    p.low_stock_threshold,
    p.status,
    p.expiration_date,
    p.user_id
FROM Product p
LEFT JOIN Category c ON c.category_id = p.category_id
WHERE p.status IN ('Low Stock','Out of Stock','Near Expiry','Expired')
   OR (p.expiration_date IS NOT NULL AND p.expiration_date < CURDATE())
ORDER BY p.quantity ASC;

-- -----------------------------------------------------------
--  Daily sales summary — BUG 3 FIX:
--  Now breaks out cash_sales, gcash_sales, and credit_sales
--  separately. The Sales page widgets should read:
--    • cash_sales   → Cash Sales count / revenue
--    • gcash_sales  → Online Sales (G-Cash) count / revenue
--    • credit_sales → Credit / Utang count / revenue
--  The old view only had cash_sales + credit_sales; G-Cash
--  was silently counted as cash, so online revenue was wrong.
-- -----------------------------------------------------------
CREATE OR REPLACE VIEW vw_daily_sales_summary AS
SELECT
    DATE(s.sale_date)                                                    AS sale_day,
    COUNT(DISTINCT s.sale_id)                                            AS total_transactions,
    SUM(s.total_amount)                                                  AS total_revenue,
    SUM(s.total_cost)                                                    AS total_cost,
    SUM(s.profit)                                                        AS total_profit,

    -- Cash (physical money only)
    SUM(CASE WHEN s.payment_method = 'cash'   THEN 1     ELSE 0    END) AS cash_sales,
    SUM(CASE WHEN s.payment_method = 'cash'   THEN s.total_amount
                                               ELSE 0    END)           AS cash_revenue,

    -- G-Cash / Online
    SUM(CASE WHEN s.payment_method = 'gcash'  THEN 1     ELSE 0    END) AS gcash_sales,
    SUM(CASE WHEN s.payment_method = 'gcash'  THEN s.total_amount
                                               ELSE 0    END)           AS gcash_revenue,

    -- Credit / Utang
    SUM(CASE WHEN s.payment_method = 'credit' THEN 1     ELSE 0    END) AS credit_sales,
    SUM(CASE WHEN s.payment_method = 'credit' THEN s.total_amount
                                               ELSE 0    END)           AS credit_revenue

FROM Sale s
GROUP BY DATE(s.sale_date)
ORDER BY sale_day DESC;

-- -----------------------------------------------------------
--  Payment method summary (per user, all-time)
--  BUG 3 FIX: New view for the Sales page summary widgets.
--  Query this filtered by user_id from Sale → Sale_Item →
--  Product.user_id, or add user_id to Sale if needed.
--
--  NOTE: Sale does not store user_id directly; join through
--  Sale_Item → Product to get user_id, or filter via a
--  subquery. For convenience a user-scoped version is below.
-- -----------------------------------------------------------
CREATE OR REPLACE VIEW vw_payment_method_summary AS
SELECT
    p.user_id,
    s.payment_method,
    COUNT(DISTINCT s.sale_id)  AS total_transactions,
    SUM(s.total_amount)        AS total_revenue,
    SUM(s.total_cost)          AS total_cost,
    SUM(s.profit)              AS total_profit
FROM Sale s
JOIN Sale_Item si ON si.sale_id    = s.sale_id
JOIN Product   p  ON p.product_id  = si.product_id
GROUP BY p.user_id, s.payment_method;

-- -----------------------------------------------------------
--  Inventory movements
-- -----------------------------------------------------------
CREATE OR REPLACE VIEW vw_inventory_movements AS
SELECT
    il.log_id,
    il.log_date,
    il.product_id,
    COALESCE(p.user_id, 0)                         AS user_id,
    COALESCE(p.sku, il.product_name_snap)          AS sku,
    COALESCE(p.product_name, il.product_name_snap) AS product_name,
    COALESCE(c.category_name, 'Uncategorized')     AS category_name,
    il.movement_type,
    il.quantity_change,
    il.stock_before,
    il.stock_after,
    il.reference_type,
    il.reference_id,
    il.adjustment_reason
FROM Inventory_Log il
LEFT JOIN Product  p ON p.product_id  = il.product_id
LEFT JOIN Category c ON c.category_id = p.category_id
ORDER BY il.log_date DESC;

-- ============================================================
--  END OF SCHEMA
-- ============================================================