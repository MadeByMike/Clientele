(function ($) {
// Create a new plugin class
    tinymce.create('tinymce.plugins.clientele', {
        createControl: function (n, cm) {
            switch (n) {
                case 'clienteleclientfields':
                    var mlb = cm.createListBox('clienteleListbox', {
                        title: 'Client Fields',
                        onselect: function (v) {
                            if(v){ // MCE bug
								var sel = cm.editor.selection;
                                cm.editor.focus();
                                //console.log(sel);
                                sel.setContent(v);
                            }
                        }
                    });

                    var data = {
                        action: 'mce_client_fields'
                    };
                    $.post(ajaxurl, data, function (response) {
                        response = $.parseJSON(response);
                        $.each(response, function (i, value) {
                            mlb.add(response[i]['label'], response[i]['value']);
                        });


                    });
                return mlb;
            }
        }

    });

    // Register plugin with a short name
    tinymce.PluginManager.add('clienteleclientfields', tinymce.plugins.clientele);

})(jQuery);

