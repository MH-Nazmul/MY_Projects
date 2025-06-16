<?php
// save_attendance.php

header('Content-Type: application/json');
date_default_timezone_set('Asia/Dhaka');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

include '../db_connect.php';
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

$teacher_id = $_SESSION['user_id'];
$student_id = $_POST['student_id'] ?? null;
$date = $_POST['date'] ?? null;
$status = $_POST['status'] ?? null;
$class_name = $_POST['class_name'] ?? null;

if (!$student_id || !$date || !$status || !$class_name) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

if (!in_array($status, ['present', 'absent'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit();
}

try {
    $conn->beginTransaction();

    $check_query = "SELECT COUNT(*) FROM attendance WHERE student_id = ? AND date = ? AND teacher_id = ? AND class = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->execute([$student_id, $date, $teacher_id, $class_name]);
    $exists = $check_stmt->fetchColumn();

    if ($exists) {
        $update_query = "UPDATE attendance SET status = ? WHERE student_id = ? AND date = ? AND teacher_id = ? AND class = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->execute([$status, $student_id, $date, $teacher_id, $class_name]);
        error_log("Updated student_id $student_id, date $date, status $status, rows: " . $update_stmt->rowCount());
    } else {
        $insert_query = "INSERT INTO attendance (student_id, status, date, teacher_id, class) VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->execute([$student_id, $status, $date, $teacher_id, $class_name]);
        error_log("Inserted student_id $student_id, date $date, status $status, ID: " . $conn->lastInsertId());
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Save error: " . $e->getMessage() . ", POST: " . print_r($_POST, true));
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>