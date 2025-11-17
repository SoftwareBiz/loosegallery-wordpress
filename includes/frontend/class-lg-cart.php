<?php
/**
 * Cart Integration
 * 
 * Handles cart display and functionality:
 * - Show design preview thumbnails in cart
 * - Add "Edit Your Design" button
 * - Show warning when removing customized products
 * - Attach design serial to cart items
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class LG_Cart {

    /**
     * Session handler
     */
    private $session;

    /**
     * Constructor
     */
    public function __construct() {
        $this->session = new LG_Session();

        // Add design data to cart items
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_design_to_cart_item'), 10, 3);
        
        // Display design info in cart
        add_filter('woocommerce_get_item_data', array($this, 'display_design_in_cart'), 10, 2);
        
        // Replace cart item thumbnail with design preview (high priority for Blocks compatibility)
        add_filter('woocommerce_cart_item_thumbnail', array($this, 'replace_cart_thumbnail'), 999, 3);
        
        // Add edit design button in cart
        add_action('woocommerce_after_cart_item_name', array($this, 'add_edit_button_cart'), 10, 2);
        
        // Clear design session when item is removed from cart
        add_action('woocommerce_remove_cart_item', array($this, 'clear_design_on_remove'), 10, 2);
        
        // Add removal warning for customized products
        add_action('wp_footer', array($this, 'add_removal_warning_script'));
        
        // Save design data to order
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_design_to_order'), 10, 4);
        
        // Format order item meta display names
        add_filter('woocommerce_order_item_display_meta_key', array($this, 'format_meta_key_display'), 10, 3);
        
        // Format order item meta display values
        add_filter('woocommerce_order_item_display_meta_value', array($this, 'format_meta_value_display'), 10, 3);
    }

    /**
     * Add design data to cart item
     */
    public function add_design_to_cart_item($cart_item_data, $product_id, $variation_id) {
        // Check if product has a design
        if ($this->session->has_design($product_id)) {
            $design_data = $this->session->get_design($product_id);
            $cart_item_data['lg_design_serial'] = $design_data['serial'];
            $cart_item_data['lg_design_data'] = $design_data['data'];
            
            // Mark this as a unique cart item (prevents merging with non-customized items)
            $cart_item_data['unique_key'] = md5(microtime() . rand());
        }
        
        return $cart_item_data;
    }

    /**
     * Display design info in cart
     * Note: Design serial is saved to order meta, but not displayed in cart/checkout
     */
    public function display_design_in_cart($item_data, $cart_item) {
        // Don't display design info in cart - only store it for order processing
        return $item_data;
    }

    /**
     * Replace cart thumbnail with design preview
     */
    public function replace_cart_thumbnail($thumbnail, $cart_item, $cart_item_key) {
        // Check if this item has a custom design
        if (!isset($cart_item['lg_design_data']['preview_url'])) {
            return $thumbnail;
        }

        $preview_url = $cart_item['lg_design_data']['preview_url'];
        $product_name = $cart_item['data']->get_name();

        // Just show the image, no badge or button
        return sprintf(
            '<img src="%s" alt="%s" class="lg-cart-preview" />',
            esc_url($preview_url),
            esc_attr($product_name)
        );
    }

    /**
     * Add edit button in cart
     */
    public function add_edit_button_cart($cart_item, $cart_item_key) {
        // Check if this item has a custom design
        if (!isset($cart_item['lg_design_serial'])) {
            return;
        }

        $product_id = $cart_item['product_id'];
        
        // Get product customization data
        $domain_id = get_post_meta($product_id, '_lg_domain_id', true);
        $template_serial = get_post_meta($product_id, '_lg_template_serial', true);
        $api_key = get_post_meta($product_id, '_lg_api_key', true);

        if (empty($domain_id) || empty($template_serial) || empty($api_key)) {
            return;
        }

        // Store product data and cart item info in session for editing
        WC()->session->set('lg_pending_product', array(
            'product_id' => $product_id,
            'domain_id' => $domain_id,
            'template_serial' => $template_serial,
            'api_key' => $api_key,
            'timestamp' => time(),
            'cart_item_key' => $cart_item_key, // Store cart item key to update it on return
            'editing' => true // Flag to indicate we're editing an existing design
        ));

        // Also store in transient as backup (same as when starting new design)
        set_transient('lg_product_' . $template_serial, $product_id, HOUR_IN_SECONDS);

        // Generate editor URL with the existing design serial as 'p' parameter
        // This tells the editor to load this design for editing
        $api = new LG_API($api_key);
        $editor_url = $api->get_editor_url($domain_id, $template_serial, '', array(
            'p' => $cart_item['lg_design_serial'] // 'p' parameter loads existing design for editing
        ));

        ?>
        <div class="lg-cart-edit-wrapper">
            <a href="<?php echo esc_url($editor_url); ?>" 
               class="button lg-cart-edit-button">
                <?php _e('Edit Your Design', 'loosegallery-woocommerce'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Clear design session when product is removed from cart
     */
    public function clear_design_on_remove($cart_item_key, $cart) {
        // Get the cart item being removed
        $cart_item = $cart->cart_contents[$cart_item_key];
        
        if (isset($cart_item['product_id']) && isset($cart_item['lg_design_serial'])) {
            $product_id = $cart_item['product_id'];
            
            // Clear the design from session
            $this->session->remove_design($product_id);
        }
    }

    /**
     * Add JavaScript for removal warning
     */
    public function add_removal_warning_script() {
        if (!is_cart() && !is_checkout()) {
            return;
        }

        $warning_message = __('Warning: Your design will be inaccessible if removed from the cart.', 'loosegallery-woocommerce');
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Find all remove buttons for customized items
            $('.cart_item').each(function() {
                var $cartItem = $(this);
                var hasDesign = $cartItem.find('.lg-cart-design-badge').length > 0 || 
                               $cartItem.find('.lg-cart-preview-badge').length > 0;
                
                if (hasDesign) {
                    var $removeButton = $cartItem.find('.remove');
                    
                    // Add warning on click
                    $removeButton.on('click', function(e) {
                        var confirmed = confirm('<?php echo esc_js($warning_message); ?>');
                        if (!confirmed) {
                            e.preventDefault();
                            return false;
                        }
                    });
                    
                    // Add visual indicator
                    $cartItem.addClass('lg-has-custom-design');
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Save design data to order line item
     */
    public function save_design_to_order($item, $cart_item_key, $values, $order) {
        if (isset($values['lg_design_serial'])) {
            $item->add_meta_data('_lg_design_serial', $values['lg_design_serial'], true);
            $item->add_meta_data('_lg_design_locked', 'no', true);
            
            // Save preview URL for order display
            if (isset($values['lg_design_data']['preview_url'])) {
                $item->add_meta_data('_lg_design_preview_url', $values['lg_design_data']['preview_url'], true);
            }
            
            // Save timestamp
            $item->add_meta_data('_lg_design_ordered_at', current_time('mysql'), true);
        }
    }

    /**
     * Format order item meta key display names
     */
    public function format_meta_key_display($display_key, $meta, $item) {
        // Check if meta has the key property
        if (!is_object($meta) || !isset($meta->key)) {
            return $display_key;
        }
        
        $key_mapping = array(
            '_lg_design_serial' => 'Design Serial',
            '_lg_design_locked' => 'Design Locked',
            '_lg_design_preview_url' => 'Design Preview',
            '_lg_design_ordered_at' => 'Design Ordered At',
            '_lg_design_locked_at' => 'Design Locked At'
        );
        
        if (isset($key_mapping[$meta->key])) {
            return $key_mapping[$meta->key];
        }
        
        return $display_key;
    }

    /**
     * Format order item meta value display
     */
    public function format_meta_value_display($display_value, $meta, $item) {
        // Check if meta has the required properties
        if (!is_object($meta) || !isset($meta->key) || !isset($meta->value)) {
            return $display_value;
        }
        
        // Format preview URL as a clickable link
        if ($meta->key === '_lg_design_preview_url' && !empty($meta->value)) {
            return sprintf(
                '<a href="%s" target="_blank">View Design Preview</a>',
                esc_url($meta->value)
            );
        }
        
        // Format locked status as Yes/No
        if ($meta->key === '_lg_design_locked') {
            return $meta->value === 'yes' ? 'Yes' : 'No';
        }
        
        return $display_value;
    }

    /**
     * Check if cart has any customized products
     */
    public function cart_has_designs() {
        if (WC()->cart->is_empty()) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['lg_design_serial'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all design serials in cart
     */
    public function get_cart_design_serials() {
        $serials = array();

        if (!WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                if (isset($cart_item['lg_design_serial'])) {
                    $serials[] = $cart_item['lg_design_serial'];
                }
            }
        }

        return $serials;
    }
}
