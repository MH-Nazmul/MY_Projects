<?php
// Start the session
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Include the database connection
include '../db_connect.php';

// Verify that $conn is defined
if (!isset($conn) || !($conn instanceof PDO)) {
    die("Error: Database connection failed. Check db_connect.php.");
}

try {
    // Fetch all teachers to display in a table
    $stmt = $conn->prepare("SELECT id, fname, lname, email FROM teachers");
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Redirect with an error message if the query fails (e.g., table doesn't exist)
    header("Location: /admin_dashboard.php?error=Failed+to+fetch+teachers:+" . urlencode($e->getMessage()));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Management</title>
    <link rel="stylesheet" href="../CSS/admin.css">
    <link rel='stylesheet' href="manage_teacher.css">
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2>Admin Dashboard</h2>
            <ul>
                <li><a href="admit_teacher.php">Admit Teacher</a></li>
                <li><a href="admit_student.php">Admit Student</a></li>
                <li><a href="manage_student.php">Manage Students</a></li>
                <li><a href="view_dues.php">View Dues & Info</a></li>
                <li><a href="teacher_management.php">Manage Teachers</a></li>
                <li><a href="settings.php">School Settings</a></li>
                <li><a href="complaints.php">View Complaints</a></li>
                <li><a href="schedule.php">Class Schedule</a></li>
                <li><a href="/logout.php">Logout</a></li>
            </ul>
        </div>
        <div class="main-content">
            <h1>Teacher Management</h1>
            <p>View and manage teachers below.</p>

            <!-- Display Teachers in a Table -->
            <?php if ($teachers) { ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($teacher['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['fname'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['lname'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <!-- Edit Link -->
                                    <a href="/admin_dashboard/edit_teacher.php?id=<?php echo $teacher['id']; ?>">Edit</a>
                                    <!-- Delete Form -->
                                    <form action="/delete_teacher.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo $teacher['id']; ?>">
                                        <button type="submit" onclick="return confirm('Are you sure you want to delete this teacher?');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <p>Zero teachers in list.</p>
            <?php } ?>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <?php if (isset($_GET['success'])) { ?>
        <div id="toast" class="toast success"><?php echo htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } elseif (isset($_GET['error'])) { ?>
        <div id="toast" class="toast error"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>

    <!-- JavaScript for Toast Notification -->
    <script>
        // Function to show the toast notification
        function showToast() {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.classList.add('show');
                // Hide the toast after 3 seconds
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
                // Remove the query parameter from the URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }

        // Show the toast when the page loads
        window.onload = showToast;
    </script>
</body>
</html>