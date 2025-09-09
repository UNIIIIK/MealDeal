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
  bool _claiming = false;
  ll.LatLng? _consumerLocation;
  ll.LatLng? _providerLocation;
  List<ll.LatLng> _routePoints = [];
  bool _loadingRoute = false;
  String? _customerAddress;
  Map<String, dynamic>? _routeInfo; // Added _routeInfo

  @override
  void initState() {
    super.initState();
    _initializeLocations();
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

  Future<void> _markClaimed() async {
    setState(() => _claiming = true);
    
    try {
      // Update cart status to claimed
      await FirebaseFirestore.instance
          .collection('cart')
          .doc(widget.cartId)
          .update({
        'status': 'claimed',
        'claimed_at': FieldValue.serverTimestamp(),
      });

      // Write provider notification and placeholder review doc if present
      try {
        final cartDoc = await FirebaseFirestore.instance.collection('cart').doc(widget.cartId).get();
        final cartData = cartDoc.data();
        final items = (cartData?['items'] as List?)?.cast<Map<String, dynamic>>() ?? [];
        String? providerId;
        if (items.isNotEmpty) {
          final listingId = items.first['listing_id'];
          if (listingId != null) {
            final listingDoc = await FirebaseFirestore.instance.collection('listings').doc(listingId).get();
            providerId = (listingDoc.data() ?? {})['provider_id'];
          }
        }
        if (providerId != null) {
          await FirebaseFirestore.instance.collection('notifications').add({
            'provider_id': providerId,
            'type': 'order_claimed',
            'cart_id': widget.cartId,
            'created_at': FieldValue.serverTimestamp(),
            'read': false,
            'message': 'A customer marked the order as claimed.'
          });
        }
      } catch (e) {
        debugPrint('Failed to write notification: $e');
      }

      if (!mounted) return;
      
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Order claimed successfully!'),
          backgroundColor: Colors.green,
        ),
      );
      
      // Ask for review/report after claiming
      if (mounted) {
        final review = await showModalBottomSheet<Map<String, dynamic>?>(
          context: context,
          isScrollControlled: true,
          shape: const RoundedRectangleBorder(
            borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
          ),
          builder: (context) {
            int rating = 0; // Captured in submit payload
            final TextEditingController controller = TextEditingController();
            return Padding(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(context).viewInsets.bottom + 16,
                left: 16,
                right: 16,
                top: 12,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(Icons.reviews, color: Colors.green.shade700),
                      const SizedBox(width: 8),
                      const Text('How was the pickup?', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                    ],
                  ),
                  const SizedBox(height: 12),
                  StatefulBuilder(
                    builder: (context, setInnerState) {
                      return Row(
                        children: List.generate(5, (i) {
                          final filled = i < rating;
                          return IconButton(
                            onPressed: () { setInnerState(() { rating = i + 1; }); },
                            icon: Icon(filled ? Icons.star : Icons.star_border, color: Colors.amber.shade600),
                          );
                        }),
                      );
                    },
                  ),
                  TextField(
                    controller: controller,
                    decoration: const InputDecoration(
                      labelText: 'Leave a note or report (optional)',
                    ),
                    maxLines: 3,
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: () {
                        Navigator.of(context).pop({'rating': rating, 'comment': controller.text});
                      },
                      style: ElevatedButton.styleFrom(backgroundColor: Colors.green.shade600, foregroundColor: Colors.white),
                      child: const Text('Submit'),
                    ),
                  ),
                ],
              ),
            );
          },
        );
        try {
          if (review != null) {
            String? providerId;
            final cartDoc = await FirebaseFirestore.instance.collection('cart').doc(widget.cartId).get();
            final items = ((cartDoc.data() ?? {})['items'] as List?)?.cast<Map<String, dynamic>>() ?? [];
            if (items.isNotEmpty) {
              final listingId = items.first['listing_id'];
              if (listingId != null) {
                final listingDoc = await FirebaseFirestore.instance.collection('listings').doc(listingId).get();
                providerId = (listingDoc.data() ?? {})['provider_id'];
              }
            }
            await FirebaseFirestore.instance.collection('reviews').add({
              'cart_id': widget.cartId,
              'provider_id': providerId,
              'rating': review['rating'] ?? 0,
              'comment': review['comment'] ?? '',
              'created_at': FieldValue.serverTimestamp(),
            });
          }
        } catch (e) {
          debugPrint('Failed to save review: $e');
        }

        Navigator.of(context).pop(true);
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to claim order: $e'),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) setState(() => _claiming = false);
    }
  }



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
          
          // Action Button
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _claiming ? null : _markClaimed,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.green.shade600,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: _claiming
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                        ),
                      )
                    : const Text('Mark as Claimed'),
              ),
            ),
          ),
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


