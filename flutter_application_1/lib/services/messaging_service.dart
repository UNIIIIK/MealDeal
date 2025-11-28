// optimized: services/messaging_service.dart
import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import '../models/message.dart';

class MessagingService extends ChangeNotifier {
  final FirebaseFirestore _firestore = FirebaseFirestore.instance;
  final FirebaseAuth _auth = FirebaseAuth.instance;

  String? get currentUserId => _auth.currentUser?.uid;

  // In-memory caches to avoid repeated reads
  final Map<String, ChatUser> _userCache = {};
  final Map<String, Conversation> _conversationCache = {};

  // Pagination default
  static const int pageSize = 50;

  // -------------------------
  // Conversations stream (ordered server-side)
  // -------------------------
  Stream<List<Conversation>> getConversations() {
    final uid = currentUserId;
    if (uid == null) return Stream.value([]);

    // Order by lastMessageTime on server to avoid client sorting work
    return _firestore
        .collection('conversations')
        .where('participants', arrayContains: uid)
        .orderBy('lastMessageTime', descending: true)
        .snapshots()
        .map((snap) {
      final conversations = snap.docs.map((doc) {
        try {
          final conv = Conversation.fromMap(doc.data(), doc.id);
          _conversationCache[doc.id] = conv; // cache
          return conv;
        } catch (e) {
          debugPrint('Error parsing conversation ${doc.id}: $e');
          return null;
        }
      }).where((c) => c != null).cast<Conversation>().toList();
      return conversations;
    }).handleError((err) {
      debugPrint('getConversations stream error: $err');
      return <Conversation>[];
    });
  }

  // -------------------------
  // Messages stream (latest page + pagination support)
  // - returns newest messages first (descending) for efficient queries.
  // - client can reverse to display chronological order.
  // -------------------------
  Stream<List<Message>> getLatestMessages(String conversationId, {int limit = pageSize}) {
    return _firestore
        .collection('conversations')
        .doc(conversationId)
        .collection('messages')
        .orderBy('timestamp', descending: true)
        .limit(limit)
        .snapshots()
        .map((snap) => snap.docs.map((d) => Message.fromMap(d.data(), d.id)).toList())
        .handleError((e) {
          debugPrint('getLatestMessages error: $e');
          return <Message>[];
        });
  }

  // Fetch older messages before a given Timestamp for pagination (one-time fetch)
  Future<List<Message>> fetchOlderMessages(String conversationId, DateTime before,
      {int limit = pageSize}) async {
    try {
      final snap = await _firestore
          .collection('conversations')
          .doc(conversationId)
          .collection('messages')
          .orderBy('timestamp', descending: true)
          .startAfter([Timestamp.fromDate(before)])
          .limit(limit)
          .get();

      return snap.docs.map((d) => Message.fromMap(d.data(), d.id)).toList();
    } catch (e) {
      debugPrint('fetchOlderMessages error: $e');
      return [];
    }
  }

  // -------------------------
  // Send message (atomic-ish): write message, update conversation, create notification
  // -------------------------
  Future<void> sendMessage({
    required String conversationId,
    required String receiverId,
    required String content,
    MessageType type = MessageType.text,
    String? listingId,
    Map<String, dynamic>? metadata,
  }) async {
    final uid = currentUserId;
    if (uid == null) throw Exception('User not authenticated');

    final messageRef = _firestore
        .collection('conversations')
        .doc(conversationId)
        .collection('messages')
        .doc();

    final now = DateTime.now();

    final message = Message(
      id: messageRef.id,
      conversationId: conversationId,
      senderId: uid,
      receiverId: receiverId,
      content: content,
      type: type,
      timestamp: now,
      listingId: listingId,
      metadata: metadata,
    );

    final batch = _firestore.batch();
    batch.set(messageRef, message.toMap());
    batch.update(_firestore.collection('conversations').doc(conversationId), {
      'lastMessageId': message.id,
      'lastMessageContent': content,
      'lastMessageTime': Timestamp.fromDate(now),
      'lastMessageSenderId': uid,
      'unreadCount': FieldValue.increment(1),
    });

    try {
      await batch.commit();
      // Fire-and-forget notification (don't block UI on network flakiness)
      unawaited(_createMessageNotification(receiverId, content, conversationId));
    } catch (e) {
      debugPrint('sendMessage failed: $e');
      rethrow;
    }
  }

