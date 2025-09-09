# MealDeal - Food Surplus Redistribution App

A Flutter application that connects food providers with consumers to reduce food waste by redistributing surplus food at discounted prices.

## 🚀 Features

### For Food Providers
- **Create Listings**: Post surplus food items with photos, descriptions, and discounted prices
- **Manage Listings**: View and edit your active food listings
- **Analytics Dashboard**: Track your impact with an enhanced bar chart showing:
  - Daily food savings
  - Total orders processed
  - Interactive tooltips with detailed information
  - Animated charts with smooth transitions
- **Profile Management**: Update your business information

### For Food Consumers
- **Browse Feed**: Discover available food deals in your area
- **Claim Orders**: Reserve food items and complete purchases
- **Order History**: Track your claimed orders and savings
- **Profile Management**: Manage your account information

## 🛠️ Technology Stack

- **Frontend**: Flutter (Dart)
- **Backend**: Firebase (Firestore, Authentication)
- **Authentication**: Firebase Auth with role-based access
- **Database**: Cloud Firestore
- **State Management**: Provider pattern
- **Charts**: Custom Flutter widgets with animations

## 📱 Screenshots

The app features a modern, intuitive interface with:
- Role-based navigation (Provider/Consumer)
- Enhanced analytics with interactive bar charts
- Real-time data synchronization
- Responsive design for various screen sizes

## 🔧 Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/UNIIIIK/MealDeal.git
   cd MealDeal
   ```

2. **Install Flutter dependencies**
   ```bash
   flutter pub get
   ```

3. **Firebase Setup**
   - Create a Firebase project
   - Enable Authentication and Firestore
   - Download `google-services.json` (Android) and `GoogleService-Info.plist` (iOS)
   - Place them in the appropriate directories

4. **Run the app**
   ```bash
   flutter run
   ```

## 📊 Analytics Features

The analytics screen includes:
- **Interactive Bar Chart**: Visual representation of daily food savings
- **Statistics Cards**: Total orders and total savings at a glance
- **Tooltips**: Hover over bars to see detailed information
- **Animations**: Smooth transitions and loading effects
- **Responsive Design**: Adapts to different screen sizes

## 🎨 UI/UX Highlights

- **Material Design 3**: Modern, clean interface
- **Green Color Scheme**: Represents sustainability and eco-friendliness
- **Smooth Animations**: Enhanced user experience with fluid transitions
- **Accessibility**: Proper contrast ratios and readable fonts
- **Cross-Platform**: Works on Android, iOS, and Web

## 🔐 Authentication

- Firebase Authentication with email/password
- Role-based access control (Provider/Consumer)
- Secure user data management
- Profile customization

## 📈 Impact Tracking

The app helps track the environmental and economic impact:
- Amount of food saved from waste
- Number of orders processed
- Daily, weekly, and monthly analytics
- Provider performance metrics

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Flutter team for the amazing framework
- Firebase for backend services
- The open-source community for various packages used

---

**Made with ❤️ to reduce food waste and help the environment**