<?php
// student/results.php

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

// Get the student's ID
$student_id = $_SESSION['user_id'];

// Fetch student results
$stmt = $conn->prepare("
    SELECT e.exam_name, e.semester, sr.result 
    FROM student_results sr
    JOIN exams e ON sr.exam_id = e.id
    WHERE sr.student_id = ?
    ORDER BY e.exam_date DESC
");
if (!$stmt) {
    die("Database prepare error.");
}
$stmt->bindParam(1, $student_id, PDO::PARAM_INT);
if (!$stmt->execute()) {
    die("Database error occurred.");
}
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Results</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
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
            <!-- Results -->
            <div class="dashboard-section">
                <h2>Your Results</h2>
                <table>
                    <tr>
                        <th>Exam Name</th>
                        <th>Semester</th>
                        <th>Result</th>
                    </tr>
                    <?php if (!empty($results)): ?>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['exam_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['semester']); ?></td>
                                <td><?php echo htmlspecialchars($row['result']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>