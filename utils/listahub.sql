-- ============================================================
--  INVENTORY & SALES MANAGEMENT SYSTEM - FULL DATABASE SCHEMA
--  Includes: Tables, Triggers, Stored Procedures, Views,
--            Transactions, and Indexes
-- ============================================================

-- ============================================================
--  TABLES
-- ============================================================

CREATE TABLE User (
    user_id       INT             NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)     NOT NULL UNIQUE,
    email         VARCHAR(100)    NOT NULL UNIQUE,
    password_hash VARCHAR(255)    NOT NULL,
    store_name    VARCHAR(100)    NOT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login    DATETIME                 DEFAULT NULL,
    PRIMARY KEY (user_id)
);

-- ------------------------------------------------------------

CREATE TABLE Category (
    category_id   INT             NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100)    NOT NULL UNIQUE,
    PRIMARY KEY (category_id)
);

-- ------------------------------------------------------------

CREATE TABLE Product (
    product_id          INT             NOT NULL AUTO_INCREMENT,
    image_url           VARCHAR(500)             DEFAULT NULL,
    product_name        VARCHAR(150)    NOT NULL,
    -- SKU is auto-generated via trigger (see below)
    sku                 VARCHAR(100)    NOT NULL UNIQUE,
    category_id         INT                      DEFAULT NULL,
    cost_price          DECIMAL(10, 2)  NOT NULL CHECK (cost_price >= 0),
    retail_price        DECIMAL(10, 2)  NOT NULL CHECK (retail_price >= 0),
    quantity            INT             NOT NULL DEFAULT 0 CHECK (quantity >= 0),
    low_stock_threshold INT             NOT NULL DEFAULT 5,
    -- status is auto-managed via trigger based on quantity
    status              ENUM('In Stock', 'Low Stock', 'Out of Stock')
                                        NOT NULL DEFAULT 'Out of Stock',
    -- NULL means no expiration
    expiration_date     DATE                     DEFAULT NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                 ON UPDATE CURRENT_TIMESTAMP,
    user_id             INT             NOT NULL,
    PRIMARY KEY (product_id),
    FOREIGN KEY (category_id) REFERENCES Category(category_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES User(user_id)
        ON DELETE CASCADE  ON UPDATE CASCADE
);

-- ------------------------------------------------------------

CREATE TABLE Inventory_Log (
    log_id           INT          NOT NULL AUTO_INCREMENT,
    product_id       INT          NOT NULL,
    -- 'In' = stock added, 'Out' = stock removed
    movement_type    ENUM('In', 'Out')
                                  NOT NULL,
    quantity_change  INT          NOT NULL CHECK (quantity_change > 0),
    stock_before     INT          NOT NULL CHECK (stock_before >= 0),
    stock_after      INT          NOT NULL CHECK (stock_after  >= 0),
    -- reference_type links this log to the originating record
    reference_type   ENUM('Restock', 'Sale', 'Manual')
                                  NOT NULL,
    reference_id     INT                   DEFAULT NULL,
    log_date         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    FOREIGN KEY (product_id) REFERENCES Product(product_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ------------------------------------------------------------

CREATE TABLE Restock_Transaction (
    restock_id    INT            NOT NULL AUTO_INCREMENT,
    restock_date  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_cost    DECIMAL(10, 2) NOT NULL CHECK (total_cost >= 0),
    PRIMARY KEY (restock_id)
);

-- ------------------------------------------------------------

CREATE TABLE Restock_Item (
    restock_item_id       INT            NOT NULL AUTO_INCREMENT,
    restock_id            INT            NOT NULL,
    product_id            INT            NOT NULL,
    quantity_added        INT            NOT NULL CHECK (quantity_added > 0),
    cost_price_at_restock DECIMAL(10, 2) NOT NULL CHECK (cost_price_at_restock >= 0),
    -- NULL means no expiration for this restock batch
    expiration_date       DATE                    DEFAULT NULL,
    PRIMARY KEY (restock_item_id),
    FOREIGN KEY (restock_id)  REFERENCES Restock_Transaction(restock_id)
        ON DELETE CASCADE  ON UPDATE CASCADE,
    FOREIGN KEY (product_id)  REFERENCES Product(product_id)
        ON DELETE CASCADE  ON UPDATE CASCADE
);

-- ------------------------------------------------------------

CREATE TABLE Customer (
    customer_id        INT            NOT NULL AUTO_INCREMENT,
    customer_name      VARCHAR(100)   NOT NULL,
    contact_number     VARCHAR(20)             DEFAULT NULL,
    address            TEXT                    DEFAULT NULL,
    total_outstanding  DECIMAL(10, 2) NOT NULL DEFAULT 0.00
                                               CHECK (total_outstanding >= 0),
    created_at         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id)
);

-- ------------------------------------------------------------

CREATE TABLE Sale (
    sale_id          INT            NOT NULL AUTO_INCREMENT,
    -- NULL customer_id = walk-in cash customer
    customer_id      INT                     DEFAULT NULL,
    -- 'Credit' triggers customer form popup in application layer
    payment_method   ENUM('Cash', 'Credit')
                                    NOT NULL,
    total_amount     DECIMAL(10, 2) NOT NULL CHECK (total_amount     >= 0),
    amount_tendered  DECIMAL(10, 2)          DEFAULT NULL
                                             CHECK (amount_tendered  >= 0),
    change_given     DECIMAL(10, 2)          DEFAULT NULL
                                             CHECK (change_given     >= 0),
    total_cost       DECIMAL(10, 2) NOT NULL CHECK (total_cost       >= 0),
    profit           DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    sale_date        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sale_id),
    FOREIGN KEY (customer_id) REFERENCES Customer(customer_id)
        ON DELETE SET NULL ON UPDATE CASCADE
);

-- ------------------------------------------------------------

CREATE TABLE Sale_Item (
    sale_item_id       INT            NOT NULL AUTO_INCREMENT,
    sale_id            INT            NOT NULL,
    product_id         INT            NOT NULL,
    quantity_sold      INT            NOT NULL CHECK (quantity_sold > 0),
    unit_price_at_sale DECIMAL(10, 2) NOT NULL CHECK (unit_price_at_sale >= 0),
    unit_cost_at_sale  DECIMAL(10, 2) NOT NULL CHECK (unit_cost_at_sale  >= 0),
    subtotal           DECIMAL(10, 2) NOT NULL CHECK (subtotal           >= 0),
    PRIMARY KEY (sale_item_id),
    FOREIGN KEY (sale_id)    REFERENCES Sale(sale_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (product_id) REFERENCES Product(product_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ------------------------------------------------------------

CREATE TABLE Debt (
    debt_id           INT            NOT NULL AUTO_INCREMENT,
    sale_id           INT            NOT NULL UNIQUE,
    original_amount   DECIMAL(10, 2) NOT NULL CHECK (original_amount   >= 0),
    remaining_balance DECIMAL(10, 2) NOT NULL CHECK (remaining_balance >= 0),
    -- settlement_date: set automatically when remaining_balance reaches 0
    settlement_date   DATE                    DEFAULT NULL,
    status            ENUM('Unpaid', 'Partially Paid', 'Fully Paid')
                                    NOT NULL DEFAULT 'Unpaid',
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (debt_id),
    FOREIGN KEY (sale_id) REFERENCES Sale(sale_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ------------------------------------------------------------

CREATE TABLE Debt_Payment (
    payment_id   INT            NOT NULL AUTO_INCREMENT,
    debt_id      INT            NOT NULL,
    -- payment_date: the actual date money was received (can be many for partial)
    payment_date DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    amount_paid  DECIMAL(10, 2) NOT NULL CHECK (amount_paid > 0),
    PRIMARY KEY (payment_id),
    FOREIGN KEY (debt_id) REFERENCES Debt(debt_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);


-- ============================================================
--  INDEXES  (frequently searched columns)
-- ============================================================

-- Products are looked up by name and SKU most often
CREATE INDEX idx_product_name ON Product(product_name);
CREATE INDEX idx_product_sku  ON Product(sku);

-- Sales filtered by date for reports
CREATE INDEX idx_sale_date ON Sale(sale_date);

-- Debt lookups by status (dashboard "unpaid debts" widget)
CREATE INDEX idx_debt_status ON Debt(status);

-- Inventory log queries by product
CREATE INDEX idx_inventory_product ON Inventory_Log(product_id);


-- ============================================================
--  TRIGGERS
-- ============================================================

DELIMITER $$

-- ------------------------------------------------------------
-- TR 1: Auto-generate SKU before inserting a new Product
--   Format: first 3 letters of product_name (upper) + product_id (zero-padded to 5)
--   e.g., "Apple Juice" with id 7 → APP00007
-- ------------------------------------------------------------
CREATE TRIGGER trg_product_before_insert
BEFORE INSERT ON Product
FOR EACH ROW
BEGIN
    -- Temporary SKU placeholder so the row inserts (id not yet known)
    SET NEW.sku = CONCAT(
        UPPER(LEFT(REPLACE(NEW.product_name, ' ', ''), 3)),
        'TEMP'
    );
END$$

CREATE TRIGGER trg_product_after_insert
AFTER INSERT ON Product
FOR EACH ROW
BEGIN
    UPDATE Product
    SET sku = CONCAT(
        UPPER(LEFT(REPLACE(NEW.product_name, ' ', ''), 3)),
        LPAD(NEW.product_id, 5, '0')
    )
    WHERE product_id = NEW.product_id;
END$$

-- ------------------------------------------------------------
-- TR 2: Auto-update Product status based on quantity
--   (fires on INSERT and UPDATE of Product.quantity)
-- ------------------------------------------------------------
CREATE TRIGGER trg_product_status_insert
BEFORE INSERT ON Product
FOR EACH ROW
BEGIN
    IF NEW.quantity = 0 THEN
        SET NEW.status = 'Out of Stock';
    ELSEIF NEW.quantity <= NEW.low_stock_threshold THEN
        SET NEW.status = 'Low Stock';
    ELSE
        SET NEW.status = 'In Stock';
    END IF;
END$$

CREATE TRIGGER trg_product_status_update
BEFORE UPDATE ON Product
FOR EACH ROW
BEGIN
    IF NEW.quantity = 0 THEN
        SET NEW.status = 'Out of Stock';
    ELSEIF NEW.quantity <= NEW.low_stock_threshold THEN
        SET NEW.status = 'Low Stock';
    ELSE
        SET NEW.status = 'In Stock';
    END IF;
END$$

-- ------------------------------------------------------------
-- TR 3: After a Sale_Item is inserted, deduct stock from Product
--       and write an Inventory_Log entry automatically
-- ------------------------------------------------------------
CREATE TRIGGER trg_sale_item_after_insert
AFTER INSERT ON Sale_Item
FOR EACH ROW
BEGIN
    DECLARE v_stock_before INT;

    SELECT quantity INTO v_stock_before
    FROM   Product
    WHERE  product_id = NEW.product_id;

    -- Deduct sold quantity
    UPDATE Product
    SET    quantity = quantity - NEW.quantity_sold
    WHERE  product_id = NEW.product_id;

    -- Write inventory log
    INSERT INTO Inventory_Log
        (product_id, movement_type, quantity_change,
         stock_before, stock_after, reference_type, reference_id)
    VALUES
        (NEW.product_id, 'Out', NEW.quantity_sold,
         v_stock_before, v_stock_before - NEW.quantity_sold,
         'Sale', NEW.sale_id);
END$$

-- ------------------------------------------------------------
-- TR 4: After a Restock_Item is inserted, add stock to Product
--       and write an Inventory_Log entry automatically
-- ------------------------------------------------------------
CREATE TRIGGER trg_restock_item_after_insert
AFTER INSERT ON Restock_Item
FOR EACH ROW
BEGIN
    DECLARE v_stock_before INT;

    SELECT quantity INTO v_stock_before
    FROM   Product
    WHERE  product_id = NEW.product_id;

    UPDATE Product
    SET    quantity = quantity + NEW.quantity_added
    WHERE  product_id = NEW.product_id;

    INSERT INTO Inventory_Log
        (product_id, movement_type, quantity_change,
         stock_before, stock_after, reference_type, reference_id)
    VALUES
        (NEW.product_id, 'In', NEW.quantity_added,
         v_stock_before, v_stock_before + NEW.quantity_added,
         'Restock', NEW.restock_id);
END$$

-- ------------------------------------------------------------
-- TR 5: After a Debt_Payment is inserted, update Debt status
--       and set settlement_date when fully paid
-- ------------------------------------------------------------
CREATE TRIGGER trg_debt_payment_after_insert
AFTER INSERT ON Debt_Payment
FOR EACH ROW
BEGIN
    DECLARE v_new_balance DECIMAL(10,2);

    -- Deduct the payment from remaining balance
    UPDATE Debt
    SET    remaining_balance = remaining_balance - NEW.amount_paid
    WHERE  debt_id = NEW.debt_id;

    -- Get updated balance
    SELECT remaining_balance INTO v_new_balance
    FROM   Debt
    WHERE  debt_id = NEW.debt_id;

    IF v_new_balance <= 0 THEN
        -- Fully paid: settlement_date = this payment's date
        UPDATE Debt
        SET    status          = 'Fully Paid',
               remaining_balance = 0,
               settlement_date = DATE(NEW.payment_date)
        WHERE  debt_id = NEW.debt_id;

        -- Update customer outstanding balance
        UPDATE Customer c
        INNER JOIN Sale s ON s.customer_id = c.customer_id
        INNER JOIN Debt d ON d.sale_id     = s.sale_id
        SET   c.total_outstanding = c.total_outstanding - (
                  SELECT d2.original_amount - 0   -- balance already zeroed
                  FROM   Debt d2
                  WHERE  d2.debt_id = NEW.debt_id
              )
        WHERE d.debt_id = NEW.debt_id;
    ELSE
        UPDATE Debt
        SET    status = 'Partially Paid'
        WHERE  debt_id = NEW.debt_id
          AND  status  = 'Unpaid';
    END IF;
END$$

DELIMITER ;


-- ============================================================
--  STORED PROCEDURES
-- ============================================================

DELIMITER $$

-- ------------------------------------------------------------
-- SP 1: Process a complete Sale (multi-table insert with transaction)
--   Parameters:
--     p_customer_id   – NULL for cash sales
--     p_payment_method – 'Cash' or 'Credit'
--     p_amount_tendered – cash handed over (NULL for credit)
--     p_items         – JSON array: [{"product_id":1,"qty":2}, ...]
--
--   Returns: sale_id of the new sale
-- ------------------------------------------------------------
CREATE PROCEDURE sp_process_sale(
    IN  p_user_id         INT,
    IN  p_customer_id     INT,
    IN  p_payment_method  ENUM('Cash','Credit'),
    IN  p_amount_tendered DECIMAL(10,2),
    IN  p_items           JSON,
    OUT p_sale_id         INT
)
BEGIN
    DECLARE v_total_amount  DECIMAL(10,2) DEFAULT 0;
    DECLARE v_total_cost    DECIMAL(10,2) DEFAULT 0;
    DECLARE v_profit        DECIMAL(10,2) DEFAULT 0;
    DECLARE v_change_given  DECIMAL(10,2) DEFAULT 0;
    DECLARE v_i             INT           DEFAULT 0;
    DECLARE v_item_count    INT;
    DECLARE v_product_id    INT;
    DECLARE v_qty           INT;
    DECLARE v_retail_price  DECIMAL(10,2);
    DECLARE v_cost_price    DECIMAL(10,2);
    DECLARE v_subtotal      DECIMAL(10,2);
    DECLARE v_stock         INT;

    -- Error handler: rollback on any error
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SET v_item_count = JSON_LENGTH(p_items);

    -- ── Validate stock availability before touching any data ──
    SET v_i = 0;
    WHILE v_i < v_item_count DO
        SET v_product_id = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].product_id')));
        SET v_qty        = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].qty')));

        SELECT quantity INTO v_stock
        FROM   Product
        WHERE  product_id = v_product_id;

        IF v_stock < v_qty THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Insufficient stock for one or more products.';
        END IF;

        SET v_i = v_i + 1;
    END WHILE;

    -- ── Insert Sale header (placeholder totals, updated below) ──
    INSERT INTO Sale (customer_id, payment_method, total_amount,
                      amount_tendered, change_given, total_cost, profit)
    VALUES (p_customer_id, p_payment_method, 0, p_amount_tendered, 0, 0, 0);

    SET p_sale_id = LAST_INSERT_ID();

    -- ── Insert Sale_Item rows (triggers handle stock deduction) ──
    SET v_i = 0;
    WHILE v_i < v_item_count DO
        SET v_product_id  = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].product_id')));
        SET v_qty         = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].qty')));

        SELECT retail_price, cost_price
        INTO   v_retail_price, v_cost_price
        FROM   Product
        WHERE  product_id = v_product_id;

        SET v_subtotal     = v_retail_price * v_qty;
        SET v_total_amount = v_total_amount + v_subtotal;
        SET v_total_cost   = v_total_cost   + (v_cost_price * v_qty);

        INSERT INTO Sale_Item
            (sale_id, product_id, quantity_sold,
             unit_price_at_sale, unit_cost_at_sale, subtotal)
        VALUES
            (p_sale_id, v_product_id, v_qty,
             v_retail_price, v_cost_price, v_subtotal);

        SET v_i = v_i + 1;
    END WHILE;

    -- ── Calculate profit and change ──
    SET v_profit = v_total_amount - v_total_cost;

    IF p_payment_method = 'Cash' AND p_amount_tendered IS NOT NULL THEN
        SET v_change_given = p_amount_tendered - v_total_amount;
    END IF;

    -- ── Update Sale with real totals ──
    UPDATE Sale
    SET    total_amount    = v_total_amount,
           change_given    = v_change_given,
           total_cost      = v_total_cost,
           profit          = v_profit
    WHERE  sale_id = p_sale_id;

    -- ── If Credit: create Debt and update customer outstanding ──
    IF p_payment_method = 'Credit' THEN
        INSERT INTO Debt (sale_id, original_amount, remaining_balance, status)
        VALUES (p_sale_id, v_total_amount, v_total_amount, 'Unpaid');

        UPDATE Customer
        SET    total_outstanding = total_outstanding + v_total_amount
        WHERE  customer_id = p_customer_id;
    END IF;

    COMMIT;
END$$

-- ------------------------------------------------------------
-- SP 2: Process a Restock (multi-table insert with transaction)
--   p_items JSON: [{"product_id":1,"qty":10,"cost":5.00,"exp":"2026-12-31"}, ...]
--   Pass exp as NULL string "null" for no expiration.
-- ------------------------------------------------------------
CREATE PROCEDURE sp_process_restock(
    IN  p_items       JSON,
    OUT p_restock_id  INT
)
BEGIN
    DECLARE v_total_cost  DECIMAL(10,2) DEFAULT 0;
    DECLARE v_i           INT           DEFAULT 0;
    DECLARE v_item_count  INT;
    DECLARE v_product_id  INT;
    DECLARE v_qty         INT;
    DECLARE v_cost        DECIMAL(10,2);
    DECLARE v_exp         DATE;
    DECLARE v_exp_str     VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SET v_item_count = JSON_LENGTH(p_items);

    -- Pre-calculate total cost
    SET v_i = 0;
    WHILE v_i < v_item_count DO
        SET v_qty  = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].qty')));
        SET v_cost = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].cost')));
        SET v_total_cost = v_total_cost + (v_qty * v_cost);
        SET v_i = v_i + 1;
    END WHILE;

    INSERT INTO Restock_Transaction (total_cost) VALUES (v_total_cost);
    SET p_restock_id = LAST_INSERT_ID();

    SET v_i = 0;
    WHILE v_i < v_item_count DO
        SET v_product_id = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].product_id')));
        SET v_qty        = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].qty')));
        SET v_cost       = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].cost')));
        SET v_exp_str    = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].exp')));

        IF v_exp_str = 'null' OR v_exp_str IS NULL THEN
            SET v_exp = NULL;
        ELSE
            SET v_exp = STR_TO_DATE(v_exp_str, '%Y-%m-%d');
        END IF;

        -- Trigger trg_restock_item_after_insert handles stock update + log
        INSERT INTO Restock_Item
            (restock_id, product_id, quantity_added,
             cost_price_at_restock, expiration_date)
        VALUES
            (p_restock_id, v_product_id, v_qty, v_cost, v_exp);

        -- Update product's cost price and expiration reference
        UPDATE Product
        SET    cost_price       = v_cost,
               expiration_date  = v_exp
        WHERE  product_id = v_product_id;

        SET v_i = v_i + 1;
    END WHILE;

    COMMIT;
