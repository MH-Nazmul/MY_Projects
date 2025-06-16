<?php
// attendance.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set timezone
date_default_timezone_set('Asia/Dhaka');

// Prevent browser caching
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Start output buffering
ob_start();

// Start the session
session_start();

// Check session contents
if (!isset($_SESSION['username']) && !isset($_SESSION['user_id'])) {
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
$teacher_id = null;
if (isset($_SESSION['user_id'])) {
    $teacher_id = $_SESSION['user_id'];
} else {
    $username = $_SESSION['username'] ?? null;
    if (!$username) {
        header("Location: ../logout.php");
        exit();
    }

    $teacher_query = "SELECT id AS user_id FROM teachers WHERE username = ?";
    $teacher_stmt = $conn->prepare($teacher_query);
    $teacher_stmt->bindParam(1, $username, PDO::PARAM_STR);
    $teacher_stmt->execute();
    $teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        die("Teacher not found for username: " . htmlspecialchars($username));
    }
    $teacher_id = $teacher['user_id'];
}

// Check user type
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../logout.php");
    exit();
}

$message = '';

// Fetch classes assigned to the teacher
$classes_query = "SELECT c.id, c.class_name FROM classes c
                 JOIN schedules s ON c.id = s.class_id
                 WHERE s.teacher_id = ?";
$classes_stmt = $conn->prepare($classes_query);
$classes_stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
$classes_stmt->execute();
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);
error_log("Fetched " . count($classes) . " classes for teacher_id $teacher_id");

// Handle year and month selection
$selected_year = $_POST['year'] ?? $_GET['year'] ?? date('Y');
$selected_month = $_POST['month'] ?? $_GET['month'] ?? date('m');
$selected_class_name = $_POST['class_name'] ?? $_GET['class_name'] ?? null;
$selected_month_full = sprintf("%04d-%02d", $selected_year, $selected_month);
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);
$first_day = "$selected_year-$selected_month-01";
$last_day = date('Y-m-t', strtotime($first_day));

// Fetch weekend days
$weekends_query = "SELECT day FROM weekends";
$weekends_stmt = $conn->prepare($weekends_query);
try {
    $weekends_stmt->execute();
    $weekend_days = array_column($weekends_stmt->fetchAll(PDO::FETCH_ASSOC), 'day');
} catch (PDOException $e) {
    error_log("Weekends query failed: " . $e->getMessage());
    $weekend_days = [];
}

// Fetch students and attendance
$students = [];
$attendance_records = [];
if ($selected_class_name) {
    // Fetch students in the selected class
    $students_query = "SELECT s.id, CONCAT(s.fname, ' ', s.lname) AS name FROM students s WHERE s.class = ?";
    $students_stmt = $conn->prepare($students_query);
    $students_stmt->bindParam(1, $selected_class_name, PDO::PARAM_STR);
    $students_stmt->execute();
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Fetched " . count($students) . " students for class $selected_class_name");

    // Fetch attendance records for the month
    if (!empty($students)) {
        $student_ids = array_column($students, 'id');
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $attendance_query = "
            SELECT student_id, status, DATE(date) AS date 
            FROM attendance 
            WHERE student_id IN ($placeholders) 
            AND DATE_FORMAT(date, '%Y-%m') = ? 
            AND teacher_id = ? 
            AND class = ?
        ";
        $attendance_stmt = $conn->prepare($attendance_query);
        foreach ($student_ids as $index => $student_id) {
            $attendance_stmt->bindValue($index + 1, $student_id, PDO::PARAM_INT);
        }
        $attendance_stmt->bindValue(count($student_ids) + 1, $selected_month_full, PDO::PARAM_STR);
        $attendance_stmt->bindValue(count($student_ids) + 2, $teacher_id, PDO::PARAM_INT);
        $attendance_stmt->bindValue(count($student_ids) + 3, $selected_class_name, PDO::PARAM_STR);
        $attendance_stmt->execute();
        $attendance_records = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Fetched " . count($attendance_records) . " attendance records for month $selected_month_full, teacher_id $teacher_id, class $selected_class_name");

        // Organize attendance by student and date
        $attendance_by_date = [];
        foreach ($attendance_records as $record) {
            $student_id = $record['student_id'];
            $date = $record['date'];
            $attendance_by_date[$student_id][$date] = ($record['status'] === 'present') ? 'checked' : '';
        }
        error_log("Attendance by date: " . print_r($attendance_by_date, true));
    }
}

