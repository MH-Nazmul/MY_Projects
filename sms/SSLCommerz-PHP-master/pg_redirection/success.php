<?php
// success.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start the session
session_start();

// Include database connection
include '../../db_connect.php';

// Check database connection
if (!$conn) {
    die("Database connection failed.");
}

// Log the incoming data for debugging
$requestData = $_POST + $_GET;
error_log("Success Callback Data: " . print_r($requestData, true));

if (isset($_POST['tran_id']) || isset($_GET['tran_id'])) {
    $tran_id = $_POST['tran_id'] ?? $_GET['tran_id'];

    // Update order status to 'success'
    $stmt = $conn->prepare("UPDATE orders SET status = 'success' WHERE tran_id = ?");
    $stmt->execute([$tran_id]);
    error_log("Updated orders status for tran_id: " . $tran_id);

    // Fetch due_id from orders
    $stmt = $conn->prepare("SELECT due_id FROM orders WHERE tran_id = ?");
    $stmt->execute([$tran_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Fetched due_id from orders: " . print_r($order, true));

    if ($order && isset($order['due_id']) && $order['due_id'] > 0) {
        $due_id = $order['due_id'];
        $stmt = $conn->prepare("UPDATE student_dues SET status = 'paid' WHERE id = ?");
        try {
            $stmt->execute([$due_id]);
            $rowsAffected = $stmt->rowCount();
            if ($rowsAffected > 0) {
                error_log("Updated student_dues status to 'paid' for due_id: " . $due_id);
            } else {
                error_log("No rows updated in student_dues for due_id: " . $due_id . ". Check if due_id exists.");
                // Fallback: Try to find due_id via student_id and amount
                $student_id = $_SESSION['user_id'];
                $stmt = $conn->prepare("SELECT id FROM student_dues WHERE student_id = ? AND amount = ? AND status = 'pending' LIMIT 1");
                $stmt->execute([$student_id, 500]); // Adjust amount if different
                $fallbackDue = $stmt->fetchColumn();
                if ($fallbackDue) {
                    $stmt = $conn->prepare("UPDATE student_dues SET status = 'paid' WHERE id = ?");
                    $stmt->execute([$fallbackDue]);
                    if ($stmt->rowCount() > 0) {
                        error_log("Fallback updated student_dues status to 'paid' for due_id: " . $fallbackDue);
                    }
                } else {
                    error_log("No matching due found for fallback.");
                }
            }
        } catch (PDOException $e) {
            error_log("Database error updating student_dues for due_id " . $due_id . ": " . $e->getMessage());
        }
    } else {
        error_log("Invalid or missing due_id for tran_id: " . $tran_id);
        // Fallback: Attempt to update based on student_id and amount
        $student_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id FROM student_dues WHERE student_id = ? AND amount = ? AND status = 'pending' LIMIT 1");
        $stmt->execute([$student_id, 500]); // Adjust amount if different
        $fallbackDue = $stmt->fetchColumn();
        if ($fallbackDue) {
            $stmt = $conn->prepare("UPDATE student_dues SET status = 'paid' WHERE id = ?");
            $stmt->execute([$fallbackDue]);
            if ($stmt->rowCount() > 0) {
                error_log("Fallback updated student_dues status to 'paid' for due_id: " . $fallbackDue);
            } else {
                error_log("Fallback update failed for due_id: " . $fallbackDue);
            }
        } else {
            error_log("No matching due found for fallback.");
        }
    }
} else {
    error_log("Success Callback Missing tran_id");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #d4edda;
            margin: 0;
        }
        .message-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .message-box p {
            color: #155724;
            margin-bottom: 15px;
        }
        .message-box a {
            color: #007bff;
            text-decoration: none;
        }
        .message-box a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="message-box">
            <p>Payment successful! Transaction ID: <?php echo htmlspecialchars($tran_id ?? 'Unknown'); ?></p>
            <a href="https://mhnazmul.free.nf/student/payments.php">Return to Payments</a>
        </div>
    </div>
</body>
</html>