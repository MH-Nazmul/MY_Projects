<?php
// db_connect.php

// Only start session if it hasn't been started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$host = 'localhost';
$dbname = 'mhnam';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
