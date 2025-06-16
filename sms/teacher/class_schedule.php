<?php
// teacher/class_schedule.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

// Start the session
session_start();

// Debug session variables
error_log("Session: user_id=" . ($_SESSION['user_id'] ?? 'not set') . ", user_type=" . ($_SESSION['user_type'] ?? 'not set'));

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
error_log("DB Connection: " . ($conn ? 'Success' : 'Failed'));

// Get the teacher's ID
$teacher_id = $_SESSION['user_id'];
$current_day = date('l'); // Today is Monday, June 09, 2025

// Fetch today's schedule (including the schedule ID)
$stmt = $conn->prepare("
    SELECT s.id AS schedule_id, sub.subject_name, c.class_name, s.period, s.start_time, s.end_time 
    FROM schedules s
    JOIN subjects sub ON sub.id = s.subject_id
    JOIN classes c ON c.id = s.class_id
    WHERE s.teacher_id = ? AND s.day = ?
    ORDER BY s.period
");
if (!$stmt) {
    die("Database prepare error: " . $conn->errorInfo()[2]);
}
$stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
$stmt->bindParam(2, $current_day, PDO::PARAM_STR);
if (!$stmt->execute()) {
    die("Database error occurred: " . $stmt->errorInfo()[2]);
}
$schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle reschedule or cancel request
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_id = $_POST['schedule_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $new_date = $_POST['new_date'] ?? '';
    $explanation = trim($_POST['explanation'] ?? '');

    if (!empty($schedule_id) && !empty($action) && !empty($explanation)) {
        $conn->beginTransaction();
        try {
            // Fetch the original schedule details
            $stmt = $conn->prepare("SELECT day, start_time, end_time FROM schedules WHERE id = ?");
            $stmt->bindParam(1, $schedule_id, PDO::PARAM_INT);
            $stmt->execute();
            $original = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$original) {
                throw new Exception("Schedule ID not found.");
            }

            if ($action === 'reschedule' && !empty($new_date)) {
                $new_day = date('l', strtotime($new_date));
                $stmt = $conn->prepare("UPDATE schedules SET day = ? WHERE id = ?");
                $stmt->bindParam(1, $new_day, PDO::PARAM_STR);
                $stmt->bindParam(2, $schedule_id, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    // Log the change in schedule_changes
                    $stmt = $conn->prepare("
                        INSERT INTO schedule_changes (teacher_id, schedule_id, action, original_date, new_date, explanation) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
                    $stmt->bindParam(2, $schedule_id, PDO::PARAM_INT);
                    $stmt->bindParam(3, $action, PDO::PARAM_STR);
                    $stmt->bindParam(4, $original['day'], PDO::PARAM_STR);
                    $stmt->bindParam(5, $new_date, PDO::PARAM_STR);
                    $stmt->bindParam(6, $explanation, PDO::PARAM_STR);
                    $stmt->execute();
                    $message = "Class rescheduled successfully!";
                }
            } elseif ($action === 'cancel') {
                $stmt = $conn->prepare("DELETE FROM schedules WHERE id = ?");
                $stmt->bindParam(1, $schedule_id, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    // Log the cancellation in schedule_changes
                    $stmt = $conn->prepare("
                        INSERT INTO schedule_changes (teacher_id, schedule_id, action, original_date, explanation) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
                    $stmt->bindParam(2, $schedule_id, PDO::PARAM_INT);
                    $stmt->bindParam(3, $action, PDO::PARAM_STR);
                    $stmt->bindParam(4, $original['day'], PDO::PARAM_STR);
                    $stmt->bindParam(5, $explanation, PDO::PARAM_STR);
                    $stmt->execute();
                    $message = "Class canceled successfully!";
                }
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $message = "Invalid request. All fields are required.";
    }

    // Redirect to avoid form resubmission on refresh
    header("Location: class_schedule.php?message=" . urlencode($message));
    exit();
}

// Check for message in URL (after redirect)
$message = $_GET['message'] ?? $message;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Class Schedule</title>
    <link rel="stylesheet" href="CSS/class_schedule.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php include 'teacher_sidebar.php' ?>

        <!-- Main Content -->
        <main>
            <!-- Class Schedule -->
            <div class="dashboard-section">
                <h2>Today's Class Schedule (<?php echo htmlspecialchars($current_day); ?>)</h2>
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <table>
                    <tr>
                        <th>Period</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                    <?php if (!empty($schedule)): ?>
                        <?php foreach ($schedule as $class): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($class['period']); ?></td>
                                <td><?php echo htmlspecialchars($class['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                <td><?php echo htmlspecialchars($class['start_time'] . ' - ' . $class['end_time']); ?></td>
                                <td>
                                    <form class="action-form">
                                        <input type="hidden" name="schedule_id" value="<?php echo htmlspecialchars($class['schedule_id']); ?>">
                                        <button type="button" onclick="openModal('reschedule', <?php echo htmlspecialchars($class['schedule_id']); ?>)">Reschedule</button>
                                    </form>
                                    <form class="action-form">
                                        <input type="hidden" name="schedule_id" value="<?php echo htmlspecialchars($class['schedule_id']); ?>">
                                        <button type="button" onclick="openModal('cancel', <?php echo htmlspecialchars($class['schedule_id']); ?>)">Cancel</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No classes scheduled for today.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal for Reschedule/Cancel -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">×</span>
            <h3 id="modal-title"></h3>
            <form method="POST" action="class_schedule.php">
                <input type="hidden" name="schedule_id" id="modal-schedule-id">
                <input type="hidden" name="action" id="modal-action">
                <div id="new-date-container">
                    <label for="new_date">New Date (for Reschedule):</label>
                    <input type="date" id="new_date" name="new_date" min="<?php echo date('Y-m-d'); ?>">
                </div>
                <label for="explanation">Explanation:</label>
                <textarea id="explanation" name="explanation" rows="4" required></textarea>
                <button type="submit">Submit</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(action, scheduleId) {
            const modal = document.getElementById('modal');
            const title = document.getElementById('modal-title');
            const scheduleInput = document.getElementById('modal-schedule-id');
            const actionInput = document.getElementById('modal-action');
            const newDateContainer = document.getElementById('new-date-container');
            const newDateInput = document.getElementById('new_date');

            title.textContent = action === 'reschedule' ? 'Reschedule Class' : 'Cancel Class';
            scheduleInput.value = scheduleId;
            actionInput.value = action;

            // Show or hide the date input based on action
            if (action === 'reschedule') {
                newDateContainer.style.display = 'block';
                newDateInput.setAttribute('required', 'required');
            } else {
                newDateContainer.style.display = 'none';
                newDateInput.removeAttribute('required');
            }

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