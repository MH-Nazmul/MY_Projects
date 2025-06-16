<?php
// teacher/exam_dates.php

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

// Fetch the teacher's subjects and classes from Schedules
$stmt = $conn->prepare("
    SELECT DISTINCT subject_name, class_name as class 
    FROM schedules s
    JOIN classes c ON s.class_id = c.id
    JOIN subjects sub ON sub.id = s.subject_id
    WHERE teacher_id = ?
    ORDER BY subject_name, class
");
if (!$stmt) {
    die("Database prepare error.");
}
$stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
if (!$stmt->execute()) {
    die("Database error occurred.");
}
$subjects_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing exam dates for the teacher's classes
$existing_exams = [];
foreach ($subjects_classes as $sc) {
    $stmt = $conn->prepare("
        SELECT exam_name, exam_date 
        FROM exams 
        WHERE class = ? AND exam_name = ?
        LIMIT 1
    ");
    $stmt->bindParam(1, $sc['class'], PDO::PARAM_STR);
    $stmt->bindParam(2, $sc['subject_name'], PDO::PARAM_STR);
    $stmt->execute();
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exam) {
        $existing_exams[$sc['subject_name'] . '|' . $sc['class']] = $exam['exam_date'];
    }
}

// Handle exam date request
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $routine_key = $_POST['routine_key'] ?? '';
    $action = $_POST['action'] ?? '';
    $new_date = $_POST['new_date'] ?? '';
    $explanation = trim($_POST['explanation'] ?? '');
    list($subject_name, $class) = explode('|', $routine_key);

    if (!empty($routine_key) && !empty($action) && !empty($explanation)) {
        $conn->beginTransaction();
        try {
            $original_date = $existing_exams[$routine_key] ?? null;
            $stmt = $conn->prepare("
                INSERT INTO exam_requests (teacher_id, class_routine_id, subject_name, class, requested_date, original_date, explanation)
                VALUES ((SELECT id FROM teachers WHERE id = ?), 
                        (SELECT id FROM class_routines WHERE teacher_id = ? AND subject_name = ? AND class = ? LIMIT 1), 
                        ?, ?, ?, ?, ?)
            ");
            $stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $teacher_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $subject_name, PDO::PARAM_STR);
            $stmt->bindParam(4, $class, PDO::PARAM_STR);
            $stmt->bindParam(5, $subject_name, PDO::PARAM_STR);
            $stmt->bindParam(6, $class, PDO::PARAM_STR);
            $stmt->bindParam(7, $new_date, PDO::PARAM_STR);
            $stmt->bindParam(8, $original_date, PDO::PARAM_STR);
            $stmt->bindParam(9, $explanation, PDO::PARAM_STR);
            if ($stmt->execute()) {
                $message = "Exam date " . ($original_date ? 'change' : 'proposal') . " submitted successfully!";
            } else {
                $message = "Failed to submit request.";
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $message = "Invalid request. All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Exam Dates</title>
    <link rel="stylesheet" href="CSS/exam_dates.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php include 'teacher_sidebar.php' ?>

        <!-- Main Content -->
        <main>
            <!-- Exam Dates -->
            <div class="dashboard-section">
                <h2>Exam Dates</h2>
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <table>
                    <tr>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Current Exam Date</th>
                        <th>Actions</th>
                    </tr>
                    <?php if (!empty($subjects_classes)): ?>
                        <?php foreach ($subjects_classes as $sc): ?>
                            <?php
                                $routine_key = $sc['subject_name'] . '|' . $sc['class'];
                                $current_date = $existing_exams[$routine_key] ?? 'Not scheduled';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sc['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($sc['class']); ?></td>
                                <td><?php echo htmlspecialchars($current_date); ?></td>
                                <td>
                                    <form class="action-form" method="POST" action="exam_dates.php">
                                        <input type="hidden" name="routine_key" value="<?php echo htmlspecialchars($routine_key); ?>">
                                        <button type="button" onclick="openModal('propose', '<?php echo htmlspecialchars($routine_key); ?>', '<?php echo htmlspecialchars($current_date); ?>')">Propose Date</button>
                                    </form>
                                    <?php if ($current_date !== 'Not scheduled'): ?>
                                        <form class="action-form" method="POST" action="exam_dates.php">
                                            <input type="hidden" name="routine_key" value="<?php echo htmlspecialchars($routine_key); ?>">
                                            <button type="button" onclick="openModal('change', '<?php echo htmlspecialchars($routine_key); ?>', '<?php echo htmlspecialchars($current_date); ?>')">Change Date</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No subjects or classes assigned.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal for Exam Date Request -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">×</span>
            <h3 id="modal-title"></h3>
            <form method="POST" action="exam_dates.php">
                <input type="hidden" name="routine_key" id="modal-routine-key">
                <input type="hidden" name="action" id="modal-action">
                <label for="new_date">New Exam Date:</label>
                <input type="date" id="new_date" name="new_date" min="<?php echo date('Y-m-d'); ?>" required>
                <label for="explanation">Explanation:</label>
                <textarea id="explanation" name="explanation" rows="4" required></textarea>
                <button type="submit">Submit Request</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(action, routineKey, currentDate) {
            const modal = document.getElementById('modal');
            const title = document.getElementById('modal-title');
            const routineInput = document.getElementById('modal-routine-key');
            const actionInput = document.getElementById('modal-action');
            const newDate = document.getElementById('new_date');

            title.textContent = action === 'propose' ? 'Propose Exam Date' : 'Change Exam Date';
            routineInput.value = routineKey;
            actionInput.value = action;
            newDate.value = ''; // Reset date field
            modal.style.display = 'block';
        }

        function closeModal() {
            const modal = document.getElementById('modal');
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>