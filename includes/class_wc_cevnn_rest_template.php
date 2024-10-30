<?php

define('WC_CEVNN_CURR_VER', 'V2-0');


require(dirname(__FILE__) . '/' . WC_CEVNN_CURR_VER . '/cevnn_properties.php');

include_once('class-wc-cevnn-helper.php');

/**
 * A templates that performs a REST API calls to Cevnn
 */
class class_wc_cevnn_rest_template
{
    /**
     * @var string The authentication  URL to authenticate on Cevnn
     */
    private static $authn_url = WC_CEVNN_API_URL . WC_CEVNN_API_VER . WC_CEVNN_AUTHN_URL;

    /**
     * @var string The Cevnn order URL
     */
    private static $order_url = WC_CEVNN_API_URL . WC_CEVNN_API_VER . WC_CEVNN_ORDER_URL;

    /**
     * @var string The Cevnn invoice URL
     */
    private static $invoice_url = WC_CEVNN_API_URL . WC_CEVNN_API_VER . WC_CEVNN_INVOICES_URL;

    private static $get_webhooks_url = WC_CEVNN_API_URL . WC_CEVNN_API_VER . WC_CEVNN_GET_WEBHOOKS_URL;

    private static $create_webhooks_url = WC_CEVNN_API_URL . WC_CEVNN_API_VER . WC_CEVNN_CREATE_WEBHOOKS_URL;

    private static $modify_webhooks_url = WC_CEVNN_API_URL . WC_CEVNN_API_VER . WC_CEVNN_MODIFY_WEBHOOKS_URL;

    private static $delete_webhooks_url = WC_CEVNN_API_URL . WC_CEVNN_API_VER . WC_CEVNN_DELETE_WEBHOOKS_URL;

    private static $refunds_path_url = WC_CEVNN_REFUNDS_WEBHOOKS_URL;

    /**
     * @var The Cevnn authentication token
     */
    private $cevnn_auth_token;

    /**
     * @var The Cevnn webhooks to register
     */
    private $cevnn_webhooks = ['invoice_created' => 'invoice/created', 'invoice_paid' => 'invoice/paid', 'invoice_refunded' => 'invoice/refunded', 'order_created' => 'order/created'];

    public function __construct($WC_Cevnn_Settings)
    {
        $this->cevnn_settings = $WC_Cevnn_Settings;
        // Retrieve authentication token from Cevnn
        $this->cevnn_auth_token = $this->build_cevnn_authn();

    }

    public function get_cevnn_auth_token()
    {
        return $this->cevnn_auth_token;
    }

    /**
     * Creates, builds Cevnn authentication and attempts to authenticate given the API key
     * @return Returns the authentication token
     */
    private function build_cevnn_authn()
    {

        $headers = array(
            'Authorization' => 'Basic ' . base64_encode($this->cevnn_settings->getIframeKey() . ':' . $this->cevnn_settings->getApiKey())
        );
        $authn = $this->make_request("GET", self::$authn_url, $headers);
//        error_log(print_r('token recieved:', true));
//        error_log(print_r($authn, true));
        return isset($authn['result']['access_token']) ? $authn['result']['access_token'] : '';

    }

    /**
     * @param $for
     * @return array
     */
    public function get_webhook_data($for)
    {
        return array(
            'topic' => $for,
            'url' => WC_Cevnn_Helper::get_webhook_url()
        );
    }

    public function check_cevnn_webhook_exist()
    {
        $result = array('success' => false, "message" => 'The iFrame key and API key inputted, has already been used on another store. To continue using Cevnn Payment Gateway on this store, please deactivate Cevnn plugin on other stores.');
        $check_webhook_exist = $this->make_request("GET", self::$get_webhooks_url, $this->init_auth_bearer_header());
//        error_log(print_r('check _cevnn_webhook_exist api call:', true));
//        error_log(print_r($check_webhook_exist, true));
        if ($check_webhook_exist['number_of_results'] == 0) {
            $response = $this->register_webhooks();
//            error_log(print_r('coming in response inner', true));
//            error_log(print_r($response, true));
            return $response;
        } else {
            if (parse_url($check_webhook_exist['result'][0]['url'])["host"] !== parse_url(trailingslashit(get_home_url()))["host"]) {
                return $result;
            }
        }

        return array('success' => true, "message" => 'Cevnn settings have been saved.');

    }

