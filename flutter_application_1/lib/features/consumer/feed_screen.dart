import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:provider/provider.dart';
import '../../theme/app_theme.dart';
import '../../widgets/animated_food_button.dart';
import '../../widgets/food_loading_widget.dart';
import '../../widgets/food_decorative_divider.dart';
import '../auth/auth_service.dart';
import '../provider/create_listing_screen.dart';
import '../provider/notifications_screen.dart';
import 'listing_detail_screen.dart';
import 'cart_screen.dart';
import 'checkout_screen.dart';
import 'report_dialog.dart';

class FeedScreen extends StatefulWidget {
  const FeedScreen({super.key});

  @override
  State<FeedScreen> createState() => _FeedScreenState();
}

class _FeedScreenState extends State<FeedScreen> {
  String _searchQuery = '';
  String _selectedCategory = 'all';
  String _sortBy = 'created_at';

  final List<String> _categories = [
    'all',
    'bakery',
    'dairy',
    'fruits',
    'vegetables',
    'meat',
    'beverages',
    'snacks',
    'frozen',
    'canned',
    'other',
  ];

  // Mapping from provider food types to consumer categories
  String _mapFoodTypeToCategory(String foodType) {
    switch (foodType.toLowerCase()) {
      case 'bakery & pastries':
        return 'bakery';
      case 'dairy products':
        return 'dairy';
      case 'fruits & vegetables':
        return 'fruits';
      case 'meat & seafood':
        return 'meat';
      case 'prepared foods':
        return 'prepared';
      case 'beverages':
        return 'beverages';
      case 'snacks & confectionery':
        return 'snacks';
      case 'frozen foods':
        return 'frozen';
      case 'canned goods':
        return 'canned';
      case 'other':
        return 'other';
      default:
        return 'other';
    }
  }

