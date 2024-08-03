<?php
// process_payment.php

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "daraja_fullstack";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Daraja API configuration
$consumer_key = 'nk16Y74eSbTaGQgc9WF8j6FigApqOMWr';
$consumer_secret = '40fD1vRXCq90XFaU';
$business_short_code = '174379';
$passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
$callback_url = 'https://655a-196-250-209-180.ngrok-free.app/callback.php';

// Function to generate access token
function getAccessToken($consumer_key, $consumer_secret) {
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    $credentials = base64_encode($consumer_key . ':' . $consumer_secret);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $result = json_decode($result);
    return $result->access_token;
}

// Function to initiate STK Push
function initiateSTKPush($access_token, $business_short_code, $passkey, $amount, $phone_number, $callback_url) {
    $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    $timestamp = date('YmdHis');
    $password = base64_encode($business_short_code . $passkey . $timestamp);
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $access_token));
    
    $curl_post_data = array(
        'BusinessShortCode' => $business_short_code,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone_number,
        'PartyB' => $business_short_code,
        'PhoneNumber' => $phone_number,
        'CallBackURL' => $callback_url,
        'AccountReference' => 'CompanyXLTD',
        'TransactionDesc' => 'Payment of X' 
    );
    
    $data_string = json_encode($curl_post_data);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($curl, CURLOPT_HEADER, false);
    $curl_response = curl_exec($curl);
    return json_decode($curl_response);
}

// Process the payment
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone_number = $_POST['phoneNumber'];
    $amount = $_POST['amount'];
    
    // Get access token
    $access_token = getAccessToken($consumer_key, $consumer_secret);
    
    // Initiate STK Push
    $stk_push_response = initiateSTKPush($access_token, $business_short_code, $passkey, $amount, $phone_number, $callback_url);
    
    if (isset($stk_push_response->ResponseCode) && $stk_push_response->ResponseCode == "0") {
        // Payment request successful, save to database
        $checkout_request_id = $stk_push_response->CheckoutRequestID;
        $sql = "INSERT INTO payments (phone_number, amount, checkout_request_id, status) VALUES (?, ?, ?, 'PENDING')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sds", $phone_number, $amount, $checkout_request_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Payment request sent. Please check your phone to complete the transaction.',
                'checkout_request_id' => $checkout_request_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error saving payment details.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to initiate payment. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();
?>