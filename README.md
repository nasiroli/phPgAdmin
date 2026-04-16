# phpPg Admin

A small [Laravel](https://laravel.com/) + [Livewire v4](https://livewire.laravel.com/) app for managing saved PostgreSQL connection profiles and browsing databases (schemas, tables, SQL, and guarded writes). The UI is optimized for a desktop-style shell via [NativeBlade](https://github.com/nativeblade/nativeblade) with a simple password gate.

## Requirements

- **PHP** 8.3+ with common extensions (`pdo_sqlite`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)
- **Composer** 2.x
- **Node.js** 20+ and **npm** (for Vite and front-end assets)

PostgreSQL servers you connect to must be reachable from the machine running this app (the app does not bundle Postgres).

## Setup

### One-shot (recommended)

From the project root:

```bash
composer run setup
```

This installs PHP dependencies, creates `.env` from `.env.example` if missing, generates `APP_KEY`, runs migrations, installs npm packages, and runs a production Vite build.

### Manual setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # if using default SQLite app DB
php artisan migrate
npm install
npm run build
```

### Environment

Copy `.env.example` to `.env` and adjust as needed:

| Variable | Purpose |
|----------|---------|
| `APP_NAME` | Application title |
| `APP_DESKTOP_PASSWORD` | Password for the in-app login screen (not a Postgres password) |
| `APP_URL` | Base URL when using `php artisan serve` (e.g. `http://127.0.0.1:8000`) |
| `DB_CONNECTION` | Laravel app database (`sqlite` by default; stores servers, connections, sessions) |

Connection credentials for PostgreSQL are stored in the app database (passwords encrypted); they are separate from `APP_DESKTOP_PASSWORD`.

## Building assets

| Command | When to use it |
|---------|----------------|
| `npm run dev` | Local development with Vite hot reload |
| `npm run build` | Production / before deployment (writes to `public/build`) |

If you change CSS or JS and the UI looks stale, run `npm run dev` or `npm run build` as appropriate.

## Running the app

### Web / API-style development

```bash
php artisan serve
```

Visit `APP_URL` (e.g. `http://127.0.0.1:8000`). In another terminal, optional:

```bash
npm run dev
```

### All-in-one dev (from Composer)

Runs the PHP server, queue worker, log tail, and Vite together:

```bash
composer run dev
```

## Usage

1. **Sign in** at `/login` with the password from `APP_DESKTOP_PASSWORD`.
2. **Add a server** — host, port, optional notes (logical grouping).
3. **Add a connection** — pick a server, database name, user, password, SSL mode.
4. **Open** a connection to enter the **workspace** (`/explorer/{id}`): choose database, schema, and table.
5. Use the tabs — **Browse**, **Structure**, **Insert**, **Indexes**, **SQL**, **Operations**. Enable **Allow writes** only when you intend to run DDL/DML; destructive actions use confirmations.

The sidebar lists servers → connections → databases → schemas → tables/views and links into the workspace with the right context.

## Tests & code style

```bash
composer run test
# or
php artisan test --compact
```

After editing PHP, format changed files with Pint:

```bash
vendor/bin/pint --dirty
```

## Optional: Tauri desktop shell

The repo includes Tauri configuration under `src-tauri/` and a `npm run tauri` script for packaging a native shell around the front end. Use this only if you are actively developing the desktop wrapper; day-to-day Laravel usage is `php artisan serve` + `npm run dev` as above.
