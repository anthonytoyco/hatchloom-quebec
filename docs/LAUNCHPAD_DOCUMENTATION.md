# LaunchPad Service — System Documentation

**Project:** Hatchloom — Team Quebec (Q2)
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

LaunchPad is the venture management layer of the Hatchloom platform. It gives student entrepreneurs the tools to ideate in a low-stakes Sandbox, graduate their best ideas into full SideHustles, plan their business strategy through a Business Model Canvas, assemble a Team, and define open Positions for collaborators. It is the second sub-pack owned by Team Quebec, built on top of the Auth (Q1) service and consumed by the ConnectHub (Q3) service.

LaunchPad exposes five functional modules through a REST API:

| Module | Responsibility |
|---|---|
| **Sandbox** | Create and manage experimental idea workspaces; promote to a SideHustle |
| **SideHustle** | Manage launched ventures; track status from `IN_THE_LAB` to `LIVE_VENTURE` |
| **Business Model Canvas** | Fill in and update the nine-section Osterwalder BMC per SideHustle |
| **Team** | Manage venture team membership (add/remove members with roles) |
| **Position** | Define open roles on a venture; flag syncs automatically with the SideHustle |

All endpoints sit behind `auth:sanctum` middleware — no unauthenticated access is possible.

---

## 2. Design Choices

### Laravel 12 + PHP 8.2

Laravel provides the routing, validation, ORM (Eloquent), and service container used throughout LaunchPad. Controller-centric design is used deliberately — the domain logic is simple CRUD with a few lifecycle guards, so the overhead of a full Service/Repository layer is avoided.

### PostgreSQL 18

A single shared PostgreSQL instance serves all three sub-packs (Q1 Auth, Q2 LaunchPad, Q3 ConnectHub). This allows ConnectHub's Classifieds service to read directly from the `positions` and `side_hustles` tables without any inter-service API call. Schema enforcement is handled at the application layer.

### Laravel Sanctum (Token Authentication)

Sanctum issues API tokens validated on every LaunchPad request. All ownership decisions compare the resource's `student_id` column against `$request->user()->id` — clients never send user IDs to assert identity. This prevents ownership spoofing.

### Inline Ownership Guards (Manual 403)

Rather than using Laravel Gates or Policies, ownership is enforced inline in each controller method with a direct comparison. This keeps the authorization logic co-located with the action it protects and avoids indirection for a domain with no complex permission matrix.

### Auto-Create Pattern (BMC + Team)

Every SideHustle record is immediately followed by the creation of an empty `BusinessModelCanvas` and `Team` record. This enforces a strict one-to-one relationship at the application level: a SideHustle can never exist without its canvas or team. Clients never need to issue separate creation requests for these resources.

### Derived Boolean Flag (`has_open_positions`)

Rather than requiring every consumer to run a count query on the `positions` table, `side_hustles.has_open_positions` is maintained as a denormalized boolean that is recalculated and written on every position create, update, or delete. This keeps the SideHustle's summary state immediately readable with a single column access.

### Terminal Position States

Position status follows a one-way state machine. `OPEN` is the only non-terminal state; once a position reaches `FILLED` or `CLOSED` it cannot be changed further. This rule is enforced in `PositionController` and is load-bearing for the ConnectHub Classifieds ownership model.

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

### LaunchPad Internal Architecture

