// Firestore connection helper with retry logic
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/foundation.dart';

class FirestoreHelper {
  // Configure Firestore settings for better connection handling
  static void configureFirestore() {
    FirebaseFirestore.instance.settings = const Settings(
      persistenceEnabled: true,
      cacheSizeBytes: Settings.CACHE_SIZE_UNLIMITED,
    );
  }

  // Helper to create a stream with error handling and retry logic
  static Stream<QuerySnapshot<T>> createQueryStream<T>({
    required Query<T> query,
    void Function(Object error)? onError,
  }) {
    return query.snapshots().handleError((error) {
      debugPrint('Firestore query error: $error');
      onError?.call(error);
      // Return empty snapshot on error
      return <QueryDocumentSnapshot<T>>[];
    });
  }

  // Helper to create a document stream with error handling
  static Stream<DocumentSnapshot<T>> createDocumentStream<T>({
    required DocumentReference<T> reference,
    void Function(Object error)? onError,
  }) {
    return reference.snapshots().handleError((error) {
      debugPrint('Firestore document stream error: $error');
      onError?.call(error);
      // Return empty snapshot on error - caller should handle this
      throw error;
    });
  }

  // Retry a Firestore operation with exponential backoff
  static Future<T> retryOperation<T>({
    required Future<T> Function() operation,
    int maxRetries = 3,
    Duration initialDelay = const Duration(seconds: 1),
  }) async {
    int attempts = 0;
    Duration delay = initialDelay;

    while (attempts < maxRetries) {
      try {
        return await operation();
      } catch (e) {
        attempts++;
        if (attempts >= maxRetries) {
          rethrow;
        }
        debugPrint('Firestore operation failed (attempt $attempts/$maxRetries): $e');
        await Future.delayed(delay);
        delay = Duration(milliseconds: delay.inMilliseconds * 2); // Exponential backoff
      }
    }
    throw Exception('Max retries exceeded');
  }
}

