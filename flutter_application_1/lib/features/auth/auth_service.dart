import 'package:flutter/foundation.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:cloud_firestore/cloud_firestore.dart';

class AuthService extends ChangeNotifier {
  final FirebaseAuth _auth = FirebaseAuth.instance;
  final FirebaseFirestore _firestore = FirebaseFirestore.instance;

  User? get currentUser => _auth.currentUser;
  bool get isLoggedIn => currentUser != null;
  bool get isEmailVerified => currentUser?.emailVerified ?? false;

  String? _userRole;
  String? get userRole => _userRole;

  Map<String, dynamic>? _userData;
  Map<String, dynamic>? get userData => _userData;

  AuthService() {
    // Listen to auth state only once and cleanly
    _auth.authStateChanges().listen(_onAuthStateChanged);
  }

  // MAIN AUTH HANDLER
  Future<void> _onAuthStateChanged(User? user) async {
    debugPrint('Auth state changed: ${user?.uid}');

    if (user == null) {
      _clearLocalState();
      notifyListeners();
      return;
    }

    // Only reload if necessary
    await _safeReload(user);

    // Load Firestore user data
    await _loadUserData(user.uid);

    notifyListeners();
  }

  void _clearLocalState() {
    _userRole = null;
    _userData = null;
  }

  String? _normalizeRole(String? role) {
    if (role == null) return null;
    switch (role.toLowerCase()) {
      case 'food_provider':
      case 'provider':
        return 'food_provider';
      case 'food_consumer':
      case 'consumer':
        return 'food_consumer';
      default:
        return role.toLowerCase();
    }
  }

  /// Recursively merge extraData into profileData, properly handling nested maps
  /// and ensuring all values are Firestore-compatible
  void _mergeExtraData(
      Map<String, dynamic> profileData, Map<String, dynamic> extraData) {
    for (final entry in extraData.entries) {
      final key = entry.key;
      final value = entry.value;

      // Skip null values
      if (value == null) continue;

      // Handle nested maps recursively
      if (value is Map<String, dynamic> || value is Map) {
        // Convert to Map<String, dynamic> and recursively process
        final nestedMap = Map<String, dynamic>.from(value);
        final processedMap = <String, dynamic>{};
        _mergeExtraData(processedMap, nestedMap);
        profileData[key] = processedMap;
      }
      // Handle lists - ensure they contain Firestore-compatible types
      else if (value is List) {
        profileData[key] = value.map((item) {
          if (item is Map) {
            final processedMap = <String, dynamic>{};
            _mergeExtraData(processedMap, Map<String, dynamic>.from(item));
            return processedMap;
          }
          return item;
        }).toList();
      }
      // Primitive types (String, int, double, bool) are Firestore-compatible
      else if (value is String ||
          value is int ||
          value is double ||
          value is bool ||
          value is DateTime) {
        profileData[key] = value;
      }
      // For any other type, convert to String to avoid invalid-argument errors
      else {
        debugPrint(
            'Warning: Converting unsupported type ${value.runtimeType} to String for Firestore');
        profileData[key] = value.toString();
      }
    }
  }

  Future<void> _safeReload(User user) async {
    try {
      await user.reload();
    } catch (_) {
      debugPrint("User reload skipped due to network or Firebase issue.");
    }
  }

  Future<void> _loadUserData(String uid) async {
    try {
      final doc = await _firestore.collection('users').doc(uid).get();

      if (!doc.exists) return;

      _userData = doc.data() ?? {};
      _userRole = _normalizeRole(_userData?['role'] as String?);

      // Update Firestore verified flag *only if needed*
      if (currentUser?.emailVerified == true &&
          (_userData?['verified'] != true)) {
        await _firestore.collection('users').doc(uid).update({
          'verified': true,
          'emailVerified': true,
          'updatedAt': FieldValue.serverTimestamp(),
        });

        _userData?['verified'] = true;
        _userData?['emailVerified'] = true;
      }
    } catch (e) {
      debugPrint('Error loading user data: $e');
    }
  }

  Future<void> resendVerificationEmail(String email, String password) async {
    try {
      final credential = await _auth.signInWithEmailAndPassword(
        email: email,
        password: password,
      );

      await credential.user?.sendEmailVerification();
      await _auth.signOut();
    } catch (e) {
      debugPrint("Resend verification failed: $e");
      rethrow;
    }
  }

  bool get isLoggedInAndVerified =>
      currentUser != null && currentUser!.emailVerified;

