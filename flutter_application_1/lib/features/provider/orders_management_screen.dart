import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
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
        title: const Text('Orders Management'),
        backgroundColor: Colors.orange.shade600,
        foregroundColor: Colors.white,
        actions: [],
      ),
      body: Consumer<AuthService>(
        builder: (context, authService, child) {
          final providerId = authService.currentUser?.uid;
          
          if (providerId == null) {
            return const Center(child: Text('Not signed in'));
          }

          return Column(
            children: [
              // Filter chips
              Container(
                padding: const EdgeInsets.all(16),
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      _buildFilterChip('All', 'all'),
                      const SizedBox(width: 8),
                      _buildFilterChip('Preparing', 'pending'),
                      const SizedBox(width: 8),
                      _buildFilterChip('Ready for Pickup', 'awaiting_pickup'),
                      const SizedBox(width: 8),
                      _buildFilterChip('Picked Up', 'claimed'),
                    ],
                  ),
                ),
              ),
              
              // Orders list
              Expanded(
                child: _buildOrdersList(providerId),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildFilterChip(String label, String value) {
    final isSelected = _selectedFilter == value;
    return FilterChip(
      label: Text(label),
      selected: isSelected,
      onSelected: (selected) {
        setState(() {
          _selectedFilter = value;
        });
      },
      selectedColor: Colors.orange.shade100,
      checkmarkColor: Colors.orange.shade700,
      labelStyle: TextStyle(
        color: isSelected ? Colors.orange.shade700 : Colors.grey.shade600,
        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
      ),
    );
  }


  Widget _buildOrdersList(String providerId) {
    Query query;
    
    if (_selectedFilter == 'all') {
      query = FirebaseFirestore.instance
          .collection('cart')
          .where('status', whereIn: ['pending', 'awaiting_pickup', 'claimed'])
          .orderBy('checkout_date', descending: true);
    } else {
      query = FirebaseFirestore.instance
          .collection('cart')
          .where('status', isEqualTo: _selectedFilter)
          .orderBy('checkout_date', descending: true);
    }

    return StreamBuilder<QuerySnapshot>(
      stream: query.snapshots(),
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }

        if (snapshot.hasError) {
          return Center(child: Text('Error: ${snapshot.error}'));
        }

        if (!snapshot.hasData || snapshot.data!.docs.isEmpty) {
          return _buildEmptyState();
        }

        final orders = snapshot.data!.docs;
        
        return ListView.builder(
          padding: const EdgeInsets.all(16),
          itemCount: orders.length,
          itemBuilder: (context, index) {
            final order = orders[index];
            final orderData = order.data() as Map<String, dynamic>;
            return _buildOrderCard(order.id, orderData);
          },
        );
      },
    );
  }

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

    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            icon,
            size: 64,
            color: Colors.grey.shade400,
          ),
          const SizedBox(height: 16),
          Text(
            message,
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: Colors.grey.shade600,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            subtitle,
            style: TextStyle(
              color: Colors.grey.shade500,
              fontSize: 14,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

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
            // Header with status and date
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
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
                      Text(
                        statusText,
                        style: TextStyle(
                          color: statusColor,
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ),
                const Spacer(),
                if (checkoutDate != null)
                  Text(
                    DateFormat('MMM dd, yyyy • HH:mm').format(checkoutDate.toDate()),
                    style: TextStyle(
                      color: Colors.grey.shade600,
                      fontSize: 12,
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
                    customerName.isNotEmpty ? customerName[0].toUpperCase() : 'C',
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
                      ),
                      Text(
                        customerPhone,
                        style: TextStyle(
                          color: Colors.grey.shade600,
                          fontSize: 14,
                        ),
                      ),
                    ],
                  ),
                ),
                Text(
                  '₱${totalPrice.toStringAsFixed(0)}',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 18,
                    color: Colors.green.shade700,
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
                        onPressed: () => _updateOrderStatus(orderId, 'awaiting_pickup'),
                        icon: const Icon(Icons.check, size: 18),
                        label: const Text('Mark Ready'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.blue.shade600,
                          foregroundColor: Colors.white,
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
                              content: const Text('Confirm this order has been collected?'),
                              actions: [
                                TextButton(
                                  onPressed: () => Navigator.of(context).pop(false),
                                  child: const Text('Cancel'),
                                ),
                                TextButton(
                                  onPressed: () => Navigator.of(context).pop(true),
                                  child: const Text('Confirm'),
                                ),
                              ],
                            ),
                          );
                          if (confirmed == true) {
                            await _updateOrderStatus(orderId, 'claimed');
                          }
                        },
                        icon: const Icon(Icons.check_circle, size: 18),
                        label: const Text('Pickup'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.green.shade600,
                          foregroundColor: Colors.white,
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

  Future<void> _updateOrderStatus(String orderId, String newStatus) async {
    try {
      await FirebaseFirestore.instance
          .collection('cart')
          .doc(orderId)
          .update({
        'status': newStatus,
        'updated_at': FieldValue.serverTimestamp(),
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Order status updated to ${newStatus.replaceAll('_', ' ')}'),
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
