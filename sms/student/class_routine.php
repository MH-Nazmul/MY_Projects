<?php
// student/class_routine.php

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

// Get the student's class
$student_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT class FROM students WHERE id = ?");
if (!$stmt) {
    die("Database prepare error: " . $conn->errorInfo()[2]);
}
$stmt->bindParam(1, $student_id, PDO::PARAM_INT);
if (!$stmt->execute()) {
    die("Database error occurred: " . $stmt->errorInfo()[2]);
}
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header("Location: ../logout.php");
    exit();
}

if (!isset($student['class']) || empty($student['class'])) {
    die("Student class not found.");
}

// Get current day for class routine
$current_day = ucfirst(strtolower(date('l'))); // Today is Monday, May 26, 2025

// Fetch class routine for the student's class using the schedules table
$stmt = $conn->prepare("
    SELECT s.day, s.start_time, s.end_time, subject_name as subject, t.fname, t.lname 
    FROM schedules s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    join subjects as sub on sub.id=s.subject_id
    JOIN classes c ON s.class_id = c.id
    WHERE c.class_name = ? AND s.day = ?
    ORDER BY s.start_time
");
if (!$stmt) {
    die("Database prepare error: " . $conn->errorInfo()[2]);
}
$stmt->bindParam(1, $student['class'], PDO::PARAM_STR);
$stmt->bindParam(2, $current_day, PDO::PARAM_STR);
if (!$stmt->execute()) {
    die("Database error occurred: " . $stmt->errorInfo()[2]);
}
$routine = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Print the fetched routine to verify
echo "<!-- Debug: Fetched routine: " . print_r($routine, true) . " -->";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Class Routine</title>
    <link rel="stylesheet" href="../CSS/admin.css">
    <style>
        .container {
            display: flex;
            width: 100%;
            max-width: 100%;
            padding: 0;
            margin: 0;
            box-sizing: border-box;
        }
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: #fff;
            padding: 20px;
            box-sizing: border-box;
        }
        .sidebar h2 {
            margin-bottom: 20px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar ul li {
            margin-bottom: 10px;
        }
        .sidebar ul li a {
            color: #ecf0f1;
            text-decoration: none;
        }
        .sidebar ul li a:hover {
            color: #3498db;
        }
        .main-content {
            flex: 1;
            padding: 20px;
            box-sizing: border-box;
            width: 100%;
        }
        .dashboard-section {
            margin-bottom: 40px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 100%;
            box-sizing: border-box;
        }
        .dashboard-section h2 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            font-size: 24px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #3498db;
            color: #fff;
            font-weight: 600;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        tr:hover {
            background-color: #e9ecef;
        }
        @media (max-width: 768px) {
            th, td {
                padding: 8px;
                font-size: 12px;
            }
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
            <!-- Class Routine -->
            <div class="dashboard-section">
                <h2>Today's Class Routine (<?php echo htmlspecialchars($current_day); ?>)</h2>
                <?php if (!empty($routine)): ?>
                    <table>
                        <tr>
                            <th>Subject</th>
                            <th>Time</th>
                            <th>Teacher</th>
                        </tr>
                        <?php foreach ($routine as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                <td><?php echo htmlspecialchars($row['start_time'] . ' - ' . $row['end_time']); ?></td>
                                <td><?php echo htmlspecialchars(($row['fname'] ?? 'N/A') . ' ' . ($row['lname'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>No classes scheduled for today.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>