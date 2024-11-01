<?php
/*
Plugin Name: Transactium WooCommerce AddOn
Description: Transactium WooCommerce AddOn
Version: 1.16
Author: Transactium Ltd
Author URI: https://www.transactium.com
WC requires at least: 2.4.0
WC tested up to: 9.1
Requires Plugins: woocommerce
*/

// Include our Gateway Class and Register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'transactium_woocommerce_addon_init', 0);
function transactium_woocommerce_addon_init()
{
    if (!function_exists('is_woocommerce_active'))
    {
        function is_woocommerce_active()
        {
            return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) || is_plugin_active_for_network('woocommerce/woocommerce.php');
        }
    }

    // If the parent WC_Payment_Gateway class doesn't exist
    // it means WooCommerce is not installed on the site
    // so do nothing
    if (!class_exists('WC_Payment_Gateway_CC') || !is_woocommerce_active() || class_exists('transactium_woocommerce_addon')) return;

    // payment gateway class
    class transactium_woocommerce_addon extends WC_Payment_Gateway_CC
    {
        private static $instance;

        public static function get_instance()
        {
            if (is_null(self::$instance))
            {
                self::$instance = new self();
            }
            return self::$instance;
        }
        // Setup our Gateway's id, description and other values
        function __construct()
        {

            // The global ID for this Payment method
            $this->id = "transactium_woocommerce_addon";

            // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
            $this->method_title = __("Transactium EZPay", 'transactium-wc-addon');

            // The description for this Payment Gateway, shown on the actual Payment options page on the backend
            $this->method_description = __("Transactium EZPay Payment Gateway Plug-in for WooCommerce", 'transactium-wc-addon');

            // The title to be used for the vertical tabs that can be ordered top to bottom
            $this->title = __("Transactium EZPay", 'transactium-wc-addon');

            // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
            $this->icon = null;

            // Bool. Can be set to true if you want payment fields to show on the checkout
            // if doing a direct integration, which we are doing in this case
            $this->has_fields = true;

            $this->ezpay_result_statuses = array(
                "Approved",
                "Declined",
                "Error",
                "Blocked",
                "Voided",
                "Dubious",
                "Enrolled",
                "Async",
                "Result Code Invalid"
            );

            // Supports the following functionalities
            $this->supports = array(
                'default_credit_card_form',
                'refunds',
                'tokenization',
                'credit_card_form_cvc_on_saved_method',
                'subscriptions',
                'subscription_cancellation',
                'subscription_reactivation',
                'subscription_suspension',
                'subscription_amount_changes',
                'subscription_date_changes',
                'multiple_subscriptions'
            );

            // This basically defines your settings which are then loaded with init_settings()
            $this->init_form_fields();

            // After init_settings() is called, you can get the settings and load them into variables, e.g:
            // $this->title = $this->get_option( 'title' );
            $this->init_settings();

            // Turn these settings into variables we can use
            foreach ($this->settings as $setting_key => $value)
            {
                $this->$setting_key = is_string($value) ? trim($value) : $value;
            }
			
			if (isset($settings["three_d_secure"]) && $this->three_d_secure>1)
				$this->three_d_secure=1;

            // Lets check for SSL
            add_action('admin_notices', array(
                $this,
                'do_admin_checks'
            ));

            // Save settings
            if (is_admin())
            {
                // Versions over 2.0
                // Save our administration options. Since we are not going to be doing anything special
                // we have not defined 'process_admin_options' in this class so the method in the parent
                // class will be used instead
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                    $this,
                    'process_admin_options',
                ));
            }

            //Once form is prepared execute init_checkout method
            add_action('woocommerce_credit_card_form_end', array(
                $this,
                'init_checkout'
            ));

            //handler is called after 3dsecure (HOST) completes from transactium - End3DSHostPostbackURL
            add_action('woocommerce_api_wc_gateway_transactium_woocommerce_addon', array(
                $this,
                'return_handler'
            ));

            add_action('woocommerce_scheduled_subscription_payment_transactium_woocommerce_addon', array(
                $this,
                'scheduled_subscription_payment'
            ) , 10, 2);

            add_action('woocommerce_order_details_after_order_table', function ($order)
            {

                $order_id = $this->WC_compat($order, 'id');

                if (get_post_meta($order_id, 'gateway_id', true) == $this->id && $this->checkout_card_details == "yes")
                {

                    $billing_full_name = $this->WC_compat($order, 'billing_first_name') . " " . $this->WC_compat($order, 'billing_last_name');

?>
					
					</section>
					<section class="woocommerce-card-details">
						<h2 class="woocommerce-card-details__title">Card details</h2>
						<table class="woocommerce-table woocommerce-table-card-details shop_table card_details">
							<tr>
								<th scope="row"><?php echo __('Card holder name'); ?>:</th>
								<td><?php echo esc_html($billing_full_name); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo __('Card type'); ?>:</th>
								<td><?php echo esc_html(get_post_meta($order_id, 'card_type', true)); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php echo __('Card number'); ?>:</th>
								<td><?php echo esc_html(get_post_meta($order_id, 'card_number_masked', true)); ?></td>
							</tr>
						</table>
					</section>
					
					<?php
                }

            }
            , 10, 1);

            add_filter('woocommerce_email_customer_details_fields', function ($fields, $sent_to_admin, $order)
            {

                $order_id = $this->WC_compat($order, 'id');

                if (get_post_meta($order_id, 'gateway_id', true) == $this->id && $this->checkout_card_details == "yes")
                {

                    $billing_full_name = $this->WC_compat($order, 'billing_first_name') . " " . $this->WC_compat($order, 'billing_last_name');
                    $fields["card_holder_name"] = array(
                        "label" => "Card holder name",
                        "value" => $billing_full_name
                    );
                    $fields["card_type"] = array(
                        "label" => "Card type",
                        "value" => get_post_meta($order_id, 'card_type', true)
                    );
                    $fields["card_number"] = array(
                        "label" => "Card number",
                        "value" => get_post_meta($order_id, 'card_number_masked', true)
                    );

                }

                return $fields;

            }
            , 10, 3);

        } // End __construct()
        // Admin Panel Options.
        // - Options for bits like 'title' and availability on a country-by-country basis.
        public function admin_options()
        {
            parent::admin_options();
            $this->checks();
        }

        // Check if SSL is enabled and notify the user.
        public function checks()
        {
            if ('no' == $this->enabled)
            {
                return;
            }

            // PHP Version
            if (version_compare(phpversion() , '7.3', '<'))
            {
                echo '<div class="error"><p>' . sprintf(__('Transactium EZPay Error: Transactium EZPay requires PHP 7.3 and above. You are using version %s.', 'transactium-wc-addon') , phpversion()) . '</p></div>';
            }

            // Check required fields
            elseif (!$this->public_key || !$this->private_key)
            {
                echo '<div class="error"><p>' . __('Transactium EZPay Error: Please enter your public and private keys', 'transactium-wc-addon') . '</p></div>';
            }

            // Show message when using standard mode and no SSL on the checkout page
            elseif ($this->ssl_verification !== "yes")
            {
                echo '<div class="error"><p>' . sprintf(__('Transactium EZPay is enabled, but the <b>SSL Verification</b> option is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL certificate - Transactium EZPay will only work in sandbox mode.', 'transaction-wallet-woocommerce')) . '</p></div>';
            }
        }

        /**
         * Check if this gateway is enabled.
         *
         * @return bool
         */
        public function is_available()
        {
            if ('yes' !== $this->enabled)
            {
                return false;
            }

            if ($this->environment == "yes" && !wc_checkout_is_https())
            {
                return false;
            }

            if (!$this->public_key || !$this->private_key)
            {
                return false;
            }

            if ($this->WC_3() === null)
            {
                return false;
            }

            return true;
        }

        // Check if we are forcing SSL on checkout pages
        // Custom function not required by the Gateway
        function do_admin_checks()
        {
            if ($this->enabled == "yes")
            {
                if ($this->WC_3() === null)
                {
                    echo "<div class=\"error\"><p>" . sprintf(__("This version of <strong>%s</strong> requires WooCommerce v2.4 or later. Please upgrade WooCommerce.", 'transactium-wc-addon') , esc_html($this->method_title)) . "</p></div>";
                }
                if (!wc_checkout_is_https() && $this->ssl_verification === "yes")
                {
                    echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured</a>. After enabling refresh to hide this message.") , esc_html($this->method_title), esc_html(admin_url('admin.php?page=wc-settings&tab=checkout'))) . "</p></div>";
                }
            }
        }

        function WC_3()
        {
            if (version_compare(get_option('woocommerce_db_version') , '3', '>='))
            {
                return true;
            }
            else if (version_compare(get_option('woocommerce_db_version') , '2.4', '>=') && version_compare(get_option('woocommerce_db_version') , '3', '<'))
            {
                return false;
            }
            else
            {
                return null;
            }
        }

        function WC_compat($order, $old, $new = null, $old_is_property = true, $new_is_property = false)
        {
            $method_property_name = !$new ? 'get_' . $old : $new;
            if (!$new_is_property)
            {
                return method_exists($order, $method_property_name) ? $order->$method_property_name() : ($old_is_property ? $order->$old : $order->$old());
            }
            else
            {
                return property_exists($order, $method_property_name) ? $order->$method_property_name : ($old_is_property ? $order->$old : $order->$old());
            }
        }

        function get_base_url()
        { //"no" => "STGING", "yes" => "LIVE"
            return ($this->environment != "yes") ? 'https://psp.stg.transactium.com/hps' : 'https://psp.transactium.com/hps';
        }

        function get_three_d_secure_list()
        {
            return array(
                "Off",
                "Async"
            );
        }

        function get_three_d_secure()
        {
            $index = $this->three_d_secure;
			if ($index>0)
				$index=1;
            $three_d_secure = $this->get_three_d_secure_list();
            return $three_d_secure[$index];
        }

        function get_wp_request_array()
        {
            return array(
                'sslverify' => $this->ssl_verification === "yes",
                'user-agent' => null,
                'compress' => false,
                'decompress' => false,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept' => null,
                    'Accept-Encoding' => null,
                    'Referer' => null
                )
            );
        }

        // Build the administration fields for this specific Gateway
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable / Disable', 'transactium-wc-addon') ,
                    'label' => __('Enable this payment gateway', 'transactium-wc-addon') ,
                    'type' => 'checkbox',
                    'default' => 'no'
                ) ,
                'title' => array(
                    'title' => __('Title', 'transactium-wc-addon') ,
                    'type' => 'text',
                    'desc_tip' => __('Payment title the customer will see during the checkout process.', 'transactium-wc-addon') ,
                    'default' => __('Credit card', 'transactium-wc-addon')
                ) ,
                'description' => array(
                    'title' => __('Description', 'transactium-wc-addon') ,
                    'type' => 'textarea',
                    'desc_tip' => __('Payment description the customer will see during the checkout process.', 'transactium-wc-addon') ,
                    'default' => __('Pay securely using your credit card.', 'transactium-wc-addon') ,
                    'css' => 'max-width:350px;'
                ) ,
                'public_key' => array(
                    'title' => __('EZPay - Public Key', 'transactium-wc-addon') ,
                    'type' => 'text',
                    'desc_tip' => __('This is the Public Key provided by Transactium when you signed up for an account.', 'transactium-wc-addon')
                ) ,
                'private_key' => array(
                    'title' => __('EZPay - Private Key', 'transactium-wc-addon') ,
                    'type' => 'text',
                    'desc_tip' => __('This is the Private Key provided by Transactium when you signed up for an account.', 'transactium-wc-addon')
                ) ,
                'three_d_secure' => array(
                    "title" => __('EZPay - 3DSecure', 'transactium-wc-addon') ,
                    "description" => __('Enables 3DSecure.', 'transactium-wc-addon') ,
                    "type" => "select",
                    'default' => 'Async',
                    "std" => "",
                    "options" => $this->get_three_d_secure_list()
                ) ,
                'environment' => array(
                    'title' => __('Staging/Live Mode', 'transactium-wc-addon') ,
                    'label' => __('Enable Live Mode', 'transactium-wc-addon') ,
                    'type' => 'checkbox',
                    'description' => __('Puts the payment gateway in live mode. Deselect for staging mode.', 'transactium-wc-addon') ,
                    'default' => 'no'
                ) ,
                'ssl_verification' => array(
                    'title' => __('SSL Verification', 'transactium-wc-addon') ,
                    'label' => __('Enable SSL Verification', 'transactium-wc-addon') ,
                    'type' => 'checkbox',
                    'description' => __('This enables SSL verification. Turn off by deselecting. This should be left on for security reasons.', 'transactium-wc-addon') ,
                    'default' => 'yes'
                ) ,
                'checkout_card_details' => array(
                    'title' => __('Checkout - Card Details', 'transactium-wc-addon') ,
                    'label' => __('Show Card Details On Checkout', 'transactium-wc-addon') ,
                    'type' => 'checkbox',
                    'description' => __('This shows a separate card details section on checkout. Hide by deselecting.', 'transactium-wc-addon') ,
                    'default' => 'no'
                )
            );
        }

        public function init_checkout($caller)
        {
            //#TODO: Fix double loading/reqesting issue (hook/plugin firing twice)
            if (!is_checkout() || !$this->is_available())
            {
                wc_add_notice(__('We are currently experiencing problems trying to load required files from this payment gateway. Sorry for the inconvenience.', 'transactium-wc-addon') , 'error');
                return;
            }

            wp_enqueue_script('transactium-wc-addon', plugins_url('assets/js/transactium-wc-addon.js', __FILE__) , array(
                'jquery',
                'wc-credit-card-form'
            ) , WC_VERSION, true);
            wp_enqueue_style('transactium-wc-addon', plugins_url('assets/css/transactium-style.css', __FILE__));

            wp_localize_script('transactium-wc-addon', 'transactium_params', array(
                'amount' => $this->get_order_total() ,
                'currency' => get_woocommerce_currency()
            ));

            $private_key = $this->private_key;
            $url = $this->get_base_url();

            $resp = wp_remote_get(add_query_arg('privateMerchantKey', urlencode($private_key) , $url . "/webservice/ezpay/CreateSessionKey") , $this->get_wp_request_array());

            //UNCOMMENT to debug
            //error_log(json_encode($resp));
            if (is_wp_error($resp))
            {
                wc_add_notice(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'transactium-wc-addon') , 'error');
                return;
            }

            if (empty($resp))
            {
                wc_add_notice(__('Gateway Response was empty.', 'transactium-wc-addon') , 'error');
                return;
            }

            if (isset($resp['body']))
            {
                $body = (object)json_decode($resp['body'], true);
                if (isset($body->Error))
                {
                    wc_add_notice(__($body->Error['Message'], 'transactium-wc-addon') , 'error');
                    return;
                }
            }
            else if (wp_remote_retrieve_response_code($resp) !== 200)
            {
                wc_add_notice(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'transactium-wc-addon') , 'error');
                return;
            }

            $jd = (object)json_decode(wp_remote_retrieve_body($resp) , true);

            wp_enqueue_script('ezpay', add_query_arg('sessionkey', urlencode($jd->sessionkey) , $url . '/CardToken/ezpay.js') , array(
                'jquery'
            ));

            wp_enqueue_style('ezpay', $url . '/Content/css/ezpay.css');

            //following input fields are critical to be left at this place so they are included within the woocommerce payment form
            
