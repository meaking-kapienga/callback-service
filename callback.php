<?php
header("Content-Type: application/json");

// Read the response from TinyPesa
$stkCallbackResponse = file_get_contents('php://input');

// Log the raw callback response for debugging
$logFile = "logs/stkTinypesaResponse.json";

// Ensure the 'logs' directory exists and is writable
if (!file_exists('logs')) {
    mkdir('logs', 0777, true);  // Create logs directory with write permissions
}

// Log the raw callback response to a file
if (file_put_contents($logFile, $stkCallbackResponse . "\n", FILE_APPEND) === false) {
    error_log("Error: Unable to write to log file.");
} else {
    error_log("Successfully logged callback response.");
}

// Decode the JSON response
$callbackContent = json_decode($stkCallbackResponse);

// Check if the callback contains the necessary structure
if ($callbackContent && isset($callbackContent->Body->stkCallback)) {
    $stkCallback = $callbackContent->Body->stkCallback;
    $ResultCode = $stkCallback->ResultCode ?? null;
    $CheckoutRequestID = $stkCallback->CheckoutRequestID ?? null;
    $CallbackMetadata = $stkCallback->CallbackMetadata->Item ?? [];

    // Initialize variables for Amount, MpesaReceiptNumber, and PhoneNumber
    $Amount = null;
    $MpesaReceiptNumber = null;
    $PhoneNumber = null;

    // Extract the necessary data from CallbackMetadata
    foreach ($CallbackMetadata as $item) {
        if ($item->Name === 'Amount') {
            $Amount = $item->Value;
        }
        if ($item->Name === 'MpesaReceiptNumber') {
            $MpesaReceiptNumber = $item->Value;
        }
        if ($item->Name === 'PhoneNumber') {
            $PhoneNumber = $item->Value;
        }
    }

    // Validate that all necessary data was extracted
    if ($Amount && $MpesaReceiptNumber && $PhoneNumber) {
        // Determine the transaction status (success or failure)
        $transactionStatus = ($ResultCode === 0) ? 'completed' : 'failed';

        // Prepare the data to send to the XAMPP server
        $postData = [
            'transaction_id' => $CheckoutRequestID,
            'status' => $transactionStatus,
            'amount' => $Amount,
            'mpesa_receipt' => $MpesaReceiptNumber,
            'phone_number' => $PhoneNumber
        ];

        // Forward the data to XAMPP (your local server)
        $ch = curl_init('http://localhost/dongbet/update-balance.php');  // Replace with your XAMPP URL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response = curl_exec($ch);

        // Log the response from the XAMPP server
        if (file_put_contents("logs/updateBalanceResponse.log", "Response: " . $response . "\n", FILE_APPEND) === false) {
            error_log("Error: Unable to write to updateBalanceResponse.log file.");
        } else {
            error_log("Successfully logged response from XAMPP server.");
        }

        // Close the CURL session
        curl_close($ch);
    } else {
        error_log("Error: Missing necessary data (Amount, MpesaReceiptNumber, PhoneNumber).");
    }
} else {
    error_log("Error: Invalid or malformed callback response.");
}
?>
