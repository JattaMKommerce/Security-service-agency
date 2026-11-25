# Jatta Security — Android app

One app for guards and admins. It opens the website (`https://security.jattamkommerce.com`) and adds
what a website cannot do on Android:

- **Background duty tracking**: after *Start Duty* a permanent "On duty — location sharing ON"
  notification appears and GPS keeps going to the control room with the screen off, while using other
  apps, and after a reboot (with "Allow all the time"). Points are saved on the phone without internet
  and sent later. It stops on *End Duty*, a handover, logout, or when the control room ends the shift.
- **Phone events**: tells the control room when the phone is being switched off, the battery is low,
  or GPS is turned off.
- **Push notifications** (Firebase Cloud Messaging): admins get SOS (siren, also on a locked phone),
  alerts and requests like WhatsApp messages with a count on the app icon; guards get approvals and handovers.

All screens stay on the website, so website updates reach phones without a new APK.
A new APK is only needed when this native part changes.

## Build

Needs Java 17 and the Android SDK (`~/Library/Android/sdk`).

```bash
cd android
./gradlew assembleRelease          # → app/build/outputs/apk/release/app-release.apk
```

Upload the APK to the website as `downloads/apk/Jatta-Security.apk`; the guard install page
(`guard/install.php`) switches to it automatically.

## Updating the old app without uninstalling (key rotation)

The app uses the old guard app's ID (`com.jattamkommerce.security.guard`), so it installs as an update.
The old signing key was published on GitHub, so releases are signed with a **new key** together with a
one-time rotation proof (APK Signature Scheme v3 lineage) signed by the old key. Phones on Android 9+
accept the update and from then on trust the new key; the old key is not allowed to sign further
updates. Phones on Android 8 or older must uninstall the old app once.

## Signing key (keep it private)

The GitHub repository is **public**, so the key and its passwords are kept **outside the repo** in
`~/Documents/Jatta-Security-Signing-Key/`. `android/keystore.properties` (git-ignored) points to it:

```
storeFile=/Users/<you>/Documents/Jatta-Security-Signing-Key/jatta-security-app.jks
storePassword=…
keyAlias=jatta
keyPassword=…
```

Back up that folder. Every future update must be signed with the same key, or phones refuse it.

## Firebase (push notifications)

No Firebase file is built into the app. The app reads the public Firebase values from
`/api/app/config.php`, which come from **Admin → Settings → Notifications**, where the private
service-account key is also uploaded (stored in `storage/`, never in git).

## Releasing an update

1. Increase `versionCode` (and `versionName`) in `app/build.gradle.kts`.
2. Build, then upload as `downloads/apk/Jatta-Security.apk`.
3. Guards install it over the old one (same key → their data and login stay).
