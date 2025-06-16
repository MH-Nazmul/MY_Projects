<?php
// teacher_management.php

// Start the session
session_start();
include '../db_connect.php';

// Check if the user is already logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: ../logout.php");
    exit();
}

// Generate a CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch school name, tagline, and address from settings
try {
    $stmt = $conn->prepare("SELECT school_name, tag_line, address FROM settings LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $school_name = $settings['school_name'] ?? 'School Name';
    $tag_line = $settings['tag_line'] ?? 'Tagline';
    $school_address = $settings['address'] ?? 'Your School Address';
} catch (PDOException $e) {
    $school_name = 'School Name';
    $tag_line = 'Tagline';
    $school_address = 'Your School Address';
}

// Initialize variables for messages
$success_message = '';
$error_message = '';

// Check for messages in session (for PRG pattern)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle form submissions (Add Teacher, Assign Subjects, Edit Teacher, Delete Teacher)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_teacher'])) {
        // Handle Add Teacher form submission
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error_message'] = "Invalid CSRF token.";
            header("Location: teacher_management.php");
            exit();
        } else {
            $fname = trim($_POST['fname']);
            $lname = trim($_POST['lname']);
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $mobile = trim($_POST['mobile']);
            $photo = null;

            // Handle photo upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $photo = file_get_contents($_FILES['photo']['tmp_name']);
            }

            // Validate inputs
            if (empty($fname) || empty($lname) || empty($username) || empty($password) || empty($confirm_password) || empty($mobile)) {
                $_SESSION['error_message'] = "All fields are required.";
                header("Location: teacher_management.php");
                exit();
            } elseif ($password !== $confirm_password) {
                $_SESSION['error_message'] = "Passwords do not match.";
                header("Location: teacher_management.php");
                exit();
            } elseif (!preg_match('/^[0-9]{11}$/', $mobile)) {
                $_SESSION['error_message'] = "Mobile number must be an 11-digit phone number.";
                header("Location: teacher_management.php");
                exit();
            } else {
                try {
                    // Check if the username is already in use
                    $stmt = $conn->prepare("SELECT id FROM teachers WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetch()) {
                        $_SESSION['error_message'] = "Username is already in use.";
                        header("Location: teacher_management.php");
                        exit();
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("
                            INSERT INTO teachers (username, password, fname, lname, phone, photo)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$username, $hashed_password, $fname, $lname, $mobile, $photo]);
                        $_SESSION['success_message'] = "Teacher added successfully.";
                        header("Location: teacher_management.php");
                        exit();
                    }
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Failed to add teacher: " . $e->getMessage();
                    header("Location: teacher_management.php");
                    exit();
                }
            }
        }
    } elseif (isset($_POST['assign_subjects'])) {
        // Handle Assign Subjects form submission
        $teacher_id = $_POST['teacher_id'];
        $class_subject_ids = isset($_POST['class_subject_ids']) ? $_POST['class_subject_ids'] : [];

        try {
            // Remove existing subject assignments for this teacher
            $stmt = $conn->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?");
            $stmt->execute([$teacher_id]);

            // Add new subject assignments
            if (!empty($class_subject_ids)) {
                $stmt = $conn->prepare("INSERT INTO teacher_subjects (teacher_id, class_subject_id) VALUES (?, ?)");
                foreach ($class_subject_ids as $class_subject_id) {
                    $stmt->execute([$teacher_id, $class_subject_id]);
                }
            }
            $_SESSION['success_message'] = "Subjects assigned successfully.";
            header("Location: teacher_management.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Failed to assign subjects: " . $e->getMessage();
            header("Location: teacher_management.php");
            exit();
        }
    } elseif (isset($_POST['edit_teacher'])) {
        // Handle Edit Teacher form submission
        $teacher_id = $_POST['teacher_id'];
        $fname = trim($_POST['edit_fname']);
        $lname = trim($_POST['edit_lname']);
        $mobile = trim($_POST['edit_mobile']);
        $class_subject_ids = isset($_POST['edit_class_subject_ids']) ? $_POST['edit_class_subject_ids'] : [];
        $photo = null;

        // Handle photo upload
        if (isset($_FILES['edit_photo']) && $_FILES['edit_photo']['error'] === UPLOAD_ERR_OK) {
            $photo = file_get_contents($_FILES['edit_photo']['tmp_name']);
        }

        if (empty($fname) || empty($lname) || empty($mobile)) {
            $_SESSION['error_message'] = "All fields are required.";
            header("Location: teacher_management.php");
            exit();
        } elseif (!preg_match('/^[0-9]{11}$/', $mobile)) {
            $_SESSION['error_message'] = "Mobile number must be an 11-digit phone number.";
            header("Location: teacher_management.php");
            exit();
        } else {
            try {
                // Update teacher details
                if ($photo) {
                    $stmt = $conn->prepare("
                        UPDATE teachers 
                        SET fname = ?, lname = ?, phone = ?, photo = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$fname, $lname, $mobile, $photo, $teacher_id]);
                } else {
                    $stmt = $conn->prepare("
                        UPDATE teachers 
                        SET fname = ?, lname = ?, phone = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$fname, $lname, $mobile, $teacher_id]);
                }

                // Update subject assignments
                $stmt = $conn->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?");
                $stmt->execute([$teacher_id]);

                if (!empty($class_subject_ids)) {
                    $stmt = $conn->prepare("INSERT INTO teacher_subjects (teacher_id, class_subject_id) VALUES (?, ?)");
                    foreach ($class_subject_ids as $class_subject_id) {
                        $stmt->execute([$teacher_id, $class_subject_id]);
                    }
                }

                $_SESSION['success_message'] = "Teacher details and subjects updated successfully.";
                header("Location: teacher_management.php");
                exit();
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Failed to update teacher: " . $e->getMessage();
                header("Location: teacher_management.php");
                exit();
            }
        }
    } elseif (isset($_POST['delete_teacher'])) {
        // Handle Delete Teacher
        $teacher_id = $_POST['teacher_id'];

        try {
            $stmt = $conn->prepare("DELETE FROM teachers WHERE id = ?");
            $stmt->execute([$teacher_id]);
            $_SESSION['success_message'] = "Teacher deleted successfully.";
            header("Location: teacher_management.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Failed to delete teacher: " . $e->getMessage();
            header("Location: teacher_management.php");
            exit();
        }
    }
}

