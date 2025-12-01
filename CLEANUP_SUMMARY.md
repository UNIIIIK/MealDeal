# Folder Cleanup Summary

## Issues Identified

1. **Firestore Invalid-Argument Error** (FIXED ✅)
   - Problem: Nested maps in provider registration extraData weren't being properly converted for Firestore
   - Solution: Added recursive `_mergeExtraData()` function to properly handle nested structures

2. **Duplicate Root-Level Platform Folders** (TO BE REMOVED)
   - `android/` - Contains only auto-generated Flutter files (duplicate of flutter_application_1/android/)
   - `ios/` - Contains only auto-generated Flutter files (duplicate of flutter_application_1/ios/)
   - `linux/` - Contains only ephemeral Flutter files (duplicate of flutter_application_1/linux/)
   - `macos/` - Contains only ephemeral Flutter files (duplicate of flutter_application_1/macos/)
   - `windows/` - Contains only ephemeral Flutter files (duplicate of flutter_application_1/windows/)

3. **Loose PHP Files in flutter_application_1/** (TO BE ORGANIZED)
   - `verify_simple.php` - Legacy verification file (duplicate/old version)
   - `verify_user.php` - Legacy verification file (duplicate/old version)
   - Proper versions exist in `flutter_application_1/php_auth/public/`

4. **Build Folder** (CAN BE CLEANED)
   - `flutter_application_1/build/` - Auto-generated build artifacts (can be regenerated with `flutter build`)

5. **Backups Folder** (TO BE ORGANIZED)
   - `flutter_application_1/backups/` - Contains backup .bak files

## Actions Taken

### Fixed
- ✅ Firestore nested map handling in `auth_service.dart`
- ✅ Added recursive data merging function to properly handle nested maps in extraData

### Cleaned Up
- ✅ Removed duplicate root-level platform folders (android, ios, linux, macos, windows)
- ✅ Moved loose PHP files (`verify_simple.php`, `verify_user.php`) to `flutter_application_1/legacy/`
- ✅ Backups folder kept as-is (contains useful backup files)

## Final Folder Structure

```
MealDeal/
├── flutter_application_1/     (Main Flutter app)
│   ├── android/              (Platform-specific)
│   ├── ios/                  (Platform-specific)
│   ├── lib/                  (Source code)
│   ├── backend/              (PHP backend)
│   ├── php_auth/             (PHP auth system)
│   ├── legacy/               (Legacy/old files)
│   ├── backups/              (Backup files)
│   └── build/                (Auto-generated - can be cleaned with `flutter clean`)
├── web_admin/                (Web admin dashboard)
└── README.md
```

## Notes

- **Build folder**: The `flutter_application_1/build/` folder contains auto-generated files. You can clean it with `flutter clean` if needed, but it will regenerate when you build.
- **Legacy folder**: Contains old PHP verification files that are no longer used (Firebase uses default verification handler now).

