<?php
/**
 * Plugin Name: Loose Gallery for WooCommerce
 * Plugin URI: https://loosegallery.com
 * Description: Integrate Loose Gallery design editor with WooCommerce products for custom product personalization. Uses GraphQL API.
 * Version: 1.0.1
 * Author: SoftwareBiz
 * Author URI: https://softwarebiz.co
 * Text Domain: loosegallery-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LG_WC_VERSION', '1.0.1');
define('LG_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LG_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LG_WC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Loose Gallery WooCommerce Plugin Class
 */
class LooseGallery_WooCommerce {

    /**
     * The single instance of the class
     */
    private static $instance = null;

    /**
     * Main Instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Check if WooCommerce is active
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Check WooCommerce dependency
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Load plugin files
        $this->includes();

        // Initialize components
        $this->init_hooks();

        // Load text domain
        load_plugin_textdomain('loosegallery-woocommerce', false, dirname(LG_WC_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once LG_WC_PLUGIN_DIR . 'includes/class-lg-api.php';
        require_once LG_WC_PLUGIN_DIR . 'includes/class-lg-session.php';
        
        // Admin classes
        if (is_admin()) {
            require_once LG_WC_PLUGIN_DIR . 'includes/admin/class-lg-admin-settings.php';
            require_once LG_WC_PLUGIN_DIR . 'includes/admin/class-lg-product-meta.php';
        }
        
        // Frontend classes
        if (!is_admin()) {
            require_once LG_WC_PLUGIN_DIR . 'includes/frontend/class-lg-product-display.php';
            require_once LG_WC_PLUGIN_DIR . 'includes/frontend/class-lg-cart.php';
            require_once LG_WC_PLUGIN_DIR . 'includes/frontend/class-lg-checkout.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Declare WooCommerce HPOS compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Initialize admin settings
        if (is_admin()) {
            new LG_Admin_Settings();
            new LG_Product_Meta();
        }

        // Initialize frontend components
        if (!is_admin()) {
            new LG_Product_Display();
            new LG_Cart();
            new LG_Checkout();
        }
    }

    /**
     * Declare compatibility with WooCommerce features
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'loosegallery-woocommerce',
            LG_WC_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            LG_WC_VERSION
        );

        wp_enqueue_script(
            'loosegallery-woocommerce',
            LG_WC_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            LG_WC_VERSION,
            true
        );

        // Localize script with AJAX data
        wp_localize_script('loosegallery-woocommerce', 'lgWooCommerce', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lg-woocommerce-nonce'),
            'deleteWarning' => __('Warning: Your design will be inaccessible if removed from the cart.', 'loosegallery-woocommerce')
        ));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on product edit page and plugin settings page
        if ($hook === 'post.php' || $hook === 'post-new.php' || strpos($hook, 'loosegallery') !== false) {
            wp_enqueue_style(
                'loosegallery-woocommerce-admin',
                LG_WC_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                LG_WC_VERSION
            );

            wp_enqueue_script(
                'loosegallery-woocommerce-admin',
                LG_WC_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'wp-color-picker'),
                LG_WC_VERSION,
                true
            );

            wp_enqueue_style('wp-color-picker');
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create default options
        $default_options = array(
            'api_keys' => array(),
            'return_url' => home_url(),
            'editor_base_url' => 'https://editor.loosegallery.com',
            'tag_text' => __('Customize Me', 'loosegallery-woocommerce'),
            'tag_color' => '#ff6b6b',
            'tag_font_color' => '#ffffff'\n        );\n\n        add_option('loosegallery_woocommerce_settings', $default_options);
        );

        add_option('loosegallery_woocommerce_settings', $default_options);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('Loose Gallery for WooCommerce requires WooCommerce to be installed and activated.', 'loosegallery-woocommerce'); ?></p>
        </div>
        <?php
    }
}

/**
 * Initialize the plugin
 */
function loosegallery_woocommerce() {
    return LooseGallery_WooCommerce::instance();
}

// Start the plugin
loosegallery_woocommerce();
