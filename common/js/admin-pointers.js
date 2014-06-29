jQuery(document).ready(function ($) {
    wp_open_pointer(0);
    function wp_open_pointer(i) {
        pointer = clientelePointer.pointers[i];
        options = $.extend(pointer.options, {
            close: function () {
                $.post(ajaxurl, {
                    pointer: pointer.pointer_id,
                    action: 'dismiss-wp-pointer'
                });
            }
        });

        $(pointer.target).pointer(options).pointer('open');
    }
});