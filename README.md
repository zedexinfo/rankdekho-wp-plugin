# RankDekho WordPress Payment Gateway Plugin

A WordPress plugin that integrates with Java backend and React frontend to handle payment processing through WooCommerce. This plugin enables seamless user synchronization and secure payment processing for subscription plans.

## Features

- **User Synchronization**: Sync users between Java backend and WordPress
- **Secure Hash-based Authentication**: Encrypted tokens for secure payment processing
- **WooCommerce Integration**: Automatic cart management and checkout handling
- **REST API Endpoints**: Easy integration with external systems
- **Admin Dashboard**: Comprehensive admin interface for monitoring and configuration
- **Debug Logging**: Detailed logging for troubleshooting
- **Rate Limiting**: Built-in protection against abuse

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- WooCommerce 5.0 or higher
- WooCommerce Subscriptions plugin (for subscription products)

## Installation

1. Upload the plugin files to the `/wp-content/plugins/rankdekho-payment-gateway/` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > RankDekho Gateway to configure the plugin

## Configuration

### API Settings

1. **API Key**: Auto-generated key for authenticating requests from Java backend
2. **Encryption Key**: Used for encrypting/decrypting hash tokens
3. **Java Webhook URL**: URL to notify Java backend about successful orders
4. **Debug Mode**: Enable for detailed logging (disable in production)

### WooCommerce Setup

1. Create subscription products in WooCommerce
2. Add custom meta field `_rankdekho_plan_id` to products with corresponding plan IDs from Java backend
3. Ensure WooCommerce Subscriptions is properly configured

## API Endpoints

### 1. Sync User
**POST** `/wp-json/rankdekho/v1/sync-user`

Creates or updates a WordPress user and generates a payment hash.

**Headers:**
```
X-API-Key: YOUR_API_KEY
Content-Type: application/json
```

**Request Body:**
```json
{
    "java_user_id": 123,
    "email": "user@example.com",
    "username": "testuser",
    "first_name": "John",
    "last_name": "Doe",
    "plan_id": 1
}
```

**Response:**
```json
{
    "success": true,
    "user_id": 456,
    "java_user_id": 123,
    "payment_url": "https://yoursite.com/wp-json/rankdekho/v1/process-payment?hash=...",
    "hash": "encrypted_hash_token",
    "expires_in": 900
}
```

### 2. Process Payment
**GET** `/wp-json/rankdekho/v1/process-payment?hash=HASH_TOKEN`

Processes the payment request, authenticates user, and redirects to checkout.

## Workflow

1. **User Registration**: User registers on React frontend and selects a plan
2. **API Call**: Java backend calls `/sync-user` endpoint with user data
3. **User Creation**: WordPress creates/updates user and generates secure hash
4. **Payment URL**: Java backend receives payment URL with hash token
5. **Redirect**: User is redirected to payment URL
6. **Authentication**: Plugin verifies hash and automatically logs in user
7. **Cart Setup**: Selected plan is added to WooCommerce cart
8. **Checkout**: User is redirected to WooCommerce checkout
9. **Order Processing**: WooCommerce handles payment and order completion
10. **Webhook**: Java backend is notified of successful order

## Security Features

- **Encrypted Hash Tokens**: All payment tokens are encrypted using AES-256-CBC
- **Token Expiration**: Payment tokens expire after 15 minutes
- **Rate Limiting**: Built-in protection against API abuse
- **Nonce Verification**: WordPress nonces for additional security
- **IP Tracking**: Client IP logging for audit trails

## Development

### Custom Authentication Override

The plugin overrides `wp_authenticate` to handle hash-based authentication:

```php
// Global variable set during payment processing
global $rankdekho_auth_data;

// Custom authentication in RankDekho_Auth class
public function custom_authenticate($user, $username, $password) {
    global $rankdekho_auth_data;
    
    if (isset($rankdekho_auth_data) && $rankdekho_auth_data['authenticated'] === true) {
        return get_user_by('ID', $rankdekho_auth_data['wp_user_id']);
    }
    
    return $user;
}
```

### WooCommerce Integration

The plugin integrates with WooCommerce to:
- Create customer sessions
- Add subscription products to cart
- Track order metadata
- Send webhook notifications

### Database Schema

The plugin creates a custom table `wp_rankdekho_user_sync`:

```sql
CREATE TABLE wp_rankdekho_user_sync (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    java_user_id bigint(20) NOT NULL,
    wp_user_id bigint(20) NOT NULL,
    hash_token varchar(255) DEFAULT NULL,
    sync_status varchar(20) DEFAULT 'pending',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY java_user_id (java_user_id)
);
```

## Troubleshooting

### Common Issues

1. **API Key Not Working**
   - Verify the API key in Settings > RankDekho Gateway
   - Check that `X-API-Key` header is properly set

2. **Hash Token Expired**
   - Tokens expire after 15 minutes
   - Generate new payment URL if needed

3. **Product Not Found**
   - Ensure WooCommerce product has `_rankdekho_plan_id` meta field
   - Verify plan_id matches between systems

4. **WooCommerce Not Active**
   - Install and activate WooCommerce plugin
   - Install WooCommerce Subscriptions for subscription products

### Debug Mode

Enable debug mode in plugin settings to:
- View detailed logs in admin dashboard
- Track API requests and responses
- Monitor authentication attempts
- Debug payment processing issues

### Log Files

When `WP_DEBUG_LOG` is enabled, logs are also written to WordPress debug.log file.

## Support

For support and bug reports, please contact the development team or create an issue in the repository.

## License

This plugin is licensed under the GPL v2 or later.
