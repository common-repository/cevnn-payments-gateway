<?php
/**
 * Plugin Name: Cevnn Payments Gateway
 * Plugin URI: https://cevnn.com
 * Description: Allows your WooCommerce store to accept payments using your bank (e-Transfer/Debit)
 * Author: Cevnn Payments
 * Author URI: https://cevnn.com
 * Version: 1.2.2
 * Text Domain: wc-cevnn-gateway
 * Domain Path: /i18n/languages/
 *
 * @license GPLv2
 */


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function woocommerce_cevnn_missing_wc_notice() {
    echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Cevnn: Payments Via Banks - requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-gateway-cevnn' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

function cevnn_plugin_already_installed() {
    echo '<div class="error"><p><strong>' . esc_html__( 'Cevnn Notice: A plugin similar to Cevnn is installed. You need to disable them first in order to use Cevnn.' ) . '</strong></p></div>';
}

    function cevnn_check_php_version(){
        echo '<div class="error"><p>' . __('Cevnn requires PHP 5.6 to function properly. Please upgrade PHP. The Plugin has been <strong> auto-deactivated. </strong>', 'woocommerce-gateway-cevnn') . '</p></div>';
    }

if ( version_compare( PHP_VERSION, '5.6.0', '<' ) ) {
    add_action( 'admin_notices', 'cevnn_check_php_version');

    add_action( 'admin_init', 'cevnn_deactivate_self' );
    function cevnn_deactivate_self() {
        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
    return;
} else { //delete this


    /* Register activation hook. */
    register_activation_hook(__FILE__, 'cevnn_admin_activation_notice_hook');

    register_deactivation_hook(__FILE__, 'cevnn_deactivate');

    function cevnn_deactivate()
    {

        delete_option('cevnn_plugin_installed');
        $payment_gateways = WC_Payment_Gateways::instance();


        $payment_gateway = $payment_gateways->payment_gateways()["cevnn"] ?? false;
        if ($payment_gateway) {
            $setting = new WC_Cevnn_Settings($payment_gateway->apiKey, $payment_gateway->iframeKey, null, null, null);
            $rest_template = new class_wc_cevnn_rest_template($setting);

            $rest_template->de_register_webhooks();
        }

    }


    function cevnn_admin_activation_notice_hook()
    {

        /* Create transient data */
        set_transient('fx-admin-notice', true, 5);
    }

    /* Add admin notice */
    add_action('admin_notices', 'cevnn_admin_activation_notice');


    function cevnn_admin_activation_notice()
    {

        if (get_transient('fx-admin-notice')) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>Cevnn: Confirm settings are correct by ensuring all fields are accurately filled and <strong>saving
                        your settings</strong>. Go to <a
                            href="/wp-admin/admin.php?page=wc-settings&tab=checkout&section=cevnn">Cevnn Settings</a>
                </p>
            </div>
            <?php
            delete_transient('fx-admin-notice');
        }

    }

    add_action('plugins_loaded', 'woocommerce_gateway_cevnn_init');


    register_activation_hook(__FILE__, 'install_cevnn');

    /**
     * This function runs when WordPress completes its upgrade process
     * It iterates through each plugin updated to see if ours is included
     * @param $upgrader_object Array
     * @param $options Array
     */
        function cevnn_wp_upe_upgrade_completed($upgrader_object, $options)
        {
            // The path to our plugin's main file
            $our_plugin = plugin_basename(__FILE__);
            // If an update has taken place and the updated type is plugins and the plugins element exists
            if ($options['action'] == 'update' && $options['type'] == 'plugin' && isset($options['plugins'])) {
                // Iterate through the plugins being updated and check if ours is there
                foreach ($options['plugins'] as $plugin) {
                    if ($plugin == $our_plugin) {
                        $payment_gateways = WC_Payment_Gateways::instance();


                        $payment_gateway = $payment_gateways->payment_gateways()["cevnn"];
                        if ($payment_gateway) {
                            $setting = new WC_Cevnn_Settings($payment_gateway->apiKey, $payment_gateway->iframeKey, null, null, null);
                            $rest_template = new class_wc_cevnn_rest_template($setting);

                            //todo: discuss with Saad
                         $result = $rest_template->register_webhooks();
                            if(!$result['success']){
                                $this->update_option( 'enabled', 'no' );
                                $this->enabled = false;
                                $transient = 'duplicate-admin-notice';
                                $this->display_message($result['message'], $transient);
                            }

                        }
                        // Set a transient to record that our plugin has just been updated
                        set_transient('wp_upe_updated', 1);
                    }
                }
            }
        }
    add_action('upgrader_process_complete', 'cevnn_wp_upe_upgrade_completed', 10, 2);

    /**
     * Show a notice to anyone who has just updated this plugin
     * This notice shouldn't display to anyone who has just installed the plugin for the first time
     */
        function cevnn_register_webhooks_admin_notice(){
        // Check the transient to see if we've just updated the plugin
        if (get_transient('wp_upe_updated')) {
            echo '<div class="notice notice-success">' . __('Thanks for updating. Webhooks are now registered', 'wp-upe') . '</div>';
            delete_transient('wp_upe_updated');
        }
    }

    add_action( 'admin_notices', 'cevnn_register_webhooks_admin_notice' );

    function install_cevnn() {

        $page = get_page_by_title('Payment Via Bank' );
        if ($page == NULL) {

            $payment_page = array(
                'post_title' => wp_strip_all_tags('Payment Via Bank'),
                'post_content' => '[cevnn_iframe]',
                'post_status' => 'publish',
                'post_author' => 1,
                'post_type' => 'page',
                'post_parent' => get_option( 'woocommerce_checkout_page_id' ),
            );

            // Insert the post into the database
            wp_insert_post($payment_page);

        } else if( get_post_status( $page->ID ) == "trash" ) {
            wp_publish_post( $page->ID );
        }

    }

    add_filter( 'plugin_action_links', 'cevnn_add_settings_link', 10, 2 );
        function cevnn_add_settings_link($links, $file){

            if ($file === 'cevnn-payments-gateway/cevnn-payment-gateway.php') {
                if (current_filter() === 'plugin_action_links') {
                    $url = admin_url('admin.php?page=wc-settings&tab=checkout&section=cevnn');
                }

                $links = (array)$links;
                $links[] = sprintf('<a href="%s">%s</a>', $url, __('Settings', 'woocommerce-gateway-cevnn'));
            }

            return $links;
        }



    function woocommerce_gateway_cevnn_init() {

        if(!get_option('cevnn_plugin_installed') && ( get_option('smartpay_plugin_installed') || get_option('emt_plugin_installed'))){
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
            deactivate_plugins(plugin_basename(__FILE__));
            add_action('admin_notices', 'cevnn_plugin_already_installed');
            return;
        } else {
            add_option( 'cevnn_plugin_installed', true, '', 'yes' );
        }


        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', 'woocommerce_cevnn_missing_wc_notice' );
            return;
        }

        if ( ! class_exists('WC_Cevnn_Loader') ) :


            final class WC_Cevnn_Loader {


                /**
                 * @var Singleton The reference the *Singleton* instance of this class
                 */
                private static  $instance;


                /**
                 * Returns the *Singleton* instance of this class.
                 *
                 * @return Singleton The *Singleton* instance.
                 */
                static function get_instance() {
                    if( self::$instance == null) {
                        self::$instance = new self();
                    }
                    return self::$instance;


                }

                /**
                 * Private clone method to prevent cloning of the instance of the
                 * *Singleton* instance.
                 *
                 * @return void
                 */
                private function __clone() {}

                /**
                 * Private unserialize method to prevent unserializing of the *Singleton*
                 * instance.
                 *
                 * @return void
                 */
                private function __wakeup() {}


                /**
                 * Protected constructor to prevent creating a new instance of the
                 * *Singleton* via the `new` operator from outside of this class.
                 */
                private function __construct()
                {
                    $this->init();
                }


                public function init(){

                    // Load gateway files
                    require_once dirname( __FILE__ ) . '/includes/class_wc_cevnn_gateway.php';


                    //Load the gateway into Woocommerce\
                    add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );




                }


                /**
                 * Add the gateways to WooCommerce.
                 *
                 */
                public function add_gateways( $methods ) {

                    // load the gateway class
                    $methods[] = 'WC_Cevnn_Gateway';

                    return $methods;
                }


                public function plugin_url() {
                    return untrailingslashit( plugin_dir_path( __FILE__ ) );
                }


            }




            WC_Cevnn_Loader::get_instance();

        endif;


    }
}



