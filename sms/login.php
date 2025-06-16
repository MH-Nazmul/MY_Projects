<?php
session_start();
include 'db_connect.php';

// Check if the user is already logged in
// if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
//     if ($_SESSION['user_type'] === 'admin') {
//         header("Location: admin/dashboard.php");
//         exit();
//     } elseif ($_SESSION['user_type'] === 'teacher') {
//         header("Location: teacher/dashboard.php");
//         exit();
//     } elseif ($_SESSION['user_type'] === 'student') {
//         header("Location: student/dashboard.php");
//         exit();
//     }
// }


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $user_type = $_POST['user_type'];

    if (empty($username) || empty($password) || empty($user_type)) {
        $error = "All fields are required.";
    } else {
        try {
            if ($user_type === 'admin') {
                $stmt = $conn->prepare("SELECT id, password FROM admins WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = 'admin';
                    header("Location: admin/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid username or password.";
                }
            } elseif ($user_type === 'teacher') {
                $stmt = $conn->prepare("SELECT id, password FROM teachers WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = 'teacher';
                    header("Location: teacher/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid username or password.";
                }
            } elseif ($user_type === 'student') {
                $stmt = $conn->prepare("SELECT id, password FROM students WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = 'student';
                    header("Location: student/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid username or password.";
                }
            } elseif ($user_type === 'accountant') {
                $stmt = $conn->prepare("SELECT id, password FROM accountants WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = 'accountant';
                    header("Location: accountants/offline_payment.php");
                    exit();
                } else {
                    $error = "Invalid username or password.";
                }
            
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - School Management System</title>
    <link rel="stylesheet" href="CSS/login.css">
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <?php if ($error) { ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <div class="form-group">
                <label for="user_type">User Type</label>
                <select id="user_type" name="user_type" required>
                    <option value="">Select user type</option>
                    <option value="admin">Admin</option>
                    <option value="teacher">Teacher</option>
                    <option value="student">Student</option>
                    <option value="accountant">Accountant</option>
                </select>
            </div>
            <dev style="display:flex;gap:10%">
            <button type="submit">Login</button>
            <button type="submit" onclick="window.location.href='index.php#home'">Home</button>
        </div>
        <center><p>Don't have an account? Contact the administrator.</p></center>
        </form>
       
    </div>
</body>
</html>