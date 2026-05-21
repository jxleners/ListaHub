<?php
// ============================================================
//  signup_process.php
//  Requirements met:
//   ✅ PDO prepared statements
//   ✅ password_hash()
//   ✅ Transaction (BEGIN / COMMIT / ROLLBACK)
//   ✅ try-catch
//   ✅ Centralized lhdb.php
//  NOTE: New schema stores store_name directly in User table.
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: signup.php");
    exit;
}

require_once './utils/lhdb.php';

// ── 1. Collect & sanitize inputs ────────────────────────────
$username   = trim($_POST["username"]   ?? '');
$email      = trim($_POST["email"]      ?? '');
$password   =      $_POST["password"]   ?? '';
$confirm    =      $_POST["confirm"]    ?? '';
$store_name = trim($_POST["store_name"] ?? '');
$terms      =      $_POST["terms"]      ?? null;

// ── 2. Validation ────────────────────────────────────────────
if (!$terms) {
    echo "<script>alert('You must agree to the terms and conditions.'); window.history.back();</script>";
    exit;
}
if (empty($username) || empty($email) || empty($password) || empty($store_name)) {
    echo "<script>alert('All fields are required.'); window.history.back();</script>";
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "<script>alert('Invalid email address.'); window.history.back();</script>";
    exit;
}
if (strlen($password) < 8) {
    echo "<script>alert('Password must be at least 8 characters.'); window.history.back();</script>";
    exit;
}
if ($password !== $confirm) {
    echo "<script>alert('Passwords do not match.'); window.history.back();</script>";
    exit;
}

// ── 3. Database operations ───────────────────────────────────
try {
    $pdo = getPDO();

    // Check for duplicate username or email
    $checkStmt = $pdo->prepare(
        "SELECT user_id FROM User WHERE username = :username OR email = :email LIMIT 1"
    );
    $checkStmt->execute([':username' => $username, ':email' => $email]);

    if ($checkStmt->fetch()) {
        echo "<script>alert('Username or email is already taken.'); window.history.back();</script>";
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Transaction: single insert into User table
    $pdo->beginTransaction();

    $userStmt = $pdo->prepare(
        "INSERT INTO User (username, email, password_hash, store_name, created_at)
         VALUES (:username, :email, :password_hash, :store_name, NOW())"
    );
    $userStmt->execute([
        ':username'      => $username,
        ':email'         => $email,
        ':password_hash' => $hashedPassword,
        ':store_name'    => $store_name,
    ]);

    $newUserId = (int) $pdo->lastInsertId();
    $pdo->commit();

    // Set session
    $_SESSION['user_id']    = $newUserId;
    $_SESSION['username']   = $username;
    $_SESSION['email']      = $email;
    $_SESSION['store_name'] = $store_name;

    header("Location: dashboard.php");
    exit;

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Signup error: " . $e->getMessage());
    echo "<script>alert('Registration failed due to a server error. Please try again.'); window.history.back();</script>";
    exit;
}
?>