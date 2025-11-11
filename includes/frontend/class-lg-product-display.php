<?php
/**
 * Product Display Frontend
 * 
 * Handles product page display:
 * - Shows "Customize Me" tag on customizable products
 * - Displays "Start Design" button
 * - Handles return from editor with design serial
 * - Shows design preview after customization
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class LG_Product_Display {

    /**
     * Session handler
     */
    private $session;

    /**
     * Constructor
     */
    public function __construct() {
        $this->session = new LG_Session();

        // Add customize me tag to product images
        add_action('woocommerce_before_shop_loop_item_title', array($this, 'add_customize_tag'), 15);
        add_action('woocommerce_before_single_product_summary', array($this, 'add_customize_tag_single'), 25);

        // Add start design button
        add_action('woocommerce_after_add_to_cart_button', array($this, 'add_design_button'));

        // Handle return from editor
        add_action('template_redirect', array($this, 'handle_editor_return'));

        // Replace product image with design preview if available
        add_filter('woocommerce_single_product_image_thumbnail_html', array($this, 'replace_with_design_preview'), 10, 2);
    }

    /**
     * Check if product is customizable
     */
    private function is_customizable($product_id) {
        $is_customizable = get_post_meta($product_id, '_lg_is_customizable', true);
        $template_serial = get_post_meta($product_id, '_lg_template_serial', true);
        $domain_id = get_post_meta($product_id, '_lg_domain_id', true);

        return $is_customizable === 'yes' && !empty($template_serial) && !empty($domain_id);
    }

    /**
     * Add customize tag to shop loop items
     */
    public function add_customize_tag() {
        global $product;
        
        if (!$product || !$this->is_customizable($product->get_id())) {
            return;
        }

        $settings = get_option('loosegallery_woocommerce_settings', array());
        $tag_text = $settings['tag_text'] ?? 'Customize Me';
        $tag_color = $settings['tag_color'] ?? '#ff6b6b';
        $tag_font_color = $settings['tag_font_color'] ?? '#ffffff';
        $tag_font_size = $settings['tag_font_size'] ?? 14;

        ?>
        <div class="lg-customize-tag" style="
            background-color: <?php echo esc_attr($tag_color); ?>;
            color: <?php echo esc_attr($tag_font_color); ?>;
            font-size: <?php echo esc_attr($tag_font_size); ?>px;
        ">
            <?php echo esc_html($tag_text); ?>
        </div>
        <?php
    }

    /**
     * Add customize tag to single product page
     */
    public function add_customize_tag_single() {
        global $product;
        
        if (!$product || !$this->is_customizable($product->get_id())) {
            return;
        }

        $settings = get_option('loosegallery_woocommerce_settings', array());
        $tag_text = $settings['tag_text'] ?? 'Customize Me';
        $tag_color = $settings['tag_color'] ?? '#ff6b6b';
        $tag_font_color = $settings['tag_font_color'] ?? '#ffffff';
        $tag_font_size = $settings['tag_font_size'] ?? 14;

        ?>
        <div class="lg-customize-tag lg-customize-tag-single" style="
            background-color: <?php echo esc_attr($tag_color); ?>;
            color: <?php echo esc_attr($tag_font_color); ?>;
            font-size: <?php echo esc_attr($tag_font_size); ?>px;
        ">
            <?php echo esc_html($tag_text); ?>
        </div>
        <?php
    }

    /**
     * Add design button after add to cart
     */
    public function add_design_button() {
        global $product;
        
        if (!$product || !$this->is_customizable($product->get_id())) {
            return;
        }

        $product_id = $product->get_id();
        $settings = get_option('loosegallery_woocommerce_settings', array());
        
        // Check if user has already designed this product
        $has_design = $this->session->has_design($product_id);
        
        // Get button settings
        $button_text = $has_design 
            ? __('Edit Your Design', 'loosegallery-woocommerce')
            : ($settings['button_text'] ?? 'Start Design');
        $button_color = $settings['button_color'] ?? '#000000';
        $button_font_color = $settings['button_font_color'] ?? '#ffffff';
        $button_font_size = $settings['button_font_size'] ?? 16;

        // Get product customization data
        $domain_id = get_post_meta($product_id, '_lg_domain_id', true);
        $template_serial = get_post_meta($product_id, '_lg_template_serial', true);
        $api_key = get_post_meta($product_id, '_lg_api_key', true);

        // Store product data in session for when user returns from editor
        // This matches the OpenCart workflow
        WC()->session->set('lg_pending_product', array(
            'product_id' => $product_id,
            'domain_id' => $domain_id,
            'template_serial' => $template_serial,
            'api_key' => $api_key,
            'timestamp' => time()
        ));

        // Generate editor URL - NO returnTo parameter
        // The editor integration settings will have the return URL configured
        $api = new LG_API($api_key);
        $editor_url = $api->get_editor_url($domain_id, $template_serial);

        ?>
        <div class="lg-design-button-wrapper">
            <?php if ($has_design): ?>
            <p class="lg-design-status">
                <span class="lg-design-icon">✓</span>
                <?php _e('Your custom design is ready!', 'loosegallery-woocommerce'); ?>
            </p>
            <?php endif; ?>
            
            <a href="<?php echo esc_url($editor_url); ?>" 
               class="button lg-design-button" 
               style="
                   background-color: <?php echo esc_attr($button_color); ?>;
                   color: <?php echo esc_attr($button_font_color); ?>;
                   font-size: <?php echo esc_attr($button_font_size); ?>px;
               ">
                <?php echo esc_html($button_text); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Handle return from editor
     * Matches OpenCart workflow: expects productSerial in URL, reads product from session
     */
    public function handle_editor_return() {
        // Check if this is a return from editor
        // OpenCart style: just check for productSerial parameter
        $design_serial = '';
        if (isset($_GET['productSerial'])) {
            $design_serial = sanitize_text_field($_GET['productSerial']);
        } elseif (isset($_GET['p'])) {
            $design_serial = sanitize_text_field($_GET['p']);
        }

        // If no design serial, this isn't a return from editor
        if (empty($design_serial)) {
            return;
        }

        // Get product data from session (stored when user clicked "Start Design")
        $pending_product = WC()->session->get('lg_pending_product');
        
        if (empty($pending_product) || !isset($pending_product['product_id'])) {
            wc_add_notice(__('Session expired. Please start designing again.', 'loosegallery-woocommerce'), 'error');
            return;
        }

        $product_id = absint($pending_product['product_id']);
        $api_key = $pending_product['api_key'];

        $product_id = absint($pending_product['product_id']);
        $api_key = $pending_product['api_key'];

        // Validate design serial
        if (!LG_API::validate_serial($design_serial)) {
            wc_add_notice(__('Invalid design serial number.', 'loosegallery-woocommerce'), 'error');
            WC()->session->set('lg_pending_product', null);
            return;
        }

        // Save design to session
        $this->session->save_design($product_id, $design_serial, array(
            'returned_at' => current_time('mysql'),
            'user_id' => get_current_user_id()
        ));

        // Get preview image from API (matches OpenCart: fetch and save preview)
        if ($api_key) {
            $api = new LG_API($api_key);
            $preview = $api->get_design_preview($design_serial, 'medium');
            
            if ($preview['success']) {
                // Save preview URL to session
                $this->session->save_design($product_id, $design_serial, array(
                    'preview_url' => $preview['preview_url'],
                    'thumbnail_url' => $preview['thumbnail_url'],
                    'returned_at' => current_time('mysql'),
                    'user_id' => get_current_user_id()
                ));
            }
        }

        // Clear the pending product from session
        WC()->session->set('lg_pending_product', null);

        // Automatically add product to cart (OpenCart workflow)
        $cart_item_key = WC()->cart->add_to_cart($product_id, 1);
        
        if ($cart_item_key) {
            // Show success message
            wc_add_notice(__('Your design has been saved and added to your cart!', 'loosegallery-woocommerce'), 'success');
            
            // Redirect to cart page (matches OpenCart exactly)
            wp_safe_redirect(wc_get_cart_url());
        } else {
            // Failed to add to cart
            wc_add_notice(__('Failed to add product to cart. Please try again.', 'loosegallery-woocommerce'), 'error');
            wp_safe_redirect(get_permalink($product_id));
        }
        exit;
    }

    /**
     * Replace product image with design preview if available
     */
    public function replace_with_design_preview($html, $attachment_id) {
        global $product;
        
        if (!$product) {
            return $html;
        }

        $product_id = $product->get_id();
        
        // Check if this product has a saved design
        if (!$this->session->has_design($product_id)) {
            return $html;
        }

        $design_data = $this->session->get_design($product_id);
        
        // Check if we have a preview URL
        if (!isset($design_data['data']['preview_url']) || empty($design_data['data']['preview_url'])) {
            return $html;
        }

        $preview_url = $design_data['data']['preview_url'];
        $product_title = $product->get_name();

        // Replace with custom design preview
        return sprintf(
            '<div class="lg-design-preview-wrapper"><img src="%s" alt="%s" class="lg-design-preview" /><span class="lg-design-badge">%s</span></div>',
            esc_url($preview_url),
            esc_attr($product_title),
            esc_html__('Your Design', 'loosegallery-woocommerce')
        );
    }

    /**
     * Get design preview URL for product
     */
    public function get_design_preview_url($product_id) {
        if (!$this->session->has_design($product_id)) {
            return false;
        }

        $design_data = $this->session->get_design($product_id);
        return isset($design_data['data']['preview_url']) ? $design_data['data']['preview_url'] : false;
    }
}