// Fetch all teachers with their assigned subjects
try {
    $stmt = $conn->prepare("
        SELECT t.*, 
               GROUP_CONCAT(s.subject_name,' - ',' Class ', c.class_name ) AS subjects
        FROM teachers t
        LEFT JOIN teacher_subjects ts ON t.id = ts.teacher_id
        LEFT JOIN class_subjects cs ON ts.class_subject_id = cs.id
        LEFT JOIN classes c ON cs.class_id = c.id
        LEFT JOIN subjects s ON cs.subject_id = s.id
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Failed to fetch teachers: " . $e->getMessage();
    $teachers = [];
}

// Fetch all class subjects for the assignment form
try {
    $stmt = $conn->prepare("
        SELECT cs.id, c.class_name, s.subject_name 
        FROM class_subjects cs
        JOIN classes c ON cs.class_id = c.id
        JOIN subjects s ON cs.subject_id = s.id
        ORDER BY c.class_name, s.subject_name
    ");
    $stmt->execute();
    $class_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Failed to fetch class subjects: " . $e->getMessage();
    $class_subjects = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Management - School Management System</title>
    <link rel="stylesheet" href="CSS/admin.css">
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

    /* Content Layout */
    .content-wrapper {
        display: flex;
        gap: 20px;
    }

    /* Teacher List */
    .teacher-list-container {
        flex: 3;
        max-height: 60vh;
        overflow-y: auto;
        background: white;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    .teacher-list-container table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
    }

    .teacher-list-container th,
    .teacher-list-container td {
        text-align: center;
        width: auto; /* Equal width for all 6 columns */
        min-width: 100px;
    }

    .teacher-list-container th {
        background-color: #2c3e50;
        color: white;
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .teacher-list-container td {
        background-color: white;
    }

    .teacher-list-container .action-buttons a {
        color: #3498db;
        text-decoration: none;
        margin-right: 5px;
    }
    .teacher-list-container .action-buttons a:hover {
        text-decoration: underline;
    }
    .teacher-list-container .action-buttons .delete-teacher {
        color: #dc3545;
    }
    .teacher-list-container .action-buttons .delete-teacher:hover {
        text-decoration: underline;
    }

    /* Action Buttons Below List */
    .action-buttons-container {
        margin-top: 20px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .action-buttons-container button {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.3s;
    }
    .add-teacher-btn, .assign-subjects-btn {
        background-color: #28a745;
        color: white;
    }
    .add-teacher-btn:hover, .assign-subjects-btn:hover {
        background-color: #218838;
    }

    /* Add Teacher Form (Hidden by Default) */
    .add-teacher-form-container {
        display: none;
        margin-top: 20px;
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }
    .add-teacher-form-container.active {
        display: block;
    }

    /* Assign Subjects Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
    }
    .modal-content {
        background: white;
        width: 90%;
        max-width: 600px;
        margin: 50px auto;
        padding: 20px;
        border-radius: 8px;
        position: relative;
    }
    .modal-content h2 {
        margin-top: 0 px;
    }
    .close-btn {
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 24px;
        cursor: pointer;
    }
    .subject-checkboxes {
        max-height: 300px;
        overflow-y: auto;
        margin-bottom: 15px;
    }
    .subject-checkboxes label {
        display: flex;
        align-items: center;
        margin: 8px auto;
    }
    .subject-checkboxes input[type="checkbox"] {
        margin-right: 10px;
        margin-top:12px;
        width: 16px;
        height: 16px;
    }

    /* Edit Teacher Modal */
    .edit-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
    }
    .edit-modal-content {
        background: white;
        width: 90%;
        max-width: 600px;
        margin: 50px auto;
        padding: 20px;
        border-radius: 8px;
        position: relative;
    }
    .edit-modal-content h2 {
        margin-top: 0;
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
</style>
</head>
<body>
    <div class="container">
        <?php include 'admin_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Title Section -->
            <div class="title-section">
                <h2><?php echo htmlspecialchars($school_name); ?></h2>
                <p><?php echo htmlspecialchars($tag_line); ?></p>
            </div>

            <!-- Teacher List -->
            <div class="teacher-list-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Subjects</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($teachers)) { ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No teachers found.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($teachers as $teacher) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($teacher['id']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['fname'] . ' ' . $teacher['lname']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['subjects'] ?? 'Not assigned'); ?></td>
                                    <td class="action-buttons">
                                        <a href="#" class="edit-teacher" 
                                           data-id="<?php echo $teacher['id']; ?>" 
                                           data-fname="<?php echo htmlspecialchars($teacher['fname']); ?>" 
                                           data-lname="<?php echo htmlspecialchars($teacher['lname']); ?>" 
                                           data-mobile="<?php echo htmlspecialchars($teacher['phone']); ?>">Edit</a>
                                        <a href="#" class="assign-subjects" 
                                           data-id="<?php echo $teacher['id']; ?>">Assign Subjects</a>
                                        <a href="#" class="delete-teacher" 
                                           data-id="<?php echo $teacher['id']; ?>">Delete</a>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons-container">
                <button class="add-teacher-btn" id="add-teacher-btn">Add Teacher</button>
            </div>

            <!-- Add Teacher Form (Hidden by Default) -->
            <div class="add-teacher-form-container" id="add-teacher-form">
                <h2>Add New Teacher</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="add_teacher" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <label for="fname">First Name:</label>
                    <input type="text" id="fname" name="fname" value="<?php echo isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : ''; ?>" required>
                    
                    <label for="lname">Last Name:</label>
                    <input type="text" id="lname" name="lname" value="<?php echo isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : ''; ?>" required>
                    
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                    
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    
                    <label for="mobile">Mobile:</label>
                    <input type="text" id="mobile" name="mobile" value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>" required>
                    
                    <label for="photo">Teacher Photo (Optional):</label>
                    <input type="file" id="photo" name="photo" accept="image/*">
                    
                    <button type="submit">Add Teacher</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Subjects Modal -->
    <div class="modal" id="assign-subjects-modal">
        <div class="modal-content">
            <span class="close-btn" id="close-assign-modal">×</span>
            <h2>Assign Subjects</h2>
            <form method="POST">
                <input type="hidden" name="assign_subjects" value="1">
                <input type="hidden" name="teacher_id" id="assign-teacher-id">
                
                <div class="subject-checkboxes">
                    <?php if (empty($class_subjects)) { ?>
                        <p>No subjects available. Please add subjects to classes first.</p>
                    <?php } else { ?>
                        <?php foreach ($class_subjects as $subject) { ?>
                            <label>
                                <input type="checkbox" name="class_subject_ids[]" value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['class_name'] . ' - ' . $subject['subject_name']); ?>
                            </label>
                        <?php } ?>
                    <?php } ?>
                </div>
                
                <button type="submit" <?php echo empty($class_subjects) ? 'disabled' : ''; ?>>Assign Subjects</button>
            </form>
        </div>
    </div>

    <!-- Edit Teacher Modal -->
    <div class="edit-modal" id="edit-teacher-modal">
        <div class="edit-modal-content">
            <span class="close-btn" id="close-edit-modal">×</span>
            <h2>Edit Teacher</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="edit_teacher" value="1">
                <input type="hidden" name="teacher_id" id="edit-teacher-id">
                
                <label for="edit_fname">First Name:</label>
                <input type="text" id="edit_fname" name="edit_fname" required>
                
                <label for="edit_lname">Last Name:</label>
                <input type="text" id="edit_lname" name="edit_lname" required>
                
                <label for="edit_mobile">Mobile:</label>
                <input type="text" id="edit_mobile" name="edit_mobile" required>
                
                <label for="edit_photo">Update Photo (Optional):</label>
                <input type="file" id="edit_photo" name="edit_photo" accept="image/*">
                
                <h3>Assign Subjects</h3>
                <div class="subject-checkboxes">
                    <?php if (empty($class_subjects)) { ?>
                        <p>No subjects available. Please add subjects to classes first.</p>
                    <?php } else { ?>
                        <?php foreach ($class_subjects as $subject) { ?>
                            <label>
                                <input type="checkbox" name="edit_class_subject_ids[]" value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['class_name'] . ' - ' . $subject['subject_name']); ?>
                            </label>
                        <?php } ?>
                    <?php } ?>
                </div>
                
                <button type="submit">Update Teacher</button>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <?php if ($success_message) { ?>
        <div id="toast" class="toast success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php } elseif ($error_message) { ?>
        <div id="toast" class="toast error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php } ?>

    <!-- JavaScript -->
    <script>
        // Show/Hide Add Teacher Form
        document.getElementById('add-teacher-btn').addEventListener('click', function() {
            const form = document.getElementById('add-teacher-form');
            form.classList.toggle('active');
        });

        // Assign Subjects Modal
        const assignModal = document.getElementById('assign-subjects-modal');
        const closeAssignModal = document.getElementById('close-assign-modal');
        document.querySelectorAll('.assign-subjects').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const teacherId = this.getAttribute('data-id');
                document.getElementById('assign-teacher-id').value = teacherId;

                // Fetch current assignments for this teacher
                fetchAssignments(teacherId, 'class_subject_ids[]');

                assignModal.style.display = 'block';
            });
        });

        closeAssignModal.addEventListener('click', function() {
            assignModal.style.display = 'none';
        });

        window.addEventListener('click', function(e) {
            if (e.target === assignModal) {
                assignModal.style.display = 'none';
            }
        });

        // Edit Teacher Modal
        const editModal = document.getElementById('edit-teacher-modal');
        const closeEditModal = document.getElementById('close-edit-modal');
        document.querySelectorAll('.edit-teacher').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');
                const fname = this.getAttribute('data-fname');
                const lname = this.getAttribute('data-lname');
                const mobile = this.getAttribute('data-mobile');

                document.getElementById('edit-teacher-id').value = id;
                document.getElementById('edit_fname').value = fname;
                document.getElementById('edit_lname').value = lname;
                document.getElementById('edit_mobile').value = mobile;

                // Fetch current assignments for this teacher
                fetchAssignments(id, 'edit_class_subject_ids[]');

                editModal.style.display = 'block';
            });
        });

        closeEditModal.addEventListener('click', function() {
            editModal.style.display = 'none';
        });

        window.addEventListener('click', function(e) {
            if (e.target === editModal) {
                editModal.style.display = 'none';
            }
        });

        // Delete Teacher
        document.querySelectorAll('.delete-teacher').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const teacherId = this.getAttribute('data-id');
                if (confirm('Are you sure you want to delete this teacher? This action cannot be undone.')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'teacher_management.php';
                    form.style.display = 'none';
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'delete_teacher';
                    input.value = '1';
                    form.appendChild(input);
                    const teacherIdInput = document.createElement('input');
                    teacherIdInput.type = 'hidden';
                    teacherIdInput.name = 'teacher_id';
                    teacherIdInput.value = teacherId;
                    form.appendChild(teacherIdInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });

        // Fetch current subject assignments for the teacher
        function fetchAssignments(teacherId, checkboxName) {
            fetch('fetch_assignments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `teacher_id=${teacherId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error(data.error);
                    return;
                }
                const checkboxes = document.querySelectorAll(`input[name="${checkboxName}"]`);
                checkboxes.forEach(checkbox => {
                    checkbox.checked = data.includes(parseInt(checkbox.value));
                });
            })
            .catch(error => console.error('Error fetching assignments:', error));
        }

        // Show Toast Notification
        function showToast() {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }
        }
        window.onload = showToast;
    </script>
</body>
</html>