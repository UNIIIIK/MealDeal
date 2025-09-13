import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart' as ll;
import '../../services/location_service.dart';
import '../../services/routing_service.dart'; // Added import for RoutingService

class PickupRouteScreen extends StatefulWidget {
  final String cartId;
  final Map<String, dynamic> provider;
  const PickupRouteScreen({super.key, required this.cartId, required this.provider});

  @override
  State<PickupRouteScreen> createState() => _PickupRouteScreenState();
}

class _PickupRouteScreenState extends State<PickupRouteScreen> {
  ll.LatLng? _consumerLocation;
  ll.LatLng? _providerLocation;
  List<ll.LatLng> _routePoints = [];
  bool _loadingRoute = false;
  String? _customerAddress;
  Map<String, dynamic>? _routeInfo; // Added _routeInfo
  Stream<DocumentSnapshot<Map<String, dynamic>>>? _cartStream;

  @override
  void initState() {
    super.initState();
    _initializeLocations();
    _listenForProviderPickup();
  }

  Future<void> _initializeLocations() async {
    // Get provider location from the provider data
    final providerLoc = widget.provider['location'];
    if (providerLoc != null) {
      _providerLocation = ll.LatLng(
        (providerLoc['lat'] ?? 0).toDouble(),
        (providerLoc['lng'] ?? 0).toDouble(),
      );
    }

    // Get consumer location from checkout info
    try {
      final cartDoc = await FirebaseFirestore.instance
          .collection('cart')
          .doc(widget.cartId)
          .get();
      
      if (cartDoc.exists) {
        final cartData = cartDoc.data() ?? {};
        final checkoutInfo = cartData['checkout_info'] as Map<String, dynamic>?;
        
        if (checkoutInfo != null) {
          final customerLocation = checkoutInfo['customer_location'] as Map<String, dynamic>?;
          if (customerLocation != null) {
            _consumerLocation = ll.LatLng(
              (customerLocation['lat'] ?? 0).toDouble(),
              (customerLocation['lng'] ?? 0).toDouble(),
            );
          }
          _customerAddress = checkoutInfo['customer_address'] as String?;
        }
      }
    } catch (e) {
      debugPrint('Error getting customer location: $e');
    }

    // If no customer location found, use current location as fallback
    if (_consumerLocation == null) {
      _consumerLocation = await LocationService.getCurrentLocation();
      
      // If current location is not available or outside Bogo City, use a default location
      if (_consumerLocation == null || !LocationService.isInBogoCity(_consumerLocation!)) {
        _consumerLocation = LocationService.getBogoCenter();
      }
    }

    if (_consumerLocation != null && _providerLocation != null) {
      await _calculateRoute();
    }
  }

