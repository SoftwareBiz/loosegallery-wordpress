<?php
/**
 * API Handler
 * 
 * Handles all communication with LooseGallery API:
 * - Test API connection
 * - Get domain information
 * - Generate editor URLs
 * - Get design preview images
 * - Lock designs after purchase
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class LG_API {

    /**
     * API Key
     */
    private $api_key;

    /**
     * API Base URL (GraphQL endpoint)
     */
    private $api_base_url = 'https://api.loosegallery.com/graphql';

    /**
     * Constructor
     */
    public function __construct($api_key = null) {
        $this->api_key = $api_key;
        
        // API endpoint is fixed to GraphQL
        $this->api_base_url = 'https://api.loosegallery.com/graphql';
    }

    /**
     * Test API connection
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('API key is required', 'loosegallery-woocommerce')
            );
        }

        $query = '
            query {
                domain {
                    id
                    name
                }
            }
        ';

        $response = $this->make_graphql_request($query);

        if ($response['success'] && isset($response['data']['domain'])) {
            return array(
                'success' => true,
                'domain_name' => $response['data']['domain']['name'] ?? 'Unknown Domain',
                'domain_id' => $response['data']['domain']['id'] ?? null
            );
        }

        return array(
            'success' => false,
            'message' => $response['message'] ?? __('Failed to connect to API', 'loosegallery-woocommerce')
        );
    }

    /**
     * Get domain information
     */
    public function get_domain_info() {
        if (empty($this->api_key)) {
            return array('success' => false);
        }

        $query = '
            query {
                domain {
                    id
                    name
                }
            }
        ';

        $response = $this->make_graphql_request($query);

        if ($response['success'] && isset($response['data']['domain'])) {
            return array(
                'success' => true,
                'domain_name' => $response['data']['domain']['name'] ?? 'Unknown Domain',
                'domain_id' => $response['data']['domain']['id'] ?? null,
                'domain_data' => $response['data']['domain'] ?? array()
            );
        }

        return array('success' => false);
    }

    /**
     * Get design preview URL
     * 
     * @param string $design_serial The design serial number
     * @param string $size Size: 'thumbnail', 'small', 'medium', 'large'
     * @return array
     */
    public function get_design_preview($design_serial, $size = 'medium') {
        if (empty($this->api_key) || empty($design_serial)) {
            return array(
                'success' => false,
                'message' => __('API key and design serial are required', 'loosegallery-woocommerce')
            );
        }

        $query = '
            query GetAsset($serial: String!) {
                asset(serial: $serial) {
                    serial
                    previewUrl
                    thumbnailUrl
                }
            }
        ';

        $variables = array(
            'serial' => $design_serial
        );

        $response = $this->make_graphql_request($query, $variables);

        if ($response['success'] && isset($response['data']['asset'])) {
            return array(
                'success' => true,
                'preview_url' => $response['data']['asset']['previewUrl'] ?? '',
                'thumbnail_url' => $response['data']['asset']['thumbnailUrl'] ?? ''
            );
        }

        return array(
            'success' => false,
            'message' => $response['message'] ?? __('Failed to get design preview', 'loosegallery-woocommerce')
        );
    }

    /**
     * Lock design (prevent further editing)
     * 
     * @param string $design_serial The design serial number
     * @return array
     */
    public function lock_design($design_serial) {
        if (empty($this->api_key) || empty($design_serial)) {
            return array(
                'success' => false,
                'message' => __('API key and design serial are required', 'loosegallery-woocommerce')
            );
        }

        $mutation = '
            mutation LockAsset($serial: String!) {
                lockAsset(serial: $serial) {
                    serial
                    locked
                }
            }
        ';

        $variables = array(
            'serial' => $design_serial
        );

        $response = $this->make_graphql_request($mutation, $variables);

        if ($response['success'] && isset($response['data']['lockAsset'])) {
            return array(
                'success' => true,
                'message' => __('Design locked successfully', 'loosegallery-woocommerce')
            );
        }

        return array(
            'success' => false,
            'message' => $response['message'] ?? __('Failed to lock design', 'loosegallery-woocommerce')
        );
    }

    /**
     * Get design information
     * 
     * @param string $design_serial The design serial number
     * @return array
     */
    public function get_design_info($design_serial) {
        if (empty($this->api_key) || empty($design_serial)) {
            return array('success' => false);
        }

        $query = '
            query GetAsset($serial: String!) {
                asset(serial: $serial) {
                    serial
                    locked
                    previewUrl
                    thumbnailUrl
                }
            }
        ';

        $variables = array(
            'serial' => $design_serial
        );

        $response = $this->make_graphql_request($query, $variables);

        if ($response['success'] && isset($response['data']['asset'])) {
            return array(
                'success' => true,
                'design_data' => $response['data']['asset'] ?? array(),
                'is_locked' => $response['data']['asset']['locked'] ?? false
            );
        }

        return array('success' => false);
    }

    /**
     * Generate editor URL for starting a design
     * 
     * @param string $domain_id Domain ID
     * @param string $template_serial Template serial number
     * @param string $return_url URL to return to after editing
     * @param array $additional_params Additional query parameters
     * @return string
     */
    public function get_editor_url($domain_id, $template_serial, $return_url = '', $additional_params = array()) {
        $settings = get_option('loosegallery_woocommerce_settings', array());
        $editor_base_url = $settings['editor_base_url'] ?? 'https://editor.loosegallery.com';
        
        if (empty($return_url)) {
            $return_url = $settings['return_url'] ?? home_url();
        }

        $params = array_merge(array(
            'domain' => $domain_id,
            'template' => $template_serial,
            'return_url' => $return_url,
            'api_key' => $this->api_key
        ), $additional_params);

        return add_query_arg($params, $editor_base_url);
    }

    /**
     * Make GraphQL API request
     * 
     * @param string $query GraphQL query or mutation
     * @param array $variables Variables for the query
     * @return array
     */
    private function make_graphql_request($query, $variables = array()) {
        $url = $this->api_base_url;
        
        $body = array(
            'query' => $query
        );

        if (!empty($variables)) {
            $body['variables'] = $variables;
        }

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'x-api-key' => $this->api_key,  // AWS API Gateway uses x-api-key header
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30,
            'sslverify' => true
        );

        // Make the request
        $response = wp_remote_request($url, $args);

        // Check for errors
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'debug' => array(
                    'url' => $url,
                    'has_api_key' => !empty($this->api_key)
                )
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($response_body, true);

        // Log for debugging (only in development)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LooseGallery API Response: ' . print_r(array(
                'status' => $status_code,
                'body' => $response_body,
                'decoded' => $decoded_body
            ), true));
        }

        // Check for GraphQL errors first
        if (isset($decoded_body['errors']) && !empty($decoded_body['errors'])) {
            $error_messages = array_map(function($error) {
                return $error['message'] ?? 'Unknown error';
            }, $decoded_body['errors']);
            
            return array(
                'success' => false,
                'message' => implode(', ', $error_messages),
                'errors' => $decoded_body['errors'],
                'status_code' => $status_code
            );
        }

        // GraphQL success with data
        if ($status_code === 200 && isset($decoded_body['data'])) {
            return array(
                'success' => true,
                'data' => $decoded_body['data'],
                'status_code' => $status_code
            );
        }

        // Handle other status codes
        $error_message = sprintf('API request failed with status %d', $status_code);
        
        if (isset($decoded_body['error'])) {
            $error_message .= ': ' . $decoded_body['error'];
        } elseif (isset($decoded_body['message'])) {
            $error_message = $decoded_body['message'];
        } elseif (!empty($response_body)) {
            $error_message .= ': ' . substr($response_body, 0, 200);
        }

        return array(
            'success' => false,
            'message' => $error_message,
            'status_code' => $status_code,
            'raw_response' => $response_body
        );
    }

    /**
     * Validate design serial format
     * 
     * @param string $serial Design serial number
     * @return bool
     */
    public static function validate_serial($serial) {
        // Basic validation - adjust based on your actual serial format
        return !empty($serial) && strlen($serial) >= 5;
    }
}