  // -------------------------
  // Create or reuse conversation (minimize list reads)
  // -------------------------
  Future<String> createOrGetConversation({
    required String otherUserId,
    String? listingId,
  }) async {
    final uid = currentUserId;
    if (uid == null) throw Exception('User not authenticated');

    try {
      // Query conversations where both participants exist (server-side array-contains can't match both,
      // so filter locally but we keep server-side index for speed)
      final snap = await _firestore
          .collection('conversations')
          .where('participants', arrayContains: uid)
          .limit(50) // safety limit to avoid scanning thousands
          .get();

      for (final doc in snap.docs) {
        final data = doc.data();
        final participants = List<String>.from(data['participants'] ?? []);
        if (participants.contains(otherUserId) &&
            participants.length == 2 &&
            (listingId == null || data['listingId'] == listingId)) {
          return doc.id;
        }
      }

      final ref = _firestore.collection('conversations').doc();
      final conv = Conversation(
        id: ref.id,
        participants: [uid, otherUserId],
        listingId: listingId,
        lastMessageId: '',
        lastMessageContent: '',
        lastMessageTime: DateTime.now(),
        lastMessageSenderId: '',
        unreadCount: 0,
      );
      await ref.set(conv.toMap());
      _conversationCache[ref.id] = conv;
      return ref.id;
    } catch (e) {
      debugPrint('createOrGetConversation error: $e');
      rethrow;
    }
  }

  // -------------------------
  // Mark messages as read (batch, careful)
  // -------------------------
  Future<void> markMessagesAsRead(String conversationId) async {
    final uid = currentUserId;
    if (uid == null) return;

    try {
      // Only proceed if there are unread messages for this user
      final unreadQuery = await _firestore
          .collection('conversations')
          .doc(conversationId)
          .collection('messages')
          .where('receiverId', isEqualTo: uid)
          .where('isRead', isEqualTo: false)
          .limit(200) // safety cap
          .get();

      if (unreadQuery.docs.isEmpty) {
        // Optionally set unreadCount to 0 for conversation doc - safer to check first
        await _firestore.collection('conversations').doc(conversationId).update({'unreadCount': 0});
        return;
      }

      final batch = _firestore.batch();
      for (final doc in unreadQuery.docs) {
        batch.update(doc.reference, {'isRead': true});
      }
      batch.update(_firestore.collection('conversations').doc(conversationId), {'unreadCount': 0});
      await batch.commit();
    } catch (e) {
      debugPrint('markMessagesAsRead error: $e');
    }
  }

  // -------------------------
  // Participant info helpers with caching
  // -------------------------
  Future<ChatUser?> getUserInfo(String userId) async {
    if (userId.isEmpty) return null;
    final cached = _userCache[userId];
    if (cached != null) return cached;

    try {
      final doc = await _firestore.collection('users').doc(userId).get();
      if (!doc.exists) return null;
      final user = ChatUser.fromMap(doc.data()!, doc.id);
      _userCache[userId] = user;
      return user;
    } catch (e) {
      debugPrint('getUserInfo error: $e');
      return null;
    }
  }

  // Return participant ChatUsers for conversation using cache & parallel fetch
  Future<List<ChatUser>> getConversationParticipants(String conversationId) async {
    try {
      final doc = await _firestore.collection('conversations').doc(conversationId).get();
      if (!doc.exists) return [];

      final participants = List<String>.from(doc.data()?['participants'] ?? []);
      final futures = participants.map((id) => getUserInfo(id)).toList();
      final results = await Future.wait(futures);
      return results.whereType<ChatUser>().toList();
    } catch (e) {
      debugPrint('getConversationParticipants error: $e');
      return [];
    }
  }

