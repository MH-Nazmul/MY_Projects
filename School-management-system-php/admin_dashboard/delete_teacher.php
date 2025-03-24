<?php
// Start the session
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Include the database connection
include 'db_connect.php';

// Check if the 'id' parameter is set and is a valid integer
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: /admin_dashboard.php?error=Invalid+teacher+ID");
    exit();
}

try {
    // Get the teacher ID
    $id = (int) $_GET['id'];

    // Prepare and execute the DELETE query
    $stmt = $conn->prepare("DELETE FROM teachers WHERE id = ?");
    $stmt->execute([$id]);

    // Check if any rows were affected (i.e., if the teacher existed)
    if ($stmt->rowCount() > 0) {
        // Redirect with a success message
        header("Location: teacher_management.php?success=Teacher+deleted+successfully");
    } else {
        // Redirect with an error message if no teacher was found
        header("Location: teacher_management.php?error=No+teacher+found+with+ID+$id");
    }
    exit();
} catch (PDOException $e) {
    // Redirect with an error message if the database operation fails
    header("Location: teacher_management.php?error=Failed+to+delete+teacher:+" . urlencode($e->getMessage()));
    exit();
}
?>