<?php

if (!defined('ABSPATH')) {
    exit;
}

class SPB_Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
    }
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $api_keys_table = $wpdb->prefix . 'spb_api_keys';
        $activity_log_table = $wpdb->prefix . 'spb_activity_log';
        $created_pages_table = $wpdb->prefix . 'spb_created_pages';
        
        $sql_api_keys = "CREATE TABLE IF NOT EXISTS $api_keys_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            key_name varchar(255) NOT NULL,
            api_key_hash varchar(255) NOT NULL,
            api_key_preview varchar(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            permissions text,
            created_date datetime NOT NULL,
            expiration_date datetime DEFAULT NULL,
            last_used datetime DEFAULT NULL,
            request_count bigint(20) unsigned DEFAULT 0,
            created_by bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY api_key_hash (api_key_hash),
            KEY status (status),
            KEY expiration_date (expiration_date)
        ) $charset_collate;";
        
        $sql_activity_log = "CREATE TABLE IF NOT EXISTS $activity_log_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            api_key_id bigint(20) unsigned DEFAULT NULL,
            endpoint varchar(255) NOT NULL,
            http_method varchar(10) NOT NULL,
            status varchar(20) NOT NULL,
            pages_created int(10) unsigned DEFAULT 0,
            response_time int(10) unsigned DEFAULT 0,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            request_data longtext,
            response_data longtext,
            error_message text,
            created_date datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY api_key_id (api_key_id),
            KEY status (status),
            KEY created_date (created_date),
            KEY endpoint (endpoint)
        ) $charset_collate;";
        
        $sql_created_pages = "CREATE TABLE IF NOT EXISTS $created_pages_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            page_id bigint(20) unsigned NOT NULL,
            page_title varchar(255) NOT NULL,
            page_url text NOT NULL,
            api_key_id bigint(20) unsigned DEFAULT NULL,
            activity_log_id bigint(20) unsigned DEFAULT NULL,
            created_date datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY page_id (page_id),
            KEY api_key_id (api_key_id),
            KEY created_date (created_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_api_keys);
        dbDelta($sql_activity_log);
        dbDelta($sql_created_pages);
    }
    
    public static function get_api_keys_table() {
        global $wpdb;
        return $wpdb->prefix . 'spb_api_keys';
    }
    
    public static function get_activity_log_table() {
        global $wpdb;
        return $wpdb->prefix . 'spb_activity_log';
    }
    
    public static function get_created_pages_table() {
        global $wpdb;
        return $wpdb->prefix . 'spb_created_pages';
    }
}
