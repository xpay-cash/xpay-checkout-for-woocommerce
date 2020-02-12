<?php
/**
 * Plugin Name: Xpay WooCommerce Plugin
 * Plugin URI: http://kijam.com/
 * Description: Xpay plugin for WooCommerce
 * Author: Kijam
 * Author URI: http://kijam.com/
 * Version: 1.0.1
 * License: MIT
 * Text Domain: woocommerce-xpay
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Xpay' ) ) :


    /**
     * WooCommerce Xpay main class.
     */
    class WC_Xpay {

        /**
         * Plugin version.
         *
         * @var string
         */
        const VERSION = '1.0.1';

        /**
         * Instance of this class.
         *
         * @var object
         */
        protected static $instance = null;

        /**
         * Initialize the plugin.
         */
        private function __construct() {
            // Load plugin text domain
            add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

            // Checks with WooCommerce is installed.
            if ( class_exists( 'WC_Payment_Gateway' ) ) {
                include_once 'includes/class-wc-xpay-gateway.php';
                add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
            } else {
                add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            }
        }

        /**
         * Return an instance of this class.
         *
         * @return object A single instance of this class.
         */
        public static function get_instance() {
            // If the single instance hasn't been set, set it now.
            if ( null == self::$instance ) {
                self::$instance = new self;
            }

            return self::$instance;
        }

        /**
         * Load the plugin text domain for translation.
         *
         * @return void
         */
        public function load_plugin_textdomain() {
            $locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-xpay' );
            load_textdomain( 'woocommerce-xpay', trailingslashit( WP_LANG_DIR ) . 'woocommerce-xpay/woocommerce-xpay-' . $locale . '.mo' );
            load_plugin_textdomain( 'woocommerce-xpay', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
        }

        /**
         * Add the gateway to WooCommerce.
         *
         * @param   array $methods WooCommerce payment methods.
         *
         * @return  array          Payment methods with Xpay.
         */
        public function add_gateway( $methods ) {
            if ( version_compare( self::woocommerce_instance()->version, '2.3.0', '>=' ) ) {
                $methods[] = WC_Xpay_Gateway::get_instance();
            } else {
                $methods[] = 'WC_Xpay_Gateway';
            }
            return $methods;
        }

        /**
         * WooCommerce fallback notice.
         *
         * @return  string
         */
        public function woocommerce_missing_notice() {
            echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Xpay Gateway depends on the last version of %s to work!', 'woocommerce-xpay' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">' . __( 'WooCommerce', 'woocommerce-xpay' ) . '</a>' ) . '</p></div>';
        }

        /**
         * Backwards compatibility with version prior to 2.1.
         *
         * @return object Returns the main instance of WooCommerce class.
         */
        public static function woocommerce_instance() {
            if ( function_exists( 'WC' ) ) {
                return WC();
            } else {
                global $woocommerce;
                return $woocommerce;
            }
        }
    }

    add_action( 'plugins_loaded', array( 'WC_Xpay', 'get_instance' ), 0 );
    function xpay_lv_metabox_cb() {
        $woocommerce = WC_Xpay::woocommerce_instance();
        $woocommerce->payment_gateways();
        do_action( 'woocommerce_xpay_metabox' );
    }
    function xpay_lv_metabox() {
        add_meta_box( 'xpay-metabox', __( 'Xpay Information', 'woocommerce-xpay' ), 'xpay_lv_metabox_cb', 'shop_order', 'normal', 'high' );
    }
    add_action( 'add_meta_boxes', 'xpay_lv_metabox' );
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'xpay_lv_add_action_links' );
    function xpay_lv_add_action_links( $links ) {
        $mylinks = array(
            '<a style="font-weight: bold;color: red" href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=xpay-gateway' ) . '">'.__('Setting', 'woocommerce-xpay').'</a>'
        );
        return array_merge( $links, $mylinks );
    }
    function xpay_lv_load_all() {
        WC_Xpay::get_instance();
        WC_Xpay_Gateway::get_instance();
        if ( isset($_POST['xpay-currency']) ) {
            if (defined('PHP_SESSION_NONE') && session_id() == PHP_SESSION_NONE || session_id() == '') {
                session_start();
            }
            $_SESSION['xpay-currency'] = $_POST['xpay-currency'];
        }
    }
    function xpay_lv_check_ipn() {
        WC_Xpay::get_instance();
        $instance = WC_Xpay_Gateway::get_instance();
        $phpinput = file_get_contents("php://input");
        if ( isset( $_GET['ipn-xpay'] ) ) {
            WC_Xpay_Gateway::debug( "IPN: " . $phpinput );
            $input = json_decode($phpinput, true);
            if ( isset( $input['status'] ) && (int)$_GET['ipn-xpay'] > 0 ) {
                $instance->checkIpn( (int)$_GET['ipn-xpay'] );
            }
        }
    }
    add_action( 'woocommerce_init', 'xpay_lv_load_all' );
    add_action( 'template_redirect', 'xpay_lv_check_ipn' );
endif;
