import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:provider/provider.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:intl/intl.dart';
import 'dart:convert';

import 'firebase_options.dart';
import 'theme/app_theme.dart';

import 'features/auth/auth_service.dart';
import 'features/auth/email_verification_screen.dart';
import 'services/messaging_service.dart';
import 'services/firestore_helper.dart';

import 'features/welcome/welcome_screen.dart';
import 'features/consumer/feed_screen.dart';
import 'features/consumer/edit_profile_screen.dart';
import 'features/provider/analytics_screen.dart';
import 'features/provider/my_listings_screen.dart';
import 'features/provider/location_management_screen.dart';
import 'features/consumer/my_claims_screen.dart';
import 'features/consumer/my_orders_screen.dart';
import 'features/provider/orders_management_screen.dart';
import 'features/messaging/chat_list_screen.dart';

// ⚠️ IMPORTANT: Changes to this function require HOT RESTART (R), not hot reload (r)
// Press 'R' in terminal or use the restart button in your IDE
void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Fast + safe Firebase initialization
  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );

  // Configure Firestore for better connection handling
  FirestoreHelper.configureFirestore();

  runApp(const MealDealApp());
}

class MealDealApp extends StatelessWidget {
  const MealDealApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        // Lazy loading = created only when needed
        ChangeNotifierProvider<AuthService>(
          lazy: true,
          create: (_) => AuthService(),
        ),
        ChangeNotifierProvider<MessagingService>(
          lazy: true,
          create: (_) => MessagingService(),
        ),
      ],
      child: MaterialApp(
        title: 'MealDeal - Food Surplus Redistribution',
        theme: AppTheme.lightTheme,
        debugShowCheckedModeBanner: false,
        home: const MainNavigationScreen(),
      ),
    );
  }
}

class MainNavigationScreen extends StatefulWidget {
  const MainNavigationScreen({super.key});
  @override
  State<MainNavigationScreen> createState() => _MainNavigationScreenState();
}

