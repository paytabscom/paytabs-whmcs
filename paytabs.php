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
        'DisableLocalCredtCardInput' => true,
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
        'Email' => array(
            'FriendlyName' => 'Merchant Email',
            'Type' => 'text',
            'Size' => '35',
        ),
        "SecretKey" => array(
            "FriendlyName" => "Secret Key",
            "Type" => "text",
            "Size" => "55",
        ),
        "Instructions" => array(
            "FriendlyName" => "Payment Instructions",
            "Type" => "textarea",
            "Rows" => "5",
            "Description" => "Do this then do that etc...",
        ),
        "testmode" => array(
            "FriendlyName" => "Test Mode",
            "Type" => "yesno",
            "Description" => "Tick this to test",
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
    $Email = $params['Email'];
    $SecretKey = $params['SecretKey'];
    $testMode = $params['testMode'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
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
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // Computed Parameters
    $billing_address = $address1 . ' ' . $address2;
    $callbackUrl = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php';
    $returnUrl = $callbackUrl . '?invoiceid=' . $invoiceId;

    $products = invoice_products($invoiceId);

    $products_per_title = implode(' || ', array_map(function ($p) {
        return $p['description'];
    }, $products));

    $quantity = implode(' || ', array_map(function ($p) {
        return '1';
    }, $products));

    $unit_price = implode(' || ', array_map(function ($p) {
        return $p['amount'];
    }, $products));


    /** 2. Fill post array */

    $post_arr = [
        'amount'           => $amount,
        'firstname'        => $firstname,
        'lastname'         => $lastname,
        'email'            => $email,
        'billing_address'  => $billing_address,
        'address_shipping' => $billing_address,
        'address1'         => $address1,
        'address2'         => $address2,
        'city'             => $city,
        'state'            => $state,
        'country'          => $country,
        'city_shipping'    => $city,
        'state_shipping'   => $state,
        'country'          => getCountryIsoCode($country),
        'country_shipping' => getCountryIsoCode($country),
        'zipcode'          => $postcode,
        'phone'            => $phone,
        'cc_phone_number'  => getccPhone($country),
        'cc_first_name'    => $firstname,
        'cc_last_name'     => $lastname,
        'phone_number'     => $phone,
        'postal_code'      => $postcode,
        'postal_code_shipping' => $postcode,
        'currency'         => $currencyCode,
        'title'            => $firstname . ' ' . $lastname,
        'products_per_title'     => $products_per_title,
        'description'    => $description,
        'quantity'         => $quantity,
        'unit_price'       => $unit_price,
        'other_charges'    => 0,
        'return_url'       => $returnUrl,
        // 'callback_url'     => $callbackUrl,
        'invoiceid'        => $invoiceId,
        'ip_customer'      => $_SERVER['REMOTE_ADDR'],
        'ip_merchant'      => $_SERVER['SERVER_ADDR'],
        'cms_with_version' => $params['whmcsVersion'],
        'reference_no'     => $invoiceId,
        'site_url'         => $systemUrl,
        'msg_lang'         => 'English'
    ];

    //


    /** 3. Send a request to build the pay page */

    $pt = new paytabs($Email, $SecretKey);

    $r = $pt->create_pay_page($post_arr);


    /** 4. Display the PayTabs pay button */

    $htmlOutput = '<form method="post" action="' . $r->payment_url . '">';
    $htmlOutput .= '<input type="submit" value="' . $langPayNow . '" class="btn btn-primary" />';
    $htmlOutput .= '</form>';

    return $htmlOutput;
}


/**
 * Private functions
 */


class paytabs
{
    const URL_AUTHENTICATION = "https://www.paytabs.com/apiv2/validate_secret_key";
    const PAYPAGE_URL = "https://www.paytabs.com/apiv2/create_pay_page";
    const VERIFY_URL = "https://www.paytabs.com/apiv2/verify_payment";

    private $merchant_email;
    private $secret_key;

    function paytabs($merchant_email, $secret_key)
    {
        $this->merchant_email = $merchant_email;
        $this->secret_key = $secret_key;
    }

    function authentication()
    {
        $obj = json_decode($this->runPost(self::URL_AUTHENTICATION, array("merchant_email" => $this->merchant_email, "secret_key" =>  $this->secret_key)), TRUE);

        if ($obj->response_code == "4000") {
            return TRUE;
        }
        return FALSE;
    }

    function create_pay_page($values)
    {
        $values['merchant_email'] = $this->merchant_email;
        $values['secret_key'] = $this->secret_key;
        $values['ip_customer'] = $_SERVER['REMOTE_ADDR'];
        $values['ip_merchant'] = $_SERVER['SERVER_ADDR'];
        return json_decode($this->runPost(self::PAYPAGE_URL, $values));
    }


    function verify_payment($payment_reference)
    {
        $values['merchant_email'] = $this->merchant_email;
        $values['secret_key'] = $this->secret_key;
        $values['payment_reference'] = $payment_reference;
        return json_decode($this->runPost(self::VERIFY_URL, $values));
    }

    function runPost($url, $fields)
    {
        $fields_string = "";
        foreach ($fields as $key => $value) {
            $fields_string .= $key . '=' . $value . '&';
        }
        $fields_string = rtrim($fields_string, '&');
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_VERBOSE, true);

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }
}

