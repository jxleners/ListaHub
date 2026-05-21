<?php
// ============================================================
//  login.php
//  Requirements met:
//   ✅ PDO prepared statements
//   ✅ password_verify()
//   ✅ try-catch
//   ✅ Centralized lhdb.php
//  NOTE: New schema uses User.password_hash (not .password)
//        and store_name lives inside User (no stores table).
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

require_once './utils/lhdb.php';

$login    = trim($_POST["login"]    ?? '');
$password =      $_POST["password"] ?? '';

if (empty($login) || empty($password)) {
    echo "<script>alert('Please enter your username/email and password.'); window.location='index.php';</script>";
    exit;
}

try {
    $pdo = getPDO();

    // Prepared statement — column is password_hash in new schema
    $stmt = $pdo->prepare(
        "SELECT user_id, username, email, password_hash, store_name
         FROM   User
         WHERE  username = :username OR email = :email
         LIMIT  1"
    );
    $stmt->execute([':username' => $login, ':email' => $login]);
    $user = $stmt->fetch();

    // password_verify() requirement
    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo "<script>alert('Invalid username/email or password.'); window.location='index.php';</script>";
        exit;
    }

    // Update last_login timestamp
    $updStmt = $pdo->prepare(
        "UPDATE User SET last_login = NOW() WHERE user_id = :user_id"
    );
    $updStmt->execute([':user_id' => $user['user_id']]);

    // Prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']    = $user['user_id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['email']      = $user['email'];
    $_SESSION['store_name'] = $user['store_name'];

    header("Location: dashboard.php");
    exit;

} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    echo "<script>alert('A server error occurred. Please try again.'); window.location='index.php';</script>";
    exit;
}
?>