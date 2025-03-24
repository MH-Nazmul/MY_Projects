<?php
// admin_dashboard.php

// Start the session
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}

include 'db_connect.php';

// if ($_SERVER['REQUEST_METHOD'] == 'POST') {
//     $school_name = $_POST['school_name'];
//     $home_text = $_POST['home_text'];
//     $about_text = $_POST['about_text'];
//     $stmt = $conn->prepare("UPDATE settings SET school_name = ?, home_text = ?, about_text = ? WHERE id = 1");
//     $stmt->execute([$school_name, $home_text, $about_text]);
//     $message = "Settings updated!";
// }

// $settings = $conn->query("SELECT * FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Settings</title>
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
            <h1>School Settings</h1>
            <?php if (isset($message)) echo "<p class='success'>$message</p>"; ?>
            <form method="POST">
                <label>School Name:</label><input type="text" name="school_name" value="<?php echo $settings['school_name']; ?>" required>
                <label>Home Text:</label><textarea name="home_text"><?php echo $settings['home_text']; ?></textarea>
                <label>About Text:</label><textarea name="about_text"><?php echo $settings['about_text']; ?></textarea>
                <button type="submit">Save Changes</button>
            </form>
        </div>
    </div>
</body>
</html>