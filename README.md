# phpPg Admin

A small [Laravel](https://laravel.com/) + [Livewire v4](https://livewire.laravel.com/) app for managing saved PostgreSQL connection profiles and browsing databases (schemas, tables, SQL, and guarded writes). The UI is built for a **desktop-style shell** via [NativeBlade](https://github.com/nativeblade/nativeblade) (Tauri + PHP in WebAssembly) with a simple password gate.

## How this app is meant to run

| Mode | When to use it |
|------|----------------|
| **NativeBlade desktop** | **Primary.** Packaged window (`php artisan nativeblade:dev`). Offline-capable Laravel-in-WASM; best match for the UI and shell. |
| **Local PHP web server** | **Optional.** Same codebase in a normal browser—handy for quick debugging, CI, or when you need **native `pdo_pgsql`** without extra setup (see [PostgreSQL drivers](#postgresql-drivers-nativeblade-vs-browser)). |

You can use **any** approach that serves this Laravel app with PHP 8.3+ and points `APP_URL` at the public URL.

## Requirements

- **PHP** 8.3+ with common extensions (`pdo_sqlite`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)
- **Composer** 2.x
- **Node.js** 20+ and **npm** (Vite and front-end assets)
- **For NativeBlade desktop:** [Rust](https://www.rust-lang.org/tools/install) (first `nativeblade:dev` compile can take several minutes)

PostgreSQL servers you connect to must be reachable from the machine running the app.

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
| `APP_URL` | **Must match** the URL you open in the browser when *not* using NativeBlade (e.g. `http://127.0.0.1:8000`, `https://phppgadmin.test`, or your Local/Herd site URL). Used for URL generation and Vite. |
| `DB_CONNECTION` | Laravel app database (`sqlite` by default; stores servers, connections, sessions) |

Connection credentials for PostgreSQL are stored in the app database (passwords encrypted); they are separate from `APP_DESKTOP_PASSWORD`.

## Running the app

### 1. NativeBlade desktop (primary)

After `composer run setup` (or `npm run build` at least once):

```bash
php artisan nativeblade:dev
```

This launches the [NativeBlade](https://github.com/nativeblade/nativeblade) + Tauri desktop shell. See the package [README](https://github.com/nativeblade/nativeblade/blob/main/README.md) and [BUILD.md](https://github.com/nativeblade/nativeblade/blob/main/BUILD.md) for platform requirements, mobile targets, and production builds.

**Hot reload during UI work:** run Vite in another terminal if you want HMR while the desktop app is open:

```bash
npm run dev
```

#### PostgreSQL drivers (NativeBlade vs browser)

The WASM PHP runtime used in the desktop shell **often does not include `pdo_pgsql`**. If Postgres connections fail with a message about the extension, either:

- Run the app with **native PHP** (see [Local web server](#2-local-web-server-optional) below), or  
- Use any **bridge / native** path NativeBlade documents for your platform.

Browsing and SQL features need a working `pdo_pgsql` when talking to real PostgreSQL servers.

### 2. Local web server (optional)

Use these when you want the app in a normal browser or need native PHP extensions (e.g. `pdo_pgsql`) without WASM limitations.

#### Laravel’s built-in server

```bash
php artisan serve
```

Set `APP_URL` to the URL shown (commonly `http://127.0.0.1:8000`). Optional second terminal:

```bash
npm run dev
```

#### Laravel Herd (macOS / Windows)

1. Add this project as a site (or symlink the project into Herd’s parked paths).  
2. Set `APP_URL` to your Herd URL (e.g. `https://phppgadmin.test`).  
3. Ensure PHP 8.3+ and required extensions are enabled in Herd.  
4. Run `npm run dev` or `npm run build` when changing front-end assets.

#### Laravel Valet (macOS)

```bash
cd /path/to/phppgadmin
valet link phppgadmin   # or your preferred name
```

Set `APP_URL` to `https://phppgadmin.test` (or the name you chose). Ensure Valet’s PHP version meets the app’s requirement.

#### Other tools (Local, MAMP, Docker, nginx + PHP-FPM, etc.)

Point the web root at this project’s `public/` directory, use PHP 8.3+, and set `APP_URL` to whatever URL serves the site (including HTTPS and port if non-default). Run `php artisan migrate` and `npm run build` (or `npm run dev`) from the project root as usual.

> **Local (WordPress) / “Local” by WP Engine:** primarily targets WordPress, but if your workflow allows a **custom PHP / non-WP** site pointing at this project’s `public/` folder, the same rules apply: correct `APP_URL`, PHP version, and extensions.

#### All-in-one dev (Composer)

Runs the PHP server, queue worker, log tail, and Vite together (good for **browser** development, not a substitute for `nativeblade:dev`):

```bash
composer run dev
```

## Building assets

| Command | When to use it |
|---------|----------------|
| `npm run dev` | Local development with Vite hot reload |
| `npm run build` | Production / before deployment / before NativeBlade builds (writes to `public/build`) |

If you change CSS or JS and the UI looks stale, run `npm run dev` or `npm run build` as appropriate.

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

## Tauri / `src-tauri/`

NativeBlade owns the desktop lifecycle; the `src-tauri/` tree is part of that stack. Use `php artisan nativeblade:dev` for day-to-day desktop development. See [NativeBlade’s build docs](https://github.com/nativeblade/nativeblade/blob/main/BUILD.md) for packaging and platform-specific notes.