class _MainNavigationScreenState extends State<MainNavigationScreen> with WidgetsBindingObserver {
  int _selectedIndex = 0;
  String? _lastRole;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeAppLifecycleState(state);
    // When app returns to foreground, check if email was verified
    if (state == AppLifecycleState.resumed && mounted) {
      final auth = Provider.of<AuthService>(context, listen: false);
      if (auth.isLoggedIn && !auth.isEmailVerified) {
        // Silently check verification status when app resumes
        auth.reloadUser();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthService>(
      builder: (context, auth, _) {
        if (!auth.isLoggedIn) {
          return const WelcomeScreen();
        }
        return _buildForRole(auth);
      },
    );
  }

  Widget _buildForRole(AuthService auth) {
    if (!auth.isEmailVerified) {
      return const EmailVerificationScreen();
    }

    final isProvider = auth.hasRole('food_provider');
    final isConsumer = auth.hasRole('food_consumer');

    final role = isProvider
        ? 'provider'
        : isConsumer
            ? 'consumer'
            : 'none';

    if (role == 'none') return const WelcomeScreen();

    List<Widget> screens;
    List<BottomNavigationBarItem> items;

    if (role == 'provider') {
      screens = [
        const FeedScreen(),
        const OrdersManagementScreen(),
        const ChatListScreen(),
      ];
      items = const [
        BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Home'),
        BottomNavigationBarItem(icon: Icon(Icons.shopping_cart), label: 'Orders'),
        BottomNavigationBarItem(icon: Icon(Icons.message), label: 'Messages'),
      ];
    } else {
      screens = [
        const FeedScreen(),
        const ChatListScreen(),
      ];
      items = const [
        BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Home'),
        BottomNavigationBarItem(icon: Icon(Icons.message), label: 'Messages'),
      ];
    }

    // Reset index when role changes or index is out of bounds
    // Capture role value to avoid closure issues
    final currentRole = role;
    if (_lastRole != currentRole || _selectedIndex >= items.length) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          setState(() {
            if (_lastRole != currentRole) {
              _lastRole = currentRole;
            }
            if (_selectedIndex >= items.length) {
              _selectedIndex = 0;
            }
          });
        }
      });
    }

    final index = _selectedIndex >= items.length ? 0 : _selectedIndex;

    return Scaffold(
      body: IndexedStack(
        index: index,
        children: screens,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.1),
              blurRadius: 10,
              offset: const Offset(0, -2),
            ),
          ],
        ),
        child: BottomNavigationBar(
        currentIndex: index,
        selectedItemColor: Colors.green.shade700,
        unselectedItemColor: Colors.grey.shade600,
          type: BottomNavigationBarType.fixed,
          elevation: 8,
          backgroundColor: Colors.white,
          selectedFontSize: 12,
          unselectedFontSize: 11,
          selectedLabelStyle: const TextStyle(fontWeight: FontWeight.w600),
        items: items,
          onTap: (i) {
            if (mounted && i < items.length && i >= 0) {
              setState(() {
                _selectedIndex = i;
              });
            }
          },
        ),
      ),
    );
  }
}

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final userData = auth.userData;
    final isConsumer = auth.hasRole('food_consumer');

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile'),
        backgroundColor: Colors.green.shade700,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildUserCard(context, auth, userData),
            const SizedBox(height: 16),

            if (isConsumer) _buildPurchases(context),
            if (isConsumer) const SizedBox(height: 16),
            if (isConsumer) _buildClaims(context, auth),
            if (isConsumer) const SizedBox(height: 16),

            if (auth.hasRole('food_provider')) _buildListings(context, auth),
            if (auth.hasRole('food_provider')) const SizedBox(height: 16),

            _buildMenu(context, auth),
            const SizedBox(height: 24),

            _buildLogoutButton(context, auth),
          ],
        ),
      ),
    );
  }

  // Drawer moved to FeedScreen

  Widget _buildUserCard(
      BuildContext context, AuthService auth, dynamic userData) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
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
              child: _userInfo(auth, userData),
            ),
            IconButton(
              icon: Icon(Icons.edit, size: 18, color: Colors.grey.shade600),
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const EditProfileScreen()),
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _userInfo(AuthService auth, dynamic userData) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          userData?['name'] ?? 'Unknown User',
          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
        ),
        Text(
          userData?['email'] ?? '',
          style: TextStyle(color: Colors.grey.shade600, fontSize: 14),
        ),
        Container(
          margin: const EdgeInsets.only(top: 4),
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
          decoration: BoxDecoration(
            color: auth.hasRole('food_provider')
                ? Colors.blue.shade100
                : Colors.green.shade100,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text(
            auth.hasRole('food_provider') ? 'Food Provider' : 'Food Consumer',
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.bold,
              color: auth.hasRole('food_provider')
                  ? Colors.blue.shade700
                  : Colors.green.shade700,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildPurchases(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Text('My Purchases',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                const Spacer(),
                TextButton(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const MyOrdersScreen(),
                      ),
                    );
                  },
                  child: const Row(
                    children: [Text('History'), Icon(Icons.chevron_right)],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                _PurchaseTile(
                    icon: Icons.restaurant_outlined,
                    label: 'Preparing',
                    onTap: () => _openOrders(context, 'pending')),
                _PurchaseTile(
                    icon: Icons.local_shipping_outlined,
                    label: 'Ready for Pickup',
                    onTap: () => _openOrders(context, 'awaiting_pickup')),
                _PurchaseTile(
                    icon: Icons.check_circle_outline,
                    label: 'Picked Up',
                    onTap: () => _openOrders(context, 'claimed')),
              ],
            ),
          ],
        ),
      ),
    );
  }

  void _openOrders(BuildContext context, String filter) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => MyOrdersScreen(initialFilter: filter),
      ),
    );
  }

  Widget _buildClaims(BuildContext context, AuthService auth) {
    return InkWell(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const MyClaimsScreen()),
        );
      },
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              Row(
                children: [
                  Icon(Icons.receipt_long,
                      color: Colors.green.shade600, size: 20),
                  const SizedBox(width: 8),
                  const Text(
                    'My Claims',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  const Spacer(),
                  const Icon(Icons.chevron_right),
                ],
              ),
              const SizedBox(height: 12),
              _buildClaimsPreview(auth),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildClaimsPreview(AuthService auth) {
    return StreamBuilder<QuerySnapshot>(
      stream: FirebaseFirestore.instance
          .collection('cart')
          .where('consumer_id', isEqualTo: auth.currentUser!.uid)
          .where('status', whereIn: ['awaiting_pickup', 'claimed'])
          .limit(10)
          .snapshots()
          .handleError((error) {
            debugPrint('Claims stream error: $error');
            return <QueryDocumentSnapshot>[];
          }),
      builder: (context, snap) {
        if (snap.hasError) {
          return _emptyState(Icons.error_outline, 'Connection error',
              'Unable to load orders. Please check your connection.');
        }
        
        if (!snap.hasData || snap.data!.docs.isEmpty) {
          return _emptyState(Icons.receipt_long, 'No orders yet',
              'Your order history will appear here');
        }

        final docs = snap.data!.docs.toList()
          ..sort((a, b) {
            final aTs =
                (a.data() as Map<String, dynamic>)['checkout_date'] as Timestamp?;
            final bTs =
                (b.data() as Map<String, dynamic>)['checkout_date'] as Timestamp?;
            return -aTs!.compareTo(bTs!);
          });

        return Column(children: docs.take(3).map(_claimTile).toList());
      },
    );
  }

  Widget _claimTile(QueryDocumentSnapshot doc) {
    final data = doc.data() as Map<String, dynamic>;
    final items = data['items'] ?? [];
    final total = data['total_price'] ?? 0.0;
    final date = (data['checkout_date'] as Timestamp).toDate();

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: _boxDecoration(),
      child: Row(
        children: [
          _iconBox(Icons.receipt, Colors.green),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${items.length} item(s)',
                    style:
                        const TextStyle(fontWeight: FontWeight.w500, fontSize: 14)),
                Text(DateFormat('MMM dd, yyyy').format(date),
                    style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
              ],
            ),
          ),
          Text('₱${total.toStringAsFixed(0)}',
              style: TextStyle(
                  color: Colors.green.shade700,
                  fontWeight: FontWeight.bold,
                  fontSize: 14)),
        ],
      ),
    );
  }

  Widget _buildListings(BuildContext context, AuthService auth) {
    return InkWell(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const MyListingsScreen()),
        );
      },
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              Row(
                children: [
                  Icon(Icons.inventory_2,
                      color: Colors.orange.shade600, size: 20),
                  const SizedBox(width: 8),
                  const Text('My Listings',
                      style:
                          TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const Spacer(),
                  const Icon(Icons.chevron_right),
                ],
              ),
              const SizedBox(height: 12),
              _buildListingsPreview(auth),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildListingsPreview(AuthService auth) {
    return StreamBuilder<QuerySnapshot>(
      stream: FirebaseFirestore.instance
          .collection('listings')
          .where('provider_id', isEqualTo: auth.currentUser!.uid)
          .where('status', whereIn: ['active', 'sold_out'])
          .limit(10)
          .snapshots()
          .handleError((error) {
            debugPrint('Listings stream error: $error');
            return <QueryDocumentSnapshot>[];
          }),
      builder: (context, snap) {
        if (snap.hasError) {
          return _emptyState(Icons.error_outline, 'Connection error',
              'Unable to load listings. Please check your connection.');
        }
        
        if (!snap.hasData || snap.data!.docs.isEmpty) {
          return _emptyState(Icons.inventory_2, 'No listings yet',
              'Create your first food listing');
        }

        final docs = snap.data!.docs.toList()
          ..sort((a, b) {
            final aTs =
                (a.data() as Map<String, dynamic>)['created_at'] as Timestamp?;
            final bTs =
                (b.data() as Map<String, dynamic>)['created_at'] as Timestamp?;
            return -aTs!.compareTo(bTs!);
          });

        return Column(children: docs.take(3).map(_listingTile).toList());
      },
    );
  }

  Widget _listingTile(QueryDocumentSnapshot doc) {
    final data = doc.data() as Map<String, dynamic>;
    final images = (data['images'] as List?)?.cast<String>() ?? [];
    final image = images.isNotEmpty ? images.first : null;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: _boxDecoration(),
      child: Row(
        children: [
          _imageBox(image),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(data['title'] ?? 'Untitled',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        fontWeight: FontWeight.w500, fontSize: 14)),
                Text(
                  '₱${(data['discounted_price'] ?? 0).toStringAsFixed(0)} • Qty: ${data['quantity'] ?? 0}',
                  style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                ),
                if (data['status'] == 'sold_out')
                  Container(
                    margin: const EdgeInsets.only(top: 4),
                    padding:
                        const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: Colors.orange.shade100,
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      'SOLD OUT',
                      style: TextStyle(
                          color: Colors.orange.shade800,
                          fontSize: 10,
                          fontWeight: FontWeight.bold),
                    ),
                  ),
              ],
            ),
          ),
          Icon(
            data['status'] == 'sold_out'
                ? Icons.pause_circle
                : Icons.check_circle,
            color: data['status'] == 'sold_out'
                ? Colors.orange.shade600
                : Colors.green.shade600,
            size: 20,
          ),
        ],
      ),
    );
  }

  Widget _buildMenu(BuildContext context, AuthService auth) {
    return Column(
      children: [
        ListTile(
          leading: const Icon(Icons.message),
          title: const Text('Messages'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const ChatListScreen()),
            );
          },
        ),
        if (auth.hasRole('food_provider'))
          ListTile(
            leading: const Icon(Icons.bar_chart, color: Colors.blue),
            title: const Text('Analytics'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const AnalyticsScreen()),
            );
          },
        ),
        if (auth.hasRole('food_provider'))
          ListTile(
            leading: const Icon(Icons.location_on),
            title: const Text('Manage Business Location'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const LocationManagementScreen()),
              );
            },
          ),
        ListTile(
          leading: const Icon(Icons.help),
          title: const Text('Help & Support'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () {},
        ),
        ListTile(
          leading: const Icon(Icons.info),
          title: const Text('About'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () {
            showAboutDialog(
              context: context,
              applicationName: 'MealDeal',
              applicationVersion: '1.0.0',
              applicationLegalese: '© 2024 MealDeal. All rights reserved.',
              children: const [
                Text('Reducing food waste, one meal at a time.'),
              ],
            );
          },
        ),
      ],
    );
  }

  Widget _buildLogoutButton(BuildContext context, AuthService auth) {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        style: ElevatedButton.styleFrom(
          backgroundColor: Colors.red.shade600,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 12),
        ),
        onPressed: () {
          showDialog(
            context: context,
            builder: (_) => AlertDialog(
              title: const Text('Sign Out'),
              content: const Text('Are you sure you want to sign out?'),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('Cancel'),
                ),
                TextButton(
                  onPressed: () {
                    Navigator.pop(context);
                    auth.signOut();
                  },
                  child: const Text('Sign Out'),
                ),
              ],
            ),
          );
        },
        child: const Text('Sign Out'),
      ),
    );
  }

  Widget _emptyState(IconData icon, String title, String subtitle) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _boxDecoration(),
      child: Column(
        children: [
          Icon(icon, size: 32, color: Colors.grey.shade400),
          const SizedBox(height: 8),
          Text(title,
              style: TextStyle(
                  fontSize: 14,
                  color: Colors.grey.shade600,
                  fontWeight: FontWeight.w500)),
          Text(subtitle,
              style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
        ],
      ),
    );
  }

  BoxDecoration _boxDecoration() {
    return BoxDecoration(
      color: Colors.grey.shade50,
      borderRadius: BorderRadius.circular(8),
      border: Border.all(color: Colors.grey.shade200),
    );
  }

  Widget _iconBox(IconData icon, MaterialColor color) {
    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: color.shade100,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Icon(icon, color: color.shade600, size: 20),
    );
  }

  Widget _imageBox(String? url) {
    return Container(
      width: 50,
      height: 50,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(8),
        color: Colors.orange.shade100,
      ),
      child: url != null
          ? ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: _buildImageFromUrl(url),
            )
          : Icon(Icons.fastfood, color: Colors.orange.shade600),
    );
  }

  bool _isDataUrl(String? value) {
    if (value == null) return false;
    return value.startsWith('data:image/');
  }

  Widget _buildImageFromUrl(String? url, {BoxFit fit = BoxFit.cover}) {
    if (url == null || url.isEmpty) {
      return const SizedBox.shrink();
    }
    if (_isDataUrl(url)) {
      try {
        final base64Part = url.split(',').last;
        final bytes = base64Decode(base64Part);
        return Image.memory(bytes, fit: fit);
      } catch (e) {
        debugPrint('Error decoding base64 image: $e');
        return Icon(Icons.broken_image, color: Colors.grey.shade400);
      }
    }
    return Image.network(
      url,
      fit: fit,
      errorBuilder: (context, error, stackTrace) => Icon(Icons.broken_image, color: Colors.grey.shade400),
    );
  }
}


class _PurchaseTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _PurchaseTile(
      {required this.icon, required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: InkWell(
        onTap: onTap,
        child: Column(
          children: [
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: Colors.grey.shade100,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.grey.shade300),
              ),
              child: Icon(icon, size: 28, color: Colors.grey.shade800),
            ),
            const SizedBox(height: 8),
            Text(label, style: const TextStyle(fontSize: 12)),
          ],
        ),
      ),
    );
  }
}
