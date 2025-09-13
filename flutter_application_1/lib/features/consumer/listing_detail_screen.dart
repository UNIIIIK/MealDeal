import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../auth/auth_service.dart';
import '../../services/messaging_service.dart';
import '../../models/message.dart';
import '../messaging/chat_screen.dart';

class ListingDetailScreen extends StatefulWidget {
  final String listingId;
  final Map<String, dynamic> listingData;

  const ListingDetailScreen({
    super.key,
    required this.listingId,
    required this.listingData,
  });

  @override
  State<ListingDetailScreen> createState() => _ListingDetailScreenState();
}

class _ListingDetailScreenState extends State<ListingDetailScreen> {
  int _quantity = 1;
  bool _isLoading = false;
  Map<String, dynamic>? _providerData;
  bool _isMessagingLoading = false;

  @override
  void initState() {
    super.initState();
    _loadProviderData();
  }

  Future<void> _loadProviderData() async {
    try {
      final doc = await FirebaseFirestore.instance
          .collection('users')
          .doc(widget.listingData['provider_id'])
          .get();
      
      if (doc.exists) {
        setState(() {
          _providerData = doc.data();
        });
      }
    } catch (e) {
      debugPrint('Error loading provider data: $e');
    }
  }

  Future<void> _addToCart() async {
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

    setState(() => _isLoading = true);

    try {
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
      final itemPrice = (widget.listingData['discounted_price'] ?? 0.0) * _quantity;
      
      await cartRef.update({
        'items': FieldValue.arrayUnion([{
          'listing_id': widget.listingId,
          'title': widget.listingData['title'],
          'price': widget.listingData['discounted_price'],
          'quantity': _quantity,
          'image': widget.listingData['images'] is List && 
              (widget.listingData['images'] as List).isNotEmpty 
              ? (widget.listingData['images'] as List)[0] 
              : null,
        }]),
        'total_price': FieldValue.increment(itemPrice),
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${widget.listingData['title']} added to cart!'),
            backgroundColor: Colors.green,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error adding to cart: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _startConversation() async {
    final authService = Provider.of<AuthService>(context, listen: false);
    final messagingService = Provider.of<MessagingService>(context, listen: false);
    
    if (authService.currentUser == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please log in to start a conversation')),
      );
      return;
    }

    if (!authService.hasRole('food_consumer')) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Only food consumers can message providers')),
      );
      return;
    }

    setState(() => _isMessagingLoading = true);

    try {
      // Create or get existing conversation
      final conversationId = await messagingService.createOrGetConversation(
        otherUserId: widget.listingData['provider_id'],
        listingId: widget.listingId,
      );

      // Get provider info
      final providerInfo = await messagingService.getUserInfo(widget.listingData['provider_id']);
      
      if (providerInfo != null) {
        // Send system message about the listing
        await messagingService.sendSystemMessage(
          conversationId: conversationId,
          content: 'Conversation started about: ${widget.listingData['title']}',
          listingId: widget.listingId,
        );

        if (mounted) {
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (context) => ChatScreen(
                conversationId: conversationId,
                otherUser: providerInfo,
                listingId: widget.listingId,
              ),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error starting conversation: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      setState(() => _isMessagingLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final expiryRaw = widget.listingData['expiry_datetime'];
    final expiryDate = expiryRaw is Timestamp ? expiryRaw.toDate() : DateTime.now();
    final timeUntilExpiry = expiryDate.difference(DateTime.now());
    final isExpiringSoon = timeUntilExpiry.inHours < 24;

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.listingData['title'] ?? 'Listing Details'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
        actions: [
          Consumer<AuthService>(
            builder: (context, authService, child) {
              if (authService.hasRole('food_consumer')) {
                return IconButton(
                  onPressed: _isMessagingLoading ? null : _startConversation,
                  icon: _isMessagingLoading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.message),
                  tooltip: 'Message Provider',
                );
              }
              return const SizedBox.shrink();
            },
          ),
        ],
      ),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Image carousel
            SizedBox(
              height: 300,
              child: widget.listingData['images'] != null && 
                     widget.listingData['images'] is List &&
                     (widget.listingData['images'] as List).isNotEmpty
                  ? PageView.builder(
                      itemCount: (widget.listingData['images'] as List).length,
                      itemBuilder: (context, index) {
                        return Image.network(
                          (widget.listingData['images'] as List)[index],
                          fit: BoxFit.cover,
                          errorBuilder: (context, error, stackTrace) {
                            return Container(
                              color: Colors.grey.shade300,
                              child: const Icon(Icons.fastfood, size: 80),
                            );
                          },
                        );
                      },
                    )
                  : Container(
                      color: Colors.grey.shade300,
                      child: const Center(
                        child: Icon(Icons.fastfood, size: 80),
                      ),
                    ),
            ),

            Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Title and price
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Text(
                          widget.listingData['title'] ?? '',
                          style: const TextStyle(
                            fontSize: 24,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            '₱${widget.listingData['original_price']?.toStringAsFixed(2) ?? '0.00'}',
                            style: const TextStyle(
                              decoration: TextDecoration.lineThrough,
                              color: Colors.grey,
                              fontSize: 16,
                            ),
                          ),
                          Text(
                            '₱${widget.listingData['discounted_price']?.toStringAsFixed(2) ?? '0.00'}',
                            style: TextStyle(
                              color: Colors.green.shade700,
                              fontWeight: FontWeight.bold,
                              fontSize: 20,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),

                  // Expiry warning
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: isExpiringSoon ? Colors.orange.shade100 : Colors.blue.shade100,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(
                        color: isExpiringSoon ? Colors.orange.shade300 : Colors.blue.shade300,
                      ),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          isExpiringSoon ? Icons.warning : Icons.schedule,
                          color: isExpiringSoon ? Colors.orange.shade700 : Colors.blue.shade700,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            isExpiringSoon
                                ? 'Expires in ${timeUntilExpiry.inHours} hours - Order soon!'
                                : 'Expires on ${DateFormat('MMM dd, yyyy - HH:mm').format(expiryDate)}',
                            style: TextStyle(
                              color: isExpiringSoon ? Colors.orange.shade700 : Colors.blue.shade700,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Description
                  const Text(
                    'Description',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    widget.listingData['description'] ?? 'No description available',
                    style: const TextStyle(fontSize: 16),
                  ),
                  const SizedBox(height: 16),

                  // Allergens
                  const Text(
                    'Allergens',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.red.shade200),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.warning, color: Colors.red.shade700),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            widget.listingData['allergens'] is List
                                ? (widget.listingData['allergens'] as List).join(', ')
                                : (widget.listingData['allergens'] ?? 'No allergen information').toString(),
                            style: TextStyle(
                              color: Colors.red.shade700,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Provider info
                  if (_providerData != null) ...[
                    const Text(
                      'Provider',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Row(
                          children: [
                            CircleAvatar(
                              child: Text(_providerData!['name']?[0] ?? 'P'),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    _providerData!['name'] ?? 'Unknown Provider',
                                    style: const TextStyle(fontWeight: FontWeight.bold),
                                  ),
                                  Text(_providerData!['address'] ?? ''),
                                ],
                              ),
                            ),
                            Consumer<AuthService>(
                              builder: (context, authService, child) {
                                if (authService.hasRole('food_consumer')) {
                                  return ElevatedButton.icon(
                                    onPressed: _isMessagingLoading ? null : _startConversation,
                                    icon: _isMessagingLoading
                                        ? const SizedBox(
                                            width: 16,
                                            height: 16,
                                            child: CircularProgressIndicator(strokeWidth: 2),
                                          )
                                        : const Icon(Icons.message, size: 16),
                                    label: Text(_isMessagingLoading ? 'Starting...' : 'Message'),
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: Colors.green.shade600,
                                      foregroundColor: Colors.white,
                                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                    ),
                                  );
                                }
                                return const SizedBox.shrink();
                              },
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 16),

                  // Quantity and fulfillment info
                  Row(
                    children: [
                      Expanded(
                        child: Card(
                          child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Column(
                              children: [
                                const Text('Available Quantity'),
                                Text(
                                  '${widget.listingData['quantity'] ?? 0}',
                                  style: const TextStyle(
                                    fontSize: 20,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                      Expanded(
                        child: Card(
                          child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Column(
                              children: [
                                const Text('Fulfillment'),
                                Text(
                                  widget.listingData['type']?.toUpperCase() ?? 'PICKUP',
                                  style: const TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),

                  // Quantity selector and add to cart
                  Consumer<AuthService>(
                    builder: (context, authService, child) {
                      if (authService.hasRole('food_consumer')) {
                        return Column(
                          children: [
                            Row(
                              children: [
                                const Text(
                                  'Quantity:',
                                  style: TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                const Spacer(),
                                IconButton(
                                  onPressed: _quantity > 1 
                                      ? () => setState(() => _quantity--)
                                      : null,
                                  icon: const Icon(Icons.remove),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 16,
                                    vertical: 8,
                                  ),
                                  decoration: BoxDecoration(
                                    border: Border.all(color: Colors.grey),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Text(
                                    '$_quantity',
                                    style: const TextStyle(
                                      fontSize: 16,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ),
                                IconButton(
                                  onPressed: _quantity < (widget.listingData['quantity'] ?? 1)
                                      ? () => setState(() => _quantity++)
                                      : null,
                                  icon: const Icon(Icons.add),
                                ),
                              ],
                            ),
                            const SizedBox(height: 16),
                            SizedBox(
                              width: double.infinity,
                              child: ElevatedButton(
                                onPressed: _isLoading ? null : _addToCart,
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
                                    : Text(
                                        'Add to Cart - ₱${(widget.listingData['discounted_price'] * _quantity).toStringAsFixed(2)}',
                                        style: const TextStyle(
                                          fontSize: 16,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                              ),
                            ),
                          ],
                        );
                      }
                      return const SizedBox.shrink();
                    },
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
