import 'package:flutter/foundation.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

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

  // ✔ SAFE, does not spam backend
  Future<Map<String, dynamic>> sendEmailVerification() async {
    try {
      final email = currentUser?.email;
      if (email == null) {
        return {'success': false, 'message': 'No signed-in user.'};
      }

      final response = await http.post(
        Uri.parse('http://localhost:8000/resend_verification.php'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'email': email}),
      );

      final data = jsonDecode(response.body);
      if (response.statusCode == 200 && data['success'] == true) {
        return {'success': true, 'message': data['message']};
      }

      return {
        'success': false,
        'message': data['message'] ?? 'Failed to send verification email.'
      };
    } catch (e) {
      return {
        'success': false,
        'message': 'Error: ${e.toString()}',
      };
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

      await _firestore.collection('users').doc(uid).set({
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
      });

      await credential.user!.sendEmailVerification();
      await _loadUserData(uid);

      return {
        'success': true,
        'message': 'Registration successful! Verify your email.',
        'user': credential.user,
        'needsVerification': true,
      };
    } on FirebaseAuthException catch (e) {
      return _authError(e);
    } catch (_) {
      return {'success': false, 'message': 'Unexpected error.'};
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

      if (!credential.user!.emailVerified) {
        await _auth.signOut();
        return {
          'success': false,
          'code': 'email-not-verified',
          'message': 'Please verify your email first.',
        };
      }

      await _loadUserData(credential.user!.uid);

      return {
        'success': true,
        'message': 'Sign in successful!',
        'user': credential.user,
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
      await currentUser?.sendEmailVerification();
      return {'success': true, 'message': 'Verification email sent!'};
    } catch (_) {
      return {'success': false, 'message': 'Error sending verification email.'};
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
      await currentUser!.reload();
      await _loadUserData(currentUser!.uid);
      notifyListeners();
    } catch (_) {}
  }

  Future<void> syncVerificationStatus() async {
    if (currentUser == null) return;

    await reloadUser();
  }
}