```
HTTP Client
    │
    ▼
┌──────────────────────────────────────────────────────────┐
│                    routes/api.php                         │
│              middleware: auth:sanctum                     │
└──────┬───────────┬───────────┬───────────┬───────────────┘
       │           │           │           │           │
       ▼           ▼           ▼           ▼           ▼
┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
│ Sandbox  │ │SideHustle│ │BusinessMo│ │  Team    │ │ Position │
│Controller│ │Controller│ │delCanvas │ │Controller│ │Controller│
│          │ │          │ │Controller│ │          │ │          │
│ index    │ │ index    │ │          │ │ show     │ │ index    │
│ store    │ │ store    │ │ show     │ │ addMember│ │ store    │
│ show     │ │ show     │ │ update   │ │removeMem.│ │ update   │
│ update   │ │ update   │ │          │ │          │ │ destroy  │
│ destroy  │ │ destroy  │ └─────┬────┘ └─────┬────┘ └─────┬────┘
└─────┬────┘ │createFrom│       │             │            │
      │      │ Sandbox  │       │             │            │
      │      │launchpad │       │             │            │
      │      │ Summary  │       │             │            │
      │      └────┬─────┘       │             │            │
      │           │             │             │            │
      ▼           ▼             ▼             ▼            ▼
┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────────────┐
│ Sandbox  │ │SideHustle│ │ Business │ │   Team   │ │    Position      │
│  Model   │ │  Model   │ │ Model    │ │  Model   │ │    Model         │
│          │ │          │ │  Canvas  │ │ TeamMem. │ │                  │
└──────────┘ └──────────┘ └──────────┘ └──────────┘ │syncOpenPositions │
                                                     │    Flag()        │
                                                     └────────┬─────────┘
                                                              │
                                                              ▼
                                                     ┌──────────────────┐
                                                     │side_hustles      │
                                                     │has_open_positions│
                                                     │   (UPDATE)       │
                                                     └──────────────────┘
```

### Sandbox Promotion Flow

```
Sandbox ──── POST /api/sandboxes/{id}/launch ────▶ SideHustle
                                                      │
                                        ┌─────────────┼─────────────┐
                                        ▼             ▼             ▼
                                   (auto-create) (auto-create)  Positions
                                   empty BMC    empty Team      (none yet)
```

---

## 4. Components

### 4.1 Controllers

#### `SandboxController`

Handles full CRUD for experimental idea workspaces.

| Method | Route | Responsibility |
|---|---|---|
| `index()` | `GET /api/sandboxes` | Returns all sandboxes; optional `?student_id` filter |
| `store()` | `POST /api/sandboxes` | Validates `student_id` exists in `users`; creates sandbox |
| `show()` | `GET /api/sandboxes/{id}` | Returns a single sandbox |
| `update()` | `PUT /api/sandboxes/{id}` | Ownership guard; updates `title` and/or `description` |
| `destroy()` | `DELETE /api/sandboxes/{id}` | Ownership guard; deletes sandbox |

Ownership check: `sandbox->student_id !== $request->user()->id` → `403 Forbidden`.

---

#### `SideHustleController`

The most complex controller. Handles the full venture lifecycle, sandbox promotion, and the aggregated dashboard summary.

| Method | Route | Responsibility |
|---|---|---|
| `index()` | `GET /api/sidehustles` | Returns SideHustles; optional `?student_id` filter |
| `store()` | `POST /api/sidehustles` | Creates SideHustle; **auto-creates empty BMC and Team** |
| `show()` | `GET /api/sidehustles/{id}` | Returns SideHustle with eager-loaded BMC, Team (with members), Positions |
| `update()` | `PUT /api/sidehustles/{id}` | Ownership guard; updates title, description, status |
| `destroy()` | `DELETE /api/sidehustles/{id}` | Ownership guard; cascades to BMC, Team, TeamMembers, Positions |
| `createFromSandbox()` | `POST /api/sandboxes/{id}/launch` | Ownership guard; clones sandbox → SideHustle; auto-creates BMC + Team |
| `launchpadSummary()` | `GET /api/launchpad/summary` | 4 scoped queries; returns counts + SideHustles with open positions only |

---

#### `BusinessModelCanvasController`

Two-method controller — the BMC record is always auto-created by `SideHustleController`; this controller only reads and updates it.

| Method | Route | Responsibility |
|---|---|---|
| `show()` | `GET /api/sidehustles/{id}/bmc` | Returns the BMC for the given SideHustle |
| `update()` | `PUT /api/sidehustles/{id}/bmc` | Ownership guard via parent SideHustle; partial update of any of the 9 sections |

All nine sections are `nullable|string`: `key_partners`, `key_activities`, `key_resources`, `value_propositions`, `customer_relationships`, `channels`, `customer_segments`, `cost_structure`, `revenue_streams`.

---

#### `TeamController`

Manages the team roster for a SideHustle.

