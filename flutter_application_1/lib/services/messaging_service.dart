import 'package:flutter/foundation.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import '../models/message.dart';

class MessagingService extends ChangeNotifier {
  final FirebaseFirestore _firestore = FirebaseFirestore.instance;
  final FirebaseAuth _auth = FirebaseAuth.instance;

  // Get current user ID
  String? get currentUserId => _auth.currentUser?.uid;

  // Get conversations for current user
  Stream<List<Conversation>> getConversations() {
    if (currentUserId == null) return Stream.value([]);

    return _firestore
        .collection('conversations')
        .where('participants', arrayContains: currentUserId)
        .snapshots()
        .map((snapshot) {
      final conversations = snapshot.docs.map((doc) {
        try {
          return Conversation.fromMap(doc.data(), doc.id);
        } catch (e) {
          debugPrint('Error parsing conversation ${doc.id}: $e');
          return null;
        }
      }).where((conversation) => conversation != null).cast<Conversation>().toList();
      
      // Sort by lastMessageTime descending
      conversations.sort((a, b) => b.lastMessageTime.compareTo(a.lastMessageTime));
      
      return conversations;
    }).handleError((error) {
      debugPrint('Error in getConversations stream: $error');
      return <Conversation>[];
    });
  }

  // Get messages for a specific conversation
  Stream<List<Message>> getMessages(String conversationId) {
    return _firestore
        .collection('conversations')
        .doc(conversationId)
        .collection('messages')
        .orderBy('timestamp', descending: false)
        .snapshots()
        .map((snapshot) {
      return snapshot.docs.map((doc) {
        return Message.fromMap(doc.data(), doc.id);
      }).toList();
    });
  }

  // Send a message
  Future<void> sendMessage({
    required String conversationId,
    required String receiverId,
    required String content,
    MessageType type = MessageType.text,
    String? listingId,
    Map<String, dynamic>? metadata,
  }) async {
    if (currentUserId == null) throw Exception('User not authenticated');

    try {
      final messageRef = _firestore
          .collection('conversations')
          .doc(conversationId)
          .collection('messages')
          .doc();

      final message = Message(
        id: messageRef.id,
        conversationId: conversationId,
        senderId: currentUserId!,
        receiverId: receiverId,
        content: content,
        type: type,
        timestamp: DateTime.now(),
        listingId: listingId,
        metadata: metadata,
      );

      // Add message to conversation
      await messageRef.set(message.toMap());

      // Update conversation with last message info
      await _firestore.collection('conversations').doc(conversationId).update({
        'lastMessageId': message.id,
        'lastMessageContent': content,
        'lastMessageTime': Timestamp.fromDate(message.timestamp),
        'lastMessageSenderId': currentUserId!,
        'unreadCount': FieldValue.increment(1),
      });

      // Create notification for receiver
      await _createMessageNotification(receiverId, content, conversationId);

    } catch (e) {
      debugPrint('Error sending message: $e');
      rethrow;
    }
  }

  // Create or get existing conversation between two users
  Future<String> createOrGetConversation({
    required String otherUserId,
    String? listingId,
  }) async {
    if (currentUserId == null) throw Exception('User not authenticated');

    try {
      // Check if conversation already exists
      final existingConversation = await _firestore
          .collection('conversations')
          .where('participants', arrayContains: currentUserId)
          .get();

      for (var doc in existingConversation.docs) {
        final data = doc.data();
        final participants = List<String>.from(data['participants'] ?? []);
        
        if (participants.contains(otherUserId) && 
            participants.length == 2 &&
            (listingId == null || data['listingId'] == listingId)) {
          return doc.id;
        }
      }

      // Create new conversation
      final conversationRef = _firestore.collection('conversations').doc();
      
      final conversation = Conversation(
        id: conversationRef.id,
        participants: [currentUserId!, otherUserId],
        listingId: listingId,
        lastMessageId: '',
        lastMessageContent: '',
        lastMessageTime: DateTime.now(),
        lastMessageSenderId: '',
        unreadCount: 0,
      );

      await conversationRef.set(conversation.toMap());
      return conversationRef.id;

    } catch (e) {
      debugPrint('Error creating conversation: $e');
      rethrow;
    }
  }

  // Mark messages as read
  Future<void> markMessagesAsRead(String conversationId) async {
    if (currentUserId == null) return;

    try {
      // Reset unread count for current user
      await _firestore.collection('conversations').doc(conversationId).update({
        'unreadCount': 0,
      });

      // Mark individual messages as read
      final messagesQuery = await _firestore
          .collection('conversations')
          .doc(conversationId)
          .collection('messages')
          .where('receiverId', isEqualTo: currentUserId)
          .where('isRead', isEqualTo: false)
          .get();

      final batch = _firestore.batch();
      for (var doc in messagesQuery.docs) {
        batch.update(doc.reference, {'isRead': true});
      }
      await batch.commit();

    } catch (e) {
      debugPrint('Error marking messages as read: $e');
    }
  }