// Generate year and month options
$years = range(date('Y') - 5, date('Y') + 5);
$months = [
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Attendance</title>
    <link rel="stylesheet" href="CSS/attendance.css">
    <script>
        function saveAttendance(studentId, date, isChecked) {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "save_attendance.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

            const data = `student_id=${studentId}&date=${date}&status=${isChecked ? 'present' : 'absent'}&class_name=${encodeURIComponent('<?php echo $selected_class_name; ?>')}&teacher_id=<?php echo $teacher_id; ?>`;
            xhr.send(data);

            xhr.onload = function() {
                const messageDiv = document.getElementById('message');
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            messageDiv.className = 'message success';
                            messageDiv.textContent = 'Attendance updated successfully!';
                        } else {
                            messageDiv.className = 'message error';
                            messageDiv.textContent = 'Error: ' + response.error;
                        }
                    } catch (e) {
                        messageDiv.className = 'message error';
                        messageDiv.textContent = 'Error parsing response: ' + e.message;
                    }
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = 'Server error: ' + xhr.status;
                }
                messageDiv.style.display = 'block';
                setTimeout(() => { messageDiv.style.display = 'none'; }, 3000);
            };
        }
    </script>
</head>
<body>
    <!-- Message Pop-up -->
    <div id="message" class="message"></div>

    <div class="container">
        <!-- Sidebar -->
        <?php include 'teacher_sidebar.php' ?>

        <!-- Main Content -->
        <main>
            <div class="dashboard-section">
                <h2>Attendance Records for <?php echo $months[$selected_month] . ' ' . $selected_year; ?></h2>

                <!-- Class, Year, Month Selection Form -->
                <div class="class-list">
                    <form method="POST" action="">
                        <label for="class_name">Class:</label>
                        <select id="class_name" name="class_name" onchange="this.form.submit()" required>
                            <option value="">-- Select a Class --</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class['class_name']); ?>" <?php echo $selected_class_name == $class['class_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="year">Year:</label>
                        <select id="year" name="year" onchange="this.form.submit()">
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $selected_year == $year ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="month">Month:</label>
                        <select id="month" name="month" onchange="this.form.submit()">
                            <?php foreach ($months as $m => $name): ?>
                                <option value="<?php echo $m; ?>" <?php echo $selected_month == $m ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <!-- Monthly Attendance Table -->
                <?php if ($selected_class_name && !empty($students)): ?>
                    <div class="table-container">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th class="student-id">ID</th>
                                    <th class="student-name">Student Name</th>
                                    <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                                        <?php
                                        $date = date('Y-m-d', strtotime("$first_day +$day days -1 day"));
                                        $day_name = date('l', strtotime($date));
                                        $is_weekend = in_array($day_name, $weekend_days);
                                        ?>
                                        <th class="<?php echo $is_weekend ? 'weekend' : ''; ?>">
                                            <?php echo $day; ?><br><?php echo date('D', strtotime($date)); ?>
                                        </th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td class="student-id"><?php echo htmlspecialchars($student['id']); ?></td>
                                        <td class="student-name"><?php echo htmlspecialchars($student['name']); ?></td>
                                        <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                                            <?php
                                            $date = date('Y-m-d', strtotime("$first_day +$day days -1 day"));
                                            $is_weekend = in_array(date('l', strtotime($date)), $weekend_days);
                                            $student_id = $student['id'];
                                            $checked = isset($attendance_by_date[$student_id][$date]) && $attendance_by_date[$student_id][$date] === 'checked' ? 'checked' : '';
                                            error_log("Display: student_id $student_id, date $date, checked: " . ($checked ? 'yes' : 'no'));
                                            ?>
                                            <td class="<?php echo $is_weekend ? 'weekend' : ''; ?>">
                                                <?php if (!$is_weekend): ?>
                                                    <input type="checkbox" 
                                                           onchange="saveAttendance(<?php echo $student_id; ?>, '<?php echo $date; ?>', this.checked)"
                                                           <?php echo $checked; ?>>
                                                <?php endif; ?>
                                            </td>
                                        <?php endfor; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($selected_class_name): ?>
                    <p>No students found in this class.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>
