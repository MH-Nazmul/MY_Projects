<?php
// admin_dashboard.php

// Start the session
session_start();

// Check if the user is already logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: ../logout.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="CSS/admin.css">
</head>
<body>
    <div class="container">
      <?php include 'admin_sidebar.php'; ?>
        <!-- Main Content -->
        <div class="main-content">
            <h1>Welcome, Admin!</h1>
            <p>Use the sidebar to manage your school efficiently.</p>
        </div>
    </div>
</body>
</html>