    /**
     * create webhook for a topic
     * @param $for
     */
    public function create_webhook($for)
    {
        $data = $this->get_webhook_data($for);
        return $this->make_request("POST", self::$create_webhooks_url, $this->init_auth_bearer_header(), $data);
    }

    /**
     * register webhooks for all the topics
     */
    public function register_webhooks()
    {
        $result = array('success' => true, "message" => 'success');

        foreach ($this->cevnn_webhooks as $key => $value) {

            $response = $this->create_webhook($value);

            if (!isset($response['result']) || !isset($response['result']['webhook_id'])) {

                //todo: when do we clear logs
                error_log(print_r($response, true));

                $result['success'] = false;
                $result['message'] = 'Cevvn was unable to register webhook for your website.';

                if (isset($response['errors']) && isset($response['fields']) && isset($response['fields']['url'])) {

                    $result['message'] = 'Your website URL is not accessible by Cevnn or It is taking too much time to process the request.';

                }

                return $result;
            }


        }

        return $result;
    }

    /**
     * Makes a request given the URL, method, and request data
     * @param $method the HTTP method
     * @param $url the URL to make request to
     * @param $requestData the request data
     * @param $headers the headers to includes
     * @param bool $as_json_response a boolean value to indicates whether to return a Json response.
     * Default is true
     * @return The JSON response of the result request
     */
    private function make_request($method, $url, $headers, $requestData = [], $as_json_response = true)
    {

        //todo: handle exception here
        $args = array(
            'body' => $requestData,
            'timeout' => '30',
            'redirection' => '10',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => $headers,
            'cookies' => array(),
            'method' => $method
        );

        $response = wp_remote_request($url, $args);
        if ($as_json_response) {
            $response = wp_remote_retrieve_body($response);
            return json_decode($response, true);
        }
        return $response;
    }

    /**
     * Returns a list of orders who their give status
     * @param $status the status to query for the orders
     * @return stdClass|WC_Order[] of orders who their status is on-hold
     */
    private function get_wc_orders_by_status($status)
    {
        return $wc_orders = wc_get_orders(array(
            'status' => $status
        ));

    }

    /**
     * @param $entity
     * @param $value
     * @param $url
     * @return mixed
     */
    public function get_cevnn_details($entity, $value, $url)
    {

        $requestData = array(
            'format' => 'json',
            $entity => $value
        );

        $results = $this->make_request("GET", $url, $this->init_auth_bearer_header(), $requestData)['result'];
        // return the first result that is enabled and not dummy
        if (!empty($results)) {
            foreach ($results as $result) {
                if (($result['is_enabled'] === true) and ($result['is_dummy'] === false)) {
                    return $result;
                }
            }
        }
    }

    /**
     * Returns the the query result information of an order Cevnn for the given order
     * @param $wc_order the order to query Cevnn
     * @return the the query result from Cevnn if it exists. Otherwise, null
     */
    public function get_cevnn_order($wc_order)
    {
        return $this->get_cevnn_details('custom_order_id', $wc_order->get_id(), self::$order_url);
    }

    /**
     * Returns the Cevnn invoice for the given Cevnn Order
     * @param $cevnn_order the Cevnn order to query for an invoice
     * @return  the Cevnn invoice for the given Cevnn Order |null
     */
    public function get_cevnn_invoice($cevnn_order)
    {
        return $this->get_cevnn_details('order_id', $cevnn_order['order_id'], self::$invoice_url);
    }

