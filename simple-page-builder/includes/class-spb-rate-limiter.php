<?php

if (!defined('ABSPATH')) {
    exit;
}

class SPB_Rate_Limiter {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
    }
    
    public function check_rate_limit($api_key_id) {
        $rate_limit = get_option('spb_rate_limit', 100);
        
        $transient_key = 'spb_rate_limit_' . $api_key_id;
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            set_transient($transient_key, 1, HOUR_IN_SECONDS);
            return true;
        }
        
        if ($requests >= $rate_limit) {
            return false;
        }
        
        set_transient($transient_key, $requests + 1, HOUR_IN_SECONDS);
        return true;
    }
    
    public function get_remaining_requests($api_key_id) {
        $rate_limit = get_option('spb_rate_limit', 100);
        $transient_key = 'spb_rate_limit_' . $api_key_id;
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            return $rate_limit;
        }
        
        return max(0, $rate_limit - $requests);
    }
    
    public function reset_rate_limit($api_key_id) {
        $transient_key = 'spb_rate_limit_' . $api_key_id;
        delete_transient($transient_key);
    }
}
