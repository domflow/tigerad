# Geofence Advertising App - Complete Setup Guide

## 🚀 Overview

This guide will walk you through setting up the complete Geofence Advertising App system, including backend services, database configuration, payment integration, and mobile app deployment.

## 📋 Prerequisites

### System Requirements
- **PHP 8.2+** with extensions: PDO, MySQL, JSON, cURL, GD
- **MySQL 8.0+** or **MariaDB 10.6+**
- **Apache 2.4+** or **Nginx 1.18+**
- **SSL Certificate** (Let's Encrypt recommended)
- **Android Studio Arctic Fox+**
- **Node.js 16+** (for payment webhooks)
- **Redis 6+** (optional, for caching)

### Accounts Needed
- **Stripe Account** (for payment processing)
- **Google Cloud Console** (for Google Wallet and Maps)
- **Google Play Console** (for app publishing)
- **Domain Name** (for API endpoints)

## 🗄️ Database Setup

### 1. Create Database and User
```bash
mysql -u root -p
```

```sql
CREATE DATABASE geofence_ads CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'geofence_user'@'localhost' IDENTIFIED BY 'secure_password_123';
GRANT ALL PRIVILEGES ON geofence_ads.* TO 'geofence_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Import Database Schema
```bash
mysql -u geofence_user -p geofence_ads < geofence_database_schema.sql
```

### 3. Verify Installation
```bash
mysql -u geofence_user -p geofence_ads -e "SHOW TABLES;"
```

## 🔧 Backend Setup

### 1. Configure Environment Variables
Create `config.php` with your actual credentials:

```php
// Update these values in geofence_config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'geofence_ads');
define('DB_USER', 'geofence_user');
define('DB_PASS', 'your_secure_password');
define('JWT_SECRET', 'your_super_secret_key_here');
define('STRIPE_SECRET_KEY', 'sk_live_your_stripe_secret_key');
define('GOOGLE_MAPS_API_KEY', 'your_google_maps_api_key');
```

### 2. Set Up Web Server

#### Apache Configuration
Create `/etc/apache2/sites-available/geofence-ads.conf`:

```apache
<VirtualHost *:80>
    ServerName api.yourdomain.com
    DocumentRoot /var/www/geofence-ads
    
    <Directory /var/www/geofence-ads>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/geofence-ads-error.log
    CustomLog ${APACHE_LOG_DIR}/geofence-ads-access.log combined
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite geofence-ads.conf
sudo systemctl reload apache2
```

#### SSL Configuration (Let's Encrypt)
```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d api.yourdomain.com
```

### 3. Deploy Backend Files
```bash
sudo mkdir -p /var/www/geofence-ads
sudo cp *.php /var/www/geofence-ads/
sudo chown -R www-data:www-data /var/www/geofence-ads/
sudo chmod 644 /var/www/geofence-ads/*.php
```

### 4. Set Up Cron Jobs
Create geofence processing cron job:

```bash
sudo crontab -e
```

Add:
```
# Process geofence events every minute
* * * * * /usr/bin/php /var/www/geofence-ads/process_geofence_events.php >> /var/log/geofence_events.log 2>&1

# Clean up old data daily at 2 AM
0 2 * * * /usr/bin/php /var/www/geofence-ads/cleanup_old_data.php >> /var/log/geofence_cleanup.log 2>&1

# Process expired credits monthly
0 3 1 * * /usr/bin/php /var/www/geofence-ads/process_expired_credits.php >> /var/log/credit_expiry.log 2>&1
```

## 💳 Payment Integration Setup

### 1. Stripe Configuration
1. Log in to [Stripe Dashboard](https://dashboard.stripe.com)
2. Navigate to Developers → API Keys
3. Copy your **Secret Key** (starts with `sk_live_`)
4. Update `STRIPE_SECRET_KEY` in `config.php`
5. Set up webhook endpoint: `https://api.yourdomain.com/webhooks/stripe`

### 2. Google Wallet Configuration
1. Go to [Google Pay Business Console](https://pay.google.com/business/)
2. Create a new business account
3. Get your **Merchant ID**
4. Update `GOOGLE_WALLET_MERCHANT_ID` in `config.php`
5. Configure allowed payment methods

### 3. Payment Webhook Setup
Create `webhooks/stripe.php`:

```php
<?php
require_once '../config.php';
require_once '../geofence_payment.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$endpoint_secret = 'whsec_your_webhook_secret';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
}

// Handle the event
switch ($event->type) {
    case 'payment_intent.succeeded':
        $paymentIntent = $event->data->object;
        handleSuccessfulPayment($paymentIntent);
        break;
    case 'charge.refunded':
        $refund = $event->data->object;
        handleRefund($refund);
        break;
    default:
        echo 'Received unknown event type ' . $event->type;
}

http_response_code(200);
?>
```

## 📱 Android App Setup

### 1. Configure API Keys
Update `app/build.gradle`:

```gradle
buildConfigField "String", "API_BASE_URL", "&quot;https://api.yourdomain.com/api/&quot;"
buildConfigField "String", "GOOGLE_MAPS_API_KEY", "&quot;your_google_maps_api_key&quot;"
buildConfigField "String", "STRIPE_PUBLISHABLE_KEY", "&quot;pk_live_your_stripe_key&quot;"
```

### 2. Google Maps Setup
1. Go to [Google Cloud Console](https://console.cloud.google.com)
2. Create a new project or select existing
3. Enable **Maps SDK for Android**
4. Create API key with restrictions:
   - Android apps
   - Your package name: `com.geofence.ads`
   - SHA-1 certificate fingerprint

Get SHA-1:
```bash
keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android -keypass android
```

### 3. Stripe Android Setup
1. Update `AndroidManifest.xml` with your publishable key
2. Configure Stripe in your app initialization

### 4. Build and Test
```bash
cd GeofenceAdsApp
./gradlew assembleDebug
```

Install on device:
```bash
adb install app/build/outputs/apk/debug/app-debug.apk
```

## 🔐 Security Configuration

### 1. HTTPS Enforcement
Update Apache configuration to force HTTPS:

```apache
<VirtualHost *:443>
    ServerName api.yourdomain.com
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/api.yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/api.yourdomain.com/privkey.pem
    
    # Security headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set X-XSS-Protection "1; mode=block"
    
    DocumentRoot /var/www/geofence-ads
    
    <Directory /var/www/geofence-ads>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Rate limiting
        <IfModule mod_ratelimit.c>
            SetOutputFilter RATE_LIMIT
            SetEnv rate-limit 100
        </IfModule>
    </Directory>
</VirtualHost>
```

### 2. Rate Limiting
Install and configure rate limiting:

```bash
sudo apt install libapache2-mod-ratelimit
sudo a2enmod ratelimit
```

### 3. Database Security
- Use strong passwords
- Limit database user permissions
- Enable SSL for database connections
- Regular security updates

## 📊 Monitoring and Analytics

### 1. Set Up Logging
Create log rotation:

```bash
sudo nano /etc/logrotate.d/geofence-ads
```

Add:
```
/var/log/geofence_*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

### 2. Performance Monitoring
Install monitoring tools:

```bash
sudo apt install htop iotop nethogs
```

### 3. Database Performance
Enable slow query logging:

```ini
[mysqld]
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
```

## 🚀 Deployment Checklist

### Pre-Deployment
- [ ] All environment variables configured
- [ ] SSL certificate installed
- [ ] Database secured and optimized
- [ ] Payment webhooks tested
- [ ] Rate limiting configured
- [ ] Security headers implemented
- [ ] Error logging configured
- [ ] Backup system in place

### Deployment
- [ ] Upload backend files to server
- [ ] Import database schema
- [ ] Configure web server
- [ ] Set up SSL
- [ ] Configure cron jobs
- [ ] Test API endpoints
- [ ] Verify payment processing
- [ ] Test geofence functionality

### Post-Deployment
- [ ] Monitor error logs
- [ ] Test with real devices
- [ ] Verify location accuracy
- [ ] Test payment flows
- [ ] Monitor performance
- [ ] Set up alerts
- [ ] Document any issues

## 🔍 Testing

### API Testing
```bash
# Test registration
curl -X POST https://api.yourdomain.com/api/register-store-owner \
  -H "Content-Type: application/json" \
  -d '{"business_name":"Test Store","owner_name":"Test Owner","email":"test@example.com","phone":"+1234567890","password":"testpass123"}'

# Test login
curl -X POST https://api.yourdomain.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"testpass123"}'

# Test nearby ads (replace TOKEN)
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://api.yourdomain.com/api/nearby-ads?latitude=37.7749&longitude=-122.4194"
```

### Location Testing
Use Google Maps to find coordinates near your test stores and verify geofence triggers.

### Payment Testing
Use Stripe test cards:
- Success: `4242424242424242`
- Decline: `4000000000000002`
- Requires authentication: `4000002500003155`

## 📞 Support and Maintenance

### Regular Maintenance Tasks
- Monitor error logs daily
- Check payment processing weekly
- Review geofence performance monthly
- Update security patches regularly
- Backup database weekly
- Review and optimize queries monthly

### Performance Optimization
- Monitor slow queries
- Optimize database indexes
- Cache frequently accessed data
- Use CDN for static assets
- Implement connection pooling
- Monitor API response times

### Scaling Considerations
- Horizontal scaling with load balancers
- Database replication
- Redis caching layer
- CDN for global distribution
- Microservices architecture
- Container orchestration

## 🆘 Troubleshooting

### Common Issues

**Location Not Working:**
- Check location permissions
- Verify GPS is enabled
- Test in outdoor environment
- Check for mock locations

**Payments Not Processing:**
- Verify API keys are correct
- Check webhook endpoints
- Review Stripe dashboard
- Test with Stripe test cards

**Geofence Not Triggering:**
- Verify store coordinates
- Check geofence radius settings
- Test with accurate GPS
- Monitor geofence events

**API Errors:**
- Check error logs
- Verify authentication
- Review rate limits
- Test individual endpoints

### Getting Help
- Check error logs first
- Review this documentation
- Test with minimal reproduction
- Contact support with detailed information

---

**Congratulations!** Your Geofence Advertising App is now fully deployed and ready for production use. Monitor the system regularly and enjoy your location-based advertising platform!