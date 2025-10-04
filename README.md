# Complete Notification System

This project consists of a complete notification system with a PHP/MySQL backend and an Android app that polls for notifications every 30 minutes using WorkManager.

## System Architecture

### Backend (PHP/MySQL)
- **RESTful API** with JWT authentication
- **MySQL PDO** for secure database operations
- **Notification queue system** for reliable delivery
- **Retry mechanism** with exponential backoff
- **Multiple notification types** with priority levels

### Android App
- **Modern architecture** with MVVM pattern
- **Hilt dependency injection** for clean code
- **WorkManager** for background polling every 30 minutes
- **Retrofit** for networking
- **Material Design** UI components
- **Local notifications** for immediate user feedback

## Features

### Backend Features
- User registration and authentication
- JWT token-based authentication
- CRUD operations for notifications
- Notification queue processing
- Scheduled notifications
- Multiple notification types (general, reminder, alert, update, urgent)
- Priority levels (low, medium, high, urgent)
- Retry mechanism for failed notifications
- RESTful API endpoints

### Android App Features
- User login/registration
- Background notification polling every 30 minutes
- Local notification display
- Notification management (mark as read, delete)
- Create new notifications
- Offline capability with local caching
- Material Design UI
- Auto-reschedule on device reboot

## Backend Setup

### Prerequisites
- PHP 8.2+
- MySQL/MariaDB
- Apache/Nginx web server

### Installation

1. **Database Setup**
```bash
mysql -u root -p < database_schema.sql
```

2. **Configure Database Connection**
Edit `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'notification_system');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

3. **Set JWT Secret**
Edit `config.php`:
```php
define('JWT_SECRET', 'your-secret-key-here');
```

4. **Copy Files to Web Server**
```bash
cp *.php /var/www/html/
```

5. **Set Up Cron Job for Queue Processing**
```bash
# Add to crontab
* * * * * /usr/bin/php /var/www/html/queue_processor.php >> /var/log/notification_queue.log 2>&1
```

### API Endpoints

#### Authentication
- `POST /api.php/register` - Register new user
- `POST /api.php/login` - Login user

#### Users
- `GET /api.php/users` - Get current user info
- `PUT /api.php/users` - Update user info

#### Notifications
- `GET /api.php/notifications` - Get user notifications
- `GET /api.php/notifications/{id}` - Get specific notification
- `POST /api.php/notifications` - Create notification
- `PUT /api.php/notifications/{id}` - Mark as read
- `DELETE /api.php/notifications/{id}` - Delete notification

#### Notification Types
- `GET /api.php/notification-types` - Get all notification types

### Example API Usage

#### Register User
```bash
curl -X POST http://localhost/api.php/register \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","email":"test@example.com","password":"testpass123"}'
```

#### Login
```bash
curl -X POST http://localhost/api.php/login \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"testpass123"}'
```

#### Create Notification
```bash
curl -X POST http://localhost/api.php/notifications \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"title":"Test Notification","message":"This is a test"}'
```

#### Get Notifications
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost/api.php/notifications
```

## Android App Setup

### Prerequisites
- Android Studio Arctic Fox or later
- Android SDK 21+ (Android 5.0+)
- Kotlin 1.9+

### Configuration

1. **Update API Base URL**
Edit `app/build.gradle`:
```gradle
buildConfigField "String", "API_BASE_URL", "&quot;http://your-server-ip/api.php/&quot;"
```

2. **Sync Dependencies**
The app uses the following key dependencies:
- Hilt for dependency injection
- Retrofit for networking
- WorkManager for background tasks
- Room for local database (optional)
- Material Components for UI

### Features

#### Background Polling
- Uses WorkManager for reliable background execution
- Polls every 30 minutes for new notifications
- Respects battery optimization and network constraints
- Auto-resumes after device reboot

#### Notification Display
- Shows local notifications for unread items
- Groups notifications by priority
- Supports different notification types
- Allows marking as read and deletion

#### User Interface
- Clean Material Design
- Swipe-to-refresh functionality
- Floating action button for creating notifications
- Responsive layout for different screen sizes

### Building the App

1. **Clone the Repository**
```bash
git clone <repository-url>
cd NotificationApp
```

2. **Open in Android Studio**
- Open Android Studio
- Select "Open an existing Android Studio project"
- Choose the `NotificationApp` directory

3. **Build and Run**
- Click the Run button in Android Studio
- Select your device or emulator

### Testing Background Polling

To test the background polling:

1. **Force Immediate Execution**
```bash
adb shell am broadcast -a com.example.notificationapp.FORCE_POLL
```

2. **Check WorkManager Status**
Use Android Studio's App Inspector to view WorkManager jobs.

3. **Simulate Device Reboot**
```bash
adb shell am broadcast -a android.intent.action.BOOT_COMPLETED
```

## System Flow

### Notification Creation Flow
1. User creates notification via Android app or API
2. Backend stores notification in database
3. Notification is added to processing queue
4. Queue processor picks up notification
5. Notification is marked as sent
6. Android app polls and receives notification
7. Local notification is displayed to user

### Background Polling Flow
1. WorkManager schedules periodic work (30 minutes)
2. Worker checks for new notifications
3. If new notifications found, display local notifications
4. Update UI if app is open
5. Schedule next polling cycle

## Security Considerations

### Backend Security
- JWT tokens with expiration
- Password hashing with bcrypt
- SQL injection prevention with PDO prepared statements
- CORS configuration
- Input validation and sanitization

### Android App Security
- Token storage in encrypted SharedPreferences
- Network security configuration
- Certificate pinning (optional)
- ProGuard/R8 obfuscation for release builds

## Performance Optimization

### Backend
- Database indexing for fast queries
- Queue processing with batch operations
- Efficient pagination for large datasets
- Connection pooling

### Android App
- Efficient RecyclerView with ViewHolder pattern
- Image loading optimization
- Background thread for network operations
- Battery-efficient polling intervals

## Troubleshooting

### Common Backend Issues

**Database Connection Errors**
- Check database credentials in `config.php`
- Ensure MariaDB/MySQL is running
- Verify database exists and user has permissions

**Authentication Failures**
- Check JWT secret key configuration
- Verify token expiration settings
- Ensure proper token validation

**Queue Processing Issues**
- Check cron job configuration
- Verify file permissions for logs
- Monitor error logs for specific issues

### Common Android Issues

**Network Connectivity**
- Check internet permissions in manifest
- Verify API base URL configuration
- Test network connectivity

**Background Polling Not Working**
- Check WorkManager constraints
- Verify battery optimization settings
- Test on different Android versions

**Notification Display Issues**
- Check notification channel configuration
- Verify notification permissions
- Test on different devices

## Future Enhancements

### Backend
- Firebase Cloud Messaging integration
- Email notification support
- WebSocket real-time notifications
- Advanced scheduling options
- Notification analytics

### Android App
- Firebase Cloud Messaging integration
- Offline mode with sync
- Rich media notifications
- Notification categories
- Dark mode support

## License

This project is open source and available under the MIT License.

## Support

For issues and questions:
- Create an issue in the repository
- Check the troubleshooting section
- Review the API documentation