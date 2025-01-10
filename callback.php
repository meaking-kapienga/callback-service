<?php
header("Content-Type: application/json");

// Read the response from TinyPesa
$stkCallbackResponse = file_get_contents('php://input');

// Log the response for debugging
$logFile = "logs/stkTinypesaResponse.json";

// Check if the logs directory exists, if not, create it
if (!file_exists('logs')) {
    mkdir('logs', 0777, true);  // Create the directory with write permissions
}

// Check if the file is writable
if (is_writable(dirname($logFile))) {
    // Open the file in append mode
    $log = fopen($logFile, "a");
    if ($log) {
        fwrite($log, $stkCallbackResponse . "\n");
        fclose($log);
    } else {
        error_log("Error: Unable to open log file for writing.");
    }
} else {
    error_log("Error: Logs directory is not writable.");
}

// Decode the JSON response
$callbackContent = json_decode($stkCallbackResponse);

// Extract transaction details from the response
if ($callbackContent && isset($callbackContent->Body->stkCallback)) {
    $ResultCode = $callbackContent->Body->stkCallback->ResultCode;
    $CheckoutRequestID = $callbackContent->Body->stkCallback->CheckoutRequestID;
    $CallbackMetadata = $callbackContent->Body->stkCallback->CallbackMetadata->Item;

    // Initialize variables for Amount, MpesaReceiptNumber, and PhoneNumber
    $Amount = null;
    $MpesaReceiptNumber = null;
    $PhoneNumber = null;

    // Iterate over CallbackMetadata to find the necessary values
    foreach ($CallbackMetadata as $item) {
        if (isset($item->Name) && $item->Name == 'Amount') {
            $Amount = $item->Value;
        }
        if (isset($item->Name) && $item->Name == 'MpesaReceiptNumber') {
            $MpesaReceiptNumber = $item->Value;
        }
        if (isset($item->Name) && $item->Name == 'PhoneNumber') {
            $PhoneNumber = $item->Value;
        }
    }

    // If necessary data is found
    if ($Amount && $MpesaReceiptNumber && $PhoneNumber) {
        // Determine transaction status
        $transactionStatus = ($ResultCode === 0) ? 'completed' : 'failed';

        // Now, send data to XAMPP server (your local server for testing)
        $postData = [
            'transaction_id' => $CheckoutRequestID,
            'status' => $transactionStatus,
            'amount' => $Amount,
            'mpesa_receipt' => $MpesaReceiptNumber,
            'phone_number' => $PhoneNumber
        ];

        // Send the data to XAMPP for user balance update
        $ch = curl_init('http://localhost/dongbet/update-balance.php');  // XAMPP URL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response = curl_exec($ch);
        curl_close($ch);
    } else {
        error_log("Error: Missing necessary data (Amount, MpesaReceiptNumber, PhoneNumber).");
    }
} else {
    error_log("Error: Invalid or malformed callback response.");
}
?>
