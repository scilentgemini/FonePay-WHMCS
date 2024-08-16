<?php
/**
 * FonePay WHMCS Payment Callback File
 */

// Require necessary libraries for WHMCS functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Extract gateway configuration parameters.
$PID = $gatewayParams['PID'];
$sharedSecretKey = $gatewayParams['sharedSecretKey'];

// Extract parameters from callback request.
$prn = isset($_GET['PRN']) ? $_GET['PRN'] : '';
$bid = isset($_GET['BID']) ? $_GET['BID'] : '';
$amt = isset($_GET['AMT']) ? $_GET['AMT'] : '';
$uid = isset($_GET['UID']) ? $_GET['UID'] : '';
$currencyCode = isset($_GET['currency']) ? $_GET['currency'] : '';
$invoiceId = isset($_GET['inv']) ? $_GET['inv'] : '';
$returnUrl = isset($_GET['vurl']) ? $_GET['vurl'] : '';

// Validate the presence of required parameters.
if (empty($prn) || empty($bid) || empty($amt) || empty($uid) || empty($invoiceId) || empty($returnUrl)) {
    die("Invalid callback data");
}

// Prepare request data for verification.
$requestData = [
    'PRN' => $prn,
    'PID' => $PID,
    'BID' => $bid,
    'AMT' => $amt,
    'UID' => $uid,
    'DV' => hash_hmac('sha512', $PID.','.$amt.','.$prn.','.$bid.','.$uid, $sharedSecretKey),
];

// Verify payment by sending a GET request to the verification URL.
$verificationUrl = isset($_GET['RU']) ? $_GET['RU'] : '';
if (!empty($verificationUrl)) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verificationUrl . '?' . http_build_query($requestData));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $responseXML = curl_exec($ch);
    curl_close($ch);

    // Parse the response.
    $response = simplexml_load_string($responseXML);
    $success = $response && $response->success == 'true';
    $message = $response ? $response->message : 'Unknown error';
} else {
    $success = false;
    $message = 'Verification URL not provided';
}

// Handle currency code mismatch if necessary.
if ($currencyCode != 'NPR') {
    $message = 'Currency code mismatch';
    $success = false;
}

// Validate Invoice ID.
checkCbInvoiceID($invoiceId, $gatewayParams['name']);

// Validate Transaction ID to prevent duplicates.
checkCbTransID($bid);

// Log the transaction result.
logTransaction($gatewayParams['name'], $_GET, $success ? 'Success' : 'Failure');

// Add invoice payment if successful.
if ($success) {
    $paymentAmount = $amt; // Ensure the amount is correct for NPR.
    $paymentFee = 0; // Set this if there are any fees involved.
    addInvoicePayment(
        $invoiceId,
        $bid,
        $paymentAmount,
        $paymentFee,
        $gatewayModuleName
    );
}

// Redirect back to the invoice page with a message.
if (isset($returnUrl)) {
    $separator = strpos($returnUrl, '?') === false ? '?' : '&';
    $returnUrl .= $separator . "msg=" . urlencode($message);
    header('Location: ' . $returnUrl);
    exit;
}
?>
