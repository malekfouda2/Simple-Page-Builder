<?php

if (!defined('ABSPATH')) {
    exit;
}

class SPB_API_Keys {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
    }
    
    public function generate_api_key($key_name, $expiration_date = null) {
        global $wpdb;
        
        $api_key = 'spb_' . bin2hex(random_bytes(32));
        $api_key_hash = wp_hash_password($api_key);
        $api_key_preview = substr($api_key, 0, 12) . '***';
        
        $data = array(
            'key_name' => sanitize_text_field($key_name),
            'api_key_hash' => $api_key_hash,
            'api_key_preview' => $api_key_preview,
            'status' => 'active',
            'permissions' => json_encode(array('create_pages')),
            'created_date' => current_time('mysql'),
            'expiration_date' => $expiration_date ? date('Y-m-d H:i:s', strtotime($expiration_date)) : null,
            'created_by' => get_current_user_id()
        );
        
        $wpdb->insert(
            SPB_Database::get_api_keys_table(),
            $data
        );
        
        if ($wpdb->insert_id) {
            return array(
                'success' => true,
                'api_key' => $api_key,
                'key_id' => $wpdb->insert_id,
                'message' => 'API key generated successfully'
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Failed to generate API key'
        );
    }
    
    public function validate_api_key($api_key) {
        global $wpdb;
        
        if (empty($api_key)) {
            return false;
        }
        
        $table = SPB_Database::get_api_keys_table();
        $all_keys = $wpdb->get_results("SELECT * FROM $table WHERE status = 'active'");
        
        foreach ($all_keys as $key_record) {
            if (wp_check_password($api_key, $key_record->api_key_hash)) {
                if ($key_record->expiration_date && strtotime($key_record->expiration_date) < time()) {
                    return false;
                }
                
                $this->update_last_used($key_record->id);
                
                return $key_record;
            }
        }
        
        return false;
    }
    
    public function revoke_api_key($key_id) {
        global $wpdb;
        
        return $wpdb->update(
            SPB_Database::get_api_keys_table(),
            array('status' => 'revoked'),
            array('id' => $key_id),
            array('%s'),
            array('%d')
        );
    }
    
    public function update_last_used($key_id) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "UPDATE " . SPB_Database::get_api_keys_table() . " 
            SET last_used = %s, request_count = request_count + 1 
            WHERE id = %d",
            current_time('mysql'),
            $key_id
        ));
    }
    
    public function get_all_keys() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM " . SPB_Database::get_api_keys_table() . " 
            ORDER BY created_date DESC"
        );
    }
    
    public function get_key_by_id($key_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . SPB_Database::get_api_keys_table() . " WHERE id = %d",
            $key_id
        ));
    }
    
    public function delete_api_key($key_id) {
        global $wpdb;
        
        return $wpdb->delete(
            SPB_Database::get_api_keys_table(),
            array('id' => $key_id),
            array('%d')
        );
    }
}
