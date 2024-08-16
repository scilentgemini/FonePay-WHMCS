<?php
/**
 * FonePay WHMCS Payment Gateway Module
 *
 * FonePay Payment Gateway modules allow you to integrate payment solutions with the
 * WHMCS platform.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "fonepaygeteway" and therefore all functions
 * begin "fonepaygeteway_".
 *
 * If your module or third party API does not support a given function, you
 * should not define that function within your module. Only the _config
 * function is required.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function fonepaygeteway_MetaData()
{
    return array(
        'DisplayName' => 'Fonepaygeteway',
        'APIVersion' => '1.1',
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
        'Description' => 'Fonepay Payment system developed by MauveineTech.',
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @return array
 */
function fonepaygeteway_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'FonePay Merchant Gateway Module',
        ),
        'PID' => array(
            'FriendlyName' => 'Merchant Code',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your Merchant Code here',
        ),
        'sharedSecretKey' => array(
            'FriendlyName' => 'SecretKey',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your SecretKey here',
        ),
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        ),
    );
}

function currency_converter($amount, $from_code, $to_code)
{
    if ($from_code != $to_code) {
        $from = mysql_fetch_array(select_query("tblcurrencies", "id", array("code" => $from_code)));
        $to = mysql_fetch_array(select_query("tblcurrencies", "id", array("code" => $to_code)));
        if (!(empty($from) or empty($to))) {
            $amount = convertCurrency($amount, $from['id'], $to['id']);
        }
    }
    return $amount;
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @return string
 */
function fonepaygeteway_link($params)
{
    // Gateway Configuration Parameters
    $accountId = $params['PID'];
    $sharedSecretKey = $params['sharedSecretKey'];
    $testMode = $params['testMode'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = rtrim($params['systemurl'], '/'); // Ensure no trailing slash
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // Ensure the systemUrl is correctly set
    $systemUrl = 'https://host.mauveinetech.com/'; // Update to your WHMCS base URL
    $url = 'https://clientapi.fonepay.com/api/merchantRequest';
    $PRN = uniqid();

    // Construct the custom return URL with URL encoding
    $customReturnURL = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php?PRN=' . urlencode($PRN) . '&inv=' . urlencode($invoiceId) . '&currency=' . urlencode($currencyCode);

    if ($currencyCode == 'USD') {
        $usdAmount = $amount;
        $amount = currency_converter($amount, $currencyCode, "NPR");
        $customReturnURL .= '&usdamt=' . urlencode($usdAmount);
    }

    $postfields = array(
        'PID' => $accountId,
        'MD' => 'P',
        'PRN' => $PRN,
        'R1' => $description,
        'R2' => 'payment',
        'AMT' => $amount,
        'CRN' => 'NPR',
        'RU' => $customReturnURL
    );

    $DT = date('m/d/Y');
    $DV = hash_hmac('sha512', $accountId.','.$postfields['MD'].','.$PRN.','.$postfields['AMT'].','.$postfields['CRN'].','.$DT.','.$postfields['R1'].','.$postfields['R2'].','.$postfields['RU'], $sharedSecretKey);

    $postfields['DT'] = $DT;
    $postfields['DV'] = $DV;

    $htmlOutput = '<form method="post" action="' . $url . '">';
    foreach ($postfields as $k => $v) {
        $htmlOutput .= '<input type="hidden" name="' . $k . '" value="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '" />';
    }
    $htmlOutput .= '<input type="submit" value="' . htmlspecialchars($langPayNow, ENT_QUOTES, 'UTF-8') . '" />';
    $htmlOutput .= '</form>';

    return $htmlOutput;
}



/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/refunds/
 *
 * @return array Transaction response status
 */
function fonepaygeteway_refund($params)
{
    // Gateway Configuration Parameters
    $accountId = $params['PID'];
    $secretKey = $params['sharedSecretKey'];
    $testMode = $params['testMode'];

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // perform API call to initiate refund and interpret result

    return array(
        'status' => 'success',
        'rawdata' => $responseData,
        'transid' => $refundTransactionId,
        'fees' => $feeAmount,
    );
}
?>
