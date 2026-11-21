# Guard Mobile App (.apk) Building & Onboarding Guide

This document explains how guards can install the mobile app on their phones, and how agency administrators can generate a standalone `.apk` package for Google Play Store or offline sideloading.

---

## 1. Fast Onboarding: 1-Tap WebAPK (No APK File Needed)
The fastest and most reliable way for 100–200 guards to install the app is via **Progressive Web App (PWA) / WebAPK**:

1. **Supervisor action in Admin Portal:**
   - Go to `Admin Portal` → `Guards` → Click any Guard.
   - Click the green **"Send App & Login via WhatsApp"** button (or show them the on-screen QR Code).
2. **Guard action on their phone:**
   - Guard taps the WhatsApp link on their Android phone (opens in Google Chrome).
   - Chrome automatically shows the **"Install App"** prompt, or the guard taps **"Install App on Phone"**.
   - Android instantly packages and installs the **Guard Duty App** to their phone's home screen.
   - Guard opens the app from their home screen, enters their Badge ID (e.g. `SEC-001`) and PIN, and taps **"Start Duty"**.
3. **Benefits:**
   - **Zero security warnings:** Does not require guards to enable "Allow installation from unknown sources".
   - **Instant automatic updates:** When you update features or assignments on Hostinger, the app updates automatically on guards' phones without manual re-installation.
   - **Full native capabilities:** Standalone fullscreen mode (no address bar), background GPS tracking, and SOS dispatch.

---

## 2. Generating a Standalone `.apk` File (For Direct Sideloading)
If your agency requires a raw `.apk` file to distribute via USB, WhatsApp attachments, or Mobile Device Management (MDM):

### Option A: 60-Second Web Packaging with PWABuilder (Recommended)
1. Go to **[https://www.pwabuilder.com](https://www.pwabuilder.com)** (free service by Microsoft/Google).
2. Enter your Hostinger website Guard URL (e.g., `https://your-domain.com/guard/`).
3. Click **"Start"**. PWABuilder reads `guard/manifest.php` and verifies 100% compliance.
4. Click **"Package for Stores"** → choose **"Android"**.
5. Click **"Download APK"** or **"Generate Package"**.
6. Place the resulting `guard-duty-app.apk` into your website's `/downloads/` directory:
   ```
   https://your-domain.com/downloads/guard-duty-app.apk
   ```
7. Any guard can now click the "Download Guard APK" button on `https://your-domain.com/guard/install.php`.

### Option B: Using Google's Bubblewrap CLI (Command Line)
If you prefer building locally via terminal:
```bash
# 1. Install Bubblewrap CLI
npm install -g @bubblewrap/cli

# 2. Initialize project from your live domain
bubblewrap init --manifest="https://your-domain.com/guard/manifest.php"

# 3. Build the signed APK
bubblewrap build

# 4. Copy the output APK into downloads/
cp app-release-signed.apk /path/to/security-agency/downloads/guard-duty-app.apk
```

---

## 3. How Guards Install the Raw `.apk` File on Android
1. Download the `.apk` file to the Android device.
2. If Android prompts *"File might be harmful"*, tap **"Download anyway"**.
3. Open the downloaded file from the Downloads folder or notification bar.
4. If prompted *"For security, your phone is not allowed to install unknown apps from this source"*:
   - Tap **Settings**.
   - Enable **"Allow from this source"**.
   - Tap the Back button.
5. Tap **"Install"**.
6. Launch the app and log in!

---

## 4. iPhone (iOS Safari) Installation
1. Open `https://your-domain.com/guard/` in **Safari**.
2. Tap the **Share icon** (`⎋` box with arrow pointing up).
3. Scroll down and tap **"Add to Home Screen"** (`➕`).
4. Tap **"Add"** in the top right.