if (!function_exists('getccPhone')) {
    function getccPhone($code)
    {
        $countries = array(
            "AF" => '+93', //array("AFGHANISTAN", "AF", "AFG", "004"),
            "AL" => '+355', //array("ALBANIA", "AL", "ALB", "008"),
            "DZ" => '+213', //array("ALGERIA", "DZ", "DZA", "012"),
            "AS" => '+376', //array("AMERICAN SAMOA", "AS", "ASM", "016"),
            "AD" => '+376', //array("ANDORRA", "AD", "AND", "020"),
            "AO" => '+244', //array("ANGOLA", "AO", "AGO", "024"),
            "AG" => '+1-268', //array("ANTIGUA AND BARBUDA", "AG", "ATG", "028"),
            "AR" => '+54', //array("ARGENTINA", "AR", "ARG", "032"),
            "AM" => '+374', //array("ARMENIA", "AM", "ARM", "051"),
            "AU" => '+61', //array("AUSTRALIA", "AU", "AUS", "036"),
            "AT" => '+43', //array("AUSTRIA", "AT", "AUT", "040"),
            "AZ" => '+994', //array("AZERBAIJAN", "AZ", "AZE", "031"),
            "BS" => '+1-242', //array("BAHAMAS", "BS", "BHS", "044"),
            "BH" => '+973', //array("BAHRAIN", "BH", "BHR", "048"),
            "BD" => '+880', //array("BANGLADESH", "BD", "BGD", "050"),
            "BB" => '1-246', //array("BARBADOS", "BB", "BRB", "052"),
            "BY" => '+375', //array("BELARUS", "BY", "BLR", "112"),
            "BE" => '+32', //array("BELGIUM", "BE", "BEL", "056"),
            "BZ" => '+501', //array("BELIZE", "BZ", "BLZ", "084"),
            "BJ" => '+229', // array("BENIN", "BJ", "BEN", "204"),
            "BT" => '+975', //array("BHUTAN", "BT", "BTN", "064"),
            "BO" => '+591', //array("BOLIVIA", "BO", "BOL", "068"),
            "BA" => '+387', //array("BOSNIA AND HERZEGOVINA", "BA", "BIH", "070"),
            "BW" => '+267', //array("BOTSWANA", "BW", "BWA", "072"),
            "BR" => '+55', //array("BRAZIL", "BR", "BRA", "076"),
            "BN" => '+673', //array("BRUNEI DARUSSALAM", "BN", "BRN", "096"),
            "BG" => '+359', //array("BULGARIA", "BG", "BGR", "100"),
            "BF" => '+226', //array("BURKINA FASO", "BF", "BFA", "854"),
            "BI" => '+257', //array("BURUNDI", "BI", "BDI", "108"),
            "KH" => '+855', //array("CAMBODIA", "KH", "KHM", "116"),
            "CA" => '+1', //array("CANADA", "CA", "CAN", "124"),
            "CV" => '+238', //array("CAPE VERDE", "CV", "CPV", "132"),
            "CF" => '+236', //array("CENTRAL AFRICAN REPUBLIC", "CF", "CAF", "140"),
            "CM" => '+237', //array("CENTRAL AFRICAN REPUBLIC", "CF", "CAF", "140"),
            "TD" => '+235', //array("CHAD", "TD", "TCD", "148"),
            "CL" => '+56', //array("CHILE", "CL", "CHL", "152"),
            "CN" => '+86', //array("CHINA", "CN", "CHN", "156"),
            "CO" => '+57', //array("COLOMBIA", "CO", "COL", "170"),
            "KM" => '+269', //array("COMOROS", "KM", "COM", "174"),
            "CG" => '+242', //array("CONGO", "CG", "COG", "178"),
            "CR" => '+506', //array("COSTA RICA", "CR", "CRI", "188"),
            "CI" => '+225', //array("COTE D'IVOIRE", "CI", "CIV", "384"),
            "HR" => '+385', //array("CROATIA (local name: Hrvatska)", "HR", "HRV", "191"),
            "CU" => '+53', //array("CUBA", "CU", "CUB", "192"),
            "CY" => '+357', //array("CYPRUS", "CY", "CYP", "196"),
            "CZ" => '+420', //array("CZECH REPUBLIC", "CZ", "CZE", "203"),
            "DK" => '+45', //array("DENMARK", "DK", "DNK", "208"),
            "DJ" => '+253', //array("DJIBOUTI", "DJ", "DJI", "262"),
            "DM" => '+1-767', //array("DOMINICA", "DM", "DMA", "212"),
            "DO" => '+1-809', //array("DOMINICAN REPUBLIC", "DO", "DOM", "214"),
            "EC" => '+593', //array("ECUADOR", "EC", "ECU", "218"),
            "EG" => '+20', //array("EGYPT", "EG", "EGY", "818"),
            "SV" => '+503', //array("EL SALVADOR", "SV", "SLV", "222"),
            "GQ" => '+240', //array("EQUATORIAL GUINEA", "GQ", "GNQ", "226"),
            "ER" => '+291', //array("ERITREA", "ER", "ERI", "232"),
            "EE" => '+372', //array("ESTONIA", "EE", "EST", "233"),
            "ET" => '+251', //array("ETHIOPIA", "ET", "ETH", "210"),
            "FJ" => '+679', //array("FIJI", "FJ", "FJI", "242"),
            "FI" => '+358', //array("FINLAND", "FI", "FIN", "246"),
            "FR" => '+33', //array("FRANCE", "FR", "FRA", "250"),
            "GA" => '+241', //array("GABON", "GA", "GAB", "266"),
            "GM" => '+220', //array("GAMBIA", "GM", "GMB", "270"),
            "GE" => '+995', //array("GEORGIA", "GE", "GEO", "268"),
            "DE" => '+49', //array("GERMANY", "DE", "DEU", "276"),
            "GH" => '+233', //array("GHANA", "GH", "GHA", "288"),
            "GR" => '+30', //array("GREECE", "GR", "GRC", "300"),
            "GD" => '+1-473', //array("GRENADA", "GD", "GRD", "308"),
            "GT" => '+502', //array("GUATEMALA", "GT", "GTM", "320"),
            "GN" => '+224', //array("GUINEA", "GN", "GIN", "324"),
            "GW" => '+245', //array("GUINEA-BISSAU", "GW", "GNB", "624"),
            "GY" => '+592', //array("GUYANA", "GY", "GUY", "328"),
            "HT" => '+509', //array("HAITI", "HT", "HTI", "332"),
            "HN" => '+504', //array("HONDURAS", "HN", "HND", "340"),
            "HK" => '+852', //array("HONG KONG", "HK", "HKG", "344"),
            "HU" => '+36', //array("HUNGARY", "HU", "HUN", "348"),
            "IS" => '+354', //array("ICELAND", "IS", "ISL", "352"),
            "IN" => '+91', //array("INDIA", "IN", "IND", "356"),
            "ID" => '+62', //array("INDONESIA", "ID", "IDN", "360"),
            "IR" => '+98', //array("IRAN, ISLAMIC REPUBLIC OF", "IR", "IRN", "364"),
            "IQ" => '+964', //array("IRAQ", "IQ", "IRQ", "368"),
            "IE" => '+353', //array("IRELAND", "IE", "IRL", "372"),
            "IL" => '+972', //array("ISRAEL", "IL", "ISR", "376"),
            "IT" => '+39', //array("ITALY", "IT", "ITA", "380"),
            "JM" => '+1-876', //array("JAMAICA", "JM", "JAM", "388"),
            "JP" => '+81', //array("JAPAN", "JP", "JPN", "392"),
            "JO" => '+962', //array("JORDAN", "JO", "JOR", "400"),
            "KZ" => '+7', //array("KAZAKHSTAN", "KZ", "KAZ", "398"),
            "KE" => '+254', //array("KENYA", "KE", "KEN", "404"),
            "KI" => '+686', //array("KIRIBATI", "KI", "KIR", "296"),
            "KP" => '+850', //array("KOREA, DEMOCRATIC PEOPLE'S REPUBLIC OF", "KP", "PRK", "408"),
            "KR" => '+82', //array("KOREA, REPUBLIC OF", "KR", "KOR", "410"),
            "KW" => '+965', //array("KUWAIT", "KW", "KWT", "414"),
            "KG" => '+996', //array("KYRGYZSTAN", "KG", "KGZ", "417"),
            "LA" => '+856', //array("LAO PEOPLE'S DEMOCRATIC REPUBLIC", "LA", "LAO", "418"),
            "LV" => '+371', //array("LATVIA", "LV", "LVA", "428"),
            "LB" => '+961', //array("LEBANON", "LB", "LBN", "422"),
            "LS" => '+266', //array("LESOTHO", "LS", "LSO", "426"),
            "LR" => '+231', //array("LIBERIA", "LR", "LBR", "430"),
            "LY" => '+218', //array("LIBYAN ARAB JAMAHIRIYA", "LY", "LBY", "434"),
            "LI" => '+423', //array("LIECHTENSTEIN", "LI", "LIE", "438"),
            "LU" => '+352', //array("LUXEMBOURG", "LU", "LUX", "442"),
            "MO" => '+389', //array("MACAU", "MO", "MAC", "446"),
            "MG" => '+261', //array("MADAGASCAR", "MG", "MDG", "450"),
            "MW" => '+265', //array("MALAWI", "MW", "MWI", "454"),
            "MY" => '+60', //array("MALAYSIA", "MY", "MYS", "458"),     
            "MX" => '+52', //array("MEXICO", "MX", "MEX", "484"),
            "MC" => '+377', //array("MONACO", "MC", "MCO", "492"),
            "MA" => '+212', //array("MOROCCO", "MA", "MAR", "504")
            "NP" => '+977', //array("NEPAL", "NP", "NPL", "524"),
            "NL" => '+31', //array("NETHERLANDS", "NL", "NLD", "528"),
            "NZ" => '+64', //array("NEW ZEALAND", "NZ", "NZL", "554"),
            "NI" => '+505', //array("NICARAGUA", "NI", "NIC", "558"),
            "NE" => '+227', //array("NIGER", "NE", "NER", "562"),
            "NG" => '+234', //array("NIGERIA", "NG", "NGA", "566"),
            "NO" => '+47', //array("NORWAY", "NO", "NOR", "578"),
            "OM" => '+968', //array("OMAN", "OM", "OMN", "512"),
            "PK" => '+92', //array("PAKISTAN", "PK", "PAK", "586"),
            "PA" => '+507', //array("PANAMA", "PA", "PAN", "591"),
            "PG" => '+675', //array("PAPUA NEW GUINEA", "PG", "PNG", "598"),
            "PY" => '+595', // array("PARAGUAY", "PY", "PRY", "600"),
            "PE" => '+51', // array("PERU", "PE", "PER", "604"),
            "PH" => '+63', // array("PHILIPPINES", "PH", "PHL", "608"),
            "PL" => '48', //array("POLAND", "PL", "POL", "616"),
            "PT" => '+351', //array("PORTUGAL", "PT", "PRT", "620"),
            "QA" => '+974', //array("QATAR", "QA", "QAT", "634"),
            "RU" => '+7', //array("RUSSIAN FEDERATION", "RU", "RUS", "643"),
            "RW" => '+250', //array("RWANDA", "RW", "RWA", "646"),
            "SA" => '+966', //array("SAUDI ARABIA", "SA", "SAU", "682"),
            "SN" => '+221', //array("SENEGAL", "SN", "SEN", "686"),
            "SG" => '+65', //array("SINGAPORE", "SG", "SGP", "702"),
            "SK" => '+421', //array("SLOVAKIA (Slovak Republic)", "SK", "SVK", "703"),
            "SI" => '+386', //array("SLOVENIA", "SI", "SVN", "705"),
            "ZA" => '+27', //array("SOUTH AFRICA", "ZA", "ZAF", "710"),
            "ES" => '+34', //array("SPAIN", "ES", "ESP", "724"),
            "LK" => '+94', //array("SRI LANKA", "LK", "LKA", "144"),
            "SD" => '+249', //array("SUDAN", "SD", "SDN", "736"),
            "SZ" => '+268', //array("SWAZILAND", "SZ", "SWZ", "748"),
            "SE" => '+46', //array("SWEDEN", "SE", "SWE", "752"),
            "CH" => '+41', //array("SWITZERLAND", "CH", "CHE", "756"),
            "SY" => '+963', //array("SYRIAN ARAB REPUBLIC", "SY", "SYR", "760"),
            "TZ" => '+255', //array("TANZANIA, UNITED REPUBLIC OF", "TZ", "TZA", "834"),
            "TH" => '+66', //array("THAILAND", "TH", "THA", "764"),
            "TG" => '+228', //array("TOGO", "TG", "TGO", "768"),
            "TO" => '+676', //array("TONGA", "TO", "TON", "776"),
            "TN" => '+216', //array("TUNISIA", "TN", "TUN", "788"),
            "TR" => '+90', //array("TURKEY", "TR", "TUR", "792"),
            "TM" => '+993', //array("TURKMENISTAN", "TM", "TKM", "795"),
            "UA" => '+380', //array("UKRAINE", "UA", "UKR", "804"),
            "AE" => '+971', //array("UNITED ARAB EMIRATES", "AE", "ARE", "784"),
            "GB" => '+44', //array("UNITED KINGDOM", "GB", "GBR", "826"),
            "US" => '+1' //array("UNITED STATES", "US", "USA", "840"),

        );

        return $countries[$code];
    }
}

