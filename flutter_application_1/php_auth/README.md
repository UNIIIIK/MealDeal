# MealDeal - Email Verification System

This directory contains the PHP backend for handling email verification in the MealDeal application.

## Features

- User registration with email verification
- Secure email verification links with expiration
- Firestore integration for user data
- Responsive email templates
- Error handling and user feedback

## Setup

1. **Prerequisites**
   - PHP 7.4 or higher
   - Composer
   - Firebase project with Authentication and Firestore enabled
   - Gmail account for sending verification emails

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Configuration**
   - Copy `config/config.example.php` to `config/config.php`
   - Update the configuration with your Firebase credentials and SMTP settings
   - Make sure to set the correct `base_url` in the config

4. **Firebase Setup**
   - Enable Email/Password authentication in Firebase Console
   - Download your service account key and save it as `firebase-credentials.json` in the project root
   - Set up Firestore with a `users` collection

5. **SMTP Configuration**
   - The system uses PHPMailer with Gmail SMTP by default
   - For Gmail, you'll need to:
     1. Enable 2-factor authentication on your Google account
     2. Create an App Password for your application
     3. Use the app password in the SMTP configuration

## File Structure

- `public/` - Publicly accessible files
  - `signup.php` - User registration page
  - `login.php` - User login page
  - `verify_simple.php` - Handles email verification links
  - `verification-success.php` - Shown after successful verification
  - `verification-error.php` - Shown if verification fails
- `src/` - Source files
  - `AuthHandler.php` - Main authentication class
- `config/` - Configuration files
  - `config.php` - Application configuration

## Email Verification Flow

1. User signs up through `signup.php`
2. System creates user in Firebase Auth (unverified)
3. Verification email is sent with a secure link
4. User clicks the verification link
5. `verify_simple.php` processes the verification:
   - Validates the verification code
   - Updates user's email verification status in Auth
   - Updates the `verified` field in Firestore
   - Redirects to success/error page

## Security Notes

- Verification links expire after 1 hour
- All sensitive operations require valid Firebase tokens
- Error messages are generic to prevent information leakage
- Input is properly sanitized and validated
- Passwords are hashed by Firebase Auth

## Troubleshooting

- **Emails not sending**: Check SMTP settings and Gmail app password
- **Verification link not working**: Ensure `base_url` is correctly set in config
- **Firebase errors**: Verify service account credentials and Firebase project settings

## License

This project is part of the MealDeal application.
