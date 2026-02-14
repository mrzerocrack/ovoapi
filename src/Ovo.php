<?php
namespace Namdevel;
/*
@ Unofficial Ovo API PHP Class
@ Author : namdevel
@ Created at 04-03-2020 14:26
@ Last Modified at 04-03-2022 13:21
*/
class Ovo
{
    const BASE_API = "https://api.ovo.id";
    const AGW_API = "https://agw.ovo.id";
    const AWS_API = "https://api.cp1.ovo.id";
    
    const os = "Android";
    const app_version = "3.153.0";
    const client_id = "ovo_android";
    const channel_code = "ovo_android";
    const user_agent = "OVO/3.153.0 Android";
    const device_brand = "Android";
    const device_model = "Android";
    
    /*
    @ Device ID (UUIDV4)
    @ Generated from self::generateUUIDV4();
    */
    const device_id = "6AA4E427-D1B4-4B7E-9C22-F4C0F86F2CFD";
    
    /*
    @ Push Notification ID (SHA256 Hash)
    @ Generated from self::generateRandomSHA256();
    */
    const push_notification_id = "e35f5a9fc1b61d0ab0c83ee5ca05ce155f82dcffee0605f1c70de38e662db362";
    
    protected $auth_token, $hmac_hash, $hmac_hash_random;
    
    public function __construct($auth_token = false)
    {
        $this->auth_token = $auth_token;
    }

    /*
    @ config
    @ runtime override from environment
    */
    protected function config($env_key, $fallback)
    {
        $value = getenv($env_key);

        if ($value === false || $value === '') {
            return $fallback;
        }

        return $value;
    }

    protected function appVersion()
    {
        return $this->config('OVOID_APP_VERSION', self::app_version);
    }

    protected function clientId()
    {
        return $this->config('OVOID_CLIENT_ID', self::client_id);
    }

    protected function channelCode()
    {
        return $this->config('OVOID_CHANNEL_CODE', self::channel_code);
    }

    protected function deviceId()
    {
        return $this->config('OVOID_DEVICE_ID', self::device_id);
    }

    protected function pushNotificationId()
    {
        return $this->config('OVOID_PUSH_NOTIFICATION_ID', self::push_notification_id);
    }

    protected function os()
    {
        return $this->config('OVOID_OS', self::os);
    }

    protected function userAgent()
    {
        return $this->config('OVOID_USER_AGENT', self::user_agent);
    }

    protected function deviceBrand()
    {
        return $this->config('OVOID_DEVICE_BRAND', self::device_brand);
    }

    protected function deviceModel()
    {
        return $this->config('OVOID_DEVICE_MODEL', self::device_model);
    }
    