  void _listenForProviderPickup() {
    _cartStream = FirebaseFirestore.instance
        .collection('cart')
        .doc(widget.cartId)
        .snapshots();

    _cartStream!.listen((doc) async {
      final data = doc.data() ?? {};
      final status = data['status'] as String?;
      if (!mounted) return;
      if (status == 'claimed') {
        // Navigate to confirmation screen when provider marks as picked up
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => _PickupConfirmationScreen(cartId: widget.cartId),
          ),
        );
      }
    });
  }

  Future<void> _calculateRoute() async {
    setState(() => _loadingRoute = true);
    
    try {
      // Get accurate route using OSRM API
      final route = await RoutingService.getWalkingRoute(_consumerLocation!, _providerLocation!);
      
      // Get route information (distance and duration)
      final routeInfo = await RoutingService.getRouteInfo(_consumerLocation!, _providerLocation!, profile: 'walking');
      
      setState(() {
        _routePoints = route;
        _routeInfo = routeInfo;
        _loadingRoute = false;
      });
      
      setState(() => _loadingRoute = false);
    } catch (e) {
      setState(() => _loadingRoute = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to calculate route: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  // Consumer no longer marks as claimed; provider does this.



  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pickup Route'),
        backgroundColor: Colors.green.shade600,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: Column(
        children: [
          // Route Info Panel
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white,
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.1),
                  blurRadius: 4,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.route, color: Colors.blue.shade600),
                    const SizedBox(width: 8),
                    const Text(
                      'Pickup Route',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                
                // Provider Info
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.blue.shade50,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.blue.shade200),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.store, color: Colors.blue.shade600),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              widget.provider['business_name'] ?? 'Provider',
                              style: const TextStyle(fontWeight: FontWeight.bold),
                            ),
                            if (widget.provider['address'] != null)
                              Text(
                                widget.provider['address'],
                                style: TextStyle(
                                  fontSize: 12,
                                  color: Colors.grey.shade600,
                                ),
                              ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                
                const SizedBox(height: 8),
                
                // Consumer Location Info
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.green.shade50,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.green.shade200),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.location_on, color: Colors.green.shade600),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Your Location',
                              style: TextStyle(fontWeight: FontWeight.bold),
                            ),
                            Text(
                              _customerAddress ?? 'Bogo City, Cebu, Philippines',
                              style: TextStyle(
                                fontSize: 12,
                                color: Colors.grey.shade600,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                
                if (_consumerLocation != null && _providerLocation != null) ...[
                  const SizedBox(height: 12),
                  
                  // Route Information
                  if (_routeInfo != null) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.orange.shade50,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: Colors.orange.shade200),
                      ),
                      child: Row(
                        children: [
                          Icon(Icons.route, color: Colors.orange.shade600),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Route Information',
                                  style: TextStyle(fontWeight: FontWeight.bold),
                                ),
                                const SizedBox(height: 4),
                                Row(
                                  children: [
                                    Icon(Icons.straighten, size: 16, color: Colors.grey.shade600),
                                    const SizedBox(width: 4),
                                    Text(
                                      'Distance: ${RoutingService.formatDistance(_routeInfo!['distance'])}',
                                      style: TextStyle(
                                        fontSize: 12,
                                        color: Colors.grey.shade600,
                                      ),
                                    ),
                                    const SizedBox(width: 16),
                                    Icon(Icons.access_time, size: 16, color: Colors.grey.shade600),
                                    const SizedBox(width: 4),
                                    Text(
                                      'Duration: ${RoutingService.formatDuration(_routeInfo!['duration'])}',
                                      style: TextStyle(
                                        fontSize: 12,
                                        color: Colors.grey.shade600,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ],
            ),
          ),

          // Map
          Expanded(
            child: _buildMap(),
          ),
          
          // No consumer claim action here per requirements
          const SizedBox(height: 12),
        ],
      ),
    );
  }

  Widget _buildMap() {
    if (_loadingRoute) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CircularProgressIndicator(),
            SizedBox(height: 16),
            Text('Loading route...'),
          ],
        ),
      );
    }

    if (_consumerLocation == null || _providerLocation == null) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.location_off, size: 64, color: Colors.grey),
            SizedBox(height: 16),
            Text('Location data not available'),
          ],
        ),
      );
    }

    // Calculate center point for the map
    final centerLat = (_consumerLocation!.latitude + _providerLocation!.latitude) / 2;
    final centerLng = (_consumerLocation!.longitude + _providerLocation!.longitude) / 2;
    final center = ll.LatLng(centerLat, centerLng);

    return FlutterMap(
      options: MapOptions(
        initialCenter: center,
        initialZoom: 14.0,
        minZoom: 13.0, // Constrain to Bogo City area
        maxZoom: 18.0,
      ),
      children: [
        TileLayer(
          urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
          userAgentPackageName: 'com.example.mealdeal',
          tileProvider: NetworkTileProvider(),
        ),
        
        // Route polyline
        if (_routePoints.isNotEmpty)
          PolylineLayer(
            polylines: [
              Polyline(
                points: _routePoints,
                strokeWidth: 4.0,
                color: Colors.blue,
              ),
            ],
          ),
        
        // Markers
        MarkerLayer(
          markers: [
            // Consumer location marker
            Marker(
              point: _consumerLocation!,
              width: 40,
              height: 40,
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.green,
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: 2),
                ),
                child: const Icon(
                  Icons.person,
                  color: Colors.white,
                  size: 20,
                ),
              ),
            ),
            
            // Provider location marker
            Marker(
              point: _providerLocation!,
              width: 40,
              height: 40,
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.red,
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: 2),
                ),
                child: const Icon(
                  Icons.store,
                  color: Colors.white,
                  size: 20,
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _PickupConfirmationScreen extends StatelessWidget {
  const _PickupConfirmationScreen({required this.cartId});
  final String cartId;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pickup Confirmed'),
        backgroundColor: Colors.green.shade600,
        foregroundColor: Colors.white,
      ),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.green.shade50,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.green.shade200),
              ),
              child: Row(
                children: [
                  Icon(Icons.check_circle, color: Colors.green.shade700, size: 28),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Text(
                      'Food is picked up — enjoy!',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            _RatingAndIssueForm(cartId: cartId),
            const Spacer(),
            ElevatedButton(
              onPressed: () => Navigator.of(context).pop(),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.green.shade600,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
              child: const Text('Done'),
            ),
          ],
        ),
      ),
    );
  }
}

