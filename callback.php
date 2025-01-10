<?php
header("Content-Type: application/json");

// Read the response from TinyPesa
$stkCallbackResponse = file_get_contents('php://input');

// Ensure logs directory exists
$logsDir = "logs";
if (!file_exists($logsDir)) {
    mkdir($logsDir, 0777, true); // Create logs directory with write permissions
}

// Log the raw callback response
$rawLogFile = "$logsDir/rawCallbackData.log";
file_put_contents($rawLogFile, $stkCallbackResponse . "\n", FILE_APPEND);

// Decode the JSON response
$callbackContent = json_decode($stkCallbackResponse, true);

// Check if the callback contains the necessary structure
if ($callbackContent && isset($callbackContent['Body']['stkCallback'])) {
    $stkCallback = $callbackContent['Body']['stkCallback'];
    $ResultCode = $stkCallback['ResultCode'] ?? null;
    $CheckoutRequestID = $stkCallback['CheckoutRequestID'] ?? null;
    $CallbackMetadata = $stkCallback['CallbackMetadata']['Item'] ?? [];

    // Initialize variables
    $Amount = null;
    $MpesaReceiptNumber = null;
    $PhoneNumber = null;

    // Extract required data from CallbackMetadata
    foreach ($CallbackMetadata as $item) {
        if ($item['Name'] === 'Amount') {
            $Amount = $item['Value'];
        }
        if ($item['Name'] === 'MpesaReceiptNumber') {
            $MpesaReceiptNumber = $item['Value'];
        }
        if ($item['Name'] === 'PhoneNumber') {
            $PhoneNumber = $item['Value'];
        }
    }

    // Validate extracted data
    if ($Amount && $MpesaReceiptNumber && $PhoneNumber) {
        // Determine transaction status
        $transactionStatus = ($ResultCode === 0) ? 'completed' : 'failed';

        // Prepare data for forwarding to XAMPP server
        $postData = [
            'transaction_id' => $CheckoutRequestID,
            'status' => $transactionStatus,
            'amount' => $Amount,
            'mpesa_receipt' => $MpesaReceiptNumber,
            'phone_number' => $PhoneNumber
        ];

        // Forward data to XAMPP server
        $ch = curl_init('http://localhost/dongbet/update-balance.php'); // Replace with your local server URL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response = curl_exec($ch);

        // Log the response from XAMPP server
        $updateResponseLogFile = "$logsDir/updateBalanceResponse.log";
        file_put_contents($updateResponseLogFile, "Response: " . $response . "\n", FILE_APPEND);

        // Close CURL session
        curl_close($ch);
    } else {
        error_log("Error: Missing necessary data (Amount, MpesaReceiptNumber, PhoneNumber).");
    }
} else {
    error_log("Error: Invalid or malformed callback response.");
}
?>
