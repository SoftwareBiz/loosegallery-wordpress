<?php
/**
 * Product Meta Box
 * 
 * Adds customization settings to WooCommerce product edit page:
 * - Enable/disable customization
 * - Select LooseGallery domain
 * - Set template serial number
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Temporary error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', WP_CONTENT_DIR . '/debug.log');

class LG_Product_Meta {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('woocommerce_process_product_meta', array($this, 'save_meta_box'));
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_tab_content'));
    }

    /**
     * Add meta box to product edit page
     */
    public function add_meta_box() {
        add_meta_box(
            'loosegallery_product_customization',
            __('LooseGallery Customization', 'loosegallery-woocommerce'),
            array($this, 'render_meta_box'),
            'product',
            'side',
            'high'
        );
    }

    /**
     * Render meta box content
     */
    public function render_meta_box($post) {
        try {
        // Add nonce for security
        wp_nonce_field('loosegallery_product_meta', 'loosegallery_product_meta_nonce');

        // Get current values
        $is_customizable = get_post_meta($post->ID, '_lg_is_customizable', true);
        $selected_domain = get_post_meta($post->ID, '_lg_domain_id', true);
        $template_serial = get_post_meta($post->ID, '_lg_template_serial', true);

        // Get available domains from API keys
        $settings = get_option('loosegallery_woocommerce_settings', array());
        $api_keys = isset($settings['api_keys']) ? $settings['api_keys'] : array();
        
        ?>
        <div class="lg-product-meta">
            <p>
                <label for="lg_is_customizable">
                    <input type="checkbox" 
                           id="lg_is_customizable" 
                           name="lg_is_customizable" 
                           value="yes" 
                           <?php checked($is_customizable, 'yes'); ?> />
                    <?php _e('Enable LooseGallery Customization', 'loosegallery-woocommerce'); ?>
                </label>
            </p>

            <div id="lg_customization_options" style="<?php echo ($is_customizable !== 'yes') ? 'display:none;' : ''; ?>">
                <p>
                    <label for="lg_domain_id">
                        <strong><?php _e('Select Domain', 'loosegallery-woocommerce'); ?></strong>
                    </label>
                    <select id="lg_domain_id" name="lg_domain_id" class="widefat">
                        <option value=""><?php _e('Select a domain...', 'loosegallery-woocommerce'); ?></option>
                        <?php
                        if (!empty($api_keys)) {
                            foreach ($api_keys as $index => $api_key) {
                                if (empty($api_key)) continue;
                                
                                // Extract domain ID from API key (first 9 characters)
                                $domain_id = substr($api_key, 0, 9);
                                $domain_name = "Domain " . ($index + 1);
                                
                                // Don't test connection here - too slow for product edit page
                                // Just show domain ID
                                ?>
                                <option value="<?php echo esc_attr($domain_id); ?>" 
                                        data-api-key="<?php echo esc_attr($api_key); ?>"
                                        <?php selected($selected_domain, $domain_id); ?>>
                                    <?php echo esc_html($domain_name . ' (' . $domain_id . ')'); ?>
                                </option>
                                <?php
                            }
                        } else {
                            ?>
                            <option value="" disabled>
                                <?php _e('No API keys configured. Please add API keys in LooseGallery settings.', 'loosegallery-woocommerce'); ?>
                            </option>
                            <?php
                        }
                        ?>
                    </select>
                    <span class="description">
                        <?php _e('Select which LooseGallery domain to use for this product.', 'loosegallery-woocommerce'); ?>
                    </span>
                </p>

                <p>
                    <label for="lg_template_serial">
                        <strong><?php _e('Template Serial Number', 'loosegallery-woocommerce'); ?></strong>
                    </label>
                    <input type="text" 
                           id="lg_template_serial" 
                           name="lg_template_serial" 
                           value="<?php echo esc_attr($template_serial); ?>" 
                           class="widefat" 
                           placeholder="<?php _e('e.g., TEMP-12345', 'loosegallery-woocommerce'); ?>" />
                    <span class="description">
                        <?php _e('Enter the template serial number from LooseGallery.', 'loosegallery-woocommerce'); ?>
                    </span>
                </p>

                <?php if ($is_customizable === 'yes' && !empty($selected_domain) && !empty($template_serial)): ?>
                <p class="lg-preview-info">
                    <strong><?php _e('Preview URL:', 'loosegallery-woocommerce'); ?></strong><br>
                    <a href="<?php echo esc_url($this->get_editor_url($selected_domain, $template_serial)); ?>" 
                       target="_blank" 
                       class="button button-small">
                        <?php _e('Test Editor Link', 'loosegallery-woocommerce'); ?>
                    </a>
                </p>
                <?php endif; ?>
            </div>
        </div>

        <style>
        .lg-product-meta p {
            margin: 10px 0;
        }
        .lg-product-meta .description {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }
        .lg-preview-info {
            padding: 10px;
            background: #f0f0f1;
            border-radius: 3px;
            margin-top: 15px !important;
        }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Toggle customization options
            $('#lg_is_customizable').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#lg_customization_options').slideDown();
                } else {
                    $('#lg_customization_options').slideUp();
                }
            });
        });
        </script>
        <?php
        } catch (Exception $e) {
            echo '<div class="error"><p>Error: ' . esc_html($e->getMessage()) . '</p>';
            echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre></div>';
            error_log('LooseGallery Product Meta Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    /**
     * Save meta box data
     */
    public function save_meta_box($post_id) {
        // Check nonce
        if (!isset($_POST['loosegallery_product_meta_nonce']) || 
            !wp_verify_nonce($_POST['loosegallery_product_meta_nonce'], 'loosegallery_product_meta')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_product', $post_id)) {
            return;
        }

        // Save customizable status
        $is_customizable = isset($_POST['lg_is_customizable']) ? 'yes' : 'no';
        update_post_meta($post_id, '_lg_is_customizable', $is_customizable);

        // Save domain ID
        if (isset($_POST['lg_domain_id'])) {
            update_post_meta($post_id, '_lg_domain_id', sanitize_text_field($_POST['lg_domain_id']));
        }

        // Save template serial
        if (isset($_POST['lg_template_serial'])) {
            update_post_meta($post_id, '_lg_template_serial', sanitize_text_field($_POST['lg_template_serial']));
        }

        // Also save the API key associated with the selected domain for quick access
        if (isset($_POST['lg_domain_id']) && !empty($_POST['lg_domain_id'])) {
            $settings = get_option('loosegallery_woocommerce_settings', array());
            $api_keys = isset($settings['api_keys']) ? $settings['api_keys'] : array();
            
            // Match domain ID (first 9 chars of API key) with the selected domain
            foreach ($api_keys as $api_key) {
                if (empty($api_key)) continue;
                
                $domain_id_from_key = substr($api_key, 0, 9);
                
                if ($domain_id_from_key === $_POST['lg_domain_id']) {
                    update_post_meta($post_id, '_lg_api_key', $api_key);
                    break;
                }
            }
        }
    }

    /**
     * Get editor URL for a product
     */
    private function get_editor_url($domain_id, $template_serial) {
        $settings = get_option('loosegallery_woocommerce_settings', array());
        $editor_base_url = $settings['editor_base_url'] ?? 'https://editor.loosegallery.com';
        $return_url = $settings['return_url'] ?? home_url();

        return add_query_arg(array(
            'domain' => $domain_id,
            'template' => $template_serial,
            'return_url' => urlencode($return_url)
        ), $editor_base_url);
    }
}