class _RatingAndIssueForm extends StatefulWidget {
  const _RatingAndIssueForm({required this.cartId});
  final String cartId;

  @override
  State<_RatingAndIssueForm> createState() => _RatingAndIssueFormState();
}

class _RatingAndIssueFormState extends State<_RatingAndIssueForm> {
  int _rating = 0;
  final TextEditingController _comment = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);
    try {
      await FirebaseFirestore.instance.collection('cart').doc(widget.cartId).update({
        'rating': _rating,
        'rating_comment': _comment.text.trim(),
        'rated_at': FieldValue.serverTimestamp(),
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Thanks for your feedback!'), backgroundColor: Colors.green),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to submit: $e'), backgroundColor: Colors.red),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _reportIssue() async {
    final TextEditingController issueCtrl = TextEditingController();
    String? selectedCategory;
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (context) {
        return Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom + 16,
            left: 16,
            right: 16,
            top: 16,
          ),
          child: StatefulBuilder(
            builder: (context, setInner) => Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Report an issue', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  children: [
                    'Missing items', 'Quality issue', 'Wrong order', 'Other',
                  ].map((c) => ChoiceChip(
                    label: Text(c),
                    selected: selectedCategory == c,
                    onSelected: (sel) => setInner(() => selectedCategory = sel ? c : null),
                  )).toList(),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: issueCtrl,
                  decoration: const InputDecoration(labelText: 'Describe the issue'),
                  maxLines: 3,
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () async {
                      await FirebaseFirestore.instance.collection('issues').add({
                        'cart_id': widget.cartId,
                        'category': selectedCategory,
                        'description': issueCtrl.text.trim(),
                        'created_at': FieldValue.serverTimestamp(),
                      });
                      if (context.mounted) Navigator.of(context).pop();
                    },
                    child: const Text('Send'),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Report submitted.'), backgroundColor: Colors.green),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text("How's your food?", style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        Row(
          children: List.generate(5, (i) => IconButton(
            onPressed: () => setState(() => _rating = i + 1),
            icon: Icon(i < _rating ? Icons.star : Icons.star_border, color: Colors.amber),
          )),
        ),
        TextField(
          controller: _comment,
          decoration: const InputDecoration(labelText: 'Leave a comment (optional)'),
          maxLines: 3,
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: ElevatedButton(
                onPressed: _submitting ? null : _submit,
                child: _submitting ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Submit rating'),
              ),
            ),
            const SizedBox(width: 8),
            TextButton(
              onPressed: _reportIssue,
              child: const Text('Report an issue'),
            ),
          ],
        ),
      ],
    );
  }
}


