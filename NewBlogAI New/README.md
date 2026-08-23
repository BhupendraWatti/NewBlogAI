# NewsBlogify AI

NewsBlogify AI is a Laravel 12 newsroom automation platform for discovering current stories, generating source-grounded articles, managing prompts and AI providers, and publishing to connected WordPress sites.

## Requirements

- PHP 8.2 or newer with PDO, SQLite or MySQL, cURL, DOM, and mbstring
- Composer 2
- Node.js 22 or newer and npm
- A queue worker and scheduler runner for automated production workflows
- At least one configured AI provider for content generation

## Local setup

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm ci
npm run build
php artisan serve
```

On macOS or Linux, replace `copy` with `cp`. The default example configuration uses `database/database.sqlite`; create that empty file first if it is not present.

Open `http://127.0.0.1:8000`. Never use demo or shared credentials outside a disposable local environment. Create the first administrator through a controlled provisioning process or a local database seeder.

For a development session with the web server, queue listener, logs, and Vite running together:

```bash
composer run dev
```

## Quality checks

```bash
composer test -- --compact
npm run build
vendor/bin/pint --test
```

The application includes a health endpoint at `/up`. Browser smoke testing should cover login, Prompt Library, Newsroom Pipeline, Websites, AI Providers, and Publishing Queue at desktop and mobile widths.

## Universal news prompt

The production prompt is versioned in `app/Modules/PromptManager/Support/UniversalNewsPrompt.php`. It is installed or upgraded through the database migrations and supports these runtime variables:

`topic`, `headline`, `summary`, `category`, `language`, `website`, `tone`, `keywords`, `sources`, `date`, and `research_context`.

The prompt is designed to keep every mutable claim inside retrieved evidence, apply inverted-pyramid reporting, distinguish current news from historical background, and produce search-friendly reporting without clickbait or keyword stuffing.

## Production deployment

Follow [PRODUCTION.md](PRODUCTION.md) before exposing the application publicly. Production requires HTTPS, `APP_DEBUG=false`, durable database backups, supervised queue workers, the scheduler, built frontend assets, and a post-deploy health check.

## Important directories

- `app/Modules/ContentPipeline` — discovery, evidence collection, generation, quality, and publishing pipeline
- `app/Modules/PromptManager` — prompt library and universal prompt definition
- `app/Modules/AIProviderManager` — provider drivers and credential routing
- `app/Modules/SiteManager` — WordPress connectivity and synchronization
- `wordpress-plugin` — companion WordPress integration
- `tests/Feature` — end-to-end backend behavior and regression coverage
- `graphify-out` — generated architecture graph and audit report

## Security

Do not commit `.env`, API keys, WordPress credentials, database dumps, or generated authentication tokens. Report vulnerabilities privately to the project owner; do not open a public issue containing exploit details or secrets.
