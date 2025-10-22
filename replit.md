# Simple Page Builder - WordPress Plugin

## Overview

Simple Page Builder is a production-ready WordPress plugin developed for a technical assessment. It provides a secure REST API for bulk page creation, enabling external applications to programmatically create WordPress pages through authenticated API endpoints. The plugin includes comprehensive security features (API key authentication, rate limiting, HMAC-signed webhooks) and a full admin dashboard for key management, activity monitoring, and webhook configuration.

**Status:** ✅ Complete and ready for submission

## User Preferences

Preferred communication style: Simple, everyday language.

## Recent Changes

**October 22, 2025** - Initial development completed
- Implemented all core features for technical assessment
- Created complete WordPress plugin with 8 PHP classes
- Built admin interface with 5 tabs (API Keys, Activity Log, Created Pages, Settings, Documentation)
- Added webhook system with HMAC-SHA256 signature verification
- Implemented rate limiting and comprehensive activity logging
- Created validation workflow to verify PHP syntax
- Wrote comprehensive README with API documentation and code examples
- Plugin validated and reviewed - ready for submission

## Project Architecture

### WordPress Plugin Structure

```
simple-page-builder/
├── simple-page-builder.php           # Main plugin file (entry point)
├── includes/                         # Core functionality classes
│   ├── class-spb-database.php       # Database table creation and management
│   ├── class-spb-api-keys.php       # API key generation, validation, hashing
│   ├── class-spb-rate-limiter.php   # Rate limiting per API key
│   ├── class-spb-activity-logger.php # Request logging and tracking
│   ├── class-spb-webhook.php        # Webhook notifications with HMAC
│   └── class-spb-rest-api.php       # REST API endpoint handler
├── admin/
│   └── class-spb-admin.php          # Admin interface (5 tabs)
├── assets/
│   ├── css/admin.css                # Admin styling
│   └── js/admin.js                  # AJAX and UI interactions
├── validate.php                      # PHP syntax validation script
└── README.md                         # Complete documentation
```

### Core Components

**1. Main Plugin File (`simple-page-builder.php`)**
- Singleton pattern for plugin initialization
- Loads all dependencies
- Handles activation/deactivation hooks
- Sets default options on activation

**2. Database Layer (`class-spb-database.php`)**
- Creates 3 custom tables: API keys, activity logs, created pages
- Uses WordPress `dbDelta()` for safe schema updates
- Proper indexing for performance

**3. API Key Management (`class-spb-api-keys.php`)**
- Generates cryptographically secure API keys using `random_bytes(32)`
- Hashes keys with `wp_hash_password()` (WordPress standard)
- Validates keys by comparing hashes
- Tracks usage statistics and expiration
- One-time display security model

**4. REST API Endpoint (`class-spb-rest-api.php`)**
- Endpoint: `POST /wp-json/pagebuilder/v1/create-pages`
- API key authentication via `X-API-Key` header
- Rate limit enforcement before processing
- Bulk page creation with comprehensive error handling
- Sanitizes all inputs before database operations
- Supports custom meta fields, featured images, templates
- Returns detailed response with created pages and errors

**5. Rate Limiting (`class-spb-rate-limiter.php`)**
- Uses WordPress transients for hourly rate tracking
- Configurable limits per API key
- Returns 429 status when exceeded
- Automatic hourly reset

**6. Activity Logging (`class-spb-activity-logger.php`)**
- Logs all API requests (successful and failed)
- Captures: timestamp, endpoint, status, response time, IP address
- Tracks pages created via API
- Supports filtering and CSV export

**7. Webhook System (`class-spb-webhook.php`)**
- Sends notifications when pages are created
- HMAC-SHA256 signature for payload verification
- Retry logic with exponential backoff (2 retries)
- Doesn't fail page creation if webhook fails
- 10-second timeout

**8. Admin Interface (`class-spb-admin.php`)**
- 5 tabbed sections under Tools → Page Builder
- AJAX-powered key generation and revocation
- Real-time activity monitoring
- CSV export functionality
- Comprehensive API documentation with examples

### Security Implementation

**API Key Security:**
- 64-character random keys with `spb_` prefix
- Hashed using WordPress password hashing (bcrypt)
- Stored hashes only, never plaintext
- One-time display after generation
- Optional expiration dates
- Revocable at any time

