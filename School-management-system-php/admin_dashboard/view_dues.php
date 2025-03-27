<?php
// admin_dashboard.php

// Start the session
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}
include '../db_connect.php';
$students = $conn->query("SELECT * FROM student")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $conn->query("SELECT * FROM teacher")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Dues & Info</title>
    <link rel="stylesheet" href="../CSS/admin.css">
</head>
<body>
    <div class="container">
         <!-- Sidebar -->
         <div class="sidebar">
            <h2>Admin Dashboard</h2>
            <ul>
                <li><a href="admit_student.php">Admit Student</a></li>
                <li><a href="teacher_management.php">Teacher Managements</a></li>
                <li><a href="manage_student.php">Manage Students</a></li>
                <li><a href="view_dues.php">View Dues & Info</a></li>
                <li><a href="settings.php">School Settings</a></li>
                <li><a href="complaints.php">View Complaints</a></li>
                <li><a href="schedule.php">Class Schedule</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
        <div class="main-content">
            <h1>View Dues & Info</h1>
            <h2>Students</h2>
            <table>
                <tr><th>ID</th><th>Name</th><th>Class</th><th>Dues</th></tr>
                <?php foreach ($students as $student) { ?>
                    <tr>
                        <td><?php echo $student['id']; ?></td>
                        <td><?php echo $student['name']; ?></td>
                        <td><?php echo $student['class']; ?></td>
                        <td><?php echo $student['dues']; ?></td>
                    </tr>
                <?php } ?>
            </table>
            <h2>Teachers</h2>
            <table>
                <tr><th>ID</th><th>Name</th><th>Email</th></tr>
                <?php foreach ($teachers as $teacher) { ?>
                    <tr>
                        <td><?php echo $teacher['id']; ?></td>
                        <td><?php echo $teacher['name']; ?></td>
                        <td><?php echo $teacher['email']; ?></td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    </div>
</body>
</html>