# Simple Page Builder

A powerful WordPress plugin that enables bulk page creation via a secure REST API with advanced authentication, webhook notifications, and comprehensive admin management.

## Features

- **Secure REST API** - External applications can create WordPress pages via REST API
- **API Key Authentication** - Production-ready API key system with hashing and expiration
- **Rate Limiting** - Configurable request limits per API key
- **Webhook Notifications** - Automatic notifications with HMAC-SHA256 signatures
- **Admin Dashboard** - Complete management interface with 5 tabs
- **Activity Logging** - Comprehensive logging of all API requests
- **CSV Export** - Export activity logs for analysis

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL/MariaDB database

## Installation

1. Download or clone this repository
2. Upload the `simple-page-builder` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **Tools → Page Builder** to configure

## Quick Start

### 1. Generate an API Key

1. Go to **Tools → Page Builder → API Keys** in WordPress admin
2. Enter a key name (e.g., "Production Server")
3. Optionally set an expiration date
4. Click **Generate API Key**
5. **Important:** Copy the API key immediately - you won't see it again!

### 2. Make Your First API Call

```bash
curl -X POST https://yoursite.com/wp-json/pagebuilder/v1/create-pages \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY_HERE" \
  -d '{
    "pages": [
      {
        "title": "About Us",
        "content": "<p>Welcome to our about page</p>",
        "status": "publish",
        "slug": "about-us"
      }
    ]
  }'
```

## API Documentation

### Authentication

All API requests require a valid API key in the request header:

```
X-API-Key: your_api_key_here
```

### Endpoint: Create Pages

**URL:** `/wp-json/pagebuilder/v1/create-pages`  
**Method:** `POST`  
**Content-Type:** `application/json`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `pages` | Array | Yes | Array of page objects to create |
| `pages[].title` | String | Yes | Page title |
| `pages[].content` | String | No | Page content (HTML allowed) |
| `pages[].status` | String | No | `publish`, `draft`, `pending` (default: `publish`) |
| `pages[].slug` | String | No | Page URL slug |
| `pages[].parent_id` | Integer | No | Parent page ID for hierarchical pages |
| `pages[].template` | String | No | Page template file name |
| `pages[].author_id` | Integer | No | Author user ID (default: 1) |
| `pages[].meta` | Object | No | Custom meta fields as key-value pairs |
| `pages[].featured_image_url` | String | No | URL of featured image to download |

#### Request Example

```json
{
  "pages": [
    {
      "title": "Services",
      "content": "<h2>Our Services</h2><p>We offer amazing services...</p>",
      "status": "publish",
      "slug": "services",
      "meta": {
        "custom_field": "value"
      }
    },
    {
      "title": "Contact",
      "content": "<p>Get in touch with us</p>",
      "status": "publish",
      "parent_id": 10,
      "featured_image_url": "https://example.com/image.jpg"
    }
  ]
}
```

#### Success Response

```json
{
  "success": true,
  "message": "2 page(s) created successfully",
  "data": {
    "created_pages": [
      {
        "id": 123,
        "title": "Services",
        "url": "https://yoursite.com/services",
        "status": "publish"
      },
      {
        "id": 124,
        "title": "Contact",
        "url": "https://yoursite.com/contact",
        "status": "publish"
      }
    ],
    "total_created": 2,
    "total_requested": 2,
    "errors": [],
    "response_time_ms": 245
  }
}
```

#### Error Response

```json
{
  "code": "invalid_api_key",
  "message": "Invalid or expired API key",
  "data": {
    "status": 401
  }
}
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| `200` | Success |
| `400` | Bad Request - Invalid parameters |
| `401` | Unauthorized - Invalid or missing API key |
| `429` | Too Many Requests - Rate limit exceeded |
| `503` | Service Unavailable - API is disabled |

## Webhook Notifications

When pages are created successfully, the plugin sends a webhook notification to your configured webhook URL.

### Webhook Configuration

1. Go to **Tools → Page Builder → Settings**
2. Enter your webhook URL
3. Note the webhook secret for signature verification
4. Save settings

### Webhook Payload

```json
{
  "event": "pages_created",
  "timestamp": "2025-10-22T14:30:00Z",
  "request_id": "req_abc123xyz",
  "api_key_name": "Production Server",
  "total_pages": 2,
  "pages": [
    {
      "id": 123,
      "title": "Services",
      "url": "https://yoursite.com/services"
    },
    {
      "id": 124,
      "title": "Contact",
      "url": "https://yoursite.com/contact"
    }
  ]
}
```

### Webhook Security

Each webhook includes an `X-Webhook-Signature` header with HMAC-SHA256 signature.

#### Verify Webhook Signature (PHP)

```php
<?php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$secret = 'your_webhook_secret_from_settings';

$expected_signature = hash_hmac('sha256', $payload, $secret);

if (hash_equals($expected_signature, $signature)) {
    // Signature is valid
    $data = json_decode($payload, true);
    // Process webhook...
} else {
    // Invalid signature
    http_response_code(401);
    echo 'Invalid signature';
}
?>
```

#### Verify Webhook Signature (Node.js)

```javascript
const crypto = require('crypto');

