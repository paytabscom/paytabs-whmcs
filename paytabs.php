<?php

/**
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "gatewaymodule" and therefore all functions
 * begin "paytabs_".
 *
 * If your module or third party API does not support a given function, you
 * should not define that function within your module. Only the _config
 * function is required.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


define('PAYTABS_PAYPAGE_VERSION', '2.2.1.2');
require_once 'paytabs_files/paytabs_core2.php';
require_once 'paytabs_files/paytabs_functions.php';

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
function paytabs_MetaData()
{
    return array(
        'DisplayName' => 'PayTabs - Payment Gateway',
        'APIVersion' => '2.0', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
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
 * @return array
 */
function paytabs_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'PayTabs - Think Cashless',
        ),
        'MerchantId' => array(
            'FriendlyName' => 'Profile ID',
            'Type' => 'text',
            'Size' => '35',
        ),
        'MerchantKey' => array(
            'FriendlyName' => 'Server Key',
            'Type' => 'text',
            'Size' => '55',
        ),
        'hide_shipping' => array(
            'FriendlyName' => 'Hide shipping information',
            'Type'         => 'yesno',
        ),
    );
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
function paytabs_link($params)
{
    /** 1. Read required Params */

    // Gateway Configuration Parameters
    $pt = paytabs_getApi($params);

    $_hide_shipping = (bool)$params['hide_shipping'];


    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params['description'];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname  = $params['clientdetails']['lastname'];
    $email     = $params['clientdetails']['email'];
    $address1  = $params['clientdetails']['address1'];
    $address2  = $params['clientdetails']['address2'];
    $city      = $params['clientdetails']['city'];
    $state     = $params['clientdetails']['state'];
    $postcode  = $params['clientdetails']['postcode'];
    $country   = $params['clientdetails']['country'];
    $phone     = $params['clientdetails']['phonenumber'];
    // $phone_cc  = $params['clientdetails']['phonecc'];


    // System Parameters

    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleName = $params['paymentmethod'];
    // $whmcsVersion = 'WHMCS ' . $params['whmcsVersion'];

    // Computed Parameters
    $billing_address = $address1 . ' ' . $address2;
    $callbackUrl = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php';
    $returnUrl = $callbackUrl . '?invoiceid=' . $invoiceId;


    // $products = invoice_products($invoiceId);

    // $items_arr = array_map(function ($p) {
    //     return [
    //         'name' => $p['description'],
    //         'quantity' => '1',
    //         'price' => $p['amount']
    //     ];
    // }, $products);


    /** 2. Fill post array */

    $country = PaytabsHelper::countryGetiso3($country);

    $pt_holder = new PaytabsHolder2();
    $pt_holder->set01PaymentCode('')
        ->set02Transaction('sale', 'ecom')
        ->set03Cart(
            $invoiceId,
            $currencyCode,
            $amount,
            $description
        )
        ->set04CustomerDetails(
            $firstname . ' ' . $lastname,
            $email,
            $phone,
            $billing_address,
            $city,
            $state,
            $country,
            $postcode,
            null
        )
        ->set06HideShipping($_hide_shipping)
        ->set07URLs($returnUrl, null)
        ->set08Lang('en');


    //

    $post_arr = $pt_holder->pt_build();


    /** 3. Send a request to build the pay page */

    $paypage = $pt->create_pay_page($post_arr);

    $success = $paypage->success;
    $message = $paypage->message;
    $payment_url = @$paypage->payment_url;


    /** 4. Display the PayTabs pay button */

    if ($success) {

        $htmlOutput = '<form method="post" action="' . $payment_url . '">';
        $htmlOutput .= '<input type="hidden" value="1" name="temp" />';
        $htmlOutput .= '<input type="submit" value="' . $langPayNow . '" class="btn btn-primary" />';
        $htmlOutput .= '</form>';
    } else {
        $htmlOutput = '<div class="alert alert-danger">' . $message . '</div>';

        paytabs_error_log(json_encode($paypage));
    }

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
function paytabs_refund($params)
{
    // Gateway Configuration Parameters
    $pt = paytabs_getApi($params);

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currencyCode = $params['currency'];
    $cart_id = $params['invoiceid'];


    $pt_refundHolder = new PaytabsRefundHolder();
    $pt_refundHolder
        ->set01RefundInfo($refundAmount, $currencyCode)
        ->set02Transaction($cart_id, $transactionIdToRefund, 'Admin panel');

    $values = $pt_refundHolder->pt_build();

    $refundRes = $pt->refund($values);

    $success = $refundRes->success;
    $message = $refundRes->message;
    $pending_success = $refundRes->pending_success;
    $refundTransactionId = @$refundRes->refund_request_id;
    if (!$refundTransactionId) $refundTransactionId = 0;

    // perform API call to initiate refund and interpret result

    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => $success ? 'success' : 'declined',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $message,
        // Unique Transaction ID for the refund transaction
        'transid' => $refundTransactionId
        // Optional fee amount for the fee value refunded
        // 'fees' => $feeAmount,
    );
}


/**
 * @return Array of products included into the @param invoiceId
 */
function invoice_products($invoiceId)
{
    $command = 'GetInvoice';
    $postData = array(
        'invoiceid' => $invoiceId,
    );

    $results = localAPI($command, $postData);

    $products = $results['items']['item'];

    return $products;
}
