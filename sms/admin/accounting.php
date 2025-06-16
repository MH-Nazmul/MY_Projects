<?php
// admin/accounting.php

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../logout.php");
    exit();
}

// Include database connection
include '../db_connect.php';

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get the admin ID
$admin_id = $_SESSION['user_id'];

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch classes
$stmt = $conn->prepare("SELECT id, class_name FROM classes ORDER BY class_name");
if (!$stmt->execute()) {
    die("Query failed: " . $stmt->errorInfo()[2]);
}
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch fee types
$stmt = $conn->prepare("SELECT id, name, frequency, default_amount FROM fee_types");
if (!$stmt->execute()) {
    die("Query failed: " . $stmt->errorInfo()[2]);
}
$fee_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch monthly fees (using class_name)
$stmt = $conn->prepare("SELECT class_name, monthly_fee FROM monthly_fees");
if (!$stmt->execute()) {
    die("Query failed: " . $stmt->errorInfo()[2]);
}
$monthly_fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
$monthly_fees_map = array_column($monthly_fees, 'monthly_fee', 'class_name');

// Fetch students
$stmt = $conn->prepare("SELECT id, CONCAT(fname, ' ', lname) AS name, class FROM students ORDER BY fname");
if (!$stmt->execute()) {
    die("Query failed: " . $stmt->errorInfo()[2]);
}
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch salary scales
$stmt = $conn->prepare("SELECT id, scale_name, base_amount FROM salary_scales");
if (!$stmt->execute()) {
    die("Query failed: " . $stmt->errorInfo()[2]);
}
$salary_scales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch staff
$stmt = $conn->prepare("SELECT id, name, role, scale_id FROM staff WHERE role IN ('teacher', 'support') ORDER BY name");
if (!$stmt->execute()) {
    die("Query failed: " . $stmt->errorInfo()[2]);
}
$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch expenses
$stmt = $conn->prepare("SELECT e.id, ec.name AS category, e.amount, e.description, e.date, e.status, s.name AS submitted_by FROM expenses e JOIN expense_categories ec ON e.category_id = ec.id JOIN staff s ON e.submitted_by = s.id");
if (!$stmt->execute()) {
    die("Query failed: " . $stmt->errorInfo()[2]);
}
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch dashboard metrics
$stmt = $conn->prepare("SELECT SUM(amount) AS total_revenue FROM transactions WHERE transaction_type = 'payment' AND YEAR(transaction_date) = YEAR(CURDATE())");
if (!$stmt->execute()) {
    die("Query failed: " . $stmt->errorInfo()[2]);
}
$total_revenue = $stmt->fetchColumn() ?: 0;

$stmt = $conn->prepare("SELECT SUM(amount) AS total_dues FROM student_dues WHERE status = 'pending'");
if (!$stmt->execute()) {
    die("Query failed: " . $stmt->errorInfo()[2]);
}
$total_dues = $stmt->fetchColumn() ?: 0;

$stmt = $conn->prepare("SELECT SUM(amount) AS total_expenses FROM expenses WHERE status = 'approved' AND YEAR(date) = YEAR(CURDATE())");
if (!$stmt->execute()) {
    die("Query failed: " . $stmt->errorInfo()[2]);
}
$total_expenses = $stmt->fetchColumn() ?: 0;

