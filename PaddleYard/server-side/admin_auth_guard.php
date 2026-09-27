<?php
session_start();
if (!isset($_SESSION['account_id'])) {
    header('Location: ../project/sign-in.php');
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "Access Denied. Admins Only.";
    exit;
} 