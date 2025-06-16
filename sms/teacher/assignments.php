<?php
// teacher/assignments.php

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
$message = '';

// Fetch the teacher's classes
$stmt = $conn->prepare("
    SELECT DISTINCT c.class_name AS class
    FROM classes c
    JOIN schedules s ON c.id = s.class_id
    WHERE s.teacher_id = ?
    ORDER BY c.class_name
");
$stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
$stmt->execute();
$classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch subjects for the selected class
$selected_class = $_GET['class'] ?? $classes[0] ?? '';
$subjects = [];
if ($selected_class) {
    $stmt = $conn->prepare("
        SELECT DISTINCT sub.subject_name AS subject
        FROM schedules s
        JOIN subjects sub ON s.subject_id = sub.id
        WHERE s.teacher_id = ? AND s.class_id IN (
            SELECT id FROM classes WHERE class_name = ?
        )
        ORDER BY sub.subject_name
    ");
    $stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $selected_class, PDO::PARAM_STR);
    $stmt->execute();
    $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $class = $_POST['class'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $due_date = $_POST['due_date'] ?? '';

    if (!empty($class) && !empty($subject) && !empty($title) && !empty($due_date)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO assignments (teacher_id, class, subject, title, description, due_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $class, PDO::PARAM_STR);
            $stmt->bindParam(3, $subject, PDO::PARAM_STR);
            $stmt->bindParam(4, $title, PDO::PARAM_STR);
            $stmt->bindParam(5, $description, PDO::PARAM_STR);
            $stmt->bindParam(6, $due_date, PDO::PARAM_STR);
            $stmt->execute();
            $message = "Assignment created successfully!";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $message = "Please fill in all required fields.";
    }
}

// Fetch existing assignments for the teacher
$stmt = $conn->prepare("
    SELECT id, class, subject, title, description, due_date, created_at
    FROM assignments
    WHERE teacher_id = ?
    ORDER BY due_date DESC
");
$stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Assignments</title>
    <link rel="stylesheet" href="CSS/assignments.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php include 'teacher_sidebar.php' ?>

        <!-- Main Content -->
        <main>
            <!-- Assignments -->
            <div class="dashboard-section">
                <h2>Assignments</h2>
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <div class="assignment-form">
                    <form method="POST" action="assignments.php">
                        <label for="class">Class:</label>
                        <select name="class" id="class" required onchange="this.form.submit()">
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class); ?>" <?php echo $selected_class === $class ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="subject">Subject:</label>
                        <select name="subject" id="subject" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo htmlspecialchars($subject); ?>">
                                    <?php echo htmlspecialchars($subject); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="title">Title:</label>
                        <input type="text" name="title" id="title" required placeholder="Enter Assignment Title">
                        <label for="description">Description:</label>
                        <textarea name="description" id="description" rows="4" placeholder="Enter Assignment Description"></textarea>
                        <label for="due_date">Due Date:</label>
                        <input type="date" name="due_date" id="due_date" required>
                        <button type="submit" name="submit_assignment">Create Assignment</button>
                    </form>
                </div>
                <div class="assignments-list">
                    <h3>Existing Assignments</h3>
                    <?php if (!empty($assignments)): ?>
                        <table>
                            <tr>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Due Date</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment['class']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['description']); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($assignment['due_date']))); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($assignment['created_at']))); ?></td>
                                    <td class="actions">
                                        <a href="#">Edit</a>
                                        <a href="#">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php else: ?>
                        <p>No assignments created yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>