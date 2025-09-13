import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'dart:async';
import '../../services/messaging_service.dart';
import '../../features/auth/auth_service.dart';
import '../messaging/chat_screen.dart';

class NotificationsScreen extends StatelessWidget {
  final String providerId;
  const NotificationsScreen({super.key, required this.providerId});

  Stream<List<QueryDocumentSnapshot<Map<String, dynamic>>>> _getCombinedNotificationsStream() {
    return FirebaseFirestore.instance
        .collection('notifications')
        .where('receiverId', isEqualTo: providerId)
        .where('type', whereIn: ['message', 'new_order', 'pending_order', 'order_claimed'])
        .orderBy('created_at', descending: true)
        .limit(50)
        .snapshots()
        .map((snapshot) => snapshot.docs);
  }

  @override
  Widget build(BuildContext context) {

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          TextButton(
            onPressed: () async {
              final query = FirebaseFirestore.instance
                  .collection('notifications')
                  .where('receiverId', isEqualTo: providerId)
                  .where('type', whereIn: ['message', 'new_order', 'pending_order', 'order_claimed']);
              
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
      body: StreamBuilder<List<QueryDocumentSnapshot<Map<String, dynamic>>>>(
        stream: _getCombinedNotificationsStream(),
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
                  Text('Error loading notifications: ${snapshot.error}'),
                ],
              ),
            );
          }
          
          if (!snapshot.hasData || snapshot.data!.isEmpty) {
            return const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.notifications_none, size: 48, color: Colors.grey),
                  SizedBox(height: 16),
                  Text('No notifications yet'),
                  SizedBox(height: 8),
                  Text('You\'ll see notifications here when customers order or message you'),
                ],
              ),
            );
          }
          
          final docs = snapshot.data!;
          
          return ListView.separated(
            padding: const EdgeInsets.all(12),
            separatorBuilder: (_, __) => const SizedBox(height: 8),
            itemCount: docs.length,
            itemBuilder: (context, index) {
              final data = docs[index].data();
              final read = data['read'] == true;
              final createdAt = data['created_at'] as Timestamp?;
              final dateTime = createdAt?.toDate();
              
              return Card(
                margin: const EdgeInsets.symmetric(vertical: 4),
                elevation: read ? 1 : 3,
                child: ListTile(
                  tileColor: read ? Colors.white : Colors.yellow.shade50,
                  leading: Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: read ? Colors.grey.shade200 : _getNotificationColor(data['type']).withOpacity(0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Icon(
                      _getNotificationIcon(data['type']),
                      color: read ? Colors.grey.shade600 : _getNotificationColor(data['type']),
                      size: 20,
                    ),
                  ),
                  title: Text(
                    data['message'] ?? 'Notification',
                    style: TextStyle(
                      fontWeight: read ? FontWeight.normal : FontWeight.bold,
                      color: read ? Colors.grey.shade700 : Colors.black87,
                    ),
                  ),
                  subtitle: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SizedBox(height: 4),
                      if (data['type'] == 'message' && data['conversationId'] != null)
                        Text(
                          'Tap to open conversation',
                          style: TextStyle(
                            color: Colors.blue.shade600,
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                          ),
                        )
                      else
                        Text(
                          'Order: ${data['cart_id'] ?? '-'}',
                          style: TextStyle(
                            color: Colors.grey.shade600,
                            fontSize: 12,
                          ),
                        ),
                      const SizedBox(height: 2),
                      Text(
                        _formatDateTime(dateTime),
                        style: TextStyle(
                          color: Colors.grey.shade500,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                  trailing: read 
                    ? Icon(Icons.check_circle, color: Colors.green.shade600, size: 20)
                    : TextButton(
                        onPressed: () => docs[index].reference.update({'read': true}),
                        child: const Text('Mark read'),
                      ),
                  onTap: () {
                    if (data['type'] == 'message' && data['conversationId'] != null) {
                      _openConversation(context, data['conversationId']);
                    } else if (data['type'] == 'new_order' || data['type'] == 'pending_order' || data['type'] == 'order_claimed') {
                      // Navigate to orders management screen
                      Navigator.of(context).pushNamed('/orders');
                    }
                  },
                ),
              );
            },
          );
        },
      ),
    );
  }

  Future<void> _openConversation(BuildContext context, String conversationId) async {
    try {
      final messagingService = context.read<MessagingService>();
      final participants = await messagingService.getConversationParticipants(conversationId);
      
      if (participants.isNotEmpty) {
        // Find the other participant (not the current user)
        final currentUserId = context.read<AuthService>().currentUser?.uid;
        final otherUser = participants.firstWhere(
          (user) => user.id != currentUserId,
          orElse: () => participants.first,
        );
        
        if (context.mounted) {
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (context) => ChatScreen(
                conversationId: conversationId,
                otherUser: otherUser,
              ),
            ),
          );
        }
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error opening conversation: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  IconData _getNotificationIcon(String? type) {
    switch (type) {
      case 'new_order':
        return Icons.shopping_cart;
      case 'pending_order':
        return Icons.restaurant;
      case 'order_claimed':
        return Icons.check_circle;
      case 'message':
        return Icons.message;
      default:
        return Icons.notifications;
    }
  }

  Color _getNotificationColor(String? type) {
    switch (type) {
      case 'new_order':
        return Colors.green.shade700;
      case 'pending_order':
        return Colors.orange.shade700;
      case 'order_claimed':
        return Colors.blue.shade700;
      case 'message':
        return Colors.purple.shade700;
      default:
        return Colors.grey.shade700;
    }
  }

  String _formatDateTime(DateTime? dateTime) {
    if (dateTime == null) return 'Unknown time';
    
    final now = DateTime.now();
    final difference = now.difference(dateTime);
    
    if (difference.inDays > 0) {
      return DateFormat('MMM dd, yyyy • hh:mm a').format(dateTime);
    } else if (difference.inHours > 0) {
      return '${difference.inHours}h ago';
    } else if (difference.inMinutes > 0) {
      return '${difference.inMinutes}m ago';
    } else {
      return 'Just now';
    }
  }
}


