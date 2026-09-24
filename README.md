# Social Post Manager

A single-user personal tool to scrape article URLs (blogs, personal pages, news sites), store the readable content, and turn it into social media posts for **X** (and, later, LinkedIn). Publishing authorization is handled with Laravel Socialite used purely as a token broker.

> Personal, single-user tool. Login is a single seeded admin account — there is no public registration.

## How it works

```
Paste URL → queued scrape (Readability) → stored Article
          → generate per-platform drafts (templates) → edit
          → publish to X (real API)
```

1. **Scrape** — paste a URL; a queued job fetches it (static HTML only) and extracts the title, body, excerpt, author, published date, and OG image via a generic Readability pass.
2. **Compose** — generate draft posts from a hardcoded per-platform template (title + fitted excerpt + link), then edit them with a live character counter.
3. **Publish** — post to X immediately via the API. Drafts are never auto-posted; you review and click Publish.

## Tech stack

- PHP 8.4, Laravel 13
- Filament v5 (admin panel / UI)
- Laravel Socialite v5 (OAuth 2.0 token broker for X, with PKCE)
- `fivefilters/readability.php` (content extraction)
- SQLite (default), database queue, Pest v4 (tests)

## Requirements

- PHP 8.4+, Composer
- Node.js + npm (for building Filament assets)
- An **X developer app** configured as a **Web App (confidential client)** with **Read and write** permissions and OAuth 2.0 enabled

## Installation

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Set these in `.env`:

```dotenv
# Single admin login (seeded)
ADMIN_NAME="Your Name"
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=change-me

# X (Twitter) OAuth 2.0 — confidential "Web App" with Read and write
X_CLIENT_ID=...
X_CLIENT_SECRET=...
X_REDIRECT_URI="${APP_URL}/twitter/redirect"
```

Then migrate, seed the admin user, and build assets:

```bash
php artisan migrate --seed
npm run build
```

### X app configuration

In the X developer portal, your app must have:

- **App permissions:** Read and write
- **Type of app:** Web App, Automated App or Bot (confidential client — required so the client secret works)
- **Callback URI / Redirect URL:** must match `X_REDIRECT_URI` exactly, e.g. `http://127.0.0.1:8000/twitter/redirect`

The app requests the scopes `users.read`, `tweet.read`, `tweet.write`, and `offline.access` (the last yields a refresh token; X access tokens expire after ~2 hours and are refreshed automatically before publishing).

## Running

```bash
composer run dev
```

This runs the dev server, a **queue worker**, log tailing, and Vite together. A queue worker is required — both scraping and publishing run as queued jobs.

Then:

1. Log in at `/admin`.
2. Go to **Connections → Connect X** and authorize the app.
3. On **Articles**, add a URL to scrape.
4. Open the article, click **Generate drafts**, edit the X draft, and **Publish**.

## Testing

```bash
php artisan test
vendor/bin/pint   # code style
```

External services (the X API, Socialite, outbound HTTP) are faked in tests.

## Architecture notes

- **Platform-generic** by design: a `Platform` enum (`x`, `linkedin`), a `Publisher` contract, and a `PublisherFactory`. X and LinkedIn publishing are supported through platform-specific publisher implementations.
- **Scraping** (`App\Services\Scraping`): a queued `ScrapeArticle` job that is idempotent and distinguishes permanent failures (4xx, no readable content → no retry) from transient ones (5xx, network → bounded retry). Includes a basic SSRF guard (http(s) only, blocks private/reserved IPs and localhost).
- **OAuth** (`App\Services\Social`, `OAuthConnectionController`): thin redirect/callback web routes behind the panel auth guard; tokens are stored encrypted at rest with on-demand refresh.
- **Composition** (`App\Services\Posts\PostComposer`): platform-aware character weighting — on X every URL counts as 23 characters (t.co); LinkedIn counts real length. Rendering always keeps the URL and truncates the excerpt/title to fit.
- **Publishing** (`App\Jobs\PublishPost`): **never auto-retries** (`tries = 1`) and uses an **atomic claim** (a single conditional `UPDATE` flipping Draft/Failed → Publishing) plus `ShouldBeUnique` to prevent double-posting, since X has no idempotency key.

### Status model

- **Article:** `pending → scraping → scraped → failed`
- **Post:** `draft → publishing → published → failed`

## Not included (deferred)

Saved/reusable URL lists, monitored/scheduled sources, scheduled posting, media/image upload, AI-generated copy, multi-user support, and configurable templates.
