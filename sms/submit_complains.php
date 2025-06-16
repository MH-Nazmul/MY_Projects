<?php
// submit_complains.php

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start the session (if needed for future enhancements)
session_start();

// Include database connection
if (!file_exists('db_connect.php')) {
    die("Error: db_connect.php not found.");
}
include 'db_connect.php';

// Verify database connection
if (!isset($conn) || !($conn instanceof PDO)) {
    die("Error: Database connection failed. Check db_connect.php.");
}

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form data
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);

    // Validate form data
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.php?error=Invalid email address#contact");
        exit();
    }
    if (!$name || strlen(trim($name)) < 2) {
        header("Location: index.php?error=Name must be at least 2 characters#contact");
        exit();
    }
    if (!$message || strlen(trim($message)) < 5) {
        header("Location: index.php?error=Message must be at least 5 characters#contact");
        exit();
    }

    try {
        // Insert the complaint into the database
        $stmt = $conn->prepare("INSERT INTO complains (email, name, message) VALUES (?, ?, ?)");
        $result = $stmt->execute([$email, $name, $message]);

        if ($result) {
            // Success: Redirect with success message
            header("Location: index.php?success=Your message has been sent successfully#contact");
            exit();
        } else {
            // Failure: Redirect with error message
            header("Location: index.php?error=Failed to send your message#contact");
            exit();
        }
    } catch (PDOException $e) {
        // Log the error (in production, log to a file instead of displaying)
        error_log("Database error: " . $e->getMessage());
        header("Location: index.php?error=Database error occurred#contact");
        exit();
    }
} else {
    // If accessed directly without POST, redirect to index.php
    header("Location: index.php#contact");
    exit();
}
?>