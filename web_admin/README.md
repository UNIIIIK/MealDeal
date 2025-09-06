# MealDeal Super Admin Dashboard

A comprehensive web-based admin dashboard for managing the MealDeal food surplus redistribution platform. Built with PHP, HTML, CSS (Bootstrap), and JavaScript, connected to Firebase Firestore.

## 🚀 Features

### 🔍 Report Management
- **Review System**: Admin reviews user reports with fair warning system
- **Warning Levels**: First, second, and final warnings before banning
- **Ban System**: Permanent account suspension for serious violations
- **Report Categories**: Inappropriate content, poor quality, fake listings, spam

### 🏆 Live Leaderboard
- **Top Providers**: Ranked by food saved and listings created
- **Top Consumers**: Ranked by orders placed and money saved
- **Time Filters**: Daily, weekly, monthly, and all-time rankings
- **Gamification**: Reward system for top performers

### 📊 Impact Tracking
- **Food Waste Reduction**: Track total kilograms of food saved
- **User Contributions**: Individual impact metrics
- **Visual Analytics**: Charts and graphs for data visualization
- **Environmental Impact**: Carbon footprint reduction tracking

### 👥 User Management
- **Account Management**: View, edit, and manage user accounts
- **Role Management**: Provider and consumer role administration
- **Dispute Resolution**: Handle user conflicts and issues
- **Account Status**: Active, suspended, and banned user management

### 📝 Content Moderation
- **Listing Review**: Manual and automated content verification
- **Image Analysis**: Quality and appropriateness checking
- **Report-Based Flags**: User-reported content review
- **Random Inspections**: Periodic content quality audits

### 💰 Pricing Control
- **Minimum Discount**: Enforce 50% minimum discount from retail price
- **Automated Pricing**: System-calculated surplus pricing
- **Compliance Monitoring**: Automated violation detection
- **Price Audits**: Regular pricing pattern analysis

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+
- **Database**: Firebase Firestore
- **Frontend**: HTML5, CSS3, Bootstrap 5
- **JavaScript**: ES6+, Chart.js
- **Authentication**: Firebase Auth
- **Dependencies**: Composer (PHP)

## 📁 Folder Structure

```
web_admin/
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       ├── admin.js
│       ├── reports.js
│       └── leaderboard.js
├── config/
│   └── database.php
├── includes/
│   └── auth.php
├── api/
│   ├── get_stats.php
│   ├── get_recent_reports.php
│   └── send_reward.php
├── index.php
├── login.php
├── logout.php
├── reports.php
├── leaderboard.php
├── users.php
├── listings.php
├── impact.php
├── pricing.php
├── composer.json
└── README.md
```

## 🔧 Setup Instructions

### 1. Prerequisites
- PHP 7.4 or higher
- Composer
- Web server (Apache/Nginx)
- Firebase project with Firestore enabled

### 2. Installation

```bash
# Clone or download the admin dashboard
cd web_admin

# Install PHP dependencies
composer install

# Create Firebase credentials file
# Download your Firebase service account key from Firebase Console
# Save it as config/firebase-credentials.json
```

### 3. Configuration

1. **Firebase Setup**:
   - Go to [Firebase Console](https://console.firebase.google.com/)
   - Create a new project or use existing MealDeal project
   - Enable Firestore Database
   - Go to Project Settings > Service Accounts
   - Generate new private key
   - Save as `config/firebase-credentials.json`

2. **Update Database Configuration**:
   - Edit `config/database.php`
   - Replace `your-firebase-project-id` with your actual Firebase project ID
   - Ensure the path to `firebase-credentials.json` is correct

3. **Create Admin Account**:
   - Add an admin document to your Firestore `admins` collection:
   ```json
   {
     "name": "Super Admin",
     "email": "admin@mealdeal.com",
     "password": "$2y$10$hashedpassword",
     "role": "super_admin",
     "created_at": "timestamp"
   }
   ```

### 4. Web Server Configuration

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

#### Nginx
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

## 🔐 Security Features

- **Session Management**: Secure PHP sessions
- **Password Hashing**: bcrypt password encryption
- **Input Validation**: XSS and SQL injection prevention
- **CSRF Protection**: Cross-site request forgery prevention
- **Access Control**: Role-based authentication
- **HTTPS Enforcement**: Secure communication

## 📊 Dashboard Features

### Real-time Statistics
- Total users (providers and consumers)
- Active food listings
- Pending reports requiring review
- Total food saved (in kilograms)

### Quick Actions
- Review pending reports
- Manage user accounts
- Moderate content
- View leaderboard

### Advanced Filtering
- Date range filters
- Status-based filtering
- User role filtering
- Report type categorization

## 🎨 UI/UX Features

- **Responsive Design**: Works on desktop, tablet, and mobile
- **Modern Interface**: Clean, professional Bootstrap 5 design
- **Interactive Elements**: Hover effects, animations, and transitions
- **Accessibility**: WCAG compliant design
- **Dark Mode Support**: Optional dark theme
- **Print Styles**: Optimized for printing reports

## 🔄 API Endpoints

### Statistics
- `GET /api/get_stats.php` - Get dashboard statistics
- `GET /api/get_recent_reports.php` - Get recent reports

### User Management
- `POST /api/update_user.php` - Update user status
- `GET /api/get_user_details.php` - Get user information

### Reports
- `POST /api/update_report.php` - Update report status
- `POST /api/issue_warning.php` - Issue user warning

### Rewards
- `POST /api/send_reward.php` - Send reward to user

## 🚀 Deployment

### Local Development
```bash
# Start PHP development server
php -S localhost:8000

# Access dashboard at http://localhost:8000
```

### Production Deployment
1. Upload files to web server
2. Set proper file permissions (755 for directories, 644 for files)
3. Configure web server (Apache/Nginx)
4. Set up SSL certificate
5. Configure environment variables
6. Test all functionality

## 🐛 Troubleshooting

### Common Issues

1. **Firebase Connection Error**:
   - Verify service account key file path
   - Check Firebase project ID
   - Ensure Firestore is enabled

2. **Permission Denied**:
   - Check file permissions
   - Verify web server user permissions
   - Ensure config directory is readable

3. **Session Issues**:
   - Check PHP session configuration
   - Verify session storage permissions
   - Clear browser cookies

### Debug Mode
Enable debug mode by setting:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📈 Performance Optimization

- **Caching**: Implement Redis/Memcached for session storage
- **CDN**: Use CDN for static assets (CSS, JS, images)
- **Database**: Optimize Firestore queries with proper indexing
- **Compression**: Enable gzip compression
- **Minification**: Minify CSS and JavaScript files

## 🔄 Updates and Maintenance

### Regular Tasks
- Monitor error logs
- Review pending reports daily
- Update user statistics weekly
- Backup Firestore data monthly
- Review security settings quarterly

### Version Updates
- Keep PHP version updated
- Update Composer dependencies
- Monitor Firebase SDK updates
- Test functionality after updates

## 📞 Support

For technical support or feature requests:
- Create an issue in the GitHub repository
- Contact the development team
- Check the documentation

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

---

**Built with ❤️ for reducing food waste and helping the environment**
