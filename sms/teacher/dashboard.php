<?php
// teacher/dashboard.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

// Start the session
session_start();

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../logout.php");
    exit();
}

// Include database connection
include '../db_connect.php';

// Check database connection
if (!$conn) {
    die("Database connection failed.");
}

// Get the teacher's details
$teacher_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT fname, lname FROM teachers WHERE id = ?");
if (!$stmt) {
    die("Database prepare error.");
}
$stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
if (!$stmt->execute()) {
    die("Database error occurred.");
}
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    header("Location: ../logout.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Profile</title>
    <link rel="stylesheet" href="CSS/dashboard.css">

</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <?php include 'teacher_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Profile Overview -->
            <div class="dashboard-section" id="profile">
                <h2>Welcome, <?php echo htmlspecialchars($teacher['fname'] . ' ' . $teacher['lname']); ?>!</h2>
                <div class="profile-info">
                    <p>No additional profile details available.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>