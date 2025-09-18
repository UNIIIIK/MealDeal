import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../../theme/app_theme.dart';
import '../auth/auth_service.dart';

class OrdersManagementScreen extends StatefulWidget {
  const OrdersManagementScreen({super.key});

  @override
  State<OrdersManagementScreen> createState() => _OrdersManagementScreenState();
}

class _OrdersManagementScreenState extends State<OrdersManagementScreen> {
  String _selectedFilter = 'all'; // all, pending, awaiting_pickup, claimed, checked_out

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'Orders Management',
          style: TextStyle(
            fontFamily: 'Poppins',
            fontWeight: FontWeight.bold,
          ),
        ),
        backgroundColor: AppTheme.primaryOrange,
        foregroundColor: Colors.white,
        elevation: 0,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(
            bottom: Radius.circular(20),
          ),
        ),
      ),
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
        child: Consumer<AuthService>(
          builder: (context, authService, child) {
            final providerId = authService.currentUser?.uid;

            if (providerId == null) {
              return const Center(child: Text('Not signed in'));
            }

            return Column(
              children: [
                _buildFilterChips(),
                Expanded(
                  child: _buildOrdersList(providerId),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  // 🔹 Filter chips row
  Widget _buildFilterChips() {
    return Container(
      padding: const EdgeInsets.all(16),
      child: Container(
        padding: const EdgeInsets.all(4),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.05),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
                children: [
                  _buildFilterChip('All', 'all', color: Colors.deepPurple),
                  _buildFilterChip('Preparing', 'pending', color: Colors.orange),
                  _buildFilterChip('Ready for Pickup', 'awaiting_pickup', color: Colors.blue),
                  _buildFilterChip('Picked Up', 'claimed', color: Colors.green),
                ],
              ),
        ),
      ),
    );
  }

  // 🔹 Filter chip
  Widget _buildFilterChip(String label, String value, {Color? color}) {
  final isSelected = _selectedFilter == value;
  return Container(
    margin: const EdgeInsets.only(right: 8),
    child: FilterChip(
      label: Text(
        label,
        style: TextStyle(
          color: isSelected ? Colors.white : AppTheme.mediumGray,
          fontWeight: FontWeight.w600,
          fontSize: 12,
          fontFamily: 'Inter',
        ),
      ),
      selected: isSelected,
      onSelected: (selected) {
        setState(() {
          _selectedFilter = value;
        });
      },
      selectedColor: color ?? AppTheme.primaryOrange, // when active
      backgroundColor: Colors.grey.shade200,          // when inactive
      checkmarkColor: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
    ),
  );
}


  // 🔹 Orders list builder (Firestore query + hybrid fallback)
  Widget _buildOrdersList(String providerId) {
    Query query = FirebaseFirestore.instance.collection('cart');

    // Try optimized query first
    if (_selectedFilter == 'all') {
      query = query.where('provider_ids', arrayContains: providerId);
    } else {
      query = query
          .where('provider_ids', arrayContains: providerId)
          .where('status', isEqualTo: _selectedFilter);
    }

    return StreamBuilder<QuerySnapshot>(
      stream: query.snapshots(),
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }

        if (snapshot.hasError) {
          return _buildErrorState(snapshot.error, providerId);
        }

        if (!snapshot.hasData || snapshot.data!.docs.isEmpty) {
          // Hybrid fallback: try pulling all docs and filtering manually
          return _buildFallbackOrdersList(providerId);
        }

        final filteredOrders = snapshot.data!.docs.toList();
        filteredOrders.sort((a, b) {
          final aData = a.data() as Map<String, dynamic>?;
          final bData = b.data() as Map<String, dynamic>?;
          final aTime = aData?['checkout_date'] as Timestamp?;
          final bTime = bData?['checkout_date'] as Timestamp?;
          if (aTime == null && bTime == null) return 0;
          if (aTime == null) return 1;
          if (bTime == null) return -1;
          return bTime.compareTo(aTime);
        });

        if (filteredOrders.isEmpty) {
          return Column(
            children: [
              _buildDebugInfo(providerId, filteredOrders.length, 0),
              Expanded(child: _buildEmptyState()),
            ],
          );
        }

        return _buildOrdersListView(filteredOrders);
      },
    );
  }
  // 🔹 Hybrid fallback: stream all docs and filter in memory
  Widget _buildFallbackOrdersList(String providerId) {
    final query = FirebaseFirestore.instance.collection('cart');

    return StreamBuilder<QuerySnapshot>(
      stream: query.snapshots(),
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }

        if (snapshot.hasError) {
          return _buildErrorState(snapshot.error, providerId);
        }

        if (!snapshot.hasData || snapshot.data!.docs.isEmpty) {
          return Column(
            children: [
              _buildDebugInfo(providerId, 0, 0),
              Expanded(child: _buildEmptyState()),
            ],
          );
        }

        final allOrders = snapshot.data!.docs.toList();

        // Fallback filtering (old data)
        final orders = allOrders.where((doc) {
          final data = doc.data() as Map<String, dynamic>? ?? {};
          bool hasProviderItem = false;

// Check top-level provider_ids first
final providerIds = (data['provider_ids'] as List<dynamic>?)
    ?.map((e) => e.toString())
    .toList() ?? [];
if (providerIds.contains(providerId)) {
  hasProviderItem = true;
}

// Check items[].provider_id (older orders)
if (!hasProviderItem) {
  final items = data['items'] as List<dynamic>? ?? [];
  hasProviderItem = items.any((item) {
    if (item is Map<String, dynamic>) {
      return item['provider_id'] == providerId;
    }
    return false;
  });
}

// If no provider info at all, fallback: show it (so past orders aren’t hidden)
if (!hasProviderItem && !data.containsKey('provider_ids')) {
  hasProviderItem = true;
}


          if (!hasProviderItem) {
            final fieldsToCheck = [
              'provider_id',
              'provider',
              'seller_id',
              'merchant_id',
              'business_id',
              'restaurant_id',
              'vendor_id',
            ];
            for (var f in fieldsToCheck) {
              if (data[f] == providerId) {
                hasProviderItem = true;
                break;
              }
            }
          }

          return hasProviderItem;
        }).toList();

        final filteredOrders = _selectedFilter == 'all'
            ? orders
            : orders.where((doc) {
                final data = doc.data() as Map<String, dynamic>? ?? {};
                String status;
if (data.containsKey('status')) {
  status = data['status'] as String? ?? 'pending';
} else if (data.containsKey('claimed_at')) {
  status = 'claimed';
} else {
  status = 'pending';
}

return _selectedFilter == 'all' || status == _selectedFilter;

              }).toList();

        filteredOrders.sort((a, b) {
          final aData = a.data() as Map<String, dynamic>?;
          final bData = b.data() as Map<String, dynamic>?;
          final aTime = aData?['checkout_date'] as Timestamp?;
          final bTime = bData?['checkout_date'] as Timestamp?;
          if (aTime == null && bTime == null) return 0;
          if (aTime == null) return 1;
          if (bTime == null) return -1;
          return bTime.compareTo(aTime);
        });

        if (filteredOrders.isEmpty) {
          return Column(
            children: [
              _buildDebugInfo(providerId, allOrders.length, orders.length),
              Expanded(child: _buildEmptyState()),
            ],
          );
        }

        return _buildOrdersListView(filteredOrders);
      },
    );
  }

  // 🔹 Debug info box
  Widget _buildDebugInfo(String providerId, int totalDocs, int providerOrders) {
    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.orange.shade50,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.orange.shade200),
      ),
      child: Column(
        children: [
          Text(
            'Debug Info:',
            style: TextStyle(
              fontWeight: FontWeight.bold,
              color: Colors.orange.shade800,
            ),
          ),
          const SizedBox(height: 8),
          Text('Provider ID: $providerId'),
          Text('Filter: $_selectedFilter'),
          Text('Total docs: $totalDocs'),
          Text('Orders for this provider: $providerOrders'),
        ],
      ),
    );
  }

  // 🔹 Error state
  Widget _buildErrorState(Object? error, String providerId) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.error_outline,
            size: 64,
            color: Colors.red.shade300,
          ),
          const SizedBox(height: 16),
          Text(
            'Error: $error',
            style: const TextStyle(
              fontSize: 16,
              color: Colors.red,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 16),
          Text(
            'Provider ID: $providerId',
            style: const TextStyle(
              fontSize: 12,
              color: Colors.grey,
            ),
            textAlign: TextAlign.center,
          ),
          Text(
            'Filter: $_selectedFilter',
            style: const TextStyle(
              fontSize: 12,
              color: Colors.grey,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  // 🔹 Empty state UI
  Widget _buildEmptyState() {
    String message;
    String subtitle;
    IconData icon;

    switch (_selectedFilter) {
      case 'pending':
        message = 'No orders being prepared';
        subtitle = 'Orders being prepared will appear here';
        icon = Icons.restaurant_outlined;
        break;
      case 'awaiting_pickup':
        message = 'No orders ready for pickup';
        subtitle = 'Orders ready for pickup will appear here';
        icon = Icons.local_shipping_outlined;
        break;
      case 'claimed':
        message = 'No picked up orders';
        subtitle = 'Orders that have been picked up will appear here';
        icon = Icons.check_circle_outline;
        break;
      default:
        message = 'No orders yet';
        subtitle = 'Orders will appear here when customers place them';
        icon = Icons.inbox_outlined;
    }

    return SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                gradient: AppTheme.foodGradient,
                borderRadius: BorderRadius.circular(80),
                boxShadow: AppTheme.foodShadow,
              ),
              child: Icon(
                icon,
                size: 50,
                color: Colors.white,
              ),
            ),
            const SizedBox(height: 24),
            Text(
              message,
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.bold,
                color: AppTheme.darkGray,
                fontFamily: 'Poppins',
              ),
            ),
            const SizedBox(height: 8),
            Text(
              subtitle,
              style: TextStyle(
                fontSize: 14,
                color: AppTheme.mediumGray,
                fontFamily: 'Inter',
                height: 1.4,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
  // 🔹 Orders list view
  Widget _buildOrdersListView(List<QueryDocumentSnapshot> filteredOrders) {
    return ListView.builder(
      padding: const EdgeInsets.only(
        left: 16,
        right: 16,
        top: 16,
        bottom: 80,
      ),
      itemCount: filteredOrders.length,
      itemBuilder: (context, index) {
        final order = filteredOrders[index];
        final orderData = order.data() as Map<String, dynamic>;
        return _buildOrderCard(order.id, orderData);
      },
    );
  }

  // 🔹 Single order card
  Widget _buildOrderCard(String orderId, Map<String, dynamic> orderData) {
    final items = orderData['items'] as List<dynamic>? ?? [];
    final totalPrice = orderData['total_price'] ?? 0.0;
    final status = orderData['status'] ?? 'pending';
    final checkoutDate = orderData['checkout_date'] as Timestamp?;
    final checkoutInfo = orderData['checkout_info'] as Map<String, dynamic>?;

    String customerName = 'Unknown Customer';
    String customerPhone = 'No phone provided';

    if (checkoutInfo != null) {
      customerName = checkoutInfo['name'] ?? customerName;
      customerPhone = checkoutInfo['phone'] ?? customerPhone;
    }

    Color statusColor;
    String statusText;
    IconData statusIcon;

    switch (status) {
      case 'pending':
        statusColor = Colors.orange;
        statusText = 'Preparing';
        statusIcon = Icons.restaurant;
        break;
      case 'awaiting_pickup':
        statusColor = Colors.blue;
        statusText = 'Ready for Pickup';
        statusIcon = Icons.local_shipping;
        break;
      case 'claimed':
        statusColor = Colors.green;
        statusText = 'Picked Up';
        statusIcon = Icons.check_circle;
        break;
      case 'checked_out':
        statusColor = Colors.grey;
        statusText = 'Completed';
        statusIcon = Icons.done_all;
        break;
      default:
        statusColor = Colors.grey;
        statusText = status;
        statusIcon = Icons.help;
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header: status + date
            Row(
              children: [
                Flexible(
                  child: Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: statusColor.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: statusColor.withValues(alpha: 0.3)),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(statusIcon, size: 16, color: statusColor),
                        const SizedBox(width: 4),
                        Flexible(
                          child: Text(
                            statusText,
                            style: TextStyle(
                              color: statusColor,
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                if (checkoutDate != null)
                  Flexible(
                    child: Text(
                      DateFormat('MMM dd, yyyy • HH:mm')
                          .format(checkoutDate.toDate()),
                      style: TextStyle(
                        color: Colors.grey.shade600,
                        fontSize: 12,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 12),

            // Customer info
            Row(
              children: [
                CircleAvatar(
                  radius: 20,
                  backgroundColor: Colors.orange.shade100,
                  child: Text(
                    customerName.isNotEmpty
                        ? customerName[0].toUpperCase()
                        : 'C',
                    style: TextStyle(
                      color: Colors.orange.shade700,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        customerName,
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                        ),
                        overflow: TextOverflow.ellipsis,
                        maxLines: 1,
                      ),
                      Text(
                        customerPhone,
                        style: TextStyle(
                          color: Colors.grey.shade600,
                          fontSize: 14,
                        ),
                        overflow: TextOverflow.ellipsis,
                        maxLines: 1,
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Flexible(
                  child: Text(
                    '₱${totalPrice.toStringAsFixed(0)}',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                      color: Colors.green.shade700,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),

            // Items
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.grey.shade50,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Order Items (${items.length})',
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                    ),
                  ),
                  const SizedBox(height: 8),
                  ...items.take(3).map((item) {
                    final itemData = item as Map<String, dynamic>;
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 4),
                      child: Row(
                        children: [
                          Text(
                            '• ${itemData['title'] ?? 'Unknown Item'}',
                            style: const TextStyle(fontSize: 13),
                          ),
                          const Spacer(),
                          Text(
                            'Qty: ${itemData['quantity'] ?? 1}',
                            style: TextStyle(
                              fontSize: 12,
                              color: Colors.grey.shade600,
                            ),
                          ),
                        ],
                      ),
                    );
                  }),
                  if (items.length > 3)
                    Text(
                      '... and ${items.length - 3} more items',
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey.shade600,
                        fontStyle: FontStyle.italic,
                      ),
                    ),
                ],
              ),
            ),
            // Action buttons
            if (status == 'pending' || status == 'awaiting_pickup') ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  if (status == 'pending')
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: () =>
                            _updateOrderStatus(orderId, 'awaiting_pickup'),
                        icon: const Icon(Icons.check, size: 16),
                        label: const Text(
                          'Mark Ready',
                          style: TextStyle(fontSize: 12),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.blue.shade600,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 8),
                        ),
                      ),
                    ),
                  if (status == 'pending') const SizedBox(width: 8),
                  if (status == 'awaiting_pickup')
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: () async {
                          final confirmed = await showDialog<bool>(
                            context: context,
                            builder: (context) => AlertDialog(
                              title: const Text('Confirm Pickup'),
                              content: const Text(
                                  'Confirm this order has been collected?'),
                              actions: [
                                TextButton(
                                  onPressed: () =>
                                      Navigator.of(context).pop(false),
                                  child: const Text('Cancel'),
                                ),
                                TextButton(
                                  onPressed: () =>
                                      Navigator.of(context).pop(true),
                                  child: const Text('Confirm'),
                                ),
                              ],
                            ),
                          );
                          if (confirmed == true) {
                            await _updateOrderStatus(orderId, 'claimed');
                          }
                        },
                        icon: const Icon(Icons.check_circle, size: 16),
                        label: const Text(
                          'Pickup',
                          style: TextStyle(fontSize: 12),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.green.shade600,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 8),
                        ),
                      ),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  // 🔹 Update order status
  Future<void> _updateOrderStatus(String orderId, String newStatus) async {
    try {
      await FirebaseFirestore.instance.collection('cart').doc(orderId).update({
        'status': newStatus,
        'updated_at': FieldValue.serverTimestamp(),
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
                'Order status updated to ${newStatus.replaceAll('_', ' ')}'),
            backgroundColor: Colors.green.shade600,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error updating order: $e'),
            backgroundColor: Colors.red.shade600,
          ),
        );
      }
    }
  }
}
