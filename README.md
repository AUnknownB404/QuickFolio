# quickFolio

quickFolio is a portfolio-building web application. Users choose a published template, fill in portfolio content through a structured editor, and publish it at a public URL. Portfolios can include uploaded images and documents, and can be downloaded as static ZIP exports.

## Features

- Browse and preview published portfolio templates.
- Create, edit, publish, and manage portfolios.
- Switch a portfolio to another template while retaining compatible content.
- Upload portfolio images and documents.
- Configure a Google Form as a portfolio contact form.
- Download a static ZIP containing the portfolio page, styles, scripts, and local assets.
- Admin tools for managing users and templates.

## Templates

The current templates are:

- Emerald Minimalist
- Dark Orange Designer
- Ivory Editorial
- Bento Grid
- Cobalt Brutalist

Template metadata and editable field definitions are seeded into the database. The template designs themselves are Vue components in `resources/js/components/Templates`.

## Tech stack

- PHP 8.2+, Laravel 12, Eloquent
- Vue 3, TypeScript, Inertia.js
- Vite, Tailwind CSS
- SQLite by default (configurable through Laravel's database environment variables)

## Requirements

- PHP 8.2 or newer with the extensions required by Laravel
- Composer
- Node.js and npm
- SQLite for the default local database, or another database supported by Laravel

## Local setup

1. Clone the repository and enter the project directory.
2. Install PHP and JavaScript dependencies:

   ```bash
   composer install
   npm install
   ```

3. Create the environment file and application key:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   In PowerShell, use `Copy-Item .env.example .env` instead of `cp`.

4. Apply database migrations and seed the starter template data:

   ```bash
   php artisan migrate --seed
   ```

   The default `.env.example` uses SQLite, file-based cache and sessions, and synchronous jobs. To use another database, update `DB_CONNECTION` and the connection URL in `.env` before migrating. SQLite keeps local development independent of any Neon credentials also present in `.env`.

5. Start the local development environment:

   ```bash
   composer run dev
   ```

   This starts Laravel's development server, queue listener, log viewer, and Vite. Open [http://localhost:8000](http://localhost:8000).

You can also run the Laravel server and Vite separately with `php artisan serve` and `npm run dev`.

### Using Neon from your local machine

Keep SQLite as the default for everyday local development. To temporarily run the app against Neon:

1. In the Neon console, create or select a **development branch**. Use its pooled connection string for `DATABASE_URL` and its direct connection string for `DATABASE_URL_UNPOOLED`; do not test migrations against production.
2. Set `DB_CONNECTION=pgsql` in `.env`. Keep `CACHE_STORE=file`, `SESSION_DRIVER=file`, and `QUEUE_CONNECTION=sync` so these local services do not create extra database connections.
3. Use the complete Neon URLs, including their SSL query parameters. The app derives the endpoint ID from standard Neon hostnames and passes it as a PostgreSQL connection option, so `NEON_ENDPOINT_ID` is normally unnecessary. Set it explicitly only when using a custom database hostname. Do not set it when the server reports an inconsistent project name between SNI and the project option.
4. Clear cached Laravel configuration, then verify the connection:

   ```bash
   php artisan config:clear
   php artisan db:show --database=pgsql
   ```

   Run migrations against the development branch only, using the direct connection:

   ```bash
   php artisan migrate --database=pgsql_unpooled
   ```

5. To return to local SQLite, set `DB_CONNECTION=sqlite` and clear the config cache again:

   ```bash
   php artisan config:clear
   ```

The PHP CLI used for local development must have the `pdo_pgsql` extension enabled to connect to Neon. On XAMPP for Windows, enable `extension=pdo_pgsql` in `C:\xampp\php\php.ini`, restart the local PHP server, and confirm it appears with `php -m | Select-String 'pdo_pgsql'`. SQLite requires `pdo_sqlite`.

## Vercel deployment

In Vercel, set the build command to `composer run vercel`. It builds the frontend, publishes the built assets, and applies migrations using `pgsql_unpooled`. Before deploying, configure these Vercel environment variables for the intended environment:

- `APP_ENV=production`, `APP_DEBUG=false`, and `APP_KEY` (generate a key with `php artisan key:generate --show` and save it as a secret).
- `DB_CONNECTION=pgsql`, `DATABASE_URL` (Neon's pooled URL), and `DATABASE_URL_UNPOOLED` (the matching direct URL). Both URLs must point to the same Neon branch.
- `CACHE_STORE=database`, `SESSION_DRIVER=database`, `DB_CACHE_CONNECTION=pgsql_unpooled`, and `QUEUE_CONNECTION=sync` (unless you have a separate queue worker).
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_ENDPOINT_URL_S3`, and `AWS_REGION` for Neon Object Storage.

The application uses the pooled URL for normal requests; rate-limit locks and deployment migrations use the direct URL. The app derives the endpoint ID from standard Neon hostnames, so `NEON_ENDPOINT_ID` is normally not needed on Vercel; set it explicitly only when using a custom database hostname. `neon deploy` pulls Neon variables into the local environment, but Vercel's environment variables must be configured separately. Do not commit `.env` or paste connection URLs into source control.

Before deploying, verify the environment and Neon connection from a local checkout configured with the same database settings. These commands connect to the database selected by the configured URLs; do not point them at production unless that is intentional:

```bash
php artisan config:clear
php artisan db:show --database=pgsql
php artisan migrate:status --database=pgsql_unpooled
```

Confirm `DATABASE_URL` and `DATABASE_URL_UNPOOLED` both refer to the intended Neon branch. `db:show` checks the pooled application connection, and `migrate:status` checks which migrations have run using the direct connection. The Vercel build command below applies pending migrations to `pgsql_unpooled`.

Portfolio uploads (images and documents) and template thumbnails use Neon Object Storage's S3-compatible API. `neon.ts` declares a private `assets` bucket and a `public_read` `portfolio-assets` bucket; the app stores public object URLs in portfolio content, so objects uploaded to `portfolio-assets` can be read anonymously. Do not store private files in that bucket. Configure these Neon-provided variables in Vercel (and locally via `neon deploy`):

- `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY`: branch-scoped storage credentials.
- `AWS_ENDPOINT_URL_S3`: the branch's S3 endpoint.
- `AWS_REGION`: the bucket's region.

The Laravel `neon` filesystem disk uses path-style S3 addressing and the `portfolio-assets` bucket. Object keys retain the `users/{user}/templates/{template}/portfolios/{portfolio}/...` prefix structure; no local storage link or directory creation is needed on Vercel. Existing R2 objects are not copied automatically; any existing database URLs continue to point to their original storage provider.

## Promoting an administrator

The first account must register before it can be promoted. Then run the command against the same database used by the deployment:

```bash
php artisan admin:promote user@example.com
```

The command reports an error when that email does not belong to an existing account.

## Useful commands

```bash
# Build frontend assets for production
npm run build

# Run the test suite
php artisan test

# Check frontend formatting
npm run format:check
```

## Application routes

- `/` — Home page
- `/templates` — Template marketplace
- `/templates/{template}` — Template details
- `/preview/{template}` — Template preview
- `/dashboard` — Authenticated portfolio dashboard
- `/p/{portfolio}` — Public published portfolio

Portfolio editing, uploads, publishing, downloads, and admin tools require authentication. Google Form integration is configured per portfolio and sends submissions directly from the visitor's browser to Google Forms.
