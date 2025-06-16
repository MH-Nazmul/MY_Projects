<?php
// manage_student.php

// Start the session
session_start();
include '../db_connect.php';

// Check if the user is already logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: ../logout.php");
    exit();
}

// Generate a CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch school name, tagline, and address from settings
try {
    $stmt = $conn->prepare("SELECT school_name, tag_line, address FROM settings LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $school_name = $settings['school_name'] ?? 'School Name';
    $tag_line = $settings['tag_line'] ?? 'Tagline';
    $school_address = $settings['address'] ?? 'Your School Address';
} catch (PDOException $e) {
    $school_name = 'School Name';
    $tag_line = 'Tagline';
    $school_address = 'Your School Address';
}

// Fetch exams for each class (for admit cards)
$exams_by_class = [];
try {
    $stmt = $conn->prepare("SELECT class, semester, exam_name, exam_date FROM exams");
    $stmt->execute();
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($exams as $exam) {
        $exams_by_class[$exam['class']][$exam['semester']] = [
            'exam_name' => $exam['exam_name'],
            'exam_date' => date('d-m-Y', strtotime($exam['exam_date']))
        ];
    }
} catch (PDOException $e) {
    $exams_by_class = [];
}

// Initialize variables for messages
$success_message = '';
$error_message = '';

// Check for messages in session (for PRG pattern)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle form submissions (Enroll Student, Promote Class, Edit Student)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['enroll_student'])) {
        // Handle Enroll Student form submission
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error_message'] = "Invalid CSRF token.";
            header("Location: student_management.php");
            exit();
        } else {
            $fname = trim($_POST['fname']);
            $lname = trim($_POST['lname']);
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $class = trim($_POST['class']);
            $section = trim($_POST['section']);
            $roll_no = trim($_POST['roll_no']);
            $mother_name = trim($_POST['mother_name']);
            $father_name = trim($_POST['father_name']);
            $parents_contact = trim($_POST['parents_contact']);
            $photo = null;

            // Handle photo upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $photo = file_get_contents($_FILES['photo']['tmp_name']);
            }

            // Validate inputs
            if (empty($fname) || empty($lname) || empty($username) || empty($password) || empty($confirm_password) || empty($class) || empty($section) || empty($roll_no) || empty($mother_name) || empty($father_name) || empty($parents_contact)) {
                $_SESSION['error_message'] = "All fields are required.";
                header("Location: student_management.php");
                exit();
            } elseif ($password !== $confirm_password) {
                $_SESSION['error_message'] = "Passwords do not match.";
                header("Location: student_management.php");
                exit();
            } elseif (!preg_match('/^[0-9]{11}$/', $parents_contact)) {
                $_SESSION['error_message'] = "Parent's contact must be an 11-digit phone number.";
                header("Location: student_management.php");
                exit();
            } else {
                try {
                    // Check if the username or roll_no is already in use
                    $stmt = $conn->prepare("SELECT id FROM students WHERE username = ? OR roll_no = ?");
                    $stmt->execute([$username, $roll_no]);
                    if ($stmt->fetch()) {
                        $_SESSION['error_message'] = "Username or Roll No. is already in use.";
                        header("Location: student_management.php");
                        exit();
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("
                            INSERT INTO students (username, password, fname, lname, class, section, roll_no, mother_name, father_name, photo, parents_mobile, due_payments)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00)
                        ");
                        $stmt->execute([$username, $hashed_password, $fname, $lname, $class, $section, $roll_no, $mother_name, $father_name, $photo, $parents_contact]);
                        $_SESSION['success_message'] = "Student enrolled successfully.";
                        header("Location: student_management.php");
                        exit();
                    }
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Failed to enroll student: " . $e->getMessage();
                    header("Location: student_management.php");
                    exit();
                }
            }
        }
    } elseif (isset($_POST['promote_class'])) {
        // Handle Promote Class
        $student_ids = json_decode($_POST['student_ids'], true);
        if (!empty($student_ids)) {
            try {
                $stmt = $conn->prepare("SELECT id, class FROM students WHERE id IN (" . implode(',', array_fill(0, count($student_ids), '?')) . ")");
                $stmt->execute($student_ids);
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($students as $student) {
                    $current_class = (int)$student['class'];
                    $new_class = $current_class + 1;
                    $update_stmt = $conn->prepare("UPDATE students SET class = ? WHERE id = ?");
                    $update_stmt->execute([$new_class, $student['id']]);
                }
                $_SESSION['success_message'] = "Selected students promoted to the next class.";
                header("Location: student_management.php");
                exit();
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Failed to promote students: " . $e->getMessage();
                header("Location: student_management.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "No students selected to promote.";
            header("Location: student_management.php");
            exit();
        }
    } elseif (isset($_POST['edit_student'])) {
        // Handle Edit Student
        $student_id = $_POST['student_id'];
        $fname = trim($_POST['edit_fname']);
        $lname = trim($_POST['edit_lname']);
        $class = trim($_POST['edit_class']);
        $section = trim($_POST['edit_section']);
        $roll_no = trim($_POST['edit_roll_no']);
        $mother_name = trim($_POST['edit_mother_name']);
        $father_name = trim($_POST['edit_father_name']);
        $parents_contact = trim($_POST['edit_parents_contact']);
        $photo = null;

        // Handle photo upload
        if (isset($_FILES['edit_photo']) && $_FILES['edit_photo']['error'] === UPLOAD_ERR_OK) {
            $photo = file_get_contents($_FILES['edit_photo']['tmp_name']);
        }

        if (empty($fname) || empty($lname) || empty($class) || empty($section) || empty($roll_no) || empty($mother_name) || empty($father_name) || empty($parents_contact)) {
            $_SESSION['error_message'] = "All fields are required.";
            header("Location: student_management.php");
            exit();
        } elseif (!preg_match('/^[0-9]{11}$/', $parents_contact)) {
            $_SESSION['error_message'] = "Parent's contact must be an 11-digit phone number.";
            header("Location: student_management.php");
            exit();
        } else {
            try {
                // Check if the roll_no is already in use by another student
                $stmt = $conn->prepare("SELECT id FROM students WHERE roll_no = ? AND id != ?");
                $stmt->execute([$roll_no, $student_id]);
                if ($stmt->fetch()) {
                    $_SESSION['error_message'] = "Roll No. is already in use by another student.";
                    header("Location: student_management.php");
                    exit();
                } else {
                    if ($photo) {
                        $stmt = $conn->prepare("
                            UPDATE students 
                            SET fname = ?, lname = ?, class = ?, section = ?, roll_no = ?, mother_name = ?, father_name = ?, parents_mobile = ?, photo = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$fname, $lname, $class, $section, $roll_no, $mother_name, $father_name, $parents_contact, $photo, $student_id]);
                    } else {
                        $stmt = $conn->prepare("
                            UPDATE students 
                            SET fname = ?, lname = ?, class = ?, section = ?, roll_no = ?, mother_name = ?, father_name = ?, parents_mobile = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$fname, $lname, $class, $section, $roll_no, $mother_name, $father_name, $parents_contact, $student_id]);
                    }
                    $_SESSION['success_message'] = "Student details updated successfully.";
                    header("Location: student_management.php");
                    exit();
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Failed to update student: " . $e->getMessage();
                header("Location: student_management.php");
                exit();
            }
        }
    }
}

