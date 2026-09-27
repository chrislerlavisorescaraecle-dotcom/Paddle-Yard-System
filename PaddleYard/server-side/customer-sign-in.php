<?php
include '../database/connect.php';
$error = '';
$success = isset($_GET['registered']) ? 'Account created successfully. Please sign in.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? $_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare(
        "SELECT account_id, full_name, password_hash, role
         FROM accounts
         WHERE email = ? AND account_status = 'active'
         LIMIT 1"
    );
    $account = null;

    if ($stmt) {
        $stmt->bind_param('s', $email);
        if ($stmt->execute() && $stmt->bind_result($account_id, $full_name, $password_hash, $role)) {
            if ($stmt->fetch()) {
                $account = [
                    'account_id' => $account_id,
                    'full_name' => $full_name,
                    'password_hash' => $password_hash,
                    'role' => $role,
                ];
            }
        }
        $stmt->close();
    }

    if ($account !== null && password_verify($password, $account['password_hash'])) {
        session_start();
        session_regenerate_id(true);
        $_SESSION['account_id'] = (int) $account['account_id'];
        $_SESSION['full_name'] = $account['full_name'];
        $_SESSION['role'] = $account['role'];

        if ($account['role'] === 'admin') {
            header('Location: ../admin/admin-landing-page.php');
        } else {
            header('Location: ../customer/customer-landing-page.php');
        }
        exit;
    }

    $error = 'Invalid email or password.';
}
?>