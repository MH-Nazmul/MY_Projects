<?php
// teacher/grades.php

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

// Fetch the teacher's classes
$stmt = $conn->prepare("
    SELECT DISTINCT class 
    FROM class_routines 
    WHERE teacher_id = ?
    ORDER BY class
");
if (!$stmt) {
    die("Database prepare error.");
}
$stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
if (!$stmt->execute()) {
    die("Database error occurred.");
}
$classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch subjects for the teacher
$stmt = $conn->prepare("
    SELECT DISTINCT subject_name 
    FROM class_routines 
    WHERE teacher_id = ?
    ORDER BY subject_name
");
$stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle grade submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grades'])) {
    $conn->beginTransaction();
    try {
        $grades_data = $_POST['grades'];
        $class = $_POST['class'];
        $semester = 'Summer 2025'; // Hardcoded for now; adjust as needed
        foreach ($grades_data as $student_id => $subject_grades) {
            foreach ($subject_grades as $subject => $grade) {
                $grade = in_array($grade, ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'D', 'F']) ? $grade : 'F';
                $stmt = $conn->prepare("
                    INSERT INTO grades (teacher_id, student_id, class, subject_name, grade, semester)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE grade = ?
                ");
                $stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $student_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $class, PDO::PARAM_STR);
                $stmt->bindParam(4, $subject, PDO::PARAM_STR);
                $stmt->bindParam(5, $grade, PDO::PARAM_STR);
                $stmt->bindParam(6, $semester, PDO::PARAM_STR);
                $stmt->bindParam(7, $grade, PDO::PARAM_STR);
                $stmt->execute();
            }
        }
        $conn->commit();
        $message = "Grades updated successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Fetch students and existing grades for a specific class when expanded
$selected_class = $_GET['class'] ?? $classes[0] ?? '';
$students = [];
$existing_grades = [];
if ($selected_class) {
    $stmt = $conn->prepare("SELECT id, fname, lname FROM students WHERE class = ? ORDER BY lname, fname");
    $stmt->bindParam(1, $selected_class, PDO::PARAM_STR);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("
        SELECT student_id, subject_name, grade 
        FROM grades 
        WHERE teacher_id = ? AND class = ? AND semester = 'Summer 2025'
        ORDER BY student_id, subject_name
    ");
    $stmt->bindParam(1, $teacher_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $selected_class, PDO::PARAM_STR);
    $stmt->execute();
    $existing_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Grade Submission</title>
    <style>
        .dashboard-section {
            margin-bottom: 40px;
        }
        .dashboard-section h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
        }
        .class-list {
            margin-bottom: 20px;
        }
        .class-list a {
            display: block;
            padding: 10px;
            background: #f9f9f9;
            margin-bottom: 5px;
            text-decoration: none;
            color: #2c3e50;
        }
        .class-list a:hover {
            background: #e0e0e0;
        }
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .grades-table th, .grades-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .grades-table th {
            background-color: #f2f2f2;
        }
        .grades-table td input[type="radio"] {
            margin: 0 5px;
        }
        .options {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .options a {
            margin-right: 10px;
            color: #3498db;
            text-decoration: none;
        }
        .options a:hover {
            text-decoration: underline;
        }
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            z-index: 10;
            position: relative;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
     <?php include 'teacher_sidebar.php' ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Grade Submission -->
            <div class="dashboard-section">
                <h2>Grade Submission</h2>
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <div class="class-list">
                    <?php foreach ($classes as $class): ?>
                        <a href="?class=<?php echo urlencode($class); ?>"><?php echo htmlspecialchars($class); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php if ($selected_class && !empty($students)): ?>
                    <form method="POST" action="grades.php">
                        <input type="hidden" name="class" value="<?php echo htmlspecialchars($selected_class); ?>">
                        <table class="grades-table">
                            <tr>
                                <th>Student</th>
                                <?php foreach ($subjects as $subject): ?>
                                    <th><?php echo htmlspecialchars($subject); ?></th>
                                <?php endforeach; ?>
                            </tr>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></td>
                                    <?php foreach ($subjects as $subject): ?>
                                        <td>
                                            <?php
                                                $grade_key = $student['id'] . '|' . $subject;
                                                $existing_grade = array_filter($existing_grades, fn($g) => $g['student_id'] == $student['id'] && $g['subject_name'] == $subject);
                                                $current_grade = !empty($existing_grade) ? reset($existing_grade)['grade'] : 'F';
                                            ?>
                                            <div>
                                                <input type="radio" name="grades[<?php echo $student['id']; ?>][<?php echo $subject; ?>]" value="A+" <?php echo $current_grade === 'A+' ? 'checked' : ''; ?>> A+
                                                <input type="radio" name="grades[<?php echo $student['id']; ?>][<?php echo $subject; ?>]" value="A" <?php echo $current_grade === 'A' ? 'checked' : ''; ?>> A
                                                <input type="radio" name="grades[<?php echo $student['id']; ?>][<?php echo $subject; ?>]" value="A-" <?php echo $current_grade === 'A-' ? 'checked' : ''; ?>> A-
                                                <input type="radio" name="grades[<?php echo $student['id']; ?>][<?php echo $subject; ?>]" value="B+" <?php echo $current_grade === 'B+' ? 'checked' : ''; ?>> B+
                                                <input type="radio" name="grades[<?php echo $student['id']; ?>][<?php echo $subject; ?>]" value="B" <?php echo $current_grade === 'B' ? 'checked' : ''; ?>> B
                                                <input type="radio" name="grades[<?php echo $student['id']; ?>][<?php echo $subject; ?>]" value="B-" <?php echo $current_grade === 'B-' ? 'checked' : ''; ?>> B-
                                                <input type="radio" name="grades[<?php echo $student['id']; ?>][<?php echo $subject; ?>]" value="C+" <?php echo $current_grade === 'C+' ? 'checked' : ''; ?>> C+
                                                <input type="radio" name="grades[<?php echo $student['id']; ?>][<?php echo $subject; ?>]" value="C" <?php echo $current_grade === 'C' ? 'checked' : ''; ?>> C
                                                <input type="radio" name="grades[<?php echo $student['id']; ?>][<?php echo $subject; ?>]" value="D" <?php echo $current_grade === 'D' ? 'checked' : ''; ?>> D
                                                <input type="radio" name="grades[<?php echo $student['id']; ?>][<?php echo $subject; ?>]" value="F" <?php echo $current_grade === 'F' ? 'checked' : ''; ?>> F
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        <button type="submit">Submit Grades</button>
                    </form>
                    <div class="options">
                        <a href="#" onclick="printGradesheet('<?php echo htmlspecialchars($selected_class); ?>')">Print Gradesheet</a>
                    </div>
                <?php elseif ($selected_class): ?>
                    <p>No students found in <?php echo htmlspecialchars($selected_class); ?>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function printGradesheet(className) {
            window.print(); // Basic print functionality; enhance with PDF generation if needed
            alert('Printing gradesheet for ' + className);
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?><?php