| Method | Route | Responsibility |
|---|---|---|
| `show()` | `GET /api/teams/{sideHustleId}` | Returns Team with nested members |
| `addMember()` | `POST /api/teams/{teamId}/members` | Ownership guard (via Team → SideHustle); adds a member with role |
| `removeMember()` | `DELETE /api/teams/{teamId}/members/{memberId}` | Ownership guard; removes a team member |

Ownership traversal: `Team → SideHustle → student_id === auth user`.

---

#### `PositionController`

Manages open roles within a SideHustle. Contains the `syncOpenPositionsFlag()` private helper that maintains the denormalized `has_open_positions` field.

| Method | Route | Responsibility |
|---|---|---|
| `index()` | `GET /api/positions/{sideHustleId}` | Lists all positions for a SideHustle |
| `store()` | `POST /api/positions` | Ownership guard; creates position (status defaults to `OPEN`); syncs flag |
| `update()` | `PUT /api/positions/{id}` | Ownership guard; terminal state guard; updates fields; syncs flag |
| `destroy()` | `DELETE /api/positions/{id}` | Ownership guard; deletes position; syncs flag |

Terminal state guard (in `update`): if `position->status !== 'OPEN'`, any status change request returns `422`.

---

### 4.2 Models

| Model | Table | Key Relationships |
|---|---|---|
| `Sandbox` | `sandboxes` | `belongsTo User (student_id)`, `hasOne SideHustle` |
| `SideHustle` | `side_hustles` | `belongsTo User (student_id)`, `belongsTo Sandbox`, `hasOne BusinessModelCanvas`, `hasOne Team`, `hasMany Position`, `hasMany ClassifiedPost` |
| `BusinessModelCanvas` | `business_model_canvases` | `belongsTo SideHustle` |
| `Team` | `teams` | `belongsTo SideHustle`, `hasMany TeamMember` |
| `TeamMember` | `team_members` | `belongsTo Team`, `belongsTo User (student_id)` |
| `Position` | `positions` | `belongsTo SideHustle`, `hasOne ClassifiedPost` |

`SideHustle` declares `hasMany(ClassifiedPost)` and `Position` declares `hasOne(ClassifiedPost)`. These relations pre-wire the ConnectHub (Q3) foreign key dependencies without creating a hard runtime dependency — ConnectHub reads these tables directly.

`SideHustle` casts `has_open_positions` to boolean.

---

### 4.3 Private Helper: `syncOpenPositionsFlag()`

Defined in `PositionController`. This is the sole write path for `side_hustles.has_open_positions`.

```
syncOpenPositionsFlag(int $sideHustleId)
  │
  ├─ query: does any Position with status=OPEN exist for this SideHustle?
  │
  ├─ true  → SideHustle::where(id)->update([has_open_positions => true])
  │
  └─ false → SideHustle::where(id)->update([has_open_positions => false])
```

Called by: `store()`, `update()`, `destroy()` in `PositionController`.

---

## 5. Design Patterns

### 5.1 Inline Ownership Guard

**Where:** Every mutating endpoint in all five controllers.

**Problem solved:** Resources are user-owned; only the owner may modify or delete them.

**Solution:** A direct comparison between the resource's `student_id` (or the parent`s `student_id` for TeamMember/BMC paths) and `$request->user()->id`. On mismatch, a manual `403 Forbidden` JSON response is returned immediately. No Laravel Gate or Policy is registered — the permission rules are simple enough that co-located inline checks reduce indirection.

```
PUT /api/sandboxes/{id}
        │
        ▼
SandboxController::update()
        │
        ├─ [ownership guard]
        │    $sandbox->student_id !== $request->user()->id  ──▶  403 Forbidden
        │
        ├─ [validate] title, description
        │
        ▼
$sandbox->update([...])
        │
        ▼
HTTP 200 { sandbox }
```

### 5.2 Auto-Create Pattern (BMC + Team)

**Where:** `SideHustleController::store()` and `SideHustleController::createFromSandbox()`.

**Problem solved:** BusinessModelCanvas and Team have a strict one-to-one relationship with SideHustle. Allowing these records to be created separately risks orphaned SideHustles (no canvas, no team).

**Solution:** Immediately after `SideHustle::create()` succeeds, two more inserts are issued inline:

