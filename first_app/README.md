# PreySON

Local meme community demo built with Laravel 13, PHP 8.4, and MySQL 8.

## Centralized setup (shared hosted MySQL + Cloudflare R2)

The team can share one database and one media store instead of keeping different copies on every computer:

- **Database**: set `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_DATABASE` in `.env` to one hosted MySQL instance (the demo uses Aiven's free MySQL tier — no credit card). Set `MYSQL_ATTR_SSL_CA` to the CA certificate downloaded from the provider console; a project-relative path such as `certs/aiven-mysql-ca.pem` works. When `DB_HOST` is not local, `Start-PreySON.ps1` skips the bundled MySQL 3308 instance and phpMyAdmin entirely.
- **Media**: set `STORAGE_DRIVER=s3` plus the `AWS_*`/`R2_*` variables in `.env` (Cloudflare R2 S3 credentials, account endpoint, one private `R2_MEDIA_BUCKET` for post files, one public `R2_AVATARS_BUCKET` for avatars with its `R2_AVATARS_URL`). The existing `local`/`public` disk names are kept, so application code and tests are unchanged; post media stays private and is streamed by R2 through short-lived signed URLs issued only after the app authorizes the viewer. `php scripts/sync-media-to-r2.php` copies existing local uploads into the buckets (repeatable — files already present with the same size are skipped).
- **Tests never touch shared resources**: `phpunit.xml` pins `STORAGE_DRIVER=local` and uses in-memory SQLite, so `php artisan test` cannot write to R2 or the hosted database.

Rules for the shared database: everyone's posts live in it, so **never run `preyson:reset-demo` against it** (it deletes every post, comment, report, and account), and only run migrations you intend the whole team to share.

## Run on this computer

From `first_app`, run:

```powershell
.\Start-PreySON.cmd
```

The launcher prefers Herd's PHP 8.4, falls back to `php` on PATH, and starts three local services in hidden windows when they are not already running (hosted configurations skip the local database services):

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
- Registration shows live username/password character counts and a checklist. Incorrect edited fields have red borders; completed requirements turn green and are crossed out. New sign-up passwords require at least 8 characters, a capital letter, a number, and a special character (spaces do not count). Username/email availability is checked after a short typing pause, using the same server rules as registration. Password feedback stays in the browser until the user submits the form. Existing account logins, including the demo administrator, continue to work.

- Registration and login have a server-checked 3×3 color challenge: 2–6 matching squares, distinguishable mixed/distant colors, ten-minute expiry, one-use answers, refresh, keyboard controls, and optional color names. This is an accessible classroom demo challenge, not a production bot-prevention service.
- Registration requires agreement to the readable demo terms/privacy notice and stores its version and acceptance time.
- Members can edit their profile, upload/remove a profile picture, change their password, and delete their account using their current password. Administrators cannot delete themselves.
- Click an author’s username to visit their profile, biography, post/reaction/comment stats, and searchable posts. Profiles start public with email hidden. A private profile hides its biography, stats, posts, discussions, and media from everyone except its owner and moderators/administrators. Username, role, avatar, and comments left on other public posts remain visible. Email visibility is a separate setting; administrators still have account-management access.
- Active Premium members can choose a solid or two-color gradient profile background, with a live preview. Colors are preserved on expiry but only displayed while Premium is active. Members can pin one owned post; it comes first in their profile’s Default order, within any search/media filters. Explicit sorting overrides the pin until Default is selected again.
- Signed-in members can add comments (up to 2,000 characters), edit their own comments, and delete them. Post owners and staff can also remove comments. Upload, ZIP import, and both edit forms include an Allow comments option. Closing a discussion hides it from visitors and prevents new comments/edits; owners and staff can review it, and reopening restores existing comments. Comment Premium badges use only the ✦ star. Discussions are paginated, and comments have stable links.
- Members can report posts, comments, and accounts using Severe, Moderate, or Minor reasons. Every group includes Other, which requires a description; descriptions are optional for other reasons (up to 3,000 characters). One report per member per target prevents repeat flags, including after review. Reports and comments are rate-limited, and text is escaped.
- **Report logs** is available to moderators and administrators. All reports and Most reported both support content type, severity, reason, status, date, and text filters with sorting and pagination. The default ranking compares Severe counts first, then Moderate, then Minor; any higher-severity report outranks all lower-severity volume. Optional weighted ranking uses **100 / 10 / 1** points. Counts and reason breakdowns reflect the active filters; the ranking initially includes open/in-review reports. Staff can mark reports open, in review, resolved, or dismissed and add staff-only notes. Reporter identities and descriptions are staff-only. Reports preserve a text snapshot and original target ID after content deletion; deleting a reporter account clears its identity. Flags never automatically remove content.
- Free accounts can keep **6 posts**, Premium accounts **30**. Each upload accepts up to 6 files totaling 100 MB. Administrators have unlimited posts and ZIP import. GIFs are a separate category.
- Premium checkout and donations collect **only a name**. Card/address details are decorative, crossed out, and never submitted. Premium lasts exactly 30 days without automatic renewal or stacking. On expiry, existing posts remain; accounts above the free limit cannot add more until they reduce their count or renew.
- Premium adds six extra reactions and an animated owner-name gradient (animation respects reduced-motion preferences).
- Administrators can create, search, read, edit, delete, and assign roles to accounts. Moderators can manage community posts but cannot manage accounts. The main administrator's email and role are protected.
- The feed displays owner/avatar/date, provides title search, media filtering, newest/oldest/reaction sorting, and shuffles by default. Pagination retains the shuffle seed. Owners and moderators can edit directly in the feed.
- One reaction per account/post; click again to remove it, or another emoji to change it. Videos pause when less than 25% visible, when another video starts, or when the page is hidden.
- Supporting protections include CSRF, login throttling, case-insensitive account uniqueness, password hashing, revoked sessions after credential/role changes, media cleanup, bounded MIME-checked ZIP imports, upload rollback, and server-enforced roles/quotas.

## Video uploads

New uploads, post replacements (including inline edits), and admin ZIP imports accept **MP4, WebM, MOV, MKV, FLV, AVI, M4V, MPEG/MPG, TS/MTS/M2TS, WMV, and OGV**. Hybrid and fragmented MP4/MOV recordings use their usual `.mp4` or `.mov` extension. The existing limits still apply: up to six files per upload, totaling 100 MB.

Each post upload field displays the **100 MB per-file limit** and the selected size. The browser checks file sizes as soon as you select them and again before submitting. Oversized files (including 4 GB recordings), combined selections above 100 MB, and batches above six files disable Publish/Save until corrected, without reading or uploading the files. The server enforces the same limit if JavaScript is bypassed; requests that exceed PHP's body limit show a readable 413 error page. Here, 100 MB means 104,857,600 bytes, matching PHP's upload size setting.

Files are checked using their contents, stored with a safe matching extension, and categorized as videos. MPEG transport streams also have a bounded packet-header check for PHP installations that identify them as generic binary data. Renaming a script to `.mkv` or uploading TypeScript as `.ts` does not make it an accepted video. HLS `.m3u8` playlists are not standalone recordings and are not accepted.

Uploading a recording does not convert its format or codec. Browser playback support varies; every video includes a download link, and a playback error displays guidance. For broad playback compatibility, use MP4 with H.264 video and AAC audio. OBS can remux compatible recordings through **File → Remux Recordings**; changing the container does not change an unsupported codec. See the [OBS recording guide](https://obsproject.com/kb/standard-recording-output-guide) and [browser media format reference](https://developer.mozilla.org/en-US/docs/Web/Media/Guides/Formats/Containers).

## Reset the demo

This deliberately removes all posts, comments, reactions, reports, other accounts, and their media, and restores the documented administrator login and profile defaults. It preserves the main admin's existing ID where possible and refuses to run in production:

```powershell
& "$env:USERPROFILE\.config\herd\bin\php84\php.exe" artisan preyson:reset-demo --force
```

## Other MySQL installations

PHP 8.3+ is required by Laravel 13. Use PHP with PDO MySQL, fileinfo, mbstring, GD, and ZIP enabled. The local bundled XAMPP PHP 8.2 is used for phpMyAdmin only. See the [Laravel release requirements](https://laravel.com/docs/13.x/releases) and [database configuration documentation](https://laravel.com/docs/13.x/database).

Copy `.env.example` to `.env`, set a new `APP_KEY` with `php artisan key:generate`, and set your MySQL host, port, username, and password. Then:

```sh
composer install
php scripts/setup-mysql.php
php artisan preyson:protect-media
php artisan storage:link
npm ci
npm run build
php -d upload_max_filesize=100M -d post_max_size=110M artisan serve
```

`setup-mysql.php` creates the configured schema, runs migrations, and seeds a new administrator if absent. Pass `--reset` only when you want the destructive demo reset. If using an existing phpMyAdmin installation, connect it to the same MySQL host/port. The sample `.env.example` uses conventional MySQL port 3306; this computer's dedicated instance uses 3308.

For an existing installation, run `php artisan migrate` followed by `php artisan preyson:protect-media` after pulling these changes. The Windows launcher runs both automatically. New post files are stored under `storage/app/private/memes` and served through permission-checked routes that support video byte ranges and downloads. The migration command copies legacy post media from the public disk, verifies SHA-256 checksums, then removes the public copies. It can safely be repeated; avatars stay on the public disk. Making a profile private also protects any remaining legacy media before saving privacy. Back up both the database and `storage/app` when moving to another PC; these files and `.env` are not included in Git. Previously downloaded copies cannot be recalled by changing privacy.

For a new dedicated Windows instance like this one, initialize an empty `storage/mysql/data` using the installed `mysqld --no-defaults --initialize-insecure --datadir=...`, start it bound to `127.0.0.1:3308`, and run `php scripts/bootstrap-local-mysql.php` once. This one-time script checks that the server's data directory is inside the project, creates random database passwords, and adds the XAMPP phpMyAdmin entry. Then run `php scripts/setup-mysql.php`. Never initialize over existing database files.

## Verification

```sh
php artisan test
php vendor/bin/phpstan analyse --memory-limit=512M
php vendor/bin/pint --dirty --test
npm run types:check
npm run test:uploads
npm run test:registration
npm run test:community
npm run build
php scripts/verify-database.php
```

Tests normally use isolated, in-memory SQLite. The full suite was also exercised on MySQL 8.0.44 using a separate `preyson_memes_test` database; never point RefreshDatabase tests at the application database. `scripts/mysql-test-database.php` prepares that temporary local schema and `--drop` removes it after verification.
