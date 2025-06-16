<?php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verify that db_connect.php is included
if (!file_exists('db_connect.php')) {
    die("Error: db_connect.php not found.");
}

include 'db_connect.php';

// Verify that $conn is defined and is a PDO object
if (!isset($conn) || !($conn instanceof PDO)) {
    die("Error: Database connection failed. Check db_connect.php.");
}
try {
    $stmt = $conn->prepare("SELECT school_name, address FROM settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $school_name = $settings['school_name'] ?? 'Your School Name';
    $school_address = $settings['address'] ?? 'Your School Address';
} catch (PDOException $e) {
    $school_name = 'Your School Name';
    $school_address = 'Your School Address';
}

try {
    // Ensure the settings table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'settings'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        die("Error: Table settings does not exist in the database.");
    }

    // Ensure the settings table has a default record if empty
    $stmt = $conn->query("SELECT COUNT(*) FROM settings");
    if ($stmt === false) {
        die("Error: Failed to execute SELECT COUNT(*) query.");
    }

    $count = $stmt->fetchColumn();
    if ($count === false) {
        die("Error: Failed to fetch count from settings.");
    }

    if ($count == 0) {
        // Insert a default record into settings
        $stmt = $conn->prepare("INSERT INTO settings (id, school_name, tag_line, about_text, background_image, logo_image) VALUES (1, ?, ?, ?, ?, ?)");
        $result = $stmt->execute(['Default School', 'Welcome to Default School', 'About Default School', NULL, NULL]);
        if (!$result) {
            die("Error: Failed to insert default record into settings.");
        }
    }

    // Fetch school details
    $stmt = $conn->prepare("SELECT * FROM settings WHERE id = 1");
    $stmt->execute();
    $school_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$school_details) {
        die("Error: Failed to fetch school details from settings.");
    }

    // Fetch gallery images (only fetch the ID, since we'll use get_image.php to display the image)
    $stmt = $conn->prepare("SELECT id FROM gallery_images ORDER BY preference DESC, created_at DESC");
    $stmt->execute();
    $gallery_images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch notices from the last month, ordered by event_date ASC
    $stmt = $conn->prepare("
        SELECT event_title, event_description, event_date 
        FROM events 
        WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) 
        AND event_date <= CURDATE() 
        ORDER BY event_date ASC
    ");
    $stmt->execute();
    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set background image (use a fallback if not set in the database)
    $background_image = $school_details['background_image'] ? 'get_image.php?type=background' : 'https://via.placeholder.com/1920x1080.jpg';

    // Set logo image (use a fallback if not set in the database)
    $logo_image = $school_details['logo_image'] ? 'get_image.php?type=logo' : 'https://via.placeholder.com/150x150.png';

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo htmlspecialchars($school_details['school_name']); ?> - School Management System
    </title>
    <link rel="stylesheet" href="CSS/index.css">
    <style>
        /* Only keep the dynamic background image style here */
        body {
            background: url('<?php echo htmlspecialchars($background_image); ?>') no-repeat center center fixed;
            background-size: cover;
        }
    </style>
</head>

<body>

    <div class="back-fill">
        <!-- Floating Navigation Bar -->
<nav id="navbar">
    <div>
        <button id="box" onclick="window.location.href='#home'">Home</button>
        <button id="box" onclick="window.location.href='#gallery'">Gallery</button>
        <button id="box" onclick="window.location.href='#about'">About</button>
        <button id="box" onclick="window.location.href='#contact'">Contact</button>
        <!-- Notices Dropdown Button -->
        <div class="dropdown">
            <button id="box">Notices</button>
            <div class="dropdown-content">
                <?php if (empty($notices)) { ?>
                    <div class="notice-item">
                        <p>No notices available in the last month.</p>
                    </div>
                <?php } else { ?>
                    <?php foreach ($notices as $notice) { ?>
                        <div class="notice-item">
                            <h4><?php echo htmlspecialchars($notice['event_title']); ?></h4>
                            <p>Date: <?php echo htmlspecialchars($notice['event_date']); ?></p>
                            <p><?php echo htmlspecialchars($notice['event_description']); ?></p>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
        <button class="box" id="float-right" onclick="window.location.href='login.php'">Login</button>
    </div>
</nav>
        <!-- Home Section -->
        <section id="home">
            <div class="container">
                <img src="<?php echo htmlspecialchars($logo_image); ?>" alt="School Logo">
                <h4>
                    <?php echo htmlspecialchars($school_details['school_name']); ?>
                </h4>
                <p>
                    <?php echo htmlspecialchars($school_details['tag_line'] ?: 'Brief description of the school goes here.'); ?>
                </p>
            </div>
        </section>

        <!-- Gallery Section -->
        <section id="gallery">
            <div class="container">
                <h2>Gallery</h2>
                <div class="gallery-grid">
                    <?php if ($gallery_images) { ?>
                        <?php foreach ($gallery_images as $image) { ?>
                            <img src="get_image.php?id=<?php echo $image['id']; ?>&table=gallery_images" alt="Gallery Image" onclick="openModal(this.src)">
                        <?php } ?>
                    <?php } else { ?>
                        <p>No images available in the gallery.</p>
                    <?php } ?>
                </div>
            </div>
        </section>

        <!-- About Section -->
        <section id="about">
            <div class="container">
                <h2>Principal's Speech</h2>
                <p>
                    <?php echo htmlspecialchars($school_details['about_text'] ?: 'Detailed information about the school, its history, mission, and values.'); ?>
                </p>
            </div>
        </section>

        <!-- Contact Section -->
        <section id="contact">
            <div class="floating-window">
                <h2>Contact Us</h2>
                <?php if (isset($_GET['success'])) { ?>
                    <center><p class="success"><?php echo htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8'); ?></p></center>
                <?php } ?>
                <?php if (isset($_GET['error'])) { ?>
                    <center><p class="error"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></p></center>
                <?php } ?>
                <form action="submit_complains.php" method="POST">
                    <div class="form-group">
                        <label for="email">Email address</label>
                        <input type="email" id="email" name="email" placeholder="Enter email" required>
                        <br>
                        <small>We'll never share your email with anyone else.</small>
                    </div>
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" placeholder="Enter your name" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" rows="4" placeholder="Enter your message" required></textarea>
                    </div>
                    <button type="submit">Send</button>
                </form>
            </div>
        </section>

        <!-- Footer -->
        <footer>
    
    <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_name); ?>. All rights reserved.</p>
    <p>Address: <?php echo htmlspecialchars($school_address); ?></p>
</footer>

        <!-- Modal for Full-Screen Image -->
        <div id="imageModal" class="modal">
            <span class="close" onclick="closeModal()">×</span>
            <img class="modal-content" id="modalImage">
        </div>
    </div>

    <!-- JavaScript for Modal -->
    <script>
        // Get the modal
        const modal = document.getElementById("imageModal");
        const modalImg = document.getElementById("modalImage");

        // Function to open the modal with the clicked image
        function openModal(src) {
            modal.style.display = "flex";
            modalImg.src = src;
        }

        // Function to close the modal
        function closeModal() {
            modal.style.display = "none";
        }

        // Close the modal when clicking outside the image
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    </script>
</body>
</html>
