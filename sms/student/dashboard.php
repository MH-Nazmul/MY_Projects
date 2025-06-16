<?php
// student/dashboard.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

// Start the session
session_start();

// Check if the user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../logout.php");
    exit();
}

// Include database connection
include '../db_connect.php';

// Check database connection
if (!$conn) {
    die("Database connection failed.");
}

// Get the student's details
$student_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT fname, lname, class, section, roll_no FROM students WHERE id = ?");
if (!$stmt) {
    die("Database prepare error.");
}
$stmt->bindParam(1, $student_id, PDO::PARAM_INT);
if (!$stmt->execute()) {
    die("Database error occurred.");
}
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header("Location: ../logout.php");
    exit();
}

if (!isset($student['class']) || empty($student['class'])) {
    die("Student class not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Profile</title>
    <link rel="stylesheet" href="../CSS/admin.css">
    <style>
        .dashboard-section {
            margin-bottom: 40px;
        }
        .dashboard-section h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
        }
        .profile-info {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2>Student Dashboard</h2>
            <ul>
                <li><a href="dashboard.php">Profile</a></li>
                <li><a href="assignments.php">Assignments</a></li>
                <li><a href="class_routine.php">Class Routine</a></li>
                <li><a href="exam_schedule.php">Exam Schedule</a></li>
                <li><a href="results.php">Results</a></li>
                <li><a href="notices.php">Notices</a></li>
                <li><a href="payments.php">Dues & Payments</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Profile Overview -->
            <div class="dashboard-section" id="profile">
                <h2>Welcome, <?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?>!</h2>
                <div class="profile-info">
                    <p><strong>Class:</strong> <?php echo htmlspecialchars($student['class']); ?></p>
                    <p><strong>Section:</strong> <?php echo htmlspecialchars($student['section']); ?></p>
                    <p><strong>Roll No:</strong> <?php echo htmlspecialchars($student['roll_no']); ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>