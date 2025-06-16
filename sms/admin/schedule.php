<?php
session_start();
include '../db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: ../logout.php");
    exit();
}

try {
    $stmt = $conn->prepare("SELECT school_name, tag_line FROM settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $school_name = $settings['school_name'] ?? 'School Name';
    $tag_line = $settings['tag_line'] ?? 'Tagline';
} catch (PDOException $e) {
    $school_name = 'School Name';
    $tag_line = 'Tagline';
    error_log("Error fetching settings: " . $e->getMessage());
}

$classes = [];
try {
    $stmt = $conn->prepare("SELECT id, class_name FROM classes ORDER BY id DESC");
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Fetched classes: " . print_r($classes, true));
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
}

$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$valid_class_ids = array_column($classes, 'id');
if (!in_array($selected_class_id, $valid_class_ids) && !empty($classes)) {
    $selected_class_id = $classes[0]['id'];
}
error_log("Final selected class_id: " . $selected_class_id);

$selected_class_name = '';
if ($selected_class_id && !empty($classes)) {
    foreach ($classes as $class) {
        if ($class['id'] == $selected_class_id) {
            $selected_class_name = $class['class_name'];
            break;
        }
    }
}

$class_subjects = [];
if ($selected_class_id) {
    try {
        $stmt = $conn->prepare("
            SELECT s.id, s.subject_name 
            FROM class_subjects cs
            JOIN subjects s ON cs.subject_id = s.id
            WHERE cs.class_id = ?
            ORDER BY s.subject_name
        ");
        $stmt->execute([$selected_class_id]);
        $class_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Subjects for class $selected_class_id: " . print_r($class_subjects, true));
    } catch (PDOException $e) {
        error_log("Error fetching subjects: " . $e->getMessage());
    }
}

$teachers_for_class = [];
if ($selected_class_id) {
    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT t.id, CONCAT(t.fname, ' ', t.lname) AS full_name
            FROM teachers t
            JOIN teacher_subjects ts ON t.id = ts.teacher_id
            JOIN class_subjects cs ON ts.class_subject_id = cs.id
            WHERE cs.class_id = ?
            ORDER BY t.fname, t.lname
        ");
        $stmt->execute([$selected_class_id]);
        $teachers_for_class = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Teachers for class $selected_class_id: " . print_r($teachers_for_class, true));
    } catch (PDOException $e) {
        error_log("Error fetching teachers for class: " . $e->getMessage());
    }
}

$schedules = [];
$max_periods = 4;
if ($selected_class_id) {
    try {
        $stmt = $conn->prepare("
            SELECT id, day, period, start_time, end_time, subject_id, teacher_id 
            FROM schedules 
            WHERE class_id = ?
            ORDER BY FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), period
        ");
        $stmt->execute([$selected_class_id]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $period_numbers = array_column($schedules, 'period');
        $max_periods = !empty($period_numbers) ? max($period_numbers) : 4;
    } catch (PDOException $e) {
        error_log("Error fetching schedules: " . $e->getMessage());
    }
}

$weekend_days = [];
try {
    $stmt = $conn->prepare("SELECT day FROM weekends");
    $stmt->execute();
    $weekend_days = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'day');
} catch (PDOException $e) {
    error_log("Error fetching weekend days: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_period'])) {
    $day = $_POST['day'];
    $period = (int)$_POST['period'];
    $start_time = $_POST['start_time'] ?: '00:00:00';
    $end_time = $_POST['end_time'] ?: '00:00:00';
    $subject_id = $_POST['subject_id'] ?: null;
    $teacher_id = $_POST['teacher_id'] ?: null;

    try {
        $stmt = $conn->prepare("INSERT INTO schedules (class_id, day, period, start_time, end_time, subject_id, teacher_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$selected_class_id, $day, $period, $start_time, $end_time, $subject_id, $teacher_id]);
        header("Location: schedule.php?class_id=$selected_class_id");
        exit();
    } catch (PDOException $e) {
        $error_message = "Failed to add period: " . $e->getMessage();
        error_log("Error adding period: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_weekends'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM weekends");
        $stmt->execute();
        if (!empty($_POST['weekend_days'])) {
            $stmt = $conn->prepare("INSERT INTO weekends (day) VALUES (?)");
            foreach ($_POST['weekend_days'] as $day) {
                if (in_array($day, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])) {
                    $stmt->execute([$day]);
                }
            }
        }
        header("Location: schedule.php?class_id=$selected_class_id");
        exit();
    } catch (PDOException $e) {
        $error_message = "Failed to update weekend days: " . $e->getMessage();
        error_log("Error updating weekends: " . $e->getMessage());
    }
}

$hide_weekends = isset($_GET['hide_weekends']) ? (bool)$_GET['hide_weekends'] : false;
$schedule_by_day = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
foreach ($days as $day) {
    $schedule_by_day[$day] = [];
    foreach ($schedules as $sched) {
        if ($sched['day'] == $day) {
            $schedule_by_day[$day][$sched['period']] = $sched;
        }
    }
}
$display_days = $hide_weekends ? array_diff($days, $weekend_days) : $days;
$error_message = isset($error_message) ? $error_message : '';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Schedule - School Management System</title>
    <link rel="stylesheet" href="CSS/admin.css">
    <style>
        .title-section {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #2c3e50;
            color: white;
            border-radius: 8px;
        }
        .title-section h1 {
            margin: 0;
            font-size: 24px;
        }
        .title-section p {
            margin: 5px 0 0;
            font-size: 16px;
            color: #ddd;
        }
        .class-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 8px;
        }
        .class-bar button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background: #007bff;
            color: white;
            cursor: pointer;
            transition: background 0.3s;
        }
        .class-bar button:hover {
            background: #0056b3;
        }
        .class-bar button.active {
            background: #28a745;
        }
        .weekend-toggle-btn {
            position: relative;
            padding: 8px 16px;
            background: #ff9800;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            z-index: 1000;
            transition: background 0.3s;
        }
        .weekend-toggle-btn:hover {
            background: #e68900;
        }
        .weekend-selection {
            position: fixed;
            top: 60px;
            right: 20px;
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: none; /* Hidden by default */
        }
        .weekend-selection .days {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }
        .weekend-selection .days label {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .weekend-selection button {
            padding: 5px 10px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .weekend-selection button:hover {
            background: #218838;
        }
        .weekend-selection .toggle {
            margin-top: 10px;
        }
        .weekend-selection .toggle label {
            margin-left: 5px;
        }
        .schedule-section {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }
        .schedule-section h2 {
            margin-top: 0;
            font-size: 20px;
            color: #2c3e50;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .schedule-table th, .schedule-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .schedule-table th {
            background: #f5f5f5;
            color: #333;
            font-weight: bold;
        }
        .schedule-table tr:hover {
            background: #f9f9f9;
        }
        .schedule-table .weekend-row {
            background: #f0f0f0;
        }
        .schedule-table .weekend-cell {
            color: #666;
            text-align: left;
            font-style: italic;
        }
        .period-cell {
            cursor: pointer;
            position: relative;
        }
        .period-cell.disabled {
            cursor: not-allowed;
            background: #f0f0f0;
            color: #666;
        }
        .period-cell .display {
            padding: 5px;
            white-space: nowrap; /* Prevent text from wrapping */
        }
        .period-cell .display div {
            margin: 0; /* Remove extra margins */
        }
        .period-cell .edit {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            padding: 10px;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .period-cell .edit input[type="time"],
        .period-cell .edit select {
            width: 100%;
            padding: 5px;
            margin-bottom: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .period-cell .edit button {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        .period-cell .edit .save-btn {
            background-color: #28a745;
            color: white;
        }
        .period-cell .edit .save-btn:hover {
            background-color: #218838;
        }
        .period-cell .edit .cancel-btn {
            background-color: #dc3545;
            color: white;
        }
        .period-cell .edit .cancel-btn:hover {
            background-color: #c82333;
        }
        .period-cell .edit button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .action-buttons {
            margin-top: 10px;
            text-align: right;
        }
        .action-buttons button {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 5px;
            transition: background 0.3s;
        }
        .action-buttons .add-btn {
            background: #007bff;
            color: white;
        }
        .action-buttons .add-btn:hover {
            background: #0056b3;
        }
        .action-buttons .delete-btn {
            background: #dc3545;
            color: white;
        }
        .action-buttons .delete-btn:hover {
            background: #c82333;
        }
        .error-message, .empty-message {
            text-align: center;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error-message {
            color: #dc3545;
            background: #f8d7da;
        }
        .empty-message {
            color: #666;
            background: #f1f1f1;
        }
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: #fff;
            font-size: 16px;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }
        .toast.success {
            background-color: #28a745;
        }
        .toast.error {
            background-color: #dc3545;
        }
        .toast.show {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="container">
       <?php include 'admin_sidebar.php'; ?>
        <div class="main-content">
            <div class="title-section">
                <h1><?php echo htmlspecialchars($school_name); ?></h1>
                <p><?php echo htmlspecialchars($tag_line); ?></p>
            </div>
            <div class="class-bar">
                <?php if (empty($classes)) { ?>
                    <p>No classes available.</p>
                <?php } else { ?>
                    <?php foreach ($classes as $class) { ?>
                        <button data-class-id="<?php echo $class['id']; ?>"
                                class="class-btn <?php echo $class['id'] == $selected_class_id ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </button>
                    <?php } ?>
                <?php } ?>
                <button class="weekend-toggle-btn" onclick="toggleWeekendForm()">Set Weekends</button>
            </div>
            <div class="weekend-selection" id="weekend-selection-form">
                <form method="POST">
                    <div class="days">
                        <?php foreach ($days as $day) { ?>
                            <label>
                                <input type="checkbox" name="weekend_days[]" value="<?php echo $day; ?>"
                                       <?php echo in_array($day, $weekend_days) ? 'checked' : ''; ?>>
                                <?php echo $day; ?>
                            </label>
                        <?php } ?>
                    </div>
                    <button type="submit" name="update_weekends">Update Weekends</button>
                    <div class="toggle">
                        <input type="checkbox" id="hide_weekends" 
                               onchange="toggleWeekendDisplay(<?php echo $selected_class_id; ?>, this.checked)"
                               <?php echo $hide_weekends ? 'checked' : ''; ?>>
                        <label for="hide_weekends">Hide Weekends</label>
                    </div>
                </form>
            </div>
            <div class="schedule-section">
                <h2>Class Schedule for <?php echo htmlspecialchars($selected_class_name); ?></h2>
                <?php if ($error_message) { ?>
                    <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                <?php } elseif (empty($classes)) { ?>
                    <p class="empty-message">No classes have been added yet.</p>
                <?php } elseif (!$selected_class_id) { ?>
                    <p class="empty-message">Please select a class.</p>
                <?php } else { ?>
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <?php for ($i = 1; $i <= $max_periods; $i++) { ?>
                                    <th>Period <?php echo $i; ?></th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($display_days as $day) { 
                                $is_weekend = in_array($day, $weekend_days);
                            ?>
                                <tr class="<?php echo $is_weekend ? 'weekend-row' : ''; ?>">
                                    <td><?php echo $day; ?></td>
                                    <?php for ($i = 1; $i <= $max_periods; $i++) { 
                                        $period = isset($schedule_by_day[$day][$i]) ? $schedule_by_day[$day][$i] : null;
                                        $cell_id = "cell_{$day}_{$i}";
                                    ?>
                                        <td class="period-cell <?php echo $is_weekend ? 'disabled' : ''; ?>" 
                                            data-day="<?php echo $day; ?>" 
                                            data-period="<?php echo $i; ?>" 
                                            id="<?php echo $cell_id; ?>"
                                            data-schedule-id="<?php echo $period['id'] ?? 0; ?>">
                                            <div class="display">
                                                <?php if ($period) { ?>
                                                    <?php if (empty($class_subjects)) { ?>
                                                        <div class="error-message">No subjects assigned.</div>
                                                    <?php } elseif (empty($teachers_for_class)) { ?>
                                                        <div class="error-message">No teachers assigned.</div>
                                                    <?php } else { ?>
                                                        <?php
                                                        $time = $period['start_time'] === '00:00:00' && $period['end_time'] === '00:00:00' 
                                                            ? 'Set Time' 
                                                            : substr($period['start_time'], 0, 5) . " - " . substr($period['end_time'], 0, 5);
                                                        $subject = $period['subject_id'] ? array_column($class_subjects, 'subject_name', 'id')[$period['subject_id']] ?? 'Select Subject' : 'Select Subject';
                                                        $teacher = $period['teacher_id'] ? array_column($teachers_for_class, 'full_name', 'id')[$period['teacher_id']] ?? 'Select Teacher' : 'Select Teacher';
                                                        echo "<div>$time<br>$subject<br>$teacher</div>";
                                                        ?>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <div class="empty-message">No period</div>
                                                <?php } ?>
                                            </div>
                                            <div class="edit">
                                                <input type="time" name="start_time" value="<?php echo $period['start_time'] ?? '00:00'; ?>">
                                                <input type="time" name="end_time" value="<?php echo $period['end_time'] ?? '00:00'; ?>">
                                                <select name="subject_id">
                                                    <option value="">Select Subject</option>
                                                    <?php foreach ($class_subjects as $subject) { ?>
                                                        <option value="<?php echo $subject['id']; ?>" 
                                                                <?php echo isset($period['subject_id']) && $period['subject_id'] == $subject['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                                <select name="teacher_id">
                                                    <option value="">Select Teacher</option>
                                                    <?php foreach ($teachers_for_class as $teacher) { ?>
                                                        <option value="<?php echo $teacher['id']; ?>" 
                                                                <?php echo isset($period['teacher_id']) && $period['teacher_id'] == $teacher['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($teacher['full_name']); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                                <button class="save-btn" onclick="savePeriod(this)" 
                                                        <?php echo empty($class_subjects) || empty($teachers_for_class) ? 'disabled' : ''; ?>>
                                                    Save
                                                </button>
                                                <button class="cancel-btn" onclick="cancelEdit(this)">Cancel</button>
                                            </div>
                                        </td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <div class="add-period-form">
                        <form method="POST">
                            <select name="day" required>
                                <?php foreach ($days as $day) { ?>
                                    <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                                <?php } ?>
                            </select>
                            <select name="period" required>
                                <?php for ($i = 1; $i <= $max_periods + 1; $i++) { ?>
                                    <option value="<?php echo $i; ?>">Period <?php echo $i; ?></option>
                                <?php } ?>
                            </select>
                            <input type="time" name="start_time" required>
                            <input type="time" name="end_time" required>
                            <select name="subject_id">
                                <option value="">Select Subject</option>
                                <?php foreach ($class_subjects as $subject) { ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <select name="teacher_id">
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers_for_class as $teacher) { ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <button type="submit" name="add_period">Add Period</button>
                        </form>
                    </div>
                    <div class="action-buttons">
                        <?php if ($max_periods > 4) { ?>
                            <button class="delete-btn" onclick="deletePeriod(<?php echo $selected_class_id; ?>, <?php echo $max_periods; ?>)">Delete Last Period</button>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div id="toast" class="toast"></div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded for class_id:', <?php echo $selected_class_id; ?>);
            document.getElementById('weekend-selection-form').style.display = 'none';

            document.querySelector('.class-bar').addEventListener('click', function(e) {
                const button = e.target.closest('.class-btn');
                if (button) {
                    const classId = button.getAttribute('data-class-id');
                    console.log('Class clicked:', classId);
                    selectClass(classId);
                }
            });

            document.querySelector('.schedule-table').addEventListener('click', function(e) {
                const cell = e.target.closest('.period-cell');
                if (cell && !cell.classList.contains('disabled')) {
                    console.log('Cell clicked:', cell.id, 'Schedule ID:', cell.dataset.scheduleId);
                    const display = cell.querySelector('.display');
                    const edit = cell.querySelector('.edit');
                    if (display && edit) {
                        closeAllEdits();
                        display.style.display = 'none';
                        edit.style.display = 'block';
                        // Force visibility of all elements
                        const inputs = edit.querySelectorAll('input[type="time"]');
                        const selects = edit.querySelectorAll('select');
                        const buttons = edit.querySelectorAll('button');
                        inputs.forEach(input => {
                            input.style.display = 'block';
                            input.style.visibility = 'visible';
                            input.style.opacity = '1';
                        });
                        selects.forEach(select => {
                            select.style.display = 'block';
                            select.style.visibility = 'visible';
                            select.style.opacity = '1';
                        });
                        buttons.forEach(button => {
                            button.style.display = 'inline-block';
                            button.style.visibility = 'visible';
                            button.style.opacity = '1';
                        });
                        // Additional debugging
                        console.log('Edit shown for:', cell.id);
                        console.log('Edit children count:', edit.children.length);
                        console.log('Edit content:', edit.innerHTML);
                        const subjectSelect = edit.querySelector('select[name="subject_id"]');
                        const teacherSelect = edit.querySelector('select[name="teacher_id"]');
                        console.log('Subject select found:', !!subjectSelect);
                        console.log('Teacher select found:', !!teacherSelect);
                        console.log('Subject select options:', subjectSelect ? subjectSelect.options.length : 'Not found');
                        console.log('Teacher select options:', teacherSelect ? teacherSelect.options.length : 'Not found');
                        console.log('Subject select computed style:', subjectSelect ? window.getComputedStyle(subjectSelect).display : 'Not found');
                        console.log('Teacher select computed style:', teacherSelect ? window.getComputedStyle(teacherSelect).display : 'Not found');
                        console.log('Subject select visibility:', subjectSelect ? window.getComputedStyle(subjectSelect).visibility : 'Not found');
                        console.log('Teacher select visibility:', teacherSelect ? window.getComputedStyle(teacherSelect).visibility : 'Not found');
                        console.log('Subject select opacity:', subjectSelect ? window.getComputedStyle(subjectSelect).opacity : 'Not found');
                        console.log('Teacher select opacity:', teacherSelect ? window.getComputedStyle(teacherSelect).opacity : 'Not found');
                    } else {
                        console.error('Display or edit missing in cell:', cell.id);
                    }
                }
            });
        });

        function showToast(message, type) {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.textContent = message;
                toast.className = `toast ${type} show`;
                setTimeout(() => { toast.className = 'toast'; }, 3000);
            }
        }

        function closeAllEdits() {
            document.querySelectorAll('.period-cell .edit').forEach(edit => {
                edit.style.display = 'none';
                edit.parentElement.querySelector('.display').style.display = 'block';
            });
        }

        function toggleWeekendForm() {
            const form = document.getElementById('weekend-selection-form');
            if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function selectClass(classId) {
            classId = parseInt(classId);
            if (classId !== <?php echo $selected_class_id; ?>) {
                window.location.href = `schedule.php?class_id=${classId}&t=${new Date().getTime()}`;
            }
        }

        function toggleWeekendDisplay(classId, hideWeekends) {
            window.location.href = `schedule.php?class_id=${classId}&hide_weekends=${hideWeekends ? '1' : '0'}`;
        }

        function savePeriod(button) {
            const cell = button.closest('.period-cell');
            if (!cell) {
                showToast('Cell not found', 'error');
                return;
            }
            const scheduleId = cell.dataset.scheduleId;
            const day = cell.dataset.day;
            const period = cell.dataset.period;
            const startTime = cell.querySelector('input[name="start_time"]').value;
            const endTime = cell.querySelector('input[name="end_time"]').value;
            const subjectId = cell.querySelector('select[name="subject_id"]').value || null;
            const teacherId = cell.querySelector('select[name="teacher_id"]').value || null;
            const classId = <?php echo $selected_class_id; ?>;

            if (!startTime || !endTime) {
                showToast('Set both times', 'error');
                return;
            }

            const action = scheduleId == 0 ? 'add_period' : 'update_period';
            const body = scheduleId == 0 
                ? `action=${action}&class_id=${classId}&day=${day}&period=${period}&start_time=${startTime}&end_time=${endTime}&subject_id=${subjectId}&teacher_id=${teacherId}`
                : `action=${action}&schedule_id=${scheduleId}&start_time=${startTime}&end_time=${endTime}&subject_id=${subjectId}&teacher_id=${teacherId}`;

            console.log('Saving period with body:', body);

            fetch('update_period.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            }).then(response => {
                console.log('Fetch response status:', response.status);
                return response.json();
            }).then(data => {
                console.log('Fetch response data:', data);
                if (data.success) {
                    const display = cell.querySelector('.display');
                    display.innerHTML = `<div>${startTime.substring(0, 5)} - ${endTime.substring(0, 5)}<br>${subjectId ? cell.querySelector(`select[name="subject_id"] option[value="${subjectId}"]`).textContent : 'Select Subject'}<br>${teacherId ? cell.querySelector(`select[name="teacher_id"] option[value="${teacherId}"]`).textContent : 'Select Teacher'}</div>`;
                    cell.dataset.scheduleId = data.schedule_id || scheduleId;
                    cell.querySelector('.edit').style.display = 'none';
                    display.style.display = 'block';
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                }
            }).catch(error => {
                console.error('Save error:', error);
                showToast('Save failed', 'error');
            });
        }

        function cancelEdit(button) {
            const cell = button.closest('.period-cell');
            if (cell) {
                cell.querySelector('.edit').style.display = 'none';
                cell.querySelector('.display').style.display = 'block';
            }
        }

        function deletePeriod(classId, period) {
            if (confirm('Delete last period?')) {
                fetch('update_period.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_period&class_id=${classId}&period=${period}`
                }).then(response => response.json()).then(data => {
                    if (data.success) location.reload();
                    else showToast(data.message, 'error');
                }).catch(error => {
                    console.error('Delete error:', error);
                    showToast('Delete failed', 'error');
                });
            }
        }
    </script>
</body>
</html>