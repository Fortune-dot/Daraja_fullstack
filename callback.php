<?php
// callback.php

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "daraja_fullstack";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


function logMessage($message) {
    $logFile = 'transaction_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Get the response from M-Pesa
$mpesa_response = file_get_contents('php://input');
$callbackContent = json_decode($mpesa_response);

logMessage("Received callback: " . $mpesa_response);

// Check if the callback content is valid
if (isset($callbackContent->Body->stkCallback->ResultCode)) {
    $result_code = $callbackContent->Body->stkCallback->ResultCode;
    $checkout_request_id = $callbackContent->Body->stkCallback->CheckoutRequestID;

    if ($result_code == 0) {
        // Payment successful
        $status = 'COMPLETED';
        $mpesa_receipt_number = $callbackContent->Body->stkCallback->CallbackMetadata->Item[1]->Value;
        logMessage("Payment successful: CheckoutRequestID: $checkout_request_id, M-Pesa Receipt: $mpesa_receipt_number");
    } else {
        // Payment failed
        $status = 'FAILED';
        $mpesa_receipt_number = null;
        logMessage("Payment failed: CheckoutRequestID: $checkout_request_id, Result Code: $result_code");
    }

    // Update the payment status in the database
    $sql = "UPDATE payments SET status = ?, mpesa_receipt = ? WHERE checkout_request_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $status, $mpesa_receipt_number, $checkout_request_id);
    
    if ($stmt->execute()) {
        logMessage("Payment status updated in database: $checkout_request_id - $status");
    } else {
        logMessage("Error updating payment status: " . $stmt->error);
    }
} else {
    logMessage("Invalid callback content received");
}

$conn->close();

// Respond to M-Pesa
$response = array(
    'ResultCode' => 0,
    'ResultDesc' => 'Confirmation received successfully'
);
header('Content-Type: application/json');
echo json_encode($response);
?>