if (!function_exists('getCountryIsoCode')) {
    /* Get country code function */
    function getCountryIsoCode($code)
    {
        $countries = array(
            "AF" => array("AFGHANISTAN", "AF", "AFG", "004"),
            "AL" => array("ALBANIA", "AL", "ALB", "008"),
            "DZ" => array("ALGERIA", "DZ", "DZA", "012"),
            "AS" => array("AMERICAN SAMOA", "AS", "ASM", "016"),
            "AD" => array("ANDORRA", "AD", "AND", "020"),
            "AO" => array("ANGOLA", "AO", "AGO", "024"),
            "AI" => array("ANGUILLA", "AI", "AIA", "660"),
            "AQ" => array("ANTARCTICA", "AQ", "ATA", "010"),
            "AG" => array("ANTIGUA AND BARBUDA", "AG", "ATG", "028"),
            "AR" => array("ARGENTINA", "AR", "ARG", "032"),
            "AM" => array("ARMENIA", "AM", "ARM", "051"),
            "AW" => array("ARUBA", "AW", "ABW", "533"),
            "AU" => array("AUSTRALIA", "AU", "AUS", "036"),
            "AT" => array("AUSTRIA", "AT", "AUT", "040"),
            "AZ" => array("AZERBAIJAN", "AZ", "AZE", "031"),
            "BS" => array("BAHAMAS", "BS", "BHS", "044"),
            "BH" => array("BAHRAIN", "BH", "BHR", "048"),
            "BD" => array("BANGLADESH", "BD", "BGD", "050"),
            "BB" => array("BARBADOS", "BB", "BRB", "052"),
            "BY" => array("BELARUS", "BY", "BLR", "112"),
            "BE" => array("BELGIUM", "BE", "BEL", "056"),
            "BZ" => array("BELIZE", "BZ", "BLZ", "084"),
            "BJ" => array("BENIN", "BJ", "BEN", "204"),
            "BM" => array("BERMUDA", "BM", "BMU", "060"),
            "BT" => array("BHUTAN", "BT", "BTN", "064"),
            "BO" => array("BOLIVIA", "BO", "BOL", "068"),
            "BA" => array("BOSNIA AND HERZEGOVINA", "BA", "BIH", "070"),
            "BW" => array("BOTSWANA", "BW", "BWA", "072"),
            "BV" => array("BOUVET ISLAND", "BV", "BVT", "074"),
            "BR" => array("BRAZIL", "BR", "BRA", "076"),
            "IO" => array("BRITISH INDIAN OCEAN TERRITORY", "IO", "IOT", "086"),
            "BN" => array("BRUNEI DARUSSALAM", "BN", "BRN", "096"),
            "BG" => array("BULGARIA", "BG", "BGR", "100"),
            "BF" => array("BURKINA FASO", "BF", "BFA", "854"),
            "BI" => array("BURUNDI", "BI", "BDI", "108"),
            "KH" => array("CAMBODIA", "KH", "KHM", "116"),
            "CM" => array("CAMEROON", "CM", "CMR", "120"),
            "CA" => array("CANADA", "CA", "CAN", "124"),
            "CV" => array("CAPE VERDE", "CV", "CPV", "132"),
            "KY" => array("CAYMAN ISLANDS", "KY", "CYM", "136"),
            "CF" => array("CENTRAL AFRICAN REPUBLIC", "CF", "CAF", "140"),
            "TD" => array("CHAD", "TD", "TCD", "148"),
            "CL" => array("CHILE", "CL", "CHL", "152"),
            "CN" => array("CHINA", "CN", "CHN", "156"),
            "CX" => array("CHRISTMAS ISLAND", "CX", "CXR", "162"),
            "CC" => array("COCOS (KEELING) ISLANDS", "CC", "CCK", "166"),
            "CO" => array("COLOMBIA", "CO", "COL", "170"),
            "KM" => array("COMOROS", "KM", "COM", "174"),
            "CG" => array("CONGO", "CG", "COG", "178"),
            "CK" => array("COOK ISLANDS", "CK", "COK", "184"),
            "CR" => array("COSTA RICA", "CR", "CRI", "188"),
            "CI" => array("COTE D'IVOIRE", "CI", "CIV", "384"),
            "HR" => array("CROATIA (local name: Hrvatska)", "HR", "HRV", "191"),
            "CU" => array("CUBA", "CU", "CUB", "192"),
            "CY" => array("CYPRUS", "CY", "CYP", "196"),
            "CZ" => array("CZECH REPUBLIC", "CZ", "CZE", "203"),
            "DK" => array("DENMARK", "DK", "DNK", "208"),
            "DJ" => array("DJIBOUTI", "DJ", "DJI", "262"),
            "DM" => array("DOMINICA", "DM", "DMA", "212"),
            "DO" => array("DOMINICAN REPUBLIC", "DO", "DOM", "214"),
            "TL" => array("EAST TIMOR", "TL", "TLS", "626"),
            "EC" => array("ECUADOR", "EC", "ECU", "218"),
            "EG" => array("EGYPT", "EG", "EGY", "818"),
            "SV" => array("EL SALVADOR", "SV", "SLV", "222"),
            "GQ" => array("EQUATORIAL GUINEA", "GQ", "GNQ", "226"),
            "ER" => array("ERITREA", "ER", "ERI", "232"),
            "EE" => array("ESTONIA", "EE", "EST", "233"),
            "ET" => array("ETHIOPIA", "ET", "ETH", "210"),
            "FK" => array("FALKLAND ISLANDS (MALVINAS)", "FK", "FLK", "238"),
            "FO" => array("FAROE ISLANDS", "FO", "FRO", "234"),
            "FJ" => array("FIJI", "FJ", "FJI", "242"),
            "FI" => array("FINLAND", "FI", "FIN", "246"),
            "FR" => array("FRANCE", "FR", "FRA", "250"),
            "FX" => array("FRANCE, METROPOLITAN", "FX", "FXX", "249"),
            "GF" => array("FRENCH GUIANA", "GF", "GUF", "254"),
            "PF" => array("FRENCH POLYNESIA", "PF", "PYF", "258"),
            "TF" => array("FRENCH SOUTHERN TERRITORIES", "TF", "ATF", "260"),
            "GA" => array("GABON", "GA", "GAB", "266"),
            "GM" => array("GAMBIA", "GM", "GMB", "270"),
            "GE" => array("GEORGIA", "GE", "GEO", "268"),
            "DE" => array("GERMANY", "DE", "DEU", "276"),
            "GH" => array("GHANA", "GH", "GHA", "288"),
            "GI" => array("GIBRALTAR", "GI", "GIB", "292"),
            "GR" => array("GREECE", "GR", "GRC", "300"),
            "GL" => array("GREENLAND", "GL", "GRL", "304"),
            "GD" => array("GRENADA", "GD", "GRD", "308"),
            "GP" => array("GUADELOUPE", "GP", "GLP", "312"),
            "GU" => array("GUAM", "GU", "GUM", "316"),
            "GT" => array("GUATEMALA", "GT", "GTM", "320"),
            "GN" => array("GUINEA", "GN", "GIN", "324"),
            "GW" => array("GUINEA-BISSAU", "GW", "GNB", "624"),
            "GY" => array("GUYANA", "GY", "GUY", "328"),
            "HT" => array("HAITI", "HT", "HTI", "332"),
            "HM" => array("HEARD ISLAND & MCDONALD ISLANDS", "HM", "HMD", "334"),
            "HN" => array("HONDURAS", "HN", "HND", "340"),
            "HK" => array("HONG KONG", "HK", "HKG", "344"),
            "HU" => array("HUNGARY", "HU", "HUN", "348"),
            "IS" => array("ICELAND", "IS", "ISL", "352"),
            "IN" => array("INDIA", "IN", "IND", "356"),
            "ID" => array("INDONESIA", "ID", "IDN", "360"),
            "IR" => array("IRAN, ISLAMIC REPUBLIC OF", "IR", "IRN", "364"),
            "IQ" => array("IRAQ", "IQ", "IRQ", "368"),
            "IE" => array("IRELAND", "IE", "IRL", "372"),
            "IL" => array("ISRAEL", "IL", "ISR", "376"),
            "IT" => array("ITALY", "IT", "ITA", "380"),
            "JM" => array("JAMAICA", "JM", "JAM", "388"),
            "JP" => array("JAPAN", "JP", "JPN", "392"),
            "JO" => array("JORDAN", "JO", "JOR", "400"),
            "KZ" => array("KAZAKHSTAN", "KZ", "KAZ", "398"),
            "KE" => array("KENYA", "KE", "KEN", "404"),
            "KI" => array("KIRIBATI", "KI", "KIR", "296"),
            "KP" => array("KOREA, DEMOCRATIC PEOPLE'S REPUBLIC OF", "KP", "PRK", "408"),
            "KR" => array("KOREA, REPUBLIC OF", "KR", "KOR", "410"),
            "KW" => array("KUWAIT", "KW", "KWT", "414"),
            "KG" => array("KYRGYZSTAN", "KG", "KGZ", "417"),
            "LA" => array("LAO PEOPLE'S DEMOCRATIC REPUBLIC", "LA", "LAO", "418"),
            "LV" => array("LATVIA", "LV", "LVA", "428"),
            "LB" => array("LEBANON", "LB", "LBN", "422"),
            "LS" => array("LESOTHO", "LS", "LSO", "426"),
            "LR" => array("LIBERIA", "LR", "LBR", "430"),
            "LY" => array("LIBYAN ARAB JAMAHIRIYA", "LY", "LBY", "434"),
            "LI" => array("LIECHTENSTEIN", "LI", "LIE", "438"),
            "LT" => array("LITHUANIA", "LT", "LTU", "440"),
            "LU" => array("LUXEMBOURG", "LU", "LUX", "442"),
            "MO" => array("MACAU", "MO", "MAC", "446"),
            "MK" => array("MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF", "MK", "MKD", "807"),
            "MG" => array("MADAGASCAR", "MG", "MDG", "450"),
            "MW" => array("MALAWI", "MW", "MWI", "454"),
            "MY" => array("MALAYSIA", "MY", "MYS", "458"),
            "MV" => array("MALDIVES", "MV", "MDV", "462"),
            "ML" => array("MALI", "ML", "MLI", "466"),
            "MT" => array("MALTA", "MT", "MLT", "470"),
            "MH" => array("MARSHALL ISLANDS", "MH", "MHL", "584"),
            "MQ" => array("MARTINIQUE", "MQ", "MTQ", "474"),
            "MR" => array("MAURITANIA", "MR", "MRT", "478"),
            "MU" => array("MAURITIUS", "MU", "MUS", "480"),
            "YT" => array("MAYOTTE", "YT", "MYT", "175"),
            "MX" => array("MEXICO", "MX", "MEX", "484"),
            "FM" => array("MICRONESIA, FEDERATED STATES OF", "FM", "FSM", "583"),
            "MD" => array("MOLDOVA, REPUBLIC OF", "MD", "MDA", "498"),
            "MC" => array("MONACO", "MC", "MCO", "492"),
            "MN" => array("MONGOLIA", "MN", "MNG", "496"),
            "MS" => array("MONTSERRAT", "MS", "MSR", "500"),
            "MA" => array("MOROCCO", "MA", "MAR", "504"),
            "MZ" => array("MOZAMBIQUE", "MZ", "MOZ", "508"),
            "MM" => array("MYANMAR", "MM", "MMR", "104"),
            "NA" => array("NAMIBIA", "NA", "NAM", "516"),
            "NR" => array("NAURU", "NR", "NRU", "520"),
            "NP" => array("NEPAL", "NP", "NPL", "524"),
            "NL" => array("NETHERLANDS", "NL", "NLD", "528"),
            "AN" => array("NETHERLANDS ANTILLES", "AN", "ANT", "530"),
            "NC" => array("NEW CALEDONIA", "NC", "NCL", "540"),
            "NZ" => array("NEW ZEALAND", "NZ", "NZL", "554"),
            "NI" => array("NICARAGUA", "NI", "NIC", "558"),
            "NE" => array("NIGER", "NE", "NER", "562"),
            "NG" => array("NIGERIA", "NG", "NGA", "566"),
            "NU" => array("NIUE", "NU", "NIU", "570"),
            "NF" => array("NORFOLK ISLAND", "NF", "NFK", "574"),
            "MP" => array("NORTHERN MARIANA ISLANDS", "MP", "MNP", "580"),
            "NO" => array("NORWAY", "NO", "NOR", "578"),
            "OM" => array("OMAN", "OM", "OMN", "512"),
            "PK" => array("PAKISTAN", "PK", "PAK", "586"),
            "PW" => array("PALAU", "PW", "PLW", "585"),
            "PA" => array("PANAMA", "PA", "PAN", "591"),
            "PG" => array("PAPUA NEW GUINEA", "PG", "PNG", "598"),
            "PY" => array("PARAGUAY", "PY", "PRY", "600"),
            "PE" => array("PERU", "PE", "PER", "604"),
            "PH" => array("PHILIPPINES", "PH", "PHL", "608"),
            "PN" => array("PITCAIRN", "PN", "PCN", "612"),
            "PL" => array("POLAND", "PL", "POL", "616"),
            "PT" => array("PORTUGAL", "PT", "PRT", "620"),
            "PR" => array("PUERTO RICO", "PR", "PRI", "630"),
            "QA" => array("QATAR", "QA", "QAT", "634"),
            "RE" => array("REUNION", "RE", "REU", "638"),
            "RO" => array("ROMANIA", "RO", "ROU", "642"),
            "RU" => array("RUSSIAN FEDERATION", "RU", "RUS", "643"),
            "RW" => array("RWANDA", "RW", "RWA", "646"),
            "KN" => array("SAINT KITTS AND NEVIS", "KN", "KNA", "659"),
            "LC" => array("SAINT LUCIA", "LC", "LCA", "662"),
            "VC" => array("SAINT VINCENT AND THE GRENADINES", "VC", "VCT", "670"),
            "WS" => array("SAMOA", "WS", "WSM", "882"),
            "SM" => array("SAN MARINO", "SM", "SMR", "674"),
            "ST" => array("SAO TOME AND PRINCIPE", "ST", "STP", "678"),
            "SA" => array("SAUDI ARABIA", "SA", "SAU", "682"),
            "SN" => array("SENEGAL", "SN", "SEN", "686"),
            "RS" => array("SERBIA", "RS", "SRB", "688"),
            "SC" => array("SEYCHELLES", "SC", "SYC", "690"),
            "SL" => array("SIERRA LEONE", "SL", "SLE", "694"),
            "SG" => array("SINGAPORE", "SG", "SGP", "702"),
            "SK" => array("SLOVAKIA (Slovak Republic)", "SK", "SVK", "703"),
            "SI" => array("SLOVENIA", "SI", "SVN", "705"),
            "SB" => array("SOLOMON ISLANDS", "SB", "SLB", "90"),
            "SO" => array("SOMALIA", "SO", "SOM", "706"),
            "ZA" => array("SOUTH AFRICA", "ZA", "ZAF", "710"),
            "ES" => array("SPAIN", "ES", "ESP", "724"),
            "LK" => array("SRI LANKA", "LK", "LKA", "144"),
            "SH" => array("SAINT HELENA", "SH", "SHN", "654"),
            "PM" => array("SAINT PIERRE AND MIQUELON", "PM", "SPM", "666"),
            "SD" => array("SUDAN", "SD", "SDN", "736"),
            "SR" => array("SURINAME", "SR", "SUR", "740"),
            "SJ" => array("SVALBARD AND JAN MAYEN ISLANDS", "SJ", "SJM", "744"),
            "SZ" => array("SWAZILAND", "SZ", "SWZ", "748"),
            "SE" => array("SWEDEN", "SE", "SWE", "752"),
            "CH" => array("SWITZERLAND", "CH", "CHE", "756"),
            "SY" => array("SYRIAN ARAB REPUBLIC", "SY", "SYR", "760"),
            "TW" => array("TAIWAN, PROVINCE OF CHINA", "TW", "TWN", "158"),
            "TJ" => array("TAJIKISTAN", "TJ", "TJK", "762"),
            "TZ" => array("TANZANIA, UNITED REPUBLIC OF", "TZ", "TZA", "834"),
            "TH" => array("THAILAND", "TH", "THA", "764"),
            "TG" => array("TOGO", "TG", "TGO", "768"),
            "TK" => array("TOKELAU", "TK", "TKL", "772"),
            "TO" => array("TONGA", "TO", "TON", "776"),
            "TT" => array("TRINIDAD AND TOBAGO", "TT", "TTO", "780"),
            "TN" => array("TUNISIA", "TN", "TUN", "788"),
            "TR" => array("TURKEY", "TR", "TUR", "792"),
            "TM" => array("TURKMENISTAN", "TM", "TKM", "795"),
            "TC" => array("TURKS AND CAICOS ISLANDS", "TC", "TCA", "796"),
            "TV" => array("TUVALU", "TV", "TUV", "798"),
            "UG" => array("UGANDA", "UG", "UGA", "800"),
            "UA" => array("UKRAINE", "UA", "UKR", "804"),
            "AE" => array("UNITED ARAB EMIRATES", "AE", "ARE", "784"),
            "GB" => array("UNITED KINGDOM", "GB", "GBR", "826"),
            "US" => array("UNITED STATES", "US", "USA", "840"),
            "UM" => array("UNITED STATES MINOR OUTLYING ISLANDS", "UM", "UMI", "581"),
            "UY" => array("URUGUAY", "UY", "URY", "858"),
            "UZ" => array("UZBEKISTAN", "UZ", "UZB", "860"),
            "VU" => array("VANUATU", "VU", "VUT", "548"),
            "VA" => array("VATICAN CITY STATE (HOLY SEE)", "VA", "VAT", "336"),
            "VE" => array("VENEZUELA", "VE", "VEN", "862"),
            "VN" => array("VIET NAM", "VN", "VNM", "704"),
            "VG" => array("VIRGIN ISLANDS (BRITISH)", "VG", "VGB", "92"),
            "VI" => array("VIRGIN ISLANDS (U.S.)", "VI", "VIR", "850"),
            "WF" => array("WALLIS AND FUTUNA ISLANDS", "WF", "WLF", "876"),
            "EH" => array("WESTERN SAHARA", "EH", "ESH", "732"),
            "YE" => array("YEMEN", "YE", "YEM", "887"),
            "YU" => array("YUGOSLAVIA", "YU", "YUG", "891"),
            "ZR" => array("ZAIRE", "ZR", "ZAR", "180"),
            "ZM" => array("ZAMBIA", "ZM", "ZMB", "894"),
            "ZW" => array("ZIMBABWE", "ZW", "ZWE", "716"),
        );

        return $countries[$code][2];
    }
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