    /**
     * Updates the woocommerce orders
     * @throws WC_Data_Exception if an exception has occured
     */
    public function update_wc_order_status()
    {

        $all_on_hold_pending_orders = array_merge($this->get_wc_orders_by_status('pending'), $this->get_wc_orders_by_status('on-hold'));

        foreach ($all_on_hold_pending_orders as $order) {

            $cevnn_order = $this->get_cevnn_order($order);

            if ($cevnn_order != null) {

                $cevnn_invoice = $this->get_cevnn_invoice($cevnn_order);

                if ($cevnn_invoice != null) {

                    $payment_method = $cevnn_invoice['payment_method'];
                    $cevnn_invoice_id = $cevnn_invoice['invoice_id'];
                    if (!empty($cevnn_invoice['date_paid'])) {

                        if ($payment_method == 'e') {
                            $payment_method = 'Interac E-transfer';
                        } else {
                            $payment_method = 'Debit Card';
                        }

                        $order->update_status('processing', 'Payment has been completed using payment method: ' . $payment_method . ' Cevnn Charge ID: <b>' . $cevnn_invoice_id . '</b>');

                    } else {
                        $order->update_status('on-hold', 'Payment is on hold using payment method: ' . $payment_method . ' Cevnn Charge ID: <b>' . $cevnn_invoice_id . '</b>');

                    }
                    $order->update_meta_data('_cevnn_invoice_id', $cevnn_invoice_id);
                    $order->update_meta_data('_cevnn_order_id', $cevnn_order['order_id']);
                    $order->update_meta_data('_cevnn_payment_method', $payment_method);

                    $order->set_payment_method($payment_method);

                }
            }

        }
    }

    /**
     * @param $id
     * @return bool|WC_Order|WC_Order_Refund
     */
    public function get_wc_orders_by_order_id($id)
    {
        return wc_get_order($id);
    }

    /**
     * Prepares the Authorization Header to be added to array of headers
     *
     * @return array the array of headers which contains Authorization header
     */
    private function init_auth_bearer_header()
    {
        return array('Authorization' => 'Bearer ' . $this->cevnn_auth_token);
    }

    /**
     * @param $cevnn_invoice_id
     * @param int $amount the amount to be refunded. Default is zero
     * @return Cevnn response
     */
    private function notify_cevnn_refund($cevnn_invoice_id, $amount = 0)
    {
        $requestData = [];
        if ($amount != 0) {
            $requestData = array(
                'amount' => $amount
            );

        }

        return $this->make_request("PUT", self::$invoice_url . "/" . $cevnn_invoice_id . self::$refunds_path_url, $this->init_auth_bearer_header(), $requestData, false);

    }

    /**
     * @param $wc_order
     * @param $partial_refund_amount
     * @return true if the refund notified the cevnn successfully. Otherwise false
     */
    public function process_wc_order_refund($wc_order, $partial_refund_amount = 0)
    {
        //check if the order status if its processing or completed
        if ($wc_order->get_status() == 'processing' || $wc_order->get_status() == 'completed') {
            $cevnn_order = $this->get_cevnn_order($wc_order);
            if ($cevnn_order != null) {
                $cevnn_invoice = $this->get_cevnn_invoice($cevnn_order);
                $response = $this->notify_cevnn_refund($cevnn_invoice['invoice_id'], $partial_refund_amount);
                $httpCode = wp_remote_retrieve_response_code($response);
                if ($httpCode == 200) {
                    $wc_order->add_order_note('Order refund process has been initiated for'
                        . ' Cevnn  invoice  ID: <b>' . $cevnn_invoice['invoice_id']
                        . '. Please await confirmation from Cevnn. Note this process may take a few days.');
                    return true;
                } else {
                    $wc_order->add_order_note('Order refund process has been declined for'
                        . ' Cevnn  invoice  ID: <b>' . $cevnn_invoice['invoice_id']
                        . '. Reason:' . $response['errors']['400'] . ' Contact Cevnn to resolve the issue');
                    return false;
                }

            }

        }
        return false;

    }

    /**
     * De-registers webhooks from Cevnn
     */
    public function de_register_webhooks()
    {
        $webhooks = $this->make_request("GET", self::$get_webhooks_url, $this->init_auth_bearer_header())['result'];
        foreach ($webhooks as $webhook) { //foreach element in $arr
            $this->make_request("DELETE", self::$get_webhooks_url . '/' . $webhook['webhook_id'], $this->init_auth_bearer_header());
        }
    }

}