?>
		<input type="hidden" name="Token" id="transactium_woocommerce_addon-token" />
		<input type="hidden" data-ezpay-token-type="card" />
		<input type="hidden" data-ezpay-token-type="cvv" />
		<input type="hidden" data-ezpay-token-type="expiry" />

        <?php
			if (false && $this->get_three_d_secure() === "Async" && is_checkout())
            { ?>
			<input type="hidden" data-ezpay-token-type="mode" value="Async" />
		<?php
            } ?>
		
		<?php if ($this->get_three_d_secure() !== "Off")
            { ?>
			<input type="hidden" data-ezpay-token-type="currency" />
		<?php
            } ?>
	<?php
        }

        // Submit payment and handle response
        public function process_payment($order_id)
        {
            global $woocommerce;

            // Get this Order's information so that we know
            // who to charge and how much
            $customer_order = new WC_Order($order_id);

            $new_payment_method = "false";
            if (isset($_POST['wc-transactium_woocommerce_addon-new-payment-method']))
            {
                $new_payment_method = "true";
            }

            $merchRef = "merchRef" . time();
            $returnURL = add_query_arg(array(
                'ref' => urlencode($merchRef) ,
                'new_payment_method' => urlencode($new_payment_method) ,
                'order_id' => urlencode($order_id)
            ) , WC()->api_request_url('WC_Gateway_Transactium_Woocommerce_Addon'));

            // This is where the fun stuff begins
            $payload = array(
                "PrivateMerchantKey" => $this->private_key,
                "Request" => array(
                    "Card" => array(
                        "HolderName" => $this->WC_compat($customer_order, 'billing_first_name') . " " . $this->WC_compat($customer_order, 'billing_last_name')
                    ) ,
                    "References" => array(
                        //"Merchant" => $requestMerchantRef, //has to be unique within 24hours
                        "Merchant" => $merchRef,
                        "Client" => $this->WC_compat($customer_order, 'user_id'),
                        "Order" => $customer_order->get_order_number()
                    ) ,
                    "Transaction" => array(
                        "Amount" => round($this->WC_compat($customer_order, 'order_total', 'get_total') * 100) , //Convert to cents
                        "Currency" => $this->WC_compat($customer_order, 'get_order_currency', 'get_currency', false) ,
                        "SuccessURL" => $returnURL,
                        "CancelURL" => $returnURL,
						"IPNURL" =>$returnURL
                    ) ,
                    "Billing" => array(
                        "FullName" => $this->WC_compat($customer_order, 'billing_first_name') . " " . $this->WC_compat($customer_order, 'billing_last_name') ,
                        "Phone" => $this->WC_compat($customer_order, 'billing_phone') ,
                        "Email" => $this->WC_compat($customer_order, 'billing_email') ,
                        "StreetName" => $this->WC_compat($customer_order, 'billing_address_1') ,
                        "AddressUnitNumber" => $this->WC_compat($customer_order, 'billing_address_2') ,
                        "CityName" => $this->WC_compat($customer_order, 'billing_city') ,
                        "TerritoryCode" => $this->WC_compat($customer_order, 'billing_state') ,
                        "CountryCode" => $this->WC_compat($customer_order, 'billing_country') ,
                        "PostalCode" => $this->WC_compat($customer_order, 'billing_postcode')
                    ) ,
                    "Shipping" => array(
                        "FullName" => $this->WC_compat($customer_order, 'shipping_first_name') . " " . $this->WC_compat($customer_order, 'shipping_last_name') ,
                        "StreetName" => $this->WC_compat($customer_order, 'shipping_address_1') ,
                        "AddressUnitNumber" => $this->WC_compat($customer_order, 'shipping_address_2') ,
                        "CityName" => $this->WC_compat($customer_order, 'shipping_city') ,
                        "TerritoryCode" => $this->WC_compat($customer_order, 'shipping_state') ,
                        "CountryCode" => $this->WC_compat($customer_order, 'shipping_country') ,
                        "PostalCode" => $this->WC_compat($customer_order, 'shipping_postcode')
                    )
                ) ,
                "RequestType" => ($this->get_three_d_secure() === "Async") ? "6" : "0" // 0 = SALE, 6 = THREEDS SALE
            );
			if ($payload['request']['References']['Client']=="0")
			{
				unset($payload['request']['References']['Client']);
			}

            $path = '';

            if (!isset($_POST['wc-transactium_woocommerce_addon-payment-token']) || sanitize_key($_POST['wc-transactium_woocommerce_addon-payment-token']) === "new")
            {

                $payload['Token'] = sanitize_key($_POST['Token']);
                $path = "PayWithToken";

            }
            else
            {
                $token = WC_Payment_Tokens::get(wc_clean($_POST['wc-transactium_woocommerce_addon-payment-token']));
                $payload['Request']['Linked'] = array(
                    "ReferenceId" => $token->get_token()
                );
                $payload['Request']['Card']['CVV2'] = wc_clean($_POST['transactium_woocommerce_addon-card-cvc']);
                $payload['Request']['Options'] = array(
                    'IPv4Address' => WC_Geolocation::get_ip_address()
                );
                $path = "PayWithReference";
            }

            if (false && $this->get_three_d_secure() === "Async")
            {

                $payload_three_d_secure = array(
                    "End3DSHostPostbackURL" => add_query_arg(array(
                        'reference' => urlencode($order_id) ,
                        'new_payment_method' => urlencode($new_payment_method)
                    ) , WC()->api_request_url('WC_Gateway_Transactium_Woocommerce_Addon'))
                );
                $payload = array_merge($payload, $payload_three_d_secure);

            }

            // Send this payload to Transactium for processing
            $response = wp_remote_post($this->get_base_url() . "/webservice/ezpay/" . $path, array_merge(array(
                'method' => 'POST',
                'body' => json_encode($payload) ,
                'timeout' => 90
            ) , $this->get_wp_request_array()));

            if (is_wp_error($response))
            {
                wc_add_notice(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'transactium-wc-addon'));
                return;
            }

            if (empty($response['body']))
            {
                wc_add_notice(__('Gateway Response was empty.', 'transactium-wc-addon'));
                return;
            }
            $jd = (object)json_decode(wp_remote_retrieve_body($response) , true);

            $transaction_id = '';
            $transaction_authcode = '';

            if (!isset($jd->Result['Code']) || $jd->Result['Code'] === null)
				$result_code = 8;
            else
            {
                $result_code = $jd->Result['Code'];
                $transaction_id = $jd->Transaction['Id'];
                $transaction_authcode = $jd->Transaction['AuthenticationCode'];
            }

            //3DS V2 - Async
            if ($this->get_three_d_secure() === "Async" && $result_code === 7)
            {
                return array(
                    'result' => 'success',
                    'redirect' => $jd->Async['RedirectURL']
                );
            }

            switch ($result_code)
            {
                case 0:
                    // Payment has been successful
                    $customer_order->add_order_note(sprintf(__('Transactium EZPay approved (ID: %s, Auth Code: %s)', 'transactium-wc-addon') , $transaction_id, $transaction_authcode));

                    update_post_meta($order_id, 'transaction_id', $transaction_id);
                    update_post_meta($order_id, 'card_number_masked', $jd->Card['Bin'] . "****" . $jd->Card['LastFourDigits']);
                    update_post_meta($order_id, 'card_type', $jd->Card['ApplicationProfile']);
                    update_post_meta($order_id, 'gateway_id', $this->id);

                    if (class_exists('WC_Subscriptions_Manager') && wcs_order_contains_subscription($customer_order))
                    {
                        $subscriptions = wcs_get_subscriptions_for_order($customer_order);
                        foreach ($subscriptions as $subscription)
                        {
                            update_post_meta($subscription->id, 'transaction_id', $transaction_id);
                        }
                    }

                    // Mark order as Paid
                    $customer_order->payment_complete();

                    if (isset($_POST['wc-transactium_woocommerce_addon-new-payment-method']) && sanitize_key($_POST['wc-transactium_woocommerce_addon-new-payment-method']) === "true")
                    {
                        $token = new WC_Payment_Token_CC();
                        $token->set_token($transaction_id);
                        $token->set_gateway_id($this->id);

                        $token->set_card_type($jd->Card['ApplicationProfile']);
                        $token->set_last4($jd->Card['LastFourDigits']);
                        $token->set_expiry_month(substr($jd->Card['ExpiryYYMM'], 2, 2));
                        $token->set_expiry_year('20' . substr($jd->Card['ExpiryYYMM'], 0, 2));
                        $token->set_user_id(get_current_user_id());
                        $token_saved = $token->save();

                        if ($token_saved === false)
                        {
                            wc_add_notice("Failed to save New Payment Method", 'error');
                            return;
                        }

                    }

                    // Empty the cart (Very important step)
                    $woocommerce
                        ->cart
                        ->empty_cart();

                    // Redirect to thank you page
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($customer_order)
                    );
                break;
                default:
                    // Transaction was not succesful
                    // Add notice to the cart
                    wc_add_notice("Transaction " . $this->ezpay_result_statuses[$result_code], 'error');
                    // Add note to the order for your reference
                    if (!(!isset($jd->Result['Message']) || trim($jd->Result['Message']) === ''))
                    {
                        $customer_order->add_order_note('Error: ' . $jd->Result['Message']);
                    }
                    return;
            }

        }

        //Used by 3DSecure Host payment flow
        public function return_handler()
        {
            @ob_clean();
            header('HTTP/1.1 200 OK');

            if (isset($_GET['ref']))
            {
                $merchref = sanitize_key($_GET['ref']);
                $payload = array(
                    "privatemerchantkey" => $this->private_key,
                    "merchantreference" => $merchref,
                );
                $response = wp_remote_post($this->get_base_url() . "/webservice/ezpay/checktransaction", array_merge(array(
                    'method' => 'POST',
                    'body' => json_encode($payload) ,
                    'timeout' => 90
                ) , $this->get_wp_request_array()));
                if (is_wp_error($response))
                {
                    wc_add_notice(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'transactium-wc-addon'));
                    return;
                }

                if (empty($response['body']))
                {
                    wc_add_notice(__('Gateway Response was empty.', 'transactium-wc-addon'));
                    return;
                }
                $jd = (object)json_decode(wp_remote_retrieve_body($response) , true);
                //error_log(json_encode($jd));
                $transaction_id = '';
                $transaction_authcode = '';

                if (!isset($jd->Result['Code']) || $jd->Result['Code'] === null) $result_code = 8;
                else
                {
                    $result_code = $jd->Result['Code'];
                    $transaction_id = $jd->Transaction['Id'];
                    $transaction_authcode = $jd->Transaction['AuthenticationCode'];
                }

                $result_code_message = $this->ezpay_result_statuses[$result_code];
                $new_payment_method = filter_var($_GET['new_payment_method'], FILTER_VALIDATE_BOOLEAN);
                $order_id = absint(sanitize_key($_GET['order_id']));
                $customer_order = wc_get_order($order_id);
				if ( !$customer_order->has_status('pending') ) {
					$customer_order->add_order_note(__('Transactium EZPay notified'));
					return;
				}
                if ($result_code === null || $result_code === false) $result_code = 8; //????
                switch ($result_code)
                {
                    case 0:
                        // Payment has been successful
                        $customer_order->add_order_note(sprintf(__('Transactium EZPay approved (ID: %s, Auth Code: %s)', 'transactium-wc-addon') , $transaction_id, $transaction_authcode));

                        update_post_meta($order_id, 'transaction_id', $transaction_id);
                        update_post_meta($order_id, 'card_number_masked', $jd->Card['Bin'] . "****" . $jd->Card['LastFourDigits']);
                        update_post_meta($order_id, 'card_type', $jd->Card['Response_Card_ApplicationProfile']);
                        update_post_meta($order_id, 'gateway_id', $this->id);

                        if (class_exists('WC_Subscriptions_Manager') && wcs_order_contains_subscription($customer_order))
                        {
                            $subscriptions = wcs_get_subscriptions_for_order($customer_order);
                            foreach ($subscriptions as $subscription)
                            {
                                update_post_meta($subscription->id, 'transaction_id', $transaction_id);
                            }
                        }

                        // Mark order as Paid
                        $customer_order->payment_complete();

                        if ($new_payment_method)
                        {
                            $token = new WC_Payment_Token_CC();
                            $token->set_token($transaction_id);
                            $token->set_gateway_id($this->id);

                            $token->set_card_type($jd->Card['ApplicationProfile']);
                            $token->set_last4($jd->Card['LastFourDigits']);
                            $token->set_expiry_month(substr($jd->Card['ExpiryYYMM'], 2, 4));
                            $token->set_expiry_year('20' . substr($jd->Card['ExpiryYYMM'], 0, 2));
                            $token->set_user_id($customer_order->get_user_id());
                            $token_saved = $token->save();

                            if ($token_saved === false)
                            {
                                wc_add_notice("Failed to save New Payment Method", 'error');
                                return;
                            }
                        }

                        // Empty the cart (Very important step)
                        WC()
                            ->cart
                            ->empty_cart();

                        // Redirect to thank you page
                        wp_redirect($this->get_return_url($customer_order));
                        exit;
                    break;
                    default:
                        // Transaction was not succesful
                        // Add notice to the cart
                        wc_add_notice("Transaction " . $this->ezpay_result_statuses[$result_code], 'error');
                        // Add note to the order for your reference
                        if (!(!isset($jd->Result['Message']) || trim($jd->Result['Message']) === ''))
                        {
                            $customer_order->add_order_note('Error: ' . $jd->Result['Message']);
                        }
                        wp_redirect(wc_get_page_permalink('cart'));
                        exit();
                }

            }
            else
            {
                $result_code_message = sanitize_key($_POST['Response_Result_Code']);
                $transaction_id = sanitize_key($_POST['Response_Transaction_Id']);
                $transaction_authcode = sanitize_key($_POST['Response_Transaction_AuthenticationCode']);

                $result_code = array_search(strtolower($result_code_message) , array_map('strtolower', $this->ezpay_result_statuses));

                $order_id = absint(sanitize_key($_REQUEST['reference']));
                $new_payment_method = filter_var($_REQUEST['new_payment_method'], FILTER_VALIDATE_BOOLEAN);
                $customer_order = wc_get_order($order_id);

                if ($result_code === null || $result_code === false) $result_code = 8;

                switch ($result_code)
                {
                    case 0:
                        // Payment has been successful
                        $customer_order->add_order_note(sprintf(__('Transactium EZPay approved (ID: %s, Auth Code: %s)', 'transactium-wc-addon') , $transaction_id, $transaction_authcode));

                        update_post_meta($order_id, 'transaction_id', $transaction_id);
                        update_post_meta($order_id, 'card_number_masked', sanitize_key($_POST['Response_Card_Bin']) . "****" . sanitize_key($_POST['Response_Card_LastFourDigits']));
                        update_post_meta($order_id, 'card_type', sanitize_key($_POST['Response_Card_ApplicationProfile']));
                        update_post_meta($order_id, 'gateway_id', $this->id);

                        if (class_exists('WC_Subscriptions_Manager') && wcs_order_contains_subscription($customer_order))
                        {
                            $subscriptions = wcs_get_subscriptions_for_order($customer_order);
                            foreach ($subscriptions as $subscription)
                            {
                                update_post_meta($subscription->id, 'transaction_id', $transaction_id);
                            }
                        }

                        // Mark order as Paid
                        $customer_order->payment_complete();

                        if ($new_payment_method)
                        {
                            $token = new WC_Payment_Token_CC();
                            $token->set_token($transaction_id);
                            $token->set_gateway_id($this->id);

                            $token->set_card_type(strtolower(sanitize_key($_POST['Response_Card_ApplicationProfile'])));
                            $token->set_last4(sanitize_key($_POST['Response_Card_LastFourDigits']));
                            $token->set_expiry_month(substr(sanitize_key($_POST['Response_Card_ExpiryYYMM'], 2, 2)));
                            $token->set_expiry_year('20' . substr(sanitize_key($_POST['Response_Card_ExpiryYYMM'], 0, 2)));
                            $token->set_user_id($customer_order->get_user_id());
                            $token_saved = $token->save();

                            if ($token_saved === false)
                            {
                                wc_add_notice("Failed to save New Payment Method", 'error');
                                return;
                            }
                            else wc_add_notice("Card Information Saved", 'error');

                        }

                        // Empty the cart (Very important step)
                        WC()
                            ->cart
                            ->empty_cart();

                        // Redirect to thank you page
                        wp_redirect($this->get_return_url($customer_order));
                        exit;
                        break;
                    default:
                        // Transaction was not succesful
                        // Add notice to the cart
                        wc_add_notice("Transaction " . $this->ezpay_result_statuses[$result_code], 'error');
                        // Add note to the order for your reference
                        if (!(!isset($jd->Result['Message']) || trim($jd->Result['Message']) === ''))
                        {
                            $customer_order->add_order_note('Error: ' . $jd->Result['Message']);
                        }
                        wp_redirect(wc_get_page_permalink('cart'));
                        exit();
                    }
            }
        }

        /**
         * Process refunds.
         * WooCommerce 2.2 or later.
         *
         * @param  int $order_id
         * @param  float $amount
         * @param  string $reason
         * @uses   Simplify_ApiException
         * @uses   Simplify_BadRequestException
         * @return bool|WP_Error
         */
        public function process_refund($order_id, $amount = null, $reason = '')
        {

            $payment_id = get_post_meta($order_id, 'transaction_id', true);
            $customer_order = new WC_Order($order_id);

            if ($amount === null || $amount == 0 || $amount === '')
            {
                return new WP_Error('transactium-wc-addon_refund_error', __('Amount is NOT optional.', 'transactium-wc-addon'));
            }

            $payload = array(
                "PrivateMerchantKey" => $this->private_key,
                "Request" => array(
                    "Linked" => array(
                        "ReferenceID" => $payment_id,

                    ),
                    "Transaction" => array(
                        "Amount" => round($amount * 100, 0)
                    ),
					"References"=>array(
						"Client" =>$this->WC_compat($customer_order, 'user_id'),
						"Order" =>$order_id,
					)
                ) ,
                "RequestType" => "3"
                // 3 = REFUND
                
            );
			if ($payload["Request"]["References"]["Client"]==0)
			{
				unset($payload["Request"]["References"]["Client"]);
			}

            $response = wp_remote_post($this->get_base_url() . "/webservice/ezpay/AdjustTransaction", array_merge(array(
                'method' => 'POST',
                'body' => json_encode($payload) ,
                'timeout' => 90
            ) , $this->get_wp_request_array()));

            if (is_wp_error($response)) return new WP_Error('transactium-wc-addon_refund_error', __('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'transactium-wc-addon'));

            if (empty($response['body'])) return new WP_Error('transactium-wc-addon_refund_error', __('Gateway Response was empty.', 'transactium-wc-addon'));

            $jd = (object)json_decode(wp_remote_retrieve_body($response) , true);

            $result_code = $jd->Result['Code'];
            $transaction_id = $jd->Transaction['Id'];
            $transaction_authcode = $jd->Transaction['AuthenticationCode'];

            if ($result_code === 0)
            {

                $customer_order->add_order_note(sprintf(__('Refund of ' . ($this->WC_compat($customer_order, 'get_order_currency', 'get_currency', false)) . ' ' . $amount . ' Approved (ID: %s, Auth Code: %s)', 'transactium-wc-addon') , $transaction_id, $transaction_authcode));
                return true;

            }
            else
            {

                // Transaction was not succesful
                // Add notice to the cart
                wc_add_notice("Refund of " . ($this->WC_compat($customer_order, 'get_order_currency', 'get_currency', false)) . " " . $amount . " " . $this->ezpay_result_statuses[$result_code], 'error');
                // Add note to the order for your reference
                if (!(!isset($jd->Result['Message']) || trim($jd->Result['Message']) === ''))
                {
                    $customer_order->add_order_note('Error: ' . $jd->Result['Message']);
                }

                return new WP_Error('transactium_ezpay_' . $this->ezpay_result_statuses[$result_code], __('Refund of ' . ($this->WC_compat($customer_order, 'get_order_currency', 'get_currency', false)) . ' ' . $amount . ' was ' . $this->ezpay_result_statuses[$result_code] . ' - please try again.', 'transactium-wc-addon'));
            }

            return false;
        }

        function scheduled_subscription_payment($amount_to_charge, $customer_order)
        {

            //Uncomment to DEBUG
            //write_log( 'Transactium: subscription payment hook' );
            //$result = $this->process_subscription_payment( $order, $amount_to_charge );
            if (!class_exists('WC_Subscriptions') || !class_exists('WC_Subscriptions_Manager'))
            {
                error_log("WooCommerce Subscriptions plugin not active.");
                return new WP_Error('no_woo', __('WooCommerce Subscriptions plugin not active.', 'transactium-wc-addon'));
            }

            $subscription = wcs_get_subscriptions_for_order($customer_order);

            if (is_array($subscription))
            {
                $subscription = $subscription[0];
            }

            $payment_id = get_post_meta($subscription->id, 'transaction_id', true);

            //Uncomment to DEBUG
            //write_log($payment_id);
            $payload = array(
                "PrivateMerchantKey" => $this->private_key,
                "Request" => array(
                    "Linked" => array(
                        "ReferenceID" => $payment_id
                    ) ,
                    "Card" => array(
                        "HolderName" => $this->WC_compat($customer_order, 'billing_first_name') . " " . $this->WC_compat($customer_order, 'billing_last_name')
                    ) ,
                    "References" => array(
                        //"Merchant" => $requestMerchantRef, //has to be unique within 24hours
                        "Client" => $this->WC_compat($customer_order, 'user_id') ,
                        "Order" => $customer_order->get_order_number()
                    ) ,
                    "Transaction" => array(
                        "Amount" => round($amount_to_charge * 100) , //Convert to cents
                        "Currency" => $this->WC_compat($customer_order, 'get_order_currency', 'get_currency', false)
                    ) ,
                    "Billing" => array(
                        "FullName" => $this->WC_compat($customer_order, 'billing_first_name') . " " . $this->WC_compat($customer_order, 'billing_last_name') ,
                        "Phone" => $this->WC_compat($customer_order, 'billing_phone') ,
                        "Email" => $this->WC_compat($customer_order, 'billing_email') ,
                        "StreetName" => $this->WC_compat($customer_order, 'billing_address_1') ,
                        "AddressUnitNumber" => $this->WC_compat($customer_order, 'billing_address_2') ,
                        "CityName" => $this->WC_compat($customer_order, 'billing_city') ,
                        "TerritoryCode" => $this->WC_compat($customer_order, 'billing_state') ,
                        "CountryCode" => $this->WC_compat($customer_order, 'billing_country') ,
                        "PostalCode" => $this->WC_compat($customer_order, 'billing_postcode')
                    ) ,
                    "Shipping" => array(
                        "FullName" => $this->WC_compat($customer_order, 'shipping_first_name') . " " . $this->WC_compat($customer_order, 'shipping_last_name') ,
                        "StreetName" => $this->WC_compat($customer_order, 'shipping_address_1') ,
                        "AddressUnitNumber" => $this->WC_compat($customer_order, 'shipping_address_2') ,
                        "CityName" => $this->WC_compat($customer_order, 'shipping_city') ,
                        "TerritoryCode" => $this->WC_compat($customer_order, 'shipping_state') ,
                        "CountryCode" => $this->WC_compat($customer_order, 'shipping_country') ,
                        "PostalCode" => $this->WC_compat($customer_order, 'shipping_postcode')
                    )
                ) ,
                "RequestType" => "0"
                // 0 = SALE
                
            );

            $response = wp_remote_post($this->get_base_url() . "/webservice/ezpay/PayWithReference", array_merge(array(
                'method' => 'POST',
                'body' => json_encode($payload) ,
                'timeout' => 90
            ) , $this->get_wp_request_array()));

            if (is_wp_error($response))
            {
                $customer_order->add_order_note(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'transactium-wc-addon'));
                WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($customer_order);
                return;
            }

            if (empty($response['body']))
            {
                $customer_order->add_order_note(__('Gateway Response was empty.', 'transactium-wc-addon'));
                WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($customer_order);
                return;
            }

            $jd = (object)json_decode(wp_remote_retrieve_body($response) , true);

            $result_code = $jd->Result['Code'];
            $transaction_id = $jd->Transaction['Id'];
            $transaction_authcode = $jd->Transaction['AuthenticationCode'];

            if ($result_code === 0)
            {

                WC_Subscriptions_Manager::process_subscription_payments_on_order($customer_order);
                $customer_order->add_order_note(sprintf(__('Subscription payment of ' . $amount_to_charge . ' Approved (ID: %s, Auth Code: %s)', 'transactium-wc-addon') , $transaction_id, $transaction_authcode));

            }
            else
            {

                // Transaction was not succesful
                WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($customer_order);
                // Add notice to the cart
                $customer_order->add_order_note("Subscription payment of " . ($this->WC_compat($customer_order, 'get_order_currency', 'get_currency', false)) . " " . $amount_to_charge . " " . $this->ezpay_result_statuses[$result_code]);
                // Add note to the order for your reference
                if (!(!isset($jd->Result['Message']) || trim($jd->Result['Message']) === ''))
                {
                    $customer_order->add_order_note('Error Description: ' . $jd->Result['Message']);
                }

            }

        }

    }

    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter('woocommerce_payment_gateways', 'transactium_woocommerce_addon_gateway');
    function transactium_woocommerce_addon_gateway($methods)
    {
        $methods[] = transactium_woocommerce_addon::get_instance();
        return $methods;
    }

}

// Add custom action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__) , 'transactium_woocommerce_addon_action_links');
function transactium_woocommerce_addon_action_links($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=transactium_woocommerce_addon') . '">' . __('Settings', 'transactium-wc-addon') . '</a>'
    );

    // Merge our new link with the default ones
    return array_merge($plugin_links, $links);
}

?>
