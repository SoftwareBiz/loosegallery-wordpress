<?php
/**
 * Plugin Updater
 * 
 * Checks for plugin updates from GitHub repository
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class LG_Plugin_Updater {

    /**
     * GitHub repository info
     */
    private $github_username = 'SoftwareBiz';
    private $github_repo = 'loosegallery-wordpress';
    private $plugin_slug = 'loosegallery-woocommerce';
    private $plugin_file;
    private $github_response;

    /**
     * Constructor
     */
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;

        // Check for updates
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        
        // Plugin information
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        
        // After update
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        
        // Clear cache on admin init
        add_action('admin_init', array($this, 'maybe_clear_cache'));
    }

    /**
     * Get GitHub release info
     */
    private function get_github_release_info() {
        if (!empty($this->github_response)) {
            return $this->github_response;
        }

        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repo
        );

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $this->github_response = json_decode($body, true);

        return $this->github_response;
    }

    /**
     * Check for plugin updates
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release_info = $this->get_github_release_info();

        if (!$release_info || !isset($release_info['tag_name'])) {
            return $transient;
        }

        // Get version from tag (remove 'v' prefix if present)
        $github_version = ltrim($release_info['tag_name'], 'v');
        $current_version = LG_WC_VERSION;

        // Compare versions
        if (version_compare($github_version, $current_version, '>')) {
            $plugin_data = array(
                'slug' => $this->plugin_slug,
                'plugin' => plugin_basename($this->plugin_file),
                'new_version' => $github_version,
                'url' => $release_info['html_url'],
                'package' => $this->get_download_url($release_info),
                'tested' => '6.4',
                'requires_php' => '7.4',
                'compatibility' => new stdClass()
            );

            $transient->response[plugin_basename($this->plugin_file)] = (object) $plugin_data;
        }

        return $transient;
    }

    /**
     * Get download URL from release
     */
    private function get_download_url($release_info) {
        // Try to find .zip asset
        if (isset($release_info['assets']) && !empty($release_info['assets'])) {
            foreach ($release_info['assets'] as $asset) {
                if (isset($asset['name']) && strpos($asset['name'], '.zip') !== false) {
                    return $asset['browser_download_url'];
                }
            }
        }

        // Fallback to zipball
        return $release_info['zipball_url'] ?? '';
    }

    /**
     * Plugin information popup
     */
    public function plugin_info($false, $action, $response) {
        if ($action !== 'plugin_information') {
            return $false;
        }

        if (empty($response->slug) || $response->slug !== $this->plugin_slug) {
            return $false;
        }

        $release_info = $this->get_github_release_info();

        if (!$release_info) {
            return $false;
        }

        $github_version = ltrim($release_info['tag_name'], 'v');

        $plugin_info = new stdClass();
        $plugin_info->name = 'LooseGallery for WooCommerce';
        $plugin_info->slug = $this->plugin_slug;
        $plugin_info->version = $github_version;
        $plugin_info->author = '<a href="https://loosegallery.com">LooseGallery</a>';
        $plugin_info->homepage = 'https://github.com/' . $this->github_username . '/' . $this->github_repo;
        $plugin_info->requires = '5.8';
        $plugin_info->tested = '6.4';
        $plugin_info->requires_php = '7.4';
        $plugin_info->download_link = $this->get_download_url($release_info);
        $plugin_info->sections = array(
            'description' => $release_info['body'] ?? 'LooseGallery WooCommerce integration plugin',
            'changelog' => $this->parse_changelog($release_info['body'] ?? '')
        );

        return $plugin_info;
    }

    /**
     * Parse changelog from release notes
     */
    private function parse_changelog($body) {
        if (empty($body)) {
            return 'See GitHub release notes for details.';
        }

        // Convert markdown to HTML
        $changelog = wpautop($body);
        return $changelog;
    }

    /**
     * After plugin installation
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        $install_directory = plugin_dir_path($this->plugin_file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        if ($this->is_plugin_active()) {
            activate_plugin($this->plugin_file);
        }

        return $result;
    }

    /**
     * Check if plugin is active
     */
    private function is_plugin_active() {
        return is_plugin_active(plugin_basename($this->plugin_file));
    }

    /**
     * Maybe clear update cache
     */
    public function maybe_clear_cache() {
        if (isset($_GET['lg_clear_update_cache'])) {
            delete_transient('update_plugins');
            $this->github_response = null;
            wp_redirect(admin_url('plugins.php'));
            exit;
        }
    }
}
