/**
 * LooseGallery WooCommerce - Frontend JavaScript
 */

(function($) {
    'use strict';

    // Design button loading state
    $('.lg-design-button').on('click', function() {
        $(this).addClass('loading').text('Loading editor...');
    });

    // Cart item removal warning (backup if inline script doesn't work)
    if (typeof lgWooCommerce !== 'undefined') {
        $('.cart_item').each(function() {
            var $cartItem = $(this);
            var hasDesign = $cartItem.find('.lg-cart-design-badge').length > 0 || 
                           $cartItem.find('.lg-cart-preview-badge').length > 0;
            
            if (hasDesign) {
                var $removeButton = $cartItem.find('.remove');
                
                $removeButton.on('click', function(e) {
                    if (!confirm(lgWooCommerce.deleteWarning)) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });
    }

    // Smooth scroll to design button on page load if returning from editor
    if (window.location.search.indexOf('lg_return=1') > -1) {
        setTimeout(function() {
            if ($('.lg-design-button-wrapper').length) {
                $('html, body').animate({
                    scrollTop: $('.lg-design-button-wrapper').offset().top - 100
                }, 500);
            }
        }, 500);
    }

    // Handle design preview image loading
    $('.lg-design-preview, .lg-cart-preview').on('load', function() {
        $(this).addClass('loaded');
    }).on('error', function() {
        $(this).addClass('error');
        console.error('Failed to load design preview image');
    });

    // Add loading state to edit buttons
    $('.lg-cart-edit-button').on('click', function() {
        $(this).text('Opening editor...').css('opacity', '0.6');
    });

    // WooCommerce Blocks Checkout - Add design preview URLs to order summary items
    if ($('.wp-block-woocommerce-checkout-order-summary-block').length && typeof lgDesignPreviews !== 'undefined') {
        var updateBlocksCheckoutImages = function() {
            setTimeout(function() {
                $('.wc-block-components-order-summary-item').each(function(index) {
                    var $item = $(this);
                    var $img = $item.find('.wc-block-components-order-summary-item__image img');
                    
                    if ($img.length && lgDesignPreviews[index]) {
                        $img.attr('src', lgDesignPreviews[index]);
                    }
                });
            }, 100);
        };
        
        updateBlocksCheckoutImages();
        $(document.body).on('updated_checkout', updateBlocksCheckoutImages);
        $(document.body).on('updated_cart_totals', updateBlocksCheckoutImages);
    }

})(jQuery);
