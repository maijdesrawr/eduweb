<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id']) ||
    ($_SESSION['admin_role'] ?? '') !== 'system admin') {
    header("Location: index.php");
    exit();
}

$adminName = $_SESSION['admin_name'] ?? 'Super Administrator';
?>