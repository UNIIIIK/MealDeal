import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';

class NotificationsScreen extends StatelessWidget {
  final String providerId;
  const NotificationsScreen({super.key, required this.providerId});

  @override
  Widget build(BuildContext context) {
    final query = FirebaseFirestore.instance
        .collection('notifications')
        .where('provider_id', isEqualTo: providerId)
        .orderBy('created_at', descending: true);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          TextButton(
            onPressed: () async {
              final snap = await query.get();
              for (final d in snap.docs) {
                await d.reference.update({'read': true});
              }
              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('All notifications marked as read')),
                );
              }
            },
            child: const Text('Mark all read'),
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
            return const Center(child: Text('No notifications'));
          }
          final docs = snapshot.data!.docs;
          return ListView.separated(
            padding: const EdgeInsets.all(12),
            separatorBuilder: (_, __) => const SizedBox(height: 8),
            itemCount: docs.length,
            itemBuilder: (context, index) {
              final data = docs[index].data();
              final read = data['read'] == true;
              return ListTile(
                tileColor: read ? Colors.white : Colors.yellow.shade50,
                leading: Icon(
                  data['type'] == 'order_claimed' ? Icons.check_circle : Icons.notifications,
                  color: read ? Colors.grey.shade600 : Colors.orange.shade700,
                ),
                title: Text(data['message'] ?? 'Notification'),
                subtitle: Text('Order: ${data['cart_id'] ?? '-'}'),
                trailing: read ? null : TextButton(
                  onPressed: () => docs[index].reference.update({'read': true}),
                  child: const Text('Mark read'),
                ),
              );
            },
          );
        },
      ),
    );
  }
}


