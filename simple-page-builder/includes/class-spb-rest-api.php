<?php

if (!defined('ABSPATH')) {
    exit;
}

class SPB_REST_API {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    public function register_routes() {
        register_rest_route('pagebuilder/v1', '/create-pages', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_pages'),
            'permission_callback' => array($this, 'check_api_key_permission'),
        ));
    }
    
    public function check_api_key_permission($request) {
        $api_enabled = get_option('spb_api_enabled', 1);
        
        if (!$api_enabled) {
            return new WP_Error(
                'api_disabled',
                'API access is currently disabled',
                array('status' => 503)
            );
        }
        
        $api_key = $request->get_header('X-API-Key');
        
        if (!$api_key) {
            $api_key = $request->get_param('api_key');
        }
        
        if (empty($api_key)) {
            return new WP_Error(
                'missing_api_key',
                'API key is required. Please provide it in the X-API-Key header.',
                array('status' => 401)
            );
        }
        
        $api_keys_manager = SPB_API_Keys::get_instance();
        $key_data = $api_keys_manager->validate_api_key($api_key);
        
        if (!$key_data) {
            return new WP_Error(
                'invalid_api_key',
                'Invalid or expired API key',
                array('status' => 401)
            );
        }
        
        $rate_limiter = SPB_Rate_Limiter::get_instance();
        if (!$rate_limiter->check_rate_limit($key_data->id)) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                array('status' => 429)
            );
        }
        
        $request->set_param('_api_key_data', $key_data);
        
        return true;
    }
    
    public function create_pages($request) {
        $start_time = microtime(true);
        
        $api_key_data = $request->get_param('_api_key_data');
        $pages_data = $request->get_param('pages');
        
        if (empty($pages_data) || !is_array($pages_data)) {
            $error_response = new WP_Error(
                'invalid_request',
                'The "pages" parameter is required and must be an array',
                array('status' => 400)
            );
            
            $this->log_failed_request($api_key_data, $request, $error_response, $start_time);
            
            return $error_response;
        }
        
        $created_pages = array();
        $errors = array();
        
        foreach ($pages_data as $index => $page_data) {
            if (empty($page_data['title'])) {
                $errors[] = "Page #{$index}: Title is required";
                continue;
            }
            
            $page_args = array(
                'post_title' => sanitize_text_field($page_data['title']),
                'post_content' => isset($page_data['content']) ? wp_kses_post($page_data['content']) : '',
                'post_status' => isset($page_data['status']) ? sanitize_text_field($page_data['status']) : 'publish',
                'post_type' => 'page',
                'post_author' => isset($page_data['author_id']) ? intval($page_data['author_id']) : 1
            );
            
            if (isset($page_data['slug'])) {
                $page_args['post_name'] = sanitize_title($page_data['slug']);
            }
            
            if (isset($page_data['parent_id'])) {
                $page_args['post_parent'] = intval($page_data['parent_id']);
            }
            
            if (isset($page_data['template'])) {
                $page_args['page_template'] = sanitize_text_field($page_data['template']);
            }
            
            $page_id = wp_insert_post($page_args, true);
            
            if (is_wp_error($page_id)) {
                $errors[] = "Page #{$index} ({$page_data['title']}): " . $page_id->get_error_message();
                continue;
            }
            
            if (isset($page_data['meta']) && is_array($page_data['meta'])) {
                foreach ($page_data['meta'] as $meta_key => $meta_value) {
                    update_post_meta($page_id, sanitize_key($meta_key), $meta_value);
                }
            }
            
            if (isset($page_data['featured_image_url'])) {
                $this->set_featured_image_from_url($page_id, $page_data['featured_image_url']);
            }
            
            $page_url = get_permalink($page_id);
            $page_title = get_the_title($page_id);
            
            $created_pages[] = array(
                'id' => $page_id,
                'title' => $page_title,
                'url' => $page_url,
                'status' => get_post_status($page_id)
            );
        }
        
        $response_time = round((microtime(true) - $start_time) * 1000);
        
        $activity_logger = SPB_Activity_Logger::get_instance();
        $log_id = $activity_logger->log_request(array(
            'api_key_id' => $api_key_data->id,
            'endpoint' => '/wp-json/pagebuilder/v1/create-pages',
            'http_method' => 'POST',
            'status' => 'success',
            'pages_created' => count($created_pages),
            'response_time' => $response_time,
            'request_data' => array('pages_count' => count($pages_data)),
            'response_data' => array('created' => count($created_pages), 'errors' => count($errors))
        ));
        
        foreach ($created_pages as $page) {
            $activity_logger->log_created_page(
                $page['id'],
                $page['title'],
                $page['url'],
                $api_key_data->id,
                $log_id
            );
        }
        
        if (!empty($created_pages)) {
            $webhook = SPB_Webhook::get_instance();
            $webhook->send_notification(array(
                'api_key_name' => $api_key_data->key_name,
                'total_pages' => count($created_pages),
                'pages' => $created_pages
            ));
        }
        
        $response = array(
            'success' => true,
            'message' => sprintf(
                '%d page(s) created successfully%s',
                count($created_pages),
                !empty($errors) ? ' with ' . count($errors) . ' error(s)' : ''
            ),
            'data' => array(
                'created_pages' => $created_pages,
                'total_created' => count($created_pages),
                'total_requested' => count($pages_data),
                'errors' => $errors,
                'response_time_ms' => $response_time
            )
        );
        
        return rest_ensure_response($response);
    }
    
    private function log_failed_request($api_key_data, $request, $error, $start_time) {
        $response_time = round((microtime(true) - $start_time) * 1000);
        
        $activity_logger = SPB_Activity_Logger::get_instance();
        $activity_logger->log_request(array(
            'api_key_id' => $api_key_data ? $api_key_data->id : null,
            'endpoint' => '/wp-json/pagebuilder/v1/create-pages',
            'http_method' => 'POST',
            'status' => 'failed',
            'pages_created' => 0,
            'response_time' => $response_time,
            'request_data' => $request->get_params(),
            'error_message' => is_wp_error($error) ? $error->get_error_message() : 'Unknown error'
        ));
    }
    
    private function set_featured_image_from_url($post_id, $image_url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $sanitized_url = esc_url_raw($image_url);
        
        if (empty($sanitized_url)) {
            return;
        }
        
        $image_id = media_sideload_image($sanitized_url, $post_id, null, 'id');
        
        if (!is_wp_error($image_id)) {
            set_post_thumbnail($post_id, $image_id);
        }
    }
}
