import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:provider/provider.dart';
import '../auth/auth_service.dart';
import 'checkout_screen.dart';

class CartScreen extends StatefulWidget {
  const CartScreen({super.key});

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  bool _isLoading = false;

  Future<void> _updateItemQuantity(String cartId, List items, int index, int newQuantity) async {
    try {
      final updatedItems = List.from(items);
      final item = updatedItems[index];
      
      if (newQuantity <= 0) {
        // Remove item
        updatedItems.removeAt(index);
      } else {
        // Check available quantity from the listing
        final listingId = item['listing_id'];
        if (listingId != null) {
          final listingDoc = await FirebaseFirestore.instance
              .collection('listings')
              .doc(listingId)
              .get();
          
          if (listingDoc.exists) {
            final listingData = listingDoc.data()!;
            final availableQuantity = listingData['quantity'] ?? 0;
            
            if (newQuantity > availableQuantity) {
              if (!mounted) return;
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('Only $availableQuantity items available in stock'),
                  backgroundColor: Colors.orange,
                ),
              );
              return;
            }
          }
        }
        
        updatedItems[index]['quantity'] = newQuantity;
      }

      // Calculate new total
      double newTotal = 0;
      for (var item in updatedItems) {
        newTotal += (item['price'] * item['quantity']);
      }

      await FirebaseFirestore.instance
          .collection('cart')
          .doc(cartId)
          .update({
        'items': updatedItems,
        'total_price': newTotal,
      });
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error updating cart: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<int> _getAvailableQuantity(String? listingId) async {
    if (listingId == null) return 0;
    
    try {
      final listingDoc = await FirebaseFirestore.instance
          .collection('listings')
          .doc(listingId)
          .get();
      
      if (listingDoc.exists) {
        final listingData = listingDoc.data()!;
        return listingData['quantity'] ?? 0;
      }
    } catch (e) {
      debugPrint('Error getting available quantity: $e');
    }
    
    return 0;
  }

  Future<void> _checkout(String cartId, List items, double totalPrice) async {
    if (_isLoading) return;
    setState(() => _isLoading = true);
    try {
      if (!mounted) return;
      final result = await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => CheckoutScreen(
            cartId: cartId,
            items: List<Map<String, dynamic>>.from(items.map((e) => Map<String, dynamic>.from(e))),
            totalPrice: totalPrice,
          ),
        ),
      );

