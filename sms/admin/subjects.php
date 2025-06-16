<?php
// subjects.php
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

// Handle adding a new subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    $subject_name = trim($_POST['subject_name']);
    if (!empty($subject_name)) {
        try {
            $stmt = $conn->prepare("INSERT INTO subjects (subject_name) VALUES (?)");
            $stmt->execute([$subject_name]);
            header("Location: subjects.php?success=Subject added successfully.");
            exit();
        } catch (PDOException $e) {
            $error_message = "Failed to add subject: " . $e->getMessage();
        }
    } else {
        $error_message = "Subject name cannot be empty.";
    }
}

// Handle deleting a subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subject'])) {
    $subject_id = (int)$_POST['subject_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
        header("Location: subjects.php?success=Subject removed successfully.");
        exit();
    } catch (PDOException $e) {
        $error_message = "Failed to remove subject: " . $e->getMessage();
    }
}

// Fetch all subjects
try {
    $stmt = $conn->prepare("SELECT id, subject_name, created_at FROM subjects ORDER BY subject_name");
    $stmt->execute();
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Failed to fetch subjects: " . $e->getMessage();
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
    <title>Manage Subjects - School Management System</title>
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
        .form-section input[type="text"] {
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

            <!-- Add Subject Form -->
            <div class="form-section">
                <h2>Add New Subject</h2>
                <form method="POST">
                    <label for="subject_name">Subject Name:</label>
                    <input type="text" id="subject_name" name="subject_name" required>
                    <button type="submit" name="add_subject">Add Subject</button>
                </form>
            </div>

            <!-- Subjects Table -->
            <div class="table-section">
                <h2>Existing Subjects</h2>
                <?php if (isset($error_message) && !empty($error_message)) { ?>
                    <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                <?php } elseif (empty($subjects)) { ?>
                    <p class="empty-message">No subjects have been added yet.</p>
                <?php } else { ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Subject Name</th>
                                <th>Created At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $subject) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($subject['created_at']); ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this subject?');">
                                            <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                            <button type="submit" name="delete_subject" class="delete-btn">Delete</button>
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