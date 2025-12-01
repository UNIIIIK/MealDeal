# Firebase Email Verification Setup

## Issue
If verification emails redirect to `localhost:8000/verify_simple.php` and show `ERR_CONNECTION_REFUSED`, this means Firebase Console has a custom action handler URL configured.

## Solution: Use Firebase's Default Verification Handler

### Step 1: Remove Custom Action Handler in Firebase Console ⚠️ CRITICAL

**This is the most important step!** If verification emails still contain `localhost:8000`, you MUST do this:

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Select your project (`mealdeal-10385`)
3. Navigate to **Authentication** → **Templates**
4. Click on **Email address verification** template
5. Look for **Action URL** or **Custom action handler URL** field
6. **Remove or clear** any URL pointing to `localhost:8000` or `verify_simple.php`
   - If you see: `http://localhost:8000/verify_simple.php` → **DELETE IT**
   - If you see any custom URL → **DELETE IT**
7. **Leave the field completely empty** to use Firebase's default handler
8. Click **Save**
9. **Wait 1-2 minutes** for changes to propagate
10. Try registering a new user or resending verification email

**Note:** Even if your Flutter code is correct (which it is), Firebase Console settings override the code. You MUST remove the custom URL from Console.

### Step 2: Verify App Code (Already Done ✅)

The Flutter app is already configured to use Firebase's default verification:
- `sendEmailVerification()` is called **without** any `ActionCodeSettings` parameters
- This means Firebase will use its built-in verification page
- No server-side PHP handler is needed

### Step 3: How It Works

1. **User registers** → Firebase sends verification email with default handler link
2. **User clicks link** → Opens Firebase's built-in verification page (works on any device/network)
3. **User returns to app** → Taps "I already verified" → App checks `user.emailVerified`
4. **If verified** → User gains full app access

### Step 4: Testing

1. Register a new user
2. Check email for verification link (should NOT contain `localhost:8000`)
3. Click the link (should open Firebase's default verification page)
4. Return to app and tap "I already verified"
5. App should detect verification and grant access

## Code Reference

The app uses Firebase's default verification in these locations:

- `lib/features/auth/auth_service.dart`:
  - `registerWithEmailAndPassword()` - Line 157
  - `resendEmailVerification()` - Line 243

Both call `sendEmailVerification()` without parameters, using Firebase defaults.

## Troubleshooting

**If verification links still point to localhost:**
- Double-check Firebase Console → Authentication → Templates
- Make sure no custom action handler URL is set
- Try creating a new verification email after clearing the setting

**If "I already verified" doesn't work:**
- Ensure user clicked the verification link in email
- Wait a few seconds after clicking link
- Tap "Refresh status" button to force reload
- Check Firebase Console → Authentication → Users to confirm email is verified

