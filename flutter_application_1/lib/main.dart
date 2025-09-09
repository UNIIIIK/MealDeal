import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:provider/provider.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:intl/intl.dart';
import 'firebase_options.dart';
import 'features/auth/auth_service.dart';
import 'features/welcome/welcome_screen.dart';
import 'features/consumer/feed_screen.dart';
import 'features/consumer/edit_profile_screen.dart';
import 'features/provider/analytics_screen.dart';
import 'features/provider/my_listings_screen.dart';
import 'features/provider/location_management_screen.dart';
import 'features/consumer/my_claims_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );
  // Ensure clean auth state during dev reloads
  try {
    await FirebaseAuth.instance.signOut();
  } catch (_) {}
  runApp(const MealDealApp()  );
}

class MealDealApp extends StatelessWidget {
  const MealDealApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthService()),
      ],
      child: MaterialApp(
        title: 'MealDeal - Food Surplus Redistribution',
        theme: ThemeData(
          colorScheme: ColorScheme.fromSeed(
            seedColor: Colors.green,
            brightness: Brightness.light,
          ),
          useMaterial3: true,
          inputDecorationTheme: InputDecorationTheme(
            filled: true,
            fillColor: Colors.white,
            hintStyle: TextStyle(color: Colors.grey.shade600),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(20),
              borderSide: BorderSide(color: Colors.grey.shade300),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(20),
              borderSide: BorderSide(color: Colors.green.shade400, width: 1.5),
            ),
            errorBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(20),
              borderSide: BorderSide(color: Colors.red.shade300),
            ),
            focusedErrorBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(20),
              borderSide: BorderSide(color: Colors.red.shade400, width: 1.5),
            ),
          ),
          appBarTheme: AppBarTheme(
            backgroundColor: Colors.green.shade50,
            foregroundColor: Colors.green.shade800,
            elevation: 0,
          ),
        ),
        home: const MainNavigationScreen(),
        debugShowCheckedModeBanner: false,
      ),
    );
  }
}

class MainNavigationScreen extends StatefulWidget {
  const MainNavigationScreen({super.key});

  @override
  State<MainNavigationScreen> createState() => _MainNavigationScreenState();
}

