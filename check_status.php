
<?php
// check_status.php

header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "daraja_fullstack";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]));
}

if (isset($_GET['checkout_request_id'])) {
    $checkout_request_id = $_GET['checkout_request_id'];
    
    $sql = "SELECT status, mpesa_receipt, amount FROM payments WHERE checkout_request_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $checkout_request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['status'] == 'COMPLETED') {
            echo json_encode([
                'success' => true,
                'status' => 'COMPLETED',
                'message' => 'Payment completed successfully',
                'mpesa_receipt' => $row['mpesa_receipt'],
                'amount' => $row['amount']
            ]);
        } elseif ($row['status'] == 'FAILED') {
            echo json_encode([
                'success' => false,
                'status' => 'FAILED',
                'message' => 'Payment failed'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'status' => 'PENDING',
                'message' => 'Payment is still pending'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Transaction not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Missing checkout_request_id parameter'
    ]);
}

$conn->close();