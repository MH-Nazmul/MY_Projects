<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if user is logged in and is an accountant
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'accountant') {
    header("Location: /index.php");
    exit();
}

include '../db_connect.php';

$accountant_id = $_SESSION['user_id'];

// Fetch accountant name
$stmt = $conn->prepare("SELECT name FROM accountants WHERE id = ?");
$stmt->execute([$accountant_id]);
$accountant = $stmt->fetch(PDO::FETCH_ASSOC);
$accountant_name = $accountant ? ($accountant['name'] ) : 'Unknown Accountant';

$message = '';
$student_dues = [];
$filtered_student = null;

// Log server environment for debugging
error_log("SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME']);
error_log("DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT']);
error_log("PHP_SELF: " . $_SERVER['PHP_SELF']);

// Check if payment was just processed and retain context
if (isset($_SESSION['last_payment']) && $_SESSION['last_payment']['student_id'] == ($_GET['student_id'] ?? '')) {
    $filtered_student = $_SESSION['last_payment']['student'];
    $student_dues = $_SESSION['last_payment']['dues'];
    // Do not unset print_trigger here; handle it after printing
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter'])) {
    $filter_type = $_POST['filter_type'] ?? '';
    $filter_value = trim($_POST['filter_value'] ?? '');

    if ($filter_value) {
        $sql = "SELECT id, fname, lname, class, due_payments FROM students WHERE ";
        $params = [];
        switch ($filter_type) {
            case 'id':
                $sql .= "id = ?";
                $params = [$filter_value];
                break;
            case 'fname':
                $sql .= "fname LIKE ?";
                $params = ["%$filter_value%"];
                break;
            case 'mother_name':
                $sql .= "mother_name LIKE ?";
                $params = ["%$filter_value%"];
                break;
            case 'father_name':
                $sql .= "father_name LIKE ?";
                $params = ["%$filter_value%"];
                break;
            case 'parents_mobile':
                $sql .= "parents_mobile = ?";
                $params = [$filter_value];
                break;
            case 'class':
                $sql .= "class = ?";
                $params = [$filter_value];
                break;
            default:
                $message = "Invalid filter type.";
                break;
        }

        if ($params) {
            try {
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $filtered_student = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($filtered_student) {
                    $stmt = $conn->prepare("SELECT id, student_id, amount, description, created_at, status FROM student_dues WHERE student_id = ? AND status IN ('pending', 'partial') ORDER BY created_at ASC");
                    $stmt->execute([$filtered_student['id']]);
                    $student_dues = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $message = "No student found with the given criteria.";
                }
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
            }
        }
    } else {
        $message = "Please enter a value to filter.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
    $student_id = $_POST['student_id'] ?? '';
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($amount_paid > 0 && $student_id) {
        $conn->beginTransaction();

        try {
            // Fetch all pending and partial dues with their IDs
            $stmt = $conn->prepare("SELECT id, amount, status FROM student_dues WHERE student_id = ? AND status IN ('pending', 'partial') ORDER BY created_at ASC");
            $stmt->execute([$student_id]);
            $dues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $remaining_amount = $amount_paid;
            $total_due = array_sum(array_column($dues, 'amount'));

            if ($amount_paid > $total_due) {
                throw new Exception("Payment amount exceeds available dues.");
            }

            $partial_updated = false; // Flag to ensure only one partial update
            foreach ($dues as $due) {
                $due_id = $due['id'];
                $due_amount = $due['amount'];

                if ($remaining_amount <= 0) break;

                if ($remaining_amount >= $due_amount && !$partial_updated) {
                    $stmt = $conn->prepare("UPDATE student_dues SET status = 'paid', payment_date = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$due_id]);
                    $remaining_amount -= $due_amount;
                } elseif ($remaining_amount < $due_amount && !$partial_updated) {
                    $new_amount = $due_amount - $remaining_amount;
                    $stmt = $conn->prepare("UPDATE student_dues SET amount = ?, status = 'partial' WHERE id = ?");
                    $stmt->execute([$new_amount, $due_id]);
                    $remaining_amount = 0;
                    $partial_updated = true; // Mark partial update as done
                }
            }

            if ($remaining_amount == 0) {
                $transaction_id = 'TXN' . strtoupper(uniqid());
                $stmt = $conn->prepare("INSERT INTO offline_payments (student_id, accountant_id, amount, transaction_id, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $accountant_id, $amount_paid, $transaction_id, $notes]);

                // Recalculate and update due_payments in students table
                $stmt = $conn->prepare("SELECT SUM(amount) as total_due FROM student_dues WHERE student_id = ? AND status IN ('pending', 'partial')");
                $stmt->execute([$student_id]);
                $total_due = $stmt->fetchColumn() ?: 0;
                $stmt = $conn->prepare("UPDATE students SET due_payments = ? WHERE id = ?");
                $stmt->execute([$total_due, $student_id]);

                // Store payment context in session with accountant details
                $stmt = $conn->prepare("SELECT id, fname, lname, class, due_payments FROM students WHERE id = ?");
                $stmt->execute([$student_id]);
                $updated_student = $stmt->fetch(PDO::FETCH_ASSOC);
                $stmt = $conn->prepare("SELECT id, student_id, amount, description, created_at, status FROM student_dues WHERE student_id = ? AND status IN ('pending', 'partial') ORDER BY created_at ASC");
                $stmt->execute([$student_id]);
                $updated_dues = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $_SESSION['last_payment'] = [
                    'student_id' => $student_id,
                    'student' => $updated_student,
                    'dues' => $updated_dues,
                    'amount_paid' => $amount_paid,
                    'transaction_id' => $transaction_id,
                    'accountant_id' => $accountant_id,
                    'accountant_name' => $accountant_name,
                    'print_trigger' => true // Set trigger for receipt
                ];

                $conn->commit();
                // Use relative redirect to stay on page
                $redirect_url = './offline_payment.php?student_id=' . $student_id;
                error_log("Redirecting to: " . $redirect_url); // Log the redirect URL
                header("Location: " . $redirect_url);
                exit();
            } else {
                $conn->rollBack();
                $message = "Error: Payment processing failed. Remaining amount: $remaining_amount Taka.";
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $message = "Transaction error: " . $e->getMessage();
        } catch (Exception $e) {
            $conn->rollBack();
            $message = $e->getMessage();
        }
    } else {
        $message = "Invalid payment amount or student.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline Payments</title>
    <link rel="stylesheet" href="CSS/offline_payment.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Offline Payment System</h1>
            <form action="../logout.php" method="POST" style="display:inline;">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </header>
        <main class="main-content">
            <h2>Offline Payments</h2>
            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message ?? ''); ?></div>
            <?php endif; ?>

            <form method="POST" class="filter-form">
                <label for="filter_type">Filter By:</label>
                <select name="filter_type" id="filter_type" required>
                    <option value="id">ID</option>
                    <option value="fname">First Name</option>
                    <option value="mother_name">Mother's Name</option>
                    <option value="father_name">Father's Name</option>
                    <option value="parents_mobile">Parents' Mobile</option>
                    <option value="class">Class</option>
                </select>
                <input type="text" name="filter_value" id="filter_value" placeholder="Enter value" required>
                <button type="submit" name="filter">Filter</button>
            </form>

            <?php if ($filtered_student): ?>
                <div class="student-details">
                    <h3>Student: <?php echo htmlspecialchars($filtered_student['fname'] . ' ' . ($filtered_student['lname'] ?? '')); ?> (ID: <?php echo $filtered_student['id']; ?>)</h3>
                    <p>Class: <?php echo htmlspecialchars($filtered_student['class'] ?? ''); ?></p>
                    <p>Total Due: <?php echo number_format($filtered_student['due_payments'] ?? 0, 2); ?> Taka</p>
                </div>

                <?php if ($student_dues): ?>
                    <h4>Pending Dues</h4>
                    <div class="dues-table-container">
                        <table class="dues-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Amount</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($student_dues as $due): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($due['id'] ?? 'N/A'); ?></td>
                                        <td><?php echo number_format($due['amount'] ?? 0, 2); ?> Taka</td>
                                        <td><?php echo htmlspecialchars($due['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($due['created_at'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($due['status'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No pending or partial dues found.</p>
                <?php endif; ?>

                <form method="POST" class="payment-form">
                    <input type="hidden" name="student_id" value="<?php echo $filtered_student['id']; ?>">
                    <div class="form-group">
                        <label for="amount_paid">Pay amount:</label>
                        <input type="number" name="amount_paid" id="amount_paid" step="10" min="0" required
                        placeholder="TAKA">
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes:</label>
                        <input type="text" name="notes" id="notes" placeholder="Add comments (optional)">
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="pay">Pay</button>
                        <?php
                        error_log("Checking receipt button: " . (isset($_SESSION['last_payment']) ? 'Session exists' : 'No session') . ", student_id match: " . ($_SESSION['last_payment']['student_id'] ?? 'N/A') . " == " . ($filtered_student['id'] ?? 'N/A') . ", print_trigger: " . (isset($_SESSION['last_payment']['print_trigger']) ? 'true' : 'false'));
                        if (isset($_SESSION['last_payment']) && $_SESSION['last_payment']['student_id'] == $filtered_student['id'] && isset($_SESSION['last_payment']['print_trigger'])) {
                        ?>
                            <button type="button" onclick="printReceipt()">Print Receipt</button>
                        <?php } ?>
                    </div>
                </form>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function printReceipt() {
            console.log("printReceipt called");
            const studentName = "<?php echo addslashes(htmlspecialchars($filtered_student['fname'] . ' ' . ($filtered_student['lname'] ?? ''))); ?>";
            const studentId = "<?php echo $filtered_student['id']; ?>";
            const amountPaid = "<?php echo number_format($_SESSION['last_payment']['amount_paid'] ?? 0, 2); ?>";
            const transactionId = "<?php echo $_SESSION['last_payment']['transaction_id'] ?? ''; ?>";
            const accountantId = "<?php echo $_SESSION['last_payment']['accountant_id'] ?? ''; ?>";
            const accountantName = "<?php echo addslashes(htmlspecialchars($_SESSION['last_payment']['accountant_name'] ?? '')); ?>";
            console.log("Receipt data: ", { studentName, studentId, amountPaid, transactionId, accountantId, accountantName });

            const printContent = `
                <html>
                <body>
                    <h2>Payment Receipt</h2>
                    <p>Student: ${studentName} (ID: ${studentId})</p>
                    <p>Amount Paid: ${amountPaid} Taka</p>
                    <p>Transaction ID: ${transactionId}</p>
                    <p>Date: ${new Date().toLocaleString()}</p>
                    <p>Accountant: ${accountantName} (ID: ${accountantId})</p>
                </body>
                </html>
            `;
            const win = window.open('', '_blank', 'width=300,height=400');
            if (win) {
                console.log("Print window opened");
                win.document.write(printContent);
                win.document.close();
                win.focus(); // Ensure the window is in focus
                setTimeout(() => {
                    console.log("Attempting to print");
                    win.print();
                    console.log("Print command sent");
                    // Clear session data after printing
                    <?php unset($_SESSION['last_payment']); ?>
                    console.log("Session cleared");
                }, 500); // Delay to ensure content loads
            } else {
                console.log("Failed to open print window - check popup blocker");
                alert("Please allow popups to print the receipt.");
            }
        }
    </script>
</body>
</html>
<?php $conn = null; ?>