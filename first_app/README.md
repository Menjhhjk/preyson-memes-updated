# PreySON

Local meme community demo built with Laravel 13, PHP 8.4, and MySQL 8.

## Run on this computer

From `first_app`, run:

```powershell
.\Start-PreySON.cmd
```

This starts three local services in hidden windows when they are not already running:

| Service                      | Address                                    |
| ---------------------------- | ------------------------------------------ |
| PreySON                      | http://127.0.0.1:8000                      |
| XAMPP phpMyAdmin             | http://127.0.0.1:8081                      |
| Dedicated MySQL 8.0 instance | `127.0.0.1:3308`, database `preyson_memes` |

Admin login: **admin@gmail.com** / **pass@123**. Complete the color check to sign in. Email addresses only identify demo accounts; registration never requires a real mailbox or email verification.

The existing system MySQL service on port 3306 is unchanged. The project instance uses the installed MySQL 8.0 executable and keeps its data in ignored `storage/mysql/data`. Database credentials are saved in the ignored `.env`; the project instance's generated root password is in ignored `storage/mysql/root-password.txt`. The app account `preyson` is limited to its database. phpMyAdmin has a separate “PreySON MySQL (3308)” entry using that account. The startup script uses Herd PHP 8.4 for Laravel and XAMPP PHP 8.2 for phpMyAdmin; it does not need Apache to serve these local URLs.

The original SQLite data was cleared too. It contains only the preserved administrator and is no longer the application's database. Uploaded media belonging to the old posts was removed.

## Features and rules

- Accounts use a username and email; first names and surnames are no longer collected or stored. New or changed usernames require 6–50 characters. Existing shorter usernames can be retained so current logins continue to work.
- A failed registration displays all field, consent, and CAPTCHA errors together. The form keeps the username/email and issues a fresh one-use CAPTCHA after a failed attempt.

- Registration and login have a server-checked 3×3 color challenge: 2–6 matching squares, distinguishable mixed/distant colors, ten-minute expiry, one-use answers, refresh, keyboard controls, and optional color names. This is an accessible classroom demo challenge, not a production bot-prevention service.
- Registration requires agreement to the readable demo terms/privacy notice and stores its version and acceptance time.
- Members can edit their profile, upload/remove a profile picture, change their password, and delete their account using their current password. Administrators cannot delete themselves.
- Free accounts can keep **6 posts**, Premium accounts **30**. Each upload accepts up to 6 files totaling 100 MB. Administrators have unlimited posts and ZIP import. GIFs are a separate category.
- Premium checkout and donations collect **only a name**. Card/address details are decorative, crossed out, and never submitted. Premium lasts exactly 30 days without automatic renewal or stacking. On expiry, existing posts remain; accounts above the free limit cannot add more until they reduce their count or renew.
- Premium adds six extra reactions and an animated owner-name gradient (animation respects reduced-motion preferences).
- Administrators can create, search, read, edit, delete, and assign roles to accounts. Moderators can manage community posts but cannot manage accounts. The main administrator's email and role are protected.
- The feed displays owner/avatar/date, provides title search, media filtering, newest/oldest/reaction sorting, and shuffles by default. Pagination retains the shuffle seed. Owners and moderators can edit directly in the feed.
- One reaction per account/post; click again to remove it, or another emoji to change it. Videos pause when less than 25% visible, when another video starts, or when the page is hidden.
- Supporting protections include CSRF, login throttling, case-insensitive account uniqueness, password hashing, revoked sessions after credential/role changes, media cleanup, bounded MIME-checked ZIP imports, upload rollback, and server-enforced roles/quotas.

## Reset the demo

This deliberately removes all posts, reactions, other accounts, and their media, and restores the documented administrator login. It preserves the main admin's existing ID where possible and refuses to run in production:

```powershell
& "$env:USERPROFILE\.config\herd\bin\php84\php.exe" artisan preyson:reset-demo --force
```

## Other MySQL installations

PHP 8.3+ is required by Laravel 13. Use PHP with PDO MySQL, fileinfo, mbstring, GD, and ZIP enabled. The local bundled XAMPP PHP 8.2 is used for phpMyAdmin only. See the [Laravel release requirements](https://laravel.com/docs/13.x/releases) and [database configuration documentation](https://laravel.com/docs/13.x/database).

Copy `.env.example` to `.env`, set a new `APP_KEY` with `php artisan key:generate`, and set your MySQL host, port, username, and password. Then:

```sh
composer install
php scripts/setup-mysql.php
php artisan storage:link
npm ci
npm run build
php -d upload_max_filesize=100M -d post_max_size=110M artisan serve
```

`setup-mysql.php` creates the configured schema, runs migrations, and seeds a new administrator if absent. Pass `--reset` only when you want the destructive demo reset. If using an existing phpMyAdmin installation, connect it to the same MySQL host/port. The sample `.env.example` uses conventional MySQL port 3306; this computer's dedicated instance uses 3308.

For a new dedicated Windows instance like this one, initialize an empty `storage/mysql/data` using the installed `mysqld --no-defaults --initialize-insecure --datadir=...`, start it bound to `127.0.0.1:3308`, and run `php scripts/bootstrap-local-mysql.php` once. This one-time script checks that the server's data directory is inside the project, creates random database passwords, and adds the XAMPP phpMyAdmin entry. Then run `php scripts/setup-mysql.php`. Never initialize over existing database files.

## Verification

```sh
php artisan test
php vendor/bin/phpstan analyse --memory-limit=512M
php vendor/bin/pint --dirty --test
npm run types:check
npm run build
php scripts/verify-database.php
```

Tests normally use isolated, in-memory SQLite. The full suite was also exercised on MySQL 8.0.44 using a separate `preyson_memes_test` database; never point RefreshDatabase tests at the application database. `scripts/mysql-test-database.php` prepares that temporary local schema and `--drop` removes it after verification.
