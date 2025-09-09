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
    _auth.authStateChanges().listen(_onAuthStateChanged);
  }

  Future<void> _onAuthStateChanged(User? user) async {
    if (user != null) {
      // Reload user to get the latest email verification status
      await user.reload();
      await _loadUserData(user.uid);
    } else {
      _userRole = null;
      _userData = null;
    }
    notifyListeners();
  }

  Future<void> _loadUserData(String uid) async {
    try {
      final doc = await _firestore.collection('users').doc(uid).get();

      if (doc.exists) {
        _userData = doc.data();
        _userRole = _userData?['role'];
        
        // Update local state with verification status
        if (currentUser?.emailVerified == true && _userData?['verified'] != true) {
          await _firestore.collection('users').doc(uid).update({
            'verified': true,
            'emailVerified': true,
            'updatedAt': FieldValue.serverTimestamp(),
          });
          _userData?['verified'] = true;
          _userData?['emailVerified'] = true;
        }
      }
    } catch (e) {
      debugPrint('Error loading user data: $e');
      // Do not rethrow to avoid breaking flows on web when Firestore is temporarily unavailable
    }
  }

  // Send email verification through PHP backend
  Future<Map<String, dynamic>> sendEmailVerification() async {
    try {
      final email = currentUser?.email;
      if (email == null) {
        throw Exception('No user is currently signed in');
      }

      final response = await http.post(
        Uri.parse('http://localhost:8000/resend_verification.php'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'email': email}),
      );

      final data = jsonDecode(response.body);
      
      if (response.statusCode == 200 && data['success'] == true) {
        return {'success': true, 'message': data['message']};
      } else {
        throw Exception(data['message'] ?? 'Failed to send verification email');
      }
    } catch (e) {
      debugPrint('Error sending verification email: $e');
      rethrow;
    }
  }

  Future<void> resendVerificationEmail(String email, String password) async {
    try {
      // Sign in the user
      final credential = await _auth.signInWithEmailAndPassword(
        email: email,
        password: password,
      );
      
      // Send verification email
      await credential.user?.sendEmailVerification();
      debugPrint('Verification email resent to: $email');
      
      // Sign out to prevent auto-login
      await _auth.signOut();
      
      return;
    } on FirebaseAuthException catch (e) {
      debugPrint('Failed to resend verification email: ${e.message}');
      rethrow;
    }
  }

  // Check if user is logged in and verified
  bool get isLoggedInAndVerified => _auth.currentUser != null && isEmailVerified;

  Future<Map<String, dynamic>> registerWithEmailAndPassword({
    required String email,
    required String password,
    required String name,
    required String phone,
    required String role,
    required String address,
  }) async {
    try {
      // Validate role
      if (!['food_provider', 'food_consumer'].contains(role)) {
        return {
          'success': false,
          'message': 'Invalid role. Must be food_provider or food_consumer.'
        };
      }

      final credential = await _auth.createUserWithEmailAndPassword(
        email: email,
        password: password,
      );

      if (credential.user != null) {
        // Create user document in Firestore
        await _firestore.collection('users').doc(credential.user!.uid).set({
          'uid': credential.user!.uid,
          'name': name,
          'email': email,
          'phone': phone,
          'role': role,
          'address': address,
          'verified': false,
          'createdAt': FieldValue.serverTimestamp(),
          'updatedAt': FieldValue.serverTimestamp(),
        });
        
        // Send email verification
        try {
          await credential.user!.sendEmailVerification();
          debugPrint('Verification email sent to: $email');
          debugPrint('User emailVerified status: ${credential.user!.emailVerified}');
        } catch (e) {
          debugPrint('Failed to send verification email: $e');
          rethrow;
        }

        await _loadUserData(credential.user!.uid);

        return {
          'success': true,
          'message': 'Registration successful! Please check your email to verify your account.',
          'user': credential.user,
          'needsVerification': true
        };
      }

      return {
        'success': false,
        'message': 'Registration failed. Please try again.'
      };
    } on FirebaseAuthException catch (e) {
      String message;
      switch (e.code) {
        case 'weak-password':
          message = 'The password provided is too weak.';
          break;
        case 'email-already-in-use':
          message = 'An account already exists for that email.';
          break;
        case 'invalid-email':
          message = 'The email address is not valid.';
          break;
        default:
          message = e.message ?? 'Registration failed.';
      }
      return {'success': false, 'message': message};
    } catch (e) {
      return {'success': false, 'message': 'An unexpected error occurred.'};
    }
  }

  // Sign in with email and password
  Future<Map<String, dynamic>> signInWithEmailAndPassword(
      String email, String password) async {
    try {
      // First try to sign in
      final userCredential = await _auth.signInWithEmailAndPassword(
        email: email,
        password: password,
      );
      
      // Check if email is verified
      if (userCredential.user?.emailVerified == false) {
        await _auth.signOut();
        return {
          'success': false,
          'code': 'email-not-verified',
          'message': 'Please verify your email before signing in.',
        };
      }

      try {
        await _loadUserData(userCredential.user!.uid);
      } catch (_) {}

      // If role missing in Firestore, infer from custom claims if present
      if (_userRole == null) {
        try {
          final idTokenResult = await userCredential.user!.getIdTokenResult();
          final claims = idTokenResult.claims ?? {};
          final claimRole = claims['role'];
          if (claimRole is String && (claimRole == 'food_provider' || claimRole == 'food_consumer')) {
            _userRole = claimRole;
          }
        } catch (_) {}
      }
      return {
        'success': true,
        'message': 'Sign in successful!',
        'user': userCredential.user,
      };
    } on FirebaseAuthException catch (e) {
      String message;
      switch (e.code) {
        case 'user-not-found':
          message = 'No user found for that email.';
          break;
        case 'wrong-password':
          message = 'Wrong password provided.';
          break;
        case 'invalid-email':
          message = 'The email address is not valid.';
          break;
        case 'user-disabled':
          message = 'This user account has been disabled.';
          break;
        default:
          message = e.message ?? 'Sign in failed.';
      }
      return {'success': false, 'message': message};
    } catch (e) {
      return {'success': false, 'message': 'An unexpected error occurred.'};
    }
  }

  // Send password reset email
  Future<Map<String, dynamic>> sendPasswordResetEmail(String email) async {
    try {
      await _auth.sendPasswordResetEmail(email: email);
      return {
        'success': true,
        'message': 'Password reset email sent! Check your inbox.',
      };
    } on FirebaseAuthException catch (e) {
      String message;
      switch (e.code) {
        case 'user-not-found':
          message = 'No user found for that email.';
          break;
        case 'invalid-email':
          message = 'The email address is not valid.';
          break;
        default:
          message = e.message ?? 'Failed to send password reset email.';
      }
      return {'success': false, 'message': message};
    } catch (e) {
      return {'success': false, 'message': 'An unexpected error occurred.'};
    }
  }

  // Resend email verification
  Future<Map<String, dynamic>> resendEmailVerification() async {
    try {
      if (currentUser != null) {
        await currentUser!.sendEmailVerification();
        return {
          'success': true,
          'message': 'Verification email sent! Check your inbox.',
        };
      }
      return {'success': false, 'message': 'No user logged in.'};
    } catch (e) {
      return {'success': false, 'message': 'Failed to send verification email.'};
    }
  }

  // Sign out
  Future<void> signOut() async {
    await _auth.signOut();
    _userRole = null;
    _userData = null;
    notifyListeners();
  }

  // Force sign-out on hot reload for web to avoid sticky sessions
  // Call from main() via WidgetsBindingObserver if desired. For now, expose a helper:
  Future<void> signOutIfWebHotReload() async {
    try {
      await _auth.signOut();
    } catch (_) {}
  }

  // Check if user has specific role
  bool hasRole(String role) {
    return _userRole == role;
  }

  // Update user verification status
  Future<void> updateVerificationStatus(bool verified) async {
    if (currentUser == null) return;

    try {
      await _firestore.collection('users').doc(currentUser!.uid).update({
        'verified': verified,
      });
      
      if (_userData != null) {
        _userData!['verified'] = verified;
        notifyListeners();
      }
    } catch (e) {
      debugPrint('Error updating verification status: $e');
    }
  }

  // Reload current user (useful after email verification)
  Future<void> reloadUser() async {
    if (currentUser != null) {
      await currentUser!.reload();
      await _loadUserData(currentUser!.uid);
      notifyListeners();
    }
  }

  // Force sync verification status from Firebase Auth to Firestore
  Future<void> syncVerificationStatus() async {
    if (currentUser == null) return;

    try {
      await currentUser!.reload();
      
      if (currentUser!.emailVerified) {
        await _firestore.collection('users').doc(currentUser!.uid).update({
          'verified': true,
          'updatedAt': FieldValue.serverTimestamp(),
        });
        
        if (_userData != null) {
          _userData!['verified'] = true;
        }
        
        debugPrint('Verification status synced to Firestore');
        notifyListeners();
      }
    } catch (e) {
      debugPrint('Error syncing verification status: $e');
    }
  }
}
