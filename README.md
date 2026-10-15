# Tripometer

A speedometer, trip recorder and fuel log for Android (and iOS), built with
[Laravel](https://laravel.com) and [NativePHP Mobile v4](https://nativephp.com/docs/mobile).
Every screen is real native UI (Jetpack Compose on Android, SwiftUI on iOS)
driven from PHP. All data stays on the phone in SQLite.

App ID: `com.ghani.tripometer`

## Features

**Speed**
- Live GPS speed as soon as the app opens, no trip needed
- Trip meter (like a car's "trip B"): distance and top speed, with Reset
- Posted road speed limit from OpenStreetMap, shown as a road sign
- Over-limit alert by vibration, sound or both, for the road limit and/or
  your own limit
- HUD mode: full screen, extra-large speed, distance, time and road limit
- Now playing: see and control Spotify, YouTube Music or any music app
  (Android)
- Screen stays awake while the app is open

**Trips**
- Record trips in the background, screen off included. On Android a
  notification shows live speed and distance.
- Trip list and details (distance, duration, average and top speed),
  with title, notes and delete
- Daily (14 days) and monthly (12 months) distance charts

**Fuel**
- Log fill-ups: date, odometer, litres, cost, full tank or partial
- Fuel average (km/L, L/100 km) using the full-tank-to-full-tank method,
  plus cost per km and total spent
- Price per litre for each fill-up, latest and average price, and a
  12-month price chart
- Edit and delete fill-ups

**Settings:** km/h or mph, alert type, road-limit alerts on or off, and a
personal speed limit. Changes are saved with the Save button.

## Platform notes

| | Android | iOS |
|---|---|---|
| Live speed, trips, fuel, HUD | Yes | Yes |
| Background trip recording | Foreground service + notification | Background location mode |
| Live speed notification | Yes | No (iOS has no equivalent without Live Activities) |
| Now playing / music controls | Yes, after granting "Notification access" | No, iOS doesn't expose other apps' playback |
| Road speed limit | Needs internet | Needs internet |

## How it's built

```
app/
  NativeComponents/   Screens: Speedometer, TripList, TripDetail, Stats,
                      FuelLog, FuelFillForm, Settings
  Layouts/TabsLayout  Bottom tabs and nav bar (hidden in HUD mode)
  Models/             Trip, TripPoint, FuelFill, Setting
  Support/TripMeter   Resettable trip meter, stored in the cache
  Tasks/              LookUpSpeedLimit: OpenStreetMap lookup on a background thread
packages/app/speed-tracker/
                      Local NativePHP plugin with the native Kotlin/Swift code
routes/mobile.php     Screen routes
```

**`speed-tracker` plugin.** Core NativePHP ships the PHP side of
`Geolocation` (facade, events, background-watch API), but the native side
is a paid plugin. This local plugin implements those native functions for
free, plus a few extras:

| Bridge function | What it does |
|---|---|
| `Geolocation.WatchPosition` / `ClearWatch` | Foreground GPS for live speed |
| `Geolocation.StartBackgroundWatch` / `StopBackgroundWatch` | Trip recording (Android foreground service, iOS background location) |
| `Geolocation.DrainWatchBuffer` / `TrimWatchBuffer` | Buffered fixes, so none are lost while PHP isn't listening |
| `SpeedTracker.SetUnit` | km/h or mph for the Android notification |
| `SpeedTracker.PlayAlert` | Alert beep |
| `SpeedTracker.KeepScreenOn` | Keep the screen awake |
| `SpeedTracker.NowPlaying` / `MediaCommand` / `OpenMediaAccessSettings` | Music info and controls via Android media sessions |

Native code only compiles in at build time, so rebuild after changing the plugin.

**Fuel history.** The `seed_fuel_fills` migration imports a 65-entry fuel
history (`database/seeders/FuelFillSeeder.php`). NativePHP runs migrations on
the device; there is no `db:seed`. The seeder skips odometer readings that
already exist, so it never duplicates entries.

## Development

Requirements: PHP 8.4, Composer, and for device builds the Android SDK with
JDK 17 (Android builds work on macOS, Windows or Linux, not WSL). iOS builds
need macOS with Xcode.

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test --compact
```

Run on a device or emulator (from Windows/macOS, not WSL):

```sh
php artisan native:run android          # or: ios
php artisan native:run android --watch  # with hot reload
```

## Releases (GitHub Actions)

`.github/workflows/android-release.yml` builds a signed release APK on GitHub,
so no local Android tooling is needed.

- Push a tag `v1.2.0` to get `Tripometer-1.2.0.apk` attached to a GitHub Release.
- Or run the workflow manually from the Actions tab to get the APK as a run artifact.

**One-time setup.** Create a signing key and add four repository secrets:

```sh
keytool -genkeypair -v -keystore ~/tripometer-release.jks -alias tripometer \
  -keyalg RSA -keysize 2048 -validity 10000

base64 -w0 ~/tripometer-release.jks | gh secret set ANDROID_KEYSTORE_BASE64
gh secret set ANDROID_KEYSTORE_PASSWORD
gh secret set ANDROID_KEY_PASSWORD
gh secret set ANDROID_KEY_ALIAS --body tripometer
```

Back up the keystore and its passwords. Android only installs updates signed
with the same key.

Release:

```sh
git tag v1.0.0 && git push origin v1.0.0
```

The version code is the workflow run number, so it always increases.

## Known limitations

- The trip meter only counts while the Speed tab is open. Recorded trips are
  unaffected.
- Every fill-up from the imported history counts as a full tank.
- Fuel is tracked in litres and km/L only, even when the app shows mph.
- Road limits from OpenStreetMap can be missing or out of date. At junctions,
  the limit shown may belong to the cross street.
- If the app is force-killed mid-trip, GPS fixes not yet saved are lost.
