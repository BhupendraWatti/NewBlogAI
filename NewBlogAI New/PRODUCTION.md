# Production deployment

This checklist assumes a Linux host, PHP-FPM, Nginx or Apache, a durable SQL database, and a process supervisor. Adapt service names to the hosting platform.

## 1. Runtime configuration

Set production environment values through the host's secret manager or an untracked `.env` file:

```dotenv
APP_NAME="NewsBlogify AI"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://news.example.com
LOG_LEVEL=warning

DB_CONNECTION=mysql
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
```

Generate `APP_KEY` once with `php artisan key:generate --show` and store it as a durable secret. Rotating it without a migration plan invalidates encrypted application data. Configure AI-provider and WordPress credentials through the application or secret store; never bake them into images or source files.

Use a database account with access only to this application's schema. Terminate TLS at the application host or a trusted proxy and forward the original HTTPS scheme correctly.

## 2. Release build

Run these commands from the immutable release directory:

```bash
composer install --no-dev --classmap-authoritative --no-interaction --prefer-dist
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan storage:link
```

Ensure the web-service account can write only to `storage` and `bootstrap/cache`. Point the web root at `public`; never expose the repository root.

## 3. Background processes

Run a supervised queue worker so long-running generation and publishing jobs restart after crashes or deployments:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=1800 --max-time=3600
```

Run the Laravel scheduler every minute from cron or the platform scheduler:

```cron
* * * * * cd /var/www/newsblogify/current && php artisan schedule:run >> /dev/null 2>&1
```

After a release, run `php artisan reload` or restart the PHP and queue services so long-lived workers load the new code.

## 4. Release verification

Before shifting traffic:

```bash
php artisan migrate:status
php artisan about --only=environment
curl --fail --silent --show-error https://news.example.com/up
```

Then verify login, one authenticated dashboard request, AI-provider configuration access, a non-publishing prompt dry run, and a WordPress connectivity check. Confirm the browser console has no errors and that `/build/manifest.json` resolves through the application release.

## 5. Operations and rollback

- Back up the database before migrations and test restoration regularly.
- Retain the previous release directory and its matching frontend assets.
- Prefer forward-fix migrations. Roll back code only when its database schema remains backward compatible.
- Monitor failed jobs, generation latency, provider rate limits, WordPress publishing failures, HTTP 5xx rates, and `/up` availability.
- Alert when the scheduler or queue heartbeat stops.
- Review dependency advisories regularly with `composer audit` and `npm audit`.

Laravel's deployment guidance recommends cached production configuration, disabled debug output, health monitoring, and reloading long-running services after a release.
