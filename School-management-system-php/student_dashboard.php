<?php
// student_dashboard.php

// Start the session
session_start();

// Check if the user is logged in as a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html");
    exit();
}

// Display student-specific content
echo "<h1>Welcome, Student " . htmlspecialchars($_SESSION['username']) . "!</h1>";
echo "<p>This is the Student Dashboard.</p>";
echo "<a href='logout.php'>Logout</a>";
?>