    /*
    @ generateUUIDV4
    @ generate random UUIDV4 for device ID
    */
    public function generateUUIDV4()
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return strtoupper(vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($data), 4)));
    }
    
    /*
    @ generateRandomSHA256
    @ generate random SHA256 hash for push notification ID
    */
    public function generateRandomSHA256()
    {
        return hash_hmac("sha256", time(), "ovo-apps");
    }
    
    /*
    @ headers
    @ OVO cutsom headers
    */
    protected function headers($bearer = false)
    {
        $headers = array(
            'content-type: application/json',
            'accept: */*',
            'app-version: ' . $this->appVersion(),
            'client-id: ' . $this->clientId(),
            'device-id: ' . $this->deviceId(),
            'os: ' . $this->os(),
            'user-agent: ' . $this->userAgent()
        );
        
        if ($this->auth_token) {
            $authorization = trim($bearer . ' ' . $this->auth_token);
            array_push($headers, 'authorization: ' . $authorization);
        }
        
        return $headers;
    }

    protected function headersForFormRequest($extra_headers = array(), $bearer = false)
    {
        $headers = $this->headers($bearer);
        $headers[0] = 'content-type: application/x-www-form-urlencoded';

        foreach ($extra_headers as $header_name => $header_value) {
            array_push($headers, $header_name . ': ' . $header_value);
        }

        return $headers;
    }
    
    /*
    @ sendOtp
    @ param (string phone_number)
    @ AGW ENDPOINT POST("/v3/user/accounts/otp")
    */
    public function sendOtp($phone_number)
    {
        $field = array(
            'msisdn' => $phone_number,
            'device_id' => $this->deviceId(),
            'otp' => array(
                'locale' => 'EN',
                'sms_hash' => 'abc'
            ),
            'channel_code' => $this->channelCode()
        );
        
        return self::request(self::AGW_API . '/v3/user/accounts/otp', $field, $this->headers());
    }
    
    /*
    @ OTPVerify
    @ param (string phone_number, string otp_ref_id, string otp_code)
    @ AGW ENDPOINT POST("/v3/user/accounts/otp/validation")
    */
    public function OTPVerify($phone_number, $otp_ref_id, $otp_code)
    {
        $field = array(
            'channel_code' => $this->channelCode(),
            'otp' => array(
                'otp_ref_id' => $otp_ref_id,
                'otp' => $otp_code,
                'type' => 'LOGIN'
            ),
            'msisdn' => $phone_number,
            'device_id' => $this->deviceId()
        );
        
        return self::request(self::AGW_API . '/v3/user/accounts/otp/validation', $field, $this->headers());
    }
    
    /*
    @ getAuthToken
    @ param (string phone_number, string otp_ref_id, string otp_token, string security_code)
    @ AGW ENDPOINT POST("/v3/user/accounts/login")
    */
    public function getAuthToken($phone_number, $otp_ref_id, $otp_token, $security_code)
    {
        $field = array(
            'msisdn' => $phone_number,
            'device_id' => $this->deviceId(),
            'push_notification_id' => $this->pushNotificationId(),
            'credentials' => array(
                'otp_token' => $otp_token,
                'password' => array(
                    'value' => $this->hashPassword($phone_number, $otp_ref_id, $security_code),
                    'format' => 'rsa'
                )
            ),
            'channel_code' => $this->channelCode()
        );
        
        return self::request(self::AGW_API . '/v3/user/accounts/login', $field, $this->headers());
    }
    
    /*
    @ getPublicKeys
    @ AGW ENDPOINT GET("/v3/user/public_keys")
    */
    public function getPublicKeys()
    {
        return self::request(self::AGW_API . '/v3/user/public_keys', false, $this->headers());
    }
    
    /*
    @ getLastTransactions
    @ param (int limit)
    @ BASE ENDPOINT GET("/wallet/transaction/last")
    */
    public function getLastTransactions($limit = 5)
    {
        return self::request(self::BASE_API . '/wallet/transaction/last?limit=' . $limit . '&transaction_type=TRANSFER&transaction_type=EXTERNAL%20TRANSFER', false, $this->headers());
    }
    
    /*
    @ getTransactionDetails
    @ param (string merchant_id. string merchant_invoice)
    @ BASE ENDPOINT GET("/wallet/transaction/{merchant_id}/{merchant_invoice}")
    */
    public function getTransactionDetails($merchant_id, $merchant_invoice)
    {
        return self::request(self::BASE_API . '/wallet/transaction/' . $merchant_id . '/' . $merchant_invoice . '', false, $this->headers());
    }
    
    /*
    @ getFavoriteTransfer
    @ AWS ENDPOINT GET("/user-profiling/favorite-transfer")
    */
    public function getFavoriteTransfer()
    {
        return self::request(self::AWS_API . '/user-profiling/favorite-transfer', false, $this->headers());
    }
    
    /*
    @ hashPassword
    @ param (string phone_number, string otp_ref_id, string security_code)
    @ return base64_encoded string
    */
    protected function hashPassword($phone_number, $otp_ref_id, $security_code)
    {
        $rsa_key = $this->parse($this->getPublicKeys(), true)['data']['keys'][0]['key'];
        $data = join("|", array(
            'LOGIN',
            $security_code,
            time(),
            $this->deviceId(),
            $phone_number,
            $this->deviceId(),
            $otp_ref_id
        ));
        openssl_public_encrypt($data, $output, $rsa_key);
        return base64_encode($output);
    }
    
    /*
    @ getEmail
    @ return account email detail
    */
    public function getEmail()
    {
        return self::request(self::AGW_API . '/v3/user/accounts/email', false, $this->headers());
    }
    
    /*
    @ transactionHistory
    @ param (int page, int limit)
    @ AGW ENDPOINT GET("/payment/orders/v1/list")
    */
    public function transactionHistory($page = 1, $limit = 10)
    {
        return self::request(self::AGW_API . "/payment/orders/v1/list?limit=$limit&page=$page", false, $this->headers('Bearer'));
    }
    
    /*
    @ walletInquiry
    @ BASE ENDPOINT GET("/wallet/inquiry")
    */
    public function walletInquiry()
    {
        return self::request(self::BASE_API . '/wallet/inquiry', false, $this->headers());
    }
    
    /*
    @ getOvoCash (Ovo Balance)
    @ parse self::walletInquiry()
    */
    public function getOvoCash()
    {
        return self::parse(self::walletInquiry(), false)->data->{'001'}->card_balance;
    }
    
    /*
    @ getOvoCashCardNumber (Ovo Cash)
    @ parse self::walletInquiry()
    */
    public function getOvoCashCardNumber()
    {
        return self::parse(self::walletInquiry(), false)->data->{'001'}->card_no;
    }
    
    /*
    @ getOvoPointsCardNumber (Ovo Points)
    @ parse self::walletInquiry()
    */
    public function getOvoPointsCardNumber()
    {
        return self::parse(self::walletInquiry(), false)->data->{'600'}->card_no;
    }
    
    /*
    @ getOvoPoints
    @ parse self::walletInquiry()
    */
    public function getOvoPoints()
    {
        return self::parse(self::walletInquiry(), false)->data->{'600'}->card_balance;
    }
    
    /*
    @ getPointDetails
    @ AGW ENDPOINT GET("/api/v1/get-expired-webview")
    */
    public function getPointDetails()
    {
        $json                   = base64_decode(json_decode(self::getHmac())->encrypted_string);
        $json                   = json_decode($json);
        $this->hmac_hash        = $json->hmac;
        $this->hmac_hash_random = $json->random;
        return self::request(self::AGW_API . "/api/v1/get-expired-webview", false, self::commander_headers());
    }
    
    /*
    @ getHmac
    @ GET("https://commander.ovo.id/api/v1/get-expired-webview")
    */
    protected function getHmac()
    {
        return self::request("https://commander.ovo.id/api/v1/auth/hmac?type=1&encoded=", false, self::commander_headers());
    }
    
    /*
    @ getBillerList (get category or biller data)
    @ AWS ENDPOINT GET("/gpdm/ovo/1/v1/billpay/catalogue/getCategories")
    */
    public function getBillerList()
    {
        return self::request(self::AWS_API . "/gpdm/ovo/1/v1/billpay/catalogue/getCategories?categoryID=0&level=1", false, $this->headers());
    }
    
    /*
    @ getBillerCategory (get biller by category ID)
    @ param (int category_id)
    @ AWS ENDPOINT GET("/gpdm/ovo/ID/v2/billpay/get-billers")
    */
    public function getBillerCategory($category_id)
    {
        return self::request(self::AWS_API . "/gpdm/ovo/ID/v2/billpay/get-billers?categoryID={$category_id}", false, $this->headers());
    }
    
    /*
    @ getDenominations
    @ param (int product_id)
    @ AWS ENDPOINT GET("/gpdm/ovo/ID/v1/billpay/get-denominations/{product_id}")
    */
    public function getDenominations($product_id)
    {
        return self::request(self::AWS_API . "/gpdm/ovo/ID/v1/billpay/get-denominations/{$product_id}", false, $this->headers());
    }
    
    /*
    @ getBankList
    @ BASE ENDPOINT GET("/v1.0/reference/master/ref_bank")
    */
    public function getBankList()
    {
        return self::request(self::BASE_API . "/v1.0/reference/master/ref_bank", false, $this->headers());
    }
    
    /*
    @ getUnreadNotifications
    @ BASE ENDPOINT GET("/v1.0/notification/status/count/UNREAD")
    */
    public function getUnreadNotifications()
    {
        return self::request(self::BASE_API . "/v1.0/notification/status/count/UNREAD", false, $this->headers());
    }
    
    /*
    @ getAllNotifications
    @ BASE ENDPOINT GET("/v1.0/notification/status/all")
    */
    public function getAllNotifications()
    {
        return self::request(self::BASE_API . "/v1.0/notification/status/all", false, $this->headers());
    }
    
    /*
    @ getInvestment
    @ GET("https://investment.ovo.id/customer")
    */
    public function getInvestment()
    {
        return self::request("https://investment.ovo.id/customer", false, $this->headers());
    }
    
    /*
    @ billerInquiry
    @ param (string phone_number, string otp_ref_id, string otp_code)
    @ AWS ENDPOINT POST("/gpdm/ovo/ID/v2/billpay/inquiry")
    */
    public function billerInquiry($biller_id, $product_id, $denomination_id, $customer_id)
    {
        $field = array(
            'product_id' => $product_id,
            'biller_id' => $biller_id,
            'customer_number' => $customer_id,
            'denomination_id' => $denomination_id,
            'period' => 0,
            'payment_method' => array(
                '001',
                '600',
                'SPLIT'
            ),
            'customer_id' => $customer_id,
            'phone_number' => $customer_id
        );
        
        return self::request(self::AWS_API . '/gpdm/ovo/ID/v2/billpay/inquiry?isFavorite=false', $field, $this->headers());
    }
    
    /*
    @ billerPay
    @ param (string biller_id, string product_id, string order_id, int amount, string customer_id)
    @ AWS ENDPOINT POST("/gpdm/ovo/ID/v1/billpay/pay")
    */
    public function billerPay($biller_id, $product_id, $order_id, $amount, $customer_id)
    {
        $field = array(
            "bundling_request" => array(
                array(
                    "product_id" => $product_id,
                    "biller_id" => $biller_id,
                    "order_id" => $order_id,
                    "customer_id" => $customer_id,
                    "parent_id" => "",
                    "payment" => array(
                        array(
                            "amount" => (int) $amount,
                            "card_type" => "001"
                        ),
                        array(
                            "card_type" => "600",
                            "amount" => 0
                        )
                    )
                )
            ),
            "phone_number" => $customer_id
        );
        
        return self::request(self::AWS_API . '/gpdm/ovo/ID/v1/billpay/pay', $field, $this->headers());
    }
    
    /*
    @ isOvo
    @ param (int amount, string phone_number)
    @ BASE ENDPOINT POST("/v1.1/api/auth/customer/isOVO")
    */
    public function isOVO($amount, $phone_number)
    {
        $field = array(
            'amount' => $amount,
            'mobile' => $phone_number
        );
        
        return self::request(self::BASE_API . '/v1.1/api/auth/customer/isOVO', $field, $this->headers());
    }
    
    /*
    @ generateTrxId
    @ param (int amount, string action_mark)
    @ BASE ENDPOINT POST("/v1.0/api/auth/customer/genTrxId")
    */
    public function generateTrxId($amount, $action_mark = "OVO Cash")
    {
        $field = array(
            'amount' => $amount,
            'actionMark' => $action_mark
        );
        
        return self::request(self::BASE_API . '/v1.0/api/auth/customer/genTrxId', $field, $this->headers());
    }
    
    /*
    @ generateSignature
    @ param (int amount, string trx_id)
    @ generate unlockAndValidateTrxId signature
    */
    protected function generateSignature($amount, $trx_id)
    {
        return sha1(join('||', array(
            $trx_id,
            $amount,
            $this->deviceId()
        )));
    }
    
    /*
    @ unlockAndValidateTrxId
    @ param (int amount, string trx_id, string security_code)
    @ BASE ENDPOINT POST("/v1.0/api/auth/customer/genTrxId")
    */
    public function unlockAndValidateTrxId($amount, $trx_id, $security_code)
    {
        $field = array(
            'trxId' => $trx_id,
            'securityCode' => $security_code,
            'appVersion' => $this->appVersion(),
            'signature' => $this->generateSignature($amount, $trx_id)
        );
        
        return self::request(self::BASE_API . '/v1.0/api/auth/customer/unlockAndValidateTrxId', $field, $this->headers());
    }
    
    /*
    @ transferOVO
    @ param (int/string amount, string phone_number, string, trx_id, string message)
    @ BASE ENDPOINT POST("/v1.0/api/customers/transfer")
    */
    public function transferOVO($amount, $phone_number, $trx_id, $message = "")
    {
        $field = array(
            'amount' => $amount,
            'to' => $phone_number,
            'trxId' => $trx_id,
            'message' => $message
        );
        
        return self::request(self::BASE_API . '/v1.0/api/customers/transfer', $field, $this->headers());
    }
    
    /*
    @ transferBankInquiry
    @ param (string bank_code, string bank_number, string amount, string message, string bank_name)
    @ BASE ENDPOINT POST("/transfer/inquiry")
    */
    public function transferBankInquiry($bank_code, $bank_number, $amount, $message = "", $bank_name = "")
    {
        if ($bank_name === "") {
            $bank_name = $bank_code;
        }

        $field = array(
            'bankCode' => $bank_code,
            'bankName' => $bank_name,
            'accountNo' => $bank_number,
            'amount' => (string) $amount,
            'message' => $message
        );

        return $this->requestWithFallbackUrls(array(
            self::BASE_API . '/v1.1/transfer/inquiry',
            self::BASE_API . '/transfer/inquiry',
            self::BASE_API . '/transfer/inquiry/'
        ), $field, $this->headers());
    }
    
    /*
    @ transferBankDirect
    @ param (string bank_code, string bank_number, string amount, string notes)
    @ BASE ENDPOINT POST("/transfer/direct")
    */
    public function transferBankDirect($bank_code, $bank_number, $bank_name, $bank_account_name, $trx_id, $amount, $notes = "")
    {
        $field = array(
            'bankCode' => $bank_code,
            'accountNo' => self::getOvoCashCardNumber(),
            'amount' => (string) $amount,
            'accountNoDestination' => $bank_number,
            'bankName' => $bank_name,
            'accountName' => $bank_account_name,
            'notes' => $notes,
            'transactionId' => $trx_id
        );
        
        return self::request(self::BASE_API . '/transfer/direct', $field, $this->headers());
    }
    
    /*
    @ qrisScan
    @ param (string qrid)
    @ ENDPOINT GET("/v1/payx/qr/scan")
    */
    public function qrisScan($qrid)
    {
        $path = '/v1/payx/qr/scan?qrid=' . urlencode($qrid);

        return $this->requestWithFallbackUrls(array(
            self::AGW_API . $path,
            self::BASE_API . $path
        ), false, $this->headers('Bearer'));
    }

    /*
    @ qrisCheckout
    @ param (string scan_id, string amount)
    @ ENDPOINT GET("/v1/payx/qr/checkout")
    */
    public function qrisCheckout($scan_id, $amount)
    {
        $path = '/v1/payx/qr/checkout?scanid=' . urlencode($scan_id) . '&amount=' . urlencode((string) $amount);

        return $this->requestWithFallbackUrls(array(
            self::AGW_API . $path,
            self::BASE_API . $path
        ), false, $this->headers('Bearer'));
    }

    /*
    @ checkoutProcess
    @ param (string trx_id, string checkout_id, int amount, string payment_type, string campaign_id, string product_name)
    @ ENDPOINT POST("/v1/checkout")
    */
    public function checkoutProcess($trx_id, $checkout_id, $amount, $payment_type = "ovo_cash", $campaign_id = "", $product_name = "")
    {
        $field = array(
            'checkout_id' => (string) $checkout_id,
            'campaign_id' => (string) $campaign_id,
            'bill' => array(
                array(
                    'amount' => (int) $amount,
                    'type' => (string) $payment_type
                )
            ),
            'metadata' => array(
                'product_name' => (string) $product_name
            )
        );

        $headers = $this->headers('Bearer');
        array_push($headers, 'trx-id: ' . (string) $trx_id);

        return $this->requestWithFallbackUrls(array(
            self::AGW_API . '/v1/checkout',
            self::BASE_API . '/v1/checkout'
        ), $field, $headers);
    }

    /*
    @ qrisPaymentV2
    @ ENDPOINT POST("/wallet/payment/v2")
    */
    public function qrisPaymentV2($amount, $trx_id, $merchant_id, $transaction_id, $merchant_invoice, $card_type = "001", $card_amount2 = 0, $card_type2 = "600")
    {
        $date_time = date('Y-m-d\TH:i:sO');
        $amount = (int) $amount;
        $card_amount2 = (int) $card_amount2;
        if ($card_amount2 < 0) {
            $card_amount2 = 0;
        }

        $field = http_build_query(array(
            'merchant_id' => (string) $merchant_id,
            'transaction_amount' => $amount,
            'card_amount1' => $amount,
            'card_type1' => (string) $card_type,
            'transaction_id' => (string) $transaction_id,
            'appsource' => 'OVOAPPS',
            'merchant_invoice' => (string) $merchant_invoice,
            'card_amount2' => $card_amount2,
            'card_type2' => (string) $card_type2
        ));

        $headers = $this->headersForFormRequest(array(
            'transaction-id' => (string) $trx_id,
            'date_time' => $date_time
        ), 'Bearer');

        return $this->requestWithFallbackUrls(array(
            self::BASE_API . '/wallet/payment/v2',
            self::AGW_API . '/wallet/payment/v2'
        ), $field, $headers);
    }

    /*
    @ QrisPay
    @ param (int amount, string trx_id, string qrid)
    @ V2 flow:
    @ 1) /v1/payx/qr/scan
    @ 2) /v1/payx/qr/checkout
    @ 3) /v1/checkout
    @ 4) /wallet/payment/v2
    @ fallback to legacy /wallet/purchase/qr
    */
    public function QrisPay($amount, $trx_id, $qrid)
    {
        $amount = (int) $amount;
        $steps = array();

        $scan_raw = $this->qrisScan($qrid);
        $scan_data = $this->parse($scan_raw, true);
        $steps['scan'] = $scan_data !== null ? $scan_data : $scan_raw;

        $scan_id = $this->extractResponseValue($scan_data, array(
            'data.details.scan_id',
            'data.scan_id',
            'details.scan_id',
            'scan_id'
        ));

        $checkout_id = $this->extractResponseValue($scan_data, array(
            'data.checkout_id',
            'checkout_id'
        ));

        if ($scan_id !== null) {
            $checkout_data_raw = $this->qrisCheckout($scan_id, (string) $amount);
            $checkout_data = $this->parse($checkout_data_raw, true);
            $steps['checkout_data'] = $checkout_data !== null ? $checkout_data : $checkout_data_raw;

            if ($checkout_id === null) {
                $checkout_id = $this->extractResponseValue($checkout_data, array(
                    'data.checkout_id',
                    'checkout_id'
                ));
            }
        }

        $merchant_id = null;
        $order_id = null;
        $payment_id = null;
        $payment_type = $this->normalizeCheckoutPaymentType($this->extractResponseValue($scan_data, array(
            'data.details.payment_methods.0.type',
            'data.details.payment_methods.0.id',
            'data.payment_methods.0.type',
            'data.payment_methods.0.id',
            'payment_methods.0.type',
            'payment_methods.0.id'
        )));
        $product_name = (string) $this->extractResponseValue($scan_data, array(
            'data.details.merchant.name',
            'data.merchant.name',
            'merchant.name'
        ));
        $campaign_id = (string) $this->extractResponseValue($scan_data, array(
            'data.selected_campaign_id',
            'data.campaign_id',
            'selected_campaign_id',
            'campaign_id'
        ));

        if ($checkout_id !== null) {
            $checkout_process_raw = $this->checkoutProcess($trx_id, $checkout_id, $amount, $payment_type, $campaign_id, $product_name);
            $checkout_process_data = $this->parse($checkout_process_raw, true);
            $steps['checkout_process'] = $checkout_process_data !== null ? $checkout_process_data : $checkout_process_raw;

            if ($this->isSuccessfulResponse($checkout_process_data)) {
                return $checkout_process_raw;
            }

            $merchant_id = $this->extractResponseValue($checkout_process_data, array(
                'data.orders.0.merchant_id',
                'data.orders.0.merchantId',
                'orders.0.merchant_id',
                'orders.0.merchantId'
            ));

            $order_id = $this->extractResponseValue($checkout_process_data, array(
                'data.orders.0.order_id',
                'data.orders.0.orderId',
                'orders.0.order_id',
                'orders.0.orderId'
            ));

            $payment_id = $this->extractResponseValue($checkout_process_data, array(
                'data.orders.0.payment_id',
                'data.orders.0.paymentId',
                'orders.0.payment_id',
                'orders.0.paymentId'
            ));
        }

        if ($merchant_id !== null && $order_id !== null) {
            if ($payment_id === null) {
                $payment_id = $order_id;
            }

            $payment_v2_raw = $this->qrisPaymentV2($amount, $trx_id, $merchant_id, $order_id, $payment_id, $payment_type, 0, "ovo_points");
            $payment_v2_data = $this->parse($payment_v2_raw, true);
            $steps['payment_v2'] = $payment_v2_data !== null ? $payment_v2_data : $payment_v2_raw;

            if ($this->isSuccessfulResponse($payment_v2_data)) {
                return $payment_v2_raw;
            }
        }

        $legacy_raw = $this->qrisPayLegacy($amount, $trx_id, $qrid);
        $legacy_data = $this->parse($legacy_raw, true);

        return json_encode(array(
            'flow' => 'qris_v2_fallback_legacy',
            'steps' => $steps,
            'legacy' => $legacy_data !== null ? $legacy_data : $legacy_raw
        ));
    }

    /*
    @ qrisPayLegacy
    @ Legacy ENDPOINT POST("/wallet/purchase/qr")
    */
    protected function qrisPayLegacy($amount, $trx_id, $qrid)
    {
        $field = array(
            'qrPayload' => $qrid,
            'locationInfo' => array(
                'accuracy' => 11.00483309472351,
                'verticalAccuracy' => 3,
                'longitude' => 84.90665207978246,
                'heading' => 11.704396994254495,
                'latitude' => -9.432921591875759,
                'altitude' => 84.28827400936305,
                'speed' => 0.11528167128562927
            ),
            'deviceInfo' => array(
                'deviceBrand' => $this->deviceBrand(),
                'deviceModel' => $this->deviceModel(),
                'appVersion' => $this->appVersion(),
                'deviceToken' => $this->pushNotificationId()
            ),
            'paymentDetail' => array(
                array(
                    'amount' => $amount,
                    'id' => '001',
                    'name' => 'OVO Cash'
                )
            ),
            'transactionId' => $trx_id,
            'appsource' => 'OVO-APPS'
        );
        
        return self::request(self::BASE_API . '/wallet/purchase/qr?qrid=' . urlencode($qrid), $field, $this->headers());
    }

    protected function isSuccessfulResponse($decoded)
    {
        if (!is_array($decoded)) {
            return false;
        }

        $code = $this->extractResponseValue($decoded, array(
            'code',
            'status',
            'response_code',
            'responseCode'
        ));

        if ($code === null) {
            return false;
        }

        $code = (string) $code;

        return in_array($code, array('0', '200', 'OV00000'), true);
    }

    protected function extractResponseValue($payload, $paths)
    {
        if (!is_array($payload)) {
            return null;
        }

        foreach ($paths as $path) {
            $value = $this->arrayGet($payload, $path);

            if ($value === null || $value === '') {
                continue;
            }

            return $value;
        }

        return null;
    }

    protected function arrayGet($payload, $path)
    {
        $segments = explode('.', $path);
        $value = $payload;

        foreach ($segments as $segment) {
            if (!is_array($value)) {
                return null;
            }

            if (array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }

            if (ctype_digit((string) $segment)) {
                $index = (int) $segment;
                if (array_key_exists($index, $value)) {
                    $value = $value[$index];
                    continue;
                }
            }

            return null;
        }

        return $value;
    }

    protected function normalizeCheckoutPaymentType($raw_type)
    {
        if ($raw_type === null) {
            return 'ovo_cash';
        }

        $type = strtolower(trim((string) $raw_type));

        if ($type === '' || $type === '001' || $type === 'ovo cash') {
            return 'ovo_cash';
        }

        if ($type === '600' || $type === 'ovo points') {
            return 'ovo_points';
        }

        return str_replace(' ', '_', $type);
    }
    
    /*
    @ parse
    @ parse JSON response
    */
    public function parse($json, $true = true)
    {
        return json_decode($json, $true);
    }

    /*
    @ requestWithFallbackUrls
    @ try multiple URLs and return the first usable response
    */
    protected function requestWithFallbackUrls($urls, $post = false, $headers = false)
    {
        $last_result = '';
        $first_json_result = '';
        $first_non_json_result = '';

        foreach ($urls as $url) {
            $result = $this->request($url, $post, $headers);
            $last_result = $result;

            if (!is_string($result)) {
                continue;
            }

            $trimmed = trim($result);

            if ($trimmed === '') {
                continue;
            }

            json_decode($trimmed, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded = json_decode($trimmed, true);

                if ($this->isSuccessfulResponse($decoded)) {
                    return $result;
                }

                if ($first_json_result === '') {
                    $first_json_result = $result;
                }

                continue;
            }

            if ($first_non_json_result === '') {
                $first_non_json_result = $result;
            }
        }

        if ($first_json_result !== '') {
            return $first_json_result;
        }

        if ($first_non_json_result !== '') {
            return $first_non_json_result;
        }

        return $last_result;
    }
    
    /*
    @ Request
    @ Curl http request
    */
    protected function request($url, $post = false, $headers = false)
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0
        ));
        
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            if (is_string($post)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
            }
        }
        
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $result = curl_exec($ch);

        if ($result === false) {
            $result = json_encode(array(
                'curl_error' => curl_error($ch),
                'curl_errno' => curl_errno($ch),
                'url' => $url
            ));
        }

        curl_close($ch);
        return $result;
    }
    
    /*
    @ commander API headers
    @ OVO Commander cutsom headers
    */
    protected function commander_headers()
    {
        $headers = array(
            'accept: application/json, text/plain, */*',
            'app-id: webview-pointexpiry',
            'client-id: ' . $this->clientId(),
            'accept-language: id',
            'service: police',
            'origin: https://webview.ovo.id',
            'user-agent: ' . $this->userAgent(),
            'referer: https://webview.ovo.id/pointexpiry?version=3.43.0'
        );
        
        if ($this->auth_token) {
            array_push($headers, 'authorization: Bearer ' . $this->auth_token);
        }
        
        if ($this->hmac_hash) {
            array_push($headers, 'hmac: ' . $this->hmac_hash);
        }
        
        if ($this->hmac_hash_random) {
            array_push($headers, 'random: ' . $this->hmac_hash_random);
        }
        
        return $headers;
    }
}
