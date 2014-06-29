/*
 *   These scripts apply to everything in the admin world.
 *
 */

jQuery(document).ready(function () {

    // This handles the disabling of modules on the dashboard
    // The change_clientele_module_state function might ask for validation 
    // before disabling dependant modules or when trying to activate a 
    // dependant module hence the seemingly complicated code.

    jQuery('body').on('click','.dismiss-clientele-welcome',function (e) {
        e.preventDefault();
        jQuery.post(ajaxurl, {
            pointer: 'clientele-welcome',
            action: 'dismiss-wp-pointer'
        }).always(function(){
            window.location = jQuery(e.target).attr('href');
        });
    });
    jQuery('.clientele-enable-disable').change(function () {
        var formOb = jQuery(this);
        if (jQuery(this).is(':checked')) {
            var module_action = 'enable';
        } else {
            var module_action = 'disable';
        }
        var slug = jQuery(this).closest('.clientele-module').attr('id');
        var change_module_nonce = jQuery('#' + slug + ' #change-module-nonce').val();
        var data = {
            action: 'change_module_state',
            module_action: module_action,
            slug: slug,
            change_module_nonce: change_module_nonce,
        }
        jQuery.post(ajaxurl, data, function (response) {
            //console.log(response);
            if (response) {
                response = jQuery.parseJSON(response);
            }

            if (response.warning && !(response.warning == "")) {
                var r = confirm(response.warning);
                if (r == true) {
                    var slug = response.slug;
                    var change_module_nonce = jQuery('#' + slug + ' #change-module-nonce').val();
                    var data = {
                        action: 'change_module_state',
                        module_action: 'disable',
                        confirmation: true,
                        slug: slug,
                        change_module_nonce: change_module_nonce,
                    }
                    jQuery.post(ajaxurl, data, function (response) {
                        location.reload();
                    });

                } else {
                    jQuery(formOb).attr("checked", false);
                }
            } else if (response.alert && !(response.alert == "")) {
                alert(response.alert);
            } else {
                location.reload();
            }

        });
    });

});