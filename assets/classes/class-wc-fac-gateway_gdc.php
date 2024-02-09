<?php

require __DIR__ . '/../lib/vendor/autoload.php';
//require __DIR__.'/../helpers/public.php';

use FacPayments\External\Woocommerce\Entities\TokenizedCard;
use FacPayments\External\Woocommerce\Repositories\TokenizedCardRepository;
use FacPayments\External\Woocommerce\Repositories\WoocommerceLoggingRepository;

use FacPayments\External\Woocommerce\Services\CardDetectorService;

use FacPayments\Entities\Responses\PaymentResponse;

use FacPayments\Factories\PaymentServiceFactory;
use FacPayments\External\Helpers\Arr;
use FacPayments\External\Helpers\Guid;

/**
 * WC wcfac Gateway Class.
 * Built the wcfac method.
 */
class WC_FAC_Payment_Gateway_GDC extends WC_Payment_Gateway_CC
{

    const FAC_ORDER_3DS_REDIRECT_META_KEY = '___fac_3ds_redirect_uri';
    const FAC_ORDER_CART_CONTENTS_META_KEY = '___fac_cart_contents';

    const ORDER_META_KEY_FOR_CARD_TO_BE_TOKENIZED = '__fac_card_to_be_tokenized';
    const ORDER_META_KEY_FOR_SUBSCRIPTION_PAYMENTS_CARD = '__fac_subscription_payment_card';
    const ORDER_META_KEY_FOR_SUBSCRIPTION_PURCHASED_BY_TOKEN = '_fac_subscription_purchased_by_token_id';

    const FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY = '__fac_customer_payment_method_id';
    const FAC_ORDER_ID_META_KEY = '_fac_transaction_order_id';
    const FAC_TRANSACTION_ID_META_KEY = '_fac_transaction_id';
    const FAC_PAYMENT_TYPE_META_KEY = '_fac_payment_type';

    const FAC_PAYMENT_METHOD_TITLE_META_KEY = '_payment_method_title';
    const FAC_OLD_PAYMENT_METHOD_TITLE_META_KEY = '_old_payment_method_title';

    const FAC_TOKENIZED_CARDS_PAGE_SLUG = 'fac_tokenized_user_cards';


    const protected_settings = [
        'title', 'description', 'fac_test_mode', 'fac_merchant_id', 'fac_merchant_password',
        'fac_payment_type', 'fac_3d_secure', 'fac_enabled_avs', 'fac_card_types',
        'kount_merchantid', 'kount_test_mode', 'kount_enabled', 'fac_tokenization',
        'enabled_hpp', 'hpp_pageset', 'hpp_pagename', 'enable_fac_logo', 'fac_order_prefix', 'force_error_display'
    ];

    protected $paymentServices = [1 => null, 2 => null];

    protected $tokenizedCardRepo = null;
    /**
     * Constructor for the gateway.
     *
     * @return void
     */
    public function __construct()
    {
        // global $woocommerce;

        $this->tokenizedCardRepo = new TokenizedCardRepository();
        $this->cardDetectorService = new CardDetectorService();
        $this->id = 'wcfac';
        $this->icon = apply_filters('woocommerce_wcfac_icon', '');
        $this->has_fields = false;
        $this->method_title = __('First Atlantic Commerce Payment Gateway', 'WC_FAC_Payment_Gateway_GDC');
        if (!session_id()) {
            error_reporting(0);
            session_start();
        }
        $this->method_description = __('Adds First Atlantic Commerce Payment Gateway to accept credit card payments on your Woocommerce store.', 'WC_FAC_Payment_Gateway_GDC');
        $this->session_id = session_id();
        $this->acquirerId = '464748';

        // Load the form fields.
        $this->init_form_fields();
        // add_action('admin_enqueue_scripts', array(&$this, 'register_admin_script'));
        $this->supports = array(
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'refunds',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'subscription_payment_method_delayed_change'
        );



        if (class_exists('WC_Subscriptions_Order')) {

            add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'process_scheduled_subscription_payment_fac'), 10, 2);
            add_action('wcs_resubscribe_order_created', array($this, 'delete_resubscribe_meta'), 10);
            add_action('woocommerce_subscription_failing_payment_method_updated_' . $this->id, 'process_subscription_payment_method_changed');

            add_filter('woocommerce_subscription_payment_meta', array($this, 'add_subscription_payment_meta'), 10, 2);
            add_filter('woocommerce_subscription_validate_payment_meta', array($this, 'validate_subscription_payment_meta'), 10, 2);

            add_filter('woocommerce_subscription_payment_method_to_display', array($this, 'fac_subscription_payment_method_to_display'), 10, 3);

