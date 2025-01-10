<?php
header("Content-Type: application/json");

// Read the response from TinyPesa
$stkCallbackResponse = file_get_contents('php://input');

// Log the response for debugging
$logFile = "stkTinypesaResponse.json";
$log = fopen($logFile, "a");
fwrite($log, $stkCallbackResponse . "\n");
fclose($log);

// Decode the JSON response
$callbackContent = json_decode($stkCallbackResponse);

// Extract transaction details from the response
if ($callbackContent && isset($callbackContent->Body->stkCallback)) {
    $ResultCode = $callbackContent->Body->stkCallback->ResultCode;
    $CheckoutRequestID = $callbackContent->Body->stkCallback->CheckoutRequestID;
    $CallbackMetadata = $callbackContent->Body->stkCallback->CallbackMetadata->Item;

    if ($CallbackMetadata && count($CallbackMetadata) >= 5) {
        $Amount = $CallbackMetadata[0]->Value;
        $MpesaReceiptNumber = $CallbackMetadata[1]->Value;
        $PhoneNumber = $CallbackMetadata[4]->Value;

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
    }
}
?>
