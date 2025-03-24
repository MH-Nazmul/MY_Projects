<?php
// admin_dashboard.php

// Start the session
session_start();

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
    <title>Admit Student</title>
    <link rel="stylesheet" href="../CSS/admin.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2>Admin Dashboard</h2>
            <ul>
                <li><a href="admit_teacher.php">Admit Teacher</a></li>
                <li><a href="admit_student.php">Admit Student</a></li>
                <li><a href="manage_student.php">Manage Students</a></li>
                <li><a href="view_dues.php">View Dues & Info</a></li>
                <li><a href="settings.php">School Settings</a></li>
                <li><a href="complaints.php">View Complaints</a></li>
                <li><a href="schedule.php">Class Schedule</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
        <div class="main-content">
            <h1>Admit Student</h1>
            <?php if (isset($message)) echo "<p class='success'>$message</p>"; ?>
            <form method="POST">
                <label>Name:</label><input type="text" name="name" required>
                <label>Class:</label><input type="text" name="class" required>
                <label>Parent Contact:</label><input type="text" name="parent_contact" required>
                <button type="submit">Admit Student</button>
            </form>
        </div>
    </div>
</body>
</html>