      if (!mounted) return;
      if (result is Map && result['status'] == 'awaiting_pickup') {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Checkout info saved. Proceed to pickup details.'), backgroundColor: Colors.green),
        );
        Navigator.of(context).pop();
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Shopping Cart'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
      ),
      body: Consumer<AuthService>(
        builder: (context, authService, child) {
          if (authService.currentUser == null) {
            return const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.shopping_cart_outlined, size: 64, color: Colors.grey),
                  SizedBox(height: 16),
                  Text(
                    'Please log in to view your cart',
                    style: TextStyle(fontSize: 18, color: Colors.grey),
                  ),
                ],
              ),
            );
          }

          return StreamBuilder<QuerySnapshot>(
            stream: FirebaseFirestore.instance
                .collection('cart')
                .where('consumer_id', isEqualTo: authService.currentUser!.uid)
                .where('status', isEqualTo: 'pending')
                .snapshots(),
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Center(child: CircularProgressIndicator());
              }

              if (snapshot.hasError) {
                return Center(
                  child: Text('Error: ${snapshot.error}'),
                );
              }

              if (!snapshot.hasData || snapshot.data!.docs.isEmpty) {
                return const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.shopping_cart_outlined, size: 64, color: Colors.grey),
                      SizedBox(height: 16),
                      Text(
                        'Your cart is empty',
                        style: TextStyle(fontSize: 18, color: Colors.grey),
                      ),
                      Text(
                        'Start shopping to add items!',
                        style: TextStyle(color: Colors.grey),
                      ),
                    ],
                  ),
                );
              }

              final cartDoc = snapshot.data!.docs.first;
              final cartData = cartDoc.data() as Map<String, dynamic>;
              final items = cartData['items'] as List? ?? [];
              final totalPrice = cartData['total_price']?.toDouble() ?? 0.0;

              if (items.isEmpty) {
                return const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.shopping_cart_outlined, size: 64, color: Colors.grey),
                      SizedBox(height: 16),
                      Text(
                        'Your cart is empty',
                        style: TextStyle(fontSize: 18, color: Colors.grey),
                      ),
                    ],
                  ),
                );
              }

              return Column(
                children: [
                  // Cart items
                  Expanded(
                    child: ListView.builder(
                      padding: const EdgeInsets.all(16),
                      itemCount: items.length,
                      itemBuilder: (context, index) {
                        final item = items[index];
                        return Dismissible(
                          key: ValueKey('cart_${item['listing_id'] ?? index}_${item['title']}'),
                          direction: DismissDirection.endToStart,
                          background: Container(
                            alignment: Alignment.centerRight,
                            padding: const EdgeInsets.symmetric(horizontal: 20),
                            decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(12)),
                            child: Icon(Icons.delete, color: Colors.red.shade600),
                          ),
                          onDismissed: (_) => _updateItemQuantity(cartDoc.id, items, index, 0),
                          child: _buildCartItem(cartDoc.id, items, index, item),
                        );
                      },
                    ),
                  ),
                  
                  // Cart summary and checkout
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.grey.shade50,
                      border: Border(
                        top: BorderSide(color: Colors.grey.shade300),
                      ),
                    ),
                    child: Column(
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Total:',
                              style: TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(
                              '₱${totalPrice.toStringAsFixed(2)}',
                              style: TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.bold,
                                color: Colors.green.shade700,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: _isLoading 
                                ? null 
                                : () => _checkout(cartDoc.id, items, totalPrice),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.green.shade600,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 16),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            child: _isLoading
                                ? const SizedBox(
                                    height: 20,
                                    width: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                                    ),
                                  )
                                : const Text(
                                    'Proceed to Checkout',
                                    style: TextStyle(
                                      fontSize: 16,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              );
            },
          );
        },
      ),
    );
  }

  Widget _buildCartItem(String cartId, List items, int index, Map<String, dynamic> item) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            // Item image
            Container(
              width: 80,
              height: 80,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(8),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: item['image'] != null
                    ? Image.network(
                        item['image'],
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) {
                          return Container(
                            color: Colors.grey.shade300,
                            child: const Icon(Icons.fastfood, size: 32),
                          );
                        },
                      )
                    : Container(
                        color: Colors.grey.shade300,
                        child: const Icon(Icons.fastfood, size: 32),
                      ),
              ),
            ),
            const SizedBox(width: 12),
            
            // Item details
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item['title'] ?? 'Unknown Item',
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '₱${item['price']?.toStringAsFixed(2) ?? '0.00'} each',
                    style: TextStyle(
                      color: Colors.green.shade700,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 8),
                  
                  // Quantity controls
                  Row(
                    children: [
                      IconButton(
                        onPressed: () => _updateItemQuantity(
                          cartId, 
                          items, 
                          index, 
                          (item['quantity'] ?? 1) - 1
                        ),
                        icon: const Icon(Icons.remove_circle_outline),
                        constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
                        padding: EdgeInsets.zero,
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                        decoration: BoxDecoration(
                          border: Border.all(color: Colors.grey.shade300),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          '${item['quantity'] ?? 1}',
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),
                      ),
                      FutureBuilder<int>(
                        future: _getAvailableQuantity(item['listing_id']),
                        builder: (context, snapshot) {
                          final availableQuantity = snapshot.data ?? 0;
                          final currentQuantity = item['quantity'] ?? 1;
                          final canAddMore = currentQuantity < availableQuantity;
                          
                          return IconButton(
                            onPressed: canAddMore ? () => _updateItemQuantity(
                              cartId, 
                              items, 
                              index, 
                              currentQuantity + 1
                            ) : null,
                            icon: Icon(
                              Icons.add_circle_outline,
                              color: canAddMore ? null : Colors.grey.shade400,
                            ),
                            constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
                            padding: EdgeInsets.zero,
                          );
                        },
                      ),
                      const Spacer(),
                      
                      // Swipe to delete enabled; explicit delete removed
                    ],
                  ),
                ],
              ),
            ),
            
            // Item total price
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  '₱${((item['price'] ?? 0.0) * (item['quantity'] ?? 1)).toStringAsFixed(2)}',
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
