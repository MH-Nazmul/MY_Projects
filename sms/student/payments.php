<?php
// student/payments.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

// Start the session
session_start();

// Check if the user is logged in (student)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../logout.php");
    exit();
}

// Include database connection
include '../db_connect.php';

// Check database connection
if (!$conn) {
    die("Database connection failed.");
}

// Include SSLCOMMERZ SDK files
$config = require_once __DIR__ . '/../SSLCommerz-PHP-master/config/config.php';
if (!defined('PROJECT_PATH')) {
    define('PROJECT_PATH', 'https://mhnazmul.free.nf');
}
require_once __DIR__ . '/../SSLCommerz-PHP-master/lib/AbstractSslCommerz.php';
require_once __DIR__ . '/../SSLCommerz-PHP-master/lib/SslCommerzNotification.php';

// Handle namespace if present
if (!class_exists('SslCommerzNotification')) {
    if (class_exists('SSLCOMMERZ\SslCommerzNotification')) {
        class_alias('SSLCOMMERZ\SslCommerzNotification', 'SslCommerzNotification');
    } else {
        die("Class SslCommerzNotification not found.");
    }
}

// Fetch student's outstanding dues
$student_id = $_SESSION['user_id'];
error_log("Session Variables: " . print_r($_SESSION, true));
$stmt = $conn->prepare("SELECT id, amount, description FROM student_dues WHERE student_id = ? AND status = 'pending'");
$stmt->bindParam(1, $student_id, PDO::PARAM_INT);
if (!$stmt->execute()) {
    error_log("Query Execution Failed: " . print_r($stmt->errorInfo(), true));
}
$dues = $stmt->fetchAll(PDO::FETCH_ASSOC);
error_log("Dues Query Result: " . print_r($dues, true));

// Handle payment initiation
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['initiate_payment'])) {
    $due_id = $_POST['due_id'] ?? null;
    $amount = $_POST['amount'] ?? null;

    if ($due_id && $amount) {
        $tran_id = 'STU' . $student_id . '_' . $due_id . '_' . time();

        // Prepare payment data with all required fields
        $post_data = [
            'store_id' => $config['apiCredentials']['store_id'],
            'store_passwd' => $config['apiCredentials']['store_passwd'],
            'total_amount' => $amount,
            'currency' => 'BDT',
            'tran_id' => $tran_id,
            'success_url' => PROJECT_PATH . $config['success_url'],
            'fail_url' => PROJECT_PATH . $config['failed_url'],
            'cancel_url' => PROJECT_PATH . $config['cancel_url'],
            'ipn_url' => PROJECT_PATH . $config['ipn_url'],
            'cus_name' => $_SESSION['user_name'] ?? 'Student ' . $student_id,
            'cus_email' => $_SESSION['user_email'] ?? 'student' . $student_id . '@example.com',
            'cus_add1' => 'Dhaka',
            'cus_city' => 'Dhaka',
            'cus_country' => 'Bangladesh',
            'cus_phone' => '01711111111',
            'product_name' => 'School Fee',
            'product_category' => 'Education',
            'product_profile' => 'general',
            'shipping_method' => 'NO',
            'api_domain' => $config['api_domain'],
            'projectPath' => PROJECT_PATH,
        ];

        error_log("Payment Initiation Data: " . print_r($post_data, true));
        try {
            $sslc = new SslCommerzNotification();
            $response = $sslc->makePayment($post_data, 'hosted');
            error_log("SSLCOMMERZ Response: " . print_r($response, true));
            if (is_array($response) && isset($response['GatewayPageURL']) && !empty($response['GatewayPageURL'])) {
                $stmt = $conn->prepare("INSERT INTO orders (tran_id, amount, currency, status, customer_name, customer_email, due_id) VALUES (?, ?, ?, 'pending', ?, ?, ?)");
                $stmt->execute([$tran_id, $amount, 'BDT', $post_data['cus_name'], $post_data['cus_email'], $due_id]);
                header("Location: " . $response['GatewayPageURL']);
                exit();
            } else {
                $message = "Payment initiation failed: " . (is_array($response) ? ($response['failedreason'] ?? 'Unknown error') : 'Invalid response');
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            error_log("Payment Error: " . $e->getMessage());
        }
    } else {
        $message = "Invalid payment request.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Payment</title>
    <link rel="stylesheet" href="../CSS/admin.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 290px;
            background-color: #2c3e50;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100%;
            /* Placeholder - will be replaced with your dashboard sidebar code */
        }
        .sidebar a {
            margin-left:20px;
            padding: 25px 20px;
            text-decoration: none;
            color: white;
            display: block;
        }
        .sidebar a:hover {
            background-color: #34495e;
        }
        .content {
            margin-left: 290px;
            padding: 20px;
            width: calc(100% - 250px);
        }
        .header {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            text-align: center;
        }
        .payment-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .payment-section h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
        }
        .due-item {
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .due-item button {
            background-color: #2ecc71;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .due-item button:hover {
            background-color: #27ae60;
        }
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2>Student Dashboard</h2>
            <ul>
                <li><a href="dashboard.php">Profile</a></li>
                <li><a href="assignments.php">Assignments</a></li>
                <li><a href="class_routine.php">Class Routine</a></li>
                <li><a href="exam_schedule.php">Exam Schedule</a></li>
                <li><a href="results.php">Results</a></li>
                <li><a href="notices.php">Notices</a></li>
                <li><a href="payments.php">Dues & Payments</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
    <div class="content">
        <div class="header">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Student'); ?></h2>
        </div>
        <div class="payment-section">
            <h2>Pay School Fees</h2>
            <?php if (!empty($message)): ?>
                <div class="message <?php echo strpos($message, 'failed') !== false ? 'error' : 'success'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <?php if (empty($dues)): ?>
                <p>No outstanding dues to pay.</p>
            <?php else: ?>
                <p>Below are your outstanding school fees. Click "Pay Now" to proceed with payment.</p>
                <?php foreach ($dues as $due): ?>
                    <div class="due-item">
                        <div>
                            <strong><?php echo htmlspecialchars($due['description']); ?>:</strong>
                            <?php echo htmlspecialchars($due['amount']); ?> BDT
                        </div>
                        <form method="POST" action="payments.php">
                            <input type="hidden" name="initiate_payment" value="1">
                            <input type="hidden" name="due_id" value="<?php echo htmlspecialchars($due['id']); ?>">
                            <input type="hidden" name="amount" value="<?php echo htmlspecialchars($due['amount']); ?>">
                            <button type="submit">Pay Now</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>