<?php
// class_specific_subjects.php
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

// Fetch all classes
try {
    $stmt = $conn->prepare("SELECT id, class_name FROM classes ORDER BY id desc");
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Failed to fetch classes: " . $e->getMessage();
}

// Fetch all subjects
try {
    $stmt = $conn->prepare("SELECT id, subject_name FROM subjects ORDER BY subject_name");
    $stmt->execute();
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Failed to fetch subjects: " . $e->getMessage();
}

// Handle adding subjects to a class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class_subject'])) {
    $class_id = (int)$_POST['class_id'];
    $subject_ids_json = isset($_POST['subject_ids']) ? $_POST['subject_ids'][0] : '[]';
    $subject_ids = json_decode($subject_ids_json, true);

    if (empty($subject_ids)) {
        $error_message = "Please select at least one subject.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO class_subjects (class_id, subject_id) VALUES (?, ?)");
            foreach ($subject_ids as $subject_id) {
                $subject_id = (int)$subject_id;
                // Check if the subject is already assigned to the class
                $checkStmt = $conn->prepare("SELECT id FROM class_subjects WHERE class_id = ? AND subject_id = ?");
                $checkStmt->execute([$class_id, $subject_id]);
                if ($checkStmt->rowCount() === 0) {
                    $stmt->execute([$class_id, $subject_id]);
                }
            }
            header("Location: class_specific_subjects.php?success=Subjects assigned to class successfully.");
            exit();
        } catch (PDOException $e) {
            $error_message = "Failed to assign subjects: " . $e->getMessage();
        }
    }
}

// Handle removing a subject from a class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_class_subject'])) {
    $class_subject_id = (int)$_POST['class_subject_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM class_subjects WHERE id = ?");
        $stmt->execute([$class_subject_id]);
        header("Location: class_specific_subjects.php?success=Subject removed from class successfully.");
        exit();
    } catch (PDOException $e) {
        $error_message = "Failed to remove subject: " . $e->getMessage();
    }
}

