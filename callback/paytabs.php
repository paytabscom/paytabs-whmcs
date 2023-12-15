<?php

/**
 * WHMCS Payment Callback File
 *
 * It demonstrates verifying that the payment gateway module is active,
 * validating an Invoice ID, checking for the existence of a Transaction ID,
 * Logging the Transaction for debugging and Adding Payment to an Invoice.
 *
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

require_once '../paytabs_files/paytabs_core.php';
require_once '../paytabs_files/paytabs_functions.php';

// Retrieve data returned in payment gateway callback
// Varies per payment gateway
$invoiceId = $_REQUEST["invoiceid"];
$paymentRef = $_POST['tranRef']; //transaction id from paytabs

if (!$paymentRef) {
	die('Payment reference is missing');
}

/**
 * Validate callback authenticity.
 *
 * Most payment gateways provide a method of verifying that a callback
 * originated from them. In the case of our example here, this is achieved by
 * way of a shared secret which is used to build and compare a hash.
 */

$pt = paytabs_getApi($gatewayParams);
$verify_response = $pt->verify_payment($paymentRef);

$success = $verify_response->success;
$message = $verify_response->message;
$transactionId = $verify_response->transaction_id;

$paymentAmount = $verify_response->tran_total;
$paymentCurrency = $verify_response->tran_currency;


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

	$rate = @(float)$verify_response->user_defined->udf1;

	if ($rate) {
		$amount = (float)$paymentAmount / $rate;
	} else {
		$amount = $paymentAmount;
	}

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
		$amount,
		$paymentFee,
		$gatewayModuleName
	);
} else {
	logTransaction($gatewayParams['name'], json_encode($verify_response), $transactionStatus);
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
	$baseUrl = \App::getSystemURL();
	return $baseUrl;
}
