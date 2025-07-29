# RankDekho Payment Gateway Configuration Example

## Java Backend Integration

### API Endpoint Usage

```java
// Example Java code to sync user and get payment URL
@RestController
public class PaymentController {
    
    @Autowired
    private RestTemplate restTemplate;
    
    @Value("${wordpress.api.url}")
    private String wordpressApiUrl;
    
    @Value("${wordpress.api.key}")
    private String wordpressApiKey;
    
    @PostMapping("/api/users/{userId}/checkout")
    public ResponseEntity<PaymentResponse> initiateCheckout(
            @PathVariable Long userId,
            @RequestBody CheckoutRequest request) {
        
        // Prepare user data for WordPress
        UserSyncRequest syncRequest = new UserSyncRequest();
        syncRequest.setJavaUserId(userId);
        syncRequest.setEmail(request.getEmail());
        syncRequest.setUsername(request.getUsername());
        syncRequest.setFirstName(request.getFirstName());
        syncRequest.setLastName(request.getLastName());
        syncRequest.setPlanId(request.getPlanId());
        
        // Set headers
        HttpHeaders headers = new HttpHeaders();
        headers.setContentType(MediaType.APPLICATION_JSON);
        headers.set("X-API-Key", wordpressApiKey);
        
        HttpEntity<UserSyncRequest> entity = new HttpEntity<>(syncRequest, headers);
        
        try {
            // Call WordPress API
            ResponseEntity<WordPressSyncResponse> response = restTemplate.postForEntity(
                wordpressApiUrl + "/wp-json/rankdekho/v1/sync-user",
                entity,
                WordPressSyncResponse.class
            );
            
            if (response.getStatusCode().is2xxSuccessful() && response.getBody().isSuccess()) {
                WordPressSyncResponse wpResponse = response.getBody();
                
                // Store mapping in database
                saveUserMapping(userId, wpResponse.getUserId(), wpResponse.getHash());
                
                // Return payment URL to frontend
                PaymentResponse paymentResponse = new PaymentResponse();
                paymentResponse.setPaymentUrl(wpResponse.getPaymentUrl());
                paymentResponse.setExpiresIn(wpResponse.getExpiresIn());
                
                return ResponseEntity.ok(paymentResponse);
            } else {
                return ResponseEntity.status(HttpStatus.BAD_REQUEST)
                    .body(new PaymentResponse("Failed to sync user with WordPress"));
            }
            
        } catch (Exception e) {
            logger.error("Error syncing user with WordPress", e);
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR)
                .body(new PaymentResponse("Internal server error"));
        }
    }
    
    @PostMapping("/webhook/wordpress/order-success")
    public ResponseEntity<String> handleOrderSuccess(@RequestBody OrderWebhookData data) {
        // Handle successful order notification from WordPress
        try {
            // Update user subscription status
            updateUserSubscription(data.getJavaUserId(), data.getPlanId(), data.getOrderId());
            
            // Send confirmation email
            sendSubscriptionConfirmation(data.getJavaUserId());
            
            return ResponseEntity.ok("Order processed successfully");
        } catch (Exception e) {
            logger.error("Error processing order webhook", e);
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR).body("Error processing order");
        }
    }
}
```

### Configuration Properties

```properties
# application.properties
wordpress.api.url=https://yourwordpresssite.com
wordpress.api.key=your-generated-api-key-from-wordpress
wordpress.webhook.url=https://yourjavabackend.com/webhook/wordpress/order-success
```

## React Frontend Integration

### Checkout Component Example

```javascript
// CheckoutComponent.jsx
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';

const CheckoutComponent = ({ user, selectedPlan }) => {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const navigate = useNavigate();
    
    const handleProceedToCheckout = async () => {
        setLoading(true);
        setError(null);
        
        try {
            const response = await fetch(`/api/users/${user.id}/checkout`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${user.token}`
                },
                body: JSON.stringify({
                    email: user.email,
                    username: user.username,
                    firstName: user.firstName,
                    lastName: user.lastName,
                    planId: selectedPlan.id
                })
            });
            
            const data = await response.json();
            
            if (response.ok && data.paymentUrl) {
                // Redirect to WordPress payment processing
                window.location.href = data.paymentUrl;
            } else {
                setError(data.message || 'Failed to initiate checkout');
            }
        } catch (err) {
            setError('Network error. Please try again.');
        } finally {
            setLoading(false);
        }
    };
    
    return (
        <div className="checkout-container">
            <h2>Complete Your Purchase</h2>
            <div className="plan-summary">
                <h3>{selectedPlan.name}</h3>
                <p>Price: ${selectedPlan.price}/month</p>
            </div>
            
            {error && (
                <div className="error-message">
                    {error}
                </div>
            )}
            
            <button 
                onClick={handleProceedToCheckout}
                disabled={loading}
                className="checkout-button"
            >
                {loading ? 'Processing...' : 'Proceed to Checkout'}
            </button>
        </div>
    );
};

export default CheckoutComponent;
```

## WordPress Configuration

### WooCommerce Product Setup

1. Create subscription products in WooCommerce
2. Add custom meta field for each product:
   - Meta key: `_rankdekho_plan_id`
   - Meta value: Corresponding plan ID from Java backend

### Plugin Settings

1. Go to WordPress Admin → Settings → RankDekho Gateway
2. Configure:
   - API Key (auto-generated)
   - Java Webhook URL: `https://yourjavabackend.com/webhook/wordpress/order-success`
   - Enable/disable debug mode as needed

### Required Plugins

- WooCommerce (latest version)
- WooCommerce Subscriptions (for recurring payments)

## Security Considerations

1. **API Key Protection**: Store API keys securely and rotate regularly
2. **HTTPS Only**: Always use HTTPS for all API communications
3. **Token Expiration**: Payment tokens expire in 15 minutes
4. **Rate Limiting**: Built-in protection against API abuse
5. **Input Validation**: All inputs are validated and sanitized

## Testing

### Test User Sync API

```bash
curl -X POST "https://yourwordpresssite.com/wp-json/rankdekho/v1/sync-user" \
     -H "Content-Type: application/json" \
     -H "X-API-Key: your-api-key" \
     -d '{
       "java_user_id": 123,
       "email": "test@example.com",
       "username": "testuser",
       "first_name": "John",
       "last_name": "Doe",
       "plan_id": 1
     }'
```

### Expected Response

```json
{
    "success": true,
    "user_id": 456,
    "java_user_id": 123,
    "payment_url": "https://yourwordpresssite.com/wp-json/rankdekho/v1/process-payment?hash=...",
    "hash": "encrypted_hash_token",
    "expires_in": 900
}
```

## Troubleshooting

### Common Issues

1. **404 on API endpoints**: Check permalink structure and REST API status
2. **Authentication failures**: Verify API key and headers
3. **Hash token expired**: Regenerate payment URL
4. **WooCommerce integration issues**: Check product meta fields and subscription setup

### Debug Mode

Enable debug mode in plugin settings to see detailed logs in WordPress admin.