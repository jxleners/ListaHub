<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once './utils/lhdb.php';

$user_id = (int) $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $pdo = getPDO();

        // =========================
        // ADD CUSTOMER + CREDIT
        // =========================
        if ($action === 'add') {
            $customer_name   = trim($_POST['customer_name'] ?? '');
            $amount_owed     = (float) ($_POST['amount_owed'] ?? 0);
            $settlement_date = $_POST['settlement_date'] ?? null;

            if (empty($customer_name) || $amount_owed <= 0) {
                $error = 'Customer name and valid amount required.';
            } else {
                $pdo->beginTransaction();

                $custStmt = $pdo->prepare("
                    INSERT INTO customers (user_id, customer_name, created_at)
                    VALUES (:user_id, :customer_name, NOW())
                ");

                $custStmt->execute([
                    ':user_id' => $user_id,
                    ':customer_name' => $customer_name
                ]);

                $customer_id = (int)$pdo->lastInsertId();

                $creditStmt = $pdo->prepare("
                    INSERT INTO customer_credits
                    (customer_id, amount_owed, settlement_date, status, created_at)
                    VALUES
                    (:customer_id, :amount_owed, :settlement_date, 'pending', NOW())
                ");

                $creditStmt->execute([
                    ':customer_id' => $customer_id,
                    ':amount_owed' => $amount_owed,
                    ':settlement_date' => !empty($settlement_date) ? $settlement_date : null
                ]);

                $pdo->commit();
                $message = "Customer added successfully.";
            }
        }

        // =========================
        // UPDATE CREDIT
        // =========================
        if ($action === 'update') {
            $credit_id       = (int) ($_POST['credit_id'] ?? 0);
            $amount_owed     = (float) ($_POST['amount_owed'] ?? 0);
            $settlement_date = $_POST['settlement_date'] ?? null;
            $status          = $_POST['status'] ?? 'pending';

            $allowed = ['pending', 'settled', 'overdue'];
            if (!in_array($status, $allowed)) $status = 'pending';

            if ($credit_id) {
                $pdo->beginTransaction();

                $upd = $pdo->prepare("
                    UPDATE customer_credits cc
                    JOIN customers c ON c.id = cc.customer_id
                    SET cc.amount_owed = :amount_owed,
                        cc.settlement_date = :settlement_date,
                        cc.status = :status
                    WHERE cc.id = :id
                    AND c.user_id = :user_id
                ");

                $upd->execute([
                    ':amount_owed' => $amount_owed,
                    ':settlement_date' => !empty($settlement_date) ? $settlement_date : null,
                    ':status' => $status,
                    ':id' => $credit_id,
                    ':user_id' => $user_id
                ]);

                $pdo->commit();
                $message = "Credit updated successfully.";
            }
        }

        // =========================
        // DELETE CUSTOMER
        // =========================
        if ($action === 'delete') {
            $customer_id = (int) ($_POST['customer_id'] ?? 0);

            if ($customer_id) {
                $pdo->beginTransaction();

                $del = $pdo->prepare("
                    DELETE FROM customers
                    WHERE id = :id AND user_id = :user_id
                ");

                $del->execute([
                    ':id' => $customer_id,
                    ':user_id' => $user_id
                ]);

                $pdo->commit();
                $message = "Customer deleted.";
            }
        }

        // =========================
        // SETTLE CREDIT
        // =========================
        if ($action === 'settle') {
            $credit_id = (int) ($_POST['credit_id'] ?? 0);

            if ($credit_id) {
                $pdo->beginTransaction();

                $settle = $pdo->prepare("
                    UPDATE customer_credits cc
                    JOIN customers c ON c.id = cc.customer_id
                    SET cc.status = 'settled',
                        cc.amount_owed = 0
                    WHERE cc.id = :id
                    AND c.user_id = :user_id
                ");

                $settle->execute([
                    ':id' => $credit_id,
                    ':user_id' => $user_id
                ]);

                $pdo->commit();
                $message = "Credit settled.";
            }
        }

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        $error = $e->getMessage(); // show real error for debugging
    }
}

// =========================
// FETCH DATA
// =========================
try {
    $pdo = getPDO();
    $search = trim($_GET['search'] ?? '');

    // SUMMARY
    $summaryStmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN cc.status='pending' THEN cc.amount_owed ELSE 0 END),0) AS total_credit,
            COALESCE(SUM(CASE WHEN cc.status='pending' AND cc.settlement_date <= CURDATE()
                              THEN cc.amount_owed ELSE 0 END),0) AS on_due
        FROM customers c
        JOIN customer_credits cc ON cc.customer_id = c.id
        WHERE c.user_id = :user_id
    ");

    $summaryStmt->execute([':user_id' => $user_id]);
    $summary = $summaryStmt->fetch();

    // CUSTOMER LIST
    $sql = "
        SELECT c.id AS customer_id, c.customer_name, c.created_at,
               cc.id AS credit_id, cc.amount_owed, cc.settlement_date, cc.status
        FROM customers c
        LEFT JOIN customer_credits cc ON cc.customer_id = c.id
        WHERE c.user_id = :user_id
    ";

    $params = [':user_id' => $user_id];

    if ($search) {
        $sql .= " AND c.customer_name LIKE :search";
        $params[':search'] = "%$search%";
    }

    $sql .= " ORDER BY c.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll();

    // HISTORY
    $hist = $pdo->prepare("
        SELECT c.customer_name, cc.amount_owed, cc.status,
               cc.settlement_date, cc.created_at
        FROM customer_credits cc
        JOIN customers c ON c.id = cc.customer_id
        WHERE c.user_id = :user_id
        ORDER BY cc.created_at DESC
        LIMIT 20
    ");

    $hist->execute([':user_id' => $user_id]);
    $history = $hist->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $summary = ['on_due'=>0,'total_credit'=>0];
    $customers = [];
    $history = [];
}
?>