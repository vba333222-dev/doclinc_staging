# Deployment Notes

## Scope

These notes cover environment and database configuration for the CodeIgniter 3 HMVC public app and the embedded `admin_menu` app.

No database schema changes are required.

## Required Environment Variables

Set these values in the web server, PHP-FPM pool, aaPanel environment configuration, or another server-side environment mechanism. Do not commit real values to the repository.

### Public App

```sh
APP_BASE_URL=https://example.com/sehat_geh/
CI_ENCRYPTION_KEY=replace_with_existing_production_key
API_ACCESS_TOKEN=replace_with_existing_bearer_token_if_used

DB_HOST=localhost
DB_PORT=3306
DB_NAME=replace_with_database_name
DB_USER=replace_with_database_user
DB_PASS=replace_with_database_password
DB_DRIVER=mysqli
DB_PREFIX=
DB_CHARSET=utf8
DB_COLLATION=utf8_general_ci
DB_DEBUG=false
DB_SAVE_QUERIES=true
```

### Admin App

If the admin app uses the same database, the `ADMIN_DB_*` variables can be omitted and it will reuse the `DB_*` values.

```sh
ADMIN_BASE_URL=https://example.com/sehat_geh/admin_menu/

ADMIN_DB_HOST=localhost
ADMIN_DB_PORT=3306
ADMIN_DB_NAME=replace_with_admin_database_name
ADMIN_DB_USER=replace_with_admin_database_user
ADMIN_DB_PASS=replace_with_admin_database_password
ADMIN_DB_DRIVER=mysqli
ADMIN_DB_PREFIX=
ADMIN_DB_CHARSET=utf8
ADMIN_DB_COLLATION=utf8_general_ci
ADMIN_DB_DEBUG=false
ADMIN_DB_SAVE_QUERIES=true
```

## Conservative Fallbacks

- `APP_BASE_URL` and `ADMIN_BASE_URL` fall back to the current request scheme, host, and script path.
- Database host falls back to `localhost`.
- Database port falls back to `3306`.
- Database driver falls back to `mysqli`.
- Database charset/collation fall back to `utf8` and `utf8_general_ci`.
- Database user, password, and database name do not have production fallback values. They must be provided by environment variables.

## Server Steps

1. Configure the environment variables above for the PHP runtime used by the site.
2. Restart PHP-FPM or the web service so PHP receives the new environment.
3. Ensure runtime directories exist and are writable by the web server user:

```sh
mkdir -p application/cache/sessions application/logs
mkdir -p admin_menu/application/cache/sessions admin_menu/application/logs
chmod 775 application/cache application/cache/sessions application/logs
chmod 775 admin_menu/application/cache admin_menu/application/cache/sessions admin_menu/application/logs
```

4. Set the correct owner/group for aaPanel or the active PHP-FPM user.
5. Open the public app and admin app in a browser.
6. Check that no database connection error, session path warning, or base URL asset issue appears.

## Validation Checklist

- Public app loads with the configured `APP_BASE_URL`.
- Admin app loads with `ADMIN_BASE_URL`, if used.
- Login page can reach the configured database.
- `application/logs` does not show missing env, database, cache, or session-path warnings.
- `admin_menu/application/logs` does not show missing env, database, cache, or session-path warnings.

## Rollback

To roll back this config change:

1. Revert these files from the previous deployment package or version control:
   - `.gitignore`
   - `application/config/config.php`
   - `application/config/database.php`
   - `admin_menu/application/config/config.php`
   - `admin_menu/application/config/database.php`
   - `DEPLOYMENT_NOTES.md`
2. Restore the previous hardcoded config values only if required for emergency recovery.
3. Restart PHP-FPM or the web service after rollback.

