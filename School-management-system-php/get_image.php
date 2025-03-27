<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
include 'db_connect.php';

// Verify that $conn is defined and is a PDO object
if (!isset($conn) || !($conn instanceof PDO)) {
    error_log("Database connection failed in get_image.php");
    header("HTTP/1.0 500 Internal Server Error");
    die("Error: Database connection failed. Check db_connect.php.");
}

// Handle gallery image requests
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $image_id = (int)$_GET['id'];

    try {
        // Fetch the image data from the gallery_images table
        $stmt = $conn->prepare("SELECT image_data FROM gallery_images WHERE id = ?");
        $stmt->execute([$image_id]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            error_log("No image found for ID: $image_id in gallery_images table");
            header("HTTP/1.0 404 Not Found");
            die("Image not found: No record for ID $image_id.");
        }

        if (!$image['image_data'] || strlen($image['image_data']) === 0) {
            error_log("Image data is empty for ID: $image_id");
            header("HTTP/1.0 404 Not Found");
            die("Image not found: Data is empty for ID $image_id.");
        }

        // Determine the image type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->buffer($image['image_data']);
        if (!$mime_type || !in_array($mime_type, ['image/jpeg', 'image/png', 'image/gif'])) {
            error_log("Invalid MIME type for ID: $image_id - MIME: $mime_type");
            header("HTTP/1.0 500 Internal Server Error");
            die("Invalid image type for ID $image_id.");
        }

        // Log success
        error_log("Serving image ID: $image_id, MIME type: $mime_type, Size: " . strlen($image['image_data']) . " bytes");

        // Set the appropriate headers
        header("Content-Type: $mime_type");
        header("Content-Length: " . strlen($image['image_data']));

        // Output the image data
        echo $image['image_data'];
        exit;
    } catch (PDOException $e) {
        error_log("Database error in get_image.php for ID $image_id: " . $e->getMessage());
        header("HTTP/1.0 500 Internal Server Error");
        die("Database error: " . $e->getMessage());
    }
}

// Handle background and logo image requests from the settings table
if (isset($_GET['type'])) {
    $type = $_GET['type'];
    $column = $type === 'background' ? 'background_image' : 'logo_image';

    try {
        // Fetch the image data from the settings table
        $stmt = $conn->prepare("SELECT $column FROM settings WHERE id = 1");
        $stmt->execute();
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            error_log("No settings record found for ID: 1");
            header("HTTP/1.0 404 Not Found");
            die("Settings record not found.");
        }

        if (!$image[$column] || strlen($image[$column]) === 0) {
            error_log("Image data is empty for type: $type");
            header("HTTP/1.0 404 Not Found");
            die("Image not found: Data is empty for $type.");
        }

        // Determine the image type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->buffer($image[$column]);
        if (!$mime_type || !in_array($mime_type, ['image/jpeg', 'image/png', 'image/gif'])) {
            error_log("Invalid MIME type for $type: $mime_type");
            header("HTTP/1.0 500 Internal Server Error");
            die("Invalid image type for $type.");
        }

        // Log success
        error_log("Serving $type image, MIME type: $mime_type, Size: " . strlen($image[$column]) . " bytes");

        // Set the appropriate headers
        header("Content-Type: $mime_type");
        header("Content-Length: " . strlen($image[$column]));

        // Output the image data
        echo $image[$column];
        exit;
    } catch (PDOException $e) {
        error_log("Database error in get_image.php for type $type: " . $e->getMessage());
        header("HTTP/1.0 500 Internal Server Error");
        die("Database error: " . $e->getMessage());
    }
}

// If neither id nor type is provided, return a 404
error_log("Invalid request to get_image.php: No id or type provided");
header("HTTP/1.0 404 Not Found");
die("Invalid request.");
?>