// My Listings Screen for Providers
import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'dart:convert';
import '../auth/auth_service.dart';
import 'package:provider/provider.dart';
import 'location_management_screen.dart';

class MyListingsScreen extends StatefulWidget {
  const MyListingsScreen({super.key});

  @override
  State<MyListingsScreen> createState() => _MyListingsScreenState();
}

class _MyListingsScreenState extends State<MyListingsScreen> {
  Future<void> _reactivateListing(String listingId) async {
    try {
      await FirebaseFirestore.instance
          .collection('listings')
          .doc(listingId)
          .update({
        'status': 'active',
        'updated_at': FieldValue.serverTimestamp(),
      });

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Listing reactivated successfully'),
          backgroundColor: Colors.green,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error reactivating listing: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<void> _deleteListing(String listingId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete Listing'),
        content: const Text('Are you sure you want to delete this listing?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
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

  bool _isDataUrl(String? value) {
    if (value == null) return false;
    return value.startsWith('data:image/');
  }

  Widget _buildImageFromUrl(String? url, {BoxFit fit = BoxFit.cover}) {
    if (url == null || url.isEmpty) {
      return const SizedBox.shrink();
    }
    if (_isDataUrl(url)) {
      try {
        final base64Part = url.split(',').last;
        final bytes = base64Decode(base64Part);
        return Image.memory(bytes, fit: fit);
      } catch (e) {
        debugPrint('Error decoding base64 image: $e');
        return const Icon(Icons.broken_image, color: Colors.grey);
      }
    }
    return Image.network(
      url,
      fit: fit,
      errorBuilder: (context, error, stackTrace) => const Icon(Icons.broken_image, color: Colors.grey),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthService>(context, listen: false);
    final uid = auth.currentUser?.uid;

    if (uid == null) {
      return const Center(child: Text('Not signed in'));
    }

    final query = FirebaseFirestore.instance
        .collection('listings')
        .where('provider_id', isEqualTo: uid);

    return Scaffold(
      appBar: AppBar(
        title: const Text('My Listings'),
        actions: [
          PopupMenuButton(
            itemBuilder: (context) => [
              const PopupMenuItem(
                value: 'location',
                child: Row(
                  children: [
                    Icon(Icons.location_on),
                    SizedBox(width: 8),
                    Text('Manage Location'),
                  ],
                ),
              ),
            ],
            onSelected: (value) {
              if (value == 'location') {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (context) => const LocationManagementScreen(),
                  ),
                );
              }
            },
          ),
        ],
      ),
      body: StreamBuilder<QuerySnapshot<Map<String, dynamic>>>(
        stream: query.snapshots(),
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (!snapshot.hasData || snapshot.data!.docs.isEmpty) {
            return const Center(child: Text('No listings yet'));
          }
          final docs = snapshot.data!.docs
              .where((d) {
                final status = d.data()['status'] ?? 'active';
                return status == 'active' || status == 'sold_out';
              })
              .toList()
            ..sort((a, b) {
              final aCreated = a.data()['created_at'];
              final bCreated = b.data()['created_at'];
              if (aCreated is! Timestamp && bCreated is! Timestamp) return 0;
              if (aCreated is! Timestamp) return 1;
              if (bCreated is! Timestamp) return -1;
              return (bCreated).compareTo(aCreated);
            });
          return ListView.separated(
            padding: const EdgeInsets.all(12),
            itemCount: docs.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final data = docs[index].data();
              final images = (data['images'] as List?)?.cast<String>() ?? [];
              final image = images.isNotEmpty ? images.first : null;
              return Card(
                clipBehavior: Clip.antiAlias,
                child: Row(
                  children: [
                    Container(
                      width: 120,
                      height: 90,
                      color: Colors.grey.shade200,
                      child: image != null
                          ? _buildImageFromUrl(image)
                          : const Icon(Icons.image, color: Colors.grey),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              data['title'] ?? 'Untitled',
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Price: ₱${(data['discounted_price'] ?? 0).toStringAsFixed(2)}',
                              style: const TextStyle(color: Colors.green),
                            ),
                            const SizedBox(height: 2),
                            Text('Qty: ${data['quantity'] ?? 0}'),
                            if (data['status'] == 'sold_out') ...[
                              const SizedBox(height: 4),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(
                                  color: Colors.orange.shade100,
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: Text(
                                  'SOLD OUT',
                                  style: TextStyle(
                                    fontSize: 10,
                                    color: Colors.orange.shade800,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                            ],
                            const SizedBox(height: 8),
                            if (data['status'] == 'sold_out')
                              SizedBox(
                                width: double.infinity,
                                child: ElevatedButton(
                                  onPressed: () => _reactivateListing(docs[index].id),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: Colors.green.shade600,
                                    foregroundColor: Colors.white,
                                    padding: const EdgeInsets.symmetric(vertical: 8),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                  ),
                                  child: const Text('Reactivate', style: TextStyle(fontSize: 12)),
                                ),
                              )
                            else
                              SizedBox(
                                width: double.infinity,
                                child: ElevatedButton(
                                  onPressed: () => _deleteListing(docs[index].id),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: Colors.red.shade600,
                                    foregroundColor: Colors.white,
                                    padding: const EdgeInsets.symmetric(vertical: 8),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                  ),
                                  child: const Text('Delete', style: TextStyle(fontSize: 12)),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }
}


