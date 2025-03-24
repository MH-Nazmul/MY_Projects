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
    <title>Manage Students</title>
    <link rel="stylesheet" href="../CSS/admin.css">
    <link rel="stylesheet" href="manage_students.css">
</head>
<body>
    <div class="container">
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
            <h1>Manage Students</h1>

            <!-- Search Form -->
            <h2>Search Student</h2>
            <form method="POST">
                <label>Student ID:</label><input type="text" name="id">
                <label>Class:</label><input type="text" name="class">
                <label>Section:</label><input type="text" name="section">
                <label>Semester:</label>
                <select name="semester">
                    <option value="sem1">Semester 1</option>
                    <option value="sem2">Semester 2</option>
                </select>
                <button type="submit" name="search">Search</button>
            </form>

            <!-- Display Results -->
            <?php if ($student_data) { ?>
                <h2>Student Details</h2>
                <div class="student-info">
                    <h3><?php echo $student_data['name']; ?> (ID: <?php echo $student_data['id']; ?>)</h3>
                    <p>Class: <?php echo $student_data['class']; ?></p>
                    <p>Section: <?php echo $student_data['section']; ?></p>
                </div>

                <!-- Admit Card -->
                <div class="admit-card">
                    <h3>Admit Card</h3>
                    <p>Student ID: <?php echo $student_data['id']; ?></p>
                    <p>Name: <?php echo $student_data['name']; ?></p>
                    <p>Class: <?php echo $student_data['class']; ?></p>
                    <p>Section: <?php echo $student_data['section']; ?></p>
                    <p>Semester: <?php echo $semester === 'sem1' ? 'Semester 1' : 'Semester 2'; ?></p>
                </div>

                <!-- Result Sheet -->
                <div class="result-sheet">
                    <h3>Result Sheet</h3>
                    <?php
                    $result_field = $semester === 'sem1' ? 'result_sem1' : 'result_sem2';
                    $result = $student_data[$result_field] ?: 'Not available';
                    ?>
                    <p><?php echo $semester === 'sem1' ? 'Semester 1' : 'Semester 2'; ?> Result: <?php echo $result; ?></p>
                </div>

                <!-- Attendance Record -->
                <div class="attendance-record">
                    <h3>Attendance Record</h3>
                    <?php
                    $attendance_field = $semester === 'sem1' ? 'attendance_sem1' : 'attendance_sem2';
                    $attendance = $student_data[$attendance_field] ?: 'Not available';
                    ?>
                    <p><?php echo $semester === 'sem1' ? 'Semester 1' : 'Semester 2'; ?> Attendance: <?php echo $attendance; ?></p>
                </div>
            <?php } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') { ?>
                <p class="error">No student found with the provided details.</p>
            <?php } ?>
        </div>
    </div>
</body>
</html>