<?php
/**
 * FonePay WHMCS Payment Callback File
 */

// Require libraries needed for gateway module functions.
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

$PID = $gatewayParams['PID'];
$sharedSecretKey = $gatewayParams['sharedSecretKey'];

// Prepare request data for verification
$requestData = [
    'PRN' => $_GET['PRN'],
    'PID' => $PID,
    'BID' => $_GET['BID'],
    'AMT' => $_GET['AMT'],
    'UID' => $_GET['UID'],
    'DV' => hash_hmac('sha512', $PID.','.$_GET['AMT'].','.$_GET['PRN'].','.$_GET['BID'].','.$_GET['UID'], $sharedSecretKey),
];

// Verify payment by sending a GET request to the verification URL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $_GET['RU'].'?'.http_build_query($requestData));
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$responseXML = curl_exec($ch);
curl_close($ch);

$response = simplexml_load_string($responseXML);
$success = $response && $response->success == 'true';
$message = $response ? $response->message : 'Unknown error';

// Retrieve data returned in payment gateway callback
$invoiceId = $_GET['inv'];
$transactionId = $_GET['BID'];
$paymentAmount = $_GET['AMT']; // Amount to be used for payment
$currencyCode = $_GET['currency'];
$paymentFee = 0;

// Handle NPR currency code
if ($currencyCode != 'NPR') {
    // Handle cases where currency code is not NPR, if applicable
    // Log an error or handle as needed
    $message = 'Currency code mismatch';
    $success = false;
}

// Validate Invoice ID
checkCbInvoiceID($invoiceId, $gatewayParams['name']);

// Validate Transaction ID to prevent duplicate entries
checkCbTransID($transactionId);

// Log Transaction
logTransaction($gatewayParams['name'], $_GET, $success ? 'Success' : 'Failure');

if ($success) {
    // Add Invoice Payment
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $paymentAmount,
        $paymentFee,
        $gatewayModuleName
    );
}

// Redirect back to the invoice page with a message
$invoiceURL = $_GET['vurl'];
if (isset($invoiceURL)) {
    $separator = strpos($invoiceURL, '?') === false ? '?' : '&';
    $invoiceURL .= $separator . "msg=" . urlencode($message);
    header('Location: ' . $invoiceURL);
    exit;
}
?>
