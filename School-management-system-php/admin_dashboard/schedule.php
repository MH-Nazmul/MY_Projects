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
    <title>Class Schedule</title>
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
            <h1>Class Schedule</h1>
            <?php if (isset($message)) echo "<p class='success'>$message</p>"; ?>
            <form method="POST">
                <label>Class:</label><input type="text" name="class" required>
                <label>Subject:</label><input type="text" name="subject" required>
                <label>Teacher:</label>
                <select name="teacher_id" required>
                    <option value="">--Select--</option>
                    <?php foreach ($teachers as $teacher) { ?>
                        <option value="<?php echo $teacher['id']; ?>"><?php echo $teacher['name']; ?></option>
                    <?php } ?>
                </select>
                <label>Day:</label><input type="text" name="day" required>
                <label>Time:</label><input type="text" name="time" required>
                <button type="submit">Add Schedule</button>
            </form>
            <h2>Current Schedule</h2>
            <table>
                <tr><th>Class</th><th>Subject</th><th>Teacher</th><th>Day</th><th>Time</th></tr>
                <?php foreach ($schedule as $entry) { ?>
                    <tr>
                        <td><?php echo $entry['class']; ?></td>
                        <td><?php echo $entry['subject']; ?></td>
                        <td><?php echo $entry['teacher_name']; ?></td>
                        <td><?php echo $entry['day']; ?></td>
                        <td><?php echo $entry['time']; ?></td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    </div>
</body>
</html>