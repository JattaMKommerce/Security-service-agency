import java.util.Properties

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

// Website the app opens. Override with: ./gradlew assembleRelease -PbaseUrl=https://example.com
val baseUrl = (project.findProperty("baseUrl") as String?) ?: "https://security.jattamkommerce.com"

// Signing key details are kept OUTSIDE the repo (the repo is public).
// android/keystore.properties (git-ignored) points to them:
//   storeFile=/Users/<you>/Documents/Jatta-Security-Signing-Key/jatta-security-app.jks
//   storePassword=...   keyAlias=jatta   keyPassword=...
val keystoreProps = Properties().apply {
    val f = rootProject.file("keystore.properties")
    if (f.exists()) f.inputStream().use { load(it) }
}

android {
    namespace = "com.jattamkommerce.securityapp"
    compileSdk = 35

    defaultConfig {
        // Same ID as the old guard app, so it installs as an UPDATE (no uninstall).
        // The release is signed with the new key plus a key-rotation proof from the old key (see README).
        applicationId = "com.jattamkommerce.security.guard"
        minSdk = 24
        targetSdk = 35
        versionCode = 1
        versionName = "1.0.0"
        buildConfigField("String", "BASE_URL", "\"$baseUrl\"")
    }

    signingConfigs {
        create("release") {
            if (keystoreProps.getProperty("storeFile") != null) {
                storeFile = file(keystoreProps.getProperty("storeFile"))
                storePassword = keystoreProps.getProperty("storePassword")
                keyAlias = keystoreProps.getProperty("keyAlias")
                keyPassword = keystoreProps.getProperty("keyPassword")
            }
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            signingConfig = signingConfigs.getByName("release")
        }
    }

    buildFeatures { buildConfig = true }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions { jvmTarget = "17" }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("androidx.activity:activity-ktx:1.9.3")
    implementation("com.google.android.gms:play-services-location:21.3.0")
    implementation(platform("com.google.firebase:firebase-bom:33.7.0"))
    implementation("com.google.firebase:firebase-messaging")
}
