import 'package:cloud_firestore/cloud_firestore.dart';
import 'models/message.dart';
import 'services/messaging_service.dart';

// Test function to create a sample conversation
Future<void> createTestConversation() async {
  try {
    final firestore = FirebaseFirestore.instance;
    
    // Create a test conversation
    final conversationRef = firestore.collection('conversations').doc();
    
    final conversation = Conversation(
      id: conversationRef.id,
      participants: ['test_user_1', 'test_user_2'],
      listingId: 'test_listing_1',
      lastMessageId: '',
      lastMessageContent: 'Hello! I\'m interested in this food item.',
      lastMessageTime: DateTime.now(),
      lastMessageSenderId: 'test_user_1',
      unreadCount: 1,
    );
    
    await conversationRef.set(conversation.toMap());
    print('Test conversation created successfully: ${conversationRef.id}');
    
    // Create a test message
    final messageRef = firestore
        .collection('conversations')
        .doc(conversationRef.id)
        .collection('messages')
        .doc();
    
    final message = Message(
      id: messageRef.id,
      conversationId: conversationRef.id,
      senderId: 'test_user_1',
      receiverId: 'test_user_2',
      content: 'Hello! I\'m interested in this food item.',
      type: MessageType.text,
      timestamp: DateTime.now(),
      listingId: 'test_listing_1',
    );
    
    await messageRef.set(message.toMap());
    print('Test message created successfully: ${messageRef.id}');
    
  } catch (e) {
    print('Error creating test conversation: $e');
  }
}

// Test function to verify messaging service
Future<void> testMessagingService() async {
  try {
    final messagingService = MessagingService();
    
    // Test getting conversations
    print('Testing getConversations...');
    final conversationsStream = messagingService.getConversations();
    
    await for (final conversations in conversationsStream.take(1)) {
      print('Found ${conversations.length} conversations');
      for (final conversation in conversations) {
        print('Conversation: ${conversation.id} - ${conversation.lastMessageContent}');
      }
    }
    
  } catch (e) {
    print('Error testing messaging service: $e');
  }
}
