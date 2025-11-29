# Building APK for User Acceptance Testing (UAT)

## Quick Build (For Testing)

### Option 1: Build Release APK (Recommended for UAT)
```bash
cd flutter_application_1
flutter clean
flutter build apk --release
```

The APK will be located at:
`build/app/outputs/flutter-apk/app-release.apk`

### Option 2: Build Split APKs (Smaller file size, but requires installing all)
```bash
flutter build apk --split-per-abi --release
```

This creates separate APKs for different architectures:
- `build/app/outputs/flutter-apk/app-armeabi-v7a-release.apk` (32-bit ARM)
- `build/app/outputs/flutter-apk/app-arm64-v8a-release.apk` (64-bit ARM - most common)
- `build/app/outputs/flutter-apk/app-x86_64-release.apk` (64-bit x86)

**For UAT, use Option 1** (single APK) - it's simpler to distribute.

## Sharing the APK

1. **File Size**: The APK will be around 30-50 MB
2. **Distribution Methods**:
   - Upload to Google Drive / Dropbox / OneDrive
   - Share via email (if file size allows)
   - Use a file sharing service
   - Host on a private server

3. **Installation Instructions for Testers**:
   - Enable "Install from Unknown Sources" on Android device
   - Settings → Security → Unknown Sources (enable)
   - Download the APK
   - Tap to install
   - Follow installation prompts

## Important Notes

### Current Configuration
- The app is currently signed with **debug keys** (fine for testing)
- For production release, you'll need to create a proper signing key

### For Production Release (Later)
When ready for production, you'll need to:
1. Generate a keystore file
2. Configure signing in `android/app/build.gradle`
3. Build with: `flutter build appbundle` (for Google Play Store)

## Troubleshooting

If build fails:
1. Ensure all dependencies are installed: `flutter pub get`
2. Check Android SDK is properly configured
3. Try: `flutter doctor` to verify setup
4. Clean and rebuild: `flutter clean && flutter build apk --release`

## Version Information
- Current version: 1.0.0+1 (from pubspec.yaml)
- To update version, edit `pubspec.yaml`:
  ```yaml
  version: 1.0.1+2  # version_name+build_number
  ```

