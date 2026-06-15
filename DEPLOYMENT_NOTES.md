# Deployment Notes

## Base URL

Set these values in the server environment for predictable URLs:

```sh
APP_BASE_URL=https://example.com/sehat_geh/
ADMIN_BASE_URL=https://example.com/sehat_geh/admin_menu/
```

If the variables are not set, the apps detect the current request scheme, host, and script path. This keeps local HTTP usable and still supports HTTPS behind a proxy when `X-Forwarded-Proto: https` or `X-Forwarded-SSL: on` is present.

## HTTPS Redirects

The root `.htaccess` and `admin_menu/.htaccess` redirect HTTP to HTTPS for normal hosts.

Local development hosts are excluded from forced HTTPS:

- `localhost`
- `127.0.0.1`
- `0.0.0.0`

For production behind a reverse proxy or load balancer, forward one of these headers:

```sh
X-Forwarded-Proto: https
X-Forwarded-SSL: on
```

This prevents redirect loops when TLS terminates before PHP/Apache.

## Routes

Current default routes were preserved:

- Public app: `home`
- Admin app: `login`

No controller, model, view, or database schema changes are required for this URL/HTTPS update.

## Server Checks

1. Enable Apache `mod_rewrite`.
2. Confirm `.htaccess` override is allowed for the document root.
3. Set `APP_BASE_URL` and `ADMIN_BASE_URL` for staging/production.
4. Restart Apache/PHP-FPM after environment changes.
5. Test local HTTP without HTTPS redirect.
6. Test staging/production HTTP redirects to HTTPS.
7. Test public assets load under `assets/`, `uploads/`, and admin `assets/`.

## Rollback

Revert these files to the previous deployment version:

- `.htaccess`
- `admin_menu/.htaccess`
- `application/config/config.php`
- `admin_menu/application/config/config.php`
- `DEPLOYMENT_NOTES.md`

Then restart Apache/PHP-FPM if environment variables were changed.
