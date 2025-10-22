<?php
/**
 * Plugin Name: Simple Page Builder
 * Plugin URI: https://github.com/malekfouda2/simple-page-builder
 * Description: Create bulk pages via secure REST API with advanced authentication and webhook notifications
 * Version: 1.0.0
 * Author: Malek Fouda
 * Author URI: https://malekfouda.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-page-builder
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SPB_VERSION', '1.0.0');
define('SPB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPB_PLUGIN_FILE', __FILE__);

class Simple_Page_Builder {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once SPB_PLUGIN_DIR . 'includes/class-spb-database.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-spb-api-keys.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-spb-rate-limiter.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-spb-activity-logger.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-spb-webhook.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-spb-rest-api.php';
        require_once SPB_PLUGIN_DIR . 'admin/class-spb-admin.php';
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        SPB_Database::get_instance();
        SPB_API_Keys::get_instance();
        SPB_Rate_Limiter::get_instance();
        SPB_Activity_Logger::get_instance();
        SPB_Webhook::get_instance();
        SPB_REST_API::get_instance();
        
        if (is_admin()) {
            SPB_Admin::get_instance();
        }
    }
    
    public function activate() {
        SPB_Database::create_tables();
        
        $default_settings = array(
            'spb_webhook_url' => '',
            'spb_webhook_secret' => wp_generate_password(32, false),
            'spb_rate_limit' => 100,
            'spb_api_enabled' => 1,
            'spb_default_expiration' => 'never'
        );
        
        foreach ($default_settings as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
}

function SPB() {
    return Simple_Page_Builder::get_instance();
}

SPB();
