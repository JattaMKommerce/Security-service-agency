# Security Agency Management System

Bodyguard management with live GPS tracking on OpenStreetMap. It has an admin portal for the control room and a mobile web app for guards.

- **Admin portal** (`/login.php`): dashboard, live tracking map, bodyguards, clients, hiring/deployments ("Hired by <client>"), attendance, SOS and geofence alerts, invoices, settings.
- **Guard app** (`/guard/`): guards sign in on their phone, start and end duty with GPS, share their live location while on duty, and use a press-and-hold SOS button. It can be installed on the phone's home screen.

Built with PHP 8.1+, MySQL/MariaDB, Bootstrap 5, Leaflet and OpenStreetMap. It needs no Composer or Node, so it runs on any Hostinger plan.

---

## 1. Deploy on Hostinger

1. **PHP version**: in hPanel, go to **Advanced → PHP Configuration** and select **PHP 8.1 or newer** (8.2/8.3 recommended).
2. **SSL**: go to **Security → SSL** and install the free SSL. **Phones only share GPS over `https://`.**
3. **Database**: go to **Databases → MySQL Databases** and create a database and user. Hostinger names them like `u123456789_agency`.
4. **Upload**: upload all project files into `public_html` (or a sub-folder) with File Manager or FTP. Keep the `.htaccess` files; they protect the private folders.
5. **Configure**: edit `config/config.php` and enter the Hostinger database name, user and password.
   > Hostinger does **not** allow the `root` user. The `root / root123` values only work on your local computer.
6. **Folder permissions**: `storage/` and `uploads/guards/` must be writable (755 is normally fine on Hostinger).
7. **Install**: open `https://your-domain.com/install.php`, enter the company name and your admin login, then click **Install**.
8. **Delete `install.php`** from the server after installing. It is also locked automatically.
9. *(Optional)* Go to **Advanced → Cron Jobs** and run every 15 minutes:
   `/usr/bin/php /home/<your-user>/public_html/cron.php`
   This step is optional because the app runs its cleanup automatically while in use.

## 2. Daily use

| Task | Where |
|---|---|
| Add a bodyguard (gets an app login) | Bodyguards → Add bodyguard |
| Add a client | Clients → Add client |
| Hire guards out to a client (one or many at once) | Hiring / Deployments → Hire bodyguards, pick the site on the map and set the geofence radius |
| See who is where, and who hired them | Live Tracking (refreshes every 10 s) |
| A guard's route for any day | Bodyguard profile → Location & route |
| Respond to SOS / left-site alerts | Bell icon (sound + popup) → Alerts |
| Bill a client | Invoices → New invoice → **Auto-fill lines** from deployments/attendance |
| Release a guard | Deployment → End deployment |

**Guard login:** employee code (e.g. `GRD-0001`) or phone number, plus the password set by the admin.
Ask guards to open `https://your-domain.com/guard/` in Chrome, allow location, and use **Add to Home screen**.

## 3. How tracking works (please read)

- While a guard is **on duty**, the phone sends its GPS position every 20 s (configurable in Settings → Tracking).
- If the internet drops, positions are saved on the phone and sent automatically when the connection returns.
- **Geofence:** if an on-duty guard goes outside the client site's radius, a *Left site* alert is raised. It resolves automatically when they return.
- **Check-in rule:** a guard can only start duty within 500 m of the site (configurable).
- **Browser limitation:** a web app can only send GPS while it is **open on screen**. The app keeps the screen awake during duty. If the guard locks the phone or switches apps, updates pause and the admin map shows **"On Duty (No Signal)"** after 3 minutes, so a gap is always visible. Fully continuous background tracking with the screen off needs a native Android app.
- Route history is kept for 60 days (configurable) to keep the database small on shared hosting.

## 4. Demo data (optional, for testing)

From a terminal (or Hostinger SSH):

```
php database/seed_demo.php          # adds 40 demo guards + 6 Mumbai clients (password: guard123)
php database/seed_demo.php --remove # removes all demo data again
```

## 5. Security built in

- Passwords hashed with bcrypt. Login lockout after 5 failed attempts (15 min).
- CSRF tokens on every form and API call. All SQL uses prepared statements. All output is HTML-escaped.
- Session cookies are HttpOnly and SameSite. Admins are logged out after 4 h idle.
- A guard stays signed in on one phone for 30 days. Logging in on another phone, or a password reset, signs out the old phone.
- `config/`, `includes/`, `database/` and `storage/` cannot be opened from the web. Uploads can't execute scripts.
- Only one active deployment per guard, enforced by the database itself.
- An activity log of every important action is in Settings → Activity log.

## 6. Folder layout

```
admin/        admin portal pages
guard/        guard mobile app (PWA)
api/admin/    JSON endpoints for live map, alerts, routes, invoice auto-fill
api/guard/    JSON endpoints for location, check-in/out, SOS
includes/     core PHP (db, auth, helpers, geo/tracking, billing, layout)
assets/       CSS, JS, icons
database/     schema.sql, demo seeder
config/       config.php (database settings)
storage/      error log, install lock (private)
uploads/      guard photos
```
