<?php
// SSLCommerz-PHP-master/pg_redirection/ipn.php

include '../../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    error_log("IPN Data: " . print_r($data, true));

    if (isset($data['tran_id']) && isset($data['status'])) {
        $tran_id = $data['tran_id'];
        $status = $data['status'] === 'VALID' ? 'success' : 'failed';
        try {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE tran_id = ?");
            $stmt->execute([$status, $tran_id]);
            if ($status === 'success') {
                $stmt = $conn->prepare("UPDATE student_dues SET status = 'paid' WHERE id = (SELECT due_id FROM orders WHERE tran_id = ?)");
                $stmt->execute([$tran_id]);
            }
            http_response_code(200);
        } catch (PDOException $e) {
            error_log("IPN Database error: " . $e->getMessage());
            http_response_code(500);
        }
    } else {
        http_response_code(400);
    }
} else {
    http_response_code(405);
}
?>