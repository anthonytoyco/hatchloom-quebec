# ConnectHub Service — System Documentation

**Project:** Hatchloom — Team Quebec (Q3)
**Version:** 1.0.0
**Stack:** Laravel 12 · PHP 8.2 · PostgreSQL 18 · Laravel Sanctum · PHPUnit

---

## Table of Contents

1. [Overview](#1-overview)
2. [Design Choices](#2-design-choices)
3. [System Architecture](#3-system-architecture)
4. [Components](#4-components)
5. [Design Patterns](#5-design-patterns)
6. [Database Schema](#6-database-schema)
7. [Data Flow](#7-data-flow)
8. [Cross-Service Interfaces](#8-cross-service-interfaces)

---

## 1. Overview

ConnectHub is the social layer of the Hatchloom platform. It gives student entrepreneurs a place to share work, advertise open positions, and communicate directly with each other. It is the third and final sub-pack owned by Team Quebec, built on top of the Auth (Q1) and LaunchPad (Q2) services.

ConnectHub exposes three functional modules through a REST API:

| Module | Responsibility |
|---|---|
| **Feed** | Create and retrieve posts (shares, announcements, achievements); like and comment |
| **Classifieds** | Advertise open positions from a SideHustle; manage post lifecycle |
| **Messaging** | Thread-based direct messaging between two users |

All twelve endpoints sit behind `auth:sanctum` middleware — no unauthenticated access is possible.

---

## 2. Design Choices

### Laravel 12 + PHP 8.2

Laravel provides the routing, validation, ORM (Eloquent), and event system used throughout ConnectHub. PHP 8.2 constructor property promotion and readonly properties are used in event classes to keep payloads concise.

### PostgreSQL 18

A single shared PostgreSQL instance serves all three sub-packs (Q1 Auth, Q2 LaunchPad, Q3 ConnectHub). This removes the need for inter-service API calls when reading LaunchPad data (e.g. the `positions` table) from within ConnectHub. Schema boundaries are enforced at the application layer rather than at the database level.

### Laravel Sanctum (Token Authentication)

Sanctum issues API tokens that are validated on every ConnectHub request. All user identity information (`user_id`, `author_id`, `sender_id`) is derived from `$request->user()` — clients never send user IDs in request bodies. This prevents impersonation.

### Eloquent ORM + JSON `metadata` Column

`FeedItem.metadata` is stored as a JSON column. Each post type (share, announcement, achievement) stores its type-specific fields inside this column rather than in separate tables, keeping the feed schema flat and easily extensible.

### Laravel Events + Listeners

Rather than coupling side-effects (notifications, logging, future webhooks) directly to controllers, ConnectHub dispatches domain events (`FeedPostCreated`, `MessageSent`) after state changes. Listeners subscribe to these events and execute independently. This implements the Observer pattern without the controller needing to know who is observing.

---

## 3. System Architecture

### Top-Level Context

```
┌───────────────────────────────────────────────────────────┐
│                     Hatchloom Platform                     │
│                                                           │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────┐  │
│  │  Auth (Q1)   │  │ LaunchPad(Q2)│  │ ConnectHub(Q3) │  │
│  │              │  │              │  │                │  │
│  │ Registration │  │  Sandbox     │  │  Feed          │  │
│  │ Login        │  │  SideHustle  │  │  Classifieds   │  │
│  │ Sanctum      │  │  BMC         │  │  Messaging     │  │
│  │ Profiles     │  │  Team        │  │                │  │
│  │ RBAC         │  │  Positions   │  │                │  │
│  └──────┬───────┘  └──────┬───────┘  └───────┬────────┘  │
│         │                 │                  │            │
│         └────────────┬────┘                  │            │
│                      │   Shared PostgreSQL    │            │
│                      └───────────────────────┘            │
└───────────────────────────────────────────────────────────┘
```

### ConnectHub Internal Architecture

```
HTTP Client
    │
    ▼
┌─────────────────────────────────────────────────────────┐
│                    routes/api.php                        │
│              middleware: auth:sanctum                    │
└────────────┬──────────────┬─────────────────────────────┘
             │              │              │
             ▼              ▼              ▼
   ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
   │FeedController│ │ClassifiedPost│ │MessageControl│
   │              │ │Controller    │ │ler           │
   │ index        │ │              │ │              │
   │ store        │ │ index        │ │ indexThreads │
   │ like         │ │ store        │ │ storeThread  │
   │ comment      │ │ show         │ │ indexMessages│
   └──────┬───────┘ │ updateStatus │ │ storeMessage │
          │         └──────┬───────┘ └──────┬───────┘
          │                │                │
          ▼                ▼                ▼
   ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
   │  PostFactory │ │ClassifiedPost│ │   Thread /   │
   │  (abstract)  │ │   Model      │ │  Message     │
   │              │ │              │ │  Models      │
   │ ShareFactory │ │ canTransition│ └──────────────┘
   │ Announce..   │ │ To()         │
   │ Achievement..│ └──────────────┘
   └──────┬───────┘
          │
          ▼
   ┌──────────────┐      ┌──────────────────────┐
   │  FeedItem    │      │  Laravel Event Bus   │
   │  Model       │─────▶│                      │
   └──────────────┘      │ FeedPostCreated      │
                         │ ClassifiedPostCreated│
                         │ MessageSent          │
                         └──────────┬───────────┘
                                    │
                                    ▼
                         ┌──────────────────────┐
                         │ NotifyFeedObservers  │
                         │ (Listener)           │
                         └──────────────────────┘
```

---

## 4. Components

### 4.1 Controllers

#### `FeedController`

Handles the social feed. Delegates all post creation to `PostFactory` — it never calls `FeedItem::create()` directly.

| Method | Route | Responsibility |
|---|---|---|
| `index()` | `GET /api/feed` | Returns all posts, newest first, with `user` and `actions` loaded |
| `store()` | `POST /api/feed` | Validates input, delegates to `PostFactory::make()`, dispatches `FeedPostCreated` |
| `like()` | `POST /api/feed/{feedItem}/like` | Creates a `like` FeedAction; returns 409 if duplicate |
| `comment()` | `POST /api/feed/{feedItem}/comment` | Creates a `comment` FeedAction |

#### `ClassifiedPostController`

Enforces the Position Status Interface (position ownership check) and the classified post lifecycle (one-way status transitions).

| Method | Route | Responsibility |
|---|---|---|
| `index()` | `GET /api/classifieds` | Lists posts; supports `?status=` filter |
| `store()` | `POST /api/classifieds` | Validates position ownership, creates post with `status=OPEN` |
| `show()` | `GET /api/classifieds/{id}` | Returns single post |
| `updateStatus()` | `PATCH /api/classifieds/{id}/status` | Ownership guard + lifecycle guard, then updates |

#### `MessageController`

Enforces participant guards — only thread members can read or write messages.

| Method | Route | Responsibility |
|---|---|---|
| `indexThreads()` | `GET /api/threads` | Lists threads the auth user participates in |
| `storeThread()` | `POST /api/threads` | Creates thread with deduplication for direct messages |
| `indexMessages()` | `GET /api/threads/{id}/messages` | Participant guard; returns messages oldest-first |
| `storeMessage()` | `POST /api/threads/{id}/messages` | Participant guard; creates message; dispatches `MessageSent` |

---

### 4.2 Services (Factory Layer)

The Factory layer is the only place in the application that may create `FeedItem` records.

```
PostFactory (abstract)
├── make(type, data, author): FeedItem   ← sole public entry point
│     └── match(type) {
│           'share'        → ShareFactory::create()
│           'announcement' → AnnouncementFactory::create()
│           'achievement'  → AchievementFactory::create()
│           default        → throw InvalidArgumentException
│         }
│
├── ShareFactory
│     └── Requires: metadata.shareLink
│
├── AnnouncementFactory
│     └── Requires: metadata.announcementDate
│
└── AchievementFactory
      └── Requires: metadata.achievementName
```

Each concrete factory validates its required metadata field and calls `FeedItem::create()`. If the required field is absent, an `InvalidArgumentException` is thrown before any database write occurs.

---

### 4.3 Models

| Model | Table | Key Relationships |
|---|---|---|
| `FeedItem` | `feed_items` | `belongsTo User`, `hasMany FeedAction` |
| `FeedAction` | `feed_actions` | `belongsTo FeedItem`, `belongsTo User` |
| `ClassifiedPost` | `classified_posts` | `belongsTo Position`, `belongsTo SideHustle`, `belongsTo User (author)` |
| `Thread` | `threads` | `belongsToMany User (participants)`, `hasMany Message` |
| `Message` | `messages` | `belongsTo Thread`, `belongsTo User (sender)` |

`ClassifiedPost` carries a `canTransitionTo(string $status): bool` method that encapsulates the lifecycle rule:

```
OPEN  → FILLED  ✓
OPEN  → CLOSED  ✓
FILLED → *      ✗  (returns false)
CLOSED → *      ✗  (returns false)
```

---

### 4.4 Events and Listeners

| Event | Dispatched by | Payload |
|---|---|---|
| `FeedPostCreated` | `FeedController::store()` | `public readonly FeedItem $feedItem` |
| `ClassifiedPostCreated` | `ClassifiedPostController::store()` | `public readonly ClassifiedPost $classifiedPost` |
| `MessageSent` | `MessageController::storeMessage()` | `public readonly Message $message` |

| Listener | Subscribes to | Action |
|---|---|---|
| `NotifyFeedObservers` | `FeedPostCreated` | Acts as the Observer notification hub for feed events |

New observers (push notifications, analytics, email digests) are added by registering additional listeners against the existing events — no controller code changes required.

---

## 5. Design Patterns

### 5.1 Factory Pattern

**Where:** `app/Services/PostFactory.php` and its concrete implementations.

**Problem solved:** The feed supports multiple post types (share, announcement, achievement), each with different required metadata. Hard-coding a `switch` block in the controller would couple post creation logic to the HTTP layer and make it difficult to test or extend.

**Solution:** `PostFactory::make()` is the single entry point for creating any `FeedItem`. It selects the correct concrete factory at runtime based on the `type` field. The controller never imports a concrete factory directly.

```
Client ──POST /api/feed──▶ FeedController
                                │
                                ▼
                          PostFactory::make(type, data, user)
                                │
                    ┌───────────┼───────────┐
                    ▼           ▼           ▼
              ShareFactory  Announce..  Achievement..
                    │           │           │
                    └───────────┴───────────┘
                                │
                         FeedItem::create()
```

### 5.2 Observer Pattern

**Where:** `app/Events/` + `app/Listeners/NotifyFeedObservers.php`

**Problem solved:** After a feed post is created, multiple independent side-effects may need to occur (notify followers, update counters, log analytics). Placing all this logic in the controller creates a monolith.

**Solution:** `FeedController::store()` dispatches `FeedPostCreated`. Any number of listeners can subscribe to this event without touching the controller. The same pattern applies to `MessageSent`.

```
FeedController::store()
        │
        ▼
  FeedItem created
        │
        ▼
  FeedPostCreated::dispatch($feedItem)
        │
        ▼
  Laravel Event Bus
        │
        ▼
  NotifyFeedObservers::handle()
  [+ any future listeners]
```

### 5.3 Strategy Pattern (RBAC + Guards)

**Where:** `auth:sanctum` middleware + ownership/participant guards in controllers.

**Problem solved:** Different actions require different authorization rules (is the user authenticated? do they own this post? are they a thread participant?).

**Solution:** Each guard is a discrete conditional check. The ownership guard in `ClassifiedPostController::updateStatus()` and the participant guard in `MessageController` are independent strategy-style checks that can be swapped or extended without touching business logic.

### 5.4 Singleton Pattern (Session Management)

**Where:** Laravel Sanctum's `SessionManager` (inherited from Q1 Auth).

**Problem solved:** Session validation must be consistent across every request.

**Solution:** Sanctum's token guard is a singleton service registered in the service container. Every ConnectHub request resolves the same guard instance for authentication.

---

## 6. Database Schema

The ConnectHub service adds six tables to the shared PostgreSQL database.

```
┌──────────────────────────────────────────────────────────────────┐
│  users (from Q1 Auth)                                            │
│  id · name · email · password · created_at · updated_at         │
└──────────┬───────────────────────────────────────────────────────┘
           │
    ┌──────┴────────────────────────────────────────────┐
    │                                                   │
    ▼                                                   ▼
┌──────────────────────────────────┐  ┌────────────────────────────────┐
│  feed_items                      │  │  classified_posts              │
│  id                              │  │  id                            │
│  user_id ──────────────(FK users)│  │  author_id ────────(FK users)  │
│  type (share|announce|achieve)   │  │  position_id ──(FK positions)  │
│  title (nullable)                │  │  side_hustle_id ─(FK side..)   │
│  content                         │  │  title                         │
│  metadata (json)                 │  │  content                       │
│  created_at · updated_at         │  │  status (OPEN|FILLED|CLOSED)   │
└──────────┬───────────────────────┘  │  created_at · updated_at       │
           │                          └────────────────────────────────┘
           ▼
┌──────────────────────────────────┐
│  feed_actions                    │
│  id                              │
│  feed_item_id ──────(FK feed_items)│
│  user_id ────────────(FK users)  │
│  action_type (like|comment)      │
│  content (nullable, for comment) │
│  created_at · updated_at         │
│  UNIQUE(feed_item_id, user_id,   │
│         action_type) on likes    │
└──────────────────────────────────┘

┌──────────────────────────────────┐
│  threads                         │
│  id                              │
│  context_type (nullable)         │
│  context_id (nullable)           │
│  created_at · updated_at         │
└──────────┬───────────────────────┘
           │
    ┌──────┴─────────────────────────┐
    │                                │
    ▼                                ▼
┌────────────────────────────┐  ┌───────────────────────────────┐
│  thread_participants       │  │  messages                     │
│  thread_id ──(FK threads)  │  │  id                           │
│  user_id ────(FK users)    │  │  thread_id ───────(FK threads)│
│  PRIMARY KEY(thread_id,    │  │  sender_id ────────(FK users) │
│              user_id)      │  │  content                      │
└────────────────────────────┘  │  created_at · updated_at      │
                                └───────────────────────────────┘
```

**Cross-service reads (no foreign key constraint, same DB):**
- `classified_posts.position_id` references `positions.id` (LaunchPad Q2)
- `classified_posts.side_hustle_id` references `side_hustles.id` (LaunchPad Q2)

---

## 7. Data Flow

### 7.1 Creating a Feed Post

```
Client
  │
  │  POST /api/feed
  │  { "type": "share", "content": "...", "metadata": { "shareLink": "..." } }
  │
  ▼
auth:sanctum middleware
  │  resolves $request->user()  ──────────────────────────▶ users table
  ▼
FeedController::store()
  │  validates type, content, metadata.*
  ▼
PostFactory::make('share', data, user)
  │
  ▼
ShareFactory
  │  validates metadata.shareLink present
  │
  ▼
FeedItem::create({ user_id, type, content, metadata })
  │                 ─────────────────────▶ feed_items table (INSERT)
  ▼
FeedPostCreated::dispatch($feedItem)
  │
  ▼
Laravel Event Bus ──▶ NotifyFeedObservers::handle()
  │
  ▼
HTTP 201 { feedItem + user }
```

### 7.2 Classified Post — Status Update

```
Client
  │
  │  PATCH /api/classifieds/{id}/status
  │  { "status": "FILLED" }
  │
  ▼
auth:sanctum ──▶ resolves $request->user()
  ▼
ClassifiedPostController::updateStatus()
  │
  ├─ [ownership guard]
  │    classifiedPost.author_id !== user.id  ──▶  403 Forbidden
  │
  ├─ [validate] status must be FILLED or CLOSED
  │
  ├─ [lifecycle guard]
  │    classifiedPost.canTransitionTo(status) === false  ──▶  422
  │
  ▼
classifiedPost.update({ status: 'FILLED' })
  │                     ──────────────────▶ classified_posts table (UPDATE)
  ▼
HTTP 200 { classifiedPost + relations }
```

### 7.3 Sending a Message

```
Client
  │
  │  POST /api/threads/{id}/messages
  │  { "content": "Hello!" }
  │
  ▼
auth:sanctum ──▶ resolves $request->user()
  ▼
MessageController::storeMessage()
  │
  ├─ [participant guard]
  │    thread.participants.contains(user.id) === false  ──▶  403 Forbidden
  │
  ├─ [validate] content required
  │
  ▼
Message::create({ thread_id, sender_id, content })
  │                ──────────────────────────────▶ messages table (INSERT)
  ▼
MessageSent::dispatch($message)
  │
  ▼
Laravel Event Bus ──▶ [listener(s)]
  │
  ▼
HTTP 201 { message + sender }
```

---

## 8. Cross-Service Interfaces

### 8.1 Session Validation Interface (Auth Q1 → ConnectHub)

**Defined in:** Design Doc p. 16–17

Every ConnectHub route requires a valid Sanctum bearer token. The `auth:sanctum` middleware resolves the token from the `Authorization` header, looks up the token in the `personal_access_tokens` table (managed by Q1 Auth), and populates `$request->user()`.

ConnectHub uses `$request->user()` to:
- Set `feed_items.user_id`
- Set `classified_posts.author_id`
- Set `messages.sender_id`

No user ID is ever accepted from the request body.

### 8.2 Position Status Interface (LaunchPad Q2 → ConnectHub)

**Defined in:** Design Doc p. 15, 19

When a classified post is created, `ClassifiedPostController::store()` performs two checks against the shared `positions` table:

1. The `position_id` must exist (`exists:positions,id` validation rule)
2. The `Position` must belong to a `SideHustle` owned by the authenticated user (`side_hustles.student_id === $request->user()->id`)

```
POST /api/classifieds
        │
        ▼
positions table (Q2 LaunchPad)
        │
        ├─ position exists?  ──── No ──▶ 422 Unprocessable
        │
        ├─ position.sideHustle.student_id === auth user?
        │         No ──▶ 403 Forbidden
        │
        ▼
ClassifiedPost::create({ ..., status: 'OPEN' })
```

This read is a direct SQL join — no HTTP call between services. Both services share the same PostgreSQL instance.
