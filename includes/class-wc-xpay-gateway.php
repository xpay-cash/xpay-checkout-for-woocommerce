<?php
/**
 * Modulo Xpay
 *
 * @author    Kijam <info@kijam.com>
 * @copyright 2019 Kijam
 * @license   GPLv2
 */

include_once( dirname( __FILE__ ) . '/class-wc-override-payment-gateway.php' );
include_once( dirname( __FILE__ ) . '/xpaylib.php' );
/**
 * WC Xpay Gateway Class.
 *
 * Built the Xpay method.
 */
if ( ! class_exists( 'WC_Xpay_Gateway' ) ) :
    class WC_Xpay_Gateway extends WC_Payment_Gateway_Xpay {

        static private $client_id       = null;
        static private $client_secret   = null;
        static private $currency_rate   = null;
        static private $sandbox         = null;
        static private $is_load         = null;
        static private $show_debug      = false;
        static private $log             = null;
        static private $token           = null;
        static private $token_id        = null;
        static private $cache_metadata  = array();
        private $convertion_rate        = array();
        private $convertion_option      = null;
        private $api_me                 = null;

        /**
         * Constructor for the gateway.
         *
         * @return void
         */
        public function __construct() {

            $this->id              = 'xpay-gateway';
            $this->icon            = apply_filters( 'woocommerce_xpay_icon', plugins_url( 'images/xpay.png', plugin_dir_path( __FILE__ ) ) );
            $this->has_fields      = false;
            $this->method_title    = __( 'Xpay', 'woocommerce-xpay' );

            self::check_database();

            $this->xpay_currencies    = include 'data-currencies.php';
            $this->xpay_country       = $this->get_option( 'xpay_country', WC()->countries->get_base_country() );
            $this->currency_iso       = get_woocommerce_currency();
            
            switch ( strtolower( $this->xpay_country ) ) {
                case 'ar':
                    $this->setting = $this->xpay_currencies[ 'AR' ];
                    break;
                case 've':
                    $this->setting = $this->xpay_currencies[ 'VE' ];
                    break;
                default:
                    $this->setting = $this->xpay_currencies[ 'CO' ];
                    break;
            }
            // Load the settings.
            $this->init_settings();

            // Define user set variables.
            $this->title                    = $this->get_option( 'title', __( 'Xpay', 'woocommerce-xpay' ) );
            $this->description              = $this->get_option( 'description', __( 'Pay with Xpay', 'woocommerce-xpay' ) );
            $this->mp_completed             = $this->get_option( 'mp_completed' ) == 'yes';
            $this->convertion_option	    = $this->get_option( 'convertion_option' );
            $this->convertion_rate		    = @json_decode( (string) get_option( $this->id . 'cconvertion_rate', 'false' ), true );

            self::$client_id            = (string) $this->get_option( 'client_id' );
            self::$client_secret        = (string) $this->get_option( 'client_secret' );
            self::$sandbox              = $this->get_option( 'sandbox' ) == 'yes';
            self::$show_debug           = $this->get_option( 'debug' ) == 'yes';
            self::$currency_rate        = (string) $this->get_option( 'convertion_rate', '1.0' );
            self::$is_load              = $this;

            // Actions.
            add_action( 'valid_xpay_ipn_request', array( $this, 'successful_request' ) );
            add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_xpay_metabox', array( $this, 'woocommerce_xpay_metabox' ) );
            add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'thankyou_text' ), 10, 2 );
            add_action( 'woocommerce_order_status_refunded', array( $this, 'void_order' ) );
            add_action( 'woocommerce_order_status_cancelled', array( $this, 'void_order' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'hook_js' ) );
            add_action( 'wp_head', array( $this, 'hook_css' ) );
            if ( is_admin() && isset( $_GET['section'] ) && $this->id == $_GET['section'] ) {
                $dt1 = dirname( __FILE__ ) . '/.test.txt';
                $dt3 = dirname( __FILE__ ) . '/../logs/.test.txt';
                @file_put_contents( $dt1, 'testing' );
                @file_put_contents( $dt3, 'testing' );
                $t1 = @file_get_contents( $dt1 );
                $t3 = @file_get_contents( $dt3 );
                @unlink( $dt1 );
                @unlink( $dt3 );
                if ( $t1 != 'testing' ) {
                    add_action( 'admin_notices', array( $this, 'directory_includes_nowrite' ) );
                }
                if ( $t3 != 'testing' ) {
                    add_action( 'admin_notices', array( $this, 'directory_logs_nowrite' ) );
                }
                if ( empty( self::$client_id ) ) {
                    add_action( 'admin_notices', array( $this, 'client_id_missing_message' ) );
                }
                if ( empty( self::$client_secret ) ) {
                    add_action( 'admin_notices', array( $this, 'client_secret_missing_message' ) );
                }
                if ( ! empty( self::$client_secret ) && ! empty( self::$client_id ) && ! $this->get_xpay_data() ) {
                    add_action( 'admin_notices', array( $this, 'client_secret_invalid_message' ) );
                }
                if ( ! $this->using_supported_currency() ) {
                    add_action( 'admin_notices', array( $this, 'currency_not_supported_message' ) );
                }
                // Load the form fields.
                $this->init_form_fields();
            }// End if().
        }
        public function payment_fields() {
            $api = new XpayLib(
                self::get_client_id(),
                self::get_client_secret()
            );
            $ordertotal = (float)wp_kses_data( WC()->cart->total );
            if (!$api->isApiKeyValid()) {
                echo __( 'Xpay is not available.', 'woocommerce-xpay' );
                return;
            }
            $total = $this->get_convertion_rate(
                $this->currency_iso,
                $this->setting['CURRENCY']
            ) * $ordertotal;
            self::debug('Xpay total: '.self::pL($total, true));
            self::debug('Xpay CURRENCY: '.self::pL($this->setting['CURRENCY'], true));
            $currencies = $api->getCurrencies($this->setting['CURRENCY'], $total);
            self::debug('Xpay currencies: '.self::pL($currencies, true));
            if ( !$currencies ) {
                echo __( 'Xpay no puede procesar la moneda o el monto de la tienda actual.', 'woocommerce-xpay' );
                return;
            }
            echo __( 'Seleccione una moneda:', 'woocommerce-xpay' ).'<br /><ul>';
            $first = true;
            foreach ( $currencies as $cur ) {
                echo '<li>
                        <input type="radio" '.($first?'checked':'').'
                                name="xpay-currency"
                                value="'.$cur['currency']['code'].'-'.$cur['exchange'].'"
                        /> - <b>'.$cur['currency']['name'].'</b>: '.$cur['amount'].' '.$cur['currency']['symbol'].'
                </li>';
                $first = false;
            }
            echo '</ul>';
        }
        public function get_xpay_data($force_reload = true)
        {
            if (!self::get_client_id() || !self::get_client_secret()) {
                return false;
            }
            if (!$force_reload && !is_null($this->api_me)) {
                return $this->api_me;
            }
            $api = new XpayLib(
                self::get_client_id(),
                self::get_client_secret()
            );
            $cache_id = 'mp_me_'.md5(self::get_client_id().'-'.self::get_client_secret());
            $me = false;
            if (!$force_reload && $me = self::get_metadata(0, $cache_id)) {
                self::debug('Result cache verifyXpay: '.self::pL($me, true));
                if (isset($me['status'])) {
                    if (time() - $me['time'] < 10) {
                        if ($me['status'] == 'fail') {
                            self::debug('ERROR-verifyXpay: '.self::pL($me, true));
                            return false;
                        } else {
                            $this->api_me = $me;
                            return true;
                        }
                    }
                }
            }
            try {
                $me = $api->isApiKeyValid();
                self::set_metadata(0, $cache_id, $me);
                self::debug('Result verifyXpay: '.self::pL($me, true));
                if (!$me) {
                    self::debug('ERROR-verifyXpay: '.self::pL($me, true));
                    return false;
                }
                self::debug('verifyXpay: new cache '.$cache_id.' -> '.self::pL($me, true));
                $this->api_me = $me;
                return $me;
            } catch (Exception $e) {
                self::debug('ERROR-verifyXpay: '.$e->getFile()."[".$e->getLine()."] -> ".$e->getMessage());
                return false;
            }
            return false;
        }
        public function get_convertion_rate( $currency_org, $currency_dst ) {
            if ( $currency_org == $currency_dst || $this->convertion_option == 'off' ) {
                $wmc_rate = $this->getcookie('wmc_currency_rate');
                return $wmc_rate?$wmc_rate:1.0;
            }
            if ( $this->convertion_option == 'live-rates' ) {
                if (
                    isset($this->convertion_rate[$currency_org]) &&
                    isset($this->convertion_rate[$currency_org][$currency_dst]) &&
                    $this->convertion_rate[$currency_org][$currency_dst]['time'] > time() - 60 * 60 * 12
                ) {
                    return $this->convertion_rate[$currency_org][$currency_dst]['rate'];
                }
                $headers = array(
                        'Connection:keep-alive',
                        'User-Agent:Mozilla/5.0 (Windows NT 6.3) AppleWebKit/53 (KHTML, like Gecko) Chrome/37 Safari/537.36');

                $ch = curl_init('https://www.live-rates.com/rates');
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $api_json = curl_exec($ch);
                $api_arr = json_decode($api_json, true);
                foreach ($api_arr as $fields) {
                    if (strlen($fields['currency']) == 7 &&
                        preg_match('/[A-Z0-9]{3}\/[A-Z0-9]{3}/', $fields['currency'])) {
                        $cur = explode('/', $fields['currency']);
                        $this->convertion_rate[$cur[0]][$cur[1]] = array();
                        $this->convertion_rate[$cur[0]][$cur[1]]['rate'] = (float)$fields['rate'];
                        $this->convertion_rate[$cur[0]][$cur[1]]['time'] = time();
                        $this->convertion_rate[$cur[1]][$cur[0]] = array();
                        $this->convertion_rate[$cur[1]][$cur[0]]['rate'] = 1.0 / (float)$fields['rate'];
                        $this->convertion_rate[$cur[1]][$cur[0]]['time'] = time();
                    }
                }
                update_option( $this->id . 'cconvertion_rate', json_encode( $this->convertion_rate ) );
                if (
                    isset($this->convertion_rate[$currency_org]) &&
                    isset($this->convertion_rate[$currency_org][$currency_dst])
                ) {
                    return $this->convertion_rate[$currency_org][$currency_dst]['rate'];
                }
            } else if (($this->convertion_option == 'dicom' || $this->convertion_option == 'promedio') && 
                       (in_array($currency_org, array('VES', 'VEF', 'VEB')) || in_array($currency_dst, array('VES', 'VEF', 'VEB')))) {
                if (
                    isset($this->convertion_rate[$currency_org]) &&
                    isset($this->convertion_rate[$currency_org][$currency_dst]) &&
                    $this->convertion_rate[$currency_org][$currency_dst]['time'] > time() - 60 * 60 * 12
                ) {
                    if ( $this->convertion_option == 'dicom' ) {
                        return $this->convertion_rate[$currency_org][$currency_dst]['rate_dicom'];
                    } else {
                        return $this->convertion_rate[$currency_org][$currency_dst]['rate'];
                    }
                }
                $headers = array(
                    'Connection:keep-alive',
                    'User-Agent:Mozilla/5.0 (Windows NT 6.3) AppleWebKit/53 (KHTML, like Gecko) Chrome/37 Safari/537.36'
                );

                $ch = curl_init('https://s3.amazonaws.com/dolartoday/data.json');
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $api_json = utf8_encode(curl_exec($ch));
                curl_close($ch);
                $api_arr = json_decode($api_json, true);
                foreach ($api_arr as $ac => $fields) {
                    if ($ac == $currency_org) {
                        $this->convertion_rate[$currency_org][$currency_dst] = array();
                        $this->convertion_rate[$currency_org][$currency_dst]['rate_dicom'] = (float)$fields['sicad2'];
                        $this->convertion_rate[$currency_org][$currency_dst]['rate'] = (float)$fields['promedio_real'];
                        $this->convertion_rate[$currency_org][$currency_dst]['time'] = time();
                        $this->convertion_rate[$currency_dst][$currency_org] = array();
                        $this->convertion_rate[$currency_dst][$currency_org]['rate_dicom'] = 1.0 / (float)$fields['sicad2'];
                        $this->convertion_rate[$currency_dst][$currency_org]['rate'] = 1.0 / (float)$fields['promedio_real'];
                        $this->convertion_rate[$currency_dst][$currency_org]['time'] = time();
                    } else if ($ac == $currency_dst) {
                        $this->convertion_rate[$currency_org][$currency_dst] = array();
                        $this->convertion_rate[$currency_org][$currency_dst]['rate_dicom'] = 1.0 / (float)$fields['sicad2'];
                        $this->convertion_rate[$currency_org][$currency_dst]['rate'] = 1.0 / (float)$fields['promedio_real'];
                        $this->convertion_rate[$currency_org][$currency_dst]['time'] = time();
                        $this->convertion_rate[$currency_dst][$currency_org] = array();
                        $this->convertion_rate[$currency_dst][$currency_org]['rate_dicom'] = (float)$fields['sicad2'];
                        $this->convertion_rate[$currency_dst][$currency_org]['rate'] = (float)$fields['promedio_real'];
                        $this->convertion_rate[$currency_dst][$currency_org]['time'] = time();
                    }
                }
                update_option( $this->id . 'cconvertion_rate', json_encode( $this->convertion_rate ) );
                if (
                    isset($this->convertion_rate[$currency_org]) &&
                    isset($this->convertion_rate[$currency_org][$currency_dst])
                ) {
                    if ( $this->convertion_option == 'dicom' ) {
                        return $this->convertion_rate[$currency_org][$currency_dst]['rate_dicom'];
                    } else {
                        return $this->convertion_rate[$currency_org][$currency_dst]['rate'];
                    }
                }
            }
            if ( $this->convertion_option == 'custom' ) {
                if ($this->setting['CURRENCY'] == $currency_dst) {
                    return self::$currency_rate;
                } else {
                    return 1.0 / self::$currency_rate;
                }
            }
            return 1.0;
        }
        public static function pL(&$data, $return_log = false)
        {
            if ( ! self::$is_load ) {
                return print_r($data, $return_log);
            }
            if ( ! self::$show_debug ) {
                return '';
            }
            return print_r($data, $return_log);
        }
        static public function debug( $message ) {
            if ( ! self::$is_load ) {
                self::$is_load = new WC_Xpay_Gateway();
            }
            if ( self::$show_debug && ! empty( $message ) ) {
                $path = dirname( __FILE__ ) . '/..';
                if ( ! @is_dir( $path . '/logs' ) ) {
                    @mkdir( $path . '/logs' );
                }

                if ( ! @is_dir( $path . '/logs/' . date( 'Y-m' ) ) ) {
                    @mkdir( $path . '/logs/' . date( 'Y-m' ) );
                }

                $fp = @fopen( $path . '/logs/' . date( 'Y-m' ) . '/log-' . date( 'Y-m-d' ) . '.log', 'a' );

                @fwrite( $fp, "\n----- " . date( 'Y-m-d H:i:s' ) . " -----\n" );
                @fwrite( $fp, $message );
                @fclose( $fp );
            }
        }
        static function get_instance() {
            if ( is_null( self::$is_load ) ) {
                self::$is_load = new WC_Xpay_Gateway();
            }
            return self::$is_load;
        }
        /**
         * Returns a bool that indicates if currency is amongst the supported ones.
         *
         * @return bool
         */
        protected function using_supported_currency() {
            if ( ! $this->setting ) {
                return false;
            }
            return get_woocommerce_currency() == $this->setting['CURRENCY'] || $this->convertion_option != 'off';
        }

        /**
         * Determina si xpay esta disponible
         *
         * @return bool
         */
        public function is_available() {
            $available = false;
            $available = ( 'yes' == $this->settings['enabled'] ) &&
                    ! empty( self::$client_id ) &&
                    ! empty( self::$client_secret ) &&
                    $this->using_supported_currency();
            if ( $available ) {
                $api = new XpayLib(
                    self::get_client_id(),
                    self::get_client_secret()
                );
                if ( !$api->isApiKeyValid() ) {
                    $available = false;
                } else {
                    $ordertotal = (float)wp_kses_data( WC()->cart->total );
                    $total = $this->get_convertion_rate(
                        $this->currency_iso,
                        $this->setting['CURRENCY']
                    ) * $ordertotal;
                    $currencies = $api->getCurrencies($this->setting['CURRENCY'], $total);
                    if ( !$currencies || count( $currencies ) < 1 ) {
                        $available = false;
                    }
                }
            }
            return $available;
        } 

        /**
         * Initialise Gateway Settings Form Fields.
         *
         * @return void
         */
        public function init_form_fields() {
            if ( ! $this->setting ) {
                return;
            }
            $currency_org = get_woocommerce_currency();
            $currency_dst = $this->setting['CURRENCY'];
            $this->form_fields = include 'data-settings-xpay.php';
            if ( $currency_org == $currency_dst ) {
                unset( $this->form_fields['convertion_option'] );
                unset( $this->form_fields['convertion_rate'] );
            }
        }
        /**
         * Void order on Xpay
         *
         * @param int $order_id Order ID..
         *
         * @return void
         *
         * @since 1.0.0
         */
        public function void_order( $order_id ) {
            //No existe en Xpay
        }
        /**
         * Generate the form.
         *
         * @param int $order_id Order ID.
         *
         * @return string           Payment form.
         */
        public function generate_form( $order_id ) {
            $order = new WC_Order( $order_id );
            if (defined('PHP_SESSION_NONE') && session_id() == PHP_SESSION_NONE || session_id() == '') {
                session_start();
            }
            $cripto = explode('-', $_SESSION['xpay-currency']);
            if ( ! $this->get_xpay_data( ) ) {
                return '';
            }
            $order_id = method_exists( $order, 'get_id' )?$order->get_id():$order->id;
            $currency = get_woocommerce_currency();
            
            $payment = self::get_metadata(
                $order_id,
                'payment_data'
            );
            if (!$payment) {
                $ordertotal = $order->get_total();
                $total = $this->get_convertion_rate($this->currency_iso, $this->setting['CURRENCY']) * $ordertotal;
                $api = new XpayLib(
                    self::get_client_id(),
                    self::get_client_secret()
                );
                try {
                    $payment = $api->createTransaction($total, $this->setting['CURRENCY'], $cripto[0], get_home_url() . '/?ipn-xpay='.$order_id, $cripto[1]);
                    $payment['gen_transaction_time'] = time();
                    self::debug("createTransaction result: ".self::pL($payment, true));
                } catch (Exception $e) {
                    self::debug("createOrder $order_id data: ".self::pL($data, true));
                    self::debug("createOrder $order_id Exception: ".$e->getFile().'['.$e->getLine().']: '.$e->getMessage());
                    $payment = false;
                }
            }
            if ( $payment && $payment['status'] == 'sending' ) {
                self::set_metadata(
                    $order_id,
                    'payment_data',
                    $payment
                );
                self::set_metadata(
                    $order_id,
                    'string_amount_to_change',
                    $payment['string_amount_to_change']
                );
                self::set_metadata(
                    $order_id,
                    'string_amount_to_paid',
                    $payment['string_amount_to_paid']
                );
                self::set_metadata(
                    $order_id,
                    'string_crypto_amount_to_commerce',
                    $payment['string_crypto_amount_to_commerce']
                );
                self::set_metadata(
                    $order_id,
                    'string_fiat_amount_to_commerce',
                    $payment['string_fiat_amount_to_commerce']
                );
                self::set_metadata(
                    $order_id,
                    'id_transaction',
                    $payment['id']
                );
                self::set_metadata(
                    $order_id,
                    'status',
                    $payment['status']
                );
                $html = '<p>' . __( 'Gracias por su pedido, complete la siguiente información para procesar su pedido con', 'woocommerce-xpay' ) . ' <a href="https://xpay.cash/" target="_blank">© Xpay</a></p>';
                $html .= 'Debes enviar la cantidad exacta de <b>'.$payment['amount_to_paid'].' '.$payment['currency_to_paid'].'</b> a la billetera:<br /><center><a href="'.$payment['url'].'" target="_blank"><img src="https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl='.urlencode($payment['qr']).'&choe=UTF-8" /><br />'.$payment['wallet'].'</a><br />En menos de <b id="xpay-timer" data-seconds="'.(int)($payment['waiting_time'] - (time() - $payment['gen_transaction_time'])).'"></b> minutos, de lo contrario esta transaccion sera cancelada.</center>';
                $html .= '<div class="row xpay-form"><br class="clearfix" /></div>';
            } else if ( $payment['status'] == 'approved' ) {
                echo '<script>window.location.href="'.$order->get_checkout_order_received_url().'";</script>';
                exit;
            } else if ( $payment && isset($payment['status']) ) {
                echo '<script>window.location.href="'.$order->get_cancel_order_url().'";</script>';
                exit;
            } else {
                $html = '<p>' . __( 'Hubo un problema al comunicarse con Xpay. Inténtalo de nuevo más tarde ...', 'woocommerce-xpay' ) . '</p>';
            }
            $html .= '<br /><br /><a id="cancel_payment_xpay" class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancelar &amp; Restaurar Carrito', 'woocommerce-xpay' ) . '</a>';
            return $html;
        }
        public function checkIpn($order_id) {
            if (isset($input['order_id'])) {
                $order = new WC_Order( $order_id );
                $api = new XpayLib(
                    self::get_client_id(),
                    self::get_client_secret()
                );
                $payment = self::get_metadata(
                    $order_id,
                    'payment_data'
                );
                $input = $api->getTransaction($payment['id']);
                $input['gen_transaction_time'] = $payment['gen_transaction_time'];
                self::set_metadata(
                    $order_id,
                    'payment_data',
                    $input
                );
                self::set_metadata(
                    $order_id,
                    'status',
                    $input['status']
                );
                self::debug('Actual transaction: '.print_r($input, true));
                if (abs($payment['amount_to_paid'] - $input['amount_to_paid']) > 0.0001) {
                    $order->update_status( 'failed', __( 'Xpay: Amount is invalid.', 'woocommerce-xpay' ) . " -> " . $payment['amount_to_paid'].' diff of '.$input['amount_to_paid'] );
                    self::debug("Result: 'reject' 1 > ".$order->get_total().' - '.$input['amount']);
                    return;
                }
                if ($input['status'] == 'approved') {
                    $order->add_order_note( __( 'Xpay: Payment approved.', 'woocommerce-xpay' ) );
                    $order->payment_complete();
                    if ( $this->mp_completed ) {
                        $order->update_status( 'completed', __( 'Xpay: Order processed.', 'woocommerce-xpay' ) );
                    }
                    self::debug("Result: 'accept'");
                    return;
                }
                if ($input['status'] == 'cancelled' || $input['status'] == 'rejected') {
                    $order->update_status( 'failed', __( 'Xpay: Transaction was declined.', 'woocommerce-xpay' ) );
                    self::debug("Result: 'reject' 2");
                    return;
                }
                if ($input['status'] == 'refunded') {
                    $order->update_status( 'refunded', __( 'Xpay: Transaction was refunded.', 'woocommerce-xpay' ) );
                    self::debug("Result: 'refunded'");
                    return;
                }
            }
        }
        /**
         * Define the woocommerce_thankyou_order_received_text callback.
         *
         * @param html  $var Default value.
         * @param order $order WC_Order.
         *
         * @return html
         *
         * @since 1.0.0
         */
        function thankyou_text( $var, $order ) {
            $old_wc    = version_compare( WC_VERSION, '3.0', '<' );
            $order_id  = $old_wc ? $order->id : $order->get_id();
            $transaction_id = $this->get_metadata( $order_id, 'id_transaction' );
            if ($transaction_id) {
                return '<center><b>' . __( 'Gracias. Tu orden ha sido recibida.', 'woocommerce-xpay' ) . '<br />' . __( 'Su ID de transacción es', 'woocommerce-xpay' ) . ': ' . $transaction_id . '</b></center>';
            }
            return $var;
        }
        /**
         * Fix Xpay CSS.
         *
         * @return string Styles.
         */
        public function hook_css() {
            wp_register_style( 'xpay_style', plugins_url( 'woocommerce-xpay/xpay_style.css' , basename( __FILE__ ) ) );
            wp_enqueue_style( 'xpay_style' );
        }
        /**
         * Load JS for Xpay.
         *
         * @return void.
         */
        public function hook_js() {
            wp_enqueue_script( 'wc-xpayjs', plugins_url( 'woocommerce-xpay/xpay_script.js' , basename( __FILE__ ) ), array( 'jquery' ), WC_Xpay::VERSION, true );
            wp_localize_script( 'wc-xpayjs', 'wc_xpay_context',
                array(
                    'home_url'  => home_url(),
                    'messages' => array(
                        'server_loading'    => __( 'Loading...', 'woocommerce-xpay' ),
                    )
                )
            );
        }
        /**
         * Process the payment and return the result.
         *
         * @param int $order_id Order ID.
         *
         * @return array           Redirect.
         */
        public function process_payment( $order_id ) {
            $order = new WC_Order( $order_id );
            return array(
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url( true ),
            );
        }

        /**
         * Output for the order received page.
         *
         * @return void
         */
        public function receipt_page( $order_id ) {
            echo $this->generate_form( $order_id );
        }

        static private function get_client_id() {
            if ( ! self::$is_load ) {
                self::$is_load = new WC_Xpay_Gateway();
            }
            return self::$client_id;
        }

        static private function get_client_secret() {
            if ( ! self::$is_load ) {
                self::$is_load = new WC_Xpay_Gateway();
            }
            return self::$client_secret;
        }

        static public function set_metadata( $order_id, $key, $value ) {
            global $wpdb;
            self::$cache_metadata[$order_id.'-'.$key] = $value;
            $table_name = $wpdb->prefix . 'woo_xpay';
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT `id` FROM
                    $table_name
                WHERE
                        `order_id` = %d
                    AND
                        `key` = '%s'
                LIMIT 1",
                (int) $order_id,
                $key
            ));
            if ( $exists ) {
                $result = $wpdb->update( $table_name,
                    array(
                        'data' => serialize( $value ),
                    ),
                    array(
                        'order_id' => $order_id,
                        'key' => $key,
                    ),
                    array( '%s' ),
                array( '%d', '%s' ) );
            } else {
                $result = $wpdb->insert( $table_name,
                    array(
                    'order_id' => $order_id,
                    'key' => $key,
                    'data' => serialize( $value ),
                ), array( '%d', '%s', '%s' ) );
            }
            self::debug( "set_metadata [order:$order_id]: [$key]=>" . self::pL( $value, true ) . ' Result: ' . self::pL( $result, true ) );
            return $result;
        }
        static public function get_metadata( $order_id, $key ) {
            global $wpdb;
            if ( isset(self::$cache_metadata[$order_id.'-'.$key] ) ) {
                return self::$cache_metadata[$order_id.'-'.$key];
            }
            $table_name = $wpdb->prefix . 'woo_xpay';
            $data = $wpdb->get_var($wpdb->prepare(
                "SELECT `data` FROM
                    $table_name
                WHERE
                        `order_id` = %d
                    AND
                        `key` = '%s'
                LIMIT 1",
                (int) $order_id,
                $key
            ));
            self::debug( "get_metadata [order:$order_id]: [$key] | Result: " . self::pL( $data, true ) );
            self::$cache_metadata[$order_id.'-'.$key] = $data?unserialize( $data ):false;
            return self::$cache_metadata[$order_id.'-'.$key];
        }
        static public function check_database() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'woo_xpay';
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
                $charset_collate = $wpdb->get_charset_collate();
                $sql = "CREATE TABLE $table_name (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `order_id` bigint NOT NULL,
                    `key` varchar(255) NOT NULL,
                    `data` longtext NOT NULL,
                    PRIMARY KEY  (`id`),
                    INDEX (`order_id`),
                    INDEX (`key`)
                ) $charset_collate;";

                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                dbDelta( $sql );
            }
        }
        function woocommerce_xpay_metabox() {
            global $theorder;
            $order_id = method_exists( $theorder, 'get_id' )?$theorder->get_id():$theorder->id;
            $status = self::get_metadata( $order_id, 'id_transaction' );
            if ( ! $status || empty( $status ) ) {
                echo __( 'Este pedido no fue procesado por Xpay.', 'woocommerce-xpay' );
                return;
            }
            
            ?>
            <table width="70%" style="width:70%">
            <?php
            echo self::showLabelMetabox( $order_id, 'id_transaction', __( 'Xpay Transaction ID', 'woocommerce-xpay' ) );
            echo self::showLabelMetabox( $order_id, 'status', __( 'Xpay Status', 'woocommerce-xpay' ) );
            echo self::showLabelMetabox( $order_id, 'string_amount_to_change', __( 'Monto Cobrado', 'woocommerce-xpay' ) );
            echo self::showLabelMetabox( $order_id, 'string_amount_to_paid', __( 'Monto Pagado', 'woocommerce-xpay' ) );
            echo self::showLabelMetabox( $order_id, 'string_fiat_amount_to_commerce', __( 'Monto recibido por el comercio en moneda Fiat', 'woocommerce-xpay' ) );
            echo self::showLabelMetabox( $order_id, 'string_crypto_amount_to_commerce', __( 'Monto recibido por el comercio en Criptomoneda', 'woocommerce-xpay' ) );
            ?>
            </table>
        <?php
        }
        public static function showLabelMetabox( $order_id, $id, $text, $is_price = false ) {
            $data = self::get_metadata( $order_id, $id );
            if ( false === $data || empty( $data ) ) {
                return;
            }
            ?>
            <tr>
                <td><strong><?php echo $text; ?>:</strong></td><td><?php echo $is_price?wc_price( $data ):$data; ?></td>
            </tr>
            <?php
        }
        /**
            * Gets the admin url.
            *
            * @return string
            */
        protected function admin_url() {
            if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
                return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=WC_Xpay_Gateway' );
            }
            return admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_Xpay_Gateway' );
        }

        /**
            * Error de email vacio
            *
            * @return string Error.
            */
        public function client_id_missing_message() {
            echo '<div class="error"><p><strong>' . __( 'Xpay', 'woocommerce-xpay' ) . '</strong>: ' . sprintf( __( 'Ingresa un e-mail valido. %s', 'woocommerce-xpay' ), '<a href="' . $this->admin_url() . '">' . __( 'Click aqui para configurarlo!', 'woocommerce-xpay' ) . '</a>' ) . '</p></div>';
        }


        /**
        * Error de clave vacia
        *
        * @return string Error.
        */
        public function client_secret_missing_message() {
            echo '<div class="error"><p><strong>' . __( 'Xpay', 'woocommerce-xpay' ) . '</strong>: ' . sprintf( __( 'Ingresa una clave valida. %s', 'woocommerce-xpay' ), '<a href="' . $this->admin_url() . '">' . __( 'Click aqui para configurarlo!', 'woocommerce-xpay' ) . '</a>' ) . '</p></div>';
        }

        /**
        * Error cuando credenciales son invalidos.
        *
        * @return string Error.
        */
        public function client_secret_invalid_message() {
            echo '<div class="error"><p><strong>' . __( 'Xpay Desactivado', 'woocommerce-xpay' ) . '</strong>: ' . sprintf( __( 'El e-mail o la clave son invalidos. %s', 'woocommerce-xpay' ), '<a href="' . $this->admin_url() . '">' . __( 'Click aqui para configurarlo!', 'woocommerce-xpay' ) . '</a>' ) . '</p></div>';
        }
        /**
        * Error cuando algun directorio no es escribible.
        *
        * @return string Error.
        */
        public function directory_nowrite( $name ) {
            echo sprintf( '<div class="error"><p><strong>' . __( 'El directorio <code>/wp-content/plugins/woocommerce-xpay/%s</code> del modulo Xpay no es escribible, cambielo a chmod 777.', 'woocommerce-xpay' ) . '</strong></p></div>', $name );
        }
        public function directory_logs_nowrite() {
            return $this->directory_nowrite( 'logs' );
        }
        public function directory_includes_nowrite() {
            return $this->directory_nowrite( 'includes' );
        }
        /**
            * Error de moneda no soportada
            *
            * @return string
            */
        public function currency_not_supported_message() {
            echo '<div class="error"><p><strong>' . __( 'Xpay', 'woocommerce-xpay' ) . '</strong>: ' . sprintf( __( 'Tu moneda no es soportada <code>%s</code>. Active una tasa de conversión en la configuración del módulo o use una de las siguientes monedas: ARS, VES, COP.', 'woocommerce-xpay' ), get_woocommerce_currency() ) . '</p></div>';
        }
    }
endif;
