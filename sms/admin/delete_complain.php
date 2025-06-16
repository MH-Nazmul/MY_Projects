<?php
// delete_complaint.php
session_start();
include '../db_connect.php';

// Check if the user is already logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: ../logout.php");
    exit();
}

// Check if the complaint ID is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complaint_id'])) {
    $complaint_id = (int)$_POST['complaint_id'];

    try {
        // Delete the complaint from the database
        $stmt = $conn->prepare("DELETE FROM complains WHERE id = ?");
        $stmt->execute([$complaint_id]);

        // Redirect back to complaints.php with a success message
        header("Location: complaints.php?success=Complaint has been removed successfully.");
        exit();
    } catch (PDOException $e) {
        // Redirect back with an error message
        header("Location: complaints.php?error=Failed to remove complaint: " . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Redirect back if no complaint ID is provided
    header("Location: complaints.php?error=Invalid request.");
    exit();
}
?>