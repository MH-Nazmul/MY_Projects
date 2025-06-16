<?php
// student/notices.php

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

// Fetch notices (events)
$stmt = $conn->prepare("
    SELECT event_title, event_description, event_date 
    FROM events 
    ORDER BY event_date
");
if (!$stmt) {
    die("Database prepare error.");
}
if (!$stmt->execute()) {
    die("Database error occurred.");
}
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Notices</title>
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
            <!-- Notices (Events) -->
            <div class="dashboard-section">
                <h2>Notices & Events</h2>
                <table>
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Date</th>
                    </tr>
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['event_title']); ?></td>
                                <td><?php echo htmlspecialchars($row['event_description']); ?></td>
                                <td><?php echo htmlspecialchars($row['event_date']); ?></td>
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