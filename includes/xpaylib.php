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
	public function __construct( $apiSecret )
	{
		$this->apiSecret = $apiSecret;
	}

	/**
	 * @return string
	 */
	public function getApiKey( )
	{
		return $this->apiKey;
	}

	/**
	 * @return string
	 */
	public function getApiSecret( )
	{
		return $this->apiSecret;
	}

	/**
	 * @return bool
	 */
	public function isSandbox( )
	{
		return false;
	}

	/**
	 * @return string
	 */
	public function getUrl( )
	{
		if ( $this->isSandbox() ) {
			return self::URL_SANDBOX;
		} else {
			return self::URL_PRODUCTION;
		}
	}

	/**
	 * @return string
	 */
	public function getRedirectUrl( )
	{
		if ( $this->isSandbox() ) {
			return self::REDIRECT_URL_STAGING;
		} else {
			return self::REDIRECT_URL_PRODUCTION;
		}
	}

	public function isApiKeyValid( )
	{
		$result           = array( );
		$result[ 'time' ] = time();
		$result[ 'key' ]  = $this->apiSecret;
		$result[ 'me' ]   = $this->getMe( $result[ 'key' ] );
		return $result[ 'me' ] ? $result : false;
	}
	private function getMe( $token )
	{
		static $me = array( );
		if ( isset( $me[ $token ] ) )
			return $me[ $token ];

		$ch        = curl_init();
		$header[ ] = 'Authorization: Token ' . $token;
		$header[ ] = 'Content-type: application/json';
		curl_setopt( $ch, CURLOPT_URL, $this->getUrl() . 'api/v1/users/' );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		$result = curl_exec( $ch );
		curl_close( $ch );
		if ( !$result ) {
			return false;
		}
		$r = json_decode( $result, true );
		return isset( $r[ 'user_info' ] ) ? ( $me[ $token ] = $r ) : ( $me[ $token ] = false );
	}
	public function createTransaction( $amount, $currency, $cripto, $url_notification, $exchange )
	{
		$token = $this->isApiKeyValid();
		if ( !$token ) {
			return false;
		}
		$token = $token[ 'key' ];
		$ch    = curl_init();

		$arr_order = array(
			 'tgt_currency' => $currency,
			'src_currency' => $cripto,
			'amount' => $amount,
			'exchange_id' => $exchange,
			'callback' => $url_notification
		);
		$post      = json_encode( $arr_order );

		$header[ ] = 'Authorization: Token ' . $token;
		$header[ ] = 'Content-length: ' . strlen( $post );
		$header[ ] = 'Content-type: application/json';
		curl_setopt( $ch, CURLOPT_URL, $this->getUrl() . 'api/v1/transactions/create/' );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $post );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		$result = curl_exec( $ch );
		curl_close( $ch );
		if ( !$result ) {
			return false;
		}
		$result = json_decode( $result, true );
		if ( !isset( $result[ 'id' ] ) || empty( $result[ 'id' ] ) ) {
			return false;
		}
		return $result;
	}
	public function getTransaction( $id )
	{
		$token = $this->isApiKeyValid();
		if ( !$token ) {
			return false;
		}
		$token = $token[ 'key' ];
		$ch    = curl_init();

		$header[ ] = 'Authorization: Token ' . $token;
		$header[ ] = 'Content-type: application/json';
		curl_setopt( $ch, CURLOPT_URL, $this->getUrl() . 'api/v1/transactions/' . $id . '/' );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		$result = curl_exec( $ch );
		curl_close( $ch );
		if ( !$result ) {
			return false;
		}
		$result = json_decode( $result, true );
		if ( !isset( $result[ 'id' ] ) || empty( $result[ 'id' ] ) ) {
			return false;
		}
		return $result;
	}
	public function cancelTransaction( $id )
	{
		$token = $this->isApiKeyValid();
		if ( !$token ) {
			return false;
		}
		$token = $token[ 'key' ];
		$ch    = curl_init();

		$header[ ] = 'Authorization: Token ' . $token;
		$header[ ] = 'Content-type: application/json';
		curl_setopt( $ch, CURLOPT_URL, $this->getUrl() . 'api/v1/transactions/cancel/' . $id . '/' );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		$result = curl_exec( $ch );
		curl_close( $ch );
		if ( !$result ) {
			return false;
		}
		$result = json_decode( $result, true );
		return $result;
	}
	public function getCurrencies( $currency, $amount )
	{
		$token    = $this->isApiKeyValid();
		if ( !$token ) {
			return false;
		}
		$token     = $token[ 'key' ];
		$header    = array( );
		$header[ ] = "Authorization: Token " . $token;
		$header[ ] = 'Content-type: application/json';
		$amount    = (float) round( $amount, 2 );
		$currency  = strtoupper( $currency );
		$ch        = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $this->getUrl() . "api/v1/transactions/available/currencies/$amount/$currency/" );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		$result = curl_exec( $ch );
		curl_close( $ch );
		if ( !$result ) {
			return false;
		}
		$result = json_decode( $result, true );
		if ( !isset( $result[ 'available_currencies' ] ) || empty( $result[ 'available_currencies' ] ) ) {
			return false;
		}
		return $result[ 'available_currencies' ];
	}
	static public function getCountries( )
	{
		$ch        = curl_init();
		$header    = array( );
		$header[ ] = 'Content-type: application/json';
		curl_setopt( $ch, CURLOPT_URL, self::URL_PRODUCTION . 'api/v1/countries/' );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		$result = curl_exec( $ch );
		curl_close( $ch );
		if ( !$result ) {
			return false;
		}
		$result = json_decode( $result, true );
		return $result;
	}
}