            add_filter('woocommerce_subscription_note_new_payment_method_title', array($this, 'fac_subscription_new_payment_method_title'), 10, 3);
        }

        // Load the settings.
        $this->init_settings();

        // Define user set variables.
        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->fac_test_mode = $this->settings['fac_test_mode'];
        $this->fac_merchant_id = $this->settings['fac_merchant_id'];
        $this->fac_merchant_password = $this->settings['fac_merchant_password'];
        $this->fac_payment_type = $this->settings['fac_payment_type'];
        $this->fac_3d_secure = $this->settings['fac_3d_secure'];
        $this->fac_enabled_avs = $this->settings['fac_enabled_avs'];
        $this->fac_card_types = $this->settings['fac_card_types'];
        $this->kount_merchantid = $this->settings['kount_merchantid'];
        $this->kount_test_mode = $this->settings['kount_test_mode'];
        $this->kount_enabled = $this->settings['kount_enabled'];
        $this->fac_tokenization = $this->settings['fac_tokenization'];
        $this->enable_hpp = $this->settings['enable_hpp'] = null;
        $this->hpp_pageset = $this->settings['hpp_pageset'] = null;
        $this->hpp_pagename = $this->settings['hpp_pagename'] = null;
        $this->enable_fac_logo = $this->settings['enable_fac_logo'];
        $this->transactionCacheTime = $this->settings['transactionCacheTime'];
        $this->isKeycard = false;
        $this->fac_order_prefix = $this->settings['fac_order_prefix'];
        $this->force_error_display = $this->settings['force_error_display'];
        



        // Actions.
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
        else
            add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));



        if($this->fac_tokenization == 'yes')
        {
            $menu_success = add_action( 'admin_menu', 'tokenized_cards_list_menu' );
        }

        // Add the tokenized_cards_list_menu() function to the admin menu
        //add_action( 'admin_menu', array( $this, 'tokenized_cards_list_menu' ) );

        // Add the hide_tokenized_cards_menu() function as a filter to show_tokenized_cards_menu
        //add_filter( 'show_tokenized_cards_menu', array( $this, 'hide_tokenized_cards_menu' ), 10, 2 );

    }

    public static function is_tokenization_enabled() {
        $gateway = new self();
        return $gateway->fac_tokenization === 'yes' && $gateway->get_option('enabled') === 'yes';
    }

    /**
     * Display the new admin page
     *
     * @return void
     */
    public static function tokenized_cards_list_page()
    {
        $repository = new TokenizedCardRepository();

        // Check if a card should be deleted
        if (isset($_GET['delete_card']) && isset($_GET['user_id'])) {
            $card_id = $_GET['delete_card'];
            $user_id = $_GET['user_id'];
            $repository->removeUserCard($user_id, $card_id);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('The card has been deleted.', 'tokenized_cards_list') . '</p></div>';
        }

        // Get all users with tokenized cards
        $users_with_tokenized_cards = get_users(array(
            'meta_key' => TokenizedCardRepository::USER_META_TOKENIZED_CARDS_KEY,
            'meta_value' => ''
        ));

        // Create a table of users with tokenized cards
        echo '<div class="wrap">';
        echo '<h1>' . __('FAC Payment Gateway Users\' Tokenized Cards', 'wcwcfac') . '</h1>';
        echo '<table class="wp-list-table widefat striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('User ID', 'wcwcfac') . '</th>';
        echo '<th>' . __('Username', 'wcwcfac') . '</th>';
        echo '<th>' . __('Tokenized Cards', 'wcwcfac') . '</th>';
        //echo '<th>' . __( 'Associated Subscriptions', 'wcwcfac' ) . '</th>';
        //echo '<th>' . __( 'Actions', 'wcwcfac' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        foreach ($users_with_tokenized_cards as $user) {
            $user_id = $user->ID;
            $user_login = $user->user_login;
            $tokenized_cards = get_user_meta($user_id, TokenizedCardRepository::USER_META_TOKENIZED_CARDS_KEY, true);

            if (!empty($tokenized_cards)) {

                echo '<tr>';
                echo '<td>' . $user_id . '</td>';
                echo '<td>' . $user_login . '</td>';
                echo '<td><ul>';
                foreach ($tokenized_cards as $card_key => $card) {
                    $subscription_ids = static::get_subscription_ids_with_payment_method($card_key);
                    $card_details = sprintf(__('%s Card ending %s (Expiry Date: %s/%s)', 'wcwcfac'), $card->cardType, $card->last4, $card->expiryMonth, $card->expiryYear);
                    echo '<li>' . $card_details;
                    if (empty($subscription_ids))
                        echo ' <a href="' . admin_url('admin.php?page=' . static::FAC_TOKENIZED_CARDS_PAGE_SLUG . '&delete_card=' . $card_key . '&user_id=' . $user_id) . '">' . __('Delete', 'wcwcfac') . '</a>';
                    else
                        echo " <span class='assoc-subscriptions' style='color: red'>" . __('Active Subscriptions:', 'wcwcfac') . ' ' . implode(', ', $subscription_ids) . "</span>";

                    echo '<br/></li>';
                }
                echo '</ul></td>';
                echo '</tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    /**
     * Get subscription ids with a specific payment method
     *
     * @param string $payment_method_id The payment method id to search for
     *
     * @return array An array of subscription ids that use the given payment method
     */
    public static function get_subscription_ids_with_payment_method($payment_method_id)
    {
        global $wpdb;
        $order_ids = $wpdb->get_col($wpdb->prepare("
            SELECT posts.ID FROM {$wpdb->posts} AS posts
            LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
            WHERE posts.post_type = 'shop_subscription'
            AND posts.post_status IN ('wc-active', 'wc-pending1', 'wc-on-hold1')
            AND meta.meta_key = '__fac_customer_payment_method_id'
            AND meta.meta_value = %s
            ", $payment_method_id));
        return $order_ids;
    }

    public function simpleIocLoad($className, $arg = null)
    {
        if (class_exists($className)) {
            return $arg ? new $className($arg) : new $className;
        }
        return null;
    }

    protected function get_order_total()
    {

        $total = parent::get_order_total();
        $total = round($total, 2);

        return $total;
    }



    public function getPaymentService($version = 2)
    {

        if (@$this->paymentServices[$version] == null) {
            $this->paymentServices[$version] = PaymentServiceFactory::create([
                'logger' => new WoocommerceLoggingRepository(),
                'loggerLogLevel' => 'ALL',
                'version' => $version,
                'config' => [
                    'testMode' => trim(strtolower($this->fac_test_mode)) == 'yes',
                    'debugMode' => trim(strtolower($this->fac_test_mode)) == 'yes',
                    'enable3DS' => trim(strtolower($this->fac_3d_secure)) == 'yes',
                    'enableAVS' => trim(strtolower($this->fac_enabled_avs)) == 'yes',
                    'enableTokenization' => trim(strtolower($this->fac_tokenization)) == "yes",
                    'paymentMode' => $this->fac_payment_type,
                    'transactionCacheTime' => $this->transactionCacheTime ?? 10,
                    'acquirerId' => '464748',
                    'merchantId' => $this->fac_merchant_id,
                    'merchantPassword' => $this->fac_merchant_password,
                    'facpg2MerchantId' => $this->fac_merchant_id,
                    'facpg2MerchantPassword' => $this->fac_merchant_password,
                    'kountConfig' => [
                        'enabled' => trim(strtolower($this->kount_enabled)) == 'yes',
                        'merchantId' => $this->kount_merchantid,
                        'testMode' => trim(strtolower($this->kount_test_mode)) == 'yes'
                    ],
                    'hppConfig' => [
                        'pageSet' => $this->hpp_pageset,
                        'pageName' => $this->hpp_pagename,
                        'enabled' => trim(strtolower($this->enable_hpp)) == 'yes'
                    ]
                ]
            ]);
        }

        return @$this->paymentServices[$version];
    }

    protected function getOrderIdentifier($orderId)
    {
        return $this->fac_order_prefix . '_' . $orderId . '_' . strtoupper(uniqid());
    }

    protected function getOrderIdFromIdentifier($identifier)
    {
        return @intval(explode('_', $identifier)[1]);
    }

    public function get_setting($settingName, $defaultValue = null)
    {
        if (
            !empty($settingName) &&
            !in_array($settingName, static::protected_settings)
        ) {
            return $this->get_option($settingName, $defaultValue);
        }
        return $defaultValue;
    }

    public function is_available()
    {
        $is_available = ('yes' === $this->enabled) ? true : false;

        if ($is_available && WC()->cart && 0 >= $this->get_order_total()) {


            if (class_exists('WC_Subscription')) {
                if (WC_Subscriptions_Cart::cart_contains_subscription()) {
                    $is_available = true;
                }
            } else {
                $is_available = false;
            }
        }

        if (is_admin()) {
            return $is_available;
        }

        return $is_available;
    }

    /* Admin Panel Options. */

    function admin_options()
    {
        ?>
        <style>
            .fac_add_banner {
                float: left;
                position: absolute;
                right: 20px;
                width: 230px;
                z-index: 1;
            }

            .fac_form_content {
                float: left;
                position: relative;
                width: 100%;
            }

            .fac_add_banner>ul {
                float: left;
                margin: 0;
                width: auto;
            }

            .fac_add_banner ul li {
                float: left;
                margin-bottom: 20px;
                width: auto;
            }

            .fac_add_banner ul li a {
                float: left;
                width: 230px;
            }

            .fac_add_banner ul li a>img {
                float: left;
                max-width: 100%;
                width: 100%;
            }

            #woocommerce_wcfac_description {
                width: 720px
            }

            .fac-admin-table td,
            .fac-admin-table th {
                padding: 1em;
                border-style: solid;
                border-width: 3px;
                border-color: #ffffff;
            }
        </style>


        <h3><?php _e('First Atlantic Commerce Payment Gateway', 'wcwcfac'); ?></h3>
        <div class="fac_add_banner">
            <ul>
                <li>

                </li>

            </ul>
        </div>
        <table class="form-table fac-admin-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        <script type="text/javascript">


          jQuery(document).ready(function() {

            jQuery("form input[type='text']").attr('autocomplete', 'off');

            if (jQuery('#woocommerce_wcfac_kount_enabled').prop("checked") == false) {
                jQuery('#woocommerce_wcfac_kount_test_mode').closest('td').closest('tr')
                .hide();
                jQuery('#woocommerce_wcfac_kount_merchantid').closest('td').closest('tr')
                .hide();

            }


            jQuery('#woocommerce_wcfac_kount_enabled').click(function() {
                if (jQuery(this).prop("checked") == false) {
                    jQuery('#woocommerce_wcfac_kount_test_mode').closest('td').closest('tr')
                    .hide();
                    jQuery('#woocommerce_wcfac_kount_merchantid').closest('td').closest('tr')
                    .hide();
                } else {
                    jQuery('#woocommerce_wcfac_kount_test_mode').closest('td').closest('tr')
                    .show();
                    jQuery('#woocommerce_wcfac_kount_merchantid').closest('td').closest('tr')
                    .show();
                }
            })


        });
    </script>
    <?php
}

/* Initialise Gateway Settings Form Fields. */

public function init_form_fields()
{
        // global $woocommerce;
        // $shipping_methods = array();

        // if (is_admin())
        //     foreach ($woocommerce->shipping->load_shipping_methods() as $method) {
        //         $shipping_methods[$method->id] = $method->get_title();
        //     }


    $this->form_fields = apply_filters('fac_gateway_form_fields', array(
        'enabled' => array(
            'title' => __('Enable/Disable Payment Gateway', 'wcwcfac'),
            'type' => 'checkbox',
            'label' => __('Enable FAC Payment Gateway', 'wcwcfac'),
            'default' => 'no'
        ),
        'fac_test_mode' => array(
            'title' => __('Enable/Disable FAC Test Mode', 'wcwcfac'),
            'type' => 'checkbox',
            'label' => __('Enable Test Mode', 'wcwcfac'),
            'description' => __('Test mode enables you to test payments before going live. If you select this option, you must enter credentials for your TEST FAC account. When you are ready to accept live payments, uncheck this option and enter your PRODUCTION FAC account credentials.', 'wcwcfac'),
            'default' => 'yes'
        ),
        'fac_payment_type' => array(
            'title' => __('Payment Method', 'wcwcfac'),
            'type' => 'select',
            'options' => array(
                "authorize_capture" => __('Authorize & Capture Payments Immediately', 'wcwcfac'),
                "authorize" => __('Authorize then Capture Payments Manually Later', 'wcwcfac')
            ),
            'description' => __('During authorization, the customer\'s card is verified and the balance is reserved on the card. During capture, the payment is taken from the customer\'s card and processed into the merchant\'s account. If you select "Authorize & Capture Payments Immediately" then both will be done during successful checkout on the website. However, if you select "Authorize then Capture Payments Manually Later" then the payment will be authorized on the customer\'s card, but you will have to manually capture the payment from the order page in Woocommerce.', 'wcwcfac'),
            'default' => 'authorize_capture'
        ),
        'fac_3d_secure' => array(
            'title' => __('Enable/Disable 3D Secure', 'wcwcfac'),
            'type' => 'checkbox',
            'label' => __('Enable 3D secure processing of payments', 'wcwcfac'),
            'default' => 'yes'
        ),
        'fac_enabled_avs' => array(
            'title' => __('Enable/Disable Address Verification (AVS)', 'wcwcfac'),
            'type' => 'checkbox',
            'label' => __('Enable address verification checks', 'wcwcfac'),
            'description' => __('Only enable this option if Address Verification (AVS) is supported by your merchant account & bank.', 'wcwcfac'),
            'default' => 'no'
        ),
        'title' => array(
            'title' => __('Title of Payment Method', 'wcwcfac'),
            'type' => 'text',
            'description' => __('This is the title of the payment option which the user selects during checkout.', 'wcwcfac'),
            'desc_tip' => true,
            'autocomplete' => 'off',
            'default' => __('Credit Card', 'wcwcfac')
        ),
        'description' => array(
            'title' => __('Description of Payment Method', 'wcwcfac'),
            'type' => 'textarea',
            'description' => __('This contains the description which the user sees during checkout.', 'wcwcfac'),
            'default' => __('Pay securely with your Credit Card', 'wcwcfac')
        ),
            /*
            'enable_hpp' => array(
                'title' => __('Enable/Disable Hosted Payment Page (HPP)', 'wcwcfac'),
                'type' => 'checkbox',
                'label' => __('Enable Hosted Payment Page (HPP)', 'wcwcfac'),
                'description' => __('Check to enable Hosted Payment Page (HPP). HPP must also be configured in your FAC Merchant Dashboard.', 'wcwcfac'),
                'default' => 'no'
            ),
            'hpp_pageset' => array(
                'title' => __('Page Set Name', 'wcwcfac'),
                'type' => 'text',
                'description' => __('Get this from Hosted Payment Pages tab from your FAC Merchant Dashboard.', 'wcwcfac'),
                'default' => __('', 'wcwcfac')
            ),
            'hpp_pagename' => array(
                'title' => __('Page Set Name', 'wcwcfac'),
                'type' => 'text',
                'description' => __('Get this from Hosted Payment Pages tab from your FAC Merchant Dashboard.', 'wcwcfac'),
                'default' => __('', 'wcwcfac')
            ),*/
            'fac_tokenization' => array(
                'title' => __('Credit Card Tokenization', 'wcwcfac'),
                'type' => 'checkbox',
                'label' => __('Enable Tokenization', 'wcwcfac'),
                'description' => __('Allow customers to securely save their payment details for future checkout. This is required for card registration or subscription services.', 'wcwcfac'),
                'default' => 'no'
            ),
            'fac_card_types' => array(
                'title' => __('Supported Card Types', 'wcwcfac'),
                'type' => 'multiselect',
                'custom_attributes' => ['multiple' => 'multiple'],
                'class' => 'select2',
                'css' => "height:150px;",
                'options' =>
                array(
                    "american_express" => __("American Express", 'wcwcfac'),
                    "diners_club" => __("Diners Club", 'wcwcfac'),
                    "discover_novus" => __("Discover/Novus", 'wcwcfac'),
                    "jcb" => __("JCB", 'wcwcfac'),
                    "mastercard" => __("MasterCard", 'wcwcfac'),
                    "maestro" => __("Maestro", 'wcwcfac'),
                    "keycard" => __("NCB Keycard", 'wcwcfac'),
                    "visa" => __("Visa", 'wcwcfac')
                ),
                'autocomplete' => 'off',
                'default' => "visa",

            ),
            'fac_merchant_id' => array(
                'title' => __('FAC Merchant ID', 'wcwcfac'),
                'type' => 'text',
                'description' => __('The Merchant ID of your First Atlantic Commerce account.', 'wcwcfac'),
                'default' => __('', 'wcwcfac')
            ),
            'fac_merchant_password' => array(
                'title' => __('FAC Merchant Processing Password', 'wcwcfac'),
                'type' => 'text',
                'description' => __('The Processing Password of your First Atlantic Commerce account.', 'wcwcfac'),
                'default' => __('', 'wcwcfac'),
            ),
            'fac_order_prefix' => array(
                'title' => __('FAC Order ID Prefix (3 Characters)', 'wcwcfac'),
                'type' => 'text',
                'description' => __('Enter a 3 digit order prefix to be used in the order IDs sent to FAC. This is sometimes useful if you use your FAC account to process transactions from multiple websites as it can allow you to easily pinpoint transactions from each website in your FAC merchant administration account.', 'wcwcfac'),
                'default' => __('FAC', 'wcwcfac'),
            ),
            'transactionCacheTime' => array(
                'title' => __('FAC Transaction Cache Time', 'wcwcfac'),
                'type' => 'numeric',
                'description' => __('The amount of seconds a FAC Transaction should take to be processed. Less times reduces the likelihood of possible security threats. 10 seconds recommended.', 'wcwcfac'),
                'default' => __(10, 'wcwcfac'),
            ),
            // 'kount_enabled' => array(
            //     'title' => __('Enable/Disable', 'wcwcfac'),
            //     'type' => 'checkbox',
            //     'label' => __('Enable Kount', 'wcwcfac'),
            //     'description' => __('Kount requires customization of your website\'s .htaccess file. Please refer to our documentation for details.', 'wcwcfac'),
            //     'default' => 'no'
            // ),
            // 'kount_test_mode' => array(
            //     'title' => __('Enable/Disable Test Mode for Kount', 'wcwcfac'),
            //     'type' => 'checkbox',
            //     'label' => __('Enable Kount Test Mode', 'wcwcfac'),
            //     'description' => __('Test mode enables you to test payments before going live. Use this option to test your KOUNT service then disable when you are ready to start accepting live payments.', 'wcwcfac'),
            //     'default' => 'yes'
            // ),
            // 'kount_merchantid' => array(
            //     'title' => __('Kount Merchant ID', 'wcwcfac'),
            //     'type' => 'text',
            //     'default' => __('', 'wcwcfac')
            // ),
            'enable_fac_logo' => array(
                'title' => __('Show FAC & 3D Secure Logos At Checkout', 'wcwcfac'),
                'type' => 'checkbox',
                'label' => __('Enable Logos', 'wcwcfac'),
                'description' => __('When enabled, the powered by FAC, Visa Secure, and Mastercard SecureCode logos will appear under the credit card form during checkout. This may be a required for approval of your website by FAC.', 'wcwcfac'),
                'default' => 'yes'
            ),
            'force_error_display' => array(
                'title' => __('Force Display of Checkout Errors', 'wcwcfac'),
                'type' => 'checkbox',
                'label' => __('Checkout error messages are not being displayed. ', 'wcwcfac'),
                'description' => __('Some themes do not display woocommerce error messages after the 3DS redirects. Check this box only if you notice that error messages are not displayed on your checkout page after a card is declined.', 'wcwcfac'),
                'default' => 'no'
            ),
        ));
}



public function can_fac_process_order($order)
{
    return $order && $order->payment_method == 'wcfac';
}

public function process_refund($orderId, $amount = null, $reason = '')
{
    $order = new WC_Order($orderId);
    $orderNumber = get_post_meta($orderId, static::FAC_ORDER_ID_META_KEY, true);
    $transactionId = get_post_meta($orderId, static::FAC_TRANSACTION_ID_META_KEY, true);
    if (
        !empty($transactionId) &&
        !empty($orderNumber) &&
        $order &&
        $amount <= $order->get_total()
    ) {
        $modify_type = get_post_meta($orderId, static::FAC_PAYMENT_TYPE_META_KEY, true);
        $transactionDetails = [
            'TransactionIdentifier' => $transactionId,
            'OrderIdentifier' => $orderNumber,
            'TotalAmount' => floatval($amount),
            'CurrencyCode' => $order->get_currency()
        ];
        $paymentResponse = null;

        if ($modify_type == 'authorize_capture') {
            $paymentResponse = $this->getPaymentService()
            ->refund(new \FacPayments\Entities\Requests\RefundRequest(
                $transactionDetails
            ));
            $msg = $paymentResponse->isSuccessful() ?
            __("Status : Refund transaction successfully.", 'wcwcfac') :
            __($paymentResponse->getMessage(), 'wcwcfac');
        } else {
            $paymentResponse = $this->getPaymentService()
            ->void(new \FacPayments\Entities\Requests\VoidRequest(
                array_merge(
                    $transactionDetails,
                    [
                        'autoReversal' => true
                    ]
                ),
                [],
                true
            ));
            $msg = $paymentResponse->isSuccessful() ?
            __("Status : Reversal transaction successfully.", 'wcwcfac') :
            __($paymentResponse->getMessage(), 'wcwcfac');
        }

        if ($paymentResponse && $paymentResponse->isSuccessful()) {
            update_post_meta($orderId, static::FAC_PAYMENT_TYPE_META_KEY, "authorize_capture");
            $order->add_order_note($msg . (!empty($reason) ? (" Reason:" . $reason) : ''));
            $order->payment_complete($paymentResponse->getTransactionIdentifier());
            return true;
        } else {
            $order->add_order_note(sprintf('FAC payment error: %s', $msg));
            return false;
        }
    }
    return false;
}

public function captureTransaction($orderId)
{
    $paymentResponse = new PaymentResponse([
        'message' => __("We were unable to capture this transaction at this time.", 'wcwcfac')
    ]);
    $order = new WC_Order($orderId);
    $orderNumber = get_post_meta($orderId, static::FAC_ORDER_ID_META_KEY, true);
    $transactionId = get_post_meta($orderId, static::FAC_TRANSACTION_ID_META_KEY, true);
    if (get_post_meta($orderId, static::FAC_PAYMENT_TYPE_META_KEY, true) == 'authorize_capture') {
        return new PaymentResponse([
            'message' => __("Transaction already captured", 'wcwcfac')
        ]);
    }
    if (
        !empty($transactionId) &&
        !empty($orderNumber) &&
        $order &&
        $order->get_total() > 0
    ) {
        $modify_type = get_post_meta($orderId, static::FAC_PAYMENT_TYPE_META_KEY, true);
        $transactionDetails = [
            'TransactionIdentifier' => $transactionId,
            'OrderIdentifier' => $orderNumber,
            'TotalAmount' => floatval($order->get_total()),
            'CurrencyCode' => $order->get_currency()
        ];
        $paymentResponse = $this->getPaymentService()
        ->capture(new \FacPayments\Entities\Requests\CaptureRequest(
            $transactionDetails
        ));

        update_post_meta($orderId, static::FAC_PAYMENT_TYPE_META_KEY, "authorize_capture");
        if ($paymentResponse && $paymentResponse->isSuccessful()) {
            update_post_meta($orderId, static::FAC_PAYMENT_TYPE_META_KEY, "authorize_capture");
            $order->add_order_note($paymentResponse->getMessage());
        } else {
            $order->add_order_note(sprintf(__('FAC payment error', 'wcwcfac') . ': %s', $paymentResponse->getMessage()));
        }
    }
    return $paymentResponse;
}

protected function getCardDetailsFromRequest($postData, $order)
{
    $cardDetails = [];

    $userId = get_current_user_id();

    if (!$userId) {

        $userId = $order->get_user_id();
    }

    $billingAddress = $order->get_address('billing');

    if (isset($postData['isitnewcard']) && $postData['isitnewcard'] == "new") {
        $cardExpiry = preg_split('/\s?\/\s?/', wc_clean($postData['wcfac-card-expiry']), 2);
        $cardPan = str_replace([' ', '-'], '', wc_clean($postData['wcfac-card-number']));
        $cardDetails = [
            'CardPan' => $cardPan,
            'CardCvv' =>  wc_clean($postData['wcfac-card-cvc']),
            'CardExpiration' => $cardExpiry[1] . $cardExpiry[0],
            'CardholderName' => sprintf(
                "%s %s",
                $billingAddress["first_name"],
                $billingAddress["last_name"]
            ),
            'ExpiryYear' => '20' . $cardExpiry[1],
            'ExpiryMonth' => $cardExpiry[0],
            'CardType' => $this->cardDetectorService->detect($cardPan)
        ];
    } else if (isset($postData['isitnewcard']) && $postData['isitnewcard'] != "new") {

        $cardToken = $this->tokenizedCardRepo->getUserCard($userId, $postData['isitnewcard']);
        $cardDetails = [
            'Token' => $cardToken->token,
            'CardExpiration' => substr($cardToken->expiryYear, -2) . $cardToken->expiryMonth,
            'ExpiryYear' => $cardToken->expiryYear,
            'ExpiryMonth' => $cardToken->expiryMonth,
            'CardType' => $cardToken->cardType,
            'Last4' => $cardToken->last4

        ];
    }
    return $cardDetails;
}



    /**
     * Process the payment
     */
    function process_payment2($orderId)
    {
        //TODO : check nonce - "woocommerce-process-checkout-nonce": "da0a32e56d"
        $encodedCart = base64_encode(serialize(WC()->cart->get_cart_contents()));
        $order = new WC_Order($orderId);
        $hasSubscription = false;
        $shouldTokenize = ($_POST['fac_tokenization'] == 1);

        if ($order) {
            $paymentResponse = null;

            $cardDetails = $this->getCardDetailsFromRequest($_POST, $order);

            if (class_exists('WC_Subscription')) {
                if (wcs_order_contains_subscription($order)) {
                    $hasSubscription = true;

                    if (isset($_POST['isitnewcard']) && $_POST['isitnewcard'] != "new") {

                        if (!add_post_meta($orderId, static::ORDER_META_KEY_FOR_SUBSCRIPTION_PURCHASED_BY_TOKEN, $_POST['isitnewcard'], true)) {

                            update_post_meta($orderId, static::ORDER_META_KEY_FOR_SUBSCRIPTION_PURCHASED_BY_TOKEN, $_POST['isitnewcard'], true);
                        }
                    } else if (!$shouldTokenize) {
                        $shouldTokenize = true;
                    }
                }
            }




            $transactionDetails = [
                'OrderIdentifier' => $this->getOrderIdentifier($orderId),
                'TotalAmount' => $this->get_order_total(),
                'CurrencyCode' => useOrElse((method_exists($order, 'get_currency') ? strtoupper($order->get_currency()) : null), 'USD'),
                'Source' => $cardDetails,
                'BillingAddress' => [
                    'FirstName' => $_POST['billing_first_name'],
                    'LastName' => $_POST['billing_last_name'],
                    'Line1' => $_POST['billing_address_1'],
                    'Line2' => $_POST['billing_address_2'],
                    'City' => $_POST['billing_city'],
                    //'State' => $_POST['billing_state'],
                    'PostalCode' => $_POST['billing_postcode'],
                    'CountryCode' => $_POST['billing_country'],
                    'EmailAddress' =>  $_POST['billing_email'],
                    'PhoneNumber' => $_POST['billing_phone'],
                ],
                'ShippingAddress' => [
                    'FirstName' => useOrElse($_POST['shipping_first_name'], $_POST['billing_first_name']),
                    'LastName' => useOrElse($_POST['shipping_last_name'], $_POST['billing_last_name']),
                    'Line1' => $_POST['shipping_address_1'],
                    'Line2' => $_POST['shipping_address_2'],
                    'City' => $_POST['shipping_city'],
                    //'State' =>  $_POST['shipping_state'],
                    'PostalCode' => $_POST['shipping_postcode'],
                    'CountryCode' => $_POST['shipping_country'],
                    'EmailAddress' =>  $_POST['billing_email'],
                    'PhoneNumber' => $_POST['billing_phone'],
                ],
                'AddressMatch' => false,
                'ExtendedData' => [
                    'MerchantResponseUrl' => home_url("fac-confirm-transaction/?") . http_build_query([
                        'tokenize' => $shouldTokenize,
                        '3DS_VERSION' => 2
                    ]),
                ],
            ];



            if ($shouldTokenize) {
                $this->saveTokenizedCardForOrder($orderId, array_merge(
                    $cardDetails,
                    [
                        'CardPan' => isset($cardDetails['CardPan']) ? substr($cardDetails['CardPan'], -4) : null,
                        'CardholderName' => ''
                    ]
                ));
            }

            if ($this->fac_payment_type == 'authorize_capture') {
                $paymentResponse = $this->getPaymentService()
                ->authorizeAndCapture(
                    new \FacPayments\Entities\Requests\SaleRequest($transactionDetails)
                );
            } else {
                $paymentResponse = $this->getPaymentService()
                ->authorize(
                    new \FacPayments\Entities\Requests\AuthRequest($transactionDetails)
                );
            }
            if ($paymentResponse) {
                $postMetaUpdates = [
                    static::FAC_TRANSACTION_ID_META_KEY => $paymentResponse->getTransactionIdentifier(),
                    static::FAC_ORDER_ID_META_KEY => $paymentResponse->getOrderIdentifier(),
                    static::FAC_PAYMENT_TYPE_META_KEY => $this->fac_payment_type,
                    static::FAC_ORDER_CART_CONTENTS_META_KEY => $encodedCart,
                    '_fac_is_subscription' => (string) $hasSubscription
                ];
                foreach ($postMetaUpdates as $metaKey => $metaValue) {
                    if (!add_post_meta($orderId, $metaKey, $metaValue, true)) {
                        update_post_meta($orderId, $metaKey, $metaValue);
                    }
                }
            }

            if (
                $paymentResponse &&
                $paymentResponse->isSuccessful() &&
                $paymentResponse->requiresRedirect()
            ) {
                $encodedRedirect = base64_encode($paymentResponse->getRedirectUri());
                if (!add_post_meta($orderId, static::FAC_ORDER_3DS_REDIRECT_META_KEY, $encodedRedirect, true)) {
                    update_post_meta($orderId, static::FAC_ORDER_3DS_REDIRECT_META_KEY, $encodedRedirect);
                }
                return array(
                    'result' => 'success',
                    'redirect' => (home_url(
                        '/fac-3ds-redirect?' . http_build_query([
                            'tokenize' => $shouldTokenize,
                            'order_id' => $orderId
                        ])
                    )
                )

                );
                exit;
            } else if (
                $paymentResponse &&
                $paymentResponse->isSuccessful() &&
                !$paymentResponse->requiresRedirect()
            ) {
                $this->confirmOrderStatus($orderId, $paymentResponse->toArray());
                wc_add_notice(__('Payment Successful', 'wcfacgdc'), 'notice');
            } else {
                $this->confirmOrderStatus($orderId, $paymentResponse->toArray());
            }
        }
        return;
    }

    function process_payment($orderId)
    {
        //TODO : check nonce - "woocommerce-process-checkout-nonce": "da0a32e56d"
        $encodedCart = base64_encode(serialize(WC()->cart->get_cart_contents()));
        $order = new WC_Order($orderId);
        $orderTotal = $order->get_total();
        //$hasSubscription = false;
        $shouldTokenize = ($_POST['fac_tokenization'] == 1);

        $enable3DSecure = trim(strtolower($this->fac_3d_secure)) == 'yes';
        //$_POST["update_all_subscriptions_payment_method"]
        $isSubscriptionRenewal = false;
        $isSubscription = false;
        $containSubscription = false;

        if (class_exists('WC_Subscription')) {
            $isSubscriptionRenewal = wcs_order_contains_renewal($order);
            $isSubscription = wcs_is_subscription($orderId);
            $containSubscription = wcs_order_contains_subscription($order);
        }

        if ($containSubscription || $isSubscription) {
            $shouldTokenize = true;
        }

        if ($isSubscription) {
            $subscription = new WC_Subscription($orderId);
            $parentOrderId = $subscription->get_parent_id();
            $parentOrder =  new WC_Order($parentOrderId);

            $orderTotal = $subscription->get_total();
            $orderReturnUrl = $this->get_return_url();
            //$encodedCartsubscriptionReturnUrl = $subscription->get_return_url();
        }


        $billingAddress = $order->get_address('billing');
        $shippingAddress = $order->get_address('shipping');


        delete_post_meta($orderId, static::ORDER_META_KEY_FOR_SUBSCRIPTION_PURCHASED_BY_TOKEN);

        if ($order) {
            $paymentResponse = null;
            $paymentResponseData = null;

            $cardDetails = $this->getCardDetailsFromRequest($_POST, $order);

            if (class_exists('WC_Subscription')) {
                if ($containSubscription || $isSubscription || $isSubscriptionRenewal) {

                    if (isset($_POST['isitnewcard']) && $_POST['isitnewcard'] != "new") {

                        if (!add_post_meta($orderId, static::ORDER_META_KEY_FOR_SUBSCRIPTION_PURCHASED_BY_TOKEN, $_POST['isitnewcard'], true)) {

                            update_post_meta($orderId, static::ORDER_META_KEY_FOR_SUBSCRIPTION_PURCHASED_BY_TOKEN, $_POST['isitnewcard'], true);
                        }
                    }
                }
            }


            $transactionDetails = [
                'OrderIdentifier' => $this->getOrderIdentifier($orderId),
                'TotalAmount' => $orderTotal,
                'CurrencyCode' => useOrElse((method_exists($order, 'get_currency') ? strtoupper($order->get_currency()) : null), 'USD'),
                'Source' => $cardDetails,
                'BillingAddress' => [
                    'FirstName' => $billingAddress['first_name'],
                    'LastName' => $billingAddress['last_name'],
                    'Line1' => $billingAddress['address_1'],
                    'Line2' => $billingAddress['address_2'],
                    'City' => $billingAddress['city'],
                    //'State' => $_POST['billing_state'],
                    'PostalCode' => $billingAddress['postcode'],
                    'CountryCode' => $billingAddress['country'],
                    'EmailAddress' =>  $billingAddress['email'],
                    'PhoneNumber' => $billingAddress['phone'],
                ],
                'ShippingAddress' => [
                    'FirstName' => useOrElse($shippingAddress['first_name'], $billingAddress['first_name']),
                    'LastName' => useOrElse($shippingAddress['last_name'], $billingAddress['first_name']),
                    'Line1' => $shippingAddress['address_1'],
                    'Line2' => $shippingAddress['address_2'],
                    'City' => $shippingAddress['city'],
                    //'State' => $_POST['billing_state'],
                    'PostalCode' => $shippingAddress['postcode'],
                    'CountryCode' => $shippingAddress['country'],
                    'EmailAddress' =>  $shippingAddress['email'],
                    'PhoneNumber' => $shippingAddress['phone'],
                ],
                'AddressMatch' => false,
                'ThreeDSecure' => $enable3DSecure,
                'ExtendedData' => !$enable3DSecure ? null : [
                    'MerchantResponseUrl' => home_url("fac-confirm-transaction/?") . http_build_query([
                        'tokenize' => $shouldTokenize,
                        '3DS_VERSION' => 2
                    ]),
                ],
            ];


            if ($shouldTokenize || $containSubscription || $isSubscription || $isSubscriptionRenewal) {
                $this->saveTokenizedCardForOrder($orderId, array_merge(
                    $cardDetails,
                    [
                        'CardPan' => isset($cardDetails['CardPan']) ? substr($cardDetails['CardPan'], -4) : null,
                        'CardholderName' => ''
                    ]
                ));
            }

            if ($order->get_total() == 0 || $isSubscription) {
                $paymentResponse = $this->getPaymentService()
                ->riskManagement(
                    new \FacPayments\Entities\Requests\RiskMgmtRequest($transactionDetails)
                );
            } else if ($this->fac_payment_type == 'authorize_capture') {
                $paymentResponse = $this->getPaymentService()
                ->authorizeAndCapture(
                    new \FacPayments\Entities\Requests\SaleRequest($transactionDetails)
                );
            } else {
                $paymentResponse = $this->getPaymentService()
                ->authorize(
                    new \FacPayments\Entities\Requests\AuthRequest($transactionDetails)
                );
            }
            if ($paymentResponse) {
                $postMetaUpdates = [
                    static::FAC_TRANSACTION_ID_META_KEY => $paymentResponse->getTransactionIdentifier(),
                    static::FAC_ORDER_ID_META_KEY => $paymentResponse->getOrderIdentifier(),
                    static::FAC_PAYMENT_TYPE_META_KEY => $this->fac_payment_type,
                    static::FAC_ORDER_CART_CONTENTS_META_KEY => $encodedCart,
                    '_fac_is_subscription' => (string) $containSubscription
                ];
                foreach ($postMetaUpdates as $metaKey => $metaValue) {
                    if (!add_post_meta($orderId, $metaKey, $metaValue, true)) {
                        update_post_meta($orderId, $metaKey, $metaValue);
                    }
                }
            }

            $paymentResponseData = $paymentResponse->getData();

            
            if (
                $paymentResponse &&
                $paymentResponse->isSuccessful() &&
                $paymentResponse->requiresRedirect()
            ) {
                $encodedRedirect = base64_encode($paymentResponse->getRedirectUri());
                if (!add_post_meta($orderId, static::FAC_ORDER_3DS_REDIRECT_META_KEY, $encodedRedirect, true)) {
                    update_post_meta($orderId, static::FAC_ORDER_3DS_REDIRECT_META_KEY, $encodedRedirect);
                }
                return array(
                    'result' => 'success',
                    'redirect' => (home_url(
                        '/fac-3ds-redirect?' . http_build_query([
                            'tokenize' => $shouldTokenize,
                            'order_id' => $orderId
                        ])
                    )
                )

                );
                exit;
            } else if (
                $paymentResponse &&
                $paymentResponse->isSuccessful() &&
                $paymentResponseData["Approved"] == true &&
                !$paymentResponse->requiresRedirect()
            ) {
                $this->confirmOrderStatus($orderId, $paymentResponse->toArray());
                wc_add_notice(__('Payment Successful', 'wcfacgdc'), 'notice');
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else {
                $this->confirmOrderStatus($orderId, $paymentResponse->toArray());
            }
        }
        return;
    }

    public function initiate3DSRedirect($orderId)
    {
        if ($orderId) {
            $encodedRedirect = get_post_meta($orderId, static::FAC_ORDER_3DS_REDIRECT_META_KEY, true);
            if (!empty($encodedRedirect)) {
                $encodedRedirect = @base64_decode($encodedRedirect);
                if (!empty($encodedRedirect)) {
                    delete_post_meta($orderId, static::FAC_ORDER_3DS_REDIRECT_META_KEY);
                    echo $encodedRedirect;
                    die;
                }
            }
        }
        wp_redirect(home_url());
    }

    public function confirmTransaction($postData, $version = 2, $shouldTokenize = false, $hasSubscription = false)
    {
        $paymentResponse = $this->getPaymentService($version)
        ->confirmTransaction($postData);
        error_log("3ds Confirm payment response : " . json_encode(func_get_args(), JSON_PRETTY_PRINT));
        error_log(json_encode($paymentResponse->toArray(), JSON_PRETTY_PRINT));
        $orderId = $this->getOrderIdFromIdentifier($paymentResponse->getOrderIdentifier());
        $order = $this->confirmOrderStatus($orderId, $paymentResponse->toArray(), $shouldTokenize);
        $isSubscription = false;

        if (class_exists('WC_Subscription')) {
            $isSubscription = wcs_is_subscription($orderId);
        }

        if ($paymentResponse->isSuccessful()) {


            if ($isSubscription) {
                $paymentResponse->setRedirectUri(wc_get_account_endpoint_url('subscriptions'));
            } else {
                $paymentResponse->setRedirectUri($this->get_return_url($order));
            }
        } else {
            $return_url = $this->get_return_url();
            $checkout_url = wc_get_checkout_url();

            if(isset($order))
            {
                $checkout_url = $order->get_checkout_payment_url();
            }

            // check if error declines and codes should be sent in query string
            if (filter_var($this->force_error_display, FILTER_VALIDATE_BOOLEAN)) {
                $query = array();
                $query["declined"] = "true";
                $query["error_code"] = $paymentResponse->getErrorCode();
                $query["message"] = $paymentResponse->getMessage();
                $checkout_url = $this->appendQueryStringToURL($checkout_url, $query);
            }
            //var_dump($paymentResponse);
            //die();  
            $paymentResponse->setRedirectUri($checkout_url);
        }


        return $paymentResponse;
    }

    /**
     * BEGIN: TOKENIZED CARD MANAGEMENT
     * TODO: Refractor to a Tokenization Service
     */

    protected function updateTokenizeCardsFromOrder($orderId, $tokenizedPan, $saveToUser = false)
    {
        $userId = get_current_user_id();
        $tokenizedCard = null;
        if (!$userId) {
            $order = wc_get_order($orderId);
            $userId = $order->get_user_id();
        }
        error_log("inside updateTokenizeCardsFromOrder for user $userId order $orderId and token $tokenizedPan");
        if (
            $orderId &&
            !empty($tokenizedPan) &&
            $tokenizedPan != 'new'
        ) {
            error_log("considering valid tokenized pan. about to extract card details");
            $cardDetails = $this->getTokenizedCardForOrder($orderId);

            if (isset($cardDetails)) {

                if (
                    !empty($cardDetails) && (isset($cardDetails['CardPan']) || $cardDetails('Token'))
                ) {

                    $tokenizedCard = new TokenizedCard([

                        'userId' => $userId,
                        'token' => $tokenizedPan,
                        'last4' => substr(
                            Arr::get(
                                $cardDetails,
                                'CardPan',
                                Arr::get($cardDetails, 'Last4')
                            ),
                            -4
                        ),
                        'expiryYear' => Arr::get($cardDetails, 'ExpiryYear'),
                        'expiryMonth' => Arr::get($cardDetails, 'ExpiryMonth'),
                        'cardType' => Arr::get($cardDetails, 'CardType', 'Credit Card')
                    ]);

                    error_log('Tokenized Card Result: ' . json_encode($tokenizedCard, JSON_PRETTY_PRINT));

                    if ($saveToUser && $$userId) {
                        $tokenizedCard = $this->tokenizedCardRepo->add($tokenizedCard);
                    }
                }
            }
        }

        return $tokenizedCard;
    }

    protected function saveTokenizedCardForOrder($orderId, $cardDetails = [])
    {
        $encodedDetails = base64_encode(serialize($cardDetails));
        if (!add_post_meta($orderId, static::ORDER_META_KEY_FOR_CARD_TO_BE_TOKENIZED, $encodedDetails, true)) {
            update_post_meta($orderId, static::ORDER_META_KEY_FOR_CARD_TO_BE_TOKENIZED, $encodedDetails);
        }
        error_log("Saving card details for order $orderId : " . json_encode($cardDetails, JSON_PRETTY_PRINT));
        return true;
    }

    protected function getTokenizedCardForOrder($orderId)
    {
        $encodedDetails = get_post_meta($orderId, static::ORDER_META_KEY_FOR_CARD_TO_BE_TOKENIZED, true);

        if (!empty($encodedDetails))
            return @unserialize(base64_decode($encodedDetails));
        else
            return null;
    }



    protected function clearTokenizedCardForOrder($orderId)
    {
        error_log("Clearing tokenized card order details cache for order $orderId");
        return delete_post_meta($orderId, static::ORDER_META_KEY_FOR_CARD_TO_BE_TOKENIZED);
    }

    public function removeTokenizedCard($userId, $cardReferenceNo)
    {
        return $this->tokenizedCardRepo->removeUserCard($userId, $cardReferenceNo);
    }



    /**
     * Don't transfer customer meta to resubscribe orders.
     *
     * @param int $orderId
     * @param TokenizedCard $cardToken
     * @return bool
     */

    protected function setSubscriptionPaymentMethod($orderId, $cardToken)
    {

        if (!isset($cardToken))
            return false;

        $order = wc_get_order($orderId);

        if (!isset($order))
            return false;

        $oldPaymentMethodTitle = get_post_meta($orderId, static::FAC_OLD_PAYMENT_METHOD_TITLE_META_KEY, true);

        if (!isset($oldPaymentMethodTitle) || empty($oldPaymentMethodTitle))
            $oldPaymentMethodTitle = get_post_meta($orderId, static::FAC_PAYMENT_METHOD_TITLE_META_KEY, true);


        $cardTitle = sprintf(__('%s card ending %s (expires: %s/%s)', 'wcwcfac'), $cardToken->cardType, $cardToken->last4, $cardToken->expiryMonth, $cardToken->expiryYear);

        // store payment method in order meta
        update_post_meta($orderId, static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY, $cardToken->id);
        update_post_meta($orderId,  static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY . '_2', $cardToken);

        update_post_meta($orderId,  static::FAC_PAYMENT_METHOD_TITLE_META_KEY, $cardTitle);
        //update_post_meta( $orderId,  static::FAC_PAYMENT_METHOD_TITLE_META_KEY.'_2', $cardTitle);


        $subscriptionArgs =  array(
            'order_type' => array('any')
        );

        if (wcs_is_subscription($orderId)) {


            if (isset($oldPaymentMethodTitle) && !empty($oldPaymentMethodTitle)) {
                $newPaymentMethodTitle = get_post_meta($orderId, static::FAC_PAYMENT_METHOD_TITLE_META_KEY, true);
                $newPaymentMethodTitle = $cardTitle;


                if ($oldPaymentMethodTitle != $newPaymentMethodTitle) {
                    $order->add_order_note(sprintf(__('Payment method successfully changed from [ %s ] to [ %s ] by the subscriber.', 'wcwcfac'), $oldPaymentMethodTitle, $newPaymentMethodTitle));
                }
            }

            delete_post_meta($orderId, static::FAC_OLD_PAYMENT_METHOD_TITLE_META_KEY);
        } else {

            $subscriptions = wcs_get_subscriptions_for_order($orderId, $subscriptionArgs);

            // Also store it on the subscriptions being purchased in the order.
            foreach ($subscriptions as $subscription) {


                $oldPaymentMethodTitle = get_post_meta($subscription->id, static::FAC_OLD_PAYMENT_METHOD_TITLE_META_KEY, true);

                if (!isset($oldPaymentMethodTitle) || empty($oldPaymentMethodTitle))
                    $oldPaymentMethodTitle = get_post_meta($subscription->id, static::FAC_PAYMENT_METHOD_TITLE_META_KEY, true);

                update_post_meta($subscription->id, static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY, $cardToken->id);
                update_post_meta($subscription->id, static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY . '_2', $cardToken);
                update_post_meta($subscription->id, static::FAC_PAYMENT_METHOD_TITLE_META_KEY, $cardTitle);
                update_post_meta($subscription->id,  static::FAC_PAYMENT_METHOD_TITLE_META_KEY . '_2', $cardTitle);


                if (isset($oldPaymentMethodTitle) && !empty($oldPaymentMethodTitle)) {
                    $newPaymentMethodTitle = get_post_meta($subscription->id, static::FAC_PAYMENT_METHOD_TITLE_META_KEY, true);
                    $newPaymentMethodTitle = $cardTitle;
                    if ($oldPaymentMethodTitle != $newPaymentMethodTitle) {
                        $subscription->add_order_note(sprintf(__('Payment method successfully changed from [ %s ] to [ %s ] during renewal process initiated by subscriber.', 'wcwcfac'), $oldPaymentMethodTitle, $newPaymentMethodTitle));
                    }
                }

                delete_post_meta($subscription->id, static::FAC_OLD_PAYMENT_METHOD_TITLE_META_KEY);
            }
        }

        error_log("Saving card details for order $orderId : " . json_encode($cardToken, JSON_PRETTY_PRINT));
        return true;
    }


    public static function  get_subscription_customer_payment_methods()
    {
        $orders = wc_get_orders(array(
            'type' => 'shop_subscription',
            'limit' => -1,
            'status' => array('wc-active', 'wc-pending', 'wc-on-hold'),
            'meta_query' => array(
                array(
                    'key' => '__fac_customer_payment_method_id',
                    'compare' => 'EXISTS',
                )
            )
        ));

        $payment_methods = array();

        foreach ($orders as $order) {
            $payment_methods[$order->get_id()] = $order->get_meta('__fac_customer_payment_method_id', true);
        }

        return $payment_methods;
    }


    /**
     * Don't transfer customer meta to resubscribe orders.
     *
     * @param int $orderId
     * @return TokenizedCard $cardToken
     */

    protected function getSubscriptionPaymentMethod($orderId)
    {

        $order = wc_get_order($orderId);
        $userId = $order->get_user_id();

        $paymentMethodId = get_post_meta($orderId, static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY, true);


        $tokenizedCard = $this->tokenizedCardRepo->getUserCard($userId, $paymentMethodId);

        if (isset($tokenizedCard))
            return $tokenizedCard;
        else
            return null;
    }

    /**
     * END: TOKENIZED CARD MANAGEMENT
     */

    protected function confirmOrderStatus($orderId, array $paymentResponse = [], $shouldTokenize = null)
    {
        $order = wc_get_order($orderId);
        
        if (!$order) {
            return null;
        }

        $success = Arr::get($paymentResponse, 'success', false);
        $paymentResponseData = Arr::get($paymentResponse, 'data', false);
        $approved = Arr::get($paymentResponseData, 'Approved', false);
        $userId = get_current_user_id();

        $isSubscriptionRenewal = false;
        $isSubscription = false;
        $containSubscription = false;

        if (!$userId) {
            $order = wc_get_order($orderId);
            $userId = $order->get_user_id();
        }

        if (class_exists('WC_Subscription')) {

            $isSubscriptionRenewal = wcs_order_contains_renewal($order);
            $isSubscription = wcs_is_subscription($orderId);
            $containSubscription = wcs_order_contains_subscription($order);
        }



        if ($success && $approved) {
            $transactionIdentifier = Arr::get($paymentResponse, 'transactionIdentifier');
            $orderIdentifier = Arr::get($paymentResponse, 'orderIdentifier');

            $tokenizedCard = null;


            $tokenizedPan = Arr::get(
                $paymentResponse,
                'tokenizedPAN',
                Arr::get($paymentResponse, 'PanToken')
            );



            $purchasedByTokenId = get_post_meta($orderId, static::ORDER_META_KEY_FOR_SUBSCRIPTION_PURCHASED_BY_TOKEN, true);

            if (isset($purchasedByTokenId) && !empty($purchasedByTokenId)) {
                $tokenizedCard = $this->tokenizedCardRepo->getUserCard(
                    $userId,
                    $purchasedByTokenId
                );
            } else if (isset($tokenizedPan) && !empty($tokenizedPan)) {
                $tokenizedCard = $this->updateTokenizeCardsFromOrder($orderId, $tokenizedPan, true);
            }

            if ($shouldTokenize && isset($tokenizedCard)) {
                $this->tokenizedCardRepo->add($tokenizedCard);
            }

            if (($containSubscription || $isSubscription || $isSubscriptionRenewal) && isset($tokenizedCard)) {

                $this->setSubscriptionPaymentMethod($orderId, $tokenizedCard);
                /*
                    if($isSubscription)
                    {
                        $oldPaymentMethodTitle = $order->get_meta('_old_payment_method_title');
                        $newPaymentMethodTitle = get_post_meta($orderId, static::FAC_PAYMENT_METHOD_TITLE_META_KEY, true);

                        $order->add_order_note(sprintf(__('Payment method successfully changed from [ %s ] to [ %s ] by the subscriber.', 'wcwcfac'), $oldPaymentMethodTitle, $newPaymentMethodTitle));
                              
                    }
                    */
                }


                $order->payment_complete();
                $order->add_order_note(
                    sprintf(
                        __('FAC transaction complete', 'wcwcfac') .
                        ' (' .
                        __('ID', 'wcwcfac') . ': %1$s). ' .
                        __('OrderID', 'wcwcfac') . ' : %2$s',
                        $transactionIdentifier,
                        $orderIdentifier
                    )
                );
            //wc_add_notice('No charge was made to your card during this update process.');
            } else {
                $error_code = $paymentResponse['errorCode'];
                $general_error_message = __('Unfortunately we were unable to process your transaction. It may be a problem with your credit card or something technical on our end. Please check your credit card details and try again or contact us if the problem persists.', 'wcwcfac');
                $subscription_payment_method_change_error = __('Unfortunately we were unable to validate or charge your new payment method. Your payment method was not updated.');
                $specific_error = __('Reference:', 'wcwcfac') . ' ' . __(Arr::get($paymentResponse, 'message'), 'wcwcfac');

                wc_add_notice(($isSubscription ? $subscription_payment_method_change_error : $general_error_message) .
                    '<br/><strong>' .  $specific_error . '</strong>', 'error');

                if ($isSubscription && class_exists('WC_Subscription')) {
                    WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($order);

                    $oldPaymentMethodTitle = get_post_meta($orderId, static::FAC_OLD_PAYMENT_METHOD_TITLE_META_KEY, true);
                    update_post_meta($orderId, static::FAC_PAYMENT_METHOD_TITLE_META_KEY, $oldPaymentMethodTitle);
                    $order->add_order_note(sprintf(__('Payment method update failed. Payment method remains: %s.', 'wcwcfac'), $oldPaymentMethodTitle));
                } else {

                    $created_via = $order->get_created_via();
                    if($created_via == "checkout")
                    {
                    //$encodedCartContents = get_post_meta($orderId, static::FAC_ORDER_CART_CONTENTS_META_KEY, true);
                        $encodedCartContents = $order->get_meta(static::FAC_ORDER_CART_CONTENTS_META_KEY, true);
                        $order->set_status('cancelled');
                        $order->delete(true);

                        if (!empty($encodedCartContents)) {
                            WC()->cart->set_cart_contents(
                                unserialize(
                                    base64_decode($encodedCartContents)
                                )
                            );
                            WC()->cart->maybe_set_cart_cookies();
                            WC()->cart->calculate_totals();
                        }
                        $order = null;
                    } else
                    {
                        $order->set_status('pending');
                        $order->add_order_note( __('There was an error processing payment.', 'wcwcfac').' '.$specific_error.' '.__('Code:', 'wcwcfac').' '.$error_code );
                    }


                }



            }

            $this->clearTokenizedCardForOrder($orderId);
            delete_post_meta($orderId, static::ORDER_META_KEY_FOR_SUBSCRIPTION_PURCHASED_BY_TOKEN);

            return $order;
        }

        public function validate_fields()
        {
            $validated = true;
            if (isset($_POST['isitnewcard']) && $_POST['isitnewcard'] != 'new') {
                if (empty($_POST['isitnewcard'])) {
                    wc_add_notice($this->get_validation_error('Saved Card', $_POST['isitnewcard']), 'error');
                    $validated = false;
                }
            } else {
                if ($this->enable_hpp == 'yes') {
                    return $validated;
                }

                if (empty($_POST['wcfac-card-number'])) {
                    wc_add_notice($this->get_validation_error(__('Card Number', 'wcwcfac'), $_POST['wcfac-card-number']), 'error');
                    $validated = false;
                }

                if (!$this->is_credit_card_valid($_POST['wcfac-card-number'])) {
                    wc_add_notice($this->get_validation_error(__('Card Number', 'wcwcfac'), 'invalid'), 'error');
                    $validated = false;
                }


                $expiryDate = str_replace(' ', '', $_POST['wcfac-card-expiry']);

                if (!$this->validateExpirationDate($expiryDate)) {
                    wc_add_notice($this->get_validation_error(__('Card Expiration Date', 'wcwcfac'), 'invalid'), 'error');
                    $validated = false;
                }

                if (!$this->is_cvv_valid($_POST['wcfac-card-cvc'])) {
                    wc_add_notice($this->get_validation_error(__('Card Code', 'wcwcfac'), 'invalid'), 'error');
                    $validated = false;
                }
            }

            return $validated;
        }


    /**
     * validateExpirationDate - Validates an expiration date string in the format of "mm/yy".
     * Returns true if the expiration date is valid and not expired, and false otherwise.
     *
     * @param string $expirationDate - The expiration date string to validate
     * @return bool - True if the expiration date is valid and not expired, and false otherwise
     */
    function validateExpirationDate($expirationDate) {
        // Trim any empty spaces from the beginning or end of the string
        $expirationDate = trim($expirationDate);

        // Create a DateTime object from the expiration date string using the 'm/y' format
        $expDate = DateTime::createFromFormat('m/y', $expirationDate);

        // If the date is invalid (e.g. not in the 'm/y' format), or is expired, or is more than 20 years in the future, return false
        $currentDate = new DateTime();
        if (!$expDate || $expDate->format('m/y') !== $expirationDate || $expDate < $currentDate->modify('first day of this month') || $expDate > $currentDate->modify('+20 years')) {
            return false;
        }

        // The date is valid and not expired
        return true;
    }
    

    protected function is_cvv_valid($cvvNumber)
    {
        // Remove any whitespace and non-numeric characters from the CVV number
        $cvvNumber = preg_replace('/\D/', '', $cvvNumber);

        // Check that the CVV number is numeric and has 3 or 4 digits
        if (!is_numeric($cvvNumber) || (strlen($cvvNumber) != 3 && strlen($cvvNumber) != 4)) {
            return false;
        }

        // The CVV number is valid
        return true;
    }



    /**
     * validateCreditCardNumber - Validates a credit card number.
     * Returns true if the credit card number is valid, and false otherwise.
     *
     * @param string $cardNumber - The credit card number to validate
     * @return bool - True if the credit card number is valid, and false otherwise
     */
    protected function is_credit_card_valid($cardNumber)
    {
        // Remove any spaces or dashes from the card number
        $cardNumber = str_replace(array(' ', '-'), '', $cardNumber);
        
        // Check if the card number matches the pattern of one of the valid card types
        if (preg_match('/^3[47][0-9]{13}$/', $cardNumber)) { // American Express
            return true;
        } elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/', $cardNumber)) { // Diners Club
            return true;
        } elseif (preg_match('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $cardNumber)) { // Discover/Novus
            return true;
        } elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/', $cardNumber)) { // JCB
            return true;
        } elseif (preg_match('/^(62|88)\d{14,17}$/', $cardNumber)) { // UnionPay
            return true;
        } elseif (preg_match('/^5[1-5][0-9]{14}$/', $cardNumber)) { // MasterCard
            return true;
        } elseif (preg_match('/^(?:5[0678]\d\d|6304|6390|67\d\d)\d{8,15}$/', $cardNumber)) { // Maestro
            return true;
        } elseif (preg_match('/^77777[0-9]{11}$/', $cardNumber)) { // NCB Keycard
            return true;
        } elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $cardNumber)) { // Visa
            return true;
        } else {
            return false;
        }
    }
    protected function get_validation_error($field, $type = 'undefined', $l = null)
    {
        if ($type === 'invalid') {
            return sprintf(__('Your <strong>%s</strong> is incorrect. Please ensure that you have entered the correct values and try again.', 'wcwcfac'), $field);
        }

        if ($l == 'card-expired') {
            return sprintf(__('Your Credit Card is expired.', 'wcwcfac'), "");
        }

        return sprintf('<strong>%s</strong>' . __(' is a required field.', 'wcwcfac'), "<strong>$field</strong>");
    }

    public function payment_fields()
    {
        // Default credit card form

        echo '<p style="margin:0 0 5px;">' . $this->description . '</p>';
        if (count($this->fac_card_types)) {
            echo '<div class="fac_cards">';
            foreach ($this->fac_card_types as $card) {
                $card_img = FAC_PLUGIN_URL . 'assets/img/' . $card . '.png';
                echo '<span><img height="24" width="46" src="' . $card_img . '"/></span>';
            }
            echo '</div>';
        }
        ?>
        <style>
            .fac_cards {
                list-style: outside none none !important;
                margin: 0 0 10px !important;
                padding: 0 !important;
                width: 100% !important;
                min-height: 50px;
            }

            .fac_cards span {
                display: inline;
                padding: 0 !important;
                margin: 0 !important;
                border: 0 !important;
                float: left !important;
            }

            .fac_cards span img {
                margin: 0 !important;
            }

            .help_cvv {
                text-align: center;
                display: inline-block;
                width: 16px;
                height: 16px;
                background-size: 100% 100%;
                margin-left: 10px;
                background-image: url("<?php echo FAC_PLUGIN_URL; ?>assets/img/help.png");
            }

            .selectcard {
                list-style: outside none none !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .selectcard .newcard {
                border: medium none !important;
                margin: 0 0 10px !important;
                padding: 0 !important;
                width: 100%;
                display: block;
            }

            .selectcard .cardno {
                float: none;
                margin-top: 9px !important;
            }

            .selectcard .saveccv {
                display: inline;
                margin-left: 10px;
            }

            .selectcard .saveccv input {
                width: 70px !important;
                display: inline-block;
            }

            .saveccv input {
                border-color: #bbb3b9 #c7c1c6 #c7c1c6;
                width: 50px;
                margin-right: 0 !important
            }

            .selectcard .newcard>label {
                float: none;
                min-width: 130px;
                line-height: 29px;
                display: inline-block;
            }

            .selectcard .newcard>i {
                background-image: url("<?php echo FAC_PLUGIN_URL; ?>img/removeme.png");
                cursor: pointer;
                display: inline-block;
                float: none;
                height: 16px;
                margin-left: 10px;
                margin-top: 8px;
                width: 16px;
            }

            .power_logo {
                margin-top: 4px;
            }

            .power_logo img {
                max-width: 250px;
            }
        </style>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $(".selectcard .cardno").click(function() {
                    if ($(this).val() == 'new') {
                        $(".customtab").show();
                        $("#fac_tokenization").prop('disabled', false);
                        $(".saveccv input").prop('disabled', true);
                    } else {
                        $(".customtab").hide();
                        $("#fac_tokenization").prop('disabled', true);
                        $(".saveccv input").prop('disabled', true);
                        $("#ccv_" + $(this).val()).prop('disabled', false);
                    }
                });

                jQuery('body').on('keyup', '#wcfac-card-expiry', function() {
                    jQuery(this).attr('maxlength', 7);
                })
            });
            fireajax = true;
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

            function removeTokenizedCard(obj, cardReferenceNo) {
                if (fireajax) {
                    var x = confirm('<?= __('Are you sure you want to remove this card?', 'wcwcfac') ?>');
                    if (!x)
                        return false

                    fireajax = false;
                    jQuery.post(
                        ajaxurl, {
                            'action': 'fac_remove_card_number',
                            'card_reference_no': cardReferenceNo
                        },
                        function(response) {
                            fireajax = true;
                            try {
                                response = JSON.parse(response);
                            } catch (e) {}
                            if (response && response.success) {

                                jQuery(obj).parent().remove();
                            } else {
                                window.location.reload()
                            }
                        }
                        ).fail(function(error) {
                        //TODO
                            window.location.reload()
                        });
                    }
                }
            </script>
            <?php
            add_filter('woocommerce_credit_card_form_fields', array($this, 'append_fac_credit_form_fields'));

            $userId = get_current_user_id();
            $savedCards = $this->tokenizedCardRepo->getUserCards($userId);

            if (class_exists('WC_Subscriptions_Order' && class_exists('WC_Subscriptions_Product'))) {
                global $woocommerce;
                foreach ($woocommerce->cart->get_cart() as $product) {
                    if (WC_Subscriptions_Product::is_subscription($product['product_id'])) {
                        $savedCards = false;
                    }
                }
            }

            if (is_array($savedCards) && count($savedCards) && $this->fac_tokenization == 'yes') {
                echo '<div class="selectcard">';

                $check = 'checked="checked"';
                $disabled = '';
                $i = 0;

                foreach ($savedCards as $index => $tokenizedCard) {
                    ?>
                    <div class="newcard">
                        <input id="isitnewcard_<?= ($index + 1) ?>" class="cardno" name="isitnewcard" <?= $index == 0 ? 'checked="checked"' : '' ?> type="radio" value="<?= $tokenizedCard->id  ?>" />
                        <label for="isitnewcard_<?= ($index + 1) ?>">
                            <?= sprintf(
                                "%s ending in %s (expires %s/%s)",
                                $tokenizedCard->cardType,
                                $tokenizedCard->last4,
                                $tokenizedCard->expiryMonth,
                                $tokenizedCard->expiryYear
                            ) ?>
                        </label>
                        <strong onclick="removeTokenizedCard(this,'<?= $tokenizedCard->id  ?>')">🗑</strong>
                    </div>
                    
                    <?php
                }

                echo '<div class="newcard new"><input class="cardno" name="isitnewcard" id="isitnewcard_new" type="radio" value="new"/><label for="isitnewcard_new">' . __('Use a new credit card.', 'wcwcfac') . '</label></div>';
                echo '</div>';
                echo '<div class="customtab" style="display:none">';
                if ($this->enable_hpp != 'yes') {
                    $this->form();
                }
                if ($this->fac_tokenization == 'yes' && is_user_logged_in()) {
                    ?>
                    <?php
                    $fast_payment = __('Save this card for faster checkout','wcwcfac');

                    ?>
                    <div class="savemycard" style="padding: 15px 15px 0px;line-height:10px">
                        <input type="checkbox" disabled="" name="fac_tokenization" id="fac_tokenization" value="1" style="margin-right: 5px;"> <?php echo $fast_payment; ?>
                    </div>
                    <?php
                }
                echo '</div>';
            } else {
                if ($this->enable_hpp != 'yes') {
                    $this->form();
                }

                echo '<input name="isitnewcard" type="radio" value="new" checked="checked" style="display:none"/>';
                if ($this->fac_tokenization == 'yes' && is_user_logged_in()) {
                    ?>
                    <div class="savemycard" style="padding: 15px 15px 0px;line-height:10px">
                        <input type="checkbox" name="fac_tokenization" id="fac_tokenization" value="1" style="margin-right: 5px;"> <?= __('Save this card for faster checkout', 'wcwcfac') ?>
                    </div>
                <?php } ?>
                <?php
            }

            remove_filter('woocommerce_credit_card_form_fields', array($this, 'append_fac_credit_form_fields'));
            if ($this->kount_enabled == 'yes') {
                $sess = $this->seesion_id;
                $merchantId = $this->kount_merchantid;
                if ($this->kount_test_mode == 'yes') {
                    $ifurl = FAC_PLUGIN_URL . "lib/logo.htm?m=" . $merchantId . "&s=" . $sess;
                    $imgurl = FAC_PLUGIN_URL . "lib/logo.gif?m=" . $merchantId . "&s=" . $sess;
                } else {
                    $ifurl = FAC_PLUGIN_URL . "include/logo.htm?m=" . $merchantId . "&s=" . $sess;
                    $imgurl = FAC_PLUGIN_URL . "include/logo.gif?m=" . $merchantId . "&s=" . $sess;
                }
                ?>
                <iframe width="1" height="1" frameborder="0" scrolling="no" src="<?php echo $ifurl; ?>">
                    <img width="1" height="1" src="<?php echo $imgurl; ?>">
                </iframe>
            <?php } ?>
            <script type="text/javascript">
            /*jQuery(document).ready(function() {
             jQuery('.help_cvv').tipr();
             });*/
            </script>
            <?php
            if ($this->enable_fac_logo == 'yes') {
                $fac_img = FAC_PLUGIN_URL . 'assets/img/first-atlantic-commerce-logo.png';
                echo '<div class="power_logo"><img src="' . $fac_img . '"></div>';
            }
            ?>
            <?php
        }

        function append_fac_credit_form_fields($fields)
        {
            $sting = '<div><h5>' . __('Where can I find my CVC number?', 'wcwcfac') . '</h5><img src="' . FAC_PLUGIN_URL . 'img/cvvVisa.jpg" alt="CVV"></div>';

            $fields['card-cvc-field'] = str_replace("Card Code", "<span>CVC</span>", $fields['card-cvc-field']);
            $helpicon = '<span data-tip="' . htmlentities($sting) . '" id="help_cvv" class="help_cvv"></span>';
            $fields['card-cvc-field'] = str_replace('<span class="required">*</span>', '<span class="required">*</span>' . $helpicon, $fields['card-cvc-field']);
            return $fields;
        }


        private function getCardType($cardNumber = '')
        {

            $cardTypes = array(
                "visa"       => "/^4[0-9]{12}(?:[0-9]{3})?$/",
                "mastercard" => "/^5[1-5][0-9]{14}$/",
                "amex"       => "/^3[47]/",
                "discover"   => "/^6(?:011|5[0-9]{2})[0-9]{12}$/",
                "keycard"    => "/^7[0-9]{12}(?:[0-9]{3})?$/",
                "jcb"        => "/^(?:2131|1800|35\d{3})\d{11}$/",
                "diners"     => "/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/"

            );

            $cardType = 'unknown';

            if ($cardNumber) {
                foreach ($cardTypes as $key => $val) {
                    if (preg_match($val, $cardNumber)) {
                        $cardType = $key;
                        break;
                    }
                }
            }

            return $cardType;
        }

        private function appendQueryStringToURL(string $url, $query)
        {
        // the query is empty, return the original url straightaway
            if (empty($query)) {
                return $url;
            }

            $parsedUrl = parse_url($url);
            if (empty($parsedUrl['path'])) {
                $url .= '/';
            }

        // if the query is array convert it to string
            $queryString = is_array($query) ? http_build_query($query) : $query;

        // check if there is already any query string in the URL
            if (empty($parsedUrl['query'])) {
            // remove duplications
                parse_str($queryString, $queryStringArray);
                $url .= '?' . http_build_query($queryStringArray);
            } else {
                $queryString = $parsedUrl['query'] . '&' . $queryString;

            // remove duplications
                parse_str($queryString, $queryStringArray);

            // place the updated query in the original query position
                $url = substr_replace($url, http_build_query($queryStringArray), strpos($url, $parsedUrl['query']), strlen($parsedUrl['query']));
            }

            return $url;
        }

    /**
     * START: PROCESS SUBSCRIPTION PAYMENTS
     */

    /**
     * Update the customer_id for a subscription after using Simplify to complete a payment to make up for
     * an automatic renewal payment which previously failed.
     *
     * @param WC_Order $original_order The original order in which the subscription was purchased.
     * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
     * @param string $subscription_key A subscription key of the form created by @see WC_Subscriptions_Manager::get_subscription_key()
     */
    function process_subscription_payment_method_changed($original_order, $renewal_order)
    {

        $userId = $renewal_order->get_user_id();
        $paymentMethodId = get_post_meta($renewal_order->id, static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY, true);

        $subscriptions = wcs_get_subscriptions_for_renewal_order($renewal_order);
        $subscription = array_pop($subscriptions);
        $parentOrderId = $subscription->get_parent_id();



        $paymentMethod = $this->tokenizedCardRepo->getUserCard($userId, $paymentMethodId);



        $this->setSubscriptionPaymentMethod($original_order->id, $paymentMethod);
        $this->setSubscriptionPaymentMethod($parentOrderId, $paymentMethod);
    }

    /**
     * Update the customer_id for a subscription after using Simplify to complete a payment to make up for
     * an automatic renewal payment which previously failed.
     *
     * @param float $amount The amount of the recurring charge
     * @param WC_Order $renew_order The order generated for the renewal of the subscription
     */
    public function process_scheduled_subscription_payment_fac($amount, $renew_order)
    {
        //return;

        $shouldTokenize = true;
        $enable3DSecure = false;

        $orderId = $renew_order->get_id();

        $subscriptions = wcs_get_subscriptions_for_renewal_order($renew_order);
        $subscription = array_pop($subscriptions);
        //$parentOrderId = $subscription->get_parent_id();

        $tokenizedCard = $this->getSubscriptionPaymentMethod($subscription->id);
        $status = $renew_order->get_status();
        $cardDetails = [
            'Token' => $tokenizedCard->token
        ];



        if (0 == $amount) {
            // Payment complete
            $renew_order->payment_complete();
            return true;
        }




        if ($renew_order && in_array($status, ["pending", "on-hold"])) {
            $paymentResponse = null;


            $billingAddress = $renew_order->get_address('billing');
            $shippingAddress = $renew_order->get_address('shipping');

            $transactionDetails = [
                'OrderIdentifier' => $this->getOrderIdentifier($orderId),
                'TotalAmount' => $amount,
                'CurrencyCode' => useOrElse((method_exists($renew_order, 'get_currency') ? strtoupper($renew_order->get_currency()) : null), 'USD'),
                'Source' => $cardDetails,
                'BillingAddress' => [
                    'FirstName' => $billingAddress['first_name'],
                    'LastName' => $billingAddress['last_name'],
                    'Line1' => $billingAddress['address_1'],
                    'Line2' => $billingAddress['address_2'],
                    'City' => $billingAddress['city'],
                    //'State' => $_POST['billing_state'],
                    'PostalCode' => $billingAddress['postcode'],
                    'CountryCode' => $billingAddress['country'],
                    'EmailAddress' =>  $billingAddress['email'],
                    'PhoneNumber' => $billingAddress['phone'],
                ],
                'ShippingAddress' => [
                    'FirstName' => useOrElse($shippingAddress['shipping_first_name'], $billingAddress['billing_first_name']),
                    'LastName' => useOrElse($shippingAddress['shipping_last_name'], $billingAddress['billing_last_name']),
                    'Line1' => $shippingAddress['shipping_address_1'],
                    'Line2' => $shippingAddress['shipping_address_2'],
                    'City' => $shippingAddress['shipping_city'],
                    //'State' =>  $_POST['shipping_state'],
                    'PostalCode' => $shippingAddress['shipping_postcode'],
                    'CountryCode' => $shippingAddress['shipping_country'],
                    'EmailAddress' =>  $shippingAddress['billing_email'],
                    'PhoneNumber' => $shippingAddress['billing_phone'],
                ],
                'AddressMatch' => false,
                'ThreeDSecure' => $enable3DSecure,
                'ExtendedData' => !$enable3DSecure ? null : [
                    'MerchantResponseUrl' => home_url("fac-confirm-transaction/?") . http_build_query([
                        'tokenize' => $shouldTokenize,
                        '3DS_VERSION' => 2
                    ]),
                ],
            ];


            if ($this->fac_payment_type == 'authorize_capture') {
                $paymentResponse = $this->getPaymentService()
                ->authorizeAndCapture(
                    new \FacPayments\Entities\Requests\SaleRequest($transactionDetails)
                );
            } else {
                $paymentResponse = $this->getPaymentService()
                ->authorize(
                    new \FacPayments\Entities\Requests\AuthRequest($transactionDetails)
                );
            }

            $paymentResponseData = $paymentResponse->getData();

            if (
                $paymentResponse &&
                $paymentResponse->isSuccessful() &&
                $paymentResponseData["Approved"] == true &&
                !$paymentResponse->requiresRedirect()
            ) {


                $postMetaUpdates = [
                    static::FAC_TRANSACTION_ID_META_KEY => $paymentResponse->getTransactionIdentifier(),
                    static::FAC_ORDER_ID_META_KEY => $paymentResponse->getOrderIdentifier()
                ];

                foreach ($postMetaUpdates as $metaKey => $metaValue) {
                    if (!add_post_meta($orderId, $metaKey, $metaValue, true)) {
                        update_post_meta($orderId, $metaKey, $metaValue);
                    }
                }

                $paymentMethodId = get_post_meta($orderId, static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY, true);

                $tokenizedCard = $this->tokenizedCardRepo->getUserCard(
                    $renew_order->get_user_id(),
                    $paymentMethodId
                );

                //$this->confirmOrderStatus($orderId,$paymentResponse->toArray());
                $renew_order->add_order_note(sprintf(__('Recurring Payment processed Successfully by %s Card ending %s (Expiry Date: %s/%s). FAC Transaction ID: %s. FAC Order ID: %s', 'wcwcfac'), $tokenizedCard->cardType, $tokenizedCard->last4, $tokenizedCard->expiryMonth, $tokenizedCard->expiryYear, $paymentResponse->getTransactionIdentifier(), $paymentResponse->getOrderIdentifier()));

                $renew_order->payment_complete();
                WC_Subscriptions_Manager::process_subscription_payments_on_order($renew_order);

                WC_Subscriptions_Manager::activate_subscriptions_for_order($renew_order);

                $subscriptionArgs =  array(
                    'order_type' => array('renewal')
                );
                $subscriptions = wcs_get_subscriptions_for_order($renew_order, $subscriptionArgs);

                foreach ($subscriptions as $subscription_id => $subscription) {
                    $subscription->update_status('active');
                }
            } else {

                $general_error = __('An error occurred processing the recurring payment.', 'wcwcfac');
                $specific_error = sprintf(__('Error Code: %s. Message: %s. Transaction ID: %s', 'wcwcfac'), $paymentResponse->getErrorCode(), $paymentResponse->getMessage(), $paymentResponse->getTransactionIdentifier());

                $renew_order->add_order_note($general_error . ' ' . $specific_error);
                WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($renew_order);
            }
        }
        return;
    }




    /**
     * Update the customer_id for a subscription after using Simplify to complete a payment to make up for.
     * an automatic renewal payment which previously failed.
     *
     * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
     * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
     */
    public function update_failing_payment_method($subscription, $renewal_order)
    {
        update_post_meta($subscription->get_id(), static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY, get_post_meta($renewal_order->id, static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY, true));
    }

    /**
     * Include the payment meta data required to process automatic recurring payments so that store managers can.
     * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions v2.0+.
     *
     * @param array $payment_meta associative array of meta data required for automatic payments
     * @param WC_Subscription $subscription An instance of a subscription object
     * @return array
     */
    public function add_subscription_payment_meta($payment_meta, $subscription)
    {


        $payment_meta[$this->id] = array(
            'post_meta' => array(
                static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY => array(
                    'value' => get_post_meta($subscription->get_id(), static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY, true),
                    'label' => 'FAC Payment Method ID',
                ),
            ),/*
            'user_meta' => array(
				static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY => array(
					'value' => get_post_meta( $subscription->get_user_id(), TokenizedCardRepository::USER_META_TOKENIZED_CARDS_KEY, true ),
					'label' => 'FAC Payment Method ID',
				),
			),*/
        );

        return $payment_meta;
    }

    /**
     * Validate the payment meta data required to process automatic recurring payments so that store managers can.
     * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions 2.0+.
     *
     * @param  string $payment_method_id The ID of the payment method to validate.
     * @param  array $payment_meta associative array of meta data required for automatic payments.
     * @return array
     * @throws Exception
     */
    public function validate_subscription_payment_meta($payment_method_id, $payment_meta)
    {
        if ($this->id === $payment_method_id) {
            if (!isset($payment_meta['post_meta'][static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY]['value']) || empty($payment_meta['post_meta'][static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY]['value'])) {
                throw new Exception('A "_fac_customer_payment_method_id" value is required.');
            }
        }
    }

    /**
     * Don't transfer customer meta to resubscribe orders.
     *
     * @param WC_Order $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription.
     */
    public function delete_resubscribe_meta($resubscribe_order)
    {
        delete_post_meta($resubscribe_order->id, static::FAC_CUSTOMER_PAYMENT_METHOD_ID_META_KEY);
    }


    //apply_filters( 'woocommerce_subscription_note_new_payment_method_title', $new_payment_method_title, $new_payment_method, $subscription );


    function fac_subscription_new_payment_method_title($new_payment_method_title, $new_payment_method, $subscription)
    {
        return $new_payment_method_title;
        // return $this->fac_subscription_payment_method_to_display($new_payment_method_title, $subscription);

    }



    function fac_subscription_payment_method_to_display($paymentMethodTitle, $subscription, $context = 'customer')
    {
        $title = get_post_meta($subscription->id, static::FAC_PAYMENT_METHOD_TITLE_META_KEY, true);

        if (isset($title) && !empty($title)) {
            $paymentMethodTitle = $title;
        }

        return $paymentMethodTitle;
    }
}