END$$

-- ------------------------------------------------------------
-- SP 3: Record a Debt Payment (with transaction)
-- ------------------------------------------------------------
CREATE PROCEDURE sp_record_debt_payment(
    IN p_debt_id    INT,
    IN p_amount     DECIMAL(10,2)
)
BEGIN
    DECLARE v_balance DECIMAL(10,2);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT remaining_balance INTO v_balance
    FROM   Debt
    WHERE  debt_id = p_debt_id
    FOR UPDATE;  -- row-level lock

    IF p_amount > v_balance THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Payment amount exceeds remaining balance.';
    END IF;

    -- Trigger trg_debt_payment_after_insert handles balance update + status
    INSERT INTO Debt_Payment (debt_id, amount_paid)
    VALUES (p_debt_id, p_amount);

    COMMIT;
END$$

DELIMITER ;


-- ============================================================
--  VIEWS  (SQL brain for reporting — no PHP loops needed)
-- ============================================================

-- ------------------------------------------------------------
-- V 1: Manager Dashboard
--   Joins Product + Category + Sale_Item + Sale
--   Gives: total revenue, total profit, units sold per product
-- ------------------------------------------------------------
CREATE VIEW vw_manager_dashboard AS
SELECT
    p.product_id,
    p.product_name,
    p.sku,
    c.category_name,
    p.quantity                                         AS current_stock,
    p.status                                           AS stock_status,
    COALESCE(SUM(si.quantity_sold),   0)               AS total_units_sold,
    COALESCE(SUM(si.subtotal),        0.00)            AS total_revenue,
    COALESCE(SUM(si.subtotal
             - (si.unit_cost_at_sale * si.quantity_sold)), 0.00) AS total_profit
