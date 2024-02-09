<?php



/*
Plugin Name: First Atlantic Commerce Payment Gateway for WooCommerce by GDC
Plugin URI: https://geniusdigitalcommerce.com/fac
Description: Adds First Atlantic Commerce Payment Gateway to accept credit card payments on your Woocommerce store.
Version: 3.4
Author: Genius Digital Commerce
Author URI: https://geniusdigitalcommerce.com/
WC requires at least: 4.5
WC tested up to: 7.0.0


*/


$gdc_plugin_slug = 'fac_woocommerce_gdc';
$gdc_plugin_name = __("GDC First Atlantic Commerce (FAC) Payment Gateway for WooCommerce - 3DS 2", 'wcfac');
$gdc_licence = NULL;
$gdc_activation_page_slug = 'fac_woocommerce_gdc_3ds2_activation';
define('FAC_PLUGIN_URL', plugins_url('/' . $gdc_plugin_slug . '/'));
define(strtoupper($gdc_plugin_slug), 1);

require __DIR__ . '/assets/lib/vendor/autoload.php';

/* GDC ACTIVATION  START */



/**
 * Initialize the plugin tracker
 *
 * @return void
 */






function appsero_init_tracker_fac_woocommerce_gdc()
{

 global $gdc_licence, $gdc_activation_page_slug, $gdc_plugin_name;


 $client = new Appsero\Client('cb2f4180-ee54-40ea-9294-e6c87309cb1d', $gdc_plugin_name, __FILE__);

    // Active insights
 $client->insights()->init();

    // Active automatic updater
 $client->updater();

    // Active license page and checker
 $args = array(
  'type'       => 'options',
  'menu_title' => __('FAC Payment Gateway 3DS 2 Activation ', 'wcwcfac'),
  'page_title' => $gdc_plugin_name . ' ' . __('Settings', 'wcwcfac'),
  'menu_slug'  => $gdc_activation_page_slug,
);




 $gdc_licence = $client->license();
 $gdc_licence->add_settings_page($args);
}

appsero_init_tracker_fac_woocommerce_gdc();

add_action('check_plugin_activation', 'gdc_check_activation');



// Setup cron on plugin activation
register_activation_hook(__FILE__, 'gdc_activation');

function gdc_activation()
{

 if (!wp_next_scheduled("check_plugin_activation")) {
  wp_schedule_event(time(), 'every-1-day', 'check_plugin_activation');
}
}

function gdc_check_activation()
{

 global $gdc_licence;

 if (!$gdc_licence->is_valid()) {

  add_filter('woocommerce_payment_gateways', 'remove_gdc_fac_gateway', 20, 1);
}
}

function remove_gdc_fac_gateway($load_gateways)
{

 $remove_gateways = array(
  'WC_FAC_Payment_Gateway_GDC'
);

 foreach ($load_gateways as $key => $value) {

  if (in_array($value, $remove_gateways)) {
   unset($load_gateways[$key]);
}
}

return $load_gateways;
}

// Remove all event on deactivation
register_deactivation_hook(__FILE__, 'fac_deactivation');
function fac_deactivation()
{
 wp_unschedule_event(time(), 'check_plugin_activation');
 wp_clear_scheduled_hook('check_plugin_activation');
}

/* GDC ACTIVATION  END */



/* Load functions. */
add_action('plugins_loaded', 'WC_FAC_Payment_Gateway_GDC_load', 0);

function WC_FAC_Payment_Gateway_GDC_load()
{

 global $gdc_licence;

    //$gdc_licence->check_license_status();

 if (!$gdc_licence->is_valid()) {
  add_action('admin_notices', 'gdc_fac_activation_notice');
  add_filter('woocommerce_payment_gateways', 'remove_gdc_fac_gateway', 20, 1);
  return;
} else {
  if (!class_exists('WC_Payment_Gateway')) {
   add_action('admin_notices', 'woocommerce_fac_fallback_notice');
   add_filter('woocommerce_payment_gateways', 'remove_gdc_fac_gateway', 20, 1);
   return;
}



require_once plugin_dir_path(__FILE__) . '/assets/classes/class-wc-fac-gateway_gdc.php';

if (is_admin()) {
   add_action('admin_notices', 'woocommerce_fac_test_fallback_notice');
}
add_filter('woocommerce_payment_gateways', 'wc_FAC_add_gateway');

function wc_FAC_add_gateway($methods)
{
   $methods[] = 'WC_FAC_Payment_Gateway_GDC';
   return $methods;
}

if(WC_FAC_Payment_Gateway_GDC::is_tokenization_enabled())
{
   $menu_success = add_action( 'admin_menu', 'tokenized_cards_list_menu' );

}

        // Include the WooCommerce Custom Payment Gateways classes.
}
}

/* Adds custom settings url in plugins page. */
function wcfac_action_links($links)
{
 $settings = array(
  'settings' => sprintf(
   '<a href="%s">%s</a>',
   admin_url('admin.php?page=wc-settings&tab=checkout&section=wcfac'),
   __('FAC Settings', 'wcfac')
)
);

 return array_merge($settings, $links);
}


add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wcfac_action_links');


function tokenized_cards_list_menu() {
    //add_submenu_page( string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = '', int|float $position = null ): string|false
 add_submenu_page('woocommerce',__('FAC Payment Gateway Users\' Tokenized Cards', 'wcwcfac'), __('FAC Tokenized Payment Methods', 'wcwcfac'), 'manage_options',   WC_FAC_Payment_Gateway_GDC::FAC_TOKENIZED_CARDS_PAGE_SLUG, array('WC_FAC_Payment_Gateway_GDC','tokenized_cards_list_page'), 'dashicons-awards');
}



function agregar_script_selectWoo_admin_page() {
  // Verificar si estamos en la página de administración de WooCommerce
  if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'checkout') {
    // Asegurémonos de que jQuery y SelectWoo estén encolados
    wp_enqueue_script('jquery');


    // Agregamos nuestro script personalizado
    ?>
    <script>
      jQuery(document).ready(function($) {
        $(".select2").selectWoo();

     });
  </script>
  <?php
}
}

add_action('admin_footer', 'agregar_script_selectWoo_admin_page');



function load_custom_textdomain_fac() {

   $plugin_dir_path = plugin_dir_path(__FILE__);
   $language_dir_path = $plugin_dir_path . '/assets/languages/wcfacgdc-es.mo';

   $result = load_textdomain('wcwcfac', $language_dir_path);

}

add_action('init', 'load_custom_textdomain_fac');




include(__DIR__ . '/assets/helpers/notices.php');
include(__DIR__ . '/assets/helpers/admin.php');
include(__DIR__ . '/assets/helpers/cron.php');
include(__DIR__ . '/assets/helpers/public.php');
