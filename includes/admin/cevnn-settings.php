<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


return apply_filters(
	'wc_cevnn_settings',
	array(
		'enabled'                       => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-cevnn' ),
			'label'       => __( 'Enable Cevnn', 'woocommerce-gateway-cevnn' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title'                         => array(
			'title'       => __( 'Title', 'woocommerce-gateway-cevnn' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-cevnn' ),
			'default'     => __( 'Pay directly from your bank', 'woocommerce-gateway-cevnn' ),
			'desc_tip'    => true,
		),
		'description'                   => array(
			'title'       => __( 'Description', 'woocommerce-gateway-cevnn' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-cevnn' ),
			'default'     => __( 'Log in to your online banking portal and pay directly from your chosen debit account.', 'woocommerce-gateway-cevnn' ),
			'desc_tip'    => true,
		),
        'cta-enable'                       => array(
            'title'       => __( 'Toggle CTA during checkout', 'woocommerce-gateway-cevnn' ),
            'label'       => __( 'Enable', 'woocommerce-gateway-cevnn' ),
            'type'        => 'checkbox',
            'description' => 'Toggle to show online debit payment support during checkout',
            'default'     => 'no',
            'desc_tip'    => true,
        ),
        'payment_page'                       => array(
            'title'       => __( 'Payment Page', 'woocommerce-gateway-cevnn' ),
            'label'       => __('Payment Page Must Be Child Of Checkout Page', 'woocommerce'),
            'type'        => 'select',
            'description' => __( 'Choose a page for your payment page. If using a custom page, make sure your woocommerce \'Checkout\' page is the parent of the preferred payment page.', 'woocommerce-gateway-cevnn' ),
            'default'     =>  'Payment Via Bank',
            'options'     => wp_list_pluck(get_pages(array(
                'sort_column' => 'post_date',
                'sort_order' => 'dsc',
                'child_of' => get_option( 'woocommerce_checkout_page_id' ),
            )),'post_title', 'ID'),
            'desc_tip'    => true,

        ),
        'api-title' => array(
            'title'       => __( 'Cevnn API Credentials', 'woocommerce-gateway-cevnn' ),
            'type'        => 'title',
            'description' => __( 'Enter Cevnn API Credentials, if not available contact support@cevnn.com for more information.', 'woocommerce-gateway-paypal-express-checkout' ),
        ),
        'iframeKey'                       => array(
            'title'       => __( 'iFrame Key', 'woocommerce-gateway-cevnn' ),
            'type'        => 'text',
            'description' => __( 'Enter your Cevnn iFrame key ', 'woocommerce-gateway-cevnn' ),
            'desc_tip'    => true,

        ),
		'ApiKey'                       => array(
			'title'       => __( 'API Key', 'woocommerce-gateway-cevnn' ),
			'type'        => 'text',
            'description' => __( 'Enter your your Cevnn API key ', 'woocommerce-gateway-cevnn' ),
            'desc_tip'    => true,

        ),
//        'webhook'     => array(
//			'title'       => __( 'Webhook Endpoints', 'woocommerce-gateway-cevnn' ),
//			'type'        => 'title',
//			'description' => $this->display_admin_settings_webhook_description(),
//		)

	)
);
