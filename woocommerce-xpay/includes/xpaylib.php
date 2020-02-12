<?php

class XpayLib
{
    const URL_PRODUCTION = "https://xpay.cash/";
    const URL_SANDBOX = "https://xpay.cash/";
    
    /**
     * @var string
     */
    private $apiKey;
    
    /**
     * @var string
     */
    private $apiSecret;

    /**
     * XpayLib constructor.
     * @param string $apiKey
     * @param string $apiSecret
     */
    public function __construct(
        $apiKey,
        $apiSecret
    )
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }
    
    /**
     * @return string
     */
    public function getApiSecret()
    {
        return $this->apiSecret;
    }

    /**
     * @return bool
     */
    public function isSandbox()
    {
        return false;
    }

    /**
     * @return string
     */
    public function getUrl() {
        if ($this->isSandbox()) {
            return self::URL_SANDBOX;
        } else {
            return self::URL_PRODUCTION;
        }
    }

    /**
     * @return string
     */
    public function getRedirectUrl() {
        if ($this->isSandbox()) {
            return self::REDIRECT_URL_STAGING;
        } else {
            return self::REDIRECT_URL_PRODUCTION;
        }
    }

    public function isApiKeyValid() {
        static $token = false;
        $auth = array('email' => $this->apiKey, 'password' => $this->apiSecret);
        $post = json_encode($auth);
        $phash = md5($post);
        if (!$token) {
            $f = @file_get_contents(dirname(__FILE__).'/.cache-token.php');
            if (!empty($f)) {
                $f = json_decode(str_replace('<'.'?php exit; ?'.'>', '', $f), true);
                if (isset($f['hash']) && $f['hash'] == $phash && time() - $f['time'] < 3600) {
                    $token = $f;
                    return $f;
                }
            }
        } else {
            return $token;
        }
        $ch = curl_init();
        $header = array();

        $header[] = 'Content-length: ' . strlen($post);
        $header[] = 'Content-type: application/json';
        $header[] = 'cache-control: no-cache';
        curl_setopt($ch, CURLOPT_URL, $this->getUrl() . "api/v1/auth/login/");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if (!$result) {
            return false;
        }
        $result = json_decode($result, true);
        if (!isset($result['key']) || empty($result['key'])) {
            return false;
        }
        $result['time'] = time();
        $result['hash'] = $phash;
        $result['me'] = $this->getMe($result['key']);
        file_put_contents(dirname(__FILE__).'/.cache-token.php', '<'.'?php exit; ?'.'>'.json_encode($result));
        return $result;
    }
    private function getMe($token) {
        $ch = curl_init();
        $header[] = 'Authorization: Token ' . $token;
        $header[] = 'Content-type: application/json';
        curl_setopt($ch, CURLOPT_URL, $this->getUrl() . 'api/v1/users/');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if (!$result) {
            return false;
        }
        $result = json_decode($result, true);
        return $result;
    }
    public function createTransaction($amount, $currency, $cripto, $url_notification, $exchange) {
        $token = $this->isApiKeyValid();
        if (!$token) {
            return false;
        }
        $token = $token['key'];
        $ch = curl_init();

        $currency = 'COP';
        $arr_order = array(
            'tgt_currency' => $currency,
            'src_currency' => $cripto,
            'amount' => $amount,
            'exchange_id' => $exchange,
            'callback' => $url_notification
        );
        $post = json_encode($arr_order);

        $header[] = 'Authorization: Token ' . $token;
        $header[] = 'Content-length: ' . strlen($post);
        $header[] = 'Content-type: application/json';
        curl_setopt($ch, CURLOPT_URL, $this->getUrl() . 'api/v1/transactions/create/');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if (!$result) {
            return false;
        }
        $result = json_decode($result, true);
        if (!isset($result['id']) || empty($result['id'])) {
            return false;
        }
        return $result;
    }
    public function getTransaction($id) {
        $token = $this->isApiKeyValid();
        if (!$token) {
            return false;
        }
        $token = $token['key'];
        $ch = curl_init();

        $header[] = 'Authorization: Token ' . $token;
        $header[] = 'Content-type: application/json';
        curl_setopt($ch, CURLOPT_URL, $this->getUrl() . 'api/v1/transactions/'.$id.'/');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if (!$result) {
            return false;
        }
        $result = json_decode($result, true);
        if (!isset($result['id']) || empty($result['id'])) {
            return false;
        }
        return $result;
    }
    public function cancelTransaction($id) {
        $token = $this->isApiKeyValid();
        if (!$token) {
            return false;
        }
        $token = $token['key'];
        $ch = curl_init();

        $header[] = 'Authorization: Token ' . $token;
        $header[] = 'Content-type: application/json';
        curl_setopt($ch, CURLOPT_URL, $this->getUrl() . 'api/v1/transactions/cancel/'.$id.'/');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if (!$result) {
            return false;
        }
        $result = json_decode($result, true);
        return $result;
    }
    public function getCurrencies($currency, $amount) {
        $currency = 'COP';
        $token = $this->isApiKeyValid();
        if (!$token) {
            return false;
        }
        $token = $token['key'];
        $header = array();
        $header[] = "Authorization: Token " . $token;
        $header[] = 'Content-type: application/json';
        $amount = (float)round($amount, 2);
        $currency = strtoupper($currency);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getUrl() . "api/v1/transactions/available/currencies/$amount/$currency/");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if (!$result) {
            return false;
        }
        $result = json_decode($result, true);
        if (!isset($result['available_currencies']) || empty($result['available_currencies'])) {
            return false;
        }
        return $result['available_currencies'];
    }
    static public function getCountries() {
        $ch = curl_init();
        $header = array();
        $header[] = 'Content-type: application/json';
        curl_setopt($ch, CURLOPT_URL, self::URL_PRODUCTION . 'api/v1/countries/');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if (!$result) {
            return false;
        }
        $result = json_decode($result, true);
        return $result;
    }
}