  // Get user information for chat
  Future<ChatUser?> getUserInfo(String userId) async {
    try {
      final userDoc = await _firestore.collection('users').doc(userId).get();
      if (userDoc.exists) {
        return ChatUser.fromMap(userDoc.data()!, userDoc.id);
      }
      return null;
    } catch (e) {
      debugPrint('Error getting user info: $e');
      return null;
    }
  }

  // Get conversation participants info
  Future<List<ChatUser>> getConversationParticipants(String conversationId) async {
    try {
      final conversationDoc = await _firestore
          .collection('conversations')
          .doc(conversationId)
          .get();

      if (!conversationDoc.exists) return [];

      final participants = List<String>.from(
          conversationDoc.data()?['participants'] ?? []);

      final List<ChatUser> users = [];
      for (String userId in participants) {
        final userInfo = await getUserInfo(userId);
        if (userInfo != null) {
          users.add(userInfo);
        }
      }

      return users;
    } catch (e) {
      debugPrint('Error getting conversation participants: $e');
      return [];
    }
  }

  // Create message notification
  Future<void> _createMessageNotification(
      String receiverId, String content, String conversationId) async {
    try {
      await _firestore.collection('notifications').add({
        'receiverId': receiverId,
        'type': 'message',
        'conversationId': conversationId,
        'message': content.length > 50 ? '${content.substring(0, 50)}...' : content,
        'created_at': FieldValue.serverTimestamp(),
        'read': false,
      });
    } catch (e) {
      debugPrint('Error creating message notification: $e');
    }
  }

  // Get unread message count for current user
  Stream<int> getUnreadMessageCount() {
    if (currentUserId == null) return Stream.value(0);

    return _firestore
        .collection('conversations')
        .where('participants', arrayContains: currentUserId)
        .snapshots()
        .map((snapshot) {
      int totalUnread = 0;
      for (var doc in snapshot.docs) {
        final data = doc.data();
        final lastMessageSenderId = data['lastMessageSenderId'] ?? '';
        
        // Only count as unread if the last message was not sent by current user
        if (lastMessageSenderId != currentUserId) {
          totalUnread += (data['unreadCount'] as num?)?.toInt() ?? 0;
        }
      }
      return totalUnread;
    });
  }

  // Delete conversation
  Future<void> deleteConversation(String conversationId) async {
    try {
      // Delete all messages in the conversation
      final messagesQuery = await _firestore
          .collection('conversations')
          .doc(conversationId)
          .collection('messages')
          .get();

      final batch = _firestore.batch();
      for (var doc in messagesQuery.docs) {
        batch.delete(doc.reference);
      }
      
      // Delete the conversation itself
      batch.delete(_firestore.collection('conversations').doc(conversationId));
      
      await batch.commit();
    } catch (e) {
      debugPrint('Error deleting conversation: $e');
      rethrow;
    }
  }

  // Send system message (e.g., "User joined conversation")
  Future<void> sendSystemMessage({
    required String conversationId,
    required String content,
    String? listingId,
  }) async {
    if (currentUserId == null) return;

    try {
      final messageRef = _firestore
          .collection('conversations')
          .doc(conversationId)
          .collection('messages')
          .doc();

      final message = Message(
        id: messageRef.id,
        conversationId: conversationId,
        senderId: 'system',
        receiverId: '',
        content: content,
        type: MessageType.system,
        timestamp: DateTime.now(),
        listingId: listingId,
      );

      await messageRef.set(message.toMap());

      // Update conversation with system message info
      await _firestore.collection('conversations').doc(conversationId).update({
        'lastMessageId': message.id,
        'lastMessageContent': content,
        'lastMessageTime': Timestamp.fromDate(message.timestamp),
        'lastMessageSenderId': 'system',
      });

    } catch (e) {
      debugPrint('Error sending system message: $e');
    }
  }

  // Search conversations
  Stream<List<Conversation>> searchConversations(String query) {
    if (currentUserId == null || query.isEmpty) return getConversations();

    return _firestore
        .collection('conversations')
        .where('participants', arrayContains: currentUserId)
        .snapshots()
        .map((snapshot) {
      final conversations = snapshot.docs.map((doc) {
        try {
          return Conversation.fromMap(doc.data(), doc.id);
        } catch (e) {
          debugPrint('Error parsing conversation ${doc.id}: $e');
          return null;
        }
      }).where((conversation) => conversation != null).cast<Conversation>().toList();
      
      // Filter by search query
      final filteredConversations = conversations.where((conversation) {
        return conversation.lastMessageContent.toLowerCase().contains(query.toLowerCase());
      }).toList();
      
      // Sort by lastMessageTime descending
      filteredConversations.sort((a, b) => b.lastMessageTime.compareTo(a.lastMessageTime));
      
      return filteredConversations;
    }).handleError((error) {
      debugPrint('Error in searchConversations stream: $error');
      return <Conversation>[];
    });
  }
}
