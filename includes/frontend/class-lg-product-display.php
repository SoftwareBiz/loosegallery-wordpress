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

        // Add customize me tag to product listings (shop/category pages)
        add_action('woocommerce_before_shop_loop_item_title', array($this, 'add_customize_tag'), 15);

        // Add start design button
        add_action('woocommerce_after_add_to_cart_button', array($this, 'add_design_button'));

        // Hide add to cart and quantity for customizable products without design
        add_action('woocommerce_before_add_to_cart_button', array($this, 'maybe_hide_cart_button'));

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

        $plugin_url = plugins_url('assets/images/customize.svg', dirname(dirname(__FILE__)));
        
        ?>
        <div class="lg-customize-tag-wrapper">
            <img src="<?php echo esc_url($plugin_url); ?>" alt="Customize" class="lg-customize-tag" />
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
        
        // Check if user has already designed this product
        $has_design = $this->session->has_design($product_id);
        
        // If user already has a design, don't show button on product page
        // (Edit button will be in cart instead)
        if ($has_design) {
            return;
        }

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

        // Also store in a transient as backup (lasts 1 hour)
        // Key is based on template serial so we can retrieve it on return
        set_transient('lg_product_' . $template_serial, $product_id, HOUR_IN_SECONDS);

        // Generate editor URL - NO returnTo parameter
        // The editor integration settings will have the return URL configured
        $api = new LG_API($api_key);
        $editor_url = $api->get_editor_url($domain_id, $template_serial);

        $svg_url = LG_WC_PLUGIN_URL . 'assets/images/letmeloose.svg';
        ?>
        <div class="lg-design-button-wrapper">
            <a href="<?php echo esc_url($editor_url); ?>" class="lg-design-link" style="display: inline-block; background: none; border: none; padding: 0; text-decoration: none;">
                <img src="<?php echo esc_url($svg_url); ?>" alt="Let Me Loose" class="lg-design-button-image" style="display: block; max-width: 300px; height: auto; border: none;" />
            </a>
        </div>
        <?php
    }

    /**
     * Hide add to cart button and quantity for customizable products without design
     */
    public function maybe_hide_cart_button() {
        global $product;
        
        if (!$product || !$this->is_customizable($product->get_id())) {
            return;
        }

        $product_id = $product->get_id();
        
        // Check if user has a design for this product
        $has_design = $this->session->has_design($product_id);
        
        // If no design, hide quantity and add to cart
        if (!$has_design) {
            ?>
            <style>
                .single-product .em-product-quantity,
                .single-product .quantity__label,
                .single-product .quantity,
                .single-product .single_add_to_cart_button,
                .single-product .variations,
                .single-product .variations_form table.variations,
                .single-product .reset_variations {
                    display: none !important;
                }
            </style>
            <?php
        }
    }

    /**
     * Handle return from editor
     * Matches OpenCart workflow: expects productSerial in URL, reads product from session or transient
     */
    public function handle_editor_return() {
        // Check if this is a return from editor
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

        // Validate design serial
        if (!LG_API::validate_serial($design_serial)) {
            wc_add_notice(__('Invalid design serial number.', 'loosegallery-woocommerce'), 'error');
            wp_safe_redirect(home_url('/shop'));
            exit;
        }

        // Try to get product from session first
        $product_id = null;
        $api_key = null;
        $template_serial = null;
        $cart_item_key = null;
        $is_editing = false;
        
        if (WC()->session) {
            $pending_product = WC()->session->get('lg_pending_product');
            
            if (!empty($pending_product) && isset($pending_product['product_id'])) {
                $product_id = absint($pending_product['product_id']);
                $api_key = $pending_product['api_key'];
                $template_serial = $pending_product['template_serial'];
                $cart_item_key = $pending_product['cart_item_key'] ?? null;
                $is_editing = $pending_product['editing'] ?? false;
            }
        }

        // If session doesn't have it, try to find from design serial by checking all customizable products
        if (!$product_id) {
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => '_lg_is_customizable',
                        'value' => 'yes',
                        'compare' => '='
                    )
                )
            );
            
            $products = get_posts($args);
            
            // Check transients for each product to find which one was opened
            foreach ($products as $product) {
                $product_template = get_post_meta($product->ID, '_lg_template_serial', true);
                $stored_product_id = get_transient('lg_product_' . $product_template);
                
                if ($stored_product_id == $product->ID) {
                    $product_id = $product->ID;
                    $api_key = get_post_meta($product_id, '_lg_api_key', true);
                    $template_serial = $product_template;
                    break;
                }
            }
        }

        if (!$product_id || !$api_key) {
            wc_add_notice(__('Could not find the product. Please try again.', 'loosegallery-woocommerce'), 'error');
            wp_safe_redirect(home_url('/shop'));
            exit;
        }

        // Save design to session
        $this->session->save_design($product_id, $design_serial, array(
            'returned_at' => current_time('mysql'),
            'user_id' => get_current_user_id()
        ));

        // Get preview image from API
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

        // Clear the pending product from session and transient
        if (WC()->session) {
            WC()->session->set('lg_pending_product', null);
        }
        if ($template_serial) {
            delete_transient('lg_product_' . $template_serial);
        }

        // If we're editing an existing cart item, update it with the new design serial
        if ($is_editing && $cart_item_key && isset(WC()->cart->cart_contents[$cart_item_key])) {
            // Update the cart item with new design data
            WC()->cart->cart_contents[$cart_item_key]['lg_design_serial'] = $design_serial;
            WC()->cart->cart_contents[$cart_item_key]['lg_design_data'] = array(
                'preview_url' => $preview['preview_url'] ?? '',
                'thumbnail_url' => $preview['thumbnail_url'] ?? '',
                'returned_at' => current_time('mysql'),
                'user_id' => get_current_user_id()
            );
            WC()->cart->set_session();
            
            wc_add_notice(__('Your design has been updated!', 'loosegallery-woocommerce'), 'success');
            wp_safe_redirect(wc_get_cart_url());
        } else {
            // New design - redirect to product page to select options
            wc_add_notice(__('Your design has been saved! Please select your options and add to cart.', 'loosegallery-woocommerce'), 'success');
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

        // Replace src, srcset, and data-src attributes while preserving all HTML structure
        $modified_html = preg_replace(
            '/src=["\']([^"\']+)["\']/i',
            'src="' . esc_url($preview_url) . '"',
            $html
        );
        
        $modified_html = preg_replace(
            '/srcset=["\']([^"\']+)["\']/i',
            'srcset="' . esc_url($preview_url) . '"',
            $modified_html
        );
        
        $modified_html = preg_replace(
            '/data-src=["\']([^"\']+)["\']/i',
            'data-src="' . esc_url($preview_url) . '"',
            $modified_html
        );

        return $modified_html;
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
