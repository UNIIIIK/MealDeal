// optimized: features/messaging/chat_list_screen.dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../../services/messaging_service.dart';
import '../../models/message.dart';
import '../../features/auth/auth_service.dart';
import 'chat_screen.dart';

class ChatListScreen extends StatefulWidget {
  const ChatListScreen({super.key});

  @override
  State<ChatListScreen> createState() => _ChatListScreenState();
}

class _ChatListScreenState extends State<ChatListScreen> {
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final messaging = context.read<MessagingService>();

    return Scaffold(
      appBar: AppBar(title: const Text('Messages'), backgroundColor: Colors.green.shade600, foregroundColor: Colors.white, elevation: 0),
      body: Container(
        decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Colors.green.shade50, Colors.white])),
        child: Column(
          children: [
            // search bar
            Container(
              margin: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 10, offset: const Offset(0, 4))]),
              child: TextField(
                controller: _searchController,
                decoration: InputDecoration(hintText: 'Search conversations...', prefixIcon: const Padding(padding: EdgeInsets.all(8), child: Icon(Icons.search)), border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none)),
                onChanged: (v) => setState(() => _searchQuery = v.trim()),
              ),
            ),

            // conversations list (streamed and ordered server-side)
            Expanded(
              child: StreamBuilder<List<Conversation>>(
                stream: _searchQuery.isEmpty ? messaging.getConversations() : messaging.searchConversations(_searchQuery),
                builder: (context, snap) {
                  if (snap.connectionState == ConnectionState.waiting) return const Center(child: CircularProgressIndicator());
                  if (snap.hasError) {
                    return Center(child: Text('Error loading conversations: ${snap.error}'));
                  }

                  final conversations = snap.data ?? [];
                  if (conversations.isEmpty) {
                    return Center(child: Text(_searchQuery.isEmpty ? 'No conversations yet' : 'No conversations found'));
                  }

                  return ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    itemCount: conversations.length,
                    itemBuilder: (context, i) {
                      final conv = conversations[i];
                      return _ConversationTile(conv: conv);
                    },
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ConversationTile extends StatefulWidget {
  final Conversation conv;
  const _ConversationTile({required this.conv});

  @override
  State<_ConversationTile> createState() => _ConversationTileState();
}

class _ConversationTileState extends State<_ConversationTile> {
  ChatUser? otherUser;
  bool loading = true;

  @override
  void initState() {
    super.initState();
    _resolveOtherUser();
  }

  Future<void> _resolveOtherUser() async {
    final messaging = context.read<MessagingService>();
    final auth = context.read<AuthService>();
    final myId = auth.currentUser?.uid ?? '';

    // Try to find participant in cache or fetch
    final participants = widget.conv.participants;
    final otherId = participants.firstWhere((p) => p != myId, orElse: () => '');
    if (otherId.isEmpty) {
      setState(() { loading = false; otherUser = ChatUser(id: '', name: 'Unknown', email: '', role: '', profileImageUrl: null); });
      return;
    }

    final cached = await messaging.getUserInfo(otherId); // uses cache internally
    setState(() {
      otherUser = cached;
      loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final conv = widget.conv;
    final auth = context.read<AuthService>();
    final myId = auth.currentUser?.uid ?? '';

    final isUnread = conv.unreadCount > 0 && conv.lastMessageSenderId != myId;

    if (loading) return _skeleton();

    final displayName = otherUser?.name ?? 'Unknown User';
    final displayImage = otherUser?.profileImageUrl;
    final roleLabel = (otherUser?.role == 'food_provider') ? 'Provider' : 'Consumer';

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8, offset: const Offset(0, 2))]),
      child: ListTile(
        contentPadding: const EdgeInsets.all(12),
        leading: CircleAvatar(radius: 28, backgroundColor: Colors.green.shade100, backgroundImage: displayImage != null ? NetworkImage(displayImage) : null, child: displayImage == null ? Text(displayName.isNotEmpty ? displayName[0].toUpperCase() : 'U', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.green.shade800)) : null),
        title: Row(children: [Expanded(child: Text(displayName, style: TextStyle(fontWeight: isUnread ? FontWeight.bold : FontWeight.w600, fontSize: 16, color: isUnread ? Colors.black87 : Colors.black54))), if (conv.listingId != null) Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4), decoration: BoxDecoration(color: Colors.orange.shade100, borderRadius: BorderRadius.circular(12)), child: Text('FOOD', style: TextStyle(color: Colors.orange.shade700, fontSize: 10, fontWeight: FontWeight.bold)))],),
        subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [const SizedBox(height: 6), Text(conv.lastMessageContent, maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(color: isUnread ? Colors.black87 : Colors.grey.shade600, fontSize: 14)), const SizedBox(height: 6), Row(children: [Text(_formatTimestamp(conv.lastMessageTime)), const SizedBox(width: 8), Text(roleLabel, style: TextStyle(color: otherUser?.role == 'food_provider' ? Colors.blue.shade600 : Colors.green.shade600, fontSize: 12, fontWeight: FontWeight.w600))])]),
        trailing: isUnread ? Container(padding: const EdgeInsets.all(8), decoration: BoxDecoration(color: Colors.green.shade600, shape: BoxShape.circle), child: Text(conv.unreadCount > 99 ? '99+' : '${conv.unreadCount}', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold))) : null,
        onTap: () {
          if (otherUser == null) return;
          Navigator.of(context).push(MaterialPageRoute(builder: (_) => ChatScreen(conversationId: conv.id, otherUser: otherUser!, listingId: conv.listingId)));
        },
      ),
    );
  }

  Widget _skeleton() {
    return Container(margin: const EdgeInsets.only(bottom: 12), padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8, offset: const Offset(0, 2))]), child: ListTile(leading: CircleAvatar(radius: 28, backgroundColor: Colors.grey.shade300), title: Container(height: 16, width: 120, color: Colors.grey.shade300), subtitle: Column(children: [const SizedBox(height: 8), Container(height: 12, width: 200, color: Colors.grey.shade300)])));
  }

  static String _formatTimestamp(DateTime timestamp) {
    final now = DateTime.now();
    final diff = now.difference(timestamp);
    if (diff.inDays > 7) return DateFormat('MMM dd, yyyy').format(timestamp);
    if (diff.inDays > 0) return DateFormat('EEE').format(timestamp);
    if (diff.inHours > 0) return '${diff.inHours}h ago';
    if (diff.inMinutes > 0) return '${diff.inMinutes}m ago';
    return 'Just now';
  }
}
