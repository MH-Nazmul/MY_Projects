<?php
// teacher_dashboard.php

// Start the session
session_start();

// Check if the user is logged in as a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.html");
    exit();
}

// Display teacher-specific content
echo "<h1>Welcome, Teacher " . htmlspecialchars($_SESSION['username']) . "!</h1>";
echo "<p>This is the Teacher Dashboard.</p>";
echo "<a href='logout.php'>Logout</a>";
?>