class _MainNavigationScreenState extends State<MainNavigationScreen> {
  int _selectedIndex = 0;
  String? _lastUserRole;

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthService>(
      builder: (context, authService, child) {
        // Show welcome screen if not logged in
        if (!authService.isLoggedIn) {
          return const WelcomeScreen();
        }

        // Show role-based navigation for logged in users
        return _buildAuthenticatedApp(authService);
      },
    );
  }

  Widget _buildAuthenticatedApp(AuthService authService) {
    final isProvider = authService.hasRole('food_provider');
    final isConsumer = authService.hasRole('food_consumer');
    final currentRole = isProvider ? 'provider' : (isConsumer ? 'consumer' : 'none');

    List<Widget> screens;
    List<BottomNavigationBarItem> navItems;

    if (isProvider) {
      screens = [
        const FeedScreen(), // Home
        const AnalyticsScreen(),
        const ProfileScreen(),
      ];
      navItems = [
        const BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Home'),
        const BottomNavigationBarItem(
          icon: Icon(Icons.bar_chart),
          label: 'Analytics',
        ),
        const BottomNavigationBarItem(
          icon: Icon(Icons.person),
          label: 'Profile',
        ),
      ];
    } else if (isConsumer) {
      screens = [const FeedScreen(), const ProfileScreen()];
      navItems = [
        const BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Home'),
        const BottomNavigationBarItem(
          icon: Icon(Icons.person),
          label: 'Profile',
        ),
      ];
    } else {
      // Fallback for users without proper role
      return const WelcomeScreen();
    }

    // Handle role changes and ensure valid index
    if (_lastUserRole != currentRole || _selectedIndex >= navItems.length) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          setState(() {
            _selectedIndex = 0; // Always reset to Home tab
            _lastUserRole = currentRole;
          });
        }
      });
    }

    // Ensure _selectedIndex is within bounds
    final safeIndex = _selectedIndex >= navItems.length ? 0 : _selectedIndex;

    return Scaffold(
      body: IndexedStack(
        index: safeIndex,
        children: screens,
      ),
      bottomNavigationBar: BottomNavigationBar(
        type: BottomNavigationBarType.fixed,
        currentIndex: safeIndex,
        onTap: (index) {
          setState(() {
            _selectedIndex = index;
          });
        },
        selectedItemColor: Colors.green.shade700,
        unselectedItemColor: Colors.grey.shade600,
        items: navItems,
      ),
    );
  }
}

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Consumer<AuthService>(
        builder: (context, authService, child) {
          final userData = authService.userData;
          final isConsumer = authService.hasRole('food_consumer');
          
          return SingleChildScrollView(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // User info card with edit button
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            CircleAvatar(
                              radius: 25,
                              backgroundColor: Colors.green.shade200,
                              child: Text(
                                userData?['name']?[0]?.toUpperCase() ?? 'U',
                                style: TextStyle(
                                  fontSize: 20,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.green.shade800,
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    userData?['name'] ?? 'Unknown User',
                                    style: const TextStyle(
                                      fontSize: 18,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                  Text(
                                    userData?['email'] ?? '',
                                    style: TextStyle(
                                      color: Colors.grey.shade600,
                                      fontSize: 14,
                                    ),
                                  ),
                                  Container(
                                    margin: const EdgeInsets.only(top: 4),
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 6,
                                      vertical: 2,
                                    ),
                                    decoration: BoxDecoration(
                                      color: authService.hasRole('food_provider')
                                          ? Colors.blue.shade100
                                          : Colors.green.shade100,
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Text(
                                      authService.hasRole('food_provider')
                                          ? 'Food Provider'
                                          : 'Food Consumer',
                                      style: TextStyle(
                                        fontSize: 10,
                                        fontWeight: FontWeight.bold,
                                        color: authService.hasRole('food_provider')
                                            ? Colors.blue.shade700
                                            : Colors.green.shade700,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            // Small edit button
                            IconButton(
                              icon: Icon(Icons.edit, size: 18, color: Colors.grey.shade600),
                              onPressed: () {
                                Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (context) => const EditProfileScreen(),
                                  ),
                                );
                              },
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        if (userData?['phone'] != null) ...[
                          Row(
                            children: [
                              const Icon(Icons.phone, size: 14),
                              const SizedBox(width: 6),
                              Text(userData!['phone'], style: const TextStyle(fontSize: 14)),
                            ],
                          ),
                          const SizedBox(height: 6),
                        ],
                        if (userData?['address'] != null) ...[
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Icon(Icons.location_on, size: 14),
                              const SizedBox(width: 6),
                              Expanded(child: Text(userData!['address'], style: const TextStyle(fontSize: 14))),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                
                // Consumer-specific: My Claims section
                if (isConsumer) ...[
                  InkWell(
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (context) => const MyClaimsScreen(),
                        ),
                      );
                    },
                    child: Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16.0),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Icon(Icons.receipt_long, color: Colors.green.shade600, size: 20),
                                const SizedBox(width: 8),
                                const Text(
                                  'My Claims',
                                  style: TextStyle(
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                const Spacer(),
                                const Icon(Icons.chevron_right),
                              ],
                            ),
                            const SizedBox(height: 12),
                            _buildClaimsList(context, authService),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
                
                // Provider notifications bell
                if (authService.hasRole('food_provider'))
                  StreamBuilder<QuerySnapshot>(
                    stream: FirebaseFirestore.instance
                        .collection('notifications')
                        .where('provider_id', isEqualTo: authService.currentUser!.uid)
                        .where('read', isEqualTo: false)
                        .snapshots(),
                    builder: (context, snap) {
                      final unread = snap.data?.docs.length ?? 0;
                      if (unread == 0) return const SizedBox.shrink();
                      return Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Colors.yellow.shade50,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: Colors.yellow.shade200),
                        ),
                        child: Row(
                          children: [
                            Icon(Icons.notifications_active, color: Colors.orange.shade700),
                            const SizedBox(width: 8),
                            Expanded(child: Text('You have $unread new notification(s).')),
                            TextButton(
                              onPressed: () async {
                                final docs = snap.data?.docs ?? [];
                                for (final d in docs) {
                                  await d.reference.update({'read': true});
                                }
                                if (context.mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Marked notifications as read')));
                                }
                              },
                              child: const Text('Mark read'),
                            ),
                          ],
                        ),
                      );
                    },
                  ),

                // Provider-specific: My Listings section
                if (authService.hasRole('food_provider')) ...[
                  InkWell(
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (context) => const MyListingsScreen(),
                        ),
                      );
                    },
                    child: Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16.0),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Icon(Icons.inventory_2, color: Colors.orange.shade600, size: 20),
                                const SizedBox(width: 8),
                                const Text(
                                  'My Listings',
                                  style: TextStyle(
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                const Spacer(),
                                const Icon(Icons.chevron_right),
                              ],
                            ),
                            const SizedBox(height: 12),
                            _buildListingsList(context, authService),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
                
                // Menu options
                if (authService.hasRole('food_provider'))
                  ListTile(
                    leading: const Icon(Icons.location_on),
                    title: const Text('Manage Business Location'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (context) => const LocationManagementScreen(),
                        ),
                      );
                    },
                  ),
                ListTile(
                  leading: const Icon(Icons.help),
                  title: const Text('Help & Support'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () {
                    // Navigate to help
                  },
                ),
                ListTile(
                  leading: const Icon(Icons.info),
                  title: const Text('About'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () {
                    // Show about dialog
                    showAboutDialog(
                      context: context,
                      applicationName: 'MealDeal',
                      applicationVersion: '1.0.0',
                      applicationLegalese: '© 2024 MealDeal. All rights reserved.',
                      children: [
                        const Text('Reducing food waste, one meal at a time.'),
                      ],
                    );
                  },
                ),
                const SizedBox(height: 24),
                
                // Logout button
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () {
                      showDialog(
                        context: context,
                        builder: (context) => AlertDialog(
                          title: const Text('Sign Out'),
                          content: const Text('Are you sure you want to sign out?'),
                          actions: [
                            TextButton(
                              onPressed: () => Navigator.of(context).pop(),
                              child: const Text('Cancel'),
                            ),
                            TextButton(
                              onPressed: () {
                                Navigator.of(context).pop();
                                authService.signOut();
                              },
                              child: const Text('Sign Out'),
                            ),
                          ],
                        ),
                      );
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.red.shade600,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                    child: const Text('Sign Out'),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildClaimsList(BuildContext context, AuthService authService) {
    return StreamBuilder<QuerySnapshot>(
      stream: FirebaseFirestore.instance
          .collection('cart')
          .where('consumer_id', isEqualTo: authService.currentUser!.uid)
          .where('status', whereIn: ['awaiting_pickup', 'claimed'])
          .limit(10)
          .snapshots(),
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }

        if (snapshot.hasError) {
          return Text('Error: ${snapshot.error}');
        }

        if (!snapshot.hasData || snapshot.data!.docs.isEmpty) {
          return Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.grey.shade50,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Column(
              children: [
                Icon(Icons.receipt_long, size: 32, color: Colors.grey.shade400),
                const SizedBox(height: 8),
                Text(
                  'No orders yet',
                  style: TextStyle(
                    fontSize: 14,
                    color: Colors.grey.shade600,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                Text(
                  'Your order history will appear here',
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.grey.shade500,
                  ),
                ),
              ],
            ),
          );
        }

        final docs = snapshot.data!.docs.toList()
          ..sort((a, b) {
            final aMap = a.data() as Map<String, dynamic>?;
            final bMap = b.data() as Map<String, dynamic>?;
            final aTs = aMap?['checkout_date'];
            final bTs = bMap?['checkout_date'];
            if (aTs is! Timestamp && bTs is! Timestamp) return 0;
            if (aTs is! Timestamp) return 1;
            if (bTs is! Timestamp) return -1;
            return bTs.compareTo(aTs);
          });
        return Column(
          children: docs.take(3).map((doc) {
            final data = doc.data() as Map<String, dynamic>;
            return _buildClaimItem(context, data);
          }).toList(),
        );
      },
    );
  }

  Widget _buildClaimItem(BuildContext context, Map<String, dynamic> data) {
    final items = data['items'] as List<dynamic>? ?? [];
    final totalPrice = data['total_price'] ?? 0.0;
    final checkoutDate = (data['checkout_date'] ?? Timestamp.now()) as Timestamp;
    final dateStr = DateFormat('MMM dd, yyyy').format(checkoutDate.toDate());

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: Colors.green.shade100,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(
              Icons.receipt,
              color: Colors.green.shade600,
              size: 20,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${items.length} item${items.length > 1 ? 's' : ''}',
                  style: const TextStyle(
                    fontWeight: FontWeight.w500,
                    fontSize: 14,
                  ),
                ),
                Text(
                  dateStr,
                  style: TextStyle(
                    color: Colors.grey.shade600,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
          Text(
            '₱${totalPrice.toStringAsFixed(0)}',
            style: TextStyle(
              fontWeight: FontWeight.bold,
              color: Colors.green.shade700,
              fontSize: 14,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildListingsList(BuildContext context, AuthService authService) {
    return StreamBuilder<QuerySnapshot>(
      stream: FirebaseFirestore.instance
          .collection('listings')
          .where('provider_id', isEqualTo: authService.currentUser!.uid)
          .where('status', whereIn: ['active', 'sold_out'])
          .limit(10)
          .snapshots(),
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }

        if (snapshot.hasError) {
          return Text('Error: ${snapshot.error}');
        }

        if (!snapshot.hasData || snapshot.data!.docs.isEmpty) {
          return Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.grey.shade50,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Column(
              children: [
                Icon(Icons.inventory_2, size: 32, color: Colors.grey.shade400),
                const SizedBox(height: 8),
                Text(
                  'No listings yet',
                  style: TextStyle(
                    fontSize: 14,
                    color: Colors.grey.shade600,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                Text(
                  'Create your first food listing',
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.grey.shade500,
                  ),
                ),
              ],
            ),
          );
        }

        final docs = snapshot.data!.docs.toList()
          ..sort((a, b) {
            final aMap = a.data() as Map<String, dynamic>?;
            final bMap = b.data() as Map<String, dynamic>?;
            final aTs = aMap?['created_at'];
            final bTs = bMap?['created_at'];
            if (aTs is! Timestamp && bTs is! Timestamp) return 1;
            if (aTs is! Timestamp) return 1;
            if (bTs is! Timestamp) return -1;
            return bTs.compareTo(aTs);
          });
        return Column(
          children: docs.take(3).map((doc) {
            final data = doc.data() as Map<String, dynamic>;
            return _buildListingItem(context, doc.id, data);
          }).toList(),
        );
      },
    );
  }

  Widget _buildListingItem(BuildContext context, String listingId, Map<String, dynamic> data) {
    final title = data['title'] ?? 'Untitled';
    final price = data['discounted_price'] ?? 0.0;
    final quantity = data['quantity'] ?? 0;
    final status = data['status'] ?? 'active';
    final images = (data['images'] as List?)?.cast<String>() ?? [];
    final image = images.isNotEmpty ? images.first : null;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Row(
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: Colors.orange.shade100,
              borderRadius: BorderRadius.circular(8),
            ),
            child: image != null
                ? ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.network(
                      image,
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) {
                        return Icon(
                          Icons.fastfood,
                          color: Colors.orange.shade600,
                          size: 20,
                        );
                      },
                    ),
                  )
                : Icon(
                    Icons.fastfood,
                    color: Colors.orange.shade600,
                    size: 20,
                  ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w500,
                    fontSize: 14,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                Text(
                  '₱${price.toStringAsFixed(0)} • Qty: $quantity',
                  style: TextStyle(
                    color: Colors.grey.shade600,
                    fontSize: 12,
                  ),
                ),
                if (status == 'sold_out')
                  Container(
                    margin: const EdgeInsets.only(top: 4),
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: Colors.orange.shade100,
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      'SOLD OUT',
                      style: TextStyle(
                        fontSize: 10,
                        color: Colors.orange.shade800,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
              ],
            ),
          ),
          Icon(
            status == 'sold_out' ? Icons.pause_circle : Icons.check_circle,
            color: status == 'sold_out' ? Colors.orange.shade600 : Colors.green.shade600,
            size: 20,
          ),
        ],
      ),
    );
  }
}