  // -------------------------
  // Notifications (fire-and-forget)
  // -------------------------
  Future<void> _createMessageNotification(String receiverId, String content, String conversationId) async {
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
      debugPrint('createMessageNotification error: $e');
    }
  }

  // -------------------------
  // Unread count stream (keeps existing behavior)
  // -------------------------
  Stream<int> getUnreadMessageCount() {
    final uid = currentUserId;
    if (uid == null) return Stream.value(0);

    return _firestore
        .collection('conversations')
        .where('participants', arrayContains: uid)
        .snapshots()
        .map((snap) {
      int total = 0;
      for (final doc in snap.docs) {
        final data = doc.data();
        final lastSender = data['lastMessageSenderId'] ?? '';
        if (lastSender != uid) {
          total += (data['unreadCount'] as num?)?.toInt() ?? 0;
        }
      }
      return total;
    });
  }

  // -------------------------
  // Delete conversation (keeps same behavior)
  // -------------------------
  Future<void> deleteConversation(String conversationId) async {
    try {
      final messagesQuery = await _firestore
          .collection('conversations')
          .doc(conversationId)
          .collection('messages')
          .get();

      final batch = _firestore.batch();
      for (final doc in messagesQuery.docs) {
        batch.delete(doc.reference);
      }
      batch.delete(_firestore.collection('conversations').doc(conversationId));
      await batch.commit();

      _conversationCache.remove(conversationId);
    } catch (e) {
      debugPrint('deleteConversation error: $e');
      rethrow;
    }
  }

  // -------------------------
  // Send system message (wrapper for sendMessage with system type)
  // -------------------------
  Future<void> sendSystemMessage({
    required String conversationId,
    required String content,
    String? listingId,
  }) async {
    final uid = currentUserId;
    if (uid == null) throw Exception('User not authenticated');

    // Get conversation to find the other participant
    final convDoc = await _firestore.collection('conversations').doc(conversationId).get();
    if (!convDoc.exists) throw Exception('Conversation not found');

    final participants = List<String>.from(convDoc.data()?['participants'] ?? []);
    final receiverId = participants.firstWhere((id) => id != uid, orElse: () => '');

    if (receiverId.isEmpty) throw Exception('Could not determine receiver');

    await sendMessage(
      conversationId: conversationId,
      receiverId: receiverId,
      content: content,
      type: MessageType.system,
      listingId: listingId,
    );
  }

  // -------------------------
  // Search conversations (client-side filtering)
  // -------------------------
  Stream<List<Conversation>> searchConversations(String query) {
    final uid = currentUserId;
    if (uid == null) return Stream.value([]);

    if (query.isEmpty) {
      return getConversations();
    }

    final lowerQuery = query.toLowerCase();

    return _firestore
        .collection('conversations')
        .where('participants', arrayContains: uid)
        .orderBy('lastMessageTime', descending: true)
        .snapshots()
        .asyncMap((snap) async {
      final allConversations = <Conversation>[];
      
      for (final doc in snap.docs) {
        try {
          final conv = Conversation.fromMap(doc.data(), doc.id);
          _conversationCache[doc.id] = conv;
          
          // Check if last message content matches
          if (conv.lastMessageContent.toLowerCase().contains(lowerQuery)) {
            allConversations.add(conv);
            continue;
          }
          
          // Check if any participant name matches (need to fetch user info)
          bool matches = false;
          for (final participantId in conv.participants) {
            if (participantId == uid) continue;
            final user = await getUserInfo(participantId);
            if (user != null && user.name.toLowerCase().contains(lowerQuery)) {
              matches = true;
              break;
            }
          }
          
          if (matches) {
            allConversations.add(conv);
          }
        } catch (e) {
          debugPrint('Error parsing conversation ${doc.id}: $e');
        }
      }
      
      return allConversations;
    }).handleError((err) {
      debugPrint('searchConversations stream error: $err');
      return <Conversation>[];
    });
  }
}
