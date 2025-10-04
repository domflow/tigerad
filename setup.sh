#!/bin/bash

# Complete Notification System Setup Script
# This script sets up both the backend and provides instructions for the Android app

set -e

echo "==================================="
echo "Notification System Setup"
echo "==================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   print_error "This script should not be run as root for security reasons"
   exit 1
fi

# Get user input
read -p "Enter MySQL root password: " -s MYSQL_ROOT_PASS
echo
read -p "Enter database name (default: notification_system): " DB_NAME
DB_NAME=${DB_NAME:-notification_system}
read -p "Enter database user (default: notification_user): " DB_USER
DB_USER=${DB_USER:-notification_user}
read -p "Enter database password (default: password123): " DB_PASS
DB_PASS=${DB_PASS:-password123}
read -p "Enter your server IP/domain (default: localhost): " SERVER_IP
SERVER_IP=${SERVER_IP:-localhost}

print_status "Starting setup with the following configuration:"
echo "Database: $DB_NAME"
echo "Database User: $DB_USER"
echo "Server: $SERVER_IP"

# Install dependencies
print_status "Installing dependencies..."
sudo apt-get update
sudo apt-get install -y php php-mysql php-pdo php-json php-curl mariadb-server apache2

# Start services
print_status "Starting services..."
sudo service mariadb start
sudo service apache2 start

# Create database
print_status "Creating database and user..."
sudo mysql -u root -p$MYSQL_ROOT_PASS << EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

# Import database schema
print_status "Importing database schema..."
sudo mysql -u $DB_USER -p$DB_PASS $DB_NAME < database_schema.sql

# Update configuration
print_status "Updating configuration files..."
sudo cp *.php /var/www/html/
sudo sed -i "s/define('DB_NAME', 'notification_system');/define('DB_NAME', '$DB_NAME');/" /var/www/html/config.php
sudo sed -i "s/define('DB_USER', 'notification_user');/define('DB_USER', '$DB_USER');/" /var/www/html/config.php
sudo sed -i "s/define('DB_PASS', 'password123');/define('DB_PASS', '$DB_PASS');/" /var/www/html/config.php

# Generate random JWT secret
JWT_SECRET=$(openssl rand -base64 32)
sudo sed -i "s/define('JWT_SECRET', 'your-secret-key-here-change-this');/define('JWT_SECRET', '$JWT_SECRET');/" /var/www/html/config.php

# Set permissions
print_status "Setting file permissions..."
sudo chown -R www-data:www-data /var/www/html/
sudo chmod 644 /var/www/html/*.php

# Set up cron job
print_status "Setting up cron job for queue processing..."
CRON_JOB="* * * * * /usr/bin/php /var/www/html/queue_processor.php >> /var/log/notification_queue.log 2>&1"
(crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -

# Test backend
print_status "Testing backend API..."
REGISTER_RESPONSE=$(curl -s -X POST http://$SERVER_IP/api.php/register \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","email":"test@example.com","password":"testpass123"}')

if echo "$REGISTER_RESPONSE" | grep -q "User created successfully"; then
    print_status "✓ Registration endpoint working"
else
    print_error "✗ Registration endpoint failed"
    echo "$REGISTER_RESPONSE"
fi

# Extract token for further testing
TOKEN=$(echo "$REGISTER_RESPONSE" | php -r "
    \\$json = file_get_contents('php://stdin');
    \\$data = json_decode(\\$json, true);
    echo \\$data['token'] ?? '';
")

# Test login
LOGIN_RESPONSE=$(curl -s -X POST http://$SERVER_IP/api.php/login \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"testpass123"}')

if echo "$LOGIN_RESPONSE" | grep -q "Login successful"; then
    print_status "✓ Login endpoint working"
else
    print_error "✗ Login endpoint failed"
fi

# Test notification creation
NOTIFICATION_RESPONSE=$(curl -s -X POST http://$SERVER_IP/api.php/notifications \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"title":"Test Notification","message":"Setup script test notification"}')

if echo "$NOTIFICATION_RESPONSE" | grep -q "Notification created successfully"; then
    print_status "✓ Notification creation working"
else
    print_error "✗ Notification creation failed"
fi

# Test notification retrieval
GET_RESPONSE=$(curl -s -H "Authorization: Bearer $TOKEN" http://$SERVER_IP/api.php/notifications)

if echo "$GET_RESPONSE" | grep -q "notifications"; then
    print_status "✓ Notification retrieval working"
else
    print_error "✗ Notification retrieval failed"
fi

print_status "Backend setup completed successfully!"

# Android app instructions
echo
echo "==================================="
echo "Android App Setup Instructions"
echo "==================================="
echo
echo "1. Open the NotificationApp folder in Android Studio"
echo "2. Update the API base URL in app/build.gradle:"
echo "   buildConfigField &quot;String&quot;, &quot;API_BASE_URL&quot;, \\&quot;http://$SERVER_IP/api.php/\\&quot;"
echo
echo "3. Build and run the app on your device/emulator"
echo
echo "4. Login with the test credentials:"
echo "   Username: testuser"
echo "   Password: testpass123"
echo
echo "5. The app will automatically start polling for notifications every 30 minutes"
echo
echo "6. To test immediately, you can force a poll by:"
echo "   - Creating a new notification in the app"
echo "   - Or running: adb shell am broadcast -a com.example.notificationapp.FORCE_POLL"
echo

print_status "Setup completed! Check the README.md file for detailed documentation."

# Save configuration
cat > notification_system_config.txt << EOF
Notification System Configuration
=================================
Database: $DB_NAME
Database User: $DB_USER
Database Password: $DB_PASS
Server: $SERVER_IP
API Base URL: http://$SERVER_IP/api.php/
JWT Secret: $JWT_SECRET

Test Credentials:
Username: testuser
Password: testpass123
Email: test@example.com
EOF

print_status "Configuration saved to notification_system_config.txt"