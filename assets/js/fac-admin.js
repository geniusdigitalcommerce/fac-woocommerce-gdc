/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
jQuery(document).ready(function ($) {
    jQuery('#woocommerce_wcfac_enable_hpp').click(function () {
        if (jQuery(this).prop("checked") == true) {
            jQuery('#woocommerce_wcfac_kount_test_mode').prop('checked', false);
            jQuery('#woocommerce_wcfac_kount_enabled').closest('td').closest('tr')
                    .hide();
            jQuery('#woocommerce_wcfac_kount_test_mode').closest('td').closest('tr')
                    .hide();
            jQuery('#woocommerce_wcfac_kount_merchantid').closest('td').closest('tr')
                    .hide();
        }
        else
        {
            jQuery('#woocommerce_wcfac_kount_enabled').closest('td').closest('tr')
                    .show();
            jQuery('#woocommerce_wcfac_kount_test_mode').closest('td').closest('tr')
                    .show();
            jQuery('#woocommerce_wcfac_kount_merchantid').closest('td').closest('tr')
                    .show();
        }
    })
    if (jQuery('#woocommerce_wcfac_enable_hpp').is(":checked"))
    {
        jQuery('#woocommerce_wcfac_kount_enabled').closest('td').closest('tr')
            .hide();
        jQuery('#woocommerce_wcfac_kount_test_mode').closest('td').closest('tr')
            .hide();
        jQuery('#woocommerce_wcfac_kount_merchantid').closest('td').closest('tr')
            .hide();
    }
})

