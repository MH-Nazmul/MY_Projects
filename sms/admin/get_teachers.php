<?php
include '../db_connect.php';

header('Content-Type: application/json');

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

if ($class_id <= 0 || $subject_id <= 0) {
    echo json_encode(['success' => false, 'teachers' => [], 'error' => 'Invalid class_id or subject_id']);
    exit();
}

try {
    // Find the class_subject_id for the given class_id and subject_id
    $stmt = $conn->prepare("
        SELECT id 
        FROM class_subjects 
        WHERE class_id = ? AND subject_id = ?
    ");
    $stmt->execute([$class_id, $subject_id]);
    $class_subject = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class_subject) {
        echo json_encode(['success' => false, 'teachers' => [], 'error' => 'No matching class_subject found']);
        exit();
    }

    $class_subject_id = $class_subject['id'];

    // Fetch teachers for the class_subject_id
    $stmt = $conn->prepare("
        SELECT t.id, CONCAT(t.fname, ' ', t.lname) AS full_name
        FROM teachers t
        JOIN teacher_subjects ts ON t.id = ts.teacher_id
        WHERE ts.class_subject_id = ?
        ORDER BY t.fname, t.lname
    ");
    $stmt->execute([$class_subject_id]);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($teachers)) {
        echo json_encode(['success' => true, 'teachers' => [], 'debug' => 'No teachers found for class_subject_id ' . $class_subject_id]);
    } else {
        echo json_encode(['success' => true, 'teachers' => $teachers]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'teachers' => [], 'error' => $e->getMessage()]);
}
?>