# ConnectHub Service — Installation and Operations Manual

**Project:** Hatchloom — Team Quebec (Q3)
**Version:** 1.0.0
**Last updated:** 2026-03-13

This manual covers everything needed to install, configure, run, and deploy the ConnectHub service on a new machine. Follow each section in order on a fresh system.

---

## Table of Contents

1. [System Requirements](#1-system-requirements)
2. [Getting the Code](#2-getting-the-code)
3. [Environment Configuration](#3-environment-configuration)
4. [Development Setup (Laravel Sail)](#4-development-setup-laravel-sail)
5. [Running Tests](#5-running-tests)
6. [Production Deployment (Docker)](#6-production-deployment-docker)
7. [Environment Variable Reference](#7-environment-variable-reference)
8. [API Quick Reference](#8-api-quick-reference)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. System Requirements

### Development (Laravel Sail)

| Dependency | Minimum Version | Notes |
|---|---|---|
| Docker Desktop | 4.x | Required for Laravel Sail |
| Docker Compose | v2.x | Bundled with Docker Desktop |
| Git | 2.x | For cloning the repository |
| PHP | 8.2 | Only needed to run `composer install` outside Sail |
| Composer | 2.x | Only needed to install Sail before first run |

> **Note:** Sail runs PHP 8.2, PostgreSQL 18, and all other dependencies inside Docker containers. You do **not** need PHP or PostgreSQL installed on your host machine if you bootstrap via Sail.

### Production

| Dependency | Minimum Version | Notes |
|---|---|---|
| Docker Engine | 24.x | Docker Desktop or Docker CE on Linux |
| Docker Compose | v2.x | |

No other server software (PHP, Nginx, PostgreSQL) needs to be installed on the host — everything runs inside containers.

---

## 2. Getting the Code

Clone the repository and enter the project directory:

```bash
git clone <repository-url> hatchloom-quebec
cd hatchloom-quebec
```

---

## 3. Environment Configuration

### 3.1 Copy the example environment file

```bash
cp .env.example .env
```

### 3.2 Minimum required changes for development

Open `.env` and set at minimum:

```env
APP_NAME=Hatchloom
APP_ENV=local
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=pgsql          # Sail's PostgreSQL service name
DB_PORT=5432
DB_DATABASE=hatchloom
DB_USERNAME=sail
DB_PASSWORD=password
```

The `APP_KEY` is generated automatically in the next step — leave it blank for now.

### 3.3 Full environment variable reference

See [Section 7](#7-environment-variable-reference) for a complete table of all variables.

---

## 4. Development Setup (Laravel Sail)

### 4.1 Install PHP dependencies

If this is the first time running the project, install Composer dependencies using a temporary Docker container (no local PHP required):

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs
```

If you already have PHP 8.2 and Composer installed locally:

```bash
composer install
```

### 4.2 Start the development environment

```bash
./vendor/bin/sail up -d
```

This starts:
- `laravel.test` — PHP 8.2 application container (port 80)
- `pgsql` — PostgreSQL 18 container (port 5432)

Wait a few seconds for both containers to become healthy.

### 4.3 Generate the application key

```bash
./vendor/bin/sail artisan key:generate
```

This writes `APP_KEY=base64:...` into your `.env` file.

### 4.4 Run database migrations

```bash
./vendor/bin/sail artisan migrate
```

This creates all 12 tables (6 from Q1/Q2, 6 from Q3 ConnectHub). Expected output:

```
  INFO  Running migrations.

  2026_03_13_000001_create_feed_items_table ............ 12ms DONE
  2026_03_13_000002_create_feed_actions_table .......... 10ms DONE
  2026_03_13_000003_create_classified_posts_table ...... 11ms DONE
  2026_03_13_000004_create_threads_table ............... 9ms  DONE
  2026_03_13_000005_create_thread_participants_table ... 10ms DONE
  2026_03_13_000006_create_messages_table .............. 9ms  DONE
```

### 4.5 Verify the service is running

```bash
curl http://localhost/api/feed
```

Expected response (unauthenticated):

```json
{ "message": "Unauthenticated." }
```

A `401` response confirms the API is live and the `auth:sanctum` middleware is active.

### 4.6 Stop the environment

```bash
./vendor/bin/sail down
```

To remove all data (wipes the PostgreSQL volume):

```bash
./vendor/bin/sail down -v
```

---

## 5. Running Tests

All tests run against an in-memory configuration (`phpunit.xml` sets `SESSION_DRIVER=array`, `CACHE_STORE=array`). The database is refreshed between tests using `RefreshDatabase`.

### 5.1 Run the full test suite

```bash
./vendor/bin/sail artisan test
```

Expected output:

```
  Tests:    68 passed (157 assertions)
  Duration: ~3s
```

### 5.2 Run only ConnectHub tests

```bash
./vendor/bin/sail artisan test --filter=ConnectHub
```

### 5.3 Run only unit tests (Factory pattern)

```bash
./vendor/bin/sail artisan test --testsuite=Unit
```

### 5.4 Test file locations

| File | What it tests |
|---|---|
| `tests/Feature/ConnectHub/FeedTest.php` | Feed endpoints, Factory pattern, Observer event dispatch |
| `tests/Feature/ConnectHub/ClassifiedPostTest.php` | Classified CRUD, lifecycle guards, ownership enforcement |
| `tests/Feature/ConnectHub/MessageTest.php` | Thread creation, deduplication, participant guards, MessageSent event |
| `tests/Unit/PostFactoryTest.php` | PostFactory and concrete factories in isolation |

---

## 6. Production Deployment (Docker)

The production setup uses a multi-stage `Dockerfile` and `docker-compose.prod.yml`. The image bundles PHP 8.2-fpm, Nginx, and Supervisor into a single container.

### 6.1 Create a production environment file

```bash
cp .env.example .env.prod
```

Edit `.env.prod` with production values:

```env
APP_NAME=Hatchloom
APP_ENV=production
APP_DEBUG=false
APP_KEY=                      # generated below
APP_URL=http://your-domain-or-ip:8080

DB_CONNECTION=pgsql
DB_HOST=db                    # Docker Compose service name
DB_PORT=5432
DB_DATABASE=hatchloom
DB_USERNAME=<choose a username>
DB_PASSWORD=<strong password>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
LOG_CHANNEL=stderr
LOG_LEVEL=error
```

### 6.2 Generate the APP_KEY

If you have Sail running in development:

```bash
./vendor/bin/sail artisan key:generate --show
```

Otherwise use OpenSSL:

```bash
echo "base64:$(openssl rand -base64 32)"
```

Paste the output into `APP_KEY=` in `.env.prod`.

### 6.3 Build and start the production stack

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod up --build -d
```

This will:
1. Build the production Docker image (installs PHP, Composer deps, copies source)
2. Start the `db` (PostgreSQL 18) container and wait for it to pass its health check
3. Start the `app` container which automatically:
   - Runs `php artisan config:cache`
   - Runs `php artisan route:cache`
   - Runs `php artisan view:cache`
   - Runs `php artisan migrate --force`
   - Starts Supervisor (Nginx + PHP-FPM)

### 6.4 Verify the deployment

```bash
# Health check — should return 401 (API is live, Sanctum blocking unauthenticated)
curl http://localhost:8080/api/feed

# Check container logs
docker compose -f docker-compose.prod.yml logs app
```

### 6.5 Run a command inside the container

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate:status
```

### 6.6 Stop and teardown

```bash
# Stop (preserves database volume)
docker compose -f docker-compose.prod.yml down

# Full teardown including database data
docker compose -f docker-compose.prod.yml down -v
```

### 6.7 Rebuild after code changes

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod up --build -d
```

---

## 7. Environment Variable Reference

### Application

| Variable | Required | Default | Description |
|---|---|---|---|
| `APP_NAME` | No | `Laravel` | Application display name |
| `APP_ENV` | Yes | `local` | `local` for development, `production` for production |
| `APP_KEY` | **Yes** | — | Base64-encoded 32-byte key. Generate with `php artisan key:generate` |
| `APP_DEBUG` | No | `true` | Set to `false` in production |
| `APP_URL` | No | `http://localhost` | Full URL including port if non-standard |

### Database

| Variable | Required | Default | Description |
|---|---|---|---|
| `DB_CONNECTION` | Yes | `pgsql` | Must be `pgsql` |
| `DB_HOST` | Yes | `pgsql` (Sail) / `db` (prod) | Hostname of the PostgreSQL server |
| `DB_PORT` | No | `5432` | PostgreSQL port |
| `DB_DATABASE` | Yes | `hatchloom` | Database name |
| `DB_USERNAME` | Yes | `sail` | Database user |
| `DB_PASSWORD` | Yes | `password` | Database password |

### Session, Cache, Queue

| Variable | Required | Default | Description |
|---|---|---|---|
| `SESSION_DRIVER` | No | `database` | Use `array` for testing only |
| `CACHE_STORE` | No | `database` | Use `array` for testing only |
| `QUEUE_CONNECTION` | No | `database` | Use `sync` for testing only |

### Auth

| Variable | Required | Description |
|---|---|---|
| `SANCTUM_STATEFUL_DOMAINS` | No | Comma-separated domains for SPA cookie auth (not required for token-based API use) |

> **Testing overrides:** `phpunit.xml` automatically overrides `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`, and `DB_DATABASE` when running `php artisan test`. You do not need to change `.env` for tests.

---

## 8. API Quick Reference

All endpoints require: `Authorization: Bearer {token}`

Obtain a token via the Auth service:
```bash
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"secret"}'
```

### Feed

| Method | URL | Description |
|---|---|---|
| `GET` | `/api/feed` | Get all posts, newest first |
| `POST` | `/api/feed` | Create a post (`type`: share / announcement / achievement) |
| `POST` | `/api/feed/{id}/like` | Like a post |
| `POST` | `/api/feed/{id}/comment` | Comment on a post |

### Classifieds

| Method | URL | Description |
|---|---|---|
| `GET` | `/api/classifieds` | List classifieds (`?status=OPEN\|FILLED\|CLOSED`) |
| `POST` | `/api/classifieds` | Create a classified (must own the position's SideHustle) |
| `GET` | `/api/classifieds/{id}` | Get single classified |
| `PATCH` | `/api/classifieds/{id}/status` | Update status (owner only; `OPEN→FILLED` or `OPEN→CLOSED`) |

### Messaging

| Method | URL | Description |
|---|---|---|
| `GET` | `/api/threads` | List your threads |
| `POST` | `/api/threads` | Start a thread with a recipient |
| `GET` | `/api/threads/{id}/messages` | Get messages (participants only) |
| `POST` | `/api/threads/{id}/messages` | Send a message (participants only) |

For full request/response schemas see [`docs/API_DOCUMENTATION.md`](API_DOCUMENTATION.md).

---

## 9. Troubleshooting

### `APP_KEY` is missing or invalid

**Symptom:** `RuntimeException: No application encryption key has been specified.`

**Fix:**
```bash
# Development
./vendor/bin/sail artisan key:generate

# Production — add to .env.prod
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env.prod
```

---

### Database connection refused

**Symptom:** `SQLSTATE[08006] ... Connection refused`

**Fix — Development:** Ensure Sail is running:
```bash
./vendor/bin/sail up -d
# Wait 5–10 seconds for PostgreSQL to become ready, then:
./vendor/bin/sail artisan migrate
```

**Fix — Production:** Check that the `db` health check passed:
```bash
docker compose -f docker-compose.prod.yml ps
# The db container should show "healthy"
```

---

### Migrations fail: relation already exists

**Symptom:** `SQLSTATE[42P07]: Duplicate table`

**Fix:** The database already has the tables from a previous run. Either use a fresh database or run:
```bash
./vendor/bin/sail artisan migrate:fresh
```

> **Warning:** `migrate:fresh` drops all tables and re-runs all migrations. Do not use in production.

---

### Production container exits immediately

**Symptom:** `docker compose logs app` shows the container restarting.

**Cause:** Most commonly a missing `APP_KEY` or unreachable database.

**Fix:**
1. Confirm `APP_KEY` is set in `.env.prod`
2. Confirm `db` container is healthy: `docker compose -f docker-compose.prod.yml ps`
3. Check the full log: `docker compose -f docker-compose.prod.yml logs --tail=50 app`

---

### `401 Unauthenticated` on every ConnectHub request

This is expected for unauthenticated requests — the API is working correctly. To make authenticated requests:

1. Register a user via `POST /register` (Auth service)
2. Log in via `POST /login` to receive a bearer token
3. Include `Authorization: Bearer {token}` on every ConnectHub request

---

### Tests fail — `could not find driver`

**Symptom:** `PDOException: could not find driver (pgsql)`

**Fix:** You are running `php artisan test` outside of Sail. Always run tests through Sail:
```bash
./vendor/bin/sail artisan test
```
