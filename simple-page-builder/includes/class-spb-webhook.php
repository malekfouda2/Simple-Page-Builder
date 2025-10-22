<?php

if (!defined('ABSPATH')) {
    exit;
}

class SPB_Webhook {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
    }
    
    public function send_notification($data) {
        $webhook_url = get_option('spb_webhook_url', '');
        
        if (empty($webhook_url)) {
            return array(
                'success' => false,
                'message' => 'No webhook URL configured'
            );
        }
        
        $payload = array(
            'event' => 'pages_created',
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'request_id' => 'req_' . bin2hex(random_bytes(8)),
            'api_key_name' => isset($data['api_key_name']) ? $data['api_key_name'] : 'Unknown',
            'total_pages' => isset($data['total_pages']) ? $data['total_pages'] : 0,
            'pages' => isset($data['pages']) ? $data['pages'] : array()
        );
        
        $signature = $this->generate_signature($payload);
        
        $args = array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature,
                'User-Agent' => 'Simple-Page-Builder-Webhook/1.0'
            ),
            'body' => json_encode($payload)
        );
        
        $max_retries = 2;
        $retry_count = 0;
        $success = false;
        $last_error = '';
        
        while ($retry_count <= $max_retries && !$success) {
            $response = wp_remote_post($webhook_url, $args);
            
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code >= 200 && $response_code < 300) {
                    $success = true;
                } else {
                    $last_error = "HTTP {$response_code}: " . wp_remote_retrieve_body($response);
                }
            } else {
                $last_error = $response->get_error_message();
            }
            
            if (!$success && $retry_count < $max_retries) {
                $wait_time = pow(2, $retry_count);
                sleep($wait_time);
            }
            
            $retry_count++;
        }
        
        if ($success) {
            return array(
                'success' => true,
                'message' => 'Webhook sent successfully',
                'payload' => $payload
            );
        } else {
            return array(
                'success' => false,
                'message' => "Webhook failed after {$max_retries} retries: {$last_error}",
                'payload' => $payload
            );
        }
    }
    
    private function generate_signature($payload) {
        $webhook_secret = get_option('spb_webhook_secret', '');
        $payload_json = json_encode($payload);
        return hash_hmac('sha256', $payload_json, $webhook_secret);
    }
    
    public function verify_signature($payload, $signature) {
        $expected_signature = $this->generate_signature($payload);
        return hash_equals($expected_signature, $signature);
    }
}
