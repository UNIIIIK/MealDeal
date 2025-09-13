import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:provider/provider.dart';
import '../auth/auth_service.dart';
import 'pickup_route_screen.dart';

class MyOrdersScreen extends StatefulWidget {
  const MyOrdersScreen({super.key, this.initialFilter});

  final String? initialFilter; // all, pending, awaiting_pickup, claimed, checked_out

  @override
  State<MyOrdersScreen> createState() => _MyOrdersScreenState();
}

class _MyOrdersScreenState extends State<MyOrdersScreen> {
  String _selectedFilter = 'all'; // all, pending, claimed, completed

  @override
  void initState() {
    super.initState();
    if (widget.initialFilter != null) {
      _selectedFilter = widget.initialFilter!;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('My Orders'),
        backgroundColor: Colors.green.shade600,
        foregroundColor: Colors.white,
      ),
      body: Consumer<AuthService>(
        builder: (context, authService, child) {
          final userId = authService.currentUser?.uid;
          
          if (userId == null) {
            return const Center(child: Text('Not signed in'));
          }

          // Build query based on filter
          Query query;
          
          if (_selectedFilter == 'all') {
            // For 'all' filter, just query by consumer_id without ordering
            query = FirebaseFirestore.instance
                .collection('cart')
                .where('consumer_id', isEqualTo: userId);
          } else {
            // For specific status filter, use composite query
            query = FirebaseFirestore.instance
                .collection('cart')
                .where('consumer_id', isEqualTo: userId)
                .where('status', isEqualTo: _selectedFilter);
          }

          return Column(
            children: [
              // Filter chips
              Container(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Expanded(
                      child: SingleChildScrollView(
                        scrollDirection: Axis.horizontal,
                        child: Row(
                          children: [
                            _buildFilterChip('all', 'All'),
                            const SizedBox(width: 8),
                            _buildFilterChip('pending', 'Preparing'),
                            const SizedBox(width: 8),
                            _buildFilterChip('awaiting_pickup', 'Ready for Pickup'),
                            const SizedBox(width: 8),
                            _buildFilterChip('claimed', 'Picked Up'),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              
              // Orders list
              Expanded(
                child: StreamBuilder<QuerySnapshot>(
                  stream: query.snapshots(),
                  builder: (context, snapshot) {
                    if (snapshot.connectionState == ConnectionState.waiting) {
                      return const Center(child: CircularProgressIndicator());
                    }

                    if (snapshot.hasError) {
                      return Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.error, color: Colors.red, size: 48),
                            const SizedBox(height: 16),
                            Text('Error loading orders: ${snapshot.error}'),
                          ],
                        ),
                      );
                    }

                    if (!snapshot.hasData || snapshot.data!.docs.isEmpty) {
                      return Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.shopping_bag_outlined, size: 48, color: Colors.grey),
                            const SizedBox(height: 16),
                            Text('No orders found'),
                            const SizedBox(height: 8),
                            Text('Your orders will appear here'),
                          ],
                        ),
                      );
                    }

                    final orders = snapshot.data!.docs;
                    
                    // Sort orders by created_at timestamp (client-side sorting)
                    orders.sort((a, b) {
                      final aData = a.data() as Map<String, dynamic>;
                      final bData = b.data() as Map<String, dynamic>;
                      final aCreated = aData['created_at'] as Timestamp?;
                      final bCreated = bData['created_at'] as Timestamp?;
                      
                      if (aCreated == null && bCreated == null) return 0;
                      if (aCreated == null) return 1;
                      if (bCreated == null) return -1;
                      
                      return bCreated.compareTo(aCreated); // Descending order (newest first)
                    });
                    
                    return ListView.builder(
                      padding: const EdgeInsets.all(16),
                      itemCount: orders.length,
                      itemBuilder: (context, index) {
                        final order = orders[index];
                        final data = order.data() as Map<String, dynamic>;
                        return _buildOrderCard(context, order.id, data);
                      },
                    );
                  },
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildFilterChip(String value, String label) {
    return FilterChip(
      label: Text(label),
      selected: _selectedFilter == value,
      onSelected: (selected) {
        setState(() {
          _selectedFilter = value;
        });
      },
      selectedColor: Colors.green.shade100,
      checkmarkColor: Colors.green.shade700,
    );
  }

  Widget _buildOrderCard(BuildContext context, String orderId, Map<String, dynamic> data) {
    final items = (data['items'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final status = data['status'] ?? 'pending';
    final totalPrice = data['total_price'] ?? 0.0;
    final createdAt = data['created_at'] as Timestamp?;
    final checkoutDate = data['checkout_date'] as Timestamp?;
    final claimedAt = data['claimed_at'] as Timestamp?;

    final awaitingPickup = status == 'awaiting_pickup';

    final card = Card(
      margin: const EdgeInsets.only(bottom: 12),
      elevation: 4,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Order header
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Order #${orderId.substring(0, 8)}',
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                _buildStatusChip(status),
              ],
            ),
            const SizedBox(height: 8),
            
            // Order items
            ...items.map((item) => Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                children: [
                  if (item['image'] != null)
                    Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(8),
                        image: DecorationImage(
                          image: NetworkImage(item['image']),
                          fit: BoxFit.cover,
                        ),
                      ),
                    )
                  else
                    Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: Colors.grey.shade300,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Icon(Icons.fastfood, color: Colors.grey),
                    ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item['title'] ?? 'Unknown Item',
                          style: const TextStyle(fontWeight: FontWeight.w500),
                        ),
                        Text(
                          'Qty: ${item['quantity']} × ₱${(item['price'] ?? 0).toStringAsFixed(0)}',
                          style: TextStyle(
                            color: Colors.grey.shade600,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            )),
            
            const Divider(height: 16),
            
            // Order details
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Total: ₱${totalPrice.toStringAsFixed(0)}',
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: Colors.green,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _getOrderDateText(createdAt, checkoutDate, claimedAt),
                      style: TextStyle(
                        color: Colors.grey.shade600,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
                
                // Right-side actions
                if (status == 'checked_out' || status == 'claimed')
                  _buildRatingSection(orderId, data),
                if (awaitingPickup)
                  TextButton.icon(
                    onPressed: () => _openPickupRoute(orderId, data),
                    icon: const Icon(Icons.route, size: 16),
                    label: const Text('View Pickup Route'),
                  ),
              ],
            ),
          ],
        ),
      ),
    );

