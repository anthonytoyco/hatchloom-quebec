# ConnectHub API Documentation

**Service:** ConnectHub (Q3 — Team Quebec)
**Base URL:** `http://localhost/api`
**Authentication:** All ConnectHub endpoints require a Sanctum bearer token issued by the Auth service (Q1).

Add the following header to every request:
```
Authorization: Bearer {token}
```

Tokens are obtained via `POST /login` (Auth service). All unauthenticated requests return `401 Unauthorized`.

---

## Table of Contents

- [Feed Module](#feed-module)
  - [GET /api/feed](#get-apifeed)
  - [POST /api/feed](#post-apifeed)
  - [POST /api/feed/{feedItem}/like](#post-apifeedfeeditemlike)
  - [POST /api/feed/{feedItem}/comment](#post-apifeedfeeditemcomment)
- [Classifieds Module](#classifieds-module)
  - [GET /api/classifieds](#get-apiclassifieds)
  - [POST /api/classifieds](#post-apiclassifieds)
  - [GET /api/classifieds/{classifiedPost}](#get-apiclassifiedsclassifiedpost)
  - [PATCH /api/classifieds/{classifiedPost}/status](#patch-apiclassifiedsclassifiedpoststatus)
- [Messaging Module](#messaging-module)
  - [GET /api/threads](#get-apithreads)
  - [POST /api/threads](#post-apithreads)
  - [GET /api/threads/{thread}/messages](#get-apithreadsthreadmessages)
  - [POST /api/threads/{thread}/messages](#post-apithreadsthreadmessages)
- [Cross-Service Dependencies](#cross-service-dependencies)
- [Design Pattern Notes](#design-pattern-notes)

---

## Feed Module

The Feed Module implements the social feed. Post creation uses the **Factory pattern** (design doc p. 21, 37) — `PostFactory` determines the concrete post type at runtime. Every successful store dispatches a `FeedPostCreated` event (Observer pattern, design doc p. 37–38).

---

### GET /api/feed

Returns all feed posts ordered newest first (`getFeed` interface, design doc p. 19).

**Request**

| Field | Value |
|---|---|
| Method | `GET` |
| URL | `/api/feed` |
| Body | None |

**Response 200 — OK**

Returns an array of `FeedItem` objects, each with nested `user` and `actions`.

```json
[
  {
    "id": 3,
    "user_id": 42,
    "type": "achievement",
    "title": null,
    "content": "Won the Entrepreneur's Choice award!",
    "metadata": { "achievementName": "Entrepreneur's Choice" },
    "created_at": "2026-03-13T10:00:00.000000Z",
    "updated_at": "2026-03-13T10:00:00.000000Z",
    "user": {
      "id": 42,
      "name": "Alice Smith",
      "email": "alice@example.com"
    },
    "actions": [
      {
        "id": 1,
        "feed_item_id": 3,
        "user_id": 55,
        "action_type": "like",
        "content": null,
        "created_at": "2026-03-13T10:05:00.000000Z"
      }
    ]
  }
]
```

**Status Codes**

| Code | Meaning |
|---|---|
| `200` | Success |
| `401` | Missing or invalid bearer token |

---

### POST /api/feed

Creates a new feed post. The `type` field determines which concrete factory is invoked at runtime (Factory pattern). Fires `FeedPostCreated` event on success.

**Request**

| Field | Value |
|---|---|
| Method | `POST` |
| URL | `/api/feed` |
| Content-Type | `application/json` |

**Request Body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `type` | string | Yes | One of: `share`, `announcement`, `achievement` |
| `content` | string | Yes | Body text of the post |
| `title` | string | No | Optional display title |
| `metadata` | object | Conditional | Required fields vary by type (see below) |
| `metadata.shareLink` | string | If `type=share` | URL to share |
| `metadata.announcementDate` | string | If `type=announcement` | Date string, e.g. `"2026-04-01"` |
| `metadata.achievementName` | string | If `type=achievement` | Name of the achievement |

**Example — Share post**
```json
{
  "type": "share",
  "content": "Check this resource out!",
  "metadata": {
    "shareLink": "https://example.com/article"
  }
}
```

**Example — Announcement post**
```json
{
  "type": "announcement",
  "title": "Launch Day",
  "content": "We are officially launching next month.",
  "metadata": {
    "announcementDate": "2026-04-15"
  }
}
```

**Example — Achievement post**
```json
{
  "type": "achievement",
  "content": "Proud to have reached 100 customers!",
  "metadata": {
    "achievementName": "100 Customers Milestone"
  }
}
```

**Response 201 — Created**

Returns the created `FeedItem` with nested `user`.

```json
{
  "id": 5,
  "user_id": 42,
  "type": "share",
  "title": null,
  "content": "Check this resource out!",
  "metadata": { "shareLink": "https://example.com/article" },
  "created_at": "2026-03-13T11:00:00.000000Z",
  "updated_at": "2026-03-13T11:00:00.000000Z",
  "user": {
    "id": 42,
    "name": "Alice Smith",
    "email": "alice@example.com"
  }
}
```

**Status Codes**

| Code | Meaning |
|---|---|
| `201` | Post created |
| `401` | Missing or invalid bearer token |
| `422` | Validation error — invalid `type`, or missing type-specific metadata field |

---

### POST /api/feed/{feedItem}/like

Records a like action on a feed post. Duplicate likes are rejected (unique constraint on `feed_actions`).

**Request**

| Field | Value |
|---|---|
| Method | `POST` |
| URL | `/api/feed/{feedItem}/like` |
| Body | None |

**URL Parameters**

| Parameter | Type | Description |
|---|---|---|
| `feedItem` | integer | ID of the `FeedItem` to like |

**Response 201 — Created**

```json
{
  "id": 8,
  "feed_item_id": 5,
  "user_id": 42,
  "action_type": "like",
  "content": null,
  "created_at": "2026-03-13T11:05:00.000000Z",
  "updated_at": "2026-03-13T11:05:00.000000Z"
}
```

**Response 409 — Already liked**

```json
{
  "message": "Already liked."
}
```

**Status Codes**

| Code | Meaning |
|---|---|
| `201` | Like recorded |
| `401` | Missing or invalid bearer token |
| `404` | Feed item not found |
| `409` | User has already liked this post |

---

### POST /api/feed/{feedItem}/comment

Appends a comment to a feed post.

**Request**

| Field | Value |
|---|---|
| Method | `POST` |
| URL | `/api/feed/{feedItem}/comment` |
| Content-Type | `application/json` |

**URL Parameters**

| Parameter | Type | Description |
|---|---|---|
| `feedItem` | integer | ID of the `FeedItem` to comment on |

**Request Body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `content` | string | Yes | Text of the comment |

```json
{
  "content": "Great achievement, keep it up!"
}
```

**Response 201 — Created**

Returns the created `FeedAction` with nested `user`.

```json
{
  "id": 9,
  "feed_item_id": 5,
  "user_id": 42,
  "action_type": "comment",
  "content": "Great achievement, keep it up!",
  "created_at": "2026-03-13T11:06:00.000000Z",
  "updated_at": "2026-03-13T11:06:00.000000Z",
  "user": {
    "id": 42,
    "name": "Alice Smith",
    "email": "alice@example.com"
  }
}
```

**Status Codes**

| Code | Meaning |
|---|---|
| `201` | Comment recorded |
| `401` | Missing or invalid bearer token |
| `404` | Feed item not found |
| `422` | Missing `content` field |

---

## Classifieds Module

Classified posts advertise open positions from the LaunchPad service (Q2). Each `ClassifiedPost` is linked to a `Position` in the shared `positions` table (**Position Status Interface**, design doc p. 15, 19). The requesting user must own the SideHustle that the position belongs to.

**Status lifecycle:** `OPEN → FILLED` or `OPEN → CLOSED` only. Transitions are one-way and cannot be reversed (design doc p. 20, Test ID 13, p. 49).

---

### GET /api/classifieds

Lists classified posts, ordered newest first. Supports optional status filter.

**Request**

| Field | Value |
|---|---|
| Method | `GET` |
| URL | `/api/classifieds` |
| Body | None |

**Query Parameters**

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `status` | string | No | Filter by status: `OPEN`, `FILLED`, or `CLOSED` |

**Example:** `GET /api/classifieds?status=OPEN`

**Response 200 — OK**

Returns an array of `ClassifiedPost` objects with nested `position`, `sideHustle`, and `author`.

```json
[
  {
    "id": 1,
    "position_id": 3,
    "side_hustle_id": 2,
    "author_id": 42,
    "title": "Looking for a backend developer",
    "content": "Join our early-stage startup building fintech tools.",
    "status": "OPEN",
    "created_at": "2026-03-13T09:00:00.000000Z",
    "updated_at": "2026-03-13T09:00:00.000000Z",
    "position": { "id": 3, "title": "Backend Developer", "side_hustle_id": 2 },
    "side_hustle": { "id": 2, "name": "FinFlow" },
    "author": { "id": 42, "name": "Alice Smith", "email": "alice@example.com" }
  }
]
```

**Status Codes**

| Code | Meaning |
|---|---|
| `200` | Success |
| `401` | Missing or invalid bearer token |
| `422` | Invalid `status` query parameter value |

---

### POST /api/classifieds

Creates a new classified post linked to a position. The requesting user must own the SideHustle that the position belongs to (Position Status Interface ownership check). Initial status is always `OPEN`. Fires `ClassifiedPostCreated` event on success.

**Request**

| Field | Value |
|---|---|
| Method | `POST` |
| URL | `/api/classifieds` |
| Content-Type | `application/json` |

**Request Body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `position_id` | integer | Yes | Must exist in `positions` table and belong to the user's SideHustle |
| `title` | string | Yes | Display title for the classified ad |
| `content` | string | Yes | Full description of the role |

```json
{
  "position_id": 3,
  "title": "Looking for a backend developer",
  "content": "Join our early-stage startup building fintech tools."
}
```

**Response 201 — Created**

Returns the created `ClassifiedPost` with nested relations.

```json
{
  "id": 1,
  "position_id": 3,
  "side_hustle_id": 2,
  "author_id": 42,
  "title": "Looking for a backend developer",
  "content": "Join our early-stage startup building fintech tools.",
  "status": "OPEN",
  "created_at": "2026-03-13T09:00:00.000000Z",
  "updated_at": "2026-03-13T09:00:00.000000Z",
  "position": { "id": 3, "title": "Backend Developer", "side_hustle_id": 2 },
  "side_hustle": { "id": 2, "name": "FinFlow" },
  "author": { "id": 42, "name": "Alice Smith", "email": "alice@example.com" }
}
```

**Response 403 — Forbidden**

```json
{
  "message": "Forbidden: position does not belong to your SideHustle."
}
```

**Status Codes**

| Code | Meaning |
|---|---|
| `201` | Classified post created |
| `401` | Missing or invalid bearer token |
| `403` | Position belongs to another user's SideHustle |
| `422` | Missing required fields, or `position_id` does not exist |

---

### GET /api/classifieds/{classifiedPost}

Retrieves a single classified post by ID (`getClassifiedById` interface, design doc p. 20).

**Request**

| Field | Value |
|---|---|
| Method | `GET` |
| URL | `/api/classifieds/{classifiedPost}` |
| Body | None |

**URL Parameters**

| Parameter | Type | Description |
|---|---|---|
| `classifiedPost` | integer | ID of the `ClassifiedPost` |

**Response 200 — OK**

Returns a single `ClassifiedPost` with nested `position`, `sideHustle`, and `author`.

```json
{
  "id": 1,
  "position_id": 3,
  "side_hustle_id": 2,
  "author_id": 42,
  "title": "Looking for a backend developer",
  "content": "Join our early-stage startup building fintech tools.",
  "status": "OPEN",
  "created_at": "2026-03-13T09:00:00.000000Z",
  "updated_at": "2026-03-13T09:00:00.000000Z",
  "position": { "id": 3, "title": "Backend Developer", "side_hustle_id": 2 },
  "side_hustle": { "id": 2, "name": "FinFlow" },
  "author": { "id": 42, "name": "Alice Smith", "email": "alice@example.com" }
}
```

**Status Codes**

| Code | Meaning |
|---|---|
| `200` | Success |
| `401` | Missing or invalid bearer token |
| `404` | Classified post not found |

---

### PATCH /api/classifieds/{classifiedPost}/status

Updates the status of a classified post. Only the owner of the post may perform this action. Valid transitions: `OPEN → FILLED` or `OPEN → CLOSED` only (design doc p. 20, Test ID 13, p. 49).

**Request**

| Field | Value |
|---|---|
| Method | `PATCH` |
| URL | `/api/classifieds/{classifiedPost}/status` |
| Content-Type | `application/json` |

**URL Parameters**

| Parameter | Type | Description |
|---|---|---|
| `classifiedPost` | integer | ID of the `ClassifiedPost` to update |

**Request Body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `status` | string | Yes | Target status: `FILLED` or `CLOSED` |

```json
{
  "status": "FILLED"
}
```

**Response 200 — OK**

Returns the updated `ClassifiedPost` with nested relations.

```json
{
  "id": 1,
  "position_id": 3,
  "side_hustle_id": 2,
  "author_id": 42,
  "title": "Looking for a backend developer",
  "content": "Join our early-stage startup building fintech tools.",
  "status": "FILLED",
  "created_at": "2026-03-13T09:00:00.000000Z",
  "updated_at": "2026-03-13T11:00:00.000000Z",
  "position": { "id": 3, "title": "Backend Developer", "side_hustle_id": 2 },
  "side_hustle": { "id": 2, "name": "FinFlow" },
  "author": { "id": 42, "name": "Alice Smith", "email": "alice@example.com" }
}
```

**Response 403 — Forbidden**

```json
{
  "message": "Forbidden: only the owner may change this status."
}
```

**Response 422 — Invalid transition**

```json
{
  "message": "Invalid transition: FILLED → CLOSED."
}
```

**Status Codes**

| Code | Meaning |
|---|---|
| `200` | Status updated |
| `401` | Missing or invalid bearer token |
| `403` | Authenticated user is not the post owner |
| `422` | Invalid target status, or transition not allowed (e.g. `FILLED → OPEN`) |

---

## Messaging Module

Messages are organized in threads between two participants. Threads may optionally carry a `context_type` + `context_id` to associate the conversation with a platform entity such as a SideHustle or challenge (design doc p. 16: "messaging widget architecture"). Sending a message fires the `MessageSent` event (Observer / Event Notifier pattern, design doc p. 15–16).

---

### GET /api/threads

Lists all threads the authenticated user participates in, ordered newest first.

**Request**

| Field | Value |
|---|---|
| Method | `GET` |
| URL | `/api/threads` |
| Body | None |

**Response 200 — OK**

Returns an array of `Thread` objects with nested `participants` and `messages`.

```json
[
  {
    "id": 1,
    "context_type": "side_hustle",
    "context_id": 2,
    "created_at": "2026-03-13T08:00:00.000000Z",
    "updated_at": "2026-03-13T08:00:00.000000Z",
    "participants": [
      { "id": 42, "name": "Alice Smith", "email": "alice@example.com" },
      { "id": 55, "name": "Bob Jones", "email": "bob@example.com" }
    ],
    "messages": [
      {
        "id": 1,
        "thread_id": 1,
        "sender_id": 42,
        "content": "Hi, I saw your open position!",
        "created_at": "2026-03-13T08:01:00.000000Z"
      }
    ]
  }
]
```

**Status Codes**

| Code | Meaning |
|---|---|
| `200` | Success |
| `401` | Missing or invalid bearer token |

---

### POST /api/threads

Creates a new thread between the authenticated user and a recipient. If a direct thread already exists between the two users (no `context_type`), that existing thread is returned instead of creating a duplicate (`sendMessage` deduplication, design doc p. 20).

**Request**

| Field | Value |
|---|---|
| Method | `POST` |
| URL | `/api/threads` |
| Content-Type | `application/json` |

**Request Body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `recipient_id` | integer | Yes | ID of the user to start a thread with |
| `context_type` | string | No | Entity type the thread relates to (e.g. `"side_hustle"`) |
| `context_id` | integer | No | ID of the related entity |

```json
{
  "recipient_id": 55,
  "context_type": "side_hustle",
  "context_id": 2
}
```

**Direct message (no context):**

```json
{
  "recipient_id": 55
}
```

**Response 201 — New thread created**

```json
{
  "id": 1,
  "context_type": "side_hustle",
  "context_id": 2,
  "created_at": "2026-03-13T08:00:00.000000Z",
  "updated_at": "2026-03-13T08:00:00.000000Z",
  "participants": [
    { "id": 42, "name": "Alice Smith", "email": "alice@example.com" },
    { "id": 55, "name": "Bob Jones", "email": "bob@example.com" }
  ],
  "messages": []
}
```

**Response 200 — Existing thread returned (deduplication)**

Same shape as 201 but with `HTTP 200` and any existing messages populated.

**Status Codes**

| Code | Meaning |
|---|---|
| `201` | New thread created |
| `200` | Existing thread returned (no duplicate created) |
| `401` | Missing or invalid bearer token |
| `422` | Missing `recipient_id`, or `recipient_id` does not exist |

---

### GET /api/threads/{thread}/messages

Returns all messages in a thread ordered chronologically (oldest first). Only thread participants may access this endpoint (`getMessages` interface, design doc p. 20).

**Request**

| Field | Value |
|---|---|
| Method | `GET` |
| URL | `/api/threads/{thread}/messages` |
| Body | None |

**URL Parameters**

| Parameter | Type | Description |
|---|---|---|
| `thread` | integer | ID of the `Thread` |

**Response 200 — OK**

Returns an array of `Message` objects with nested `sender`, ordered oldest to newest.

```json
[
  {
    "id": 1,
    "thread_id": 1,
    "sender_id": 42,
    "content": "Hi, I saw your open position!",
    "created_at": "2026-03-13T08:01:00.000000Z",
    "updated_at": "2026-03-13T08:01:00.000000Z",
    "sender": {
      "id": 42,
      "name": "Alice Smith",
      "email": "alice@example.com"
    }
  },
  {
    "id": 2,
    "thread_id": 1,
    "sender_id": 55,
    "content": "Thanks for reaching out! Let's chat.",
    "created_at": "2026-03-13T08:05:00.000000Z",
    "updated_at": "2026-03-13T08:05:00.000000Z",
    "sender": {
      "id": 55,
      "name": "Bob Jones",
      "email": "bob@example.com"
    }
  }
]
```

**Response 403 — Forbidden**

```json
{
  "message": "Forbidden: you are not a participant of this thread."
}
```

**Status Codes**

| Code | Meaning |
|---|---|
| `200` | Success |
| `401` | Missing or invalid bearer token |
| `403` | Authenticated user is not a thread participant |
| `404` | Thread not found |

---

### POST /api/threads/{thread}/messages

Sends a message within a thread. Only thread participants may send. Dispatches the `MessageSent` event (Observer / Event Notifier pattern, design doc p. 15–16) after the message is persisted.

**Request**

| Field | Value |
|---|---|
| Method | `POST` |
| URL | `/api/threads/{thread}/messages` |
| Content-Type | `application/json` |

**URL Parameters**

| Parameter | Type | Description |
|---|---|---|
| `thread` | integer | ID of the `Thread` to post into |

**Request Body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `content` | string | Yes | Text content of the message |

```json
{
  "content": "Hi, I saw your open position!"
}
```

**Response 201 — Created**

Returns the created `Message` with nested `sender`.

```json
{
  "id": 3,
  "thread_id": 1,
  "sender_id": 42,
  "content": "Hi, I saw your open position!",
  "created_at": "2026-03-13T08:10:00.000000Z",
  "updated_at": "2026-03-13T08:10:00.000000Z",
  "sender": {
    "id": 42,
    "name": "Alice Smith",
    "email": "alice@example.com"
  }
}
```

**Response 403 — Forbidden**

```json
{
  "message": "Forbidden: you are not a participant of this thread."
}
```

**Status Codes**

| Code | Meaning |
|---|---|
| `201` | Message sent |
| `401` | Missing or invalid bearer token |
| `403` | Authenticated user is not a thread participant |
| `404` | Thread not found |
| `422` | Missing `content` field |

---

## Cross-Service Dependencies

ConnectHub depends on the other two subpacks (Q1, Q2) in specific ways defined by the design document (p. 12–13, 15).

### Auth Service (Q1) → ConnectHub

Every ConnectHub route requires a valid Sanctum token. The `auth:sanctum` middleware enforces the **Session Validation Interface** (design doc p. 16–17). The authenticated `$request->user()` is used to set:

- `feed_items.user_id` — identifies the post author
- `classified_posts.author_id` — identifies the classified post owner
- `messages.sender_id` — identifies the message sender

Obtain a token via the Auth service:

```
POST /login
Content-Type: application/json

{ "email": "alice@example.com", "password": "secret" }
```

### LaunchPad Service (Q2) → ConnectHub (Position Status Interface)

When creating a classified post (`POST /api/classifieds`), the ConnectHub service reads directly from the shared `positions` table (same PostgreSQL instance) to enforce the **Position Status Interface** (design doc p. 15, 19):

1. The `position_id` must exist in `positions`
2. The position must belong to a `SideHustle` owned by the authenticated user (`side_hustles.student_id === auth user id`)
3. If either check fails, the request is rejected with `403 Forbidden`

This read-only cross-table dependency requires no API call — both services share the same database.

---

## Design Pattern Notes

| Pattern | Where Used | Design Doc Reference |
|---|---|---|
| **Factory** | `POST /api/feed` — `PostFactory::make(type, data, user)` dispatches to `ShareFactory`, `AnnouncementFactory`, or `AchievementFactory` at runtime | p. 21, 37 |
| **Observer** | `POST /api/feed` fires `FeedPostCreated`; `POST /api/threads/{thread}/messages` fires `MessageSent` | p. 24–25, 37–38 |
| **Strategy (RBAC)** | Auth middleware + ownership guards (classified post owner check, thread participant check) | p. 38 |
| **Singleton** | `SessionManager` — enforced by Sanctum; all ConnectHub routes share the same session validation path | p. 38 |
