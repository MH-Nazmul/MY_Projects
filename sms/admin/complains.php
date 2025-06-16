<?php
// complaints.php

// Start the session
session_start();
include '../db_connect.php';

// Check if the user is already logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: ../logout.php");
    exit();
}

// Fetch school name and tagline from settings
try {
    $stmt = $conn->prepare("SELECT school_name, tag_line FROM settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $school_name = $settings['school_name'] ?? 'School Name';
    $tag_line = $settings['tag_line'] ?? 'Tagline';
} catch (PDOException $e) {
    $school_name = 'School Name';
    $tag_line = 'Tagline';
}

// Fetch all complaints from the database, ordered by created_at
try {
    $stmt = $conn->prepare("SELECT id, name, email, message, created_at FROM complains ORDER BY created_at DESC");
    $stmt->execute();
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Failed to fetch complaints: " . $e->getMessage();
}

// Check for success or error messages in the query string
$success_message = isset($_GET['success']) ? $_GET['success'] : '';
$error_message = isset($_GET['error']) ? $_GET['error'] : (isset($error_message) ? $error_message : '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Complaints - School Management System</title>

    <style>
        /* Title Section */
        .title-section {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #2c3e50;
            color: white;
            border-radius: 8px;
        }
        .title-section h1 {
            margin: 0;
            font-size: 24px;
        }
        .title-section p {
            margin: 5px 0 0;
            font-size: 16px;
            color: #ddd;
        }

        /* Complaints List Container */
        .complaints-list {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
            overflow: hidden;
        }

        /* Complaint Row */
        .complaint-row {
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .complaint-row:hover {
            background: #f5f5f5;
        }
        .complaint-row:last-child {
            border-bottom: none;
        }

        /* Complaint Preview */
        .complaint-preview {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .sender-info {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .sender-name {
            font-weight: bold;
            color: #333;
            margin-right: 10px;
            font-size: 15px;
        }
        .sender-email {
            color: #666;
            font-size: 14px;
            font-style: italic;
        }
        .message-snippet {
            color: #666;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 300px;
        }

        /* Timestamp */
        .timestamp {
            font-size: 13px;
            color: #999;
            font-style: italic;
            margin-right: 20px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .action-buttons button {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s ease;
        }
        .action-buttons .acknowledge-btn {
            background: #28a745;
            color: white;
        }
        .action-buttons .acknowledge-btn:hover {
            background: #218838;
        }
        .action-buttons .checked-btn {
            background: #007bff;
            color: white;
        }
        .action-buttons .checked-btn:hover {
            background: #0056b3;
        }

        /* Full Message (Hidden by Default) */
        .full-message {
            display: none;
            padding: 15px 20px;
            background: #f9f9f9;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
            animation: slideDown 0.3s ease;
        }
        .full-message.show {
            display: block;
        }
        .full-message-text {
            font-size: 15px;
            color: #333;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        /* Error Message */
        .error-message {
            text-align: center;
            color: #dc3545;
            font-size: 16px;
            padding: 20px;
            background: #f8d7da;
            border-radius: 5px;
            margin: 10px;
        }

        /* Empty State */
        .empty-message {
            text-align: center;
            color: #666;
            font-size: 16px;
            padding: 30px;
            background: #f1f1f1;
            border-radius: 5px;
            margin: 10px;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: #fff;
            font-size: 16px;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }
        .toast.success {
            background-color: #28a745;
        }
        .toast.error {
            background-color: #dc3545;
        }
        .toast.show {
            opacity: 1;
        }

        /* Animation for Expanding Message */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .complaint-row {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px 15px;
            }
            .message-snippet {
                max-width: 100%;
            }
            .timestamp {
                margin-top: 5px;
                margin-right: 0;
                align-self: flex-end;
            }
            .action-buttons {
                margin-top: 10px;
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="container">
       <?php include 'admin_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Title Section -->
            <div class="title-section">
                <h1><?php echo htmlspecialchars($school_name); ?></h1>
                <p><?php echo htmlspecialchars($tag_line); ?></p>
            </div>

            <!-- Complaints List -->
            <div class="complaints-list">
                <?php if (isset($error_message) && !empty($error_message)) { ?>
                    <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                <?php } elseif (empty($complaints)) { ?>
                    <p class="empty-message">No complaints have been submitted yet.</p>
                <?php } else { ?>
                    <?php foreach ($complaints as $complaint) { ?>
                        <div class="complaint-row" onclick="toggleMessage(this)">
                            <div class="complaint-preview">
                                <div class="sender-info">
                                    <span class="sender-name"><?php echo htmlspecialchars($complaint['name']); ?></span>
                                    <span class="sender-email">(<?php echo htmlspecialchars($complaint['email']); ?>)</span>
                                </div>
                                <div class="message-snippet">
                                    <?php echo htmlspecialchars(substr($complaint['message'], 0, 50)) . (strlen($complaint['message']) > 50 ? '...' : ''); ?>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center;">
                                <div class="timestamp">
                                    <?php echo htmlspecialchars($complaint['created_at']); ?>
                                </div>
                                <div class="action-buttons">
                                    <form method="POST" action="delete_complain.php" onsubmit="return confirmDelete('Acknowledge')">
                                        <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                        <button type="submit" class="acknowledge-btn">Acknowledge</button>
                                    </form>
                                    <form method="POST" action="delete_complain.php" onsubmit="return confirmDelete('Checked')">
                                        <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                        <button type="submit" class="checked-btn">Checked</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="full-message">
                            <p class="full-message-text"><?php echo htmlspecialchars($complaint['message']); ?></p>
                            <div class="timestamp"><?php echo htmlspecialchars($complaint['created_at']); ?></div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </div>

    <?php include '../footer.php'; ?>

    <!-- Toast Notification Container -->
    <?php if ($success_message) { ?>
        <div id="toast" class="toast success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php } elseif ($error_message) { ?>
        <div id="toast" class="toast error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php } ?>

    <!-- JavaScript for Toggling Full Message and Handling Deletion -->
    <script>
        // Toggle full message visibility
        function toggleMessage(row) {
            // Prevent toggling if the click target is a button
            if (event.target.tagName !== 'BUTTON') {
                const fullMessage = row.nextElementSibling;
                fullMessage.classList.toggle('show');
            }
        }

        // Confirm deletion
        function confirmDelete(action) {
            return confirm(`Are you sure you want to mark this complaint as "${action}"? This will remove it from the list.`);
        }

        // Show toast notification
        function showToast() {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
                // Remove the query parameter from the URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }
        window.onload = showToast;
    </script>
</body>
</html>