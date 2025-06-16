<?php
// teacher/add_notices.php

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


// Handle form submission (Add or Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $action = $_POST['action'] ?? 'add';
    $notice_id = $_POST['notice_id'] ?? null;

    if (!empty($title) && !empty($description) && !empty($event_date) && !empty($due_date)) {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO events (event_title, event_description, event_date, due_date) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bindParam(1, $title, PDO::PARAM_STR);
                $stmt->bindParam(2, $description, PDO::PARAM_STR);
                $stmt->bindParam(3, $event_date, PDO::PARAM_STR);
                $stmt->bindParam(4, $due_date, PDO::PARAM_STR);
                if ($stmt->execute()) {
                    $message = "Notice added successfully!";
                } else {
                    $message = "Failed to add notice: " . implode(", ", $stmt->errorInfo());
                }
            } else {
                $message = "Database prepare error: " . implode(", ", $conn->errorInfo());
            }
        } elseif ($action === 'edit' && $notice_id) {
            $stmt = $conn->prepare("UPDATE events SET event_title = ?, event_description = ?, event_date = ?, due_date = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bindParam(1, $title, PDO::PARAM_STR);
                $stmt->bindParam(2, $description, PDO::PARAM_STR);
                $stmt->bindParam(3, $event_date, PDO::PARAM_STR);
                $stmt->bindParam(4, $due_date, PDO::PARAM_STR);
                $stmt->bindParam(5, $notice_id, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    $message = "Notice updated successfully!";
                } else {
                    $message = "Failed to update notice: " . implode(", ", $stmt->errorInfo());
                }
            } else {
                $message = "Database prepare error: " . implode(", ", $conn->errorInfo());
            }
        }
    } else {
        $message = "All fields are required.";
    }
}

// Delete expired notices
$current_date = date('Y-m-d');
$stmt = $conn->prepare("DELETE FROM events WHERE due_date < ?");
if ($stmt) {
    $stmt->bindParam(1, $current_date, PDO::PARAM_STR);
    $stmt->execute();
}

// Fetch current notices (events where due_date >= current_date)
$stmt = $conn->prepare("SELECT id, event_title, event_description, event_date, due_date FROM events WHERE due_date >= ? ORDER BY event_date DESC");
if (!$stmt) {
    die("Database prepare error: " . implode(", ", $conn->errorInfo()));
}
$stmt->bindParam(1, $current_date, PDO::PARAM_STR);
if (!$stmt->execute()) {
    die("Database error occurred: " . implode(", ", $stmt->errorInfo()));
}
$notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Print the query and results
echo "<!-- Debug: Current date: $current_date, Fetched notices: " . print_r($notices, true) . " -->";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Add Notices</title>
    <link rel="stylesheet" href="CSS/add_notices.css">
    
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php include 'teacher_sidebar.php'; ?>
        <!-- Main Content -->
        <div class="main-content">
            <!-- Add Notices -->
            <div class="dashboard-section">
                <h2>Add Notices</h2>
                <?php if (isset($message)): ?>
                    <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <div class="notice-form">
                    <form method="POST" action="add_notices.php">
                        <input type="hidden" name="action" value="add">
                        <label for="title">Title:</label>
                        <input type="text" id="title" name="title" value="" required>
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="4" required></textarea>
                        <label for="event_date">Event Date:</label>
                        <input type="date" id="event_date" name="event_date" value="<?php echo date('Y-m-d'); ?>" required>
                        <label for="due_date">Due Date:</label>
                        <input type="date" id="due_date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                        <button type="submit">Add Notice</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Side Panel for Current Notices -->
        <div class="side-panel">
            <h3>Current Notices</h3>
            <?php if (!empty($notices)): ?>
                <?php foreach ($notices as $notice): ?>
                    <div class="notice-item">
                        <h4><?php echo htmlspecialchars($notice['event_title']); ?></h4>
                        <p><?php echo htmlspecialchars($notice['event_description']); ?></p>
                        <small>Event: <?php echo htmlspecialchars($notice['event_date']); ?> | Due: <?php echo htmlspecialchars($notice['due_date']); ?></small>
                        <a href="?edit=<?php echo $notice['id']; ?>">Edit</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No current notices.</p>
            <?php endif; ?>

            <!-- Edit Form (shown when edit link is clicked) -->
            <?php
            if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
                $edit_id = (int)$_GET['edit'];
                $stmt = $conn->prepare("SELECT id, event_title, event_description, event_date, due_date FROM events WHERE id = ?");
                $stmt->bindParam(1, $edit_id, PDO::PARAM_INT);
                $stmt->execute();
                $edit_notice = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($edit_notice) {
                    echo '<div class="notice-form" style="margin-top: 20px;">';
                    echo '<form method="POST" action="add_notices.php">';
                    echo '<input type="hidden" name="action" value="edit">';
                    echo '<input type="hidden" name="notice_id" value="' . htmlspecialchars($edit_notice['id']) . '">';
                    echo '<label for="title">Title:</label>';
                    echo '<input type="text" id="title" name="title" value="' . htmlspecialchars($edit_notice['event_title']) . '" required>';
                    echo '<label for="description">Description:</label>';
                    echo '<textarea id="description" name="description" rows="4" required>' . htmlspecialchars($edit_notice['event_description']) . '</textarea>';
                    echo '<label for="event_date">Event Date:</label>';
                    echo '<input type="date" id="event_date" name="event_date" value="' . htmlspecialchars($edit_notice['event_date']) . '" required>';
                    echo '<label for="due_date">Due Date:</label>';
                    echo '<input type="date" id="due_date" name="due_date" value="' . htmlspecialchars($edit_notice['due_date']) . '" required>';
                    echo '<button type="submit">Update Notice</button>';
                    echo '</form>';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>