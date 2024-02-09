<?php 

/**
 * GDC FAC Notices
 */

if(
    defined('FAC_WOOCOMMERCE_GDC') &&
    !defined('FAC_WOOCOMMERCE_GDC_NOTICES')
){
    define('FAC_WOOCOMMERCE_GDC_NOTICES',1);

    /* WooCommerce fallback notice. */
function woocommerce_fac_fallback_notice() {
    global $gdc_plugin_name;
    echo '<div class="error"><p>' . sprintf( __( '%s depends on the last version of %s to work!', 'wcfac' ), $gdc_plugin_name,'<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>' ) . '</p></div>';
}

/* PHP version notice. */
function woocommerce_fac_version_notice() {
    echo '<div class="error"><p>' . sprintf( __( 'For this plugin to work correctly, please ensure your server is running on PHP 5.6 natively.', 'wcfac' ), '' ) . '</p></div>';
}


function woocommerce_fac_test_fallback_notice() {
    global $gdc_plugin_name;
    $facerror='';
    $fac_payment=new WC_FAC_Payment_Gateway_GDC();        
    if($fac_payment->enabled=='yes' && $fac_payment->fac_test_mode=='yes'){
        $facerror.=sprintf( __('%s is currently in test mode. To disable test mode and start accepting live payments click %s', 'wcfac' ), $gdc_plugin_name, '<a href="'.admin_url( 'admin.php?page=wc-settings&tab=checkout&section=WC_FAC_Payment_Gateway_GDC' ).'">here</a>' );
    }    
    if($facerror!=""){
        echo '<div class="error"><p>'.$facerror.'</p></div>';   
    }
}


function gdc_fac_activation_notice() {
    global $gdc_plugin_name, $gdc_plugin_slug, $gdc_licence, $gdc_activation_page_slug;
    
    $gdc_notice="";
    if( !$gdc_licence->is_valid())
        $gdc_notice.=sprintf( __('%s needs to be activated before you can use it. To activate it, %s.', 'wcfac' ), $gdc_plugin_name, '<a href="'.admin_url( 'admin.php?page='.$gdc_activation_page_slug).'">click here</a>' );
   
    if($gdc_notice!=""){
        echo '<div class="error"><p>'.$gdc_notice.'</p></div>';   
    }
}


} // End notices