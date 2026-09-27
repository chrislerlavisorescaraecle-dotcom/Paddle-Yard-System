<?php
include '../database/connect.php';
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

if (!isset($_SESSION['account_id'], $_SESSION['role'])) {
	header('Location: ../project/sign-in.php');
	exit;
}

if ($_SESSION['role'] !== 'customer') {
	http_response_code(403);
	echo 'Access Denied. Customers Only.';
	exit;
}

$full_name = $_SESSION['full_name'] ?? 'Customer';
?>
