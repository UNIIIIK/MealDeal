import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:provider/provider.dart';
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
    'prepared',
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

  @override
  Widget build(BuildContext context) {
    final authServiceTop = Provider.of<AuthService>(context);
    return Scaffold(
      floatingActionButton: Consumer<AuthService>(
        builder: (context, authService, child) {
          if (authService.hasRole('food_provider')) {
            return FloatingActionButton(
              onPressed: () {
                // Navigate to create listing
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (context) => const CreateListingScreen(),
                  ),
                );
              },
              backgroundColor: Colors.orange.shade600,
              foregroundColor: Colors.white,
              child: const Icon(Icons.add),
            );
          }
          return const SizedBox.shrink();
        },
      ),
      appBar: authServiceTop.hasRole('food_provider') ? AppBar(
        backgroundColor: Colors.green.shade600,
        elevation: 0,
        title: const Text('Home', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        actions: [
          Consumer<AuthService>(
            builder: (context, authService, child) {
              return IconButton(
                icon: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Icon(
                    Icons.notifications,
                    color: Colors.white,
                    size: 24,
                  ),
                ),
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (context) => NotificationsScreen(providerId: authService.currentUser!.uid),
                    ),
                  );
                },
              );
            },
          ),
        ],
      ) : AppBar(
        backgroundColor: Colors.green.shade600,
        elevation: 0,
        title: const Text(
          'Home',
          style: TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.bold,
          ),
        ),
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
                  child: IconButton(
                    icon: Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.2),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Icon(
                        Icons.shopping_cart,
                        color: Colors.white,
                        size: 24,
                      ),
                    ),
                    onPressed: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (context) => const CartScreen(),
                        ),
                      );
                    },
                  ),
                );
              } else if (authService.hasRole('food_provider')) {
                // Notifications bell for provider
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
                          IconButton(
                            icon: const Icon(Icons.notifications, color: Colors.white),
                            onPressed: () {
                              Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => NotificationsScreen(providerId: authService.currentUser!.uid),
                                ),
                              );
                            },
                          ),
                          if (unread > 0)
                            Positioned(
                              right: 4,
                              top: 4,
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                                decoration: const BoxDecoration(color: Colors.red, shape: BoxShape.rectangle, borderRadius: BorderRadius.all(Radius.circular(10))),
                                child: Text('$unread', style: const TextStyle(color: Colors.white, fontSize: 10)),
                              ),
                            ),
                        ],
                      );
                    },
                  ),
                );
              }
              return const SizedBox.shrink();
            },
          ),
        ],
      ),
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Colors.green.shade50,
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
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [Colors.orange.shade200, Colors.green.shade200],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.1),
                          blurRadius: 10,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.3),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Icon(
                            Icons.store,
                            color: Colors.orange.shade700,
                            size: 24,
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Welcome!',
                                style: TextStyle(
                                  fontSize: 20,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.black87,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Here are your recently added foods.',
                                style: TextStyle(
                                  fontSize: 14,
                                  color: Colors.black87.withValues(alpha: 0.8),
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
                          borderRadius: BorderRadius.circular(16),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.1),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: TextField(
                          decoration: InputDecoration(
                            hintText: 'Search for food...',
                            hintStyle: TextStyle(
                              color: Colors.grey.shade400,
                              fontSize: 16,
                            ),
                            prefixIcon: Container(
                              margin: const EdgeInsets.all(8),
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: Colors.green.shade100,
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Icon(
                                Icons.search,
                                color: Colors.green.shade700,
                                size: 20,
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
                          onChanged: (value) {
                            setState(() {
                              _searchQuery = value.toLowerCase();
                            });
                          },
                        ),
                      ),
                      const SizedBox(height: 16),
                      
                      // Enhanced filters
                      Row(
                        children: [
                          Expanded(
                            child: Container(
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(12),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.05),
                                    blurRadius: 8,
                                    offset: const Offset(0, 2),
                                  ),
                                ],
                              ),
                              child: DropdownButtonFormField<String>(
                                value: _selectedCategory,
                                decoration: InputDecoration(
                                  prefixIcon: Container(
                                    margin: const EdgeInsets.all(8),
                                    padding: const EdgeInsets.all(8),
                                    decoration: BoxDecoration(
                                      color: Colors.blue.shade100,
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Icon(
                                      Icons.category,
                                      color: Colors.blue.shade700,
                                      size: 16,
                                    ),
                                  ),
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(12),
                                    borderSide: BorderSide.none,
                                  ),
                                  filled: true,
                                  fillColor: Colors.transparent,
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                                ),
                                items: _categories.map((category) {
                                  return DropdownMenuItem(
                                    value: category,
                                    child: Text(
                                      category == 'all' ? 'All Categories' : category.toUpperCase(),
                                      style: const TextStyle(fontSize: 14),
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
                          const SizedBox(width: 12),
                          Expanded(
                            child: Container(
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(12),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.05),
                                    blurRadius: 8,
                                    offset: const Offset(0, 2),
                                  ),
                                ],
                              ),
                              child: DropdownButtonFormField<String>(
                                value: _sortBy,
                                decoration: InputDecoration(
                                  prefixIcon: Container(
                                    margin: const EdgeInsets.all(8),
                                    padding: const EdgeInsets.all(8),
                                    decoration: BoxDecoration(
                                      color: Colors.purple.shade100,
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Icon(
                                      Icons.sort,
                                      color: Colors.purple.shade700,
                                      size: 16,
                                    ),
                                  ),
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(12),
                                    borderSide: BorderSide.none,
                                  ),
                                  filled: true,
                                  fillColor: Colors.transparent,
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
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
                        return Center(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              CircularProgressIndicator(
                                color: Colors.green.shade600,
                                strokeWidth: 3,
                              ),
                              const SizedBox(height: 16),
                              Text(
                                'Loading delicious deals...',
                                style: TextStyle(
                                  color: Colors.grey.shade600,
                                  fontSize: 16,
                                ),
                              ),
                            ],
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
                          childAspectRatio: 0.75,
                          crossAxisSpacing: 16,
                          mainAxisSpacing: 16,
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
      elevation: 8,
      shadowColor: Colors.black.withValues(alpha: 0.15),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Colors.white,
              Colors.grey.shade50,
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
          borderRadius: BorderRadius.circular(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Enhanced image with discount badge
              Stack(
                children: [
                  Container(
                    height: 120,
                    width: double.infinity,
                    decoration: BoxDecoration(
                      color: Colors.grey.shade300,
                      borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                    ),
                    child: ClipRRect(
                      borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                      child: imageUrl != null
                          ? Image.network(
                              imageUrl,
                              fit: BoxFit.cover,
                              errorBuilder: (context, error, stackTrace) {
                                return Container(
                                  color: Colors.grey.shade300,
                                  child: Icon(
                                    Icons.fastfood,
                                    size: 40,
                                    color: Colors.grey.shade600,
                                  ),
                                );
                              },
                            )
                          : Container(
                              color: Colors.grey.shade300,
                              child: Icon(
                                Icons.fastfood,
                                size: 40,
                                color: Colors.grey.shade600,
                              ),
                            ),
                    ),
                  ),
                  // Enhanced discount badge (only for consumers)
                  if (authService.hasRole('food_consumer'))
                    Positioned(
                      top: 8,
                      right: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: [Colors.red.shade500, Colors.red.shade600],
                          ),
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.2),
                              blurRadius: 4,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.local_offer,
                              color: Colors.white,
                              size: 14,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              '$calculatedDiscount% OFF',
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                                fontSize: 11,
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
                      top: 8,
                      left: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: Colors.orange.shade600,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Text(
                          'Only $quantity left!',
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                            fontSize: 10,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
              
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(12.0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 15,
                          color: Colors.black87,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 8),
                      
                      // Price section
                      Row(
                        children: [
                          Text(
                            '₱${originalPrice.toStringAsFixed(0)}',
                            style: const TextStyle(
                              decoration: TextDecoration.lineThrough,
                              color: Colors.grey,
                              fontSize: 12,
                            ),
                          ),
                          const SizedBox(width: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: Colors.green.shade100,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              '₱${discountedPrice.toStringAsFixed(0)}',
                              style: TextStyle(
                                color: Colors.green.shade700,
                                fontWeight: FontWeight.bold,
                                fontSize: 14,
                              ),
                            ),
                          ),
                        ],
                      ),
                      
                      const SizedBox(height: 6),
                      if (description.isNotEmpty)
                        Text(
                          description,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 12, color: Colors.black54),
                        ),
                      if (expiryTs != null) ...[
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            Icon(Icons.schedule, size: 12, color: Colors.red.shade400),
                            const SizedBox(width: 4),
                            Text(
                              _formatExpiry(expiryTs),
                              style: TextStyle(fontSize: 11, color: Colors.red.shade600, fontWeight: FontWeight.w600),
                            ),
                          ],
                        ),
                      ],

                      const Spacer(),
                      
                      // Action buttons (removed View button as requested)
                      if (authService.hasRole('food_consumer'))
                        Row(
                          children: [
                            Expanded(
                              child: ElevatedButton(
                                onPressed: () => _addToCart(context, doc, data),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.orange.shade400,
                                  foregroundColor: Colors.white,
                                  padding: const EdgeInsets.symmetric(vertical: 8),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  elevation: 2,
                                ),
                                child: Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    Icon(Icons.add_shopping_cart, size: 14),
                                    const SizedBox(width: 4),
                                    Text('Add to Cart', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                                  ],
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: ElevatedButton(
                                onPressed: () => _buyNow(context, doc, data),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.green.shade600,
                                  foregroundColor: Colors.white,
                                  padding: const EdgeInsets.symmetric(vertical: 8),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  elevation: 2,
                                ),
                                child: Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    Icon(Icons.shopping_bag, size: 14),
                                    const SizedBox(width: 4),
                                    Text('Buy Now', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        )
                      else
                        // Provider view - show delete button
                        ElevatedButton(
                          onPressed: () => _deleteListing(context, doc.id),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.red.shade500,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 8),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                            elevation: 2,
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.delete, size: 14),
                              const SizedBox(width: 4),
                              Text('Delete', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                            ],
                          ),
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
}
