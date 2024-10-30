<?php

require('class-wc-cevnn-settings.php');
require('class_wc_cevnn_rest_template.php');
require_once('class-wc-cevnn-helper.php');


/**
 * A gateway class that initiates a payment through Cevnn
 *
 */
class WC_Cevnn_Gateway extends WC_Payment_Gateway
{

    /**
     * @var
     */
    private $wc_postId;

    /**
     * @var WC_Cevnn_Settings
     */
    public $WC_Cevnn_Settings;

    /**
     * WC_Cevnn_Gateway constructor.
     */
    function __construct()
    {
        $this->id = 'cevnn';
        $this->icon = apply_filters( 'wc_cevnn_gateway_icon', plugins_url( '/assets/images/banking.png', dirname( __FILE__ ) ) );
        $this->has_fields = false;
        $this->method_title = 'Cevnn - Payments via Canadian Banks';
        //$this->method_description = sprintf( __( 'Cevnn works by adding payment fields on the checkout and then sending the details to Cevnn for verification. <a href="%1$s" target="_blank">Sign up</a> for a Stripe account, and <a href="%2$s" target="_blank">get your Stripe account keys</a>.', 'woocommerce-gateway-stripe' ), 'https://dashboard.stripe.com/register', 'https://dashboard.stripe.com/account/apikeys' );
        $this->method_description = 'Pay online by debit or eTransfer using Cevnn Gateway - https://cevnn.com for your store keys.';
        $this->order_button_text = 'Place Order';

        //load form fields
        $this->init_form_fields();

        //initialize settings
        $this->init_settings();

        //define user set variables
        $this->enabled        = $this->get_option( 'enabled' );
        $this->title          = $this->get_option('title');
        $this->description    = $this->get_option('description');
        $this->ctaEnable      = $this->get_option('cta-enable');
        $this->apiKey         = $this->get_option('ApiKey');
        $this->iframeKey      = $this->get_option('iframeKey');
        $this->payment_page_name = $this->get_option('payment_page');

        //Instance of gateway settings

        $this->WC_Cevnn_Settings = new WC_Cevnn_Settings($this->apiKey,$this->iframeKey, $this->description, $this->title, $this->payment_page_name);

        $this->supports           = array(
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'refunds'
        );

        add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) ); // admin processing
        // add_filter('cron_schedules', array($this, 'cevnn_order_status_cron_interval'));

        add_filter('woocommerce_settings_saved', array($this,'is_valid_for_use'));
        // Check if the gateway can be used

        add_filter('woocommerce_settings_saved', array($this,'is_webhooks_active'));

        /*
         *
                if (!wp_next_scheduled('cevnn_order_status_cron_hook')) {
                    wp_schedule_event(time(), '15min', 'cevnn_order_status_cron_hook');
                }
        */
        //add_action( 'cevnn_order_status_cron_hook', array($this,'cevnn_order_status_cron' ));

        add_action( 'woocommerce_cart_totals_after_order_total', array($this,'add_cevnn_cta' ), 10);

        //Webhook action
        add_action( 'woocommerce_api_wc_cevnn', array( $this, 'check_for_webhook' ) );

        // Refund action
        add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );


    }

    /**
     *
     */
    public function add_cevnn_cta(){

        if($this->ctaEnable == 'yes'){
            $custom_logo_id = get_theme_mod( 'custom_logo' );
            $merchantLogo = wp_get_attachment_image_src( $custom_logo_id , 'thumbnail' );

            if(!empty($merchantLogo[0])){
                echo "<div class='online-debit-cta'><img class='online-debit-cta-img' src='". $merchantLogo[0] . "'><p class='center-cta'>Now accepting payments through <b>Online Banking</b></p></div>";
            }else{
                echo "<div class='online-debit-cta'><p class='center-cta-p'>Now accepting payments through <b>Online Banking</b></p></div>";
            }

        }

    }


    /**
     * Returns the current Word Press post
     * @return  the current Word Press post
     */
    public function getWcPostId()
    {
        return $this->wc_postId;
    }

    /**
     * A cron job hook that looks the performse query REST API calls
     * to Cevnn to update the orders
     */

    function cevnn_order_status_cron(){

        $rest_call = new class_wc_cevnn_rest_template($this->WC_Cevnn_Settings);

        $rest_call->update_wc_order_status();

    }

    /**
     * Sets the configuration schedules of the cron job
     * @param $schedules the schedules to configure
     * @return the configured $schedules
     */

    function cevnn_order_status_cron_interval($schedules)
    {

        $schedules['15min']  = array(
            'interval' => 1 * 60,
            'display' => esc_html__('Every 1 Minutes')
        );
        return $schedules;
    }


    /**
     * Sets the Post ID
     * @param $wc_postId the Post ID to set to
     */
    public function setWcPostId($wc_postId)
    {
        $this->wc_postId = $wc_postId;
    }

    /**
     * An a hook to registers scripts during the  initialization of the payment gateway
     */
    function register_scripts() {
        wp_register_style( 'cevnn-style', plugins_url('../assets/css/cevnn-style.css', __FILE__), array(), '1.0.0', 'all');
        wp_enqueue_style( 'cevnn-style' );
        /*
        wp_register_script( 'cevnn-js', plugins_url( '/assets/js/cevnn.js', dirname( __FILE__ ) ), array('jquery'));
        wp_enqueue_script( 'cevnn-js' );
        */
    }

    /**
     * Initializes the payment gateway user settings from a file
     */
    public function init_form_fields() {
        $this->form_fields = require( dirname( __FILE__ ) . '/admin/cevnn-settings.php' );
    }


    /**
     * Processes the current order through Cevnn channel by building a redirect URL
     * that redirects to Cevnn.
     *
     * @param   $order_id the order ID to redirect to Cevnn
     * @return array an array that contains the successful redirect URL
     */
    public function process_payment( $order_id ) {

        $page_slug = get_permalink($this->WC_Cevnn_Settings->get_payment_page_name());
        $order = wc_get_order( $order_id );

        /*
        if(wcs_order_contains_subscription( $order )){

        };

        */
        $redirect_url = substr($page_slug, 0, -1). '?' . http_build_query(array('orderId' => $order_id));


        $order->update_status('pending', 'Payment is on pending and awaiting verification from Cevnn');

        return array(
            'result' => 'success',
            'redirect' => $redirect_url
        );

    }

    /**
     * Returns and buils the URL query parameters from the given settings
     * for the given order
     * @param $order the order to build the URL query parameter
     * @return string the string that contains the URL query parameters
     */
    private function build_cevnn_iframe_query_param($order) {
        return http_build_query(array(
            'key' => $this->WC_Cevnn_Settings->getIframeKey(),
            'singleAmt' => $order->get_total(),
            'curr' => $order->get_currency(),
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'address' => $order->get_billing_address_1(),
            'email' => $order->get_billing_email(),
            'city' => $order-> get_billing_city(),
            'region' => $order->get_billing_state(),
            'country' => $order->get_billing_country(),
            'postalCode' => $order->get_billing_postcode(),
            'phone' => $phone = preg_replace('/[^0-9+-]/', '', $order->get_billing_phone()),
            'customOrderID' => $order->get_id(),
            'customConsumerID' => $order->get_customer_id()

        ) );


    }

    /**
     * A shortcode that injects an Iframe page once an order is checked out
     * @return string contains Iframe page that process a payment through Cevnn
     */
    public function cevnn_iframe_shortcode(){

        $order_id = sanitize_text_field($_GET['orderId']);
        if (!$order_id || !is_numeric($order_id)) {
            return 'null' ;
        }
        if (!function_exists('wp_get_current_user')) {
            include (ABSPATH . "wp-includes/pluggable.php") ;
        }

        $order = wc_get_order($order_id) ;

        if($order){

            $iframe_url = 'https://checkout.cevnn.com?' . $this->build_cevnn_iframe_query_param($order) ;

            WC()->cart->empty_cart();

            return '<div class="iframe-container"><iframe class="cevnn_iframe" src="' . $iframe_url . '" width="100%" height="800px" style="height:100%;"></iframe></div>';
        }

    }

    /**
     * A validation action to check if certain settings are configured in place to allow the plugin to work
     *
     *  Returns true if all the validations holds. Otherwise, false
     */
    public function is_valid_for_use()
    {

        if ('CAD' != get_woocommerce_currency()) {
            $this->update_option('enabled', 'no');
            $this->enabled = false;
            $transient = 'cad-admin-notice';
            $this->display_message('WooCommerce currency is not set to Canadian Dollars currency \'CAD\'.
             Cevnn gateway plugin will be disabled', $transient);

        }

    }

    public function display_message($message, $transient){
        if (!get_transient($transient)) {
            set_transient($transient, true, 20);
            WC_Admin_Settings::add_error(__($message, 'woocommerce-gateway-cevnn'));
        }
    }

    /**
     *
     */
    public function is_webhooks_active(){

        if(!empty($this->WC_Cevnn_Settings->getApiKey())) {

            $rest_call = new class_wc_cevnn_rest_template($this->WC_Cevnn_Settings);
            if($rest_call->get_cevnn_auth_token()){
                $check_webhooks =  $rest_call->check_cevnn_webhook_exist();
                error_log(print_r('coming in response', true));
                error_log(print_r($check_webhooks, true));
                if(!$check_webhooks['success']){
                    $this->update_option( 'enabled', 'no' );
                    $this->enabled = false;
                    $transient = 'duplicate-admin-notice';
                    $this->display_message($check_webhooks['message'], $transient);
                }

            } else {
                $this->update_option( 'enabled', 'no' );
                $this->enabled = false;
                $transient = 'wrong-keys-admin-notice';
                $this->display_message('Your Iframe and/or API key is invalid. Please double check before saving. ', $transient);

            }

        }

    }

    /**
     * Displays the admin settings webhook description.
     *
     * @since 2.0.0
     * @return mixed
     */
    public function display_admin_settings_webhook_description() {
        return sprintf( __( 'Cevnn Webhooks have been registered to your store. This will enable you to receive notifications on the charge statuses.', 'woocommerce-gateway-cevnn' ), WC_Cevnn_Helper::get_webhook_url() );
    }

    /**
     * Check incoming requests for Cevnn Webhook data and process them.
     *
     * @since 2.0.0
     * @version 2.0.0
     */
    public function check_for_webhook() {
        if ( ( 'POST' !== $_SERVER['REQUEST_METHOD'] )
            || ! isset( $_GET['wc-api'] )
            || ( 'wc_cevnn' !== $_GET['wc-api'] )
        ) {
            return;
        }

        $request_body    = file_get_contents( 'php://input' );
        $request_headers = array_change_key_case( $this->get_request_headers(), CASE_UPPER );

        // Validate it to make sure it is legit.
        if ( $this->is_valid_request( $request_headers, $request_body ) ) {
            $this->process_webhook( $request_body , $request_headers['X-SMARTPAY-TOPIC']);
            status_header( 200 );
            exit;
        } else {
            status_header( 400 );
            exit;
        }
    }

    /**
     * Processes the incoming webhook.
     *
     * @since 2.0.0
     * @version 2.0.0
     * @param string $request_body
     */
    private function process_webhook( $request_body, $topic ) {
        $notification = json_decode( $request_body );

        switch ($topic ) {
            case 'invoice/paid':
                $this->process_webhook_invoice_paid( $notification );
                break;
            case 'invoice/cancelled':
            case 'order/cancelled':
                $this->process_webhook_invoice_cancelled( $notification );
                break;
            case 'order/created':
            case 'invoice/created':
                error_log(print_r('test'));
                $this->process_webhook_order_created( $notification );
                break;
            case 'invoice/refunded':
                $this->process_webhook_refund( $notification );
                break;

        }
    }

    /**
     * Verify the incoming webhook notification to make sure it is legit.
     *
     * @since 2.0.0
     * @version 2.0.0
     * @todo Implement proper webhook signature validation. Ref https://stripe.com/docs/webhooks#signatures
     * @param string $request_headers The request headers from Cevnn.
     * @param string $request_body The request body from Cevnn.
     * @return true if the request. Otherwise, false
     */
    public function is_valid_request( $request_headers = null, $request_body = null ) {
        if ( null === $request_headers || null === $request_body ) {
            return false;
        }
        // remove this later
        return true;

        $hmac = base64_encode(hash_hmac('sha256', $request_body, $this->WC_Cevnn_Settings->getIframeKey(), true));

        return $request_headers['X-SmartPay-Hmac-Sha256'] == $hmac;

        return true;
    }

    /**
     * Gets the incoming request headers. Some servers are not using
     * Apache and "getallheaders()" will not work so we may need to
     * build our own headers.
     *
     * @since 2.0.0
     * @version 2.0.0
     */
    public function get_request_headers() {
        if ( ! function_exists( 'getallheaders' ) ) {
            $headers = array();

            foreach ( $_SERVER as $name => $value ) {
                if ( 'HTTP_' === substr( $name, 0, 5 ) ) {
                    $headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
                }
            }

            return $headers;
        } else {
            return getallheaders();
        }
    }

    private function get_message($notification){
        $platform =  $notification->data->payment_method ?? null;
        $message = "";
        if($platform === 'e')
        {
            $message = " Payment was made through E-transfer.";

        } elseif($platform === 'd') {
            $message = " Payment was made through Online Banking.";
        }

        return $message;
    }


    /**
     * @param $notification
     */
    private function process_webhook_invoice_paid($notification) {
        $rest_call = new class_wc_cevnn_rest_template($this->WC_Cevnn_Settings);
        $wc_order =  $rest_call->get_wc_orders_by_order_id($notification->data->order_custom_id);
        $cevnn_order = $rest_call->get_cevnn_order($wc_order);
        $cevnn_invoice = $rest_call->get_cevnn_invoice($cevnn_order);

        if($wc_order->get_status() != 'completed'){
            $wc_order->update_status('processing', 'Payment received,<strong> Invoice ID: ' . $cevnn_invoice['invoice_id'] . '</strong>.'  . $this->get_message($notification));
        }else{

            $wc_order->add_order_note( 'Payment received,<strong> Invoice ID: ' . $cevnn_invoice['invoice_id'] . '</strong>.'  . $this->get_message($notification));
        }



    }

    /**
     * @param $notification
     */
    private function process_webhook_invoice_cancelled($notification) {
        $rest_call = new class_wc_cevnn_rest_template($this->WC_Cevnn_Settings);
        $wc_order =  $rest_call->get_wc_orders_by_order_id($notification->data->order_custom_id);
        $cevnn_order = $rest_call->get_cevnn_order($wc_order);
        $cevnn_invoice = $rest_call->get_cevnn_invoice($cevnn_order);
        $wc_order->update_status('on-hold', 'Cevnn invoice generated, <strong> Invoice ID: ' . $cevnn_invoice['invoice_id'] . '<strong>.' . $this->get_message($notification));


    }

    /**
     * @param $notification
     */
    private function process_webhook_order_created($notification) {
        error_log('order created', 3, plugin_dir_path(__FILE__) . 'debug.log');

        $rest_call = new class_wc_cevnn_rest_template($this->WC_Cevnn_Settings);

        $wc_order = $rest_call->get_wc_orders_by_order_id($notification->data->order_custom_id);

        if ($wc_order->get_status() !== 'processing') {

            $cevnn_order = $rest_call->get_cevnn_order($wc_order);
            error_log('got cevnn order details', 3, plugin_dir_path(__FILE__) . 'debug.log');
            error_log(print_r($cevnn_order), 3, plugin_dir_path(__FILE__) . 'debug.log');

            $cevnn_invoice = $rest_call->get_cevnn_invoice($cevnn_order);
            error_log('got cevnn invoice details', 3, plugin_dir_path(__FILE__) . 'debug.log');
            error_log(print_r($cevnn_order), 3, plugin_dir_path(__FILE__) . 'debug.log');


            if ($wc_order->get_status() == 'completed'){

                $wc_order->add_order_note( 'Cevnn: Invoice created,<strong> Invoice ID: ' . $cevnn_invoice['invoice_id'] . '</strong>.' );

                return;
            }

            $wc_order->update_status('on-hold', 'Cevnn invoice generated, <strong> Invoice ID: ' . $cevnn_invoice['invoice_id'] . '</strong>.' . $this->get_message($notification));
            error_log('status changed to on hold', 3, plugin_dir_path(__FILE__) . 'debug.log');
    
        }


    }

    /**
     * Process webhook refund.
     *
     * @since 4.0.0
     * @version 4.0.0
     * @param object $notification
     */
    public function process_webhook_refund( $notification ) {
        $rest_call = new class_wc_cevnn_rest_template($this->WC_Cevnn_Settings);
        $order =  $rest_call->get_wc_orders_by_order_id($notification->data->order_custom_id);


        if ( ! $order ) {
            return;
        }

        $refund_message =  'Refund has been successfully processed by Cevnn';

        $order->add_order_note( $refund_message );
        $order->update_status('refunded');

    }


    /**
     * Refund a charge from 'Refund' Button
     *
     * @param  int $order_id
     * @param  float $amount
     * @return bool true if the refund notified the cevnn. Otherwise false
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {

        $order = wc_get_order( $order_id );

        if ( ! $order  || $amount == 0  || count($order->get_refunds()) > 1) {
            return false;
        }
        if(! ($order->get_status() == 'processing' || $order->get_status() == 'completed') ) {
            return false;
        }

        $rest_call = new class_wc_cevnn_rest_template($this->WC_Cevnn_Settings);

        return $rest_call->process_wc_order_refund($order, $amount != $order->get_total() ? $amount : 0);
    }









}

$WC_Cevnn_Gateway = new WC_Cevnn_Gateway();
add_shortcode('cevnn_iframe', [$WC_Cevnn_Gateway, 'cevnn_iframe_shortcode']) ;
add_action( 'woocommerce_before_checkout_form', array($WC_Cevnn_Gateway,'add_cevnn_cta' ), 10,3);
