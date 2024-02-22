<?php

use FacPayments\Services\V2\PaymentService;


if (!function_exists('useOrElse')) {
    function useOrElse($value, $orElseValue)
    {
        return $value && !empty($value)  ? $value : $orElseValue;
    }
}

if (!function_exists('useOrElseArray')) {
    function useOrElseArray($array, $index, $orElseValue)
    {
        if(isset($array[$index]))
        {
            return $array[$index];
        }
        else
        {
            return $orElseValue;
        }
    }
}


function fac_enqueue_script() {
    global $gdc_licence;
    if(!$gdc_licence->is_valid())return;
    wp_enqueue_script('tipr-js', FAC_PLUGIN_URL.'assets/js/tipr.min.js', ['jquery'] );
    wp_enqueue_style('tipr-css', FAC_PLUGIN_URL.'assets/css/tipr.css' ); 
    wp_enqueue_script('fac-custome', FAC_PLUGIN_URL.'assets/js/fac-customer.js', ['jquery'] );
}

add_action('wp_enqueue_scripts', 'fac_enqueue_script' );


function fac_custom_url_handlers(){
    global $gdc_licence;
    if(!$gdc_licence || !$gdc_licence->is_valid())return;
    global $wp; 
    
    $gateway = new WC_FAC_Payment_Gateway_GDC();


    if( stripos(home_url( $wp->request ),'/fac-3ds-redirect' )!==false ){
        $orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0; 
        $gateway->initiate3DSRedirect($orderId);
    }
    
    if(stripos(home_url( $wp->request ),'/fac-confirm-transaction' )!==false ){

        $version = useOrElse(isset($_GET['3DS_VERSION']) ? intval($_GET['3DS_VERSION']) : 2,2);
        $postData = $_POST;

        if(isset($postData['Response'])){
            $postData['Response'] = wp_unslash($postData['Response']);
        }

        $paymentResponse = $gateway->confirmTransaction(
            $postData,
            $version,
            isset($_GET['tokenize']) && $_GET['tokenize']==1
        );

        wp_redirect(
            $paymentResponse && $paymentResponse->requiresRedirect() ?
            $paymentResponse->getRedirectUri() :
            home_url()
        );


    }  
}

add_action('template_redirect','fac_custom_url_handlers', 10);

add_action('wp_ajax_fac_remove_card_number','fac_remove_card_number');

function fac_remove_card_number(){
    global $gdc_licence;
    if(!$gdc_licence->is_valid())return;

    $gateway = new WC_FAC_Payment_Gateway_GDC();
    $cardReferenceNo = @$_POST['card_reference_no'];
    $success = $gateway->removeTokenizedCard(
        get_current_user_id(),
        $cardReferenceNo
    );
    if(!$success){
        wc_add_notice(__('We were unable to remove this card at this time. Please try again','wcfacgdc'), 'error');
    }
    die(json_encode([
        'success'=>$success,
        'cardReferenceNo'=>$cardReferenceNo
    ]));

}


