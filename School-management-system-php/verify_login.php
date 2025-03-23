<?php
// verify_login.php

// Start the session
session_start();

// Database connection details
$host = 'localhost'; // Replace with your database host
$dbname = 'smsdb'; // Replace with your database name
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password

// Establish a database connection
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Retrieve form data
$email = $_POST['email'];
$password = $_POST['password'];
$role = $_POST['role'];

// Determine the table based on the selected role
$table = '';
switch ($role) {
    case 'admin':
        $table = 'admin';
        break;
    case 'teacher':     
        $table = 'teacher';
        break;
    case "student":
        $table = 'student';
        break;
    default:
        die("Invalid role selected.");
}
echo $role;

// Prepare and execute the SQL query
$stmt = $conn->prepare("SELECT * FROM $table WHERE email = :email");
$stmt->bindParam(':email', $email);
$stmt->execute();

// Fetch the user record
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // Verify the password
    if (password_verify($password, $user['password'])) {
        // Login successful
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $role;

        // Redirect based on role
        switch ($role) {
            case 'admin':
                header("Location:admin_dashboard.php");
                break;
            case 'teacher':
                header("Location:teacher_dashboard.php");
                break;
            case 'student':
                header("Location:student_dashboard.php");
                break;
            default:
                die("Invalid role selected.");
        }
        exit();
    } else {
        // Invalid password
        echo "Invalid password. <a href='login.html'>Try again</a>";
    }
} else {
    // User not found
    echo "User not found. <a href='login.html'>Try again</a>";
}
?>