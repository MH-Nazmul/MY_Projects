<?php
// teacher/announcements.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

// Start the session
session_start();

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../logout.php");
    exit();
}

// Include database connection
include '../db_connect.php';

// Check database connection
if (!$conn) {
    die("Database connection failed.");
}

// Get the teacher's ID
$teacher_id = $_SESSION['user_id'];

// [CHANGE] Debug session data
error_log("Teacher ID: " . $teacher_id);

// Fetch the teacher's classes
$stmt = $conn->prepare("
    SELECT DISTINCT class 
    FROM class_routines 
    WHERE teacher_id = ?
    ORDER BY class
");
if (!$stmt) {
    die("Database prepare error: " . $conn->errorInfo()[2]);
}
$stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
if (!$stmt->execute()) {
    die("Database execute error: " . $stmt->errorInfo()[2]);
}
$classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// [CHANGE] Debug the fetched classes
error_log("Fetched classes: " . print_r($classes, true));

// Handle announcement submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement'])) {
    $title = trim($_POST['title'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    $class = $_POST['class'] ?? '';
    $announcement_date = $_POST['announcement_date'] ?? date('Y-m-d');

    if (!empty($title) && !empty($message_text) && !empty($class)) {
        $stmt = $conn->prepare("
            INSERT INTO announcements (teacher_id, class, title, message, announcement_date)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $class, PDO::PARAM_STR);
        $stmt->bindParam(3, $title, PDO::PARAM_STR);
        $stmt->bindParam(4, $message_text, PDO::PARAM_STR);
        $stmt->bindParam(5, $announcement_date, PDO::PARAM_STR);
        if ($stmt->execute()) {
            $message = "Announcement added successfully!";
        } else {
            $message = "Failed to add announcement.";
        }
    } else {
        $message = "All fields are required.";
    }
}

// Handle urgent notification submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['urgent'])) {
    $urgent_message = trim($_POST['urgent_message'] ?? '');
    if (!empty($urgent_message)) {
        $stmt = $conn->prepare("
            INSERT INTO urgent_notifications (teacher_id, message)
            VALUES (?, ?)
        ");
        $stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $urgent_message, PDO::PARAM_STR);
        if ($stmt->execute()) {
            $urgent_message = "Urgent notification sent successfully!";
        } else {
            $urgent_message = "Failed to send urgent notification.";
        }
    } else {
        $urgent_message = "Message is required.";
    }
}

// Fetch past announcements
$stmt = $conn->prepare("
    SELECT title, message, class, announcement_date 
    FROM announcements 
    WHERE teacher_id = ? 
    ORDER BY announcement_date DESC
");
$stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Announcements</title>
    <link rel="stylesheet" href="CSS/announcements.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php include 'teacher_sidebar.php' ?>

        <!-- Main Content -->
        <main>
            <!-- Announcements -->
            <div class="dashboard-section">
                <h2>Announcements</h2>
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <div class="announcement-form">
                    <form method="POST" action="announcements.php">
                        <input type="hidden" name="announcement" value="1">
                        <label for="title">Title:</label>
                        <input type="text" id="title" name="title" required>
                        <label for="message">Message:</label>
                        <textarea id="message" name="message" rows="4" required></textarea>
                        <label for="class">Class:</label>
                        <select id="class" name="class" required>
                            <option value="">Select a class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class); ?>"><?php echo htmlspecialchars($class); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="announcement_date">Date:</label>
                        <input type="date" id="announcement_date" name="announcement_date" value="<?php echo date('Y-m-d'); ?>" required>
                        <button type="submit">Add Announcement</button>
                    </form>
                </div>
                <table>
                    <tr>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Class</th>
                        <th>Date</th>
                    </tr>
                    <?php if (!empty($announcements)): ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                <td><?php echo htmlspecialchars($announcement['message']); ?></td>
                                <td><?php echo htmlspecialchars($announcement['class']); ?></td>
                                <td><?php echo htmlspecialchars($announcement['announcement_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No announcements yet.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </main>

        <!-- Side Panel for Urgent Notifications -->
        <div class="side-panel">
            <h3>Urgent Notifications</h3>
            <?php if (!empty($urgent_message)): ?>
                <div class="message <?php echo strpos($urgent_message, 'successfully') !== false ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($urgent_message); ?>
                </div>
            <?php endif; ?>
            <div class="urgent-form">
                <form method="POST" action="announcements.php">
                    <input type="hidden" name="urgent" value="1">
                    <label for="urgent_message">Message to Admin:</label>
                    <textarea id="urgent_message" name="urgent_message" placeholder="Enter urgent message..." required></textarea>
                    <button type="submit">Send Urgent Notification</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>