```
SideHustleController::store()
    │
    ▼
SideHustle::create({ student_id, title, description, status })
    │
    ├── $sideHustle->bmc()->create([])      ──▶  business_model_canvases (INSERT, all nulls)
    │
    └── $sideHustle->team()->create([])     ──▶  teams (INSERT)
```

The response always includes the freshly created BMC and Team, so the client has all three resources in a single round trip.

### 5.3 Derived Boolean Flag (Position Sync)

**Where:** `PositionController::syncOpenPositionsFlag()`, called after every position mutation.

**Problem solved:** Consumers (including ConnectHub, the summary endpoint, and any future UI) need to know instantly whether a SideHustle has open positions without running a sub-query.

**Solution:** `has_open_positions` is a denormalized boolean written eagerly on every position change. The derivation query is trivial (a single count > 0 check), so the cost is negligible.

```
POST /api/positions
    │
    ▼
Position::create({ side_hustle_id, title, status: OPEN })
    │
    ▼
syncOpenPositionsFlag($sideHustleId)
    │  → positions.where(side_hustle_id, status=OPEN).exists()  ──▶  true
    │
    ▼
SideHustle::update({ has_open_positions: true })
```

### 5.4 Terminal State Machine (Position Status)

**Where:** `PositionController::update()`.

**Problem solved:** Positions should not cycle between statuses. A filled or closed role should not be re-opened without intention; allowing free transitions could corrupt the ConnectHub Classifieds ownership checks which rely on `positions.status`.

**Solution:** `OPEN` is the only state from which a transition is allowed. `FILLED` and `CLOSED` are terminal — any attempt to change status from these states returns `422 Unprocessable`.

```
Status Transition Map:

  OPEN ──────▶ FILLED   ✓
  OPEN ──────▶ CLOSED   ✓
  FILLED ──▶ *          ✗  422 Unprocessable
  CLOSED ──▶ *          ✗  422 Unprocessable
```

### 5.5 Sandbox-to-SideHustle Promotion

**Where:** `SideHustleController::createFromSandbox()`.

**Problem solved:** Sandboxes are lightweight scratch spaces. When a student is ready to commit, the idea needs to be elevated into a full SideHustle (with BMC, Team, status tracking, and position management) without re-entering all the data.

**Solution:** The `POST /api/sandboxes/{id}/launch` endpoint clones the sandbox's `title`, `description`, and `student_id` into a new SideHustle, sets `sandbox_id` (preserving the link), then immediately auto-creates the blank BMC and Team.

```
POST /api/sandboxes/{id}/launch
    │
    ▼
Sandbox::findOrFail($id)
    │
    ├─ [ownership guard] sandbox->student_id !== auth user  ──▶  403
    │
    ▼
SideHustle::create({
    student_id: sandbox.student_id,
    sandbox_id: sandbox.id,
    title:       sandbox.title,
    description: sandbox.description,
    status:      'IN_THE_LAB'
})
    │
    ├── $sh->bmc()->create([])     ──▶  blank BMC
    └── $sh->team()->create([])    ──▶  blank Team
    │
    ▼
HTTP 201 { sideHustle + bmc + team + positions }
```

---

## 6. Database Schema

LaunchPad adds six tables to the shared PostgreSQL database.