// Fetch all students with their results from student_results
$where_clauses = [];
$params = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter'])) {
    if (!empty($_POST['id'])) {
        $where_clauses[] = "s.id = ?";
        $params[] = $_POST['id'];
    }
    if (!empty($_POST['class'])) {
        $where_clauses[] = "s.class = ?";
        $params[] = $_POST['class'];
    }
    if (!empty($_POST['section'])) {
        $where_clauses[] = "s.section = ?";
        $params[] = $_POST['section'];
    }
    if (!empty($_POST['semester'])) {
        $semester = $_POST['semester'];
        if ($semester === 'sem1' || $semester === 'sem2') {
            $where_clauses[] = "e.semester = ?";
            $params[] = $semester;
        }
    }
    if (!empty($_POST['results'])) {
        $where_clauses[] = "sr.result LIKE ?";
        $params[] = "%" . $_POST['results'] . "%";
    }
    if (isset($_POST['due_payments']) && $_POST['due_payments'] !== '') {
        $where_clauses[] = "s.due_payments > ?";
        $params[] = $_POST['due_payments'];
    }
}

// Fetch students and their results
$query = "
    SELECT s.*, 
       COALESCE(MAX(CASE WHEN e.semester = 'sem1' THEN sr.result ELSE NULL END), 'Not available') AS result_sem1,
       COALESCE(MAX(CASE WHEN e.semester = 'sem2' THEN sr.result ELSE NULL END), 'Not available') AS result_sem2,
       COALESCE(SUM(d.amount), 0.00) AS due_payments
