<?php
// student/student_dashboard.php

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

// Fetch the student's class directly from the students table
$stmt = $conn->prepare("SELECT class FROM students WHERE id = ?");
$stmt->bindParam(1, $student_id, PDO::PARAM_INT);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Debug: Check if student data is fetched
if (!$student) {
    die("Error: Student not found in the database.");
}

$student_class = $student['class'] ?? '';
$_SESSION['class'] = $student_class; // Update session variable for consistency

// Debug: Print the student's class to verify
echo "<!-- Debug: Student's class is: " . htmlspecialchars($student_class) . " -->";

// Fetch assignments for the student's class (case-insensitive comparison)
$stmt = $conn->prepare("
    SELECT id, class, subject, title, description, due_date, created_at
    FROM assignments
    WHERE LOWER(class) = LOWER(?)
    ORDER BY due_date DESC
");
$stmt->bindParam(1, $student_class, PDO::PARAM_STR);
$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Print the fetched assignments to verify
echo "<!-- Debug: Fetched assignments: " . print_r($assignments, true) . " -->";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Assignments</title>
    <link rel="stylesheet" href="../CSS/admin.css"> <!-- Adjust path as needed -->
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
        .assignments-list {
            margin-top: 20px;
        }
        .assignments-list table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .assignments-list th,
        .assignments-list td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .assignments-list th {
            background-color: #3498db;
            color: #fff;
            font-weight: 600;
        }
        .assignments-list tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .assignments-list tr:hover {
            background-color: #e9ecef;
        }
        @media (max-width: 768px) {
            .assignments-list th,
            .assignments-list td {
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
            <!-- Assignments -->
            <div class="dashboard-section">
                <h2>Assignments</h2>
                <div class="assignments-list">
                    <?php if (!empty($assignments)): ?>
                        <table>
                            <tr>
                                <th>Subject</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Due Date</th>
                                <th>Created At</th>
                            </tr>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['description']); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($assignment['due_date']))); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($assignment['created_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php else: ?>
                        <p>No assignments assigned to your class.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>