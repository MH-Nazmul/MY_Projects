<?php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $message = $_POST['message'];

    try {
        // Insert the contact message into the database
        $stmt = $conn->prepare("INSERT INTO complains (name, email, MESSAGE_TEXT) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $message]);

        // Redirect back to the home page with a success message in the URL
        header("Location: index.php?success=Complaint+sent+successfully#contact");
        exit();
    } catch (PDOException $e) {
        // Redirect with an error message if the database operation fails
        header("Location: index.php?error=Failed+to+send+complaint:+" . urlencode($e->getMessage()) . "#contact");
        exit();
    }
} else {
    // Redirect with an error message if the request method is not POST
    header("Location: index.php?error=Invalid+request+method#contact");
    exit();
}
?>