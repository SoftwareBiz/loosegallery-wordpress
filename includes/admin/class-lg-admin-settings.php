<?php
/**
 * Admin Settings Page
 * 
 * Handles plugin configuration including API keys, redirect URLs,
 * button customization, and copyright text.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class LG_Admin_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_lg_test_api_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_lg_remove_api_key', array($this, 'remove_api_key'));
    }

    /**
     * Add settings page to WordPress admin menu
     */
    public function add_settings_page() {
        add_menu_page(
            __('LooseGallery Settings', 'loosegallery-woocommerce'),
            __('LooseGallery', 'loosegallery-woocommerce'),
            'manage_options',
            'loosegallery-settings',
            array($this, 'render_settings_page'),
            'dashicons-art',
            56
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('loosegallery_settings_group', 'loosegallery_woocommerce_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));

        // API Settings Section
        add_settings_section(
            'lg_api_section',
            __('API Configuration', 'loosegallery-woocommerce'),
            array($this, 'render_api_section'),
            'loosegallery-settings'
        );

        // URL Settings Section
        add_settings_section(
            'lg_url_section',
            __('URL Configuration', 'loosegallery-woocommerce'),
            array($this, 'render_url_section'),
            'loosegallery-settings'
        );

        // Button Customization Section
        add_settings_section(
            'lg_button_section',
            __('Button Customization', 'loosegallery-woocommerce'),
            array($this, 'render_button_section'),
            'loosegallery-settings'
        );

        // Tag Customization Section
        add_settings_section(
            'lg_tag_section',
            __('Tag Customization', 'loosegallery-woocommerce'),
            array($this, 'render_tag_section'),
            'loosegallery-settings'
        );

        // Copyright Section
        add_settings_section(
            'lg_copyright_section',
            __('Copyright & Legal', 'loosegallery-woocommerce'),
            array($this, 'render_copyright_section'),
            'loosegallery-settings'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        // Sanitize API keys
        if (isset($input['api_keys']) && is_array($input['api_keys'])) {
            $sanitized['api_keys'] = array_map('sanitize_text_field', $input['api_keys']);
        } else {
            $sanitized['api_keys'] = array();
        }

        // Sanitize URLs
        $sanitized['return_url'] = isset($input['return_url']) ? esc_url_raw($input['return_url']) : home_url();
        $sanitized['editor_base_url'] = isset($input['editor_base_url']) ? esc_url_raw($input['editor_base_url']) : 'https://editor.loosegallery.com';

        // Sanitize button settings
        $sanitized['button_text'] = isset($input['button_text']) ? sanitize_text_field($input['button_text']) : 'Start Design';
        $sanitized['button_color'] = isset($input['button_color']) ? sanitize_hex_color($input['button_color']) : '#000000';
        $sanitized['button_font_color'] = isset($input['button_font_color']) ? sanitize_hex_color($input['button_font_color']) : '#ffffff';
        $sanitized['button_font_size'] = isset($input['button_font_size']) ? absint($input['button_font_size']) : 16;

        // Sanitize tag settings
        $sanitized['tag_text'] = isset($input['tag_text']) ? sanitize_text_field($input['tag_text']) : 'Customize Me';
        $sanitized['tag_color'] = isset($input['tag_color']) ? sanitize_hex_color($input['tag_color']) : '#ff6b6b';
        $sanitized['tag_font_color'] = isset($input['tag_font_color']) ? sanitize_hex_color($input['tag_font_color']) : '#ffffff';
        $sanitized['tag_font_size'] = isset($input['tag_font_size']) ? absint($input['tag_font_size']) : 14;

        // Sanitize copyright text
        $sanitized['copyright_text'] = isset($input['copyright_text']) ? wp_kses_post($input['copyright_text']) : '';

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current settings
        $settings = get_option('loosegallery_woocommerce_settings', array());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors('loosegallery_woocommerce_settings'); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('loosegallery_settings_group');
                do_settings_sections('loosegallery-settings');
                submit_button(__('Save Settings', 'loosegallery-woocommerce'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render API section
     */
    public function render_api_section() {
        $settings = get_option('loosegallery_woocommerce_settings', array());
        $api_keys = isset($settings['api_keys']) ? $settings['api_keys'] : array();
        ?>
        <p><?php _e('Add your LooseGallery API keys to connect multiple domains. Each API key will display its associated domain name.', 'loosegallery-woocommerce'); ?></p>
        
        <table class="form-table lg-api-keys-table">
            <tbody id="lg-api-keys-container">
                <?php
                if (!empty($api_keys)) {
                    foreach ($api_keys as $index => $api_key) {
                        $this->render_api_key_row($index, $api_key);
                    }
                } else {
                    $this->render_api_key_row(0, '');
                }
                ?>
            </tbody>
        </table>
        
        <button type="button" class="button" id="lg-add-api-key">
            <?php _e('+ Add Another API Key', 'loosegallery-woocommerce'); ?>
        </button>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var apiKeyIndex = <?php echo count($api_keys); ?>;
            
            // Add new API key row
            $('#lg-add-api-key').on('click', function() {
                var newRow = `
                    <tr class="lg-api-key-row">
                        <th scope="row">
                            <label><?php _e('API Key', 'loosegallery-woocommerce'); ?> ${apiKeyIndex + 1}</label>
                        </th>
                        <td>
                            <input type="password" 
                                   name="loosegallery_woocommerce_settings[api_keys][${apiKeyIndex}]" 
                                   class="regular-text lg-api-key-input" 
                                   placeholder="<?php _e('Enter API key...', 'loosegallery-woocommerce'); ?>" />
                            <button type="button" class="button lg-test-api-key" data-index="${apiKeyIndex}">
                                <?php _e('Test Connection', 'loosegallery-woocommerce'); ?>
                            </button>
                            <button type="button" class="button lg-remove-api-key">
                                <?php _e('Remove', 'loosegallery-woocommerce'); ?>
                            </button>
                            <span class="lg-api-status"></span>
                            <p class="description lg-domain-name"></p>
                        </td>
                    </tr>
                `;
                $('#lg-api-keys-container').append(newRow);
                apiKeyIndex++;
            });

            // Remove API key row
            $(document).on('click', '.lg-remove-api-key', function() {
                if ($('.lg-api-key-row').length > 1) {
                    $(this).closest('tr').remove();
                } else {
                    alert('<?php _e('You must have at least one API key field.', 'loosegallery-woocommerce'); ?>');
                }
            });

            // Test API connection
            $(document).on('click', '.lg-test-api-key', function() {
                var $button = $(this);
                var $row = $button.closest('tr');
                var $input = $row.find('.lg-api-key-input');
                var $status = $row.find('.lg-api-status');
                var $domainName = $row.find('.lg-domain-name');
                var apiKey = $input.val();

                if (!apiKey) {
                    alert('<?php _e('Please enter an API key first.', 'loosegallery-woocommerce'); ?>');
                    return;
                }

                $button.prop('disabled', true).text('<?php _e('Testing...', 'loosegallery-woocommerce'); ?>');
                $status.html('<span style="color: #999;">⏳ <?php _e('Testing...', 'loosegallery-woocommerce'); ?></span>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lg_test_api_connection',
                        api_key: apiKey,
                        nonce: '<?php echo wp_create_nonce('lg-test-api'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('<span style="color: #46b450;">✓ <?php _e('Connected', 'loosegallery-woocommerce'); ?></span>');
                            if (response.data.domain_name) {
                                $domainName.text('<?php _e('Domain:', 'loosegallery-woocommerce'); ?> ' + response.data.domain_name);
                            }
                        } else {
                            var errorMsg = response.data.message || '<?php _e('Connection failed', 'loosegallery-woocommerce'); ?>';
                            if (response.data.status_code) {
                                errorMsg += ' (Status: ' + response.data.status_code + ')';
                            }
                            $status.html('<span style="color: #dc3232;">✗ ' + errorMsg + '</span>');
                            $domainName.text('');
                            
                            // Log full error to console
                            console.error('LooseGallery API Error:', response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        $status.html('<span style="color: #dc3232;">✗ <?php _e('Connection failed', 'loosegallery-woocommerce'); ?></span>');
                        $domainName.text('');
                        console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php _e('Test Connection', 'loosegallery-woocommerce'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render single API key row
     */
    private function render_api_key_row($index, $api_key) {
        ?>
        <tr class="lg-api-key-row">
            <th scope="row">
                <label><?php printf(__('API Key %d', 'loosegallery-woocommerce'), $index + 1); ?></label>
            </th>
            <td>
                <input type="password" 
                       name="loosegallery_woocommerce_settings[api_keys][<?php echo $index; ?>]" 
                       value="<?php echo esc_attr($api_key); ?>" 
                       class="regular-text lg-api-key-input" 
                       placeholder="<?php _e('Enter API key...', 'loosegallery-woocommerce'); ?>" />
                <button type="button" class="button lg-test-api-key" data-index="<?php echo $index; ?>">
                    <?php _e('Test Connection', 'loosegallery-woocommerce'); ?>
                </button>
                <button type="button" class="button lg-remove-api-key">
                    <?php _e('Remove', 'loosegallery-woocommerce'); ?>
                </button>
                <span class="lg-api-status"></span>
                <p class="description lg-domain-name"></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Render URL section
     */
    public function render_url_section() {
        $settings = get_option('loosegallery_woocommerce_settings', array());
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="lg_return_url"><?php _e('Return URL', 'loosegallery-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="url" 
                           id="lg_return_url" 
                           name="loosegallery_woocommerce_settings[return_url]" 
                           value="<?php echo esc_attr($settings['return_url'] ?? home_url()); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('URL where users return after editing in LooseGallery. Default: your site home page.', 'loosegallery-woocommerce'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="lg_editor_base_url"><?php _e('Editor Base URL', 'loosegallery-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="url" 
                           id="lg_editor_base_url" 
                           name="loosegallery_woocommerce_settings[editor_base_url]" 
                           value="<?php echo esc_attr($settings['editor_base_url'] ?? 'https://editor.loosegallery.com'); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('Base URL for the LooseGallery editor. Default: https://editor.loosegallery.com', 'loosegallery-woocommerce'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render button customization section
     */
    public function render_button_section() {
        $settings = get_option('loosegallery_woocommerce_settings', array());
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="lg_button_text"><?php _e('Button Text', 'loosegallery-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="lg_button_text" 
                           name="loosegallery_woocommerce_settings[button_text]" 
                           value="<?php echo esc_attr($settings['button_text'] ?? 'Start Design'); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="lg_button_color"><?php _e('Button Background Color', 'loosegallery-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="lg_button_color" 
                           name="loosegallery_woocommerce_settings[button_color]" 
                           value="<?php echo esc_attr($settings['button_color'] ?? '#000000'); ?>" 
                           class="lg-color-picker" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="lg_button_font_color"><?php _e('Button Text Color', 'loosegallery-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="lg_button_font_color" 
                           name="loosegallery_woocommerce_settings[button_font_color]" 
                           value="<?php echo esc_attr($settings['button_font_color'] ?? '#ffffff'); ?>" 
                           class="lg-color-picker" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="lg_button_font_size"><?php _e('Button Font Size (px)', 'loosegallery-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="lg_button_font_size" 
                           name="loosegallery_woocommerce_settings[button_font_size]" 
                           value="<?php echo esc_attr($settings['button_font_size'] ?? 16); ?>" 
                           min="10" 
                           max="32" 
                           class="small-text" />
                </td>
            </tr>
        </table>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.lg-color-picker').wpColorPicker();
        });
        </script>
        <?php
    }

    /**
     * Render tag customization section
     */
    public function render_tag_section() {
        $settings = get_option('loosegallery_woocommerce_settings', array());
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="lg_tag_text"><?php _e('Tag Text', 'loosegallery-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="lg_tag_text" 
                           name="loosegallery_woocommerce_settings[tag_text]" 
                           value="<?php echo esc_attr($settings['tag_text'] ?? 'Customize Me'); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="lg_tag_color"><?php _e('Tag Background Color', 'loosegallery-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="lg_tag_color" 
                           name="loosegallery_woocommerce_settings[tag_color]" 
                           value="<?php echo esc_attr($settings['tag_color'] ?? '#ff6b6b'); ?>" 
                           class="lg-color-picker" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="lg_tag_font_color"><?php _e('Tag Text Color', 'loosegallery-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="lg_tag_font_color" 
                           name="loosegallery_woocommerce_settings[tag_font_color]" 
                           value="<?php echo esc_attr($settings['tag_font_color'] ?? '#ffffff'); ?>" 
                           class="lg-color-picker" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="lg_tag_font_size"><?php _e('Tag Font Size (px)', 'loosegallery-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="lg_tag_font_size" 
                           name="loosegallery_woocommerce_settings[tag_font_size]" 
                           value="<?php echo esc_attr($settings['tag_font_size'] ?? 14); ?>" 
                           min="10" 
                           max="24" 
                           class="small-text" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render copyright section
     */
    public function render_copyright_section() {
        $settings = get_option('loosegallery_woocommerce_settings', array());
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="lg_copyright_text"><?php _e('Copyright Agreement Text', 'loosegallery-woocommerce'); ?></label>
                </th>
                <td>
                    <textarea id="lg_copyright_text" 
                              name="loosegallery_woocommerce_settings[copyright_text]" 
                              rows="3" 
                              class="large-text"><?php echo esc_textarea($settings['copyright_text'] ?? 'I agree to the copyright ownership and understand my design will be printed as is.'); ?></textarea>
                    <p class="description">
                        <?php _e('This text will appear as a required checkbox during checkout.', 'loosegallery-woocommerce'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * AJAX: Test API connection
     */
    public function test_api_connection() {
        check_ajax_referer('lg-test-api', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'loosegallery-woocommerce')));
        }

        $api_key = sanitize_text_field($_POST['api_key']);

        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key is required', 'loosegallery-woocommerce')));
        }

        // Test API connection
        $api = new LG_API($api_key);
        $result = $api->test_connection();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('API connection successful!', 'loosegallery-woocommerce'),
                'total_items' => $result['total_items'] ?? 0
            ));
        } else {
            // Return detailed error information
            wp_send_json_error(array(
                'message' => $result['message'] ?? __('Connection failed', 'loosegallery-woocommerce'),
                'status_code' => $result['status_code'] ?? null,
                'raw_response' => $result['raw_response'] ?? null
            ));
        }
    }
}
