<?php
/**
 * Session Handler
 * 
 * Manages user design state across sessions:
 * - Save design serial numbers per product
 * - Persist across logout/login
 * - Associate designs with user accounts
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class LG_Session {

    /**
     * Session key prefix
     */
    const SESSION_KEY = 'lg_designs';

    /**
     * User meta key
     */
    const USER_META_KEY = '_lg_user_designs';

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize session if needed
        add_action('init', array($this, 'maybe_start_session'));
        
        // Save session to user meta on login
        add_action('wp_login', array($this, 'sync_session_to_user'), 10, 2);
        
        // Load user meta to session on login
        add_action('wp_loaded', array($this, 'sync_user_to_session'));
    }

    /**
     * Start session if not already started
     */
    public function maybe_start_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }

    /**
     * Save design for a product
     * 
     * @param int $product_id Product ID
     * @param string $design_serial Design serial number
     * @param array $additional_data Additional data to store
     */
    public function save_design($product_id, $design_serial, $additional_data = array()) {
        $designs = $this->get_all_designs();
        
        $designs[$product_id] = array(
            'serial' => $design_serial,
            'timestamp' => time(),
            'data' => $additional_data
        );
        
        // Save to session
        $_SESSION[self::SESSION_KEY] = $designs;
        
        // If user is logged in, also save to user meta
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), self::USER_META_KEY, $designs);
        }
    }

    /**
     * Get design for a product
     * 
     * @param int $product_id Product ID
     * @return array|false Design data or false if not found
     */
    public function get_design($product_id) {
        $designs = $this->get_all_designs();
        return isset($designs[$product_id]) ? $designs[$product_id] : false;
    }

    /**
     * Get design serial for a product
     * 
     * @param int $product_id Product ID
     * @return string|false Design serial or false if not found
     */
    public function get_design_serial($product_id) {
        $design = $this->get_design($product_id);
        return $design ? $design['serial'] : false;
    }

    /**
     * Get all saved designs
     * 
     * @return array All designs
     */
    public function get_all_designs() {
        // Try session first
        if (isset($_SESSION[self::SESSION_KEY])) {
            return $_SESSION[self::SESSION_KEY];
        }
        
        // Try user meta if logged in
        if (is_user_logged_in()) {
            $designs = get_user_meta(get_current_user_id(), self::USER_META_KEY, true);
            if (is_array($designs)) {
                return $designs;
            }
        }
        
        return array();
    }

    /**
     * Remove design for a product
     * 
     * @param int $product_id Product ID
     */
    public function remove_design($product_id) {
        $designs = $this->get_all_designs();
        
        if (isset($designs[$product_id])) {
            unset($designs[$product_id]);
            
            // Update session
            $_SESSION[self::SESSION_KEY] = $designs;
            
            // Update user meta if logged in
            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), self::USER_META_KEY, $designs);
            }
        }
    }

    /**
     * Clear all designs
     */
    public function clear_all_designs() {
        // Clear session
        unset($_SESSION[self::SESSION_KEY]);
        
        // Clear user meta if logged in
        if (is_user_logged_in()) {
            delete_user_meta(get_current_user_id(), self::USER_META_KEY);
        }
    }

    /**
     * Sync session data to user meta on login
     * 
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public function sync_session_to_user($user_login, $user) {
        if (isset($_SESSION[self::SESSION_KEY])) {
            $session_designs = $_SESSION[self::SESSION_KEY];
            $user_designs = get_user_meta($user->ID, self::USER_META_KEY, true);
            
            if (!is_array($user_designs)) {
                $user_designs = array();
            }
            
            // Merge session designs with user designs (session takes precedence)
            $merged_designs = array_merge($user_designs, $session_designs);
            
            update_user_meta($user->ID, self::USER_META_KEY, $merged_designs);
        }
    }

    /**
     * Sync user meta to session on page load
     */
    public function sync_user_to_session() {
        if (is_user_logged_in() && (!isset($_SESSION[self::SESSION_KEY]) || empty($_SESSION[self::SESSION_KEY]))) {
            $user_designs = get_user_meta(get_current_user_id(), self::USER_META_KEY, true);
            
            if (is_array($user_designs) && !empty($user_designs)) {
                $_SESSION[self::SESSION_KEY] = $user_designs;
            }
        }
    }

    /**
     * Check if product has a saved design
     * 
     * @param int $product_id Product ID
     * @return bool
     */
    public function has_design($product_id) {
        return $this->get_design($product_id) !== false;
    }

    /**
     * Get design age in seconds
     * 
     * @param int $product_id Product ID
     * @return int|false Age in seconds or false if not found
     */
    public function get_design_age($product_id) {
        $design = $this->get_design($product_id);
        if ($design && isset($design['timestamp'])) {
            return time() - $design['timestamp'];
        }
        return false;
    }

    /**
     * Clean up old designs (older than specified days)
     * 
     * @param int $days Number of days
     */
    public function cleanup_old_designs($days = 30) {
        $designs = $this->get_all_designs();
        $cutoff_time = time() - ($days * DAY_IN_SECONDS);
        
        foreach ($designs as $product_id => $design) {
            if (isset($design['timestamp']) && $design['timestamp'] < $cutoff_time) {
                unset($designs[$product_id]);
            }
        }
        
        // Update session
        $_SESSION[self::SESSION_KEY] = $designs;
        
        // Update user meta if logged in
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), self::USER_META_KEY, $designs);
        }
    }
}

// Initialize session handler
new LG_Session();