FROM       Product       p
LEFT JOIN  Category      c  ON c.category_id  = p.category_id
LEFT JOIN  Sale_Item     si ON si.product_id  = p.product_id
LEFT JOIN  Sale          s  ON s.sale_id      = si.sale_id
GROUP BY
    p.product_id, p.product_name, p.sku,
    c.category_name, p.quantity, p.status;

-- ------------------------------------------------------------
-- V 2: Outstanding Debt Report
--   Joins Debt + Sale + Customer
-- ------------------------------------------------------------
CREATE VIEW vw_outstanding_debts AS
SELECT
    d.debt_id,
    cu.customer_id,
    cu.customer_name,
    cu.contact_number,
    s.sale_id,
    s.sale_date,
    d.original_amount,
    d.remaining_balance,
    d.status                                           AS debt_status,
    d.settlement_date,
    COALESCE(SUM(dp.amount_paid), 0.00)                AS total_paid
FROM       Debt          d
INNER JOIN Sale          s  ON s.sale_id     = d.sale_id
INNER JOIN Customer      cu ON cu.customer_id = s.customer_id
LEFT JOIN  Debt_Payment  dp ON dp.debt_id    = d.debt_id
GROUP BY
    d.debt_id, cu.customer_id, cu.customer_name, cu.contact_number,
    s.sale_id, s.sale_date, d.original_amount,
    d.remaining_balance, d.status, d.settlement_date;

