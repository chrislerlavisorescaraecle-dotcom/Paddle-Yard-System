<?php
include 'database/connect.php';

$full_name = 'Administrator';
$email = 'admin@picklebook.com';
$password = 'Admin1234';

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$check_stmt = $conn->prepare('SELECT account_id FROM accounts WHERE email = ? LIMIT 1');
$check_stmt->bind_param('s', $email);
$check_stmt->execute();
$existing_account = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if ($existing_account) {
    echo 'Admin account already exists.';
} else {
    $sql = "INSERT INTO accounts
            (full_name, email, password_hash, role, usertype_id, account_status)
            VALUES (?, ?, ?, 'admin', 3, 'active')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $full_name, $email, $password_hash);

    if ($stmt->execute()) {
        echo 'Admin account created successfully.';
    } else {
        echo 'Error: ' . htmlspecialchars($stmt->error);
    }

    $stmt->close();
}

$conn->close();
?>