<?php

if (!defined('ABSPATH')) {
    exit;
}

class SPB_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('wp_ajax_spb_generate_api_key', array($this, 'ajax_generate_api_key'));
        add_action('wp_ajax_spb_revoke_api_key', array($this, 'ajax_revoke_api_key'));
        add_action('wp_ajax_spb_export_logs', array($this, 'ajax_export_logs'));
    }
    
    public function add_admin_menu() {
        add_management_page(
            'Simple Page Builder',
            'Page Builder',
            'manage_options',
            'simple-page-builder',
            array($this, 'render_admin_page')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'tools_page_simple-page-builder') {
            return;
        }
        
        wp_enqueue_style('spb-admin-css', SPB_PLUGIN_URL . 'assets/css/admin.css', array(), SPB_VERSION);
        wp_enqueue_script('spb-admin-js', SPB_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SPB_VERSION, true);
        
        wp_localize_script('spb-admin-js', 'spbAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spb_admin_nonce')
        ));
    }
    
    public function handle_admin_actions() {
        if (!isset($_POST['spb_action']) || !current_user_can('manage_options')) {
            return;
        }
        
        check_admin_referer('spb_admin_action');
        
        $action = sanitize_text_field($_POST['spb_action']);
        
        switch ($action) {
            case 'save_settings':
                $this->save_settings();
                break;
        }
    }
    
    public function ajax_generate_api_key() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $key_name = isset($_POST['key_name']) ? sanitize_text_field($_POST['key_name']) : '';
        $expiration_date = isset($_POST['expiration_date']) ? sanitize_text_field($_POST['expiration_date']) : null;
        
        if (empty($key_name)) {
            wp_send_json_error(array('message' => 'Key name is required'));
        }
        
        $api_keys = SPB_API_Keys::get_instance();
        $result = $api_keys->generate_api_key($key_name, $expiration_date);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_revoke_api_key() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $key_id = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;
        
        if (!$key_id) {
            wp_send_json_error(array('message' => 'Invalid key ID'));
        }
        
        $api_keys = SPB_API_Keys::get_instance();
        $result = $api_keys->revoke_api_key($key_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'API key revoked successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to revoke API key'));
        }
    }
    
    public function ajax_export_logs() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $activity_logger = SPB_Activity_Logger::get_instance();
        $logs = $activity_logger->get_activity_logs();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="spb-activity-logs-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, array('Date', 'API Key', 'Endpoint', 'Method', 'Status', 'Pages Created', 'Response Time (ms)', 'IP Address'));
        
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log->created_date,
                $log->key_name ? $log->key_name : 'Unknown',
                $log->endpoint,
                $log->http_method,
                $log->status,
                $log->pages_created,
                $log->response_time,
                $log->ip_address
            ));
        }
        
        fclose($output);
        exit;
    }
    
    private function save_settings() {
        $settings = array(
            'spb_webhook_url' => isset($_POST['webhook_url']) ? esc_url_raw($_POST['webhook_url']) : '',
            'spb_rate_limit' => isset($_POST['rate_limit']) ? intval($_POST['rate_limit']) : 100,
            'spb_api_enabled' => isset($_POST['api_enabled']) ? 1 : 0,
            'spb_default_expiration' => isset($_POST['default_expiration']) ? sanitize_text_field($_POST['default_expiration']) : 'never'
        );
        
        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
        
        add_settings_error('spb_messages', 'spb_message', 'Settings saved successfully', 'updated');
    }
    
    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'api-keys';
        ?>
        <div class="wrap spb-admin-wrap">
            <h1>Simple Page Builder</h1>
            
            <?php settings_errors('spb_messages'); ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=simple-page-builder&tab=api-keys" class="nav-tab <?php echo $active_tab === 'api-keys' ? 'nav-tab-active' : ''; ?>">API Keys</a>
                <a href="?page=simple-page-builder&tab=activity-log" class="nav-tab <?php echo $active_tab === 'activity-log' ? 'nav-tab-active' : ''; ?>">Activity Log</a>
                <a href="?page=simple-page-builder&tab=created-pages" class="nav-tab <?php echo $active_tab === 'created-pages' ? 'nav-tab-active' : ''; ?>">Created Pages</a>
                <a href="?page=simple-page-builder&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
                <a href="?page=simple-page-builder&tab=documentation" class="nav-tab <?php echo $active_tab === 'documentation' ? 'nav-tab-active' : ''; ?>">Documentation</a>
            </h2>
            
            <div class="spb-tab-content">
                <?php
                switch ($active_tab) {
                    case 'api-keys':
                        $this->render_api_keys_tab();
                        break;
                    case 'activity-log':
                        $this->render_activity_log_tab();
                        break;
                    case 'created-pages':
                        $this->render_created_pages_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'documentation':
                        $this->render_documentation_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_api_keys_tab() {
        $api_keys = SPB_API_Keys::get_instance();
        $keys = $api_keys->get_all_keys();
        ?>
        <div class="spb-section">
            <h2>Generate New API Key</h2>
            <div class="spb-card">
                <form id="spb-generate-key-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="key_name">Key Name <span class="required">*</span></label></th>
                            <td>
                                <input type="text" id="key_name" name="key_name" class="regular-text" required>
                                <p class="description">A friendly name to identify this API key (e.g., "Production Server", "Mobile App")</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="expiration_date">Expiration Date</label></th>
                            <td>
                                <input type="date" id="expiration_date" name="expiration_date">
                                <p class="description">Optional. Leave empty for no expiration.</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Generate API Key</button>
                    </p>
                </form>
                
                <div id="spb-new-key-display" style="display:none;" class="notice notice-success">
                    <h3>API Key Generated Successfully!</h3>
                    <p><strong>⚠️ Important:</strong> Copy this API key now. You won't be able to see it again!</p>
                    <div class="spb-key-display">
                        <code id="spb-api-key-value"></code>
                        <button type="button" class="button" id="spb-copy-key">Copy</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="spb-section">
            <h2>API Keys</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Key Name</th>
                        <th>Key Preview</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Expires</th>
                        <th>Last Used</th>
                        <th>Requests</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($keys)): ?>
                        <tr>
                            <td colspan="8">No API keys found. Generate your first API key above.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($keys as $key): ?>
                            <tr>
                                <td><strong><?php echo esc_html($key->key_name); ?></strong></td>
                                <td><code><?php echo esc_html($key->api_key_preview); ?></code></td>
                                <td>
                                    <?php if ($key->status === 'active'): ?>
                                        <span class="spb-badge spb-badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="spb-badge spb-badge-danger">Revoked</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($key->created_date))); ?></td>
                                <td><?php echo $key->expiration_date ? esc_html(date('Y-m-d', strtotime($key->expiration_date))) : 'Never'; ?></td>
                                <td><?php echo $key->last_used ? esc_html(date('Y-m-d H:i', strtotime($key->last_used))) : 'Never'; ?></td>
                                <td><?php echo number_format($key->request_count); ?></td>
                                <td>
                                    <?php if ($key->status === 'active'): ?>
                                        <button class="button button-small spb-revoke-key" data-key-id="<?php echo esc_attr($key->id); ?>">Revoke</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function render_activity_log_tab() {
        $activity_logger = SPB_Activity_Logger::get_instance();
        $logs = $activity_logger->get_activity_logs();
        ?>
        <div class="spb-section">
            <div class="spb-section-header">
                <h2>API Activity Log</h2>
                <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                    <input type="hidden" name="action" value="spb_export_logs">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('spb_admin_nonce'); ?>">
                    <button type="submit" class="button">Export as CSV</button>
                </form>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>API Key</th>
                        <th>Endpoint</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Pages Created</th>
                        <th>Response Time</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="8">No activity logged yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log->created_date))); ?></td>
                                <td>
                                    <?php if ($log->key_name): ?>
                                        <strong><?php echo esc_html($log->key_name); ?></strong><br>
                                        <small><code><?php echo esc_html($log->api_key_preview); ?></code></small>
                                    <?php else: ?>
                                        <em>Unknown</em>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo esc_html($log->endpoint); ?></code></td>
                                <td><span class="spb-method"><?php echo esc_html($log->http_method); ?></span></td>
                                <td>
                                    <?php if ($log->status === 'success'): ?>
                                        <span class="spb-badge spb-badge-success">Success</span>
                                    <?php else: ?>
                                        <span class="spb-badge spb-badge-danger">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo intval($log->pages_created); ?></td>
                                <td><?php echo intval($log->response_time); ?>ms</td>
                                <td><?php echo esc_html($log->ip_address); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function render_created_pages_tab() {
        $activity_logger = SPB_Activity_Logger::get_instance();
        $pages = $activity_logger->get_created_pages();
        ?>
        <div class="spb-section">
            <h2>Pages Created via API</h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Page Title</th>
                        <th>URL</th>
                        <th>Created By</th>
                        <th>Created Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pages)): ?>
                        <tr>
                            <td colspan="4">No pages created via API yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url(get_edit_post_link($page->page_id)); ?>" target="_blank">
                                            <?php echo esc_html($page->page_title); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($page->page_url); ?>" target="_blank">
                                        <?php echo esc_html($page->page_url); ?>
                                    </a>
                                </td>
                                <td><?php echo $page->key_name ? esc_html($page->key_name) : '<em>Unknown</em>'; ?></td>
                                <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($page->created_date))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function render_settings_tab() {
        $webhook_url = get_option('spb_webhook_url', '');
        $webhook_secret = get_option('spb_webhook_secret', '');
        $rate_limit = get_option('spb_rate_limit', 100);
        $api_enabled = get_option('spb_api_enabled', 1);
        $default_expiration = get_option('spb_default_expiration', 'never');
        ?>
        <div class="spb-section">
            <h2>Settings</h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('spb_admin_action'); ?>
                <input type="hidden" name="spb_action" value="save_settings">
                
                <table class="form-table">
                    <tr>
                        <th><label for="webhook_url">Webhook URL</label></th>
                        <td>
                            <input type="url" id="webhook_url" name="webhook_url" value="<?php echo esc_attr($webhook_url); ?>" class="regular-text">
                            <p class="description">URL to receive notifications when pages are created</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Webhook Secret</label></th>
                        <td>
                            <code><?php echo esc_html($webhook_secret); ?></code>
                            <p class="description">Use this secret to verify webhook signatures (HMAC-SHA256)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rate_limit">Rate Limit (per hour)</label></th>
                        <td>
                            <input type="number" id="rate_limit" name="rate_limit" value="<?php echo esc_attr($rate_limit); ?>" min="1" max="10000">
                            <p class="description">Maximum requests per API key per hour</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="api_enabled">Enable API Access</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="api_enabled" name="api_enabled" value="1" <?php checked($api_enabled, 1); ?>>
                                Enable REST API endpoints
                            </label>
                            <p class="description">Disable to prevent all API access globally</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="default_expiration">Default Key Expiration</label></th>
                        <td>
                            <select id="default_expiration" name="default_expiration">
                                <option value="never" <?php selected($default_expiration, 'never'); ?>>Never</option>
                                <option value="30" <?php selected($default_expiration, '30'); ?>>30 days</option>
                                <option value="60" <?php selected($default_expiration, '60'); ?>>60 days</option>
                                <option value="90" <?php selected($default_expiration, '90'); ?>>90 days</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">Save Settings</button>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function render_documentation_tab() {
        $site_url = get_site_url();
        $api_endpoint = $site_url . '/wp-json/pagebuilder/v1/create-pages';
        $webhook_secret = get_option('spb_webhook_secret', '');
        ?>
        <div class="spb-section spb-documentation">
            <h2>API Documentation</h2>
            
            <div class="spb-card">
                <h3>Authentication</h3>
                <p>All API requests must include a valid API key in the request header:</p>
                <pre><code>X-API-Key: your_api_key_here</code></pre>
                <p>You can generate API keys in the <a href="?page=simple-page-builder&tab=api-keys">API Keys tab</a>.</p>
            </div>
            
            <div class="spb-card">
                <h3>Endpoint: Create Pages</h3>
                <p><strong>URL:</strong> <code><?php echo esc_html($api_endpoint); ?></code></p>
                <p><strong>Method:</strong> POST</p>
                <p><strong>Content-Type:</strong> application/json</p>
                
                <h4>Request Example (cURL):</h4>
                <pre><code>curl -X POST <?php echo esc_html($api_endpoint); ?> \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY_HERE" \
  -d '{
  "pages": [
    {
      "title": "About Us",
      "content": "<p>Welcome to our about page</p>",
      "status": "publish",
      "slug": "about-us"
    },
    {
      "title": "Contact Us",
      "content": "<p>Get in touch with us</p>",
      "status": "publish",
      "slug": "contact"
    }
  ]
}'</code></pre>
                
                <h4>Request Parameters:</h4>
                <table class="wp-list-table widefat">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>pages</code></td>
                            <td>Array</td>
                            <td>Yes</td>
                            <td>Array of page objects to create</td>
                        </tr>
                        <tr>
                            <td><code>pages[].title</code></td>
                            <td>String</td>
                            <td>Yes</td>
                            <td>Page title</td>
                        </tr>
                        <tr>
                            <td><code>pages[].content</code></td>
                            <td>String</td>
                            <td>No</td>
                            <td>Page content (HTML allowed)</td>
                        </tr>
                        <tr>
                            <td><code>pages[].status</code></td>
                            <td>String</td>
                            <td>No</td>
                            <td>publish, draft, pending (default: publish)</td>
                        </tr>
                        <tr>
                            <td><code>pages[].slug</code></td>
                            <td>String</td>
                            <td>No</td>
                            <td>Page URL slug</td>
                        </tr>
                        <tr>
                            <td><code>pages[].parent_id</code></td>
                            <td>Integer</td>
                            <td>No</td>
                            <td>Parent page ID</td>
                        </tr>
                        <tr>
                            <td><code>pages[].template</code></td>
                            <td>String</td>
                            <td>No</td>
                            <td>Page template file name</td>
                        </tr>
                        <tr>
                            <td><code>pages[].meta</code></td>
                            <td>Object</td>
                            <td>No</td>
                            <td>Custom meta fields</td>
                        </tr>
                        <tr>
                            <td><code>pages[].featured_image_url</code></td>
                            <td>String</td>
                            <td>No</td>
                            <td>URL of featured image</td>
                        </tr>
                    </tbody>
                </table>
                
                <h4>Response Example (Success):</h4>
                <pre><code>{
  "success": true,
  "message": "2 page(s) created successfully",
  "data": {
    "created_pages": [
      {
        "id": 123,
        "title": "About Us",
        "url": "<?php echo esc_html($site_url); ?>/about-us",
        "status": "publish"
      },
      {
        "id": 124,
        "title": "Contact Us",
        "url": "<?php echo esc_html($site_url); ?>/contact",
        "status": "publish"
      }
    ],
    "total_created": 2,
    "total_requested": 2,
    "errors": [],
    "response_time_ms": 245
  }
}</code></pre>
                
                <h4>Error Response Example:</h4>
                <pre><code>{
  "code": "invalid_api_key",
  "message": "Invalid or expired API key",
  "data": {
    "status": 401
  }
}</code></pre>
            </div>
            
            <div class="spb-card">
                <h3>Webhook Notifications</h3>
                <p>When pages are created successfully, a webhook notification is sent to your configured webhook URL.</p>
                
                <h4>Webhook Payload:</h4>
                <pre><code>{
  "event": "pages_created",
  "timestamp": "2025-10-22T14:30:00Z",
  "request_id": "req_abc123xyz",
  "api_key_name": "Production Server",
  "total_pages": 2,
  "pages": [
    {
      "id": 123,
      "title": "About Us",
      "url": "<?php echo esc_html($site_url); ?>/about-us"
    },
    {
      "id": 124,
      "title": "Contact Us",
      "url": "<?php echo esc_html($site_url); ?>/contact"
    }
  ]
}</code></pre>
                
                <h4>Webhook Signature Verification:</h4>
                <p>Each webhook includes an <code>X-Webhook-Signature</code> header with HMAC-SHA256 signature.</p>
                <p><strong>Your Webhook Secret:</strong> <code><?php echo esc_html($webhook_secret); ?></code></p>
                
                <h4>PHP Verification Example:</h4>
                <pre><code>$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$secret = '<?php echo esc_html($webhook_secret); ?>';

$expected_signature = hash_hmac('sha256', $payload, $secret);

if (hash_equals($expected_signature, $signature)) {
    // Signature is valid
    $data = json_decode($payload, true);
    // Process webhook...
} else {
    // Invalid signature
    http_response_code(401);
}</code></pre>
            </div>
            
            <div class="spb-card">
                <h3>Rate Limiting</h3>
                <p>API requests are limited to <strong><?php echo esc_html(get_option('spb_rate_limit', 100)); ?> requests per hour</strong> per API key.</p>
                <p>If you exceed the rate limit, you'll receive a <code>429 Too Many Requests</code> response.</p>
            </div>
            
            <div class="spb-card">
                <h3>Status Codes</h3>
                <table class="wp-list-table widefat">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>200</code></td>
                            <td>Success</td>
                        </tr>
                        <tr>
                            <td><code>400</code></td>
                            <td>Bad Request - Invalid parameters</td>
                        </tr>
                        <tr>
                            <td><code>401</code></td>
                            <td>Unauthorized - Invalid or missing API key</td>
                        </tr>
                        <tr>
                            <td><code>429</code></td>
                            <td>Too Many Requests - Rate limit exceeded</td>
                        </tr>
                        <tr>
                            <td><code>503</code></td>
                            <td>Service Unavailable - API is disabled</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