    if (awaitingPickup) {
      return InkWell(
        onTap: () => _openPickupRoute(orderId, data),
        borderRadius: BorderRadius.circular(12),
        child: card,
      );
    }

    return card;
  }

  Widget _buildStatusChip(String status) {
    Color color;
    String label;
    
    switch (status) {
      case 'pending':
        color = Colors.orange;
        label = 'Preparing';
        break;
      case 'awaiting_pickup':
        color = Colors.blue;
        label = 'Ready for Pickup';
        break;
      case 'claimed':
        color = Colors.green;
        label = 'Picked Up';
        break;
      case 'checked_out':
        color = Colors.green.shade700;
        label = 'Completed';
        break;
      default:
        color = Colors.grey;
        label = status;
    }
    
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w500,
        ),
      ),
    );
  }

  Widget _buildRatingSection(String orderId, Map<String, dynamic> data) {
    final rating = data['rating'] ?? 0;
    final hasRated = rating > 0;
    
    if (hasRated) {
      return Row(
        children: [
          ...List.generate(5, (index) => Icon(
            index < rating ? Icons.star : Icons.star_border,
            color: Colors.amber,
            size: 16,
          )),
        ],
      );
    } else {
      return TextButton.icon(
        onPressed: () => _showRatingDialog(orderId),
        icon: const Icon(Icons.star_border, size: 16),
        label: const Text('Rate'),
        style: TextButton.styleFrom(
          foregroundColor: Colors.amber.shade700,
        ),
      );
    }
  }

  String _getOrderDateText(Timestamp? createdAt, Timestamp? checkoutDate, Timestamp? claimedAt) {
    if (claimedAt != null) {
      return 'Claimed: ${_formatDate(claimedAt)}';
    } else if (checkoutDate != null) {
      return 'Ordered: ${_formatDate(checkoutDate)}';
    } else if (createdAt != null) {
      return 'Created: ${_formatDate(createdAt)}';
    }
    return 'Unknown date';
  }

  String _formatDate(Timestamp timestamp) {
    final date = timestamp.toDate();
    return '${date.day}/${date.month}/${date.year} ${date.hour}:${date.minute.toString().padLeft(2, '0')}';
  }

  void _showRatingDialog(String orderId) {
    int rating = 0;
    
    showDialog(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setState) => AlertDialog(
          title: const Text('Rate Your Order'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text('How was your experience?'),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(5, (index) => IconButton(
                  onPressed: () => setState(() => rating = index + 1),
                  icon: Icon(
                    index < rating ? Icons.star : Icons.star_border,
                    color: Colors.amber,
                    size: 32,
                  ),
                )),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: rating > 0 ? () => _submitRating(orderId, rating) : null,
              child: const Text('Submit'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openPickupRoute(String orderId, Map<String, dynamic> data) async {
    try {
      Map<String, dynamic>? providerData;
      final items = (data['items'] as List?)?.cast<Map<String, dynamic>>() ?? [];
      if (items.isNotEmpty) {
        final listingId = items.first['listing_id'];
        if (listingId != null) {
          final listingDoc = await FirebaseFirestore.instance.collection('listings').doc(listingId).get();
          final listingData = listingDoc.data();
          final providerId = listingData?['provider_id'];
          if (providerId != null) {
            final providerDoc = await FirebaseFirestore.instance.collection('users').doc(providerId).get();
            providerData = providerDoc.data();
          }
        }
      }

      if (!mounted) return;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => PickupRouteScreen(
            cartId: orderId,
            provider: providerData ?? {'business_name': 'Provider', 'address': 'Bogo City, Cebu'},
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Unable to open pickup route: $e'), backgroundColor: Colors.red),
      );
    }
  }

  Future<void> _submitRating(String orderId, int rating) async {
    try {
      await FirebaseFirestore.instance
          .collection('cart')
          .doc(orderId)
          .update({
        'rating': rating,
        'rated_at': FieldValue.serverTimestamp(),
      });
      
      if (mounted) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Thank you for rating! ($rating stars)'),
            backgroundColor: Colors.green,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to submit rating: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }
}