```
┌─────────────────────────────────────────────────────────────────┐
│  users (from Q1 Auth)                                           │
│  id · name · email · password · created_at · updated_at        │
└──────────┬──────────────────────────────────────────────────────┘
           │
    ┌──────┴──────────────────────────────────────────────────┐
    │                                                         │
    ▼                                                         │
┌───────────────────────────────────────┐                    │
│  sandboxes                            │                    │
│  id                                   │                    │
│  student_id ────────────(FK users,    │                    │
│  title (string)          cascade)     │                    │
│  description (text, nullable)         │                    │
│  created_at · updated_at              │                    │
└──────────┬────────────────────────────┘                    │
           │ (nullable FK)                                    │
           ▼                                                  ▼
┌───────────────────────────────────────────────────────────────┐
│  side_hustles                                                 │
│  id                                                           │
│  student_id ────────────────────────────────(FK users,        │
│  sandbox_id (nullable) ────────(FK sandboxes, nullOnDelete)    cascade)
│  title (string)                                               │
│  description (text, nullable)                                 │
│  status (enum: IN_THE_LAB | LIVE_VENTURE, default IN_THE_LAB) │
│  has_open_positions (boolean, default false)                  │
│  created_at · updated_at                                      │
└──────┬────────────────────────────────────────────────────────┘
       │
  ┌────┼─────────────────────────────────────────┐
  │    │                                         │
  ▼    ▼                                         ▼
┌──────────────────────────────┐   ┌───────────────────────────────────────┐
│  business_model_canvases     │   │  teams                                │
│  id                          │   │  id                                   │
│  side_hustle_id ─(FK sh,     │   │  side_hustle_id ──────(FK sh, cascade)│
│                   cascade)   │   │  created_at · updated_at              │
│  key_partners (text|null)    │   └──────────┬────────────────────────────┘
│  key_activities (text|null)  │              │
│  key_resources (text|null)   │              ▼
│  value_propositions (t|null) │   ┌──────────────────────────────┐
│  customer_relationships(t|n) │   │  team_members                │
│  channels (text|null)        │   │  id                          │
│  customer_segments (text|n)  │   │  team_id ──────(FK teams,    │
│  cost_structure (text|null)  │   │               cascade)       │
│  revenue_streams (text|null) │   │  student_id ───(FK users,    │
│  created_at · updated_at     │   │               cascade)       │
└──────────────────────────────┘   │  role (string, nullable)     │
                                   │  joined_at (timestamp, null) │
  ┌─────────────────────────────┐  │  created_at · updated_at     │
  │  positions                  │  └──────────────────────────────┘
  │  id                         │
  │  side_hustle_id ─(FK sh,    │
  │                  cascade)   │
  │  title (string)             │
  │  description (text, null)   │
  │  status (enum: OPEN |       │
  │    FILLED | CLOSED,         │
  │    default OPEN)            │
  │  created_at · updated_at    │
  └─────────────────────────────┘
```

**Cross-service reads used by Q3 ConnectHub (no FK constraint, same DB):**
- `classified_posts.position_id` references `positions.id`
- `classified_posts.side_hustle_id` references `side_hustles.id`
- `side_hustles.student_id` is read for ownership enforcement in ConnectHub

---

## 7. Data Flow

### 7.1 Creating a SideHustle

```
Client
  │
  │  POST /api/sidehustles
  │  { "student_id": 42, "title": "Compound Butter Co." }
  │
  ▼
auth:sanctum middleware
  │  resolves $request->user()  ──────────────────────▶ users table
  ▼
SideHustleController::store()
  │  validates student_id, title, description
  ▼
SideHustle::create({ student_id, title, description, status: 'IN_THE_LAB' })
  │                ─────────────────────────────────▶ side_hustles (INSERT)
  │
  ├─ $sideHustle->bmc()->create([])
  │                ─────────────────────────────────▶ business_model_canvases (INSERT)
  │
  └─ $sideHustle->team()->create([])
                   ─────────────────────────────────▶ teams (INSERT)
  │
  ▼
HTTP 201 { sideHustle + bmc + team + positions:[] }
```

### 7.2 Promoting a Sandbox to a SideHustle

```
Client
  │
  │  POST /api/sandboxes/{id}/launch
  │
  ▼
auth:sanctum ──▶ resolves $request->user()
  ▼
SideHustleController::createFromSandbox()
  │
  ├─ Sandbox::findOrFail($id)  ──────────────────────▶ sandboxes (SELECT)
  │
  ├─ [ownership guard]
  │    sandbox->student_id !== user->id  ──▶ 403 Forbidden
  │
  ▼
SideHustle::create({
    student_id: sandbox.student_id,
    sandbox_id: sandbox.id,
    title:       sandbox.title,
    description: sandbox.description,
    status:      'IN_THE_LAB'
})  ──────────────────────────────────────▶ side_hustles (INSERT)
  │
  ├─ $sideHustle->bmc()->create([])  ─────▶ business_model_canvases (INSERT)
  └─ $sideHustle->team()->create([]) ─────▶ teams (INSERT)
  │
  ▼
HTTP 201 { sideHustle + bmc + team + positions:[] }
```

