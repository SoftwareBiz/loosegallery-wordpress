/**
 * LooseGallery WooCommerce - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize color pickers
        if ($.fn.wpColorPicker) {
            $('.lg-color-picker').wpColorPicker();
        }

        // Product meta box: Toggle customization options
        $('#lg_is_customizable').on('change', function() {
            if ($(this).is(':checked')) {
                $('#lg_customization_options').slideDown();
            } else {
                $('#lg_customization_options').slideUp();
            }
        });

        // Trigger change on page load to set initial state
        $('#lg_is_customizable').trigger('change');

    });

})(jQuery);