  // --------------------------
  // REGISTER
  // --------------------------
  Future<Map<String, dynamic>> registerWithEmailAndPassword({
    required String email,
    required String password,
    required String name,
    required String phone,
    required String role,
    required String address,
    Map<String, dynamic>? extraData,
  }) async {
    try {
      if (!['food_provider', 'food_consumer'].contains(role)) {
        return {'success': false, 'message': 'Invalid role.'};
      }

      final credential = await _auth.createUserWithEmailAndPassword(
        email: email,
        password: password,
      );

      final uid = credential.user!.uid;

      final profileData = {
        'uid': uid,
        'name': name,
        'email': email,
        'phone': phone,
        'role': role,
        'address': address,
        'verified': false,
        'emailVerified': false,
        'createdAt': FieldValue.serverTimestamp(),
        'updatedAt': FieldValue.serverTimestamp(),
      };

      if (extraData != null && extraData.isNotEmpty) {
        // Properly merge extraData, handling nested maps recursively
        _mergeExtraData(profileData, extraData);
      }

      await _firestore.collection('users').doc(uid).set(profileData);

      // Use Firebase's default email verification (no custom actionCodeSettings)
      // IMPORTANT: If verification emails still contain localhost:8000, 
      // you must remove the custom action handler URL in Firebase Console:
      // Authentication → Templates → Email address verification → Action URL
      // Leave it empty to use Firebase's default handler
      await credential.user!.sendEmailVerification();
      await _safeReload(credential.user!);
      await _auth.signOut();
      await _loadUserData(uid);

      return {
        'success': true,
        'message': 'Registration successful! Verify your email.',
        'user': credential.user,
        'needsVerification': true,
      };
    } on FirebaseAuthException catch (e) {
      debugPrint('Firebase Auth Error during registration: ${e.code} - ${e.message}');
      return _authError(e);
    } catch (e, stackTrace) {
      debugPrint('Unexpected error during registration: $e');
      debugPrint('Stack trace: $stackTrace');
      return {
        'success': false,
        'message': 'Registration failed: ${e.toString()}',
      };
    }
  }

  // --------------------------
  // LOGIN
  // --------------------------
  Future<Map<String, dynamic>> signInWithEmailAndPassword(
      String email, String password) async {
    try {
      final credential = await _auth.signInWithEmailAndPassword(
        email: email,
        password: password,
      );

      await _loadUserData(credential.user!.uid);

      return {
        'success': true,
        'message': credential.user!.emailVerified
            ? 'Sign in successful!'
            : 'Please verify your email.',
        'user': credential.user,
        'requiresVerification': !(credential.user!.emailVerified),
      };
    } on FirebaseAuthException catch (e) {
      return _authError(e);
    } catch (_) {
      return {'success': false, 'message': 'Unexpected error.'};
    }
  }

  Map<String, dynamic> _authError(FirebaseAuthException e) {
    switch (e.code) {
      case 'weak-password':
        return {'success': false, 'message': 'Password too weak.'};
      case 'email-already-in-use':
        return {'success': false, 'message': 'Email already used.'};
      case 'invalid-email':
        return {'success': false, 'message': 'Invalid email.'};
      case 'user-not-found':
        return {'success': false, 'message': 'User not found.'};
      case 'wrong-password':
        return {'success': false, 'message': 'Wrong password.'};
      default:
        return {'success': false, 'message': e.message ?? 'Auth error.'};
    }
  }

  // --------------------------
  // PASSWORD RESET
  // --------------------------
  Future<Map<String, dynamic>> sendPasswordResetEmail(String email) async {
    try {
      await _auth.sendPasswordResetEmail(email: email);
      return {'success': true, 'message': 'Reset email sent.'};
    } on FirebaseAuthException catch (e) {
      return _authError(e);
    } catch (_) {
      return {'success': false, 'message': 'Unexpected error.'};
    }
  }

  // --------------------------
  // VERIFICATION RESEND
  // --------------------------
  Future<Map<String, dynamic>> resendEmailVerification() async {
    try {
      if (currentUser == null) {
        return {'success': false, 'message': 'Please sign in first.'};
      }

      // Use Firebase's default email verification (no custom actionCodeSettings)
      // The verification link will use Firebase's built-in handler page
      await currentUser!.sendEmailVerification();
      return {'success': true, 'message': 'Verification email sent!'};
    } catch (e) {
      return {
        'success': false,
        'message': e is FirebaseAuthException
            ? (e.message ?? 'Error sending verification email.')
            : 'Error sending verification email.'
      };
    }
  }

  // --------------------------
  // SIGN OUT
  // --------------------------
  Future<void> signOut() async {
    await _auth.signOut();
    _clearLocalState();
    notifyListeners();
  }

  // --------------------------
  // ROLE CHECKER
  // --------------------------
  bool hasRole(String role) {
    final normalizedTarget = _normalizeRole(role);
    if (normalizedTarget == null) return false;

    final currentRole = _userRole ?? _normalizeRole(_userData?['role'] as String?);
    if (currentRole == normalizedTarget) return true;

    final rolesField = _userData?['roles'];
    if (rolesField is List) {
      for (final entry in rolesField) {
        final normalizedEntry = entry is String
            ? _normalizeRole(entry)
            : _normalizeRole(entry?.toString());
        if (normalizedEntry == normalizedTarget) {
          return true;
        }
      }
    }

    return false;
  }

  // --------------------------
  // VERIFICATION STATUS
  // --------------------------
  Future<void> updateVerificationStatus(bool verified) async {
    if (currentUser == null) return;

    try {
      await _firestore.collection('users').doc(currentUser!.uid).update({
        'verified': verified,
      });

      _userData?['verified'] = verified;
      notifyListeners();
    } catch (e) {
      debugPrint('Verification status update failed: $e');
    }
  }

  Future<void> reloadUser() async {
    if (currentUser == null) return;

    try {
      // Reload user to get latest emailVerified status from Firebase
      await currentUser!.reload();
      await _loadUserData(currentUser!.uid);
      
      // Sync Firestore verified field with Firebase Auth emailVerified status
      if (currentUser!.emailVerified && (_userData?['verified'] != true)) {
        await updateVerificationStatus(true);
      }
      
      notifyListeners();
    } catch (e) {
      debugPrint('Error reloading user: $e');
    }
  }

  Future<void> syncVerificationStatus() async {
    if (currentUser == null) return;

    await reloadUser();
  }
}
