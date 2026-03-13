# Installation & Operations Manual — Hatchloom Backend (Team Quebec)

**Project:** Hatchloom — Student Entrepreneurship Platform
**Team:** Quebec
**Last updated:** 2026-03-13

---

## Table of Contents

1. [System Requirements](#1-system-requirements)
2. [Development Setup (Laravel Sail)](#2-development-setup-laravel-sail)
3. [Environment Variables Reference](#3-environment-variables-reference)
4. [Database Migrations](#4-database-migrations)
5. [Running the Test Suite](#5-running-the-test-suite)
6. [Production Deployment (Docker)](#6-production-deployment-docker)
7. [API Quick Reference](#7-api-quick-reference)
8. [Troubleshooting](#8-troubleshooting)
9. [Known Issues](#9-known-issues)

---

## 1. System Requirements

### For Development (Laravel Sail)

Sail handles all PHP, PostgreSQL, and Node.js versions inside containers — you only need the tools below on your machine.

| Tool | Minimum Version | Notes |
|---|---|---|
| **Docker Desktop** | 4.x | Includes Docker Engine + Compose v2 |
| **Git** | 2.x | For cloning the repository |
| **PHP** | 8.2 | Only needed if running without Sail (optional) |
| **Composer** | 2.x | Only needed if running without Sail (optional) |

> **Windows users:** Enable WSL 2 in Docker Desktop settings before starting.

### For Production Deployment

| Tool | Minimum Version |
|---|---|
| **Docker Engine** | 24.x |
| **Docker Compose** | v2.x (`docker compose`, not `docker-compose`) |
| **OpenSSL** | Any (used to generate `APP_KEY`) |

### Runtime Versions (inside the container)

| Component | Version |
|---|---|
| PHP | 8.2 (php:8.2-fpm-alpine) |
| PostgreSQL | 18 (postgres:18-alpine) |
| Nginx | Latest stable (Alpine package) |
| Composer | 2.x |
| Laravel | 12.x |

---

## 2. Development Setup (Laravel Sail)

### Step 1 — Clone the repository

```bash
git clone <repository-url> hatchloom-quebec
cd hatchloom-quebec
```

### Step 2 — Install PHP dependencies

Run Composer in a temporary Docker container (no local PHP required):

```bash
docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v "$(pwd):/var/www/html" \
  -w /var/www/html \
  laravelsail/php82-composer:latest \
  composer install --ignore-platform-reqs
```

### Step 3 — Configure environment

Copy the example environment file:

```bash
cp .env.example .env
```

The defaults in `.env.example` are pre-configured for Sail. The following values work out of the box:

```
DB_CONNECTION=pgsql
DB_HOST=pgsql          # Sail's internal service name
DB_PORT=5432
DB_DATABASE=hatchloom
DB_USERNAME=sail
DB_PASSWORD=secret
```

### Step 4 — Start Sail

```bash
./vendor/bin/sail up -d
```

This starts two containers:
- `laravel.test` — PHP 8.2 + Nginx
- `pgsql` — PostgreSQL 18

Wait ~10 seconds for PostgreSQL to finish initializing, then verify both are running:

```bash
docker ps
```

### Step 5 — Generate application key

```bash
./vendor/bin/sail artisan key:generate
```

This writes `APP_KEY=base64:...` into your `.env` file.

### Step 6 — Run database migrations

```bash
./vendor/bin/sail artisan migrate
```

This creates all tables across Q1, Q2, and Q3:

```
users, password_reset_tokens, cache, jobs, sessions
sandboxes, side_hustles, business_model_canvases
teams, team_members, positions
feed_items, feed_actions, classified_posts
threads, thread_participants, messages
```

### Step 7 — Verify the application is running

```bash
curl http://localhost/api/feed
# Expected: {"message":"Unauthenticated."} (401)
# This confirms Nginx, PHP-FPM, and auth middleware are all working.
```

### Stopping Sail

```bash
./vendor/bin/sail down
# To also remove the database volume (wipes all data):
./vendor/bin/sail down -v
```

---

## 3. Environment Variables Reference

All variables are set in `.env` (development) or passed via `docker compose` environment keys (production).

### Required — Application

| Variable | Example | Description |
|---|---|---|
| `APP_NAME` | `Hatchloom` | Display name |
| `APP_ENV` | `local` / `production` | Environment mode |
| `APP_KEY` | `base64:abc...` | 32-byte encryption key — **generate with `artisan key:generate`** |
| `APP_DEBUG` | `true` / `false` | Show debug errors — **must be `false` in production** |
| `APP_URL` | `http://localhost` | Full base URL |

### Required — Database

| Variable | Example | Description |
|---|---|---|
| `DB_CONNECTION` | `pgsql` | Must be `pgsql` — SQLite is not supported |
| `DB_HOST` | `pgsql` (dev) / `db` (prod) | Hostname of the PostgreSQL container |
| `DB_PORT` | `5432` | PostgreSQL port |
| `DB_DATABASE` | `hatchloom` | Database name |
| `DB_USERNAME` | `sail` | PostgreSQL username |
| `DB_PASSWORD` | `secret` | PostgreSQL password — use a strong value in production |

### Optional — Session / Cache / Queue

| Variable | Default | Notes |
|---|---|---|
| `SESSION_DRIVER` | `database` | Stores sessions in the `sessions` table |
| `CACHE_STORE` | `database` | Stores cache in the `cache` table |
| `QUEUE_CONNECTION` | `database` | Stores jobs in the `jobs` table |
| `LOG_CHANNEL` | `stack` | Use `stderr` in production for Docker log forwarding |
| `LOG_LEVEL` | `debug` | Use `error` in production |

### Auth (Sanctum)

| Variable | Example | Notes |
|---|---|---|
| `SANCTUM_STATEFUL_DOMAINS` | `localhost` | Frontend domain(s) that use cookie-based auth |

---

## 4. Database Migrations

### Run all migrations

```bash
# Development (Sail)
./vendor/bin/sail artisan migrate

# Production (Docker)
docker exec <app-container-name> php artisan migrate --force
```

### Roll back

```bash
./vendor/bin/sail artisan migrate:rollback
```

### Fresh install (drops all tables and re-runs)

```bash
./vendor/bin/sail artisan migrate:fresh
```

### Seed with test data (if seeders are available)

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

### Migration file order

Migrations run in this order (filename-sorted):

```
0001_01_01_000000  — users, password_reset_tokens, sessions
0001_01_01_000001  — cache
0001_01_01_000002  — jobs
2026_03_05_...     — sandboxes
2026_03_05_...     — side_hustles
2026_03_05_...     — business_model_canvases
2026_03_05_...     — teams
2026_03_05_...     — team_members
2026_03_05_...     — positions
2026_03_13_000001  — feed_items
2026_03_13_000002  — feed_actions
2026_03_13_000003  — classified_posts
2026_03_13_000004  — threads
2026_03_13_000005  — thread_participants
2026_03_13_000006  — messages
```

---

## 5. Running the Test Suite

### Run all tests

```bash
./vendor/bin/sail artisan test
```

Expected output:

```
Tests: 68 passed (157 assertions)
Duration: ~3s
```

### Run a specific test group

```bash
# ConnectHub only
./vendor/bin/sail artisan test --filter=ConnectHub

# Single test class
./vendor/bin/sail artisan test tests/Feature/ConnectHub/FeedTest.php

# Single test method
./vendor/bin/sail artisan test --filter=test_user_can_like_a_post
```

### Test suite layout

```
tests/
├── Unit/
│   ├── ExampleTest.php
│   └── PostFactoryTest.php          ← Factory pattern unit tests (9 methods)
└── Feature/
    ├── Auth/
    │   ├── AuthenticationTest.php
    │   ├── EmailVerificationTest.php
    │   ├── PasswordConfirmationTest.php
    │   ├── PasswordResetTest.php
    │   └── PasswordUpdateTest.php
    ├── ConnectHub/
    │   ├── FeedTest.php              ← 11 tests (feed CRUD, events, auth guard)
    │   ├── ClassifiedPostTest.php    ← 11 tests (lifecycle, ownership, filters)
    │   └── MessageTest.php           ← 12 tests (threads, messages, events)
    ├── ExampleTest.php
    ├── ProfileTest.php
    └── RegistrationTest.php
```

### How the test database works

`phpunit.xml` automatically overrides these values during test runs — no manual configuration needed:

| Setting | Test value |
|---|---|
| `APP_ENV` | `testing` |
| `DB_DATABASE` | `testing` |
| `SESSION_DRIVER` | `array` |
| `CACHE_STORE` | `array` |
| `QUEUE_CONNECTION` | `sync` |

The `RefreshDatabase` trait used in all test classes wraps each test in a transaction and rolls it back after — no persistent test data is written.

---

## 6. Production Deployment (Docker)

### Step 1 — Prepare your environment file

Create a `.env.prod` file (do **not** commit this file):

```bash
cp .env.example .env.prod
```

Edit `.env.prod` with production values:

```dotenv
APP_NAME=Hatchloom
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-server-ip:8080

APP_KEY=            # fill in step 2

DB_CONNECTION=pgsql
DB_HOST=db          # Docker Compose service name — do not change
DB_PORT=5432
DB_DATABASE=hatchloom
DB_USERNAME=sail
DB_PASSWORD=choose-a-strong-password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
LOG_CHANNEL=stderr
LOG_LEVEL=error
```

### Step 2 — Generate APP_KEY

```bash
# If Sail is available:
./vendor/bin/sail artisan key:generate --show

# Without Sail (requires local PHP):
php artisan key:generate --show

# Without PHP at all:
echo "base64:$(openssl rand -base64 32)"
```

Paste the output (`base64:...`) as the value of `APP_KEY` in `.env.prod`.

### Step 3 — Build the Docker image

```bash
docker build -t hatchloom-connecthub:latest .
```

This runs the multi-stage Dockerfile:
1. Stage 1: installs Composer production dependencies
2. Stage 2: builds the production image (PHP 8.2-fpm + Nginx + Supervisor)

### Step 4 — Start the stack

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
```

On first startup, the container automatically:
1. Warms config, route, and view caches
2. Runs `php artisan migrate --force` — creates all tables in the production database

### Step 5 — Verify the deployment

```bash
# Check containers are running
docker compose -f docker-compose.prod.yml ps

# Confirm the API is live (expect 401 Unauthenticated)
curl http://localhost:8080/api/feed

# View application logs
docker compose -f docker-compose.prod.yml logs app

# View database logs
docker compose -f docker-compose.prod.yml logs db
```

### Step 6 — Create your first user

Use the Auth registration endpoint:

```bash
curl -s -X POST http://localhost:8080/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Alice","email":"alice@example.com","password":"password","password_confirmation":"password"}'
```

### Stopping the stack

```bash
# Stop containers (preserves database volume)
docker compose -f docker-compose.prod.yml down

# Stop containers and delete all data
docker compose -f docker-compose.prod.yml down -v
```

### Updating the application

```bash
# Pull latest code
git pull

# Rebuild the image
docker build -t hatchloom-connecthub:latest .

# Restart the app container (db keeps running, migrations run on startup)
docker compose -f docker-compose.prod.yml up -d --no-deps --build app
```

### Running artisan commands in production

```bash
# General
docker exec <app-container-name> php artisan <command>

# Examples
docker exec <app-container-name> php artisan migrate:status
docker exec <app-container-name> php artisan tinker
docker exec <app-container-name> php artisan cache:clear
```

Find the container name with: `docker compose -f docker-compose.prod.yml ps`

---

## 7. API Quick Reference

All ConnectHub endpoints require `Authorization: Bearer {token}`.
Obtain a token by calling `POST /login`.

| Method | URL | Description |
|---|---|---|
| `POST` | `/register` | Register a new user |
| `POST` | `/login` | Login — returns session token |
| `POST` | `/logout` | Invalidates session token |
| `GET` | `/api/feed` | Social feed (newest first) |
| `POST` | `/api/feed` | Create feed post |
| `POST` | `/api/feed/{id}/like` | Like a post |
| `POST` | `/api/feed/{id}/comment` | Comment on a post |
| `GET` | `/api/classifieds` | List classifieds (`?status=OPEN\|FILLED\|CLOSED`) |
| `POST` | `/api/classifieds` | Create classified post |
| `GET` | `/api/classifieds/{id}` | View single classified |
| `PATCH` | `/api/classifieds/{id}/status` | Update status (owner only) |
| `GET` | `/api/threads` | List user's threads |
| `POST` | `/api/threads` | Create / get existing thread |
| `GET` | `/api/threads/{id}/messages` | Get messages (chronological) |
| `POST` | `/api/threads/{id}/messages` | Send a message |

See `docs/API_DOCUMENTATION.md` for full request/response details for every endpoint.

---

## 8. Troubleshooting

### Container fails to start

**Symptom:** `docker compose up` exits immediately.
**Diagnosis:**
```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod logs app
```
**Common causes:**
- `APP_KEY` is empty or malformed — regenerate it (see Step 2 above)
- Database is not reachable — ensure the `db` service started successfully and the healthcheck passed

---

### 500 Internal Server Error on all requests

**Symptom:** API returns `500` with no useful message.
**Steps:**
1. Check `APP_DEBUG=false` is set — if `true`, you will see the full error in the response.
2. Temporarily set `APP_DEBUG=true` and restart to expose the exception.
3. Check storage permissions:
   ```bash
   docker exec <app-container> chown -R www-data:www-data /var/www/html/storage
   ```

---

### 401 Unauthenticated on all requests

**Symptom:** Every request returns `{"message":"Unauthenticated."}` even with a token.
**Common causes:**
- Token was issued before `APP_KEY` changed — log in again to get a new token
- Missing `Authorization: Bearer {token}` header — ensure the header is present and uses the word `Bearer`

---

### Database connection refused

**Symptom:** `SQLSTATE[08006] ... Connection refused`
**In development:** Wait ~10 seconds after `sail up` and retry — PostgreSQL is still initializing.
**In production:** Check `DB_HOST=db` (the Docker Compose service name, not `localhost`).

---

### Tests fail with "database not found"

**Symptom:** `SQLSTATE[3D000]: Invalid catalog name: database "testing" does not exist`
**Fix:** In development with Sail, the Sail init script creates a `testing` database automatically. If it's missing:
```bash
./vendor/bin/sail artisan migrate --database=testing
```

---

### Port 8080 already in use (production)

**Symptom:** `Error starting userland proxy: listen tcp4 0.0.0.0:8080: bind: address already in use`
**Fix:** Change the host port in `docker-compose.prod.yml`:
```yaml
ports:
  - "8181:8080"   # use any available host port
```

---

## 9. Known Issues

| # | Issue | Workaround |
|---|---|---|
| 1 | LaunchPad routes (`/api/sandboxes`, `/api/sidehustles`, etc.) are not behind `auth:sanctum` middleware — any unauthenticated request can read/write data. | Add `middleware('auth:sanctum')` to the LaunchPad route group in `routes/api.php`. Out of scope for Q3 but should be resolved before production handoff. |
| 2 | No pagination on `GET /api/feed` — all feed items are returned in a single response. Large datasets will cause slow responses. | Add `->paginate(20)` in `FeedController::index()`. |
| 3 | No queue worker is running inside the Docker container — events (`FeedPostCreated`, `MessageSent`) are dispatched synchronously. | `QUEUE_CONNECTION=sync` (set in `docker-compose.prod.yml`) ensures events fire synchronously. Add a queue worker if async processing is needed. |
| 4 | Thread deduplication only applies to direct threads (`context_type = null`). Two users can have multiple context-scoped threads for the same context. | By design — context threads are intentionally not deduplicated. |
