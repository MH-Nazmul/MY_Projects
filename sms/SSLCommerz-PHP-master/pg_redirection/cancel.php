<?php
// SSLCommerz-PHP-master/pg_redirection/cancel.php

session_start();
include '../../db_connect.php';

if (isset($_GET['tran_id'])) {
    $tran_id = $_GET['tran_id'];
    try {
        $stmt = $conn->prepare("UPDATE orders SET status = 'canceled' WHERE tran_id = ?");
        $stmt->execute([$tran_id]);
        $message = "Payment canceled. Transaction ID: " . htmlspecialchars($tran_id);
    } catch (PDOException $e) {
        error_log("Database error in cancel.php: " . $e->getMessage());
        $message = "Payment canceled, but database update failed. Contact support.";
    }
} else {
    $message = "Payment canceled or invalid.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Canceled</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f0f0f0;
        }
        .container {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .error {
            color: #721c24;
            background-color: #f8d7da;
            padding: 10px;
            border-radius: 4px;
        }
        a {
            color: #3498db;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <p><a href="../../student/payments.php">Return to Payments</a></p>
    </div>
</body>
</html>