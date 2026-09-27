<?php
include '../database/connect.php';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $contact_number = trim($_POST['contact_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $contact_number === '' || strlen($contact_number) > 30) {
        $error = 'Please provide a valid name, email address, and contact number.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        $check_stmt = $conn->prepare('SELECT account_id FROM accounts WHERE email = ? LIMIT 1');
        if (!$check_stmt) {
            $error = 'Unable to check the account. Database error: ' . $conn->error;
        } else {
        $check_stmt->bind_param('s', $email);
        $check_stmt->execute();
        $account_exists = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($account_exists) {
            $error = 'An account with that email already exists.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT INTO accounts
                 (full_name, email, contact_number, password_hash, role, usertype_id, account_status)
                 VALUES (?, ?, ?, ?, 'customer', 1, 'active')"
            );
            if (!$stmt) {
                $error = 'Unable to create the account. Database error: ' . $conn->error;
            } else {
                $stmt->bind_param('ssss', $full_name, $email, $contact_number, $password_hash);

                if ($stmt->execute() && $stmt->affected_rows === 1) {
                    $stmt->close();
                    $conn->close();
                    header('Location: ../project/sign-in.php?registered=1');
                    exit;
                }

                $error = 'Unable to create the account. Database error: ' . $stmt->error;
                $stmt->close();
            }
        }
        }
    }
}
?>