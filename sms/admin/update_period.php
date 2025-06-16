<?php
session_start();
include '../db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    $response['message'] = 'Unauthorized access';
    echo json_encode($response);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_period') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $day = $_POST['day'] ?? '';
        $period = (int)($_POST['period'] ?? 0);
        $start_time = $_POST['start_time'] ?? '00:00:00';
        $end_time = $_POST['end_time'] ?? '00:00:00';
        $subject_id = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
        $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;

        if (!$class_id || !$day || !$period || !$start_time || !$end_time) {
            throw new Exception('Missing required fields');
        }

        $stmt = $conn->prepare("
            INSERT INTO schedules (class_id, day, period, start_time, end_time, subject_id, teacher_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$class_id, $day, $period, $start_time, $end_time, $subject_id, $teacher_id]);

        $response['success'] = true;
        $response['message'] = 'Period added successfully';
        $response['schedule_id'] = $conn->lastInsertId();
    } elseif ($action === 'update_period') {
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        $start_time = $_POST['start_time'] ?? '00:00:00';
        $end_time = $_POST['end_time'] ?? '00:00:00';
        $subject_id = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
        $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;

        if (!$schedule_id || !$start_time || !$end_time) {
            throw new Exception('Missing required fields');
        }

        $stmt = $conn->prepare("
            UPDATE schedules 
            SET start_time = ?, end_time = ?, subject_id = ?, teacher_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$start_time, $end_time, $subject_id, $teacher_id, $schedule_id]);

        $response['success'] = true;
        $response['message'] = 'Period updated successfully';
    } elseif ($action === 'delete_period') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $period = (int)($_POST['period'] ?? 0);

        if (!$class_id || !$period) {
            throw new Exception('Missing required fields');
        }

        $stmt = $conn->prepare("DELETE FROM schedules WHERE class_id = ? AND period = ?");
        $stmt->execute([$class_id, $period]);

        $response['success'] = true;
        $response['message'] = 'Period deleted successfully';
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Error in update_period.php: " . $e->getMessage());
}

echo json_encode($response);
?>