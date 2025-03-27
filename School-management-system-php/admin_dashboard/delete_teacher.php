<?php
// Start the session
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Include the database connection
include '../db_connect.php';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: teacher_management.php?error=Invalid+request+method");
    exit();
}

// Check if the 'id' parameter is set and is a valid integer
if (!isset($_POST['id']) || !filter_var($_POST['id'], FILTER_VALIDATE_INT) || $_POST['id'] <= 0) {
    header("Location: teacher_management.php?error=Invalid+teacher+ID");
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header("Location: teacher_management.php?error=Invalid+CSRF+token");
    exit();
}

$teacher_id = (int)$_POST['id'];

try {
    // Delete the teacher from the database
    $stmt = $conn->prepare("DELETE FROM teachers WHERE id = ?");
    $stmt->execute([$teacher_id]);

    // Check if any rows were affected
    if ($stmt->rowCount() > 0) {
        header("Location: teacher_management.php?success=Teacher+deleted+successfully");
    } else {
        header("Location: teacher_management.php?error=Teacher+not+found");
    }
    exit();
} catch (PDOException $e) {
    header("Location: teacher_management.php?error=Failed+to+delete+teacher:+" . urlencode($e->getMessage()));
    exit();
}
?>