### 7.3 Creating a Position (with Flag Sync)

```
Client
  │
  │  POST /api/positions
  │  { "side_hustle_id": 5, "title": "Marketing Lead" }
  │
  ▼
auth:sanctum ──▶ resolves $request->user()
  ▼
PositionController::store()
  │  validates side_hustle_id, title, status
  │
  ├─ [ownership guard]
  │    sideHustle->student_id !== user->id  ──▶ 403 Forbidden
  │
  ▼
Position::create({ side_hustle_id, title, status: 'OPEN' })
  │                ────────────────────────────▶ positions (INSERT)
  │
  ▼
syncOpenPositionsFlag(5)
  │  query: any OPEN position for side_hustle_id=5?  ──▶ positions (SELECT)
  │  result: true
  │
  ▼
SideHustle::update({ has_open_positions: true })
  │                ─────────────────────────────▶ side_hustles (UPDATE)
  ▼
HTTP 201 { position }
```

### 7.4 LaunchPad Summary

```
Client
  │
  │  GET /api/launchpad/summary
  │
  ▼
auth:sanctum ──▶ resolves $request->user() (id = 42)
  ▼
SideHustleController::launchpadSummary()
  │
  ├─ Sandbox::where(student_id, 42)->count()
  │                ────────────────────────────▶ sandboxes (SELECT COUNT)
  │
  ├─ SideHustle::where(student_id, 42)
  │              ->where(status, 'IN_THE_LAB')->count()
  │                ────────────────────────────▶ side_hustles (SELECT COUNT)
  │
  ├─ SideHustle::where(student_id, 42)
  │              ->where(status, 'LIVE_VENTURE')->count()
  │                ────────────────────────────▶ side_hustles (SELECT COUNT)
  │
  └─ SideHustle::where(student_id, 42)
                 ->with(['positions' => fn → where(status, OPEN)])
                 ->get()
                  ────────────────────────────▶ side_hustles + positions (SELECT)
  │
  ▼
HTTP 200 {
  sandbox_count, in_the_lab_count, live_venture_count,
  side_hustles: [ { ...sideHustle, positions: [ ...openOnly ] } ]
}
```

---

## 8. Cross-Service Interfaces

### 8.1 Session Validation Interface (Auth Q1 → LaunchPad)

Every LaunchPad route requires a valid Sanctum bearer token. The `auth:sanctum` middleware resolves the token from the `Authorization: Bearer {token}` header, looks up the token in the `personal_access_tokens` table (managed by Q1 Auth), and populates `$request->user()`.

LaunchPad uses `$request->user()` to:
- Enforce ownership on all mutating sandbox endpoints
- Enforce ownership on all mutating SideHustle endpoints (directly and via Team, BMC, and Position traversal)
- Scope the `launchpadSummary` query to the authenticated user's data only

No user ID is ever accepted from the request body for authentication purposes.

Obtain a token via the Auth service:
```
POST /login
Content-Type: application/json

{ "email": "student@hatchloom.dev", "password": "password" }
```

### 8.2 Position Status Interface (LaunchPad Q2 → ConnectHub Q3)

ConnectHub reads directly from the `positions` and `side_hustles` tables when creating a `ClassifiedPost` (same PostgreSQL instance — no API call). This interface has two requirements:

1. `positions.id` is the foreign key target for `classified_posts.position_id`
2. `side_hustles.student_id` is read to verify the posting user owns the venture the position belongs to

```
ConnectHub: POST /api/classifieds
        │
        ▼
positions table (LaunchPad Q2)
        │
        ├─ position exists?  ──── No ──▶ 422 Unprocessable
        │
        ├─ position.sideHustle.student_id === auth user?
        │         No ──▶ 403 Forbidden
        │
        ▼
ClassifiedPost::create({ position_id, side_hustle_id, ..., status: 'OPEN' })
```

The `positions` table must contain valid records with correct `side_hustle_id` foreign keys, and `side_hustles` must contain correct `student_id` values, for ConnectHub Q3 to function correctly.
