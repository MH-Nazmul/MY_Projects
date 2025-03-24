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
    // Ensure the smsdb_settings table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'settings'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        die("Error: Table smsdb_settings does not exist in the database.");
    }

    // Ensure the smsdb_settings table has a default record if empty
    $stmt = $conn->query("SELECT COUNT(*) FROM settings");
    if ($stmt === false) {
        die("Error: Failed to execute SELECT COUNT(*) query.");
    }

    $count = $stmt->fetchColumn();
    if ($count === false) {
        die("Error: Failed to fetch count from smsdb_settings.");
    }

    if ($count == 0) {
        // Insert a default record into settings
        $stmt = $conn->prepare("INSERT INTO settings (id, school_name, home_text, about_text) VALUES (1, ?, ?, ?)");
        $result = $stmt->execute(['Default School', 'Welcome to Default School', 'About Default School']);
        if (!$result) {
            die("Error: Failed to insert default record into smsdb_settings.");
        }
    } 

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
        <?php echo $school_details['school_name']; ?> - School Management System
    </title>
    <link rel="stylesheet" href="CSS/index.css">
    <style>
        body {
            background: url('<?php echo $background_image; ?>') no-repeat center center fixed;
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
                <button class="box" id="float-right" onclick="window.location.href='login.html'">Login</button>
            </div>
        </nav>

        <!-- Home Section -->
        <section id="home">
            <div class="container">
                <img src="<?php echo $logo_image; ?>" alt="School Logo">
                <h4>Welcome to
                    <?php echo $school_details['school_name']; ?>
                </h4>
                <p>
                    <?php echo $school_details['home_text'] ?: 'Brief description of the school goes here.'; ?>
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
                    <img src="data:image/jpeg;base64,<?php echo base64_encode($image['image']); ?>" alt="Gallery Image">
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
                <h2>About Us</h2>
                <p>
                    <?php echo $school_details['about_text'] ?: 'Detailed information about the school, its history, mission, and values.'; ?>
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
            <p>© 2023 School Management System. All rights reserved.</p>
        </footer>
    </div>
</body>

</html>