  PreferredSizeWidget _buildAppBar(AuthService authService) {
    if (authService.hasRole('food_provider')) {
      return AppBar(
        backgroundColor: AppTheme.primaryOrange,
        elevation: 0,
        title: const Text(''),
        centerTitle: true,
        actions: [
          Consumer<AuthService>(
            builder: (context, authService, child) {
              return Container(
                margin: const EdgeInsets.only(right: 16),
                child: StreamBuilder<QuerySnapshot>(
                  stream: FirebaseFirestore.instance
                      .collection('notifications')
                      .where('provider_id', isEqualTo: authService.currentUser!.uid)
                      .where('read', isEqualTo: false)
                      .snapshots(),
                  builder: (context, snap) {
                    final unread = snap.data?.docs.length ?? 0;
                    
                    return Stack(
                      clipBehavior: Clip.none,
                      children: [
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: Colors.white.withOpacity(0.2),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: IconButton(
                            icon: Icon(
                              Icons.notifications_outlined, 
                              color: unread > 0 ? AppTheme.accentYellow : Colors.white,
                              size: 24,
                            ),
                            onPressed: () async {
                              if (context.mounted) {
                                Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) => NotificationsScreen(providerId: authService.currentUser!.uid),
                                  ),
                                );
                              }
                            },
                          ),
                        ),
                        if (unread > 0)
                          Positioned(
                            right: 4,
                            top: 4,
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                              decoration: BoxDecoration(
                                color: AppTheme.primaryRed,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: Colors.white, width: 2),
                                boxShadow: AppTheme.foodShadow,
                              ),
                              child: Text(
                                '$unread',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                  fontFamily: 'Inter',
                                ),
                              ),
                            ),
                          ),
                      ],
                    );
                  },
                ),
              );
            },
          ),
        ],
      );
    } else {
      return AppBar(
        backgroundColor: AppTheme.primaryOrange,
        elevation: 0,
        title: const Text(''),
        centerTitle: true,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(
            bottom: Radius.circular(20),
          ),
        ),
        actions: [
          Consumer<AuthService>(
            builder: (context, authService, child) {
              if (authService.hasRole('food_consumer')) {
                return Container(
                  margin: const EdgeInsets.only(right: 16),
                  child: Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.2),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: IconButton(
                      icon: const Icon(
                        Icons.shopping_cart_outlined,
                        color: Colors.white,
                        size: 24,
                      ),
                      onPressed: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (context) => const CartScreen(),
                          ),
                        );
                      },
                    ),
                  ),
                );
              }
              return const SizedBox.shrink();
            },
          ),
        ],
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final authServiceTop = Provider.of<AuthService>(context);
    return Scaffold(
      floatingActionButton: Consumer<AuthService>(
        builder: (context, authService, child) {
          if (authService.hasRole('food_provider')) {
            return Container(
              decoration: BoxDecoration(
                gradient: AppTheme.foodGradient,
                borderRadius: BorderRadius.circular(16),
                boxShadow: AppTheme.foodShadow,
              ),
              child: FloatingActionButton(
                onPressed: () {
                  // Navigate to create listing
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (context) => const CreateListingScreen(),
                    ),
                  );
                },
                backgroundColor: Colors.transparent,
                foregroundColor: Colors.white,
                elevation: 0,
                child: const Icon(Icons.add, size: 28),
              ),
            );
          }
          return const SizedBox.shrink();
        },
      ),
      appBar: _buildAppBar(authServiceTop),
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              AppTheme.backgroundGray,
              Colors.white,
            ],
          ),
        ),
        child: Column(
          children: [
            // Search and filters only for consumers
            Consumer<AuthService>(
              builder: (context, authService, child) {
                if (!authService.hasRole('food_consumer')) {
                  return Container(
                    margin: const EdgeInsets.all(16),
                    padding: const EdgeInsets.all(24),
                    decoration: BoxDecoration(
                      gradient: AppTheme.foodGradient,
                      borderRadius: BorderRadius.circular(20),
                      boxShadow: AppTheme.cardShadow,
                    ),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: Colors.white.withOpacity(0.2),
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: const Icon(
                            Icons.restaurant_menu,
                            color: Colors.white,
                            size: 32,
                          ),
                        ),
                        const SizedBox(width: 20),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Welcome to MealDeal!',
                                style: TextStyle(
                                  fontSize: 22,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.white,
                                  fontFamily: 'Poppins',
                                ),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                'Manage your food listings and reduce waste',
                                style: TextStyle(
                                  fontSize: 16,
                                  color: Colors.white.withOpacity(0.9),
                                  fontFamily: 'Inter',
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  );
                }
                return Container(
                  margin: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      // Enhanced search bar
                      Container(
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: AppTheme.cardShadow,
                        ),
                        child: TextField(
                          decoration: InputDecoration(
                            hintText: 'Search for delicious deals...',
                            hintStyle: TextStyle(
                              color: Colors.grey.shade500,
                              fontSize: 16,
                              fontFamily: 'Inter',
                            ),
                            prefixIcon: Container(
                              margin: const EdgeInsets.all(12),
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                gradient: AppTheme.freshGradient,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: const Icon(
                                Icons.search,
                                color: Colors.white,
                                size: 20,
                              ),
                            ),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(20),
                              borderSide: BorderSide.none,
                            ),
                            filled: true,
                            fillColor: Colors.transparent,
                            contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 20),
                          ),
                          onChanged: (value) {
                            setState(() {
                              _searchQuery = value.toLowerCase();
                            });
                          },
                        ),
                      ),
                      const SizedBox(height: 16),
                      
                      // Decorative divider
                      const FoodIconDivider(
                        icon: Icons.restaurant_menu,
                        iconColor: AppTheme.primaryOrange,
                        lineColor: AppTheme.lightGray,
                      ),
                      
                      // Enhanced filters
                      Row(
                        children: [
                          Expanded(
                            child: Container(
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(16),
                                boxShadow: AppTheme.cardShadow,
                              ),
                              child: DropdownButtonFormField<String>(
                                value: _selectedCategory,
                                decoration: InputDecoration(
                                  prefixIcon: Container(
                                    margin: const EdgeInsets.all(12),
                                    padding: const EdgeInsets.all(12),
                                    decoration: BoxDecoration(
                                      gradient: AppTheme.warmGradient,
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: const Icon(
                                      Icons.category,
                                      color: Colors.white,
                                      size: 18,
                                    ),
                                  ),
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(16),
                                    borderSide: BorderSide.none,
                                  ),
                                  filled: true,
                                  fillColor: Colors.transparent,
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                                ),
                                items: _categories.map((category) {
                                  return DropdownMenuItem(
                                    value: category,
                                    child: Text(
                                      category == 'all' ? 'All Categories' : category.toUpperCase(),
                                      style: const TextStyle(
                                        fontSize: 14,
                                        fontFamily: 'Inter',
                                        fontWeight: FontWeight.w500,
                                      ),
                                    ),
                                  );
                                }).toList(),
                                onChanged: (value) {
                                  setState(() {
                                    _selectedCategory = value!;
                                  });
                                },
                              ),
                            ),
                          ),
                          const SizedBox(width: 16),
                          Expanded(
                            child: Container(
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(16),
                                boxShadow: AppTheme.cardShadow,
                              ),
                              child: DropdownButtonFormField<String>(
                                value: _sortBy,
                                decoration: InputDecoration(
                                  prefixIcon: Container(
                                    margin: const EdgeInsets.all(12),
                                    padding: const EdgeInsets.all(12),
                                    decoration: BoxDecoration(
                                      gradient: AppTheme.foodGradient,
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: const Icon(
                                      Icons.sort,
                                      color: Colors.white,
                                      size: 18,
                                    ),
                                  ),
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(16),
                                    borderSide: BorderSide.none,
                                  ),
                                  filled: true,
                                  fillColor: Colors.transparent,
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                                ),
                                items: [
                                  DropdownMenuItem(value: 'created_at', child: Text('Newest First')),
                                  DropdownMenuItem(value: 'discounted_price', child: Text('Price: Low to High')),
                                  DropdownMenuItem(value: 'expiry_datetime', child: Text('Expiring Soon')),
                                ],
                                onChanged: (value) {
                                  setState(() {
                                    _sortBy = value!;
                                  });
                                },
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              },
            ),
            
            // Enhanced listings
            Expanded(
              child: Consumer<AuthService>(
                builder: (context, authService, child) {
                  return StreamBuilder<QuerySnapshot>(
                    stream: FirebaseFirestore.instance
                        .collection('listings')
                        .where('status', whereIn: ['active'])
                        .where('quantity', isGreaterThan: 0)
                        .snapshots(),
                    builder: (context, snapshot) {
                      if (snapshot.hasError) {
                        return Center(
                          child: Container(
                            padding: const EdgeInsets.all(20),
                            decoration: BoxDecoration(
                              color: Colors.red.shade50,
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: Colors.red.shade200),
                            ),
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(
                                  Icons.error_outline,
                                  color: Colors.red.shade600,
                                  size: 48,
                                ),
                                const SizedBox(height: 16),
                                Text(
                                  'Error loading listings',
                                  style: TextStyle(
                                    color: Colors.red.shade700,
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  'Please try again later',
                                  style: TextStyle(
                                    color: Colors.red.shade600,
                                    fontSize: 14,
                                  ),
                                ),
                                const SizedBox(height: 16),
                                ElevatedButton.icon(
                                  onPressed: () {
                                    // Force a rebuild to retry the query
                                    setState(() {});
                                  },
                                  icon: const Icon(Icons.refresh),
                                  label: const Text('Retry'),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: Colors.red.shade600,
                                    foregroundColor: Colors.white,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                ElevatedButton.icon(
                                  onPressed: () async {
                                    // Test Firestore connection
                                    try {
                                      final testQuery = await FirebaseFirestore.instance
                                          .collection('listings')
                                          .limit(1)
                                          .get();
                                      debugPrint('Test query successful: ${testQuery.docs.length} documents');
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        SnackBar(
                                          content: Text('Connection test successful: ${testQuery.docs.length} docs found'),
                                          backgroundColor: Colors.green,
                                        ),
                                      );
                                    } catch (e) {
                                      debugPrint('Test query failed: $e');
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        SnackBar(
                                          content: Text('Connection test failed: $e'),
                                          backgroundColor: Colors.red,
                                        ),
                                      );
                                    }
                                  },
                                  icon: const Icon(Icons.wifi),
                                  label: const Text('Test Connection'),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: Colors.blue.shade600,
                                    foregroundColor: Colors.white,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  'Error: ${snapshot.error.toString().split(':').last.trim()}',
                                  style: TextStyle(
                                    color: Colors.red.shade500,
                                    fontSize: 12,
                                  ),
                                  textAlign: TextAlign.center,
                                ),
                              ],
                            ),
                          ),
                        );
                      }

                      if (snapshot.connectionState == ConnectionState.waiting) {
                        return const Center(
                          child: FoodLoadingWidget(
                            message: 'Loading delicious deals...',
                            size: 60,
                          ),
                        );
                      }

                      final listings = snapshot.data?.docs ?? [];
                      
                      if (listings.isEmpty) {
                        return Center(
                          child: Container(
                            padding: const EdgeInsets.all(20),
                            decoration: BoxDecoration(
                              color: Colors.grey.shade50,
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: Colors.grey.shade200),
                            ),
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(
                                  Icons.fastfood,
                                  color: Colors.grey.shade400,
                                  size: 64,
                                ),
                                const SizedBox(height: 16),
                                Text(
                                  'No food listings available',
                                  style: TextStyle(
                                    color: Colors.grey.shade600,
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  'Check back later for new deals!',
                                  style: TextStyle(
                                    color: Colors.grey.shade500,
                                    fontSize: 14,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      }

                      // Apply search and category filters
                      final filteredListings = listings.where((doc) {
                        final data = doc.data() as Map<String, dynamic>;
                        final title = (data['title'] ?? '').toString().toLowerCase();
                        final foodType = (data['food_type'] ?? '').toString();
                        final category = _mapFoodTypeToCategory(foodType);
                        
                        final matchesSearch = _searchQuery.isEmpty || title.contains(_searchQuery.toLowerCase());
                        final matchesCategory = _selectedCategory == 'all' || category == _selectedCategory;
                        
                        return matchesSearch && matchesCategory;
                      }).toList();

                      // Sort listings
                      filteredListings.sort((a, b) {
                        final aData = a.data() as Map<String, dynamic>;
                        final bData = b.data() as Map<String, dynamic>;
                        
                        switch (_sortBy) {
                          case 'discounted_price':
                            final aPrice = (aData['discounted_price'] ?? 0.0) as double;
                            final bPrice = (bData['discounted_price'] ?? 0.0) as double;
                            return aPrice.compareTo(bPrice);
                          case 'expiry_datetime':
                            final aExpiry = aData['expiry_datetime'] as Timestamp?;
                            final bExpiry = bData['expiry_datetime'] as Timestamp?;
                            if (aExpiry == null && bExpiry == null) return 0;
                            if (aExpiry == null) return 1;
                            if (bExpiry == null) return -1;
                            return aExpiry.compareTo(bExpiry);
                          default: // 'created_at'
                            final aCreated = aData['created_at'] as Timestamp?;
                            final bCreated = bData['created_at'] as Timestamp?;
                            if (aCreated == null && bCreated == null) return 0;
                            if (aCreated == null) return 1;
                            if (bCreated == null) return -1;
                            return bCreated.compareTo(aCreated); // Newest first
                        }
                      });

                      return GridView.builder(
                        padding: const EdgeInsets.all(16),
                        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          childAspectRatio: 0.7,
                          crossAxisSpacing: 12,
                          mainAxisSpacing: 12,
                        ),
                        itemCount: filteredListings.length,
                        itemBuilder: (context, index) {
                          final doc = filteredListings[index];
                          final data = doc.data() as Map<String, dynamic>;
                          return _buildEnhancedListingCard(context, doc, data, authService);
                        },
                      );
                    },
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEnhancedListingCard(BuildContext context, DocumentSnapshot doc, Map<String, dynamic> data, AuthService authService) {
    final title = data['title'] ?? 'Unknown Food';
    final originalPrice = (data['original_price'] ?? 0.0) as double;
    final discountedPrice = (data['discounted_price'] ?? 0.0) as double;
    final quantity = (data['quantity'] ?? 0) as int;
    final imageUrl = data['images'] != null && 
        data['images'] is List && 
        (data['images'] as List).isNotEmpty 
        ? (data['images'] as List).first 
        : null;
    final description = (data['description'] ?? '').toString();
    final expiryTs = data['expiry_datetime'] as Timestamp?;
    
    // Calculate discount percentage
    final calculatedDiscount = ((originalPrice - discountedPrice) / originalPrice * 100).round();
    
    return Card(
      elevation: 6,
      shadowColor: AppTheme.primaryOrange.withOpacity(0.2),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(20),
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Colors.white,
              AppTheme.backgroundGray,
            ],
          ),
        ),
        child: InkWell(
          onTap: () {
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (context) => ListingDetailScreen(listingId: doc.id, listingData: data),
              ),
            );
          },
          borderRadius: BorderRadius.circular(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Enhanced image with discount badge
              Stack(
                children: [
                  Container(
                    height: 140,
                    width: double.infinity,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [
                          AppTheme.primaryOrange.withOpacity(0.1),
                          AppTheme.primaryRed.withOpacity(0.1),
                        ],
                      ),
                      borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
                    ),
                    child: ClipRRect(
                      borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
                      child: imageUrl != null
                          ? Image.network(
                              imageUrl,
                              fit: BoxFit.cover,
                              errorBuilder: (context, error, stackTrace) {
                                return Container(
                                  decoration: BoxDecoration(
                                    gradient: AppTheme.foodGradient,
                                  ),
                                  child: const Icon(
                                    Icons.restaurant,
                                    size: 48,
                                    color: Colors.white,
                                  ),
                                );
                              },
                            )
                          : Container(
                              decoration: BoxDecoration(
                                gradient: AppTheme.foodGradient,
                              ),
                              child: const Icon(
                                Icons.restaurant,
                                size: 48,
                                color: Colors.white,
                              ),
                            ),
                    ),
                  ),
                  // Enhanced discount badge (only for consumers)
                  if (authService.hasRole('food_consumer'))
                    Positioned(
                      top: 12,
                      right: 12,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          gradient: AppTheme.foodGradient,
                          borderRadius: BorderRadius.circular(25),
                          boxShadow: AppTheme.foodShadow,
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(
                              Icons.local_offer,
                              color: Colors.white,
                              size: 16,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              '$calculatedDiscount% OFF',
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                                fontSize: 12,
                                fontFamily: 'Inter',
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  // Report Button (only for consumers)
                  if (authService.hasRole('food_consumer'))
                    Positioned(
                      bottom: 8,
                      right: 8,
                      child: Container(
                        decoration: BoxDecoration(
                          color: Colors.black.withValues(alpha: 0.7),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: IconButton(
                          onPressed: () => _showReportDialog(context, data),
                          icon: const Icon(
                            Icons.report,
                            color: Colors.white,
                            size: 18,
                          ),
                          tooltip: 'Report this listing',
                          padding: const EdgeInsets.all(8),
                          constraints: const BoxConstraints(
                            minWidth: 36,
                            minHeight: 36,
                          ),
                        ),
                      ),
                    ),
                  // Quantity badge
                  if (quantity <= 5)
                    Positioned(
                      top: 12,
                      left: 12,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                        decoration: BoxDecoration(
                          gradient: AppTheme.warmGradient,
                          borderRadius: BorderRadius.circular(15),
                          boxShadow: AppTheme.foodShadow,
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(
                              Icons.warning_amber,
                              color: Colors.white,
                              size: 14,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              'Only $quantity left!',
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                                fontSize: 11,
                                fontFamily: 'Inter',
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
              
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                          color: AppTheme.darkGray,
                          fontFamily: 'Poppins',
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 8),
                      
                      // Price section
                      Row(
                        children: [
                          Flexible(
                            child: Text(
                              '₱${originalPrice.toStringAsFixed(0)}',
                              style: const TextStyle(
                                decoration: TextDecoration.lineThrough,
                                color: AppTheme.mediumGray,
                                fontSize: 13,
                                fontFamily: 'Inter',
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Flexible(
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                gradient: AppTheme.freshGradient,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Text(
                                '₱${discountedPrice.toStringAsFixed(0)}',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 15,
                                  fontFamily: 'Inter',
                                ),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ),
                        ],
                      ),
                      
                      const SizedBox(height: 8),
                      if (description.isNotEmpty)
                        Text(
                          description,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 13, 
                            color: AppTheme.mediumGray,
                            fontFamily: 'Inter',
                            height: 1.3,
                          ),
                        ),
                      if (expiryTs != null) ...[
                        const SizedBox(height: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: AppTheme.primaryRed.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: AppTheme.primaryRed.withOpacity(0.3)),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.schedule, size: 14, color: AppTheme.primaryRed),
                              const SizedBox(width: 6),
                              Text(
                                _formatExpiry(expiryTs),
                                style: TextStyle(
                                  fontSize: 12, 
                                  color: AppTheme.primaryRed, 
                                  fontWeight: FontWeight.w600,
                                  fontFamily: 'Inter',
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],

                      const Spacer(),
                      
                      // Action buttons (removed View button as requested)
                      if (authService.hasRole('food_consumer'))
                        Row(
                          children: [
                            Expanded(
                              flex: 1,
                              child: AnimatedFoodButton(
                                text: 'Add to Cart',
                                icon: Icons.add_shopping_cart,
                                onPressed: () => _addToCart(context, doc, data),
                                gradient: AppTheme.warmGradient,
                                height: 36,
                              ),
                            ),
                            const SizedBox(width: 6),
                            Expanded(
                              flex: 1,
                              child: AnimatedFoodButton(
                                text: 'Buy Now',
                                icon: Icons.shopping_bag,
                                onPressed: () => _buyNow(context, doc, data),
                                gradient: AppTheme.freshGradient,
                                height: 36,
                              ),
                            ),
                          ],
                        )
                      else
                        // Provider view - show subtle delete button
                        AnimatedFoodButton(
                          text: 'Remove',
                          icon: Icons.delete_outline,
                          onPressed: () => _deleteListing(context, doc.id),
                          backgroundColor: AppTheme.lightGray.withOpacity(0.3),
                          foregroundColor: AppTheme.mediumGray,
                          height: 36,
                          borderRadius: BorderRadius.circular(10),
                        ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _formatExpiry(Timestamp ts) {
    final dt = ts.toDate();
    final now = DateTime.now();
    final difference = dt.difference(now);
    if (difference.inMinutes <= 0) {
      return 'Expired';
    }
    if (difference.inHours < 1) {
      return 'Expires in ${difference.inMinutes} min';
    }
    if (difference.inHours < 24) {
      return 'Expires in ${difference.inHours} hr';
    }
    return 'Expires on ${dt.month}/${dt.day}/${dt.year}';
  }

  void _showReportDialog(BuildContext context, Map<String, dynamic> listing) {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return ReportDialog(listing: listing);
      },
    );
  }

  Future<void> _deleteListing(BuildContext context, String listingId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete Listing'),
        content: const Text('Are you sure you want to delete this listing? This action cannot be undone.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      try {
        await FirebaseFirestore.instance
            .collection('listings')
            .doc(listingId)
            .update({'status': 'deleted'});

        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Listing deleted successfully'),
            backgroundColor: Colors.green,
          ),
        );
      } catch (e) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error deleting listing: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  void _buyNow(BuildContext context, DocumentSnapshot doc, Map<String, dynamic> data) {
    final authService = Provider.of<AuthService>(context, listen: false);
    
    if (authService.currentUser == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please log in to buy items')),
      );
      return;
    }

    if (!authService.hasRole('food_consumer')) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Only food consumers can buy items')),
      );
      return;
    }

    // Add to cart and navigate to checkout
    _addItemToCartAndCheckout(doc.id, data);
  }

  void _addToCart(BuildContext context, DocumentSnapshot doc, Map<String, dynamic> data) {
    final authService = Provider.of<AuthService>(context, listen: false);
    
    if (authService.currentUser == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please log in to add items to cart')),
      );
      return;
    }

    if (!authService.hasRole('food_consumer')) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Only food consumers can add items to cart')),
      );
      return;
    }

    // Add to cart logic
    _addItemToCart(doc.id, data);
  }

  Future<void> _addItemToCart(String listingId, Map<String, dynamic> data) async {
    try {
      final authService = Provider.of<AuthService>(context, listen: false);
      final userId = authService.currentUser!.uid;

      // Check if user already has a cart
      final cartQuery = await FirebaseFirestore.instance
          .collection('cart')
          .where('consumer_id', isEqualTo: userId)
          .where('status', isEqualTo: 'pending')
          .get();

      DocumentReference cartRef;
      
      if (cartQuery.docs.isEmpty) {
        // Create new cart
        cartRef = await FirebaseFirestore.instance.collection('cart').add({
          'consumer_id': userId,
          'items': [],
          'total_price': 0.0,
          'status': 'pending',
          'created_at': FieldValue.serverTimestamp(),
        });
      } else {
        cartRef = cartQuery.docs.first.reference;
      }

      // Add item to cart
      await cartRef.update({
        'items': FieldValue.arrayUnion([{
          'listing_id': listingId,
          'title': data['title'],
          'price': data['discounted_price'],
          'quantity': 1,
          'image': data['images'] is List && (data['images'] as List).isNotEmpty 
              ? (data['images'] as List)[0] 
              : null,
        }]),
        'total_price': FieldValue.increment(data['discounted_price'] ?? 0.0),
      });

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${data['title']} added to cart!'),
          backgroundColor: Colors.green,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error adding to cart: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<void> _addItemToCartAndCheckout(String listingId, Map<String, dynamic> data) async {
    try {
      final authService = Provider.of<AuthService>(context, listen: false);
      final userId = authService.currentUser!.uid;

      // Check if user already has a cart
      final cartQuery = await FirebaseFirestore.instance
          .collection('cart')
          .where('consumer_id', isEqualTo: userId)
          .where('status', isEqualTo: 'pending')
          .get();

      DocumentReference cartRef;
      
      if (cartQuery.docs.isEmpty) {
        // Create new cart
        cartRef = await FirebaseFirestore.instance.collection('cart').add({
          'consumer_id': userId,
          'items': [],
          'total_price': 0.0,
          'status': 'pending',
          'created_at': FieldValue.serverTimestamp(),
        });
      } else {
        cartRef = cartQuery.docs.first.reference;
      }

      // Add item to cart
      await cartRef.update({
        'items': FieldValue.arrayUnion([{
          'listing_id': listingId,
          'title': data['title'],
          'price': data['discounted_price'],
          'quantity': 1,
          'image': data['images'] is List && (data['images'] as List).isNotEmpty 
              ? (data['images'] as List)[0] 
              : null,
        }]),
        'total_price': FieldValue.increment(data['discounted_price'] ?? 0.0),
      });

      // Create notification for provider about new pending order
      await _createPendingOrderNotification(cartRef.id, listingId, data);

      if (!mounted) return;
      
      // Navigate directly to checkout screen
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (context) => CheckoutScreen(
            cartId: cartRef.id,
            items: [{
              'listing_id': listingId,
              'title': data['title'],
              'price': data['discounted_price'],
              'quantity': 1,
              'image': data['images'] is List && (data['images'] as List).isNotEmpty 
              ? (data['images'] as List)[0] 
              : null,
            }],
            totalPrice: data['discounted_price'] ?? 0.0,
          ),
        ),
      );
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${data['title']} added to cart and ready for checkout!'),
          backgroundColor: Colors.green,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error adding to cart: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<void> _createPendingOrderNotification(String cartId, String listingId, Map<String, dynamic> data) async {
    try {
      // Get provider ID from listing
      final listingDoc = await FirebaseFirestore.instance
          .collection('listings')
          .doc(listingId)
          .get();
      
      if (listingDoc.exists) {
        final providerId = listingDoc.data()?['provider_id'] as String?;
        if (providerId != null) {
          await FirebaseFirestore.instance.collection('notifications').add({
            'provider_id': providerId,
            'type': 'pending_order',
            'cart_id': cartId,
            'created_at': FieldValue.serverTimestamp(),
            'read': false,
            'message': 'New pending order: ${data['title']} has been added to cart.',
          });
        }
      }
    } catch (e) {
      debugPrint('Failed to create pending order notification: $e');
    }
  }
}
