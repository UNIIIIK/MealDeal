import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:latlong2/latlong.dart' as ll;
import 'package:provider/provider.dart';
import 'pickup_route_screen.dart';
import '../provider/location_picker_screen.dart';
import '../auth/auth_service.dart';

class CheckoutScreen extends StatefulWidget {
  final String cartId;
  final List<Map<String, dynamic>> items;
  final double totalPrice;

  const CheckoutScreen({super.key, required this.cartId, required this.items, required this.totalPrice});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _formKey = GlobalKey<FormState>();
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _phoneController = TextEditingController();
  bool _isSubmitting = false;
  ll.LatLng? _selectedLocation;
  String? _selectedAddress;
  bool _isLoadingUserData = true;

  @override
  void initState() {
    super.initState();
    _loadUserData();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _loadUserData() async {
    try {
      final authService = Provider.of<AuthService>(context, listen: false);
      if (authService.currentUser != null) {
        final userDoc = await FirebaseFirestore.instance
            .collection('users')
            .doc(authService.currentUser!.uid)
            .get();
        
        if (userDoc.exists) {
          final userData = userDoc.data() as Map<String, dynamic>;
          debugPrint('User data loaded: ${userData.toString()}');
          
          // Load saved location if available
          final savedLocation = userData['saved_location'];
          final regularLocation = userData['location'];
          debugPrint('Saved location data: ${savedLocation.toString()}');
          debugPrint('Regular location data: ${regularLocation.toString()}');
          
          // Try saved_location first, then fall back to location
          Map<String, dynamic>? locationData = savedLocation;
          if (locationData == null || locationData['lat'] == null || locationData['lng'] == null) {
            locationData = regularLocation;
          }
          
          setState(() {
            _nameController.text = userData['name'] ?? '';
            _phoneController.text = userData['phone'] ?? '';
            
            if (locationData != null && locationData['lat'] != null && locationData['lng'] != null) {
              _selectedLocation = ll.LatLng(
                (locationData['lat'] ?? 0).toDouble(),
                (locationData['lng'] ?? 0).toDouble(),
              );
              _selectedAddress = locationData['address'] ?? userData['address'] ?? 'Saved location in Bogo City';
              debugPrint('Location loaded: $_selectedLocation, Address: $_selectedAddress');
            } else {
              debugPrint('No saved location found or location data is incomplete');
            }
            _isLoadingUserData = false;
          });
          
          // Show success message if location was auto-filled
          if (mounted && locationData != null && 
              locationData['lat'] != null && 
              locationData['lng'] != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Your saved location has been automatically loaded!'),
                backgroundColor: Colors.green,
                duration: Duration(seconds: 2),
              ),
            );
          }
        } else {
          debugPrint('User document does not exist');
          setState(() {
            _isLoadingUserData = false;
          });
        }
      } else {
        debugPrint('No authenticated user found');
        setState(() {
          _isLoadingUserData = false;
        });
      }
    } catch (e) {
      debugPrint('Error loading user data: $e');
      setState(() {
        _isLoadingUserData = false;
      });
    }
  }

  Future<void> _selectLocation() async {
    final result = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        builder: (context) => LocationPickerScreen(
          initialLocation: _selectedLocation,
          initialAddress: _selectedAddress,
        ),
      ),
    );

    if (result != null && mounted) {
      setState(() {
        _selectedLocation = result['location'];
        _selectedAddress = result['address'];
      });
    }
  }

  Future<void> _submitCheckout() async {
    if (!_formKey.currentState!.validate()) return;
    
    if (_selectedLocation == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please select your location'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }
    
    setState(() => _isSubmitting = true);
    try {
      final authService = Provider.of<AuthService>(context, listen: false);
      
      // Save user data for future use
      if (authService.currentUser != null) {
        await FirebaseFirestore.instance
            .collection('users')
            .doc(authService.currentUser!.uid)
            .update({
          'name': _nameController.text.trim(),
          'phone': _phoneController.text.trim(),
          'saved_location': {
            'lat': _selectedLocation!.latitude,
            'lng': _selectedLocation!.longitude,
            'address': _selectedAddress ?? 'Selected location in Bogo City',
          },
          'last_updated': FieldValue.serverTimestamp(),
        });
      }

      // Update cart with checkout info
      await FirebaseFirestore.instance.collection('cart').doc(widget.cartId).update({
        'checkout_info': {
          'name': _nameController.text.trim(),
          'phone': _phoneController.text.trim(),
          'customer_location': {
            'lat': _selectedLocation!.latitude,
            'lng': _selectedLocation!.longitude,
          },
          'customer_address': _selectedAddress ?? 'Selected location in Bogo City',
          'payment_method': 'cash_on_pickup',
        },
        'status': 'awaiting_pickup',
        'checkout_date': FieldValue.serverTimestamp(),
      });

      // Create notification for providers
      await _createOrderNotifications(widget.cartId, widget.items);

      // Update listing quantities
      for (final item in widget.items) {
        final listingId = item['listing_id'];
        final quantity = item['quantity'] as int;
        
        if (listingId != null) {
          final listingRef = FirebaseFirestore.instance.collection('listings').doc(listingId);
          
          // Use transaction to safely update quantity
          await FirebaseFirestore.instance.runTransaction((transaction) async {
            final listingDoc = await transaction.get(listingRef);
            if (listingDoc.exists) {
              final currentQuantity = listingDoc.data()?['quantity'] ?? 0;
              final newQuantity = currentQuantity - quantity;
              
              if (newQuantity <= 0) {
                // Remove listing if quantity becomes 0 or negative
                transaction.update(listingRef, {
                  'status': 'sold_out',
                  'quantity': 0,
                  'updated_at': FieldValue.serverTimestamp(),
                });
              } else {
                // Update quantity
                transaction.update(listingRef, {
                  'quantity': newQuantity,
                  'updated_at': FieldValue.serverTimestamp(),
                });
              }
            }
          });
        }
      }

      if (!mounted) return;
      // Find provider info from the first item's listing
      Map<String, dynamic>? providerData;
      if (widget.items.isNotEmpty) {
        final firstItem = widget.items.first;
        final listingId = firstItem['listing_id'];
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
            cartId: widget.cartId,
            provider: providerData ?? {'name': 'Provider', 'address': 'Bogo City, Cebu'},
          ),
        ),
      );

      if (!mounted) return;
      Navigator.of(context).pop({'status': 'awaiting_pickup'});
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Checkout failed: $e'), backgroundColor: Colors.red),
      );
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<void> _confirmBeforeProceed() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedLocation == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select your location'), backgroundColor: Colors.red),
      );
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text('Confirm Order'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Please review your order before proceeding.'),
              const SizedBox(height: 12),
              // Itemized summary
              ...widget.items.map((item) => Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Row(
                  children: [
                    Expanded(child: Text('${item['title'] ?? 'Item'} x${item['quantity'] ?? 1}', overflow: TextOverflow.ellipsis)),
                    Text('₱${((item['price'] ?? 0.0) * (item['quantity'] ?? 1)).toStringAsFixed(2)}'),
                  ],
                ),
              )),
              const Divider(),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Total', style: TextStyle(fontWeight: FontWeight.bold)),
                  Text('₱${widget.totalPrice.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.bold)),
                ],
              ),
              const SizedBox(height: 12),
              Text('Pickup from your location: ${_selectedAddress ?? 'Bogo City'}', style: const TextStyle(fontSize: 12, color: Colors.grey)),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Modify'),
            ),
            ElevatedButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('Confirm'),
            ),
          ],
        );
      },
    );

    if (confirmed == true) {
      await _submitCheckout();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Checkout'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.green.shade50,
                  border: Border.all(color: Colors.green.shade200),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  children: [
                    Icon(Icons.payments, color: Colors.green.shade700),
                    const SizedBox(width: 8),
                    const Text('Payment: Cash on Pickup only', style: TextStyle(fontWeight: FontWeight.bold)),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _nameController,
                decoration: const InputDecoration(labelText: 'Full Name'),
                validator: (v) => (v == null || v.trim().length < 2) ? 'Enter your name' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _phoneController,
                decoration: const InputDecoration(labelText: 'Phone Number'),
                keyboardType: TextInputType.phone,
                validator: (v) => (v == null || v.trim().length < 7) ? 'Enter a valid phone' : null,
              ),
              const SizedBox(height: 16),
              
              // Location Selection Section
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.blue.shade50,
                  border: Border.all(color: Colors.blue.shade200),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.location_on, color: Colors.blue.shade600),
                        const SizedBox(width: 8),
                                                 const Text(
                           'Your Location',
                           style: TextStyle(fontWeight: FontWeight.bold),
                         ),
                      ],
                    ),
                    const SizedBox(height: 8),
                                         const Text(
                       'Select your location in Bogo City (starting point for pickup route)',
                       style: TextStyle(fontSize: 12, color: Colors.grey),
                     ),
                    const SizedBox(height: 12),
                    
                                         // Selected Location Display
                     if (_selectedAddress != null) ...[
                       Container(
                         padding: const EdgeInsets.all(12),
                         decoration: BoxDecoration(
                           color: Colors.green.shade50,
                           borderRadius: BorderRadius.circular(8),
                           border: Border.all(color: Colors.green.shade200),
                         ),
                         child: Row(
                           children: [
                             Icon(Icons.check_circle, color: Colors.green.shade600, size: 20),
                             const SizedBox(width: 12),
                             Expanded(
                               child: Column(
                                 crossAxisAlignment: CrossAxisAlignment.start,
                                 children: [
                                   const Text(
                                     'Location Set',
                                     style: TextStyle(
                                       fontSize: 14,
                                       fontWeight: FontWeight.bold,
                                       color: Colors.green,
                                     ),
                                   ),
                                   const SizedBox(height: 2),
                                   Text(
                                     _selectedAddress!,
                                     style: const TextStyle(fontSize: 13),
                                   ),
                                 ],
                               ),
                             ),
                           ],
                         ),
                       ),
                       const SizedBox(height: 12),
                     ],
                    
                                         SizedBox(
                       width: double.infinity,
                       child: ElevatedButton.icon(
                         onPressed: _isLoadingUserData ? null : _selectLocation,
                         icon: _isLoadingUserData 
                           ? const SizedBox(
                               width: 20,
                               height: 20,
                               child: CircularProgressIndicator(strokeWidth: 2, valueColor: AlwaysStoppedAnimation<Color>(Colors.white)),
                             )
                           : const Icon(Icons.map),
                         label: _isLoadingUserData 
                           ? const Text('Loading...')
                           : Text(_selectedLocation == null ? 'Select Location' : 'Change Location'),
                         style: ElevatedButton.styleFrom(
                           backgroundColor: Colors.blue.shade600,
                           foregroundColor: Colors.white,
                           padding: const EdgeInsets.symmetric(vertical: 12),
                         ),
                       ),
                     ),
                  ],
                ),
              ),
              
              const SizedBox(height: 24),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Total', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                  Text('₱${widget.totalPrice.toStringAsFixed(2)}', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.green.shade700)),
                ],
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _isSubmitting ? null : _confirmBeforeProceed,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.green.shade600,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  child: _isSubmitting
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, valueColor: AlwaysStoppedAnimation<Color>(Colors.white)))
                      : const Text('Proceed'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _createOrderNotifications(String cartId, List<Map<String, dynamic>> items) async {
    try {
      // Get unique provider IDs from items
      final Set<String> providerIds = {};
      
      for (final item in items) {
        final listingId = item['listing_id'] as String?;
        if (listingId != null) {
          final listingDoc = await FirebaseFirestore.instance
              .collection('listings')
              .doc(listingId)
              .get();
          
          if (listingDoc.exists) {
            final providerId = listingDoc.data()?['provider_id'] as String?;
            if (providerId != null) {
              providerIds.add(providerId);
            }
          }
        }
      }

      // Create notifications for each provider
      for (final providerId in providerIds) {
        await FirebaseFirestore.instance.collection('notifications').add({
          'provider_id': providerId,
          'type': 'new_order',
          'cart_id': cartId,
          'created_at': FieldValue.serverTimestamp(),
          'read': false,
          'message': 'You have a new order! Customer has placed an order and is ready for pickup.',
        });
      }
    } catch (e) {
      debugPrint('Failed to create order notifications: $e');
    }
  }
}


