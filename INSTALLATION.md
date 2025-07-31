# RankDekho Payment Gateway - Installation Guide

## Quick Installation Steps

### 1. WordPress Setup

1. **Upload Plugin Files**
   ```bash
   # Upload all files to your WordPress plugins directory
   /wp-content/plugins/rankdekho-payment-gateway/
   ```

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "RankDekho Payment Gateway"
   - Click "Activate"

3. **Install Required Plugins**
   - Install and activate **WooCommerce**
   - Install and activate **WooCommerce Subscriptions**

### 2. Plugin Configuration

1. **Access Settings**
   - Go to WordPress Admin → Settings → RankDekho Gateway

2. **Configure API Settings**
   - API Key: Auto-generated (copy this for Java backend)
   - Encryption Key: Auto-generated (do not change unless necessary)
   - Java Webhook URL: Enter your Java backend webhook endpoint
   - Debug Mode: Enable only for testing

### 3. WooCommerce Product Setup

1. **Create Subscription Products**
   - Go to Products → Add New
   - Select "Simple subscription" or "Variable subscription"
   - Set pricing and subscription details

2. **Add Plan ID Meta Field**
   For each product, add custom meta:
   - Meta Key: `_rankdekho_plan_id`
   - Meta Value: Corresponding plan ID from Java backend (e.g., 1, 2, 3)

### 4. Java Backend Configuration

1. **Add Dependencies**
   ```xml
   <!-- Add to pom.xml -->
   <dependency>
       <groupId>org.springframework.boot</groupId>
       <artifactId>spring-boot-starter-web</artifactId>
   </dependency>
   ```

2. **Configure Properties**
   ```properties
   # application.properties
   wordpress.api.url=https://yourwordpresssite.com
   wordpress.api.key=YOUR_API_KEY_FROM_WORDPRESS
   wordpress.webhook.url=https://yourjavabackend.com/webhook/wordpress/order-success
   ```

3. **Implement Integration**
   - Use the example code from CONFIGURATION.md
   - Create endpoints for user checkout
   - Handle webhook notifications

### 5. Testing

1. **Test API Connectivity**
   - Go to WordPress Admin → Settings → RankDekho Gateway → Tools
   - Click "Test API Connection"

2. **Test User Sync**
   ```bash
   curl -X POST "https://yourwordpresssite.com/wp-json/rankdekho/v1/sync-user" \
        -H "Content-Type: application/json" \
        -H "X-API-Key: YOUR_API_KEY" \
        -d '{
          "java_user_id": 123,
          "email": "test@example.com",
          "username": "testuser",
          "plan_id": 1
        }'
   ```

3. **Test Payment Flow**
   - Register a test user on your React frontend
   - Select a subscription plan
   - Proceed to checkout
   - Verify redirect to WordPress
   - Complete payment process

## Security Checklist

- [ ] HTTPS enabled on all domains
- [ ] API keys stored securely
- [ ] Debug mode disabled in production
- [ ] Regular backups configured
- [ ] WooCommerce security hardened

## Production Deployment

1. **Performance Optimization**
   - Enable WordPress caching
   - Optimize database queries
   - Use CDN for static assets

2. **Monitoring**
   - Set up error monitoring
   - Monitor API response times
   - Track payment success rates

3. **Maintenance**
   - Regular plugin updates
   - Database cleanup (automated)
   - Log rotation

## Support

For technical support:
1. Check debug logs in WordPress admin
2. Verify all configuration settings
3. Test API endpoints individually
4. Contact development team with specific error messages

## Common URLs

- Plugin Admin: `/wp-admin/options-general.php?page=rankdekho-payment-gateway`
- API Sync Endpoint: `/wp-json/rankdekho/v1/sync-user`
- Payment Processing: `/wp-json/rankdekho/v1/process-payment`
- WooCommerce Checkout: `/checkout/`