-- ------------------------------------------------------------
-- V 3: Daily Sales Summary
--   Useful for dashboard charts — pure SQL aggregation
-- ------------------------------------------------------------
CREATE VIEW vw_daily_sales_summary AS
SELECT
    DATE(s.sale_date)          AS sale_day,
    COUNT(DISTINCT s.sale_id)  AS total_transactions,
    SUM(s.total_amount)        AS total_revenue,
    SUM(s.profit)              AS total_profit,
    SUM(s.total_cost)          AS total_cost,
    SUM(CASE WHEN s.payment_method = 'Cash'   THEN 1 ELSE 0 END) AS cash_sales,
    SUM(CASE WHEN s.payment_method = 'Credit' THEN 1 ELSE 0 END) AS credit_sales
FROM Sale s
GROUP BY DATE(s.sale_date)
ORDER BY sale_day DESC;

-- ------------------------------------------------------------
-- V 4: Low Stock / Out of Stock Alert View
-- ------------------------------------------------------------
CREATE VIEW vw_stock_alerts AS
SELECT
    p.product_id,
    p.product_name,
    p.sku,
    c.category_name,
    p.quantity,
    p.low_stock_threshold,
    p.status,
    p.expiration_date
FROM      Product  p
LEFT JOIN Category c ON c.category_id = p.category_id
WHERE p.status IN ('Low Stock', 'Out of Stock')
ORDER BY p.quantity ASC;

-- ============================================================
--  END OF SCHEMA
-- ============================================================