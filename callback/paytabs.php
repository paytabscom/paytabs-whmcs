<?php

/**
 * WHMCS Sample Payment Callback File
 *
 * It demonstrates verifying that the payment gateway module is active,
 * validating an Invoice ID, checking for the existence of a Transaction ID,
 * Logging the Transaction for debugging and Adding Payment to an Invoice.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
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

// Retrieve data returned in payment gateway callback
// Varies per payment gateway
$invoiceId = $_REQUEST["invoiceid"];
$transactionId = $_POST['payment_reference']; //transaction id from paytabs

$Email = $gatewayParams['Email'];
$SecretKey = $gatewayParams['SecretKey'];


/**
 * Validate callback authenticity.
 *
 * Most payment gateways provide a method of verifying that a callback
 * originated from them. In the case of our example here, this is achieved by
 * way of a shared secret which is used to build and compare a hash.
 */

$pt = new paytabs($Email, $SecretKey);
$verify_response = $pt->verify_payment($transactionId);

$paymentAmount = $verify_response->amount;
$paymentCurrency = $verify_response->currency;

//if get response successful
if ($verify_response->response_code == 100) {
	$success = true;
	$res_msg = $verify_response->result;
} else {
	$success = false;
	$res_msg = $verify_response->result;
}

/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 *
 * @param int $invoiceId Invoice ID
 * @param string $gatewayName Gateway Name
 */
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 *
 * @param string $transactionId Unique Transaction ID
 */
checkCbTransID($transactionId);

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */
$transactionStatus = $success ? 'Success' : 'Failure';
logTransaction($gatewayParams['name'], $_POST, $transactionStatus);

if ($success) {
	/**
	 * Add Invoice Payment.
	 *
	 * Applies a payment transaction entry to the given invoice ID.
	 *
	 * @param int $invoiceId         Invoice ID
	 * @param string $transactionId  Transaction ID
	 * @param float $paymentAmount   Amount paid (defaults to full balance)
	 * @param float $paymentFee      Payment fee (optional)
	 * @param string $gatewayModule  Gateway module name
	 */
	addInvoicePayment(
		$invoiceId,
		$transactionId,
		$paymentAmount,
		$paymentFee,
		$gatewayModuleName
	);
}

redirectToInvoice($success);

//

function redirectToInvoice($result)
{
	global $invoiceId;

	$page = WHMCS\Utility\Environment\WebHelper::getBaseUrl();
	$host = getServerUrl();

	$url = $host . $page . '/viewinvoice.php?id=' . $invoiceId;

	$callbackUrl = $url . ($result ? '&paymentsuccess=true' : '&paymentfailed=true');

	header('Location: ' . $callbackUrl);
	die;
}

function getServerUrl()
{
	$s = $_SERVER;

	$ssl = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on') ? true : false;

	$sp = strtolower($s['SERVER_PROTOCOL']);
	$protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');

	$port = $s['SERVER_PORT'];
	$port = ((!$ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ':' . $port;

	$host = isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : $s['SERVER_NAME'];

	return $protocol . '://' . $host;
}
