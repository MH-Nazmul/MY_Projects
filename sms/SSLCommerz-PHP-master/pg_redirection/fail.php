<?php
// fail.php

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
error_log("Fail Callback Data: " . print_r($requestData, true));

// Get failure reason if available
$failureReason = $requestData['error'] ?? $requestData['failedreason'] ?? 'Unknown error';

if (isset($_POST['tran_id']) || isset($_GET['tran_id'])) {
    $tran_id = $_POST['tran_id'] ?? $_GET['tran_id'];

    // Update order status to 'failed'
    $stmt = $conn->prepare("UPDATE orders SET status = 'failed' WHERE tran_id = ?");
    $stmt->execute([$tran_id]);

    // Fetch due_id to update student_dues
    $stmt = $conn->prepare("SELECT due_id FROM orders WHERE tran_id = ?");
    $stmt->execute([$tran_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($order && isset($order['due_id'])) {
        $stmt = $conn->prepare("UPDATE student_dues SET status = 'pending' WHERE id = ?");
        $stmt->execute([$order['due_id']]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed</title>
    <style>
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f8d7da;
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
            color: #721c24;
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
            <p>Payment failed or invalid. Reason: <?php echo htmlspecialchars($failureReason); ?></p>
            <a href="https://mhnazmul.free.nf/student/payments.php">Return to Payments</a>
        </div>
    </div>
</body>
</html>