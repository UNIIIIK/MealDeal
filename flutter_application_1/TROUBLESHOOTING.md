# Troubleshooting Guide

## Issue: "Unexpected error" during Registration

If you see "Unexpected error" when registering:

1. **Check the debug console** - The actual error message is now logged there
2. **Common causes:**
   - Network connectivity issues
   - Firestore permissions not set correctly
   - Firebase project configuration issues
   - Email already in use (should show specific message)

3. **To see the actual error:**
   - Open Flutter DevTools or check your terminal/console
   - Look for lines starting with "Unexpected error during registration:"
   - The error message will show the root cause

4. **Fix based on error:**
   - **Network error**: Check internet connection
   - **Permission denied**: Check Firestore security rules
   - **Email already in use**: Use a different email or sign in instead

## Issue: Verification Email Still Contains localhost:8000

**This is a Firebase Console setting issue, NOT a code issue.**

### Symptoms:
- Verification email link contains: `http://localhost:8000/verify_simple.php`
- Clicking the link shows: `ERR_CONNECTION_REFUSED`

### Solution:

1. **Go to Firebase Console:**
   - https://console.firebase.google.com/
   - Select project: `mealdeal-10385`
   - Navigate to: **Authentication** → **Templates**

2. **Edit Email Verification Template:**
   - Click on **"Email address verification"** template
   - Find **"Action URL"** or **"Custom action handler URL"** field
   - **DELETE** any URL containing `localhost:8000` or `verify_simple.php`
   - **Leave the field EMPTY**
   - Click **Save**

3. **Wait and Test:**
   - Wait 1-2 minutes for changes to propagate
   - Register a new user or resend verification email
   - Check the email - link should NOT contain localhost

4. **If still not working:**
   - Double-check Firebase Console settings
   - Try clearing browser cache
   - Wait a few more minutes (Firebase can take time to update)

### Why This Happens:

Firebase Console has a setting that overrides the default verification handler. Even though our Flutter code correctly uses Firebase's default handler (no custom URLs), the Console setting takes precedence.

**The Flutter code is correct** - the issue is in Firebase Console configuration.

## Issue: Registration Shows "Unexpected error" but User is Created

If registration shows an error but the user account is actually created:

1. **Check Firestore:**
   - Go to Firebase Console → Firestore Database
   - Check if user document exists in `users` collection
   - If yes, the registration partially succeeded

2. **Possible causes:**
   - Email verification send failed (non-critical)
   - Firestore write succeeded but response timed out
   - Network interruption during registration

3. **Solution:**
   - Try logging in with the credentials
   - If login works, resend verification email from the app
   - Check debug console for specific error details

## Debugging Tips

### Enable Verbose Logging:

In `auth_service.dart`, errors are now logged with:
- `debugPrint('Unexpected error during registration: $e')`
- `debugPrint('Stack trace: $stackTrace')`

Check your Flutter console/terminal for these messages.

### Check Firebase Console:

1. **Authentication → Users:**
   - Verify user was created
   - Check email verification status

2. **Firestore Database:**
   - Check `users` collection
   - Verify user document exists with correct data

3. **Authentication → Templates:**
   - Verify no custom action URLs are set
   - Check email template content

### Test Verification Flow:

1. Register new user
2. Check email (should NOT have localhost in link)
3. Click verification link
4. Return to app
5. Tap "I already verified"
6. App should detect verification and grant access

