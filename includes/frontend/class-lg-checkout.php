<?php
/**
 * Checkout Integration
 * 
 * Handles checkout process:
 * - Add copyright agreement checkbox
 * - Validate checkbox is checked
 * - Lock designs after successful order
 * - Display design info in order details
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class LG_Checkout {

    /**
     * Constructor
     */
    public function __construct() {
        // Add copyright checkbox to checkout
        add_action('woocommerce_review_order_before_submit', array($this, 'add_copyright_checkbox'));
        
        // Validate copyright checkbox
        add_action('woocommerce_checkout_process', array($this, 'validate_copyright_checkbox'));
        
        // Lock designs after successful order
        add_action('woocommerce_thankyou', array($this, 'lock_designs_on_order'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this, 'lock_designs_on_completion'), 10, 1);
        
        // Display design info in order details
        add_action('woocommerce_order_item_meta_start', array($this, 'display_design_in_order'), 10, 3);
        
        // Hide edit button for completed orders
        add_filter('woocommerce_order_item_meta_end', array($this, 'hide_edit_button_for_locked'), 10, 3);
        
        // Add design preview to order emails
        add_filter('woocommerce_order_item_thumbnail', array($this, 'add_design_preview_to_email'), 10, 2);
    }

    /**
     * Check if cart has customized products
     */
    private function cart_has_designs() {
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
     * Add copyright agreement checkbox
     */
    public function add_copyright_checkbox() {
        // Only show if cart has customized products
        if (!$this->cart_has_designs()) {
            return;
        }

        $settings = get_option('loosegallery_woocommerce_settings', array());
        $copyright_text = $settings['copyright_text'] ?? 
            __('I agree to the copyright ownership and understand my design will be printed as is.', 'loosegallery-woocommerce');

        ?>
        <div class="lg-copyright-agreement">
            <p class="form-row terms lg-copyright-checkbox">
                <label class="checkbox">
                    <input type="checkbox" 
                           name="lg_copyright_agreement" 
                           id="lg_copyright_agreement" 
                           value="yes" />
                    <span class="required">*</span> <?php echo wp_kses_post($copyright_text); ?>
                </label>
            </p>
        </div>

        <style>
        .lg-copyright-agreement {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
        }
        .lg-copyright-checkbox {
            margin: 0 !important;
        }
        .lg-copyright-checkbox label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .lg-copyright-checkbox input[type="checkbox"] {
            margin-top: 3px;
            flex-shrink: 0;
        }
        .lg-copyright-checkbox .required {
            color: #b32d2e;
            font-weight: bold;
        }
        </style>
        <?php
    }

    /**
     * Validate copyright checkbox is checked
     */
    public function validate_copyright_checkbox() {
        // Only validate if cart has customized products
        if (!$this->cart_has_designs()) {
            return;
        }

        if (!isset($_POST['lg_copyright_agreement']) || $_POST['lg_copyright_agreement'] !== 'yes') {
            wc_add_notice(
                __('You must agree to the copyright terms to proceed with your custom design order.', 'loosegallery-woocommerce'),
                'error'
            );
        }
    }

    /**
     * Lock designs after successful order (on thank you page)
     */
    public function lock_designs_on_order($order_id) {
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $this->lock_order_designs($order);
    }

    /**
     * Lock designs when order status is completed
     */
    public function lock_designs_on_completion($order_id) {
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $this->lock_order_designs($order);
    }

    /**
     * Lock all designs in an order
     */
    private function lock_order_designs($order) {
        foreach ($order->get_items() as $item_id => $item) {
            $design_serial = $item->get_meta('_lg_design_serial');
            $already_locked = $item->get_meta('_lg_design_locked');

            // Skip if no design or already locked
            if (empty($design_serial) || $already_locked === 'yes') {
                continue;
            }

            // Get API key from product
            $product_id = $item->get_product_id();
            $api_key = get_post_meta($product_id, '_lg_api_key', true);

            if (empty($api_key)) {
                continue;
            }

            // Lock the design via API
            $api = new LG_API($api_key);
            $result = $api->lock_design($design_serial);

            if ($result['success']) {
                // Mark as locked in order meta
                $item->update_meta_data('_lg_design_locked', 'yes');
                $item->update_meta_data('_lg_design_locked_at', current_time('mysql'));
                $item->save();

                // Log the action
                $order->add_order_note(
                    sprintf(
                        __('Design %s has been locked and can no longer be edited.', 'loosegallery-woocommerce'),
                        $design_serial
                    )
                );
            } else {
                // Log the error
                $order->add_order_note(
                    sprintf(
                        __('Failed to lock design %s: %s', 'loosegallery-woocommerce'),
                        $design_serial,
                        $result['message']
                    )
                );
            }
        }
    }

    /**
     * Display design info in order details
     * Note: Design serial is stored in order meta but not displayed to customer
     */
    public function display_design_in_order($item_id, $item, $order) {
        // Design serial is saved in order meta for printing/processing
        // but we don't display it to the customer in order details
        return;
    }

    /**
     * Hide edit button for locked designs
     */
    public function hide_edit_button_for_locked($item_id, $item, $order) {
        $is_locked = $item->get_meta('_lg_design_locked');
        $design_serial = $item->get_meta('_lg_design_serial');

        if (empty($design_serial) || $is_locked !== 'yes') {
            return;
        }

        ?>
        <style>
        /* Hide edit button for this item */
        .order_item_<?php echo esc_attr($item_id); ?> .lg-cart-edit-button {
            display: none !important;
        }
        </style>
        <?php
    }

    /**
     * Add design preview to order emails
     */
    public function add_design_preview_to_email($thumbnail, $item) {
        $preview_url = $item->get_meta('_lg_design_preview_url');

        if (empty($preview_url)) {
            return $thumbnail;
        }

        $product_name = $item->get_name();

        return sprintf(
            '<div class="lg-email-preview-wrapper"><img src="%s" alt="%s" class="lg-email-preview" style="max-width: 150px; height: auto;" /><div style="font-size: 11px; color: #666; margin-top: 5px;">%s</div></div>',
            esc_url($preview_url),
            esc_attr($product_name),
            esc_html__('Custom Design', 'loosegallery-woocommerce')
        );
    }

    /**
     * Check if order has customized products
     */
    public function order_has_designs($order) {
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_lg_design_serial')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all design serials from order
     */
    public function get_order_design_serials($order) {
        $serials = array();

        foreach ($order->get_items() as $item) {
            $serial = $item->get_meta('_lg_design_serial');
            if (!empty($serial)) {
                $serials[] = array(
                    'serial' => $serial,
                    'locked' => $item->get_meta('_lg_design_locked') === 'yes',
                    'product_name' => $item->get_name()
                );
            }
        }

        return $serials;
    }
}
