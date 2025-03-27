<?php
// admin_dashboard.php

// Start the session
session_start();
include 'db_connect.php';
// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Complaints</title>
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
            <h1>View Complaints</h1>
            <table>
                <tr><th>ID</th><th>Submitted By</th><th>Message</th><th>Date</th></tr>
                <?php foreach ($complaints as $complaint) { ?>
                    <tr>
                        <td><?php echo $complaint['id']; ?></td>
                        <td><?php echo $complaint['submitted_by']; ?></td>
                        <td><?php echo $complaint['message']; ?></td>
                        <td><?php echo $complaint['submitted_at']; ?></td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    </div>
</body>
</html>