**Request Security:**
- API key validation on every request
- Rate limiting to prevent abuse
- Input sanitization using WordPress functions
- `wp_kses_post()` for HTML content
- `sanitize_text_field()` for text inputs
- `esc_url_raw()` for URLs

**Admin Security:**
- `manage_options` capability required
- WordPress nonces for all AJAX requests
- Nonce verification for all forms
- Output escaping in admin views

**Webhook Security:**
- HMAC-SHA256 signatures
- Secret key automatically generated
- Signature sent in `X-Webhook-Signature` header
- Verification examples provided in documentation

### Database Schema

**wp_spb_api_keys**
- Stores hashed API keys with metadata
- Tracks usage statistics (request count, last used)
- Supports expiration dates
- Indexed on: api_key_hash, status, expiration_date

**wp_spb_activity_log**
- Complete audit trail of all API requests
- Stores request/response data as JSON
- Captures IP address and user agent
- Indexed on: api_key_id, status, created_date, endpoint

**wp_spb_created_pages**
- Links WordPress pages to API keys
- Tracks which key created each page
- References activity log for full context
- Indexed on: page_id, api_key_id, created_date

## External Dependencies

### WordPress Core
- **Version:** 5.0 or higher
- **Purpose:** Plugin framework, REST API, database abstraction
- **Key APIs:** `register_rest_route()`, `wpdb`, AJAX hooks, admin menus

### PHP
- **Version:** 7.4 or higher
- **Required Extensions:** Standard (json, hash, openssl for random_bytes)
- **Security Functions:** `random_bytes()`, `wp_hash_password()`, `hash_hmac()`

### MySQL/MariaDB
- WordPress standard database
- Custom tables created with proper character sets and collation

## Features Implemented

✅ **REST API Endpoint**
- External access from any application
- API key authentication
- Bulk page creation
- Comprehensive error handling

✅ **API Key System**
- Secure generation and hashing
- Expiration support
- Revocation capability
- Usage tracking

✅ **Rate Limiting**
- Configurable per-key limits
- Hourly reset
- 429 status codes

✅ **Webhook Notifications**
- HMAC-SHA256 signatures
- Retry logic with backoff
- Non-blocking (doesn't fail page creation)

✅ **Admin Dashboard**
- API Keys management tab
- Activity Log with filtering
- Created Pages tracking
- Settings configuration
- Complete API documentation

✅ **Activity Logging**
- All requests logged
- CSV export
- Filtering capabilities
- Performance metrics

✅ **Security**
- Hashed API keys
- Input sanitization
- Output escaping
- Rate limiting
- Webhook signatures

## Installation & Testing

### For Development (Local WordPress)
1. Copy `simple-page-builder/` folder to `wp-content/plugins/`
2. Activate plugin in WordPress admin
3. Navigate to **Tools → Page Builder**
4. Generate an API key
5. Test the endpoint with cURL or Postman

### Validation
A PHP validation script is included:
```bash
php simple-page-builder/validate.php
```

This checks PHP syntax of all plugin files and confirms the structure is correct.

## API Usage Example

```bash
curl -X POST https://yoursite.com/wp-json/pagebuilder/v1/create-pages \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{
    "pages": [
      {
        "title": "About Us",
        "content": "<p>About our company</p>",
        "status": "publish"
      }
    ]
  }'
```

## Submission Details

**Assessment:** WordPress Developer Technical Assessment - Advanced REST API & Automation Task

**Requirements Met:**
- ✅ Secure REST API accessible from external applications
- ✅ API key authentication (not username/password)
- ✅ Admin interface for key management
- ✅ Webhook system with notifications
- ✅ Production-ready security
- ✅ Comprehensive documentation
- ✅ Ready for Git repository submission

**Next Steps for Submission:**
1. Create public Git repository (GitHub/GitLab/Bitbucket)
2. Copy the `simple-page-builder/` folder to the repository
3. Commit with meaningful messages
4. Submit repository URL to: wordpress@thewebops.com

## Technical Notes

- **WordPress Standards:** Follows WordPress coding standards and plugin development best practices
- **Security:** Production-ready with proper authentication, hashing, sanitization, and escaping
- **Performance:** Optimized database queries with proper indexing
- **Scalability:** Custom tables support high-volume API usage
- **Maintainability:** Modular class structure with clear separation of concerns
- **Documentation:** Complete README with API docs, code examples, and troubleshooting guide
