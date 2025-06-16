<?php
// fetch_assignments.php

include '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacher_id'])) {
    $teacher_id = $_POST['teacher_id'];

    try {
        // Fetch the class_subject_ids assigned to the teacher
        $stmt = $conn->prepare("SELECT class_subject_id FROM teacher_subjects WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        $assignments = $stmt->fetchAll(PDO::FETCH_COLUMN, 0); // Fetch only the class_subject_id column as an array

        // Return the assignments as a JSON array
        echo json_encode($assignments);
    } catch (PDOException $e) {
        // Return an error response
        echo json_encode(['error' => 'Failed to fetch assignments: ' . $e->getMessage()]);
    }
} else {
    // Return an error if the request is invalid
    echo json_encode(['error' => 'Invalid request']);
}

exit();
?>