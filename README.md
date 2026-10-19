# Sembark Tech URL Shortener

Laravel 12 URL shortener implementing the user's corrected specification supplied
on 19 October 2026. That specification supersedes the original PDF's negative wording.

## Local setup

Requirements: PHP 8.2+, Composer, PDO SQLite. The Blade screens use simple HTML and
inline CSS; Node/Vite is optional and not required to use the application.

```sh
composer install
```

Copy `.env.example` to `.env` (`Copy-Item .env.example .env` in PowerShell, or
`cp .env.example .env` on macOS/Linux). For SQLite use:

```dotenv
APP_NAME="Sembark Tech"
APP_ENV=local
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
```

Remove stale `DB_URL` and `DB_DATABASE` values to use the default SQLite path.

```sh
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder
php artisan serve
```

Open http://127.0.0.1:8000. This existing workspace already uses a migrated SQLite
file at `database/sembark-local.sqlite`; its previous MySQL configuration is backed
up in `.env.backup`. No MySQL records were changed. For existing installations run
`php artisan migrate` without resetting your database.

SQLite is verified. MySQL configuration is available, but MySQL runtime testing was
not performed. Use migrations rather than the legacy SQL dump. The role foreign-key
repair migration preserves existing data and rejects orphan role assignments.

## Demo accounts

Default seeding creates SuperAdmin using parameterized raw SQL. Optional DemoSeeder
creates Admin/Member accounts across two companies. Both seed paths are repeatable;
DemoSeeder only runs in local/testing environments.

| Email | Role | Company |
|---|---|---|
| superadmin@example.com | SuperAdmin | Sembark Tech (platform account) |
| admin@example.com | Admin | Sembark Tech |
| member@example.com | Member | Sembark Tech |
| travel.admin@example.com | Admin | Example Travel |
| travel.member@example.com | Member | Example Travel |

Local demo password: `ChangeMe@12345`. Seeders do not reset existing passwords.
Existing obsolete-role accounts are converted to Member by the new migration,
with their company assignments and URLs preserved. Fresh installs define only
SuperAdmin, Admin and Member. This data conversion is not reversed on rollback.

## Permissions

| Role | Create URL | List URLs | Invite users |
|---|---|---|---|
| SuperAdmin | No | Every company | Admin in a new company |
| Admin | Yes | Own company only | Admin or Member in own company |
| Member | Yes | Created by themselves only | No |

**Short links are public.** Anyone can open `/s/{code}` without login and receive a
302 redirect to the original HTTP/HTTPS URL. Unknown codes return 404. Creation,
listing and sending invitations require authentication. The supplied test bullet
says "notpublicly" but conflicts with the explicit public-link feature requirement;
this implementation follows the public-link requirement and tests guest redirects.

## Invitations

1. Sign in as SuperAdmin or Admin and select **Invite users**.
2. SuperAdmin enters a new company name and invitee email; the role is Admin.
3. Admin chooses Admin or Member; the company is fixed to the inviter's company.
4. Submit and copy the generated invitation link to share with the invitee.
5. The invitee opens the link, enters a name and confirmed password, then logs in.

The invite creates the new company immediately for the SuperAdmin flow. A user is
created only on acceptance. The email, company and role are fixed by the invitation;
acceptance form fields cannot change them. Only a SHA-256 token hash is stored.
Links expire after seven days and can only be accepted once; invalid links return
404, expired/used links 410. Duplicate account emails and privilege escalation are
rejected. Acceptance and token consumption are transactional.

## Validation and tests

```sh
php artisan test
php vendor/bin/pint --test
php artisan route:list --except-vendor
```




