<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WC_Cevnn_Helper
 */
class WC_Cevnn_Helper {

    /**
     * Gets the webhook URL for Cevnn triggers. Used mainly for
     * asyncronous redirect payment methods in which statuses are
     * not immediately chargeable.
     *
     * @since 2.0.0
     * @version 2.0.0
     * @return string
     */
    public static function get_webhook_url() {
        return add_query_arg( 'wc-api', 'wc_cevnn', trailingslashit( get_home_url() ) );
   }

}