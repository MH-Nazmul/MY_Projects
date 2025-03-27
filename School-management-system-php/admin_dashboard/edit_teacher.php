<?php
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Include the database connection
include '../db_connect.php';

// Handle GET request to display the edit form
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT) || $_GET['id'] <= 0) {
        header("Location: teacher_management.php?error=Invalid+teacher+ID");
        exit();
    }

    $id = (int) $_GET['id'];
    try {
        $stmt = $conn->prepare("SELECT id, fname, lname, email, mobile_number, date_of_birth, gender FROM teachers WHERE id = ?");
        $stmt->execute([$id]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$teacher) {
            header("Location: teacher_management.php?error=No+teacher+found+with+ID+$id");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: teacher_management.php?error=Failed+to+fetch+teacher:+" . urlencode($e->getMessage()));
        exit();
    }
}

// Handle POST request to update the teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['id']) || !filter_var($_POST['id'], FILTER_VALIDATE_INT) || $_POST['id'] <= 0) {
        header("Location: teacher_management.php?error=Invalid+teacher+ID");
        exit();
    }

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: teacher_management.php?error=Invalid+CSRF+token");
        exit();
    }

    $id = (int) $_POST['id'];
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $email = $_POST['email'];
    $mobile_number = $_POST['mobile_number'];
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];

    // Basic validation
    if (empty($fname) || empty($lname) || empty($email)) {
        header("Location: edit_teacher.php?id=$id&error=First+name,+last+name,+and+email+are+required");
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: edit_teacher.php?id=$id&error=Invalid+email+address");
        exit();
    }

    try {
        $stmt = $conn->prepare("UPDATE teachers SET fname = ?, lname = ?, email = ?, mobile_number = ?, date_of_birth = ?, gender = ? WHERE id = ?");
        $stmt->execute([$fname, $lname, $email, $mobile_number, $date_of_birth, $gender, $id]);

        header("Location: teacher_management.php?success=Teacher+updated+successfully");
        exit();
    } catch (PDOException $e) {
        header("Location: teacher_management.php?error=Failed+to+update+teacher:+" . urlencode($e->getMessage()));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Teacher</title>
    <link rel="stylesheet" href="../CSS/admin.css">
    <style>
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        button {
            padding: 10px 20px;
            background-color: #28a745;
            color: #fff;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #218838;
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
        <div class="sidebar">
            <h2>Admin Dashboard</h2>
            <ul>
                <li><a href="admit_teacher.php">Admit Teacher</a></li>
                <li><a href="admit_student.php">Admit Student</a></li>
                <li><a href="manage_student.php">Manage Students</a></li>
                <li><a href="view_dues.php">View Dues & Info</a></li>
                <li><a href="teacher_management.php">Manage Teachers</a></li>
                <li><a href="settings.php">School Settings</a></li>
                <li><a href="complaints.php">View Complaints</a></li>
                <li><a href="schedule.php">Class Schedule</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
        <div class="main-content">
            <h1>Edit Teacher</h1>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($teacher['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="fname">First Name</label>
                    <input type="text" id="fname" name="fname" value="<?php echo htmlspecialchars($teacher['fname'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="lname">Last Name</label>
                    <input type="text" id="lname" name="lname" value="<?php echo htmlspecialchars($teacher['lname'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="mobile_number">Mobile Number</label>
                    <input type="text" id="mobile_number" name="mobile_number" value="<?php echo htmlspecialchars($teacher['mobile_number'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($teacher['date_of_birth'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="Male" <?php echo $teacher['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $teacher['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo $teacher['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <button type="submit">Update Teacher</button>
            </form>
        </div>
    </div>

    <!-- Toast Notification Container (for errors on this page) -->
    <?php if (isset($_GET['error'])) { ?>
        <div id="toast" class="toast error"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></div>
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
                window.history.replaceState({}, document.title, window.location.pathname + "?id=<?php echo $teacher['id']; ?>");
            }
        }
        window.onload = showToast;
    </script>
</body>
</html>