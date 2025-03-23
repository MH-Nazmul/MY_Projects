<?php
// admin_dashboard.php

// Start the session
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// Display admin-specific content
echo "<h1>Welcome, Admin " . htmlspecialchars($_SESSION['username']) . "!</h1>";
echo "<p>This is the Admin Dashboard.</p>";
echo "<a href='logout.php'>Logout</a>";
?>