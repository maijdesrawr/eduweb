<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "edupocket-db.cpym6mgi0mla.ap-southeast-1.rds.amazonaws.com";
$username = "admin";
$password = "Rexiee2005";
$database = "try";
$port = 3306;

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database,
    $port
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>