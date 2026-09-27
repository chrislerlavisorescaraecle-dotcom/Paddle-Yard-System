<?php
if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../project/sign-in.php');
    exit;
}

$full_name = $_SESSION['full_name'];

$account_result = $conn->query("SELECT COUNT(*) AS total FROM accounts");
$account_count = $account_result->fetch_assoc()['total'];

$court_result = $conn->query("SELECT COUNT(*) AS total FROM courts");
$court_count = $court_result->fetch_assoc()['total'];

$reservation_result = $conn->query("SELECT COUNT(*) AS total FROM reservations");
$reservation_count = $reservation_result->fetch_assoc()['total'];

$payment_result = $conn->query("SELECT COUNT(*) AS total FROM payments");
$payment_count = $payment_result->fetch_assoc()['total'];

?>