// Handle POST requests
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_fee') {
        $class_id = $_POST['class_id'] ?? '';
        $fee_type_id = $_POST['fee_type_id'] ?? '';
        $new_amount = $_POST['new_amount'] ?? '';
        $next_month = date('Y-m-01', strtotime('+1 month'));

        // Map class_id to class_name
        $class = array_filter($classes, fn($c) => $c['id'] == $class_id);
        $class_name = $class ? reset($class)['class_name'] : '';

        if (!empty($class_id) && !empty($fee_type_id) && is_numeric($new_amount) && $new_amount >= 0 && $class_name) {
            try {
                $conn->beginTransaction();

                $stmt = $conn->prepare("SELECT default_amount FROM fee_types WHERE id = ?");
                $stmt->bindParam(1, $fee_type_id, PDO::PARAM_INT);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }
                $current_amount = $stmt->fetchColumn();

                $stmt = $conn->prepare("INSERT INTO fee_changes (class_id, fee_type_id, old_amount, new_amount, effective_date, admin_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bindParam(1, $class_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $fee_type_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $current_amount, PDO::PARAM_STR);
                $stmt->bindParam(4, $new_amount, PDO::PARAM_STR);
                $stmt->bindParam(5, $next_month, PDO::PARAM_STR);
                $stmt->bindParam(6, $admin_id, PDO::PARAM_INT);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }

                $stmt = $conn->prepare("UPDATE fee_types SET default_amount = ? WHERE id = ?");
                $stmt->bindParam(1, $new_amount, PDO::PARAM_STR);
                $stmt->bindParam(2, $fee_type_id, PDO::PARAM_INT);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }

                if ($fee_type_id == 1) { // Assume fee_type_id 1 is Monthly Tuition
                    $stmt = $conn->prepare("INSERT INTO monthly_fees (class_name, monthly_fee) VALUES (?, ?) ON DUPLICATE KEY UPDATE monthly_fee = ?");
                    $stmt->bindParam(1, $class_name, PDO::PARAM_STR);
                    $stmt->bindParam(2, $new_amount, PDO::PARAM_STR);
                    $stmt->bindParam(3, $new_amount, PDO::PARAM_STR);
                    if (!$stmt->execute()) {
                        throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                    }
                }

                $stmt = $conn->prepare("SELECT id, class FROM students WHERE class = ?");
                $stmt->bindParam(1, $class_name, PDO::PARAM_STR);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }
                $student_ids = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($student_ids as $student) {
                    $stmt = $conn->prepare("INSERT INTO fees (student_id, class_id, fee_type_id, amount, status, due_date, month) VALUES (?, ?, ?, ?, 'pending', ?, ?)");
                    $stmt->bindParam(1, $student['id'], PDO::PARAM_INT);
                    $stmt->bindParam(2, $class_id, PDO::PARAM_INT);
                    $stmt->bindParam(3, $fee_type_id, PDO::PARAM_INT);
                    $stmt->bindParam(4, $new_amount, PDO::PARAM_STR);
                    $stmt->bindParam(5, $next_month, PDO::PARAM_STR);
                    $stmt->bindParam(6, date('Y-m', strtotime('+1 month')), PDO::PARAM_STR);
                    if (!$stmt->execute()) {
                        throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                    }

                    $stmt = $conn->prepare("INSERT INTO student_dues (student_id, amount, description, status) VALUES (?, ?, ?, 'pending')");
                    $stmt->bindParam(1, $student['id'], PDO::PARAM_INT);
                    $stmt->bindParam(2, $new_amount, PDO::PARAM_STR);
                    $fee_type_name = $fee_types[array_search($fee_type_id, array_column($fee_types, 'id'))]['name'];
                    $stmt->bindParam(3, $fee_type_name, PDO::PARAM_STR);
                    if (!$stmt->execute()) {
                        throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                    }
                }

                $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, details) VALUES (?, 'fee_updated', ?)");
                $stmt->bindParam(1, $admin_id, PDO::PARAM_INT);
                $details = "Updated fee for class_id $class_id ($class_name), fee_type_id $fee_type_id to $new_amount";
                $stmt->bindParam(2, $details, PDO::PARAM_STR);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }

                $conn->commit();
                $message = "Fee updated successfully, effective from next month!";
            } catch (Exception $e) {
                $conn->rollBack();
                $message = "Error: " . $e->getMessage();
            }
        } else {
            $message = "Invalid fee update request.";
        }
    } elseif ($action === 'add_extra_fee') {
        $class_id = $_POST['class_id'] ?? '';
        $student_ids = $_POST['student_ids'] ?? [];
        $description = trim($_POST['description'] ?? '');
        $amount = $_POST['amount'] ?? '';
        $frequency = $_POST['frequency'] ?? 'one-time';
        $due_date = date('Y-m-d', strtotime('+1 month'));

        // Map class_id to class_name
        $class = array_filter($classes, fn($c) => $c['id'] == $class_id);
        $class_name = $class ? reset($class)['class_name'] : '';

        if (!empty($class_id) && !empty($student_ids) && !empty($description) && is_numeric($amount) && $amount >= 0 && $class_name) {
            try {
                $conn->beginTransaction();

                $stmt = $conn->prepare("INSERT INTO fee_types (name, frequency, default_amount) VALUES (?, ?, ?)");
                $stmt->bindParam(1, $description, PDO::PARAM_STR);
                $stmt->bindParam(2, $frequency, PDO::PARAM_STR);
                $stmt->bindParam(3, $amount, PDO::PARAM_STR);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }
                $fee_type_id = $conn->lastInsertId();

                foreach ($student_ids as $student_id) {
                    $stmt = $conn->prepare("INSERT INTO fees (student_id, class_id, fee_type_id, amount, status, due_date) VALUES (?, ?, ?, ?, 'pending', ?)");
                    $stmt->bindParam(1, $student_id, PDO::PARAM_INT);
                    $stmt->bindParam(2, $class_id, PDO::PARAM_INT);
                    $stmt->bindParam(3, $fee_type_id, PDO::PARAM_INT);
                    $stmt->bindParam(4, $amount, PDO::PARAM_STR);
                    $stmt->bindParam(5, $due_date, PDO::PARAM_STR);
                    if (!$stmt->execute()) {
                        throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                    }

                    $stmt = $conn->prepare("INSERT INTO student_dues (student_id, amount, description, status) VALUES (?, ?, ?, 'pending')");
                    $stmt->bindParam(1, $student_id, PDO::PARAM_INT);
                    $stmt->bindParam(2, $amount, PDO::PARAM_STR);
                    $stmt->bindParam(3, $description, PDO::PARAM_STR);
                    if (!$stmt->execute()) {
                        throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                    }
                }

                $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, details) VALUES (?, 'add_extra_fee', ?)");
                $stmt->bindParam(1, $admin_id, PDO::PARAM_INT);
                $details = "Added extra fee '$description' for class_id $class_id ($class_name)";
                $stmt->bindParam(2, $details, PDO::PARAM_STR);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }

                $conn->commit();
                $message = "Extra fee added successfully!";
            } catch (Exception $e) {
                $conn->rollBack();
                $message = "Error: " . $e->getMessage();
            }
        } else {
            $message = "Invalid extra fee request.";
        }
    } elseif ($action === 'update_scale') {
        $scale_id = $_POST['scale_id'] ?? '';
        $base_amount = $_POST['base_amount'] ?? '';

        if (!empty($scale_id) && is_numeric($base_amount) && $base_amount >= 0) {
            try {
                $conn->beginTransaction();

                $stmt = $conn->prepare("UPDATE salary_scales SET base_amount = ? WHERE id = ?");
                $stmt->bindParam(1, $base_amount, PDO::PARAM_STR);
                $stmt->bindParam(2, $scale_id, PDO::PARAM_INT);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }

                $stmt = $conn->prepare("UPDATE salaries SET base_amount = ?, total_amount = ? + bonuses - deductions WHERE scale_id = ? AND status = 'unpaid' AND month = ?");
                $stmt->bindParam(1, $base_amount, PDO::PARAM_STR);
                $stmt->bindParam(2, $base_amount, PDO::PARAM_STR);
                $stmt->bindParam(3, $scale_id, PDO::PARAM_INT);
                $stmt->bindParam(4, date('Y-m'), PDO::PARAM_STR);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }

                $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, details) VALUES (?, 'scale_updated', ?)");
                $stmt->bindParam(1, $admin_id, PDO::PARAM_INT);
                $details = "Updated salary scale $scale_id to $base_amount";
                $stmt->bindParam(2, $details, PDO::PARAM_STR);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }

                $conn->commit();
                $message = "Salary scale updated successfully!";
            } catch (Exception $e) {
                $conn->rollBack();
                $message = "Error: " . $e->getMessage();
            }
        } else {
            $message = "Invalid scale update request.";
        }
    } elseif ($action === 'assign_scale') {
        $staff_id = $_POST['staff_id'] ?? '';
        $scale_id = $_POST['scale_id'] ?? '';

        if (!empty($staff_id) && !empty($scale_id)) {
            try {
                $conn->beginTransaction();

                $stmt = $conn->prepare("SELECT base_amount FROM salary_scales WHERE id = ?");
                $stmt->bindParam(1, $scale_id, PDO::PARAM_INT);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }
                $base_amount = $stmt->fetchColumn();

                $stmt = $conn->prepare("UPDATE staff SET scale_id = ? WHERE id = ?");
                $stmt->bindParam(1, $scale_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $staff_id, PDO::PARAM_INT);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }

                $stmt = $conn->prepare("INSERT INTO salaries (staff_id, scale_id, base_amount, total_amount, month, status) VALUES (?, ?, ?, ?, ?, 'unpaid') ON DUPLICATE KEY UPDATE scale_id = ?, base_amount = ?, total_amount = ?");
                $stmt->bindParam(1, $staff_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $scale_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $base_amount, PDO::PARAM_STR);
                $stmt->bindParam(4, $base_amount, PDO::PARAM_STR);
                $stmt->bindParam(5, date('Y-m'), PDO::PARAM_STR);
                $stmt->bindParam(6, $scale_id, PDO::PARAM_INT);
                $stmt->bindParam(7, $base_amount, PDO::PARAM_STR);
                $stmt->bindParam(8, $base_amount, PDO::PARAM_STR);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }

                $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, details) VALUES (?, 'scale_assigned', ?)");
                $stmt->bindParam(1, $admin_id, PDO::PARAM_INT);
                $details = "Assigned scale $scale_id to staff $staff_id";
                $stmt->bindParam(2, $details, PDO::PARAM_STR);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }

                $conn->commit();
                $message = "Scale assigned successfully!";
            } catch (Exception $e) {
                $conn->rollBack();
                $message = "Error: " . $e->getMessage();
            }
        } else {
            $message = "Invalid scale assignment request.";
        }
    } elseif ($action === 'approve_expense') {
        $expense_id = $_POST['expense_id'] ?? '';
        $status = $_POST['status'] ?? '';

        if (!empty($expense_id) && in_array($status, ['approved', 'rejected'])) {
            try {
                $conn->beginTransaction();

                $stmt = $conn->prepare("UPDATE expenses SET status = ?, approved_by = ? WHERE id = ?");
                $stmt->bindParam(1, $status, PDO::PARAM_STR);
                $stmt->bindParam(2, $admin_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $expense_id, PDO::PARAM_INT);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }

                $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, details) VALUES (?, 'expense_updated', ?)");
                $stmt->bindParam(1, $admin_id, PDO::PARAM_INT);
                $details = "Set expense $expense_id to $status";
                $stmt->bindParam(2, $details, PDO::PARAM_STR);
                if (!$stmt->execute()) {
                    throw new Exception("Query failed: " . $stmt->errorInfo()[2]);
                }

                $conn->commit();
                $message = "Expense $status successfully!";
            } catch (Exception $e) {
                $conn->rollBack();
                $message = "Error: " . $e->getMessage();
            }
        } else {
            $message = "Invalid expense action.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Management</title>
    <link rel="stylesheet" href="CSS/accounting.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <?php include 'admin_sidebar.php'; ?>

        <main class="main-content">
            <div class="tabs">
                <button class="tab-btn" data-tab="dashboard">Dashboard</button>
                <button class="tab-btn" data-tab="fees">Fees</button>
                <button class="tab-btn" data-tab="salaries">Salaries</button>
                <button class="tab-btn" data-tab="expenses">Expenses</button>
                <button class="tab-btn" data-tab="reports">Reports</button>
                <button class="tab-btn" data-tab="audit">Audit Trail</button>
            </div>

            <?php if ($message): ?>
                <div class="message">
                    <?php echo htmlspecialchars($message ?? ''); ?>
                </div>
            <?php endif; ?>

            <section id="dashboard" class="tab-content">
                <h2>Financial Overview</h2>
                <div class="metrics">
                    <div class="metric">
                        <h3>Total Revenue</h3>
                        <p>$<?php echo number_format($total_revenue, 2); ?></p>
                    </div>
                    <div class="metric">
                        <h3>Pending Dues</h3>
                        <p>$<?php echo number_format($total_dues, 2); ?></p>
                    </div>
                    <div class="metric">
                        <h3>Total Expenses</h3>
                        <p>$<?php echo number_format($total_expenses, 2); ?></p>
                    </div>
                </div>
            </section>

            <section id="fees" class="tab-content">
                <h2>Fee Management</h2>
                <div class="class-nav">
                    <?php foreach ($classes as $class): ?>
                        <button class="nav-link" data-class="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($classes as $class): ?>
                    <div class="class-details" id="class-<?php echo $class['id']; ?>">
                        <?php foreach ($fee_types as $type): ?>
                            <div class="fee-card">
                                <h3><?php echo htmlspecialchars($type['name']); ?> (<?php echo $type['frequency']; ?>)</h3>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="update_fee">
                                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                    <input type="hidden" name="fee_type_id" value="<?php echo $type['id']; ?>">
                                    <label>Current Amount: $<?php echo htmlspecialchars($type['default_amount']); ?></label>
                                    <input type="number" name="new_amount" step="0.01" required min="0" value="<?php echo htmlspecialchars($type['default_amount']); ?>">
                                    <button type="submit">Update</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <h3>Add Extra Fee</h3>
                <form method="POST" class="extra-fee-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="add_extra_fee">
                    <label>Class:</label>
                    <select name="class_id" id="class_select" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Students:</label>
                    <div id="student_checkboxes" class="student-list"></div>
                    <label>Description:</label>
                    <input type="text" name="description" required>
                    <label>Amount:</label>
                    <input type="number" name="amount" step="0.01" required min="0">
                    <label>Frequency:</label>
                    <select name="frequency">
                        <option value="one-time">One-Time</option>
                        <option value="monthly">Monthly</option>
                    </select>
                    <button type="submit">Add Fee</button>
                </form>
                <h3>Student Dues</h3>
                <table>
                    <tr>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                    <?php
                    $stmt = $conn->prepare("SELECT sd.id, s.fname, s.lname, s.class, sd.amount, sd.description, sd.status FROM student_dues sd JOIN students s ON sd.student_id = s.id order by student_id");
                    if (!$stmt->execute()) {
                        die("Query failed: " . $stmt->errorInfo()[2]);
                    }
                    $dues = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($dues as $due): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($due['fname'] . ' ' . $due['lname']); ?></td>
                            <td><?php echo htmlspecialchars($due['class']); ?></td>
                            <td><?php echo htmlspecialchars($due['description']); ?></td>
                            <td>$<?php echo number_format($due['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($due['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </section>

            <section id="salaries" class="tab-content">
                <h2>Salary Management</h2>
                <h3>Salary Scales</h3>
                <table>
                    <tr>
                        <th>Scale Name</th>
                        <th>Base Amount</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($salary_scales as $scale): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($scale['scale_name']); ?></td>
                            <td>$<?php echo number_format($scale['base_amount'], 2); ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="update_scale">
                                    <input type="hidden" name="scale_id" value="<?php echo $scale['id']; ?>">
                                    <input type="number" name="base_amount" step="0.01" value="<?php echo $scale['base_amount']; ?>" required min="0">
                                    <button type="submit">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <h3>Staff Salaries</h3>
                <table>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Scale</th>
                        <th>Base Salary</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($staff as $member): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($member['name']); ?></td>
                            <td><?php echo htmlspecialchars($member['role']); ?></td>
                            <td>
                                <?php
                                $scale = array_filter($salary_scales, fn($s) => $s['id'] == $member['scale_id']);
                                echo htmlspecialchars($scale ? reset($scale)['scale_name'] : 'None');
                                ?>
                            </td>
                            <td>
                                <?php
                                echo htmlspecialchars($scale ? '$' . number_format(reset($scale)['base_amount'], 2) : 'N/A');
                                ?>
                            </td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="assign_scale">
                                    <input type="hidden" name="staff_id" value="<?php echo $member['id']; ?>">
                                    <select name="scale_id">
                                        <option value="">Select Scale</option>
                                        <?php foreach ($salary_scales as $scale): ?>
                                            <option value="<?php echo $scale['id']; ?>" <?php echo $scale['id'] == $member['scale_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($scale['scale_name'] . ' ($' . number_format($scale['base_amount'], 2) . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit">Assign</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </section>

            <section id="expenses" class="tab-content">
                <h2>Expenses Management</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="add_expense">
                    <label>Category:</label>
                    <select name="category_id" required>
                        <?php
                        $stmt = $conn->prepare("SELECT id, name FROM expense_categories");
                        if (!$stmt->execute()) {
                            die("Query failed: " . $stmt->errorInfo()[2]);
                        }
                        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Amount:</label>
                    <input type="number" name="amount" step="0.01" required min="0">
                    <label>Description:</label>
                    <textarea name="description" required></textarea>
                    <label>Date:</label>
                    <input type="date" name="date" required>
                    <button type="submit">Add Expense</button>
                </form>
                <table>
                    <tr>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Submitted By</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($expense['category']); ?></td>
                            <td>$<?php echo number_format($expense['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($expense['description']); ?></td>
                            <td><?php echo htmlspecialchars($expense['date']); ?></td>
                            <td><?php echo htmlspecialchars($expense['status']); ?></td>
                            <td><?php echo htmlspecialchars($expense['submitted_by']); ?></td>
                            <td>
                                <?php if ($expense['status'] == 'pending'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="approve_expense">
                                        <input type="hidden" name="expense_id" value="<?php echo $expense['id']; ?>">
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit">Approve</button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="approve_expense">
                                        <input type="hidden" name="expense_id" value="<?php echo $expense['id']; ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit">Reject</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </section>

            <section id="reports" class="tab-content">
                <h2>Reports</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="generate_report">
                    <label>Report Type:</label>
                    <select name="report_type">
                        <option value="income">Income Statement</option>
                        <option value="expenses">Expense Report</option>
                        <option value="dues">Pending Dues</option>
                    </select>
                    <label>Start Date:</label>
                    <input type="date" name="start_date">
                    <label>End Date:</label>
                    <input type="date" name="end_date">
                    <button type="submit">Generate</button>
                </form>
            </section>

            <section id="audit" class="tab-content">
                <h2>Audit Trail</h2>
                <table>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Timestamp</th>
                    </tr>
                    <?php
                    $stmt = $conn->prepare("SELECT a.id, s.name, a.action, a.details, a.timestamp FROM audit_log a JOIN staff s ON a.user_id = s.id ORDER BY a.timestamp DESC LIMIT 50");
                    if (!$stmt->execute()) {
                        die("Query failed: " . $stmt->errorInfo()[2]);
                    }
                    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['name']); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                            <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </section>
        </main>
    </div>

    <script>
        // Pass PHP variables to JavaScript
        const classes = <?php echo json_encode($classes); ?>;
        const students = <?php echo json_encode($students); ?>;

        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
                btn.classList.add('active');
                document.getElementById(btn.getAttribute('data-tab')).style.display = 'block';
            });
        });

        // Class navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                document.querySelectorAll('.class-details').forEach(d => d.style.display = 'none');
                document.getElementById('class-' + link.getAttribute('data-class')).style.display = 'block';
            });
        });

        // Student selection
        const classSelect = document.getElementById('class_select');
        const studentCheckboxes = document.getElementById('student_checkboxes');
        classSelect.addEventListener('change', (e) => {
            studentCheckboxes.innerHTML = '';
            if (e.target.value) {
                const className = classes.find(c => c.id == e.target.value).class_name;
                const studentsInClass = students.filter(s => s.class === className);
                const container = document.createElement('div');
                container.className = 'scrollable-students';
                studentsInClass.forEach(student => {
                    const div = document.createElement('div');
                    div.innerHTML = `<input type="checkbox" name="student_ids[]" value="${student.id}"> ${student.name}`;
                    container.appendChild(div);
                });
                const allCheckbox = document.createElement('div');
                allCheckbox.innerHTML = `<input type="checkbox" id="all-checkbox" onclick="toggleAll()"> Select All`;
                container.prepend(allCheckbox);
                studentCheckboxes.appendChild(container);
            }
        });

        function toggleAll() {
            const checkboxes = document.querySelectorAll('#student_checkboxes .scrollable-students input[type="checkbox"]');
            const allCheckbox = document.getElementById('all-checkbox');
            checkboxes.forEach(cb => cb.checked = allCheckbox.checked);
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>