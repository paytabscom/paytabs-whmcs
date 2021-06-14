<?php

function paytabs_error_log($message)
{
    logTransaction('paytabs', $message, 'Failure');
}


function paytabs_getApi($params)
{
    // Gateway Configuration Parameters
    $_endpoint = $params['Endpoint'];
    $_merchant_id = $params['MerchantId'];
    $_merchant_key = $params['MerchantKey'];

    $pt = PaytabsApi::getInstance($_endpoint, $_merchant_id, $_merchant_key);

    return $pt;
}


function paytabs_session_paypage($payment_url = null)
{
    if ($payment_url) {
        $_SESSION['paytabs_paypage_url'] = $payment_url;
        $_SESSION['paytabs_paypage_time'] = time();
    } else {
        $is_sessioned =
            array_key_exists('paytabs_paypage_url', $_SESSION) &&
            array_key_exists('paytabs_paypage_time', $_SESSION);

        if (!$is_sessioned) {
            return false;
        }

        $pp_time = $_SESSION['paytabs_paypage_time'];
        $diff = time() - $pp_time;
        if ($diff > 1 * 60) {
            return false;
        }

        $pp_payment_url = $_SESSION['paytabs_paypage_url'];
        return $pp_payment_url;
    }
}
