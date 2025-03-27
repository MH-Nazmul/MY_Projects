<?php
// Start the session
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.html");
    exit();
}

// Include the database connection
include '../db_connect.php';

// Generate a CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables for messages
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid CSRF token.";
    } else {
        // Get form inputs
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $class = trim($_POST['class']);
        $section = trim($_POST['section']);
        $parents_contact = trim($_POST['parents_contact']);

        // Validate inputs
        if (empty($fname) || empty($lname) || empty($username) || empty($password) || empty($confirm_password) || empty($class) || empty($section) || empty($parents_contact)) {
            $error_message = "All fields are required.";
        } elseif ($password !== $confirm_password) {
            $error_message = "Passwords do not match.";
        } elseif (!preg_match('/^[0-9]{11}$/', $parents_contact)) { // Basic phone number validation (10 digits)
            $error_message = "Parent's contact must be a 11-digit phone number.";
        } else {
            try {
                // Check if the username is already in use
                $stmt = $conn->prepare("SELECT id FROM students WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error_message = "Username is already in use.";
                } else {
                    // Hash the password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert the student into the database
                    $stmt = $conn->prepare("
                        INSERT INTO students (username, password, fname, lname, class, section, parents_mobile)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$username, $hashed_password, $fname, $lname, $class, $section, $parents_contact]);

                    $success_message = "Student admitted successfully.";
                    
                    // Clear the form by resetting the POST data
                    $_POST = array();
                }
            } catch (PDOException $e) {
                $error_message = "Failed to admit student: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admit Student</title>
    <link rel="stylesheet" href="../CSS/admin.css">
    <style>
        /* Form Styles */
        .main-content form {
            max-width: 500px;
            margin: 20px 0;
        }
        .main-content label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .main-content input[type="text"],
        .main-content input[type="password"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .main-content button {
            padding: 10px 20px;
            background-color: #28a745;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .main-content button:hover {
            background-color: #218838;
        }

        /* Toast Notification Styles */
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
        <!-- Sidebar -->
        <div class="sidebar">
            <h2>Admin Dashboard</h2>
            <ul>
                <li><a href="admit_student.php">Admit Student</a></li>
                <li><a href="teacher_management.php">Teacher Managements</a></li>
                <li><a href="manage_student.php">Manage Students</a></li>
                <li><a href="view_dues.php">View Dues & Info</a></li>
                <li><a href="settings.php">School Settings</a></li>
                <li><a href="complaints.php">View Complaints</a></li>
                <li><a href="schedule.php">Class Schedule</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <h1>Admit Student</h1>
            <form method="POST">
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
                
                <label for="parents_contact">Parent's Contact:</label>
                <input type="text" id="parents_contact" name="parents_contact" value="<?php echo isset($_POST['parents_contact']) ? htmlspecialchars($_POST['parents_contact']) : ''; ?>" required>
                
                <button type="submit">Admit Student</button>
            </form>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <?php if ($success_message) { ?>
        <div id="toast" class="toast success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php } elseif ($error_message) { ?>
        <div id="toast" class="toast error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php } ?>

    <!-- JavaScript for Toast Notification -->
    <script>
        function showToast() {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
                // Remove the query parameter from the URL (if any)
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }
        window.onload = showToast;
    </script>
</body>
</html>