# 🍽️ MealDeal - Food Surplus Redistribution Platform

[![Flutter](https://img.shields.io/badge/Flutter-02569B?style=for-the-badge&logo=flutter&logoColor=white)](https://flutter.dev/)
[![Firebase](https://img.shields.io/badge/Firebase-FFCA28?style=for-the-badge&logo=firebase&logoColor=black)](https://firebase.google.com/)
[![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](LICENSE)

**MealDeal** is a comprehensive food surplus redistribution platform that connects food providers (restaurants, cafes, bakeries) with consumers to reduce food waste and provide affordable meals. The platform includes a Flutter mobile app, PHP backend services, and a web admin dashboard.

## 🌟 Key Features

### 📱 Mobile App (Flutter)
- **Dual Role System**: Food providers and consumers with role-based interfaces
- **Real-time Messaging**: In-app chat system for communication
- **Location Services**: GPS-based pickup location management
- **Image Upload**: Photo capture for food listings
- **Push Notifications**: Real-time updates and alerts
- **Order Management**: Complete order lifecycle tracking
- **Analytics Dashboard**: Provider performance metrics

### 🌐 Web Admin Dashboard
- **User Management**: Comprehensive user account administration
- **Report System**: Content moderation and user reporting
- **Leaderboard**: Gamified ranking system for top performers
- **Impact Tracking**: Food waste reduction metrics
- **Pricing Control**: Automated discount enforcement (50% minimum)
- **Analytics**: Real-time statistics and insights

### 🔧 Backend Services (PHP)
- **Firebase Integration**: Cloud Firestore database
- **Authentication**: Secure user authentication and authorization
- **API Endpoints**: RESTful services for mobile and web clients
- **Validation**: Input validation and security measures
- **File Management**: Image upload and storage handling

## 🏗️ Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Flutter App   │    │  Web Admin      │    │  PHP Backend    │
│   (Mobile)      │◄──►│  Dashboard      │◄──►│  Services       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌─────────────────┐
                    │   Firebase      │
                    │   (Database)    │
                    └─────────────────┘
```

## 📁 Project Structure

```
MealDeal/
├── flutter_application_1/          # Flutter mobile app
│   ├── lib/
│   │   ├── features/              # Feature-based architecture
│   │   │   ├── auth/             # Authentication features
│   │   │   ├── consumer/         # Consumer-specific features
│   │   │   ├── provider/         # Provider-specific features
│   │   │   ├── messaging/        # Chat and messaging
│   │   │   └── welcome/          # Onboarding screens
│   │   ├── services/             # Business logic services
│   │   ├── models/               # Data models
│   │   └── widgets/              # Reusable UI components
│   ├── android/                  # Android-specific configuration
│   ├── ios/                      # iOS-specific configuration
│   └── pubspec.yaml             # Flutter dependencies
├── web_admin/                    # Web admin dashboard
│   ├── assets/                   # CSS, JS, and static files
│   ├── api/                      # API endpoints
│   ├── config/                   # Configuration files
│   ├── includes/                 # Shared PHP includes
│   └── *.php                     # Dashboard pages
├── backend/                      # PHP backend services
│   ├── auth/                     # Authentication services
│   ├── cart/                     # Shopping cart logic
│   ├── listings/                 # Food listing management
│   └── config/                   # Firebase configuration
└── README.md                     # This file
```

## 🚀 Getting Started

### Prerequisites

- **Flutter SDK** (3.6.0 or higher)
- **Dart SDK** (3.0.0 or higher)
- **PHP** (7.4 or higher)
- **Composer** (for PHP dependencies)
- **Firebase Project** with Firestore enabled
- **Android Studio** or **Xcode** (for mobile development)

### Installation

#### 1. Clone the Repository

```bash
git clone https://github.com/UNIIIIK/MealDeal.git
cd MealDeal
```

#### 2. Flutter Mobile App Setup

```bash
cd flutter_application_1

# Install Flutter dependencies
flutter pub get

# Configure Firebase
# 1. Create a Firebase project at https://console.firebase.google.com/
# 2. Enable Authentication and Firestore
# 3. Download google-services.json (Android) and GoogleService-Info.plist (iOS)
# 4. Place them in android/app/ and ios/Runner/ respectively

# Run the app
flutter run
```

#### 3. Web Admin Dashboard Setup

```bash
cd web_admin

# Install PHP dependencies
composer install

# Configure Firebase
# 1. Download Firebase service account key from Firebase Console
# 2. Save as config/firebase-credentials.json
# 3. Update config/database.php with your Firebase project ID

# Start local server
php -S localhost:8000
```

#### 4. Backend Services Setup

```bash
cd backend

# Install PHP dependencies
composer install

# Configure Firebase credentials
# Copy your Firebase service account key to config/firebase-credentials.json
```

## 🔧 Configuration

### Firebase Setup

1. **Create Firebase Project**:
   - Go to [Firebase Console](https://console.firebase.google.com/)
   - Create a new project named "MealDeal"
   - Enable Authentication (Email/Password)
   - Enable Firestore Database

2. **Configure Authentication**:
   - Enable Email/Password authentication
   - Set up custom claims for user roles
   - Configure security rules

3. **Firestore Security Rules**:
```javascript
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    // Users can read/write their own data
    match /users/{userId} {
      allow read, write: if request.auth != null && request.auth.uid == userId;
    }
    
    // Listings are readable by all authenticated users
    match /listings/{listingId} {
      allow read: if request.auth != null;
      allow write: if request.auth != null && 
        request.auth.uid == resource.data.provider_id;
    }
    
    // Cart items are user-specific
    match /cart/{cartId} {
      allow read, write: if request.auth != null && 
        request.auth.uid == resource.data.consumer_id;
    }
  }
}
```

### Environment Variables

Create a `.env` file in the root directory:

```env
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_API_KEY=your-api-key
FIREBASE_AUTH_DOMAIN=your-project.firebaseapp.com
FIREBASE_STORAGE_BUCKET=your-project.appspot.com
```

## 📱 Mobile App Features

### For Food Providers
- **Create Listings**: Upload photos and details of surplus food
- **Manage Orders**: Track and fulfill customer orders
- **Analytics**: View performance metrics and earnings
- **Location Management**: Set up pickup locations
- **Messaging**: Communicate with customers

### For Food Consumers
- **Browse Listings**: Discover available food deals
- **Place Orders**: Add items to cart and checkout
- **Track Orders**: Monitor order status and pickup details
- **Messaging**: Chat with food providers
- **Order History**: View past purchases

## 🌐 Web Admin Features

### Dashboard
- **Real-time Statistics**: User counts, active listings, reports
- **Quick Actions**: Review reports, manage users, moderate content

### User Management
- **Account Administration**: View, edit, suspend user accounts
- **Role Management**: Assign provider/consumer roles
- **Dispute Resolution**: Handle user conflicts

### Content Moderation
- **Report Review**: Process user-generated reports
- **Warning System**: Issue warnings and bans
- **Content Verification**: Manual and automated checks

### Analytics
- **Impact Metrics**: Track food waste reduction
- **User Engagement**: Monitor platform usage
- **Performance Reports**: Generate insights and reports

## 🔐 Security Features

- **Authentication**: Firebase Auth with custom claims
- **Authorization**: Role-based access control
- **Input Validation**: XSS and injection prevention
- **File Upload Security**: Image validation and virus scanning
- **API Security**: Rate limiting and request validation
- **Data Encryption**: Secure data transmission and storage

## 🧪 Testing

### Flutter App Testing

```bash
cd flutter_application_1

# Run unit tests
flutter test

# Run integration tests
flutter test integration_test/

# Run widget tests
flutter test test/
```

### PHP Backend Testing

```bash
cd backend

# Run PHP unit tests
vendor/bin/phpunit

# Test API endpoints
php test/firebase_test.php
```

## 🚀 Deployment

### Mobile App Deployment

#### Android
```bash
cd flutter_application_1

# Build APK
flutter build apk --release

# Build App Bundle
flutter build appbundle --release
```

#### iOS
```bash
cd flutter_application_1

# Build iOS app
flutter build ios --release
```

### Web Admin Deployment

1. **Upload files** to web server
2. **Configure web server** (Apache/Nginx)
3. **Set up SSL certificate**
4. **Configure environment variables**
5. **Test all functionality**

### Backend Services Deployment

1. **Deploy PHP files** to web server
2. **Configure PHP** settings
3. **Set up cron jobs** for scheduled tasks
4. **Monitor logs** and performance

## 📊 Performance Optimization

### Mobile App
- **Image Optimization**: Compress and resize images
- **Lazy Loading**: Load content on demand
- **Caching**: Implement local caching strategies
- **Code Splitting**: Reduce app bundle size

### Web Admin
- **CDN**: Use CDN for static assets
- **Caching**: Implement Redis/Memcached
- **Database**: Optimize Firestore queries
- **Compression**: Enable gzip compression

## 🤝 Contributing

1. **Fork the repository**
2. **Create a feature branch**: `git checkout -b feature/amazing-feature`
3. **Commit changes**: `git commit -m 'Add amazing feature'`
4. **Push to branch**: `git push origin feature/amazing-feature`
5. **Open a Pull Request**

### Development Guidelines

- Follow Flutter/Dart style guidelines
- Write comprehensive tests
- Update documentation
- Follow semantic versioning
- Use conventional commits

## 📈 Roadmap

### Phase 1 (Current)
- ✅ Basic mobile app functionality
- ✅ Web admin dashboard
- ✅ Firebase integration
- ✅ User authentication

### Phase 2 (Planned)
- 🔄 Push notifications
- 🔄 Payment integration
- 🔄 Advanced analytics
- 🔄 Multi-language support

### Phase 3 (Future)
- 📅 AI-powered recommendations
- 📅 Social features
- 📅 API for third-party integrations
- 📅 Advanced reporting tools

## 🐛 Known Issues

- Image upload may fail on slow connections
- Push notifications require device registration
- Some UI elements may not be fully responsive
- Offline functionality is limited

## 📞 Support

- **Documentation**: Check this README and inline code comments
- **Issues**: Create an issue in the GitHub repository
- **Email**: Contact the development team
- **Discord**: Join our community server

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Flutter Team** for the amazing framework
- **Firebase Team** for backend services
- **Bootstrap** for web UI components
- **Open Source Community** for various packages and libraries

## 📊 Project Statistics

- **Languages**: Dart (65.6%), PHP (24.3%), C++ (3.2%), JavaScript (1.4%)
- **Lines of Code**: 15,000+ lines
- **Features**: 25+ major features
- **Platforms**: Android, iOS, Web
- **Database**: Firebase Firestore

---

**Built with ❤️ for reducing food waste and helping the environment**

*MealDeal - Making surplus food accessible to everyone*
