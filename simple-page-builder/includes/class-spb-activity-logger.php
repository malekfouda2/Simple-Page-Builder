<?php

if (!defined('ABSPATH')) {
    exit;
}

class SPB_Activity_Logger {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
    }
    
    public function log_request($data) {
        global $wpdb;
        
        $log_data = array(
            'api_key_id' => isset($data['api_key_id']) ? $data['api_key_id'] : null,
            'endpoint' => isset($data['endpoint']) ? sanitize_text_field($data['endpoint']) : '',
            'http_method' => isset($data['http_method']) ? sanitize_text_field($data['http_method']) : 'POST',
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'failed',
            'pages_created' => isset($data['pages_created']) ? intval($data['pages_created']) : 0,
            'response_time' => isset($data['response_time']) ? intval($data['response_time']) : 0,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'request_data' => isset($data['request_data']) ? json_encode($data['request_data']) : '',
            'response_data' => isset($data['response_data']) ? json_encode($data['response_data']) : '',
            'error_message' => isset($data['error_message']) ? sanitize_text_field($data['error_message']) : '',
            'created_date' => current_time('mysql')
        );
        
        $wpdb->insert(
            SPB_Database::get_activity_log_table(),
            $log_data
        );
        
        return $wpdb->insert_id;
    }
    
    public function log_created_page($page_id, $page_title, $page_url, $api_key_id, $activity_log_id = null) {
        global $wpdb;
        
        $wpdb->insert(
            SPB_Database::get_created_pages_table(),
            array(
                'page_id' => $page_id,
                'page_title' => $page_title,
                'page_url' => $page_url,
                'api_key_id' => $api_key_id,
                'activity_log_id' => $activity_log_id,
                'created_date' => current_time('mysql')
            )
        );
    }
    
    public function get_activity_logs($filters = array()) {
        global $wpdb;
        
        $table = SPB_Database::get_activity_log_table();
        $keys_table = SPB_Database::get_api_keys_table();
        
        $where = array('1=1');
        $where_values = array();
        
        if (!empty($filters['status'])) {
            $where[] = 'al.status = %s';
            $where_values[] = $filters['status'];
        }
        
        if (!empty($filters['api_key_id'])) {
            $where[] = 'al.api_key_id = %d';
            $where_values[] = $filters['api_key_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'al.created_date >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'al.created_date <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT al.*, ak.key_name, ak.api_key_preview 
                FROM $table al 
                LEFT JOIN $keys_table ak ON al.api_key_id = ak.id 
                WHERE $where_clause 
                ORDER BY al.created_date DESC";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    public function get_created_pages($filters = array()) {
        global $wpdb;
        
        $table = SPB_Database::get_created_pages_table();
        $keys_table = SPB_Database::get_api_keys_table();
        
        $sql = "SELECT cp.*, ak.key_name 
                FROM $table cp 
                LEFT JOIN $keys_table ak ON cp.api_key_id = ak.id 
                ORDER BY cp.created_date DESC";
        
        return $wpdb->get_results($sql);
    }
    
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
    }
}
