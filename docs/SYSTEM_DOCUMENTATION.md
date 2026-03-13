# System Documentation — Hatchloom Backend (Team Quebec)

**Project:** Hatchloom — Student Entrepreneurship Platform
**Team:** Quebec (Andrew, Anthony, Daniel, Ronald)
**Scope:** Auth & User Profiles (Q1) · LaunchPad (Q2) · ConnectHub (Q3)
**Stack:** Laravel 12 · PHP 8.2 · PostgreSQL 18 · Laravel Sanctum · Inertia.js + React

---

## Table of Contents

1. [Overview](#1-overview)
2. [High-Level Architecture](#2-high-level-architecture)
3. [Service Breakdown](#3-service-breakdown)
   - [Q1 — Auth & User Profile](#q1--auth--user-profile-service)
   - [Q2 — LaunchPad](#q2--launchpad-service)
   - [Q3 — ConnectHub](#q3--connecthub-service)
4. [Database Schema](#4-database-schema)
5. [Design Patterns](#5-design-patterns)
6. [Data Flow & Sequence Diagrams](#6-data-flow--sequence-diagrams)
7. [Cross-Service Dependencies](#7-cross-service-dependencies)
8. [Events & Observer System](#8-events--observer-system)
9. [Docker & Deployment Architecture](#9-docker--deployment-architecture)
10. [CI/CD Pipeline](#10-cicd-pipeline)

---

## 1. Overview

Hatchloom is a web platform that enables secondary-school students to explore entrepreneurship.
Team Quebec owns the entire backend, broken into three tightly coupled sub-packs:

| Sub-Pack | Service | Responsibility |
|---|---|---|
| Q1 | Auth & User Profile | User identity, sessions (Sanctum tokens), RBAC |
| Q2 | LaunchPad | Sandbox workspaces, SideHustle ventures, BMC, Team, Positions |
| Q3 | ConnectHub | Social feed, classified ads, direct messaging |

All three services run inside a single Laravel 12 application backed by one PostgreSQL 18 database. They are deployed together as a single Docker container (with a sidecar PostgreSQL container), managed by `docker-compose.prod.yml`.

---

## 2. High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     CLIENT (Browser / App)                   │
└──────────────────────────┬──────────────────────────────────┘
                           │ HTTP
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                  Nginx (port 8080)                           │
│         Reverse proxy → PHP-FPM (port 9000)                 │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                 Laravel 12 Application                       │
│                                                              │
│  ┌────────────┐   ┌──────────────┐   ┌──────────────────┐  │
│  │  Q1: Auth  │   │ Q2:LaunchPad │   │  Q3: ConnectHub  │  │
│  │            │   │              │   │                  │  │
│  │ Sanctum    │   │ Sandbox      │   │ Feed (Factory +  │  │
│  │ Middleware │   │ SideHustle   │   │   Observer)      │  │
│  │ RBAC       │   │ BMC          │   │ Classifieds      │  │
│  │            │   │ Team         │   │ Messaging        │  │
│  │            │   │ Positions    │   │                  │  │
│  └─────┬──────┘   └──────┬───────┘   └────────┬─────────┘  │
│        │                 │                    │             │
│        └─────────────────┼────────────────────┘             │
│                          │                                   │
│              ┌───────────▼──────────┐                       │
│              │   Event Bus          │                       │
│              │ FeedPostCreated      │                       │
│              │ ClassifiedPostCreated│                       │
│              │ MessageSent          │                       │
│              └──────────────────────┘                       │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              PostgreSQL 18 Database                          │
│                                                              │
│  users · sessions · sandboxes · side_hustles · bmcs         │
│  teams · team_members · positions                            │
│  feed_items · feed_actions                                   │
│  classified_posts · threads · thread_participants · messages │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Service Breakdown

### Q1 — Auth & User Profile Service

**Purpose:** Provides user identity for every API call across the entire platform. No other service functions without a valid session token.

**Key Components:**

| Component | Location | Responsibility |
|---|---|---|
| `User` model | `app/Models/User.php` | Eloquent model; polymorphic roles (student, teacher, parent, school admin, entrepreneur) |
| Sanctum middleware | `bootstrap/app.php` | Validates `Authorization: Bearer {token}` on every protected route |
| Auth controllers | `app/Http/Controllers/Auth/` | Registration, login, password reset, email verification |
| Profile controller | `app/Http/Controllers/ProfileController.php` | CRUD for user profile |
| Auth routes | `routes/auth.php` | Registration, login, logout, password flows |

**Interfaces exposed:**

```
POST   /register              — Create new user account
POST   /login                 — Authenticate and receive session token
POST   /logout                — Invalidate session token
GET    /user                  — Get authenticated user profile
PATCH  /profile               — Update profile
DELETE /profile               — Delete account
```

**Design Patterns used:** Singleton (SessionManager via Sanctum), Strategy (RBAC role resolution)

---

### Q2 — LaunchPad Service

**Purpose:** Manages the entrepreneurship workspace. Students progress from a Sandbox (experimentation) to a SideHustle (live venture).

**Key Components:**

| Component | Location | Responsibility |
|---|---|---|
| `Sandbox` model | `app/Models/Sandbox.php` | Workspace record; belongs to a student |
| `SideHustle` model | `app/Models/SideHustle.php` | Live venture; owns BMC, Team, Positions |
| `BusinessModelCanvas` model | `app/Models/BusinessModelCanvas.php` | 9-section BMC tool; one per SideHustle |
| `Team` / `TeamMember` models | `app/Models/Team.php` | Team roster attached to a SideHustle |
| `Position` model | `app/Models/Position.php` | Open role within a SideHustle; read by ConnectHub Classifieds |
| Controllers | `app/Http/Controllers/` | `SandboxController`, `SideHustleController`, `BusinessModelCanvasController`, `TeamController`, `PositionController` |
| LaunchPad routes | `routes/api.php` | `/sandboxes`, `/sidehustles`, `/sidehustles/{id}/bmc`, `/teams`, `/positions` |

**Interfaces exposed:**

```
GET/POST        /api/sandboxes                   — List / create sandboxes
GET/PUT/DELETE  /api/sandboxes/{id}              — Manage single sandbox
POST            /api/sandboxes/{id}/launch        — Promote sandbox to SideHustle
GET/POST        /api/sidehustles                 — List / create SideHustles
GET/PUT/DELETE  /api/sidehustles/{id}            — Manage single SideHustle
GET/PUT         /api/sidehustles/{id}/bmc        — Read / update BMC sections
GET             /api/teams/{sideHustleId}         — Get team roster
POST            /api/teams/{teamId}/members       — Add team member
DELETE          /api/teams/{teamId}/members/{id}  — Remove team member
GET             /api/positions/{sideHustleId}     — List positions
POST            /api/positions                    — Create position
PUT             /api/positions/{id}               — Update position status
```

---

### Q3 — ConnectHub Service

**Purpose:** Social layer of the platform. Enables feed sharing, classified job postings, and direct messaging between students.

**Key Components:**

| Component | Location | Responsibility |
|---|---|---|
| `FeedController` | `app/Http/Controllers/FeedController.php` | Feed CRUD; delegates creation to PostFactory |
| `ClassifiedPostController` | `app/Http/Controllers/ClassifiedPostController.php` | Classified lifecycle; enforces ownership + status transitions |
| `MessageController` | `app/Http/Controllers/MessageController.php` | Thread deduplication; participant guard; message send |
| `PostFactory` | `app/Services/PostFactory.php` | Abstract factory; dispatches to concrete factories by type |
| `ShareFactory` | `app/Services/ShareFactory.php` | Creates `type=share` FeedItems; requires `metadata.shareLink` |
| `AnnouncementFactory` | `app/Services/AnnouncementFactory.php` | Creates `type=announcement`; requires `metadata.announcementDate` |
| `AchievementFactory` | `app/Services/AchievementFactory.php` | Creates `type=achievement`; requires `metadata.achievementName` |
| `FeedPostCreated` event | `app/Events/FeedPostCreated.php` | Dispatched on every feed store |
| `ClassifiedPostCreated` event | `app/Events/ClassifiedPostCreated.php` | Dispatched when a classified post is created |
| `MessageSent` event | `app/Events/MessageSent.php` | Dispatched after every message send |
| `NotifyFeedObservers` listener | `app/Listeners/NotifyFeedObservers.php` | Observer; reacts to `FeedPostCreated` |

**Interfaces exposed (all require `Authorization: Bearer {token}`):**

```
GET    /api/feed                              — Social feed (newest first)
POST   /api/feed                              — Create feed post via factory
POST   /api/feed/{feedItem}/like              — Like a post (409 on duplicate)
POST   /api/feed/{feedItem}/comment           — Comment on a post
GET    /api/classifieds                       — List classifieds (?status= filter)
POST   /api/classifieds                       — Create classified post
GET    /api/classifieds/{classifiedPost}      — View single classified
PATCH  /api/classifieds/{classifiedPost}/status — Update status (owner only)
GET    /api/threads                           — List user's threads
POST   /api/threads                           — Create thread (deduplication applied)
GET    /api/threads/{thread}/messages         — Get messages (chronological)
POST   /api/threads/{thread}/messages         — Send message
```

---

## 4. Database Schema

### Entity-Relationship Overview

```
users ──────────────────────────────────────────────────────────────────┐
  │                                                                      │
  ├──< sandboxes                                                         │
  │                                                                      │
  ├──< side_hustles ──< business_model_canvases                         │
  │         │                                                            │
  │         ├──< teams ──< team_members >── users                       │
  │         │                                                            │
  │         └──< positions ──< classified_posts >── users (author)      │
  │                                                                      │
  ├──< feed_items ──< feed_actions >── users                            │
  │                                                                      │
  └──< thread_participants >── threads ──< messages >── users (sender)  │
                                                                         │
────────────────────────────────────────────────────────────────────────┘
```

### Table Definitions

**`users`** — Core identity table (Q1)
```
id, name, email, password, email_verified_at, remember_token,
created_at, updated_at
```

**`sandboxes`** — Experimentation workspace (Q2)
```
id, student_id (FK users), name, description,
created_at, updated_at
```

**`side_hustles`** — Live ventures (Q2)
```
id, student_id (FK users), sandbox_id (FK sandboxes, nullable),
name, description, created_at, updated_at
```

**`business_model_canvases`** — One per SideHustle (Q2)
```
id, side_hustle_id (FK), key_partners, key_activities, key_resources,
value_propositions, customer_relationships, channels,
customer_segments, cost_structure, revenue_streams,
created_at, updated_at
```

**`teams` / `team_members`** — Roster (Q2)
```
teams: id, side_hustle_id (FK), created_at, updated_at
team_members: id, team_id (FK), user_id (FK), role, created_at, updated_at
```

**`positions`** — Open roles (Q2, read by Q3)
```
id, side_hustle_id (FK), title, description, status (OPEN/FILLED),
created_at, updated_at
```

**`feed_items`** — Social feed posts (Q3)
```
id, user_id (FK users), type (share|announcement|achievement),
title (nullable), content, metadata (JSON), created_at, updated_at
```

**`feed_actions`** — Likes and comments (Q3)
```
id, feed_item_id (FK), user_id (FK), action_type (like|comment),
content (nullable), created_at, updated_at
UNIQUE (feed_item_id, user_id, action_type='like')
```

**`classified_posts`** — Job listings (Q3)
```
id, position_id (FK positions), side_hustle_id (FK), author_id (FK users),
title, content, status (OPEN|FILLED|CLOSED), created_at, updated_at
```

**`threads`** — Message conversations (Q3)
```
id, context_type (nullable), context_id (nullable),
created_at, updated_at
```

**`thread_participants`** — Pivot: user ↔ thread (Q3)
```
thread_id (FK), user_id (FK)
```

**`messages`** — Individual messages (Q3)
```
id, thread_id (FK), sender_id (FK users), content,
created_at, updated_at
```

---

## 5. Design Patterns

### Factory Pattern — Post Creation

`PostFactory` is the **sole creator** of `FeedItem` records. Controllers must never call `FeedItem::create()` directly.

```
PostFactory (abstract)
    │
    ├── make(type, data, author) : FeedItem
    │       ↓
    │   match(type) {
    │       'share'        → ShareFactory::create()
    │       'announcement' → AnnouncementFactory::create()
    │       'achievement'  → AchievementFactory::create()
    │       default        → InvalidArgumentException
    │   }
    │
    ├── ShareFactory        — requires metadata.shareLink
    ├── AnnouncementFactory — requires metadata.announcementDate
    └── AchievementFactory  — requires metadata.achievementName
```

**Benefit:** Adding a new post type (e.g. `poll`) requires only a new concrete factory class and one new `match` arm — no controller changes.

---

### Observer Pattern — Feed & Message Events

Three events are dispatched to decouple side-effects from the core request lifecycle:

```
FeedController::store()
    └── PostFactory::make(...)  → FeedItem persisted
    └── FeedPostCreated::dispatch($feedItem)
            └── NotifyFeedObservers::handle()   ← listener

ClassifiedPostController::store()
    └── ClassifiedPost::create(...)
    └── ClassifiedPostCreated::dispatch($classified)

MessageController::storeMessage()
    └── Message::create(...)
    └── MessageSent::dispatch($message)
```

**Benefit:** Additional side-effects (push notifications, activity logs, email digests) can be added by registering new listeners to existing events — no controller changes required.

---

### Strategy Pattern — RBAC

Role resolution is a strategy applied at the session validation layer. Sanctum middleware resolves the authenticated user's role and makes it available to controllers via `$request->user()`. Ownership guards (classified post author check, thread participant check) apply the correct strategy at the resource level.

---

### Singleton Pattern — Session Management

Laravel Sanctum acts as a Singleton session manager. A single, shared session store validates every token across all three services. There is no duplicated session logic in any sub-pack.

---

## 6. Data Flow & Sequence Diagrams

### Creating a Feed Post

```
Client                    Nginx              Laravel              PostFactory          DB
  │                         │                   │                     │                │
  │  POST /api/feed         │                   │                     │                │
  │  Bearer: {token}        │                   │                     │                │
  ├────────────────────────►│                   │                     │                │
  │                         │  PHP-FPM proxy    │                     │                │
  │                         ├──────────────────►│                     │                │
  │                         │                   │ auth:sanctum        │                │
  │                         │                   │ validate token      │                │
  │                         │                   ├────────────────────────────────────► │
  │                         │                   │ users.find(id)      │                │
  │                         │                   │◄────────────────────────────────────┤│
  │                         │                   │                     │                │
  │                         │                   │ validate request    │                │
  │                         │                   │ (type, content,     │                │
  │                         │                   │  metadata)          │                │
  │                         │                   │                     │                │
  │                         │                   │ PostFactory::make() │                │
  │                         │                   ├────────────────────►│                │
  │                         │                   │                     │ match(type)    │
  │                         │                   │                     │ ShareFactory   │
  │                         │                   │                     │ .create()      │
  │                         │                   │                     ├───────────────►│
  │                         │                   │                     │ INSERT         │
  │                         │                   │                     │ feed_items     │
  │                         │                   │                     │◄───────────────┤
  │                         │                   │◄────────────────────┤ FeedItem       │
  │                         │                   │                     │                │
  │                         │                   │ FeedPostCreated     │                │
  │                         │                   │ ::dispatch()        │                │
  │                         │                   │ → NotifyFeedObservers                │
  │                         │                   │                     │                │
  │  201 Created            │                   │                     │                │
  │◄────────────────────────┤◄──────────────────┤                     │                │
```

---

### Updating a Classified Post Status

```
Client                      FeedController          ClassifiedPost          DB
  │                               │                       │                  │
  │  PATCH /api/classifieds/1/    │                       │                  │
  │  status  { status: FILLED }   │                       │                  │
  ├──────────────────────────────►│                       │                  │
  │                               │ auth:sanctum          │                  │
  │                               │ ownership guard       │                  │
  │                               │ author_id === user.id │                  │
  │                               │                       │                  │
  │                               │ canTransitionTo(FILLED)                  │
  │                               ├──────────────────────►│                  │
  │                               │                       │ current=OPEN?    │
  │                               │                       ├─────────────────►│
  │                               │                       │◄─────────────────┤
  │                               │                       │ ✓ valid          │
  │                               │◄──────────────────────┤                  │
  │                               │                       │                  │
  │                               │ UPDATE status=FILLED  │                  │
  │                               ├──────────────────────►├─────────────────►│
  │                               │                       │◄─────────────────┤
  │  200 OK { status: FILLED }    │                       │                  │
  │◄──────────────────────────────┤                       │                  │
```

---

### Sending a Message

```
Client                   MessageController         Thread          DB          Event Bus
  │                            │                     │              │               │
  │ POST /threads/1/messages   │                     │              │               │
  │ { content: "Hi!" }         │                     │              │               │
  ├───────────────────────────►│                     │              │               │
  │                            │ auth:sanctum        │              │               │
  │                            │                     │              │               │
  │                            │ thread.participants │              │               │
  │                            │ .contains(user.id)? │              │               │
  │                            ├────────────────────►│              │               │
  │                            │◄────────────────────┤              │               │
  │                            │ ✓ participant       │              │               │
  │                            │                     │              │               │
  │                            │ Message::create()   │              │               │
  │                            ├──────────────────────────────────►│               │
  │                            │◄──────────────────────────────────┤               │
  │                            │                     │              │               │
  │                            │ MessageSent::dispatch($message)   │               │
  │                            ├──────────────────────────────────────────────────►│
  │                            │                     │              │               │
  │ 201 Created                │                     │              │               │
  │◄───────────────────────────┤                     │              │               │
```

---

## 7. Cross-Service Dependencies

```
┌──────────────────┐      Session token        ┌──────────────────────────┐
│   Q1: Auth       │ ─────────────────────────► │   Q3: ConnectHub         │
│   (Sanctum)      │  auth:sanctum middleware   │   All 12 routes require  │
└──────────────────┘  injects $request->user()  │   valid Sanctum token    │
                                                └──────────────────────────┘

┌──────────────────┐     positions table        ┌──────────────────────────┐
│   Q2: LaunchPad  │ ─────────────────────────► │   Q3: ConnectHub         │
│   (Positions)    │  shared PostgreSQL DB       │   ClassifiedPost must    │
└──────────────────┘  Position Status Interface  │   link to a Position     │
                                                │   owned by the req. user │
                                                └──────────────────────────┘
```

**Position Status Interface rule:** When `POST /api/classifieds` is called, the service reads directly from the shared `positions` table. The `position.sideHustle.student_id` must equal `$request->user()->id`. If not, `403 Forbidden` is returned. This enforces structural integrity between Q2 and Q3 without an inter-service HTTP call.

---

## 8. Events & Observer System

All three events are registered in `app/Providers/AppServiceProvider.php` (or `EventServiceProvider.php`). The Observer pattern decouples post-action side-effects from the core controller logic.

```
Event                   Dispatched by                   Listener(s)
─────────────────────────────────────────────────────────────────────
FeedPostCreated         FeedController::store()         NotifyFeedObservers
ClassifiedPostCreated   ClassifiedPostController::store()  (extensible)
MessageSent             MessageController::storeMessage()  (extensible)
```

Adding new side-effects (push notifications, Slack alerts, audit logs) requires only a new `Listener` class registered to the relevant event — no changes to any controller.

---

## 9. Docker & Deployment Architecture

### Container Overview

```
┌──────────────────────────────────────────────────────────┐
│  Docker host                                             │
│                                                          │
│  ┌──────────────────────────────────┐                   │
│  │  app (hatchloom-connecthub)      │  port 8080:8080   │
│  │                                  │◄──────────────────┤
│  │  Supervisor                      │                   │
│  │    ├── nginx (port 8080)         │                   │
│  │    └── php-fpm (port 9000)       │                   │
│  │                                  │                   │
│  │  Entrypoint (on start):          │                   │
│  │    1. php artisan config:cache   │                   │
│  │    2. php artisan route:cache    │                   │
│  │    3. php artisan view:cache     │                   │
│  │    4. php artisan migrate --force│                   │
│  └────────────────┬─────────────────┘                   │
│                   │ internal network                     │
│  ┌────────────────▼─────────────────┐                   │
│  │  db (postgres:18-alpine)         │  pg_data volume   │
│  │  POSTGRES_DB, USER, PASSWORD     │◄──────────────────┤
│  └──────────────────────────────────┘                   │
└──────────────────────────────────────────────────────────┘
```

### Multi-Stage Dockerfile Strategy

```
Stage 1: composer:2 (builder)
  └── composer install --no-dev --optimize-autoloader
  └── Produces: /app/vendor

Stage 2: php:8.2-fpm-alpine (production)
  ├── Installs: pdo_pgsql, zip, nginx, supervisor
  ├── Copies: application source + vendor from Stage 1
  ├── Copies: docker/nginx.conf, docker/supervisord.conf, docker/php.ini
  └── ENTRYPOINT: docker/entrypoint.sh
```

---

## 10. CI/CD Pipeline

The pipeline is defined in `.github/workflows/ci.yml` and triggers on every push/PR.

```
Push to any branch / PR to main
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│  Job: test  (ubuntu-latest)                             │
│                                                         │
│  ┌─ Service: postgres:18-alpine ─────────────────────┐ │
│  │  POSTGRES_DB=testing, USER=sail, PASS=password    │ │
│  └───────────────────────────────────────────────────┘ │
│                                                         │
│  1. Checkout code                                       │
│  2. Setup PHP 8.2 (pdo_pgsql, zip, opcache)            │
│  3. composer install                                    │
│  4. cp .env.example .env  +  set DB_ env vars           │
│  5. php artisan key:generate                            │
│  6. php artisan migrate --force                         │
│  7. php artisan test  (68 tests, 157 assertions)        │
└────────────────────┬────────────────────────────────────┘
                     │ passes
                     │ only on push to main
                     ▼
┌─────────────────────────────────────────────────────────┐
│  Job: build  (ubuntu-latest)                            │
│                                                         │
│  1. Checkout code                                       │
│  2. Setup Docker Buildx                                 │
│  3. docker build -t hatchloom-connecthub:latest .       │
│  4. docker run --entrypoint php ... artisan --version   │
│     (smoke-test: verifies image boots correctly)        │
└─────────────────────────────────────────────────────────┘
```

**Pipeline rules:**

| Trigger | Jobs Run | Effect |
|---|---|---|
| Push to `develop` | `test` | Validates changes don't break tests |
| PR to `main` | `test` | Blocks merge if tests fail |
| Push to `main` | `test` → `build` | Full test + Docker image build |
