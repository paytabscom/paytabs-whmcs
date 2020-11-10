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
