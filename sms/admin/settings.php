<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: ../logout.php");
    exit();
}

// Include database connection
include '../db_connect.php';

// Generate a CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables for messages
$success_message = '';
$error_message = '';

// Handle school settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid CSRF token.";
    } else {
        $school_name = $_POST['school_name'] ?? '';
        $tag_line = $_POST['tag_line'] ?? '';
        $about_text = $_POST['about_text'] ?? '';
        $address = $_POST['address'] ?? '';
        $background_image = null;
        $logo_image = null;

        // Handle background image upload
        if (isset($_FILES['background_image']) && $_FILES['background_image']['error'] === UPLOAD_ERR_OK) {
            $image = $_FILES['background_image'];
            $file_type = mime_content_type($image['tmp_name']);
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 10 * 1024 * 1024; // 10MB
            if (!in_array($file_type, $allowed_types)) {
                $error_message = "Invalid background image type. Only JPEG, PNG, and GIF are allowed.";
            } elseif ($image['size'] > $max_size) {
                $error_message = "Background image size exceeds 10MB limit.";
            } elseif ($image['size'] === 0) {
                $error_message = "Background image file is empty.";
            } else {
                $background_image = file_get_contents($image['tmp_name']);
                if ($background_image === false) {
                    $error_message = "Failed to read background image data.";
                } else {
                    $image_size = strlen($background_image);
                    error_log("Background image size: $image_size bytes");
                }
            }
        }

        // Handle logo image upload
        if (empty($error_message) && isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
            $image = $_FILES['logo_image'];
            $file_type = mime_content_type($image['tmp_name']);
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            if (!in_array($file_type, $allowed_types)) {
                $error_message = "Invalid logo image type. Only JPEG, PNG, and GIF are allowed.";
            } elseif ($image['size'] > $max_size) {
                $error_message = "Logo image size exceeds 5MB limit.";
            } elseif ($image['size'] === 0) {
                $error_message = "Logo image file is empty.";
            } else {
                $logo_image = file_get_contents($image['tmp_name']);
                if ($logo_image === false) {
                    $error_message = "Failed to read logo image data.";
                } else {
                    $image_size = strlen($logo_image);
                    error_log("Logo image size: $image_size bytes");
                }
            }
        }

        // Update the settings table if no errors
        if (empty($error_message)) {
            try {
                $stmt = $conn->prepare("UPDATE settings SET school_name = ?, tag_line = ?, about_text = ?, address = ?, background_image = COALESCE(?, background_image), logo_image = COALESCE(?, logo_image) WHERE id = 1");
                $stmt->execute([$school_name, $tag_line, $about_text, $address, $background_image, $logo_image]);
                $success_message = "Settings updated successfully.";
            } catch (PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Handle gallery image upload (multiple images)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['gallery_images'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid CSRF token.";
    } else {
        $images = $_FILES['gallery_images'];
        $preference = isset($_POST['preference']) ? (int)$_POST['preference'] : 0;

        // Check if any files were uploaded
        if (empty($images['name'][0])) {
            $error_message = "No images selected for upload.";
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB per image
            $success_count = 0;
            $error_messages = [];

            // Loop through each uploaded image
            for ($i = 0; $i < count($images['name']); $i++) {
                if ($images['error'][$i] !== UPLOAD_ERR_OK) {
                    $error_messages[] = "Error uploading file " . htmlspecialchars($images['name'][$i]) . ": " . $images['error'][$i];
                    continue;
                }

                // Validate file type
                $file_type = mime_content_type($images['tmp_name'][$i]);
                if (!in_array($file_type, $allowed_types)) {
                    $error_messages[] = "Invalid file type for " . htmlspecialchars($images['name'][$i]) . ". Only JPEG, PNG, and GIF are allowed.";
                    continue;
                }

                // Validate file size
                if ($images['size'][$i] > $max_size) {
                    $error_messages[] = "File size exceeds 5MB limit for " . htmlspecialchars($images['name'][$i]) . ".";
                    continue;
                }
                if ($images['size'][$i] === 0) {
                    $error_messages[] = "File is empty for " . htmlspecialchars($images['name'][$i]) . ".";
                    continue;
                }

                // Read the image data
                $image_data = file_get_contents($images['tmp_name'][$i]);
                if ($image_data === false) {
                    $error_messages[] = "Failed to read image data for " . htmlspecialchars($images['name'][$i]) . ".";
                    continue;
                }

                // Debug: Log the size of the image data
                $image_size = strlen($image_data);
                error_log("Gallery image " . $images['name'][$i] . " size: $image_size bytes");

                // Check the current number of images in the gallery
                try {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM gallery_images");
                    $stmt->execute();
                    $image_count = $stmt->fetchColumn();

                    // If there are already 20 images, delete the one with the least preference
                    if ($image_count >= 20) {
                        $stmt = $conn->prepare("SELECT id FROM gallery_images ORDER BY preference ASC, created_at ASC LIMIT 1");
                        $stmt->execute();
                        $least_preferred = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($least_preferred) {
                            $stmt = $conn->prepare("DELETE FROM gallery_images WHERE id = ?");
                            $stmt->execute([$least_preferred['id']]);
                            error_log("Deleted least preferred image ID: " . $least_preferred['id']);
                        }
                    }

                    // Insert the new image
                    $stmt = $conn->prepare("INSERT INTO gallery_images (image_data, preference) VALUES (?, ?)");
                    $stmt->execute([$image_data, $preference]);
                    $success_count++;
                } catch (PDOException $e) {
                    $error_messages[] = "Database error for " . htmlspecialchars($images['name'][$i]) . ": " . $e->getMessage();
                }
            }

            // Prepare success/error message
            if ($success_count > 0) {
                $success_message = "$success_count image(s) uploaded successfully.";
                if (!empty($error_messages)) {
                    $error_message = implode(" | ", $error_messages);
                }
            } else {
                $error_message = implode(" | ", $error_messages);
            }
        }
    }
}

// Fetch existing gallery images (for display)
try {
    // Include image_data in the query if needed by get_image.php
    $stmt = $conn->prepare("SELECT id, preference, created_at FROM gallery_images ORDER BY preference DESC, created_at DESC");
    $stmt->execute();
    $gallery_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Failed to fetch gallery images: " . $e->getMessage();
    $gallery_images = [];
}

// Fetch current settings
try {
    $stmt = $conn->prepare("SELECT * FROM settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $school_name = $settings['school_name'] ?? 'School Name';
    $tag_line = $settings['tag_line'] ?? 'Tagline';
} catch (PDOException $e) {
    $error_message = "Failed to fetch settings: " . $e->getMessage();
    $school_name = 'School Name';
    $tag_line = 'Tagline';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - School Management System</title>
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

        /* Forms Container */
        .forms-container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .form-section {
            flex: 1;
            min-width: 300px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .form-section h2 {
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
        .form-section input[type="text"],
        .form-section input[type="file"],
        .form-section input[type="number"],
        .form-section textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .form-section textarea {
            resize: vertical;
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

        /* Gallery Grid */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }
        .gallery-grid .image-container {
            width: 120px;
            text-align: center;
        }
        .gallery-grid img {
            width: 100%;
            max-width: 120px;
            height: auto;
            border-radius: 5px;
            cursor: pointer;
            transitionoulos: transform 0.3s ease;
        }
        .gallery-grid img:hover {
            transform: scale(1.05);
        }
        .gallery-grid p {
            margin: 5px 0;
            font-size: 12px;
            color: #555;
        }
        .gallery-grid .image-error {
            color: red;
            font-size: 12px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 5px;
        }
        .close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #bbb;
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

        /* Responsive Adjustments */
        @media (min-width: 768px) {
            .gallery-grid {
                grid-template-columns: repeat(5, 120px);
                justify-content: center;
            }
        }
        @media (max-width: 767px) {
            .gallery-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
            .gallery-grid .image-container {
                width: 100px;
            }
            .gallery-grid img {
                max-width: 100px;
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

            <!-- Forms Container -->
            <div class="forms-container">
                <!-- Update School Settings Form -->
                <div class="form-section">
                    <h2>Update School Settings</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <label for="school_name">School Name:</label>
                        <input type="text" id="school_name" name="school_name" value="<?php echo htmlspecialchars($settings['school_name'] ?? ''); ?>" required>
                        <label for="tag_line">Tag Line:</label>
                        <input type="text" id="tag_line" name="tag_line" value="<?php echo htmlspecialchars($settings['tag_line'] ?? ''); ?>" required>
                        <label for="about_text">About Text:</label>
                        <textarea id="about_text" name="about_text" rows="4" required><?php echo htmlspecialchars($settings['about_text'] ?? ''); ?></textarea>
                        <label for="address">School Address:</label>
                        <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($settings['address'] ?? ''); ?>" required>
                        <label for="background_image">Background Image (Max 10MB):</label>
                        <input type="file" id="background_image" name="background_image" accept="image/*">
                        <label for="logo_image">Logo Image (Max 5MB):</label>
                        <input type="file" id="logo_image" name="logo_image" accept="image/*">
                        <button type="submit" name="update_settings">Update Settings</button>
                    </form>
                </div>

                <!-- Upload Gallery Images Form and Existing Gallery Images -->
                <div class="form-section">
                    <h2>Upload Gallery Images</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <label for="gallery_images">Select Images (Max 5MB each, hold Ctrl to select multiple):</label>
                        <input type="file" id="gallery_images" name="gallery_images[]" accept="image/*" multiple>
                        <label for="preference">Preference (higher number = higher priority):</label>
                        <input type="number" id="preference" name="preference" value="0">
                        <button type="submit">Upload Images</button>
                    </form>

                    <h3>Existing Gallery Images</h3>
                    <div class="gallery-grid">
                        <?php if ($gallery_images) { ?>
                            <?php foreach ($gallery_images as $image) { ?>
                                <div class="image-container">
                                    <!-- Adjust the src path to match your server structure -->
                                    <img src="../get_image.php?id=<?php echo $image['id']; ?>" 
                                         alt="Gallery Image" 
                                         onclick="openModal(this.src)" 
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <p class="image-error" style="display: none;">Failed to load image (ID: <?php echo $image['id']; ?>)</p>
                                    <p>Preference: <?php echo htmlspecialchars($image['preference']); ?></p>
                                    <p>Uploaded: <?php echo htmlspecialchars($image['created_at']); ?></p>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <p>No images available in the gallery.</p>
                        <?php } ?>
                    </div>

                    <!-- Modal structure for full-screen image view -->
                    <div id="imageModal" class="modal">
                        <span class="close" onclick="closeModal()">×</span>
                        <img class="modal-content" id="modalImage">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <?php if ($success_message) { ?>
        <div id="toast" class="toast success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php } elseif ($error_message) { ?>
        <div id="toast" class="toast error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php } ?>

    <!-- JavaScript for Toast Notification and Modal -->
    <script>
        // Toast Notification
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

        // Modal for Full-Screen Image
        const modal = document.getElementById("imageModal");
        const modalImg = document.getElementById("modalImage");

        function openModal(src) {
            modal.style.display = "flex";
            modalImg.src = src;
        }

        function closeModal() {
            modal.style.display = "none";
        }

        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    </script>
</body>
</html>