FROM students s
LEFT JOIN student_results sr ON s.id = sr.student_id
LEFT JOIN exams e ON sr.exam_id = e.id
LEFT JOIN student_dues d ON s.id = d.student_id
    
";
if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}
$query .= " GROUP BY s.id ORDER BY s.id asc";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Debug: Log the fetched students to check due_payments
    error_log("Fetched students: " . print_r($students, true));
} catch (PDOException $e) {
    $error_message = "Failed to fetch students: " . $e->getMessage();
    $students = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - School Management System</title>
    <link rel="stylesheet" href="CSS/admin.css">
    <style>
        /* Title Section */
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

        /* Content Layout */
        .content-wrapper {
            display: flex;
            gap: 20px;
        }

        /* Student List */
        .student-list-container {
            flex: 3;
            max-height: 60vh;
            overflow-y: auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .student-list-container table {
            margin-top: 0;
        }
        .student-list-container th, .student-list-container td {
            padding: 10px;
        }
        .student-list-container th {
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .action-buttons a {
            color: #3498db;
            text-decoration: none;
            margin-right: 10px;
        }
        .action-buttons a:hover {
            text-decoration: underline;
        }

        /* Filter Sidebar */
        .filter-sidebar {
            flex: 1;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .filter-sidebar h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .filter-sidebar form {
            background: none;
            padding: 0;
            box-shadow: none;
        }
        .filter-sidebar button {
            width: 100%;
            margin-top: 10px;
        }

        /* Action Buttons Below List */
        .action-buttons-container {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .action-buttons-container button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .enroll-btn {
            background-color: #28a745;
            color: white;
        }
        .enroll-btn:hover {
            background-color: #218838;
        }
        .print-results-btn, .admit-cards-btn, .promote-class-btn, .export-csv-btn, .mark-attendance-btn {
            background-color: #3498db;
            color: white;
        }
        .print-results-btn:hover, .admit-cards-btn:hover, .promote-class-btn:hover, .export-csv-btn:hover, .mark-attendance-btn:hover {
            background-color: #2980b9;
        }

        /* Enroll Form (Hidden by Default) */
        .enroll-form-container {
            display: none;
            margin-top: 20px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .enroll-form-container.active {
            display: block;
        }

        /* Edit Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        .modal-content {
            background: white;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
            position: relative;
        }
        .modal-content h2 {
            margin-top: 0;
        }
        .close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
        }

        /* Toast Notification */
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

        <!-- Main Content -->
        <div class="main-content">
            <!-- Title Section -->
            <div class="title-section">
                <h2><?php echo htmlspecialchars($school_name); ?></h2>
                <p><?php echo htmlspecialchars($tag_line); ?></p>
            </div>

            <!-- Content Wrapper (Student List + Filter Sidebar) -->
            <div class="content-wrapper">
                <!-- Student List -->
                <div class="student-list-container">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all"></th>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Class</th>
                                <th>Section</th>
                                <th>Roll No</th>
                                <th>Sem 1 Result</th>
                                <th>Sem 2 Result</th>
                                <th>Due Payments</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)) { ?>
                                <tr>
                                    <td colspan="10" style="text-align: center;">No students found.</td>
                                </tr>
                            <?php } else { ?>
                                <?php foreach ($students as $student) { ?>
                                    <tr>
                                        <td><input type="checkbox" class="student-checkbox" value="<?php echo $student['id']; ?>"></td>
                                        <td><?php echo htmlspecialchars($student['id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['username']); ?></td>
                                        <td><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></td>
                                        <td><?php echo htmlspecialchars($student['class']); ?></td>
                                        <td><?php echo htmlspecialchars($student['section']); ?></td>
                                        <td><?php echo htmlspecialchars($student['roll_no']); ?></td>
                                        <td><?php echo htmlspecialchars($student['result_sem1']); ?></td>
                                        <td><?php echo htmlspecialchars($student['result_sem2']); ?></td>
                                        <td><?php echo htmlspecialchars(number_format($student['due_payments']),3); ?></td>
                                        <td class="action-buttons">
                                            <a href="#" class="edit-student" 
                                               data-id="<?php echo $student['id']; ?>" 
                                               data-fname="<?php echo htmlspecialchars($student['fname']); ?>" 
                                               data-lname="<?php echo htmlspecialchars($student['lname']); ?>" 
                                               data-class="<?php echo htmlspecialchars($student['class']); ?>" 
                                               data-section="<?php echo htmlspecialchars($student['section']); ?>" 
                                               data-roll_no="<?php echo htmlspecialchars($student['roll_no']); ?>" 
                                               data-mother_name="<?php echo htmlspecialchars($student['mother_name']); ?>" 
                                               data-father_name="<?php echo htmlspecialchars($student['father_name']); ?>" 
                                               data-parents_contact="<?php echo htmlspecialchars($student['parents_mobile']); ?>">Edit</a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <!-- Filter Sidebar -->
                <div class="filter-sidebar">
                    <h3>Filter Students</h3>
                    <form method="POST">
                        <input type="hidden" name="filter" value="1">
                        <label for="id">Student ID:</label>
                        <input type="text" id="id" name="id" value="<?php echo isset($_POST['id']) ? htmlspecialchars($_POST['id']) : ''; ?>">

                        <label for="class">Class:</label>
                        <input type="text" id="class" name="class" value="<?php echo isset($_POST['class']) ? htmlspecialchars($_POST['class']) : ''; ?>">

                        <label for="section">Section:</label>
                        <input type="text" id="section" name="section" value="<?php echo isset($_POST['section']) ? htmlspecialchars($_POST['section']) : ''; ?>">

                        <label for="semester">Semester:</label>
                        <select id="semester" name="semester">
                            <option value="">All</option>
                            <option value="sem1" <?php echo (isset($_POST['semester']) && $_POST['semester'] === 'sem1') ? 'selected' : ''; ?>>Semester 1</option>
                            <option value="sem2" <?php echo (isset($_POST['semester']) && $_POST['semester'] === 'sem2') ? 'selected' : ''; ?>>Semester 2</option>
                        </select>

                        <label for="results">Results (e.g., A+, 80%):</label>
                        <input type="text" id="results" name="results" value="<?php echo isset($_POST['results']) ? htmlspecialchars($_POST['results']) : ''; ?>">

                        <label for="due_payments">Due Payments (greater than):</label>
                        <input type="number" id="due_payments" name="due_payments" step="0.01" value="<?php echo isset($_POST['due_payments']) ? htmlspecialchars($_POST['due_payments']) : ''; ?>">

                        <button type="submit">Apply Filters</button>
                    </form>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons-container">
                <button class="enroll-btn" id="enroll-btn">Enroll Students</button>
                <button class="print-results-btn" id="print-results-btn">Print Results</button>
                <button class="admit-cards-btn" id="admit-cards-btn">Create Admit Cards</button>
                <button class="promote-class-btn" id="promote-class-btn">Promote Class</button>
                <button class="export-csv-btn" id="export-csv-btn">Export to CSV</button>
                <button class="mark-attendance-btn" id="mark-attendance-btn">Mark Attendance</button>
            </div>

            <!-- Enroll Students Form (Hidden by Default) -->
            <div class="enroll-form-container" id="enroll-form">
                <h2>Enroll New Student</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="enroll_student" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <label for="fname">First Name:</label>
                    <input type="text" id="fname" name="fname" value="<?php echo isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : ''; ?>" required>
                    
                    <label for="lname">Last Name:</label>
                    <input type="text" id="lname" name="lname" value="<?php echo isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : ''; ?>" required>
                    
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                    
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    
                    <label for="class">Class:</label>
                    <input type="text" id="class" name="class" value="<?php echo isset($_POST['class']) ? htmlspecialchars($_POST['class']) : ''; ?>" required>
                    
                    <label for="section">Section:</label>
                    <input type="text" id="section" name="section" value="<?php echo isset($_POST['section']) ? htmlspecialchars($_POST['section']) : ''; ?>" required>
                    
                    <label for="roll_no">Roll No:</label>
                    <input type="text" id="roll_no" name="roll_no" value="<?php echo isset($_POST['roll_no']) ? htmlspecialchars($_POST['roll_no']) : ''; ?>" required>
                    
                    <label for="mother_name">Mother's Name:</label>
                    <input type="text" id="mother_name" name="mother_name" value="<?php echo isset($_POST['mother_name']) ? htmlspecialchars($_POST['mother_name']) : ''; ?>" required>
                    
                    <label for="father_name">Father's Name:</label>
                    <input type="text" id="father_name" name="father_name" value="<?php echo isset($_POST['father_name']) ? htmlspecialchars($_POST['father_name']) : ''; ?>" required>
                    
                    <label for="photo">Student Photo (Optional):</label>
                    <input type="file" id="photo" name="photo" accept="image/*">
                    
                    <label for="parents_contact">Parent's Contact:</label>
                    <input type="text" id="parents_contact" name="parents_contact" value="<?php echo isset($_POST['parents_contact']) ? htmlspecialchars($_POST['parents_contact']) : ''; ?>" required>
                    
                    <button type="submit">Enroll Student</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div class="modal" id="edit-modal">
        <div class="modal-content">
            <span class="close-btn" id="close-modal">×</span>
            <h2>Edit Student</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="edit_student" value="1">
                <input type="hidden" name="student_id" id="edit-student-id">
                
                <label for="edit_fname">First Name:</label>
                <input type="text" id="edit_fname" name="edit_fname" required>
                
                <label for="edit_lname">Last Name:</label>
                <input type="text" id="edit_lname" name="edit_lname" required>
                
                <label for="edit_class">Class:</label>
                <input type="text" id="edit_class" name="edit_class" required>
                
                <label for="edit_section">Section:</label>
                <input type="text" id="edit_section" name="edit_section" required>
                
                <label for="edit_roll_no">Roll No:</label>
                <input type="text" id="edit_roll_no" name="edit_roll_no" required>
                
                <label for="edit_mother_name">Mother's Name:</label>
                <input type="text" id="edit_mother_name" name="edit_mother_name" required>
                
                <label for="edit_father_name">Father's Name:</label>
                <input type="text" id="edit_father_name" name="edit_father_name" required>
                
                <label for="edit_photo">Update Photo (Optional):</label>
                <input type="file" id="edit_photo" name="edit_photo" accept="image/*">
                
                <label for="edit_parents_contact">Parent's Contact:</label>
                <input type="text" id="edit_parents_contact" name="edit_parents_contact" required>
                
                <button type="submit">Update Student</button>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <?php if ($success_message) { ?>
        <div id="toast" class="toast success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php } elseif ($error_message) { ?>
        <div id="toast" class="toast error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php } ?>

    <!-- JavaScript -->
    <script>
        // Show/Hide Enroll Form
        document.getElementById('enroll-btn').addEventListener('click', function() {
            const form = document.getElementById('enroll-form');
            form.classList.toggle('active');
        });

        // Select All Checkboxes
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Get Selected Student IDs
        function getSelectedStudentIds() {
            const checkboxes = document.querySelectorAll('.student-checkbox:checked');
            return Array.from(checkboxes).map(checkbox => checkbox.value);
        }

        // Print Results
        document.getElementById('print-results-btn').addEventListener('click', function() {
            const students = <?php echo json_encode($students); ?>;
            if (students.length === 0) {
                alert('No students to print results for.');
                return;
            }
            let printContent = `
                <html>
                <head>
                    <title>Student Results</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #2c3e50; color: white; }
                    </style>
                </head>
                <body>
                    <h1>Student Results - <?php echo htmlspecialchars($school_name); ?></h1>
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Sem 1 Result</th>
                            <th>Sem 2 Result</th>
                        </tr>`;
            students.forEach(student => {
                printContent += `
                    <tr>
                        <td>${student.id}</td>
                        <td>${student.fname} ${student.lname}</td>
                        <td>${student.class}</td>
                        <td>${student.section}</td>
                        <td>${student.result_sem1}</td>
                        <td>${student.result_sem2}</td>
                    </tr>`;
            });
            printContent += `
                    </table>
                </body>
                </html>`;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        });

        // Create Admit Cards
        document.getElementById('admit-cards-btn').addEventListener('click', function() {
            const students = <?php echo json_encode($students); ?>;
            const examsByClass = <?php echo json_encode($exams_by_class); ?>;
            if (students.length === 0) {
                alert('No students to create admit cards for.');
                return;
            }
            let printContent = `
                <html>
                <head>
                    <title>Admit Cards</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 0;
                            padding: 20px;
                            background: #f4f4f4;
                        }
                        .admit-card-container {
                            display: flex;
                            flex-wrap: wrap;
                            gap: 20px;
                            justify-content: center;
                        }
                        .admit-card {
                            width: 350px;
                            border: 2px solid #000;
                            padding: 15px;
                            margin: 10px;
                            background: #fff;
                            position: relative;
                            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                            font-size: 12px;
                            line-height: 1.4;
                        }
                        .admit-card-header {
                            text-align: center;
                            border-bottom: 2px solid #000;
                            padding-bottom: 10px;
                            margin-bottom: 15px;
                        }
                        .admit-card-header h1 {
                            font-size: 16px;
                            margin: 0;
                            text-transform: uppercase;
                            font-weight: bold;
                        }
                        .admit-card-header p {
                            font-size: 11px;
                            margin: 5px 0;
                            text-transform: uppercase;
                        }
                        .admit-card-title {
                            text-align: center;
                            font-size: 14px;
                            font-weight: bold;
                            margin: 10px 0;
                            text-transform: uppercase;
                        }
                        .admit-card-content {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 15px;
                        }
                        .admit-card-details {
                            flex: 1;
                        }
                        .admit-card-details p {
                            margin: 5px 0;
                        }
                        .admit-card-details p strong {
                            display: inline-block;
                            width: 120px;
                            font-weight: bold;
                        }
                        .admit-card-photo {
                            width: 80px;
                            height: 100px;
                            border: 1px solid #000;
                            background: #ddd;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 10px;
                            color: #666;
                        }
                        .admit-card-photo img {
                            width: 100%;
                            height: 100%;
                            object-fit: cover;
                        }
                        .admit-card-contact {
                            margin: 10px 0;
                            font-weight: bold;
                        }
                        .admit-card-footer {
                            border-top: 2px solid #000;
                            padding-top: 10px;
                            font-size: 11px;
                            margin-bottom: 15px;
                        }
                        .admit-card-signatures {
                            display: flex;
                            justify-content: space-between;
                            font-size: 11px;
                            margin-top: 20px;
                        }
                        .admit-card-signatures p {
                            margin: 0;
                        }
                        .school-seal {
                            text-align: center;
                        }
                        @media print {
                            .admit-card {
                                margin: 10px;
                                page-break-inside: avoid;
                            }
                            .admit-card-container {
                                gap: 0;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="admit-card-container">`;
            students.forEach(student => {
                const exam = examsByClass[student.class]?.['sem1'] || { exam_name: 'TERM-I S.A-I EXAM', exam_date: '20-09-2025' };
                const photoSrc = student.photo ? `data:image/jpeg;base64,${btoa(String.fromCharCode(...new Uint8Array(student.photo)))}` : '';
                printContent += `
                    <div class="admit-card">
                        <div class="admit-card-header">
                            <h1><?php echo htmlspecialchars($school_name); ?></h1>
                            <p><?php echo htmlspecialchars($school_address); ?></p>
                        </div>
                        <div class="admit-card-title">
                            ADMIT CARD
                        </div>
                        <div class="admit-card-content">
                            <div class="admit-card-details">
                                <p><strong>Name of Student:</strong> ${student.fname} ${student.lname}</p>
                                <p><strong>Class:</strong> ${student.class}</p>
                                <p><strong>Roll No:</strong> ${student.roll_no}</p>
                                <p><strong>Mother's Name:</strong> ${student.mother_name}</p>
                                <p><strong>Father's Name:</strong> ${student.father_name}</p>
                                <p><strong>Allowed to appear in the:</strong> ${exam.exam_name}</p>
                            </div>
                            <div class="admit-card-photo">
                                ${photoSrc ? `<img src="${photoSrc}" alt="Student Photo">` : 'No Photo'}
                            </div>
                        </div>
                        <div class="admit-card-contact">
                            <p>Contacting No: ${student.parents_mobile}</p>
                            <p>Date: ${exam.exam_date}</p>
                        </div>
                        <div class="admit-card-footer">
                            <p><strong>Note:</strong> Keep this card safely and must bring to the exam venue on every exam.</p>
                        </div>
                        <div class="admit-card-signatures">
                            <p>Principal</p>
                            <p class="school-seal">School Seal</p>
                            <p>Exam Controller</p>
                        </div>
                    </div>`;
            });
            printContent += `
                    </div>
                </body>
                </html>`;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        });

        // Promote Class
        document.getElementById('promote-class-btn').addEventListener('click', function() {
            const studentIds = getSelectedStudentIds();
            if (studentIds.length === 0) {
                alert('Please select at least one student to promote.');
                return;
            }
            if (confirm('Are you sure you want to promote the selected students to the next class?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'student_management.php';
                form.style.display = 'none';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'promote_class';
                input.value = '1';
                form.appendChild(input);
                const studentIdsInput = document.createElement('input');
                studentIdsInput.type = 'hidden';
                studentIdsInput.name = 'student_ids';
                studentIdsInput.value = JSON.stringify(studentIds);
                form.appendChild(studentIdsInput);
                document.body.appendChild(form);
                form.submit();
            }
        });

        // Export to CSV
        document.getElementById('export-csv-btn').addEventListener('click', function() {
            const students = <?php echo json_encode($students); ?>;
            if (students.length === 0) {
                alert('No students to export.');
                return;
            }
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "ID,Name,Class,Section,Sem 1 Result,Sem 2 Result,Due Payments\n";
            students.forEach(student => {
                const row = [
                    student.id,
                    `"${student.fname} ${student.lname}"`,
                    student.class,
                    student.section,
                    `"${student.result_sem1}"`,
                    `"${student.result_sem2}"`,
                    student.due_payments
                ].join(",");
                csvContent += row + "\n";
            });
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "students_list.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });

        // Mark Attendance (Placeholder)
        document.getElementById('mark-attendance-btn').addEventListener('click', function() {
            alert('Mark Attendance feature coming soon!');
        });

        // Edit Student Modal
        const modal = document.getElementById('edit-modal');
        const closeModal = document.getElementById('close-modal');
        document.querySelectorAll('.edit-student').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');
                const fname = this.getAttribute('data-fname');
                const lname = this.getAttribute('data-lname');
                const studentClass = this.getAttribute('data-class');
                const section = this.getAttribute('data-section');
                const roll_no = this.getAttribute('data-roll_no');
                const mother_name = this.getAttribute('data-mother_name');
                const father_name = this.getAttribute('data-father_name');
                const parents_contact = this.getAttribute('data-parents_contact');

                document.getElementById('edit-student-id').value = id;
                document.getElementById('edit_fname').value = fname;
                document.getElementById('edit_lname').value = lname;
                document.getElementById('edit_class').value = studentClass;
                document.getElementById('edit_section').value = section;
                document.getElementById('edit_roll_no').value = roll_no;
                document.getElementById('edit_mother_name').value = mother_name;
                document.getElementById('edit_father_name').value = father_name;
                document.getElementById('edit_parents_contact').value = parents_contact;

                modal.style.display = 'block';
            });
        });

        closeModal.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Show Toast Notification
        function showToast() {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }
        }
        window.onload = showToast;
    </script>
</body>
</html>