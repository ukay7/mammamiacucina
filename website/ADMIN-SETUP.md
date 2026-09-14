# Administration — phase 1

The real SmartAdmin 4.4.1 HTML distribution is used for the layout and Bootstrap 4 controls. The application overrides its palette in `public/admin-assets/mmc-admin.css`.

## Local setup

From the repository root:

```powershell
.tools\php\php.exe website\artisan migrate
.tools\php\php.exe website\artisan admin:create
```

The command asks for name, email and a hidden password (with confirmation). There is no public registration and no default administrator password.

Open `/admin/login`. Super Admin accounts are protected from edits through the browser. Additional protected accounts can be created via the command.

## Access model

- User Management: create/edit users, change type, activate/deactivate, set a new password.
- User Types & Permissions: create/edit types and configure viewing/managing each current module.
- Sidebar items and HTTP endpoints enforce the same permissions.
- Management access includes viewing its module. Dashboard access is included for all types.
- A user cannot grant permissions they do not hold or manage a type/account with broader permissions. Users cannot deactivate themselves or edit their own type's permissions.
- Deactivation blocks existing sessions on the next request. Password changes invalidate existing authenticated sessions via Laravel's `auth.session` middleware.
- A type cannot be deleted while users are assigned to it. Protected Super Admin types cannot be edited or deleted.
- Login uses Laravel sessions, CSRF protection, session regeneration, hashed passwords and rate limiting. Logout is POST-only.

Products, categories, imports, inventory, media and banners are implemented. Storefront products and order processing are still a separate phase.

## SmartAdmin distribution

The supplied package and `public/admin-assets/smartadmin/` are excluded from Git because the current repository is public and the commercial template license restricts redistribution. Custom application code remains separate. Do not publish the full template ZIP or source package.

For deployment, privately copy these files from the licensed template `dist` directory to `website/public/admin-assets/smartadmin/`, retaining folders:

- css/vendors.bundle.css
- css/app.bundle.css
- css/fa-solid.css
- js/vendors.bundle.js
- js/app.bundle.js
- webfonts/ (directory)

## Production prerequisites

The server's current frontend-only setup has no configured database. Before enabling this admin:
1. Configure a persistent MySQL database with a dedicated application account, or install the PHP 8.4 SQLite extension and create a persistent SQLite file with appropriate permissions.
2. Set the matching DB_* values in the server `.env` (never commit credentials).
3. Run `php artisan config:clear`, then `php artisan migrate --force` as the deployment user.
4. Run `php artisan admin:create` interactively as the deployment user.
5. Run `php artisan config:cache` and `php artisan view:cache`.
6. Verify `/admin/login`, authorization and write permissions on storage/bootstrap/cache. Use HTTPS and SESSION_SECURE_COOKIE=true in production.

The existing frontend file-session/cache drivers can continue to be used. Do not replace the existing APP_KEY or database on subsequent deployments.

## Validation

Run `php artisan test --filter=AdminAccessTest`. The feature tests use an isolated in-memory SQLite database, not the application's database.