// Fetch all class-subject mappings grouped by class
try {
    $stmt = $conn->prepare("
        SELECT c.id AS class_id, c.class_name, s.id AS subject_id, s.subject_name, cs.id AS class_subject_id
        FROM classes c
        LEFT JOIN class_subjects cs ON c.id = cs.class_id
        LEFT JOIN subjects s ON cs.subject_id = s.id
        ORDER BY c.id desc, s.subject_name
    ");
    $stmt->execute();
    $class_subjects_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group subjects by class
    $class_subjects = [];
    foreach ($class_subjects_raw as $row) {
        $class_id = $row['class_id'];
        if (!isset($class_subjects[$class_id])) {
            $class_subjects[$class_id] = [
                'class_name' => $row['class_name'],
                'subjects' => []
            ];
        }
        if ($row['subject_id']) { // Only add if subject exists
            $class_subjects[$class_id]['subjects'][] = [
                'subject_id' => $row['subject_id'],
                'subject_name' => $row['subject_name'],
                'class_subject_id' => $row['class_subject_id']
            ];
        }
    }
} catch (PDOException $e) {
    $error_message = "Failed to fetch class subjects: " . $e->getMessage();
}

// Check for success or error messages
$success_message = isset($_GET['success']) ? $_GET['success'] : '';
$error_message = isset($_GET['error']) ? $_GET['error'] : (isset($error_message) ? $error_message : '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Specific Subjects - School Management System</title>
    <style>
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
        .form-section, .table-section {
            max-width: 900px;
            margin: 0 auto 20px;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }
        .form-section h2, .table-section h2 {
            margin-top: 0;
            font-size: 20px;
            color: #2c3e50;
        }
        .form-section label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-section select {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .form-section button {
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .form-section button:hover {
            background-color: #218838;
        }
        .selected-subjects {
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-height: 50px;
            background: #f9f9f9;
        }
        .selected-subjects p {
            margin: 0 0 5px;
            font-size: 14px;
        }
        #selectedSubjectsList {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .subject-item {
            display: flex;
            align-items: center;
            background: #e0e0e0;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .remove-subject {
            margin-left: 5px;
            cursor: pointer;
            color: #dc3545;
            font-weight: bold;
        }
        .remove-subject:hover {
            color: #c82333;
        }
        .table-section table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .table-section th, .table-section td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }
        .table-section th {
            background: #f5f5f5;
            color: #333;
            font-weight: bold;
        }
        .table-section tr:hover {
            background: #f9f9f9;
        }
        .table-section td:nth-child(2) {
            white-space: normal;
        }
        .table-section td:nth-child(3) {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
        }
        .table-section .delete-btn {
            padding: 4px 8px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 10px;
        }
        .table-section .delete-btn:hover {
            background: #c82333;
        }
        .error-message, .empty-message {
            text-align: center;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error-message {
            color: #dc3545;
            background: #f8d7da;
        }
        .empty-message {
            color: #666;
            background: #f1f1f1;
        }
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
        <div class="main-content">
            <!-- Title Section -->
            <div class="title-section">
                <h2><?php echo htmlspecialchars($school_name); ?></h1>
                <p><?php echo htmlspecialchars($tag_line); ?></p>
            </div>

            <!-- Assign Subjects to Class Form -->
            <div class="form-section">
                <h2>Assign Subjects to Class</h2>
                <form method="POST" id="assignSubjectsForm">
                    <label for="class_id">Select Class:</label>
                    <select id="class_id" name="class_id" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $class) { ?>
                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                        <?php } ?>
                    </select>
                    <label for="subject_dropdown">Select Subject:</label>
                    <select id="subject_dropdown">
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $subject) { ?>
                            <option value="<?php echo $subject['id']; ?>" data-name="<?php echo htmlspecialchars($subject['subject_name']); ?>">
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <div class="selected-subjects" id="selectedSubjects">
                        <p><strong>Selected Subjects:</strong></p>
                        <div id="selectedSubjectsList"></div>
                    </div>
                    <input type="hidden" name="subject_ids[]" id="hiddenSubjectIds">
                    <button type="submit" name="add_class_subject">Assign Subjects</button>
                </form>
            </div>

            <!-- Class Subjects Table -->
            <div class="table-section">
                <h2>Assigned Subjects</h2>
                <?php if (isset($error_message) && !empty($error_message)) { ?>
                    <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                <?php } elseif (empty($class_subjects) || !array_filter($class_subjects, fn($class) => !empty($class['subjects']))) { ?>
                    <p class="empty-message">No subjects have been assigned to any class yet.</p>
                <?php } else { ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Subjects</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($class_subjects as $class_id => $class) { ?>
                                <?php if (!empty($class['subjects'])) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                        <td>
                                            <?php
                                            $subject_names = array_map(fn($subject) => htmlspecialchars($subject['subject_name']), $class['subjects']);
                                            echo implode(', ', $subject_names);
                                            ?>
                                        </td>
                                        <td>
                                            <?php foreach ($class['subjects'] as $subject) { ?>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to remove <?php echo htmlspecialchars($subject['subject_name']); ?> from <?php echo htmlspecialchars($class['class_name']); ?>?');" style="display: inline;">
                                                    <input type="hidden" name="class_subject_id" value="<?php echo $subject['class_subject_id']; ?>">
                                                    <button type="submit" name="remove_class_subject" class="delete-btn"><?php echo htmlspecialchars($subject['subject_name']); ?> (Remove)</button>
                                                </form>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>
        </div>
    </div>

    <?php include '../footer.php'; ?>

    <!-- Toast Notification -->
    <?php if ($success_message) { ?>
        <div id="toast" class="toast success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php } elseif ($error_message) { ?>
        <div id="toast" class="toast error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php } ?>

    <script>
        function showToast() {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }
        window.onload = showToast;

        // Subject selection handling
        const subjectDropdown = document.getElementById('subject_dropdown');
        const selectedSubjectsList = document.getElementById('selectedSubjectsList');
        const hiddenSubjectIds = document.getElementById('hiddenSubjectIds');
        let selectedSubjects = [];

        subjectDropdown.addEventListener('change', function () {
            const subjectId = this.value;
            const subjectName = this.options[this.selectedIndex].getAttribute('data-name');

            if (subjectId && !selectedSubjects.some(subject => subject.id === subjectId)) {
                selectedSubjects.push({ id: subjectId, name: subjectName });
                updateSelectedSubjects();
            }
            this.value = ''; // Reset dropdown
        });

        function updateSelectedSubjects() {
            // Update mini box display
            selectedSubjectsList.innerHTML = '';
            selectedSubjects.forEach(subject => {
                const subjectItem = document.createElement('div');
                subjectItem.classList.add('subject-item');
                subjectItem.innerHTML = `
                    ${subject.name}
                    <span class="remove-subject" data-id="${subject.id}">×</span>
                `;
                selectedSubjectsList.appendChild(subjectItem);

                // Add remove functionality
                subjectItem.querySelector('.remove-subject').addEventListener('click', () => {
                    selectedSubjects = selectedSubjects.filter(s => s.id !== subject.id);
                    updateSelectedSubjects();
                });
            });

            // Update hidden input for form submission
            hiddenSubjectIds.value = JSON.stringify(selectedSubjects.map(subject => subject.id));
        }

        // Prevent form submission if no subjects are selected
        document.getElementById('assignSubjectsForm').addEventListener('submit', function (e) {
            if (selectedSubjects.length === 0) {
                e.preventDefault();
                alert('Please select at least one subject.');
            }
        });
    </script>
</body>
</html>