app.post('/webhook', (req, res) => {
    const signature = req.headers['x-webhook-signature'];
    const secret = 'your_webhook_secret_from_settings';
    
    const expectedSignature = crypto
        .createHmac('sha256', secret)
        .update(JSON.stringify(req.body))
        .digest('hex');
    
    if (crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expectedSignature))) {
        // Signature is valid
        const data = req.body;
        // Process webhook...
        res.sendStatus(200);
    } else {
        // Invalid signature
        res.sendStatus(401);
    }
});
```

## Admin Interface

The plugin provides a comprehensive admin interface under **Tools → Page Builder** with 5 tabs:

### 1. API Keys Tab
- Generate new API keys with optional expiration
- View all API keys with status, usage stats, and last used date
- Revoke API keys instantly
- Copy newly generated keys (shown only once)

### 2. Activity Log Tab
- View all API requests with timestamps
- Filter by status, date range, and API key
- Export logs as CSV
- Monitor response times and error rates

### 3. Created Pages Tab
- View all pages created via the API
- Quick links to edit or view pages
- Track which API key created each page

### 4. Settings Tab
- Configure webhook URL and view webhook secret
- Set rate limits (requests per hour per key)
- Enable/disable API access globally
- Set default API key expiration period

### 5. Documentation Tab
- Complete API documentation
- cURL examples
- Authentication guide
- Webhook verification code samples

## Rate Limiting

API requests are limited per API key to prevent abuse:

- Default: 100 requests per hour per API key
- Configurable in Settings tab
- Returns `429 Too Many Requests` when exceeded
- Rate limits reset hourly

## Security Features

- **API Key Hashing** - Keys are hashed using WordPress password hashing (similar to user passwords)
- **One-Time Display** - API keys are shown only once upon generation
- **Expiration Support** - Set optional expiration dates for API keys
- **Rate Limiting** - Prevent abuse with configurable request limits
- **Activity Logging** - All requests logged with IP address and user agent
- **Webhook Signatures** - HMAC-SHA256 signatures prevent webhook spoofing
- **Input Sanitization** - All inputs sanitized and validated
- **Permission Checks** - Admin features require `manage_options` capability

## Code Examples

### Python Example

```python
import requests

url = "https://yoursite.com/wp-json/pagebuilder/v1/create-pages"
headers = {
    "Content-Type": "application/json",
    "X-API-Key": "YOUR_API_KEY_HERE"
}
data = {
    "pages": [
        {
            "title": "Python Created Page",
            "content": "<p>This page was created with Python!</p>",
            "status": "publish"
        }
    ]
}

response = requests.post(url, json=data, headers=headers)
print(response.json())
```

### JavaScript (Node.js) Example

```javascript
const axios = require('axios');

const url = 'https://yoursite.com/wp-json/pagebuilder/v1/create-pages';
const headers = {
    'Content-Type': 'application/json',
    'X-API-Key': 'YOUR_API_KEY_HERE'
};
const data = {
    pages: [
        {
            title: 'JavaScript Created Page',
            content: '<p>This page was created with JavaScript!</p>',
            status: 'publish'
        }
    ]
};

axios.post(url, data, { headers })
    .then(response => console.log(response.data))
    .catch(error => console.error(error));
```

### PHP Example

```php
<?php
$url = 'https://yoursite.com/wp-json/pagebuilder/v1/create-pages';
$api_key = 'YOUR_API_KEY_HERE';

$data = array(
    'pages' => array(
        array(
            'title' => 'PHP Created Page',
            'content' => '<p>This page was created with PHP!</p>',
            'status' => 'publish'
        )
    )
);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'X-API-Key: ' . $api_key
));

$response = curl_exec($ch);
curl_close($ch);

print_r(json_decode($response));
?>
```

## Troubleshooting

### API Returns 401 Unauthorized
- Verify your API key is correct
- Check if the API key has been revoked
- Ensure the API key hasn't expired
- Make sure you're sending the key in the `X-API-Key` header

### API Returns 429 Rate Limit Exceeded
- Wait for the rate limit to reset (1 hour)
- Increase rate limit in Settings tab
- Consider using multiple API keys for different applications

### Pages Not Created
- Check the Activity Log tab for error details
- Verify the request format matches the documentation
- Ensure required fields (title) are provided
- Check WordPress user permissions

### Webhook Not Received
- Verify webhook URL is correct in Settings
- Check webhook endpoint is publicly accessible
- Review webhook signature verification code
- Check Activity Log for webhook delivery status

## Development

### File Structure

```
simple-page-builder/
├── simple-page-builder.php        # Main plugin file
├── includes/
│   ├── class-spb-database.php     # Database table management
│   ├── class-spb-api-keys.php     # API key generation and validation
│   ├── class-spb-rate-limiter.php # Rate limiting logic
│   ├── class-spb-activity-logger.php # Activity logging
│   ├── class-spb-webhook.php      # Webhook notifications
│   └── class-spb-rest-api.php     # REST API endpoints
├── admin/
│   └── class-spb-admin.php        # Admin interface
├── assets/
│   ├── css/
│   │   └── admin.css              # Admin styling
│   └── js/
│       └── admin.js               # Admin JavaScript
└── README.md                      # This file
```

### Database Tables

The plugin creates three custom database tables:

1. **wp_spb_api_keys** - Stores API keys (hashed)
2. **wp_spb_activity_log** - Logs all API requests
3. **wp_spb_created_pages** - Tracks pages created via API

### Hooks and Filters

Currently, the plugin doesn't provide custom hooks, but this can be extended in future versions.

## Changelog

### Version 1.0.0
- Initial release
- REST API endpoint for bulk page creation
- API key authentication system
- Rate limiting
- Webhook notifications with HMAC-SHA256 signatures
- Complete admin interface with 5 tabs
- Activity logging and CSV export
- Comprehensive documentation

## Support

For issues, questions, or feature requests:
- Email: wordpress@thewebops.com
- Create an issue in the GitHub repository

## License

GPL v2 or later

## Credits

Developed by [Your Name] as part of WordPress Developer Technical Assessment.

## Submission Information

- **Assessment:** WordPress Developer Technical Assessment - Advanced REST API & Automation Task
- **Submission Date:** October 22, 2025
- **Repository:** [Your GitHub Repository URL]
