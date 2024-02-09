<?php 

/**
 * GDC FAC Notices
 */

if(
    defined('FAC_WOOCOMMERCE_GDC') &&
    !defined('FAC_WOOCOMMERCE_GDC_ADMIN')
){
    define('FAC_WOOCOMMERCE_GDC_ADMIN',1);

    add_action('admin_enqueue_scripts', 'fac_admin_scripts');
    function fac_admin_scripts()
    {
        $screen = get_current_screen();
        if($screen->base == 'woocommerce_page_wc-settings')
        {
           wp_enqueue_script('fac_admin',FAC_PLUGIN_URL.'assets/js/fac-admin.js'); 
           wp_enqueue_style('style', FAC_PLUGIN_URL.'assets/css/admin/style.css');
        }
    }
    
    
    add_filter( 'plugin_row_meta', 'FAC_plugin_row_meta', 10, 2 );
    
    function FAC_plugin_row_meta( $links, $file ) {
    
        if ( strpos( $file, 'fac_woocommerce_gdc.php') !== false ) {
            $new_links = array(
                        '<a target="_blank" href="https://geniusdigitalcommerce.com" target="_blank">Visit plugin site</a>'
                    );
            
            $links = array_merge( $links, $new_links );
        }
        
        return $links;
    }
    
    
    function capture_woocommerce_order_item_add_action_buttons($order){    
        $capture=false;
        if(get_post_meta($order->id, '_fac_payment_type',true)=='authorize_capture'){?>
            <button type="button" class="button capture-items" disabled="disabled"><?php _e('Capture transaction', 'woocommerce' ); ?></button>
        <?php }else{?>
        <button type="button" class="button capture-items"><?php _e('Capture transaction', 'woocommerce' ); ?></button>
        <script type="text/javascript">    
        jQuery(document).ready(function(){
            var wpajax=true;
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            jQuery(".capture-items").click(function(){
                if(wpajax){                
                    jQuery.post(
                        ajaxurl, 
                        {
                            'action': 'fac_capture_transaction',
                            'order_id':  <?php echo $order->id;?>
                        },function(response){
                            wpajax=true;
                            if(response == 'Transaction successful')
                            {
                                alert(response)
                                location.reload();
                            }
                            else
                            {
                                 alert(response);
                            }                        
                        }
                    );    
                }else{
                    alert('Please wait...');
                }
            }); 
        });
        
        </script>
    <?php 
        }
    }
    
    add_action( 'woocommerce_order_item_add_action_buttons', 'capture_woocommerce_order_item_add_action_buttons', 10, 1 );

    add_action('wp_ajax_fac_capture_transaction', 'facCaptureTransaction');
    
    function facCaptureTransaction(){
        $gateway = new WC_FAC_Payment_Gateway_GDC();
        $orderId = @$_POST['order_id'];
   
        $paymentResponse = $gateway->captureTransaction($orderId);
        if($paymentResponse->isSuccessful()){
            die('Transaction successful');
        }else{
            die(__($paymentResponse->getMessage(),'wcwcfac'));
        }
    
    }
    
}