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

        // Use getImageDataList query to test connection (doesn't require parameters)
        $query = '
            query {
                getImageDataList(itemsPerPage: 1, page: 0) {
                    page
                    totalItems
                }
            }
        ';

        $response = $this->make_graphql_request($query);

        if ($response['success'] && isset($response['data']['getImageDataList'])) {
            return array(
                'success' => true,
                'message' => __('API connection successful', 'loosegallery-woocommerce'),
                'total_items' => $response['data']['getImageDataList']['totalItems'] ?? 0
            );
        }

        return array(
            'success' => false,
            'message' => $response['message'] ?? __('Failed to connect to API', 'loosegallery-woocommerce')
        );
    }

    /**
     * Get domain information
     * (Note: This API doesn't provide domain endpoints, so we'll use product list as proxy)
     */
    public function get_domain_info() {
        if (empty($this->api_key)) {
            return array('success' => false);
        }

        // Use getImageDataList as a connection test
        $query = '
            query {
                getImageDataList(itemsPerPage: 1, page: 0) {
                    page
                    totalItems
                }
            }
        ';

        $response = $this->make_graphql_request($query);

        if ($response['success'] && isset($response['data']['getImageDataList'])) {
            return array(
                'success' => true,
                'total_products' => $response['data']['getImageDataList']['totalItems'] ?? 0
            );
        }

        return array('success' => false);
    }

    /**
     * Get design preview URL
     * 
     * @param string $product_serial The product serial number (design ID)
     * @param int $width Width in pixels (default 800)
     * @param int $height Height in pixels (default 800)
     * @return array
     */
    public function get_design_preview($product_serial, $width = 800, $height = 800) {
        if (empty($this->api_key) || empty($product_serial)) {
            return array(
                'success' => false,
                'message' => __('API key and product serial are required', 'loosegallery-woocommerce')
            );
        }

        $query = '
            query GetProduct($productSerial: String!) {
                getProduct(productSerial: $productSerial) {
                    title
                    description
                    imageUrl
                }
            }
        ';

        $variables = array(
            'productSerial' => $product_serial
        );

        $response = $this->make_graphql_request($query, $variables);

        if ($response['success'] && isset($response['data']['getProduct'])) {
            return array(
                'success' => true,
                'preview_url' => $response['data']['getProduct']['imageUrl'] ?? '',
                'thumbnail_url' => $response['data']['getProduct']['imageUrl'] ?? '',
                'title' => $response['data']['getProduct']['title'] ?? '',
                'description' => $response['data']['getProduct']['description'] ?? ''
            );
        }

        return array(
            'success' => false,
            'message' => $response['message'] ?? __('Failed to get design preview', 'loosegallery-woocommerce')
        );
    }

    /**
     * Lock design (prevent further editing)
     * Note: This API doesn't support locking, so we'll just return success
     * Locking should be handled on the plugin side via order meta
     * 
     * @param string $product_serial The product serial number
     * @return array
     */
    public function lock_design($product_serial) {
        // API doesn't support locking - handle locally
        return array(
            'success' => true,
            'message' => __('Design marked as locked locally', 'loosegallery-woocommerce')
        );
    }

    /**
     * Get design information
     * 
     * @param string $product_serial The product serial number
     * @return array
     */
    public function get_design_info($product_serial) {
        if (empty($this->api_key) || empty($product_serial)) {
            return array('success' => false);
        }

        $query = '
            query GetProduct($productSerial: String!) {
                getProduct(productSerial: $productSerial) {
                    title
                    description
                    imageUrl
                }
            }
        ';

        $variables = array(
            'productSerial' => $product_serial
        );

        $response = $this->make_graphql_request($query, $variables);

        if ($response['success'] && isset($response['data']['getProduct'])) {
            return array(
                'success' => true,
                'design_data' => $response['data']['getProduct'] ?? array(),
                'title' => $response['data']['getProduct']['title'] ?? '',
                'description' => $response['data']['getProduct']['description'] ?? '',
                'preview_url' => $response['data']['getProduct']['imageUrl'] ?? ''
            );
        }

        return array('success' => false);
    }

    /**
     * Create/request high-resolution image for a product
     * 
     * @param string $product_serial The product serial number
     * @param int $width Width in pixels (max 12000)
     * @param int $height Height in pixels (max 12000)
     * @param string $file_extension File extension (.png, .jpg, .jpeg, .pdf)
     * @param int $dpi DPI (max 300, default 300)
     * @param string $group_id Optional group identifier
     * @return array
     */
    public function create_image($product_serial, $width = 3000, $height = 3000, $file_extension = '.png', $dpi = 300, $group_id = null) {
        if (empty($this->api_key) || empty($product_serial)) {
            return array(
                'success' => false,
                'message' => __('API key and product serial are required', 'loosegallery-woocommerce')
            );
        }

        $mutation = '
            mutation CreateImage($productSerial: String!, $width: Int!, $height: Int!, $fileExtension: String!, $dpi: Int, $groupId: String) {
                createImage(
                    productSerial: $productSerial
                    width: $width
                    height: $height
                    fileExtension: $fileExtension
                    dpi: $dpi
                    groupId: $groupId
                )
            }
        ';

        $variables = array(
            'productSerial' => $product_serial,
            'width' => $width,
            'height' => $height,
            'fileExtension' => $file_extension,
            'dpi' => $dpi
        );

        if ($group_id) {
            $variables['groupId'] = $group_id;
        }

        $response = $this->make_graphql_request($mutation, $variables);

        if ($response['success'] && isset($response['data']['createImage'])) {
            return array(
                'success' => true,
                'message' => __('Image creation requested successfully', 'loosegallery-woocommerce')
            );
        }

        return array(
            'success' => false,
            'message' => $response['message'] ?? __('Failed to create image', 'loosegallery-woocommerce')
        );
    }

    /**
     * Get image for a product (checks if image exists or creates it)
     * 
     * @param string $product_serial The product serial number
     * @param int $width Width in pixels (max 12000)
     * @param int $height Height in pixels (max 12000)
     * @param string $file_extension File extension (.png, .jpg, .jpeg, .pdf)
     * @param int $dpi DPI (max 300, default 300)
     * @return array
     */
    public function get_image($product_serial, $width = 3000, $height = 3000, $file_extension = '.png', $dpi = 300) {
        if (empty($this->api_key) || empty($product_serial)) {
            return array(
                'success' => false,
                'message' => __('API key and product serial are required', 'loosegallery-woocommerce')
            );
        }

        $query = '
            query GetImage($productSerial: String!, $width: Int!, $height: Int!, $fileExtension: String!, $dpi: Int) {
                getImage(
                    productSerial: $productSerial
                    width: $width
                    height: $height
                    fileExtension: $fileExtension
                    dpi: $dpi
                ) {
                    imageUrl
                    status
                    createProgressPercentage
                    requestedDatetime
                }
            }
        ';

        $variables = array(
            'productSerial' => $product_serial,
            'width' => $width,
            'height' => $height,
            'fileExtension' => $file_extension,
            'dpi' => $dpi
        );

        $response = $this->make_graphql_request($query, $variables);

        if ($response['success'] && isset($response['data']['getImage'])) {
            $image_data = $response['data']['getImage'];
            return array(
                'success' => true,
                'image_url' => $image_data['imageUrl'] ?? '',
                'status' => $image_data['status'] ?? 'undefined',
                'progress' => $image_data['createProgressPercentage'] ?? 0,
                'requested' => $image_data['requestedDatetime'] ?? ''
            );
        }

        return array(
            'success' => false,
            'message' => $response['message'] ?? __('Failed to get image', 'loosegallery-woocommerce')
        );
    }

    /**
     * Generate editor URL for starting a design
     * 
     * @param string $domain_id Domain ID
     * @param string $product_serial Product serial number (template ID)
     * @param string $return_url URL to return to after editing (optional, ignored - editor handles it)
     * @param array $additional_params Additional query parameters
     * @return string
     */
    public function get_editor_url($domain_id, $product_serial, $return_url = '', $additional_params = array()) {
        // Editor URL with correct path
        $editor_base_url = 'https://editor.loosegallery.com/editor/';

        // Use correct parameter names for LooseGallery editor
        // 'dom' = domain ID, 'p' = product serial (template)
        // 'productId' = WooCommerce product ID (editor will return this back to us)
        $params = array_merge(array(
            'dom' => $domain_id,
            'p' => $product_serial
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
                'Authorization' => $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30,
            'sslverify' => true,
            'data_format' => 'body'  // Prevent WordPress from URL-encoding the body
        );

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LooseGallery API Request: ' . print_r(array(
                'url' => $url,
                'query' => $query,
                'variables' => $variables,
                'api_key_length' => strlen($this->api_key),
                'api_key_first_chars' => substr($this->api_key, 0, 10) . '...',
                'body_json' => json_encode($body),
                'headers' => $args['headers']
            ), true));
        }

        // Make the request
        $response = wp_remote_request($url, $args);

        // Debug response
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LooseGallery API Response RAW: ' . print_r(array(
                'status' => wp_remote_retrieve_response_code($response),
                'headers' => wp_remote_retrieve_headers($response),
                'body' => wp_remote_retrieve_body($response)
            ), true));
        }

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
