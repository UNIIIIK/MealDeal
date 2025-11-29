// optimized: features/messaging/chat_screen.dart
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import 'dart:convert';
import '../../services/messaging_service.dart';
import '../../models/message.dart';
import '../../features/auth/auth_service.dart';

class ChatScreen extends StatefulWidget {
  final String conversationId;
  final ChatUser otherUser;
  final String? listingId;

  const ChatScreen({
    super.key,
    required this.conversationId,
    required this.otherUser,
    this.listingId,
  });

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  bool _isTyping = false;

  ImageProvider? _buildImageProvider(String? url) {
    if (url == null || url.isEmpty) return null;
    if (url.startsWith('data:image/')) {
      try {
        final base64Part = url.split(',').last;
        final bytes = base64Decode(base64Part);
        return MemoryImage(bytes);
      } catch (e) {
        debugPrint('Error decoding base64 image: $e');
        return null;
      }
    }
    return NetworkImage(url);
  }

  // Local state for pagination
  bool _isLoadingMore = false;
  bool _hasMore = true;
  DateTime? _oldestLoaded; // timestamp of oldest message currently loaded

  StreamSubscription<List<Message>>? _messagesSub;
  List<Message> _messages = [];

  @override
  void initState() {
    super.initState();

    // Mark messages as read when opening chat (non-blocking)
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<MessagingService>().markMessagesAsRead(widget.conversationId);
    });

    // Subscribe to latest messages (descending)
    _messagesSub = context
        .read<MessagingService>()
        .getLatestMessages(widget.conversationId)
        .listen((latest) {
      // latest is newest-first; convert to chronological by reversing
      final chrono = List<Message>.from(latest.reversed);
      setState(() {
        _messages = chrono;
        _oldestLoaded = chrono.isNotEmpty ? chrono.first.timestamp : null;
        // If fewer than pageSize loaded, we likely have no more older messages
        _hasMore = latest.length >= MessagingService.pageSize;
      });

      // Auto-scroll to bottom when new message arrives and user is near bottom
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!_scrollController.hasClients) return;
        final position = _scrollController.position;
        final nearBottom = position.pixels >= (position.maxScrollExtent - 200);
        if (nearBottom) {
          _scrollController.animateTo(
            position.maxScrollExtent,
            duration: const Duration(milliseconds: 250),
            curve: Curves.easeOut,
          );
        }
      });
    }, onError: (e) {
      debugPrint('messages stream error: $e');
    });

    // Add scroll listener to implement "load more when scrolled to top"
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels <= _scrollController.position.minScrollExtent + 60) {
      // near top -> load older messages
      _loadOlderMessages();
    }
  }

  Future<void> _loadOlderMessages() async {
    if (_isLoadingMore || !_hasMore || _oldestLoaded == null) return;
    _isLoadingMore = true;
    try {
      final older = await context.read<MessagingService>().fetchOlderMessages(
            widget.conversationId,
            _oldestLoaded!,
            limit: MessagingService.pageSize,
          );
      if (older.isEmpty) {
        _hasMore = false;
      } else {
        // older is newest-first; we want to prepend chronologically
        final chronoOlder = List<Message>.from(older.reversed);
        setState(() {
          _messages = [...chronoOlder, ..._messages];
          _oldestLoaded = _messages.first.timestamp;
        });
      }
    } catch (e) {
      debugPrint('loadOlderMessages error: $e');
    } finally {
      _isLoadingMore = false;
    }
  }

  @override
  void dispose() {
    _messageController.dispose();
    _scrollController.dispose();
    _messagesSub?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final authService = context.read<AuthService>();
    final currentUserId = authService.currentUser?.uid;

    return Scaffold(
      appBar: AppBar(
        backgroundColor: Colors.green.shade600,
        foregroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => Navigator.of(context).pop()),
        title: Row(
          children: [
            CircleAvatar(
              radius: 18,
              backgroundColor: Colors.white,
              backgroundImage: _buildImageProvider(widget.otherUser.profileImageUrl),
              child: widget.otherUser.profileImageUrl == null ? Text(widget.otherUser.name.isNotEmpty ? widget.otherUser.name[0].toUpperCase() : 'U', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.green.shade800)) : null,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(widget.otherUser.name, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                  Text(widget.otherUser.isOnline ? 'Online' : 'Last seen ${_formatLastSeen(widget.otherUser.lastSeen)}', style: const TextStyle(fontSize: 12)),
                ],
              ),
            ),
          ],
        ),
      ),
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Colors.green.shade50, Colors.white]),
        ),
        child: Column(
          children: [
            // Messages list (chronological order)
            Expanded(
              child: _buildMessagesList(currentUserId),
            ),

            // Input bar
            SafeArea(
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: Colors.white, boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 8, offset: const Offset(0, -2))]),
                child: Row(
                  children: [
                    Expanded(
                      child: Container(
                        decoration: BoxDecoration(color: Colors.grey.shade100, borderRadius: BorderRadius.circular(24)),
                        child: TextField(
                          controller: _messageController,
                          decoration: InputDecoration(
                            hintText: 'Type a message...',
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                          ),
                          maxLines: null,
                          onChanged: (v) => setState(() => _isTyping = v.trim().isNotEmpty),
                          onSubmitted: (v) => _sendMessage(),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    CircleAvatar(
                      backgroundColor: _isTyping ? Colors.green.shade600 : Colors.grey.shade300,
                      child: IconButton(
                        icon: Icon(Icons.send, color: _isTyping ? Colors.white : Colors.grey.shade600),
                        onPressed: _isTyping ? _sendMessage : null,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMessagesList(String? currentUserId) {
    if (_messages.isEmpty) {
      return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [Icon(Icons.chat_bubble_outline, size: 64, color: Colors.grey.shade400), const SizedBox(height: 12), Text('No messages yet', style: TextStyle(color: Colors.grey.shade600, fontSize: 16))]));
    }

    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      itemCount: _messages.length + (_hasMore ? 1 : 0),
      itemBuilder: (context, index) {
        if (_hasMore && index == 0) {
          // top-loading indicator for more messages
          return Center(child: _isLoadingMore ? Padding(padding: const EdgeInsets.all(8), child: CircularProgressIndicator(strokeWidth: 2)) : const SizedBox(height: 12));
        }

        final msgIndex = _hasMore ? index - 1 : index;
        final message = _messages[msgIndex];
        final isCurrentUser = message.senderId == currentUserId;
        final isSystem = message.type == MessageType.system;

        if (isSystem) return _buildSystemMessage(message);
        return _buildMessageBubble(message, isCurrentUser);
      },
    );
  }

  // Reuse your existing bubble builders (kept similar to original)
  Widget _buildMessageBubble(Message message, bool isCurrentUser) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      child: Row(
        mainAxisAlignment: isCurrentUser ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!isCurrentUser) ...[
            CircleAvatar(radius: 16, backgroundColor: Colors.green.shade100, backgroundImage: _buildImageProvider(widget.otherUser.profileImageUrl), child: widget.otherUser.profileImageUrl == null ? Text(widget.otherUser.name.isNotEmpty ? widget.otherUser.name[0].toUpperCase() : 'U', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.green.shade800)) : null),
            const SizedBox(width: 8),
          ],
          Flexible(
            child: Container(
              constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.72),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(color: isCurrentUser ? Colors.green.shade600 : Colors.grey.shade200, borderRadius: BorderRadius.circular(16)),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(message.content, style: TextStyle(color: isCurrentUser ? Colors.white : Colors.black87, fontSize: 15)),
                const SizedBox(height: 6),
                Row(mainAxisSize: MainAxisSize.min, children: [
                  Text(_formatMessageTime(message.timestamp), style: TextStyle(color: isCurrentUser ? Colors.white70 : Colors.grey.shade600, fontSize: 11)),
                  if (isCurrentUser) ...[const SizedBox(width: 6), Icon(message.isRead ? Icons.done_all : Icons.done, size: 14, color: message.isRead ? Colors.blue.shade300 : Colors.white70)]
                ])
              ]),
            ),
          ),
          if (isCurrentUser) ...[
            const SizedBox(width: 8),
            CircleAvatar(radius: 16, backgroundColor: Colors.green.shade100, child: Text(context.read<AuthService>().userData?['name']?[0]?.toUpperCase() ?? 'U', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.green.shade800))),
          ],
        ],
      ),
    );
  }

  Widget _buildSystemMessage(Message message) {
    return Container(margin: const EdgeInsets.symmetric(vertical: 8), child: Center(child: Container(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8), decoration: BoxDecoration(color: Colors.grey.shade200, borderRadius: BorderRadius.circular(16)), child: Text(message.content, style: TextStyle(color: Colors.grey.shade700, fontStyle: FontStyle.italic)))));
  }

  void _sendMessage() {
    final content = _messageController.text.trim();
    if (content.isEmpty) return;

    final messaging = context.read<MessagingService>();
    messaging.sendMessage(conversationId: widget.conversationId, receiverId: widget.otherUser.id, content: content, listingId: widget.listingId);

    _messageController.clear();
    setState(() => _isTyping = false);

    // jump to bottom quickly for immediate feedback
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(_scrollController.position.maxScrollExtent, duration: const Duration(milliseconds: 200), curve: Curves.easeOut);
      }
    });
  }

  String _formatMessageTime(DateTime ts) {
    final now = DateTime.now();
    final diff = now.difference(ts);
    if (diff.inDays > 0) return DateFormat('MMM dd, hh:mm a').format(ts);
    return DateFormat('hh:mm a').format(ts);
  }

  String _formatLastSeen(DateTime? lastSeen) {
    if (lastSeen == null) return 'unknown';
    final now = DateTime.now();
    final d = now.difference(lastSeen);
    if (d.inDays > 0) return '${d.inDays}d ago';
    if (d.inHours > 0) return '${d.inHours}h ago';
    if (d.inMinutes > 0) return '${d.inMinutes}m ago';
    return 'just now';
  }
}
