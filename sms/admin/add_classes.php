<?php
// add_classes.php
session_start();
include '../db_connect.php';

// Check if the user is already logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: ../logout.php");
    exit();
}

// Fetch school name and tagline from settings
try {
    $stmt = $conn->prepare("SELECT school_name, tag_line FROM settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $school_name = $settings['school_name'] ?? 'School Name';
    $tag_line = $settings['tag_line'] ?? 'Tagline';
} catch (PDOException $e) {
    $school_name = 'School Name';
    $tag_line = 'Tagline';
}

// Handle adding a new class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
    $class_name = trim($_POST['class_name']);
    $semester_count = (int)$_POST['semester_number'];

    if (!empty($class_name) && $semester_count > 0 && $semester_count <= 4) {
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare("INSERT INTO classes (class_name) VALUES (?)");
            $stmt->execute([$class_name]);
            $class_id = $conn->lastInsertId();

            $current_year = date('Y');
            $quarter_duration = 3;
            for ($i = 1; $i <= $semester_count; $i++) {
                $start_month = ($i - 1) * 3 + 1;
                $end_month = $start_month + 2;
                $start_date = date('Y-m-d', strtotime("$current_year-$start_month-01"));
                $end_date = date('Y-m-d', strtotime("$current_year-$end_month-" . date('t', strtotime("$current_year-$end_month-01"))));
                $stmt = $conn->prepare("INSERT INTO semesters (class_id, semester_number, start_date, end_date) VALUES (?, ?, ?, ?)");
                $stmt->execute([$class_id, $i, $start_date, $end_date]);
            }

            $conn->commit();
            header("Location: add_classes.php?success=Class and $semester_count semester(s) added successfully.");
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $error_message = "Failed to add class or semesters: " . $e->getMessage();
            error_log("Add error at " . date('Y-m-d H:i:s') . ": " . $e->getMessage());
        }
    } else {
        $error_message = "Class name is required, and semester count must be between 1 and 4.";
    }
}

// Handle deleting a class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_class'])) {
    $class_id = (int)$_POST['class_id'];
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("DELETE FROM semesters WHERE class_id = ?");
        $stmt->execute([$class_id]);
        $deleted_semesters = $stmt->rowCount();
        error_log("Deleted $deleted_semesters semesters for class_id $class_id at " . date('Y-m-d H:i:s'));

        $stmt = $conn->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$class_id]);
        $deleted_classes = $stmt->rowCount();
        error_log("Deleted $deleted_classes class with id $class_id at " . date('Y-m-d H:i:s'));

        $conn->commit();
        header("Location: add_classes.php?success=Class removed successfully.");
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = "Failed to remove class: " . $e->getMessage();
        error_log("Delete error at " . date('Y-m-d H:i:s') . ": " . $e->getMessage() . " for class_id $class_id");
    }
}

// Fetch all classes with their semesters
$classes = [];
try {
    $stmt = $conn->prepare("SELECT id, class_name, created_at FROM classes ORDER BY class_name");
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($classes as &$class) {
        $stmt = $conn->prepare("SELECT semester_number, start_date, end_date FROM semesters WHERE class_id = ? ORDER BY semester_number");
        $stmt->execute([$class['id']]);
        $class['semesters'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = "Failed to fetch classes or semesters: " . $e->getMessage();
    error_log("Fetch error at " . date('Y-m-d H:i:s') . ": " . $e->getMessage());
}

// Check for success or error messages
$success_message = isset($_GET['success']) ? $_GET['success'] : '';
$error_message = isset($_GET['error']) ? $_GET['error'] : (isset($error_message) ? $error_message : '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes - School Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            display: flex;
            min-height: 100vh;
        }
        .main-content {
            flex: 1;
            padding: 20px;
        }
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
        .form-section, .table-section {
            max-width: 900px;
            margin: 0 auto 20px;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }
        .form-section h2, .table-section h2 {
            margin-top: 0;
            font-size: 20px;
            color: #2c3e50;
        }
        .form-section label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-section input[type="text"],
        .form-section input[type="number"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .form-section button {
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .form-section button:hover {
            background-color: #218838;
        }
        .table-section table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .table-section th, .table-section td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .table-section th {
            background: #f5f5f5;
            color: #333;
            font-weight: bold;
        }
        .table-section tr:hover {
            background: #f9f9f9;
        }
        .table-section .delete-btn {
            padding: 6px 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .table-section .delete-btn:hover {
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
        <!-- Main Content -->
        <div class="main-content">
            <!-- Title Section -->
            <div class="title-section">
                <h1><?php echo htmlspecialchars($school_name); ?></h1>
                <p><?php echo htmlspecialchars($tag_line); ?></p>
            </div>

            <!-- Add Class Form -->
            <div class="form-section">
                <h2>Add New Class</h2>
                <form method="POST">
                    <label for="class_name">Class Name:</label>
                    <input type="text" id="class_name" name="class_name" required placeholder="e.g., Class 1">
                    <label for="semester_number">Number of Semesters to Add:</label>
                    <input type="number" id="semester_number" name="semester_number" min="1" max="4" required value="1" placeholder="1 to 4">
                    <button type="submit" name="add_class">Add Class and Semesters</button>
                </form>
                <?php if (isset($error_message) && !empty($error_message)): ?>
                    <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                <?php endif; ?>
            </div>

            <!-- Classes Table -->
            <div class="table-section">
                <h2>Existing Classes</h2>
                <?php if (isset($error_message) && !empty($error_message)) { ?>
                    <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                <?php } elseif (empty($classes)) { ?>
                    <p class="empty-message">No classes have been added yet.</p>
                <?php } else { ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Class Name</th>
                                <th>Created At</th>
                                <th>Semesters</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($class['created_at']); ?></td>
                                    <td>
                                        <?php if (!empty($class['semesters'])) {
                                            echo "<ul>";
                                            foreach ($class['semesters'] as $semester) {
                                                echo "<li>Sem " . htmlspecialchars($semester['semester_number']) . ": " .
                                                     htmlspecialchars($semester['start_date']) . " to " .
                                                     htmlspecialchars($semester['end_date']) . "</li>";
                                            }
                                            echo "</ul>";
                                        } else {
                                            echo "No semesters";
                                        } ?>
                                    </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this class? This will also delete associated subjects and schedules.');">
                                            <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                            <button type="submit" name="delete_class" class="delete-btn">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>
        </div>
    </div>

    <?php include '../footer.php'; ?>

    <!-- Toast Notification -->
    <?php if ($success_message) { ?>
        <div id="toast" class="toast success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php } elseif ($error_message) { ?>
        <div id="toast" class="toast error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php } ?>

    <script>
        function showToast() {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }
        window.onload = showToast;
    </script>
</body>
</html>
<?php $conn = null; ?>