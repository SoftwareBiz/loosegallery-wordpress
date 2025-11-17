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

    // WooCommerce Blocks Checkout - Replace product images with design previews
    if (typeof lgDesignPreviews !== 'undefined' && lgDesignPreviews.length > 0) {
        var replaceCheckoutImages = function() {
            // Try multiple selectors to find product images in different checkout layouts
            var selectors = [
                '.wc-block-components-order-summary-item img',
                '.wc-block-cart-item__image img',
                '.wp-block-woocommerce-checkout-order-summary-block img',
                '.wc-block-components-product-image img',
                '[class*="order-summary"] img[alt*="Test Print"]',
                '.product-thumbnail img'
            ];
            
            var imageIndex = 0;
            
            selectors.forEach(function(selector) {
                $(selector).each(function() {
                    var $img = $(this);
                    
                    // Skip if already replaced
                    if ($img.data('lg-replaced')) {
                        return;
                    }
                    
                    // Get the preview URL for this index
                    if (lgDesignPreviews[imageIndex]) {
                        $img.attr('src', lgDesignPreviews[imageIndex]);
                        $img.attr('srcset', lgDesignPreviews[imageIndex]);
                        $img.data('lg-replaced', true);
                        console.log('Replaced checkout image ' + imageIndex + ' with design preview');
                    }
                    
                    imageIndex++;
                });
            });
        };
        
        // Run immediately
        replaceCheckoutImages();
        
        // Run after short delay for dynamic content
        setTimeout(replaceCheckoutImages, 500);
        setTimeout(replaceCheckoutImages, 1000);
        setTimeout(replaceCheckoutImages, 2000);
        
        // Watch for DOM changes and replace images as they're added
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    replaceCheckoutImages();
                }
            });
        });
        
        // Observe the entire checkout area
        var checkoutArea = document.querySelector('.woocommerce-checkout') || 
                          document.querySelector('[class*="checkout"]') || 
                          document.body;
        
        if (checkoutArea) {
            observer.observe(checkoutArea, {
                childList: true,
                subtree: true
            });
        }
        
        // Also listen for WooCommerce events
        $(document.body).on('updated_checkout updated_cart_totals', function() {
            setTimeout(replaceCheckoutImages, 100);
        });
    }

})(jQuery);
