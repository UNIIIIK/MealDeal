import 'package:cloud_firestore/cloud_firestore.dart';

class Message {
  final String id;
  final String conversationId;
  final String senderId;
  final String receiverId;
  final String content;
  final MessageType type;
  final DateTime timestamp;
  final bool isRead;
  final String? listingId; // Optional: for messages related to specific listings
  final Map<String, dynamic>? metadata; // For additional data like image URLs, etc.

  Message({
    required this.id,
    required this.conversationId,
    required this.senderId,
    required this.receiverId,
    required this.content,
    required this.type,
    required this.timestamp,
    this.isRead = false,
    this.listingId,
    this.metadata,
  });

  factory Message.fromMap(Map<String, dynamic> data, String id) {
    try {
      return Message(
        id: id,
        conversationId: data['conversationId'] ?? '',
        senderId: data['senderId'] ?? '',
        receiverId: data['receiverId'] ?? '',
        content: data['content'] ?? '',
        type: MessageType.values.firstWhere(
          (e) => e.toString() == 'MessageType.${data['type']}',
          orElse: () => MessageType.text,
        ),
        timestamp: (data['timestamp'] as Timestamp?)?.toDate() ?? DateTime.now(),
        isRead: data['isRead'] ?? false,
        listingId: data['listingId'],
        metadata: data['metadata'] != null ? Map<String, dynamic>.from(data['metadata']) : null,
      );
    } catch (e) {
      // Return a default message if parsing fails
      return Message(
        id: id,
        conversationId: '',
        senderId: '',
        receiverId: '',
        content: 'Error loading message',
        type: MessageType.text,
        timestamp: DateTime.now(),
        isRead: false,
        listingId: null,
        metadata: null,
      );
    }
  }

  Map<String, dynamic> toMap() {
    return {
      'conversationId': conversationId,
      'senderId': senderId,
      'receiverId': receiverId,
      'content': content,
      'type': type.toString().split('.').last,
      'timestamp': Timestamp.fromDate(timestamp),
      'isRead': isRead,
      'listingId': listingId,
      'metadata': metadata,
    };
  }

  Message copyWith({
    String? id,
    String? conversationId,
    String? senderId,
    String? receiverId,
    String? content,
    MessageType? type,
    DateTime? timestamp,
    bool? isRead,
    String? listingId,
    Map<String, dynamic>? metadata,
  }) {
    return Message(
      id: id ?? this.id,
      conversationId: conversationId ?? this.conversationId,
      senderId: senderId ?? this.senderId,
      receiverId: receiverId ?? this.receiverId,
      content: content ?? this.content,
      type: type ?? this.type,
      timestamp: timestamp ?? this.timestamp,
      isRead: isRead ?? this.isRead,
      listingId: listingId ?? this.listingId,
      metadata: metadata ?? this.metadata,
    );
  }
}

enum MessageType {
  text,
  image,
  system, // For system messages like "User joined conversation"
}

class Conversation {
  final String id;
  final List<String> participants; // User IDs
  final String? listingId; // Optional: for conversations about specific listings
  final String lastMessageId;
  final String lastMessageContent;
  final DateTime lastMessageTime;
  final String lastMessageSenderId;
  final int unreadCount;
  final Map<String, dynamic>? metadata; // For additional conversation data

  Conversation({
    required this.id,
    required this.participants,
    this.listingId,
    required this.lastMessageId,
    required this.lastMessageContent,
    required this.lastMessageTime,
    required this.lastMessageSenderId,
    this.unreadCount = 0,
    this.metadata,
  });

  factory Conversation.fromMap(Map<String, dynamic> data, String id) {
    try {
      return Conversation(
        id: id,
        participants: List<String>.from(data['participants'] ?? []),
        listingId: data['listingId'],
        lastMessageId: data['lastMessageId'] ?? '',
        lastMessageContent: data['lastMessageContent'] ?? '',
        lastMessageTime: (data['lastMessageTime'] as Timestamp?)?.toDate() ?? DateTime.now(),
        lastMessageSenderId: data['lastMessageSenderId'] ?? '',
        unreadCount: (data['unreadCount'] as num?)?.toInt() ?? 0,
        metadata: data['metadata'] != null ? Map<String, dynamic>.from(data['metadata']) : null,
      );
    } catch (e) {
      // Return a default conversation if parsing fails
      return Conversation(
        id: id,
        participants: [],
        listingId: null,
        lastMessageId: '',
        lastMessageContent: 'Error loading conversation',
        lastMessageTime: DateTime.now(),
        lastMessageSenderId: '',
        unreadCount: 0,
        metadata: null,
      );
    }
  }

  Map<String, dynamic> toMap() {
    return {
      'participants': participants,
      'listingId': listingId,
      'lastMessageId': lastMessageId,
      'lastMessageContent': lastMessageContent,
      'lastMessageTime': Timestamp.fromDate(lastMessageTime),
      'lastMessageSenderId': lastMessageSenderId,
      'unreadCount': unreadCount,
      'metadata': metadata,
    };
  }

  Conversation copyWith({
    String? id,
    List<String>? participants,
    String? listingId,
    String? lastMessageId,
    String? lastMessageContent,
    DateTime? lastMessageTime,
    String? lastMessageSenderId,
    int? unreadCount,
    Map<String, dynamic>? metadata,
  }) {
    return Conversation(
      id: id ?? this.id,
      participants: participants ?? this.participants,
      listingId: listingId ?? this.listingId,
      lastMessageId: lastMessageId ?? this.lastMessageId,
      lastMessageContent: lastMessageContent ?? this.lastMessageContent,
      lastMessageTime: lastMessageTime ?? this.lastMessageTime,
      lastMessageSenderId: lastMessageSenderId ?? this.lastMessageSenderId,
      unreadCount: unreadCount ?? this.unreadCount,
      metadata: metadata ?? this.metadata,
    );
  }
}

class ChatUser {
  final String id;
  final String name;
  final String email;
  final String? profileImageUrl;
  final String role; // 'food_provider' or 'food_consumer'
  final bool isOnline;
  final DateTime? lastSeen;

  ChatUser({
    required this.id,
    required this.name,
    required this.email,
    this.profileImageUrl,
    required this.role,
    this.isOnline = false,
    this.lastSeen,
  });

  factory ChatUser.fromMap(Map<String, dynamic> data, String id) {
    try {
      return ChatUser(
        id: id,
        name: data['name'] ?? '',
        email: data['email'] ?? '',
        profileImageUrl: data['profileImageUrl'],
        role: data['role'] ?? '',
        isOnline: data['isOnline'] ?? false,
        lastSeen: (data['lastSeen'] as Timestamp?)?.toDate(),
      );
    } catch (e) {
      // Return a default user if parsing fails
      return ChatUser(
        id: id,
        name: 'Unknown User',
        email: '',
        profileImageUrl: null,
        role: '',
        isOnline: false,
        lastSeen: null,
      );
    }
  }

  Map<String, dynamic> toMap() {
    return {
      'name': name,
      'email': email,
      'profileImageUrl': profileImageUrl,
      'role': role,
      'isOnline': isOnline,
      'lastSeen': lastSeen != null ? Timestamp.fromDate(lastSeen!) : null,
    };
  }
}
