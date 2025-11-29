# Hot Reload vs Hot Restart Guide

## When Hot Reload Works ✅
- Changes to widget `build()` methods
- Changes to UI code
- Changes to state management logic (setState, etc.)
- Changes to most Dart code

## When Hot Restart is Required 🔄
- Changes to `main()` function
- Changes to top-level initialization code
- Changes to static variables
- Changes to `const` constructors that affect app structure
- Changes to Firebase initialization
- Changes to provider setup

## Quick Commands

### Hot Reload (Quick - doesn't restart app)
- Press `r` in terminal
- Or click the 🔥 icon in VS Code/Android Studio
- Or use `Ctrl+S` (if auto-save is enabled)

### Hot Restart (Full restart - required for main() changes)
- Press `R` (capital R) in terminal
- Or click the 🔄 icon in VS Code/Android Studio
- Or use `Ctrl+Shift+F5` in VS Code

### Full Restart (Complete rebuild)
- Press `Ctrl+C` to stop
- Run `flutter run` again
- Or use `flutter run --hot` for hot restart

## For Your Recent Changes

Since we modified:
- `main()` function
- `FirestoreHelper.configureFirestore()` initialization
- Navigation structure

**You MUST use Hot Restart (R) not Hot Reload (r)**

## How to Tell if You Need Restart

If you see "Reloaded X libraries" but changes don't appear:
1. Try Hot Restart (R)
2. If still not working, do Full Restart
3. Check console for errors

