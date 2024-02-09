/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
jQuery(document).ready(function($){
    var maxlength = 5
    jQuery('body').on('input keyup','#wcFac-card-expiry',function(){
        var val  = jQuery(this).val();
        var newv = val.replace(/\s/g,'');
        var len  = newv.length;
        //console.log(len);
        if(len == 5)
        {
            //jQuery(this).attr('maxlength', '5');
            jQuery('#wcFac-card-expiry').attr('maxlength', '7');

        }
    })
    jQuery('body').on('keypress','#wcFac-card-expiry',function(){
       var val  = jQuery(this).val();
        var newv = val.replace(/\s/g,'');
        var len  = newv.length;
        //console.log(len);
        if(len == 5)
        {
            //jQuery(this).attr('maxlength', '5');
            jQuery('#wcFac-card-expiry').attr('maxlength', '7');

        }
    })
   //jQuery('#wcFac-card-expiry').attr('maxlength', '5');
});


jQuery( document ).ready(function() {
   
    const urlParams = new URLSearchParams(location.search);

    declined = urlParams.get('declined');
    error_code = urlParams.get('error_code');
    error_message = urlParams.get('message');
    if(declined == 'true')
    {
        var declinedMessage='<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><ul class="woocommerce-error" role="alert"><li>Unfortunately we were unable to process your transaction. It may be a problem with your credit card or something technical on our end. Please check your credit card details and try again or contact us if the problem persists.<br/><strong>Reference: '+error_message+'</strong></li></ul></div>';

        var declinedSelector = jQuery( "form.woocommerce-checkout" );

        if(declinedSelector.length==0)
        {
            declinedSelector = jQuery( ".woocommerce-notices-wrapper" );
        }

        declinedSelector.prepend( declinedMessage );
        
    }

});
