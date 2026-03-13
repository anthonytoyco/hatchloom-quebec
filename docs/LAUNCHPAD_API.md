# LaunchPad API Documentation

**Service:** LaunchPad (Q2 — Team Quebec)
**Base URL:** `http://localhost/api`
**Authentication:** All LaunchPad endpoints require a Sanctum bearer token issued by the Auth service (Q1).

Add the following header to every request:
```
Authorization: Bearer {token}
```

Tokens are obtained via `POST /login` (Auth service). All unauthenticated requests return `401 Unauthorized`.

---

## Table of Contents

- [Sandbox Module](#sandbox-module)
  - [GET /api/sandboxes](#get-apisandboxes)
  - [POST /api/sandboxes](#post-apisandboxes)
  - [GET /api/sandboxes/{id}](#get-apisandboxesid)
  - [PUT /api/sandboxes/{id}](#put-apisandboxesid)
  - [DELETE /api/sandboxes/{id}](#delete-apisandboxesid)
  - [POST /api/sandboxes/{id}/launch](#post-apisandboxesidlaunch)
- [SideHustle Module](#sidehustle-module)
  - [GET /api/launchpad/summary](#get-apilaunchpadsummary)
  - [GET /api/sidehustles](#get-apisidehustles)
  - [POST /api/sidehustles](#post-apisidehustles)
  - [GET /api/sidehustles/{id}](#get-apisidehustlesid)
  - [PUT /api/sidehustles/{id}](#put-apisidehustlesid)
  - [DELETE /api/sidehustles/{id}](#delete-apisidehustlesid)
- [Business Model Canvas Module](#business-model-canvas-module)
  - [GET /api/sidehustles/{id}/bmc](#get-apisidehustlesidbmc)
  - [PUT /api/sidehustles/{id}/bmc](#put-apisidehustlesidbmc)
- [Team Module](#team-module)
  - [GET /api/teams/{sideHustleId}](#get-apiteamssidehustleid)
  - [POST /api/teams/{teamId}/members](#post-apiteamsteamidmembers)
  - [DELETE /api/teams/{teamId}/members/{memberId}](#delete-apiteamsteamidmembersmemberid)
- [Position Module](#position-module)
  - [GET /api/positions/{sideHustleId}](#get-apipositionssidehustleid)
  - [POST /api/positions](#post-apipositions)
  - [PUT /api/positions/{id}](#put-apipositionsid)
  - [DELETE /api/positions/{id}](#delete-apipositionsid)
- [Cross-Service Dependencies](#cross-service-dependencies)

---

## Sandbox Module

A Sandbox is an experimental workspace where a student explores an idea. It can be promoted to a SideHustle via the `/launch` endpoint.

---

### GET /api/sandboxes

Lists sandboxes. Optionally filter by `student_id`.

| Field | Value |
|---|---|
| Method | `GET` |
| URL | `/api/sandboxes` |
| Body | None |

**Query Parameters**

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `student_id` | integer | No | Filter results to a specific student |

**Response 200 — OK**

```json
[
  {
    "id": 1,
    "student_id": 42,
    "title": "Food Tech Lab",
    "description": "Experimenting with artisanal food products.",
    "created_at": "2026-03-13T09:00:00.000000Z",
    "updated_at": "2026-03-13T09:00:00.000000Z"
  }
]
```

| Code | Meaning |
|---|---|
| `200` | Success |
| `401` | Missing or invalid bearer token |

---

### POST /api/sandboxes

Creates a new sandbox.

| Field | Value |
|---|---|
| Method | `POST` |
| URL | `/api/sandboxes` |
| Content-Type | `application/json` |

**Request Body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `student_id` | integer | Yes | Must exist in `users` table |
| `title` | string | Yes | Name of the sandbox |
| `description` | string | No | Optional description |

```json
{
  "student_id": 42,
  "title": "Food Tech Lab",
  "description": "Experimenting with artisanal food products."
}
```

**Response 201 — Created**

```json
{
  "id": 1,
  "student_id": 42,
  "title": "Food Tech Lab",
  "description": "Experimenting with artisanal food products.",
  "created_at": "2026-03-13T09:00:00.000000Z",
  "updated_at": "2026-03-13T09:00:00.000000Z"
}
```

| Code | Meaning |
|---|---|
| `201` | Sandbox created |
| `401` | Missing or invalid bearer token |
| `422` | Validation error — missing `student_id` or `title`, or `student_id` not found |

---

### GET /api/sandboxes/{id}

Retrieves a single sandbox by ID.

| Field | Value |
|---|---|
| Method | `GET` |
| URL | `/api/sandboxes/{id}` |
| Body | None |

**Response 200 — OK**

Same shape as the POST response above.

| Code | Meaning |
|---|---|
| `200` | Success |
| `401` | Missing or invalid bearer token |
| `404` | Sandbox not found |

---

### PUT /api/sandboxes/{id}

Updates a sandbox's title and/or description.

| Field | Value |
|---|---|
| Method | `PUT` |
| URL | `/api/sandboxes/{id}` |
| Content-Type | `application/json` |

**Request Body** (all fields optional)

| Field | Type | Notes |
|---|---|---|
| `title` | string | New title |
| `description` | string | New description (nullable) |

```json
{
  "title": "Butter Lab v2"
}
```

**Response 200 — OK** — returns updated sandbox.

| Code | Meaning |
|---|---|
| `200` | Updated |
| `401` | Missing or invalid bearer token |
| `404` | Sandbox not found |
| `422` | Validation error |

---

### DELETE /api/sandboxes/{id}

Deletes a sandbox.

| Field | Value |
|---|---|
| Method | `DELETE` |
| URL | `/api/sandboxes/{id}` |
| Body | None |

**Response 200 — OK**

```json
{ "message": "Sandbox deleted" }
```

| Code | Meaning |
|---|---|
| `200` | Deleted |
| `401` | Missing or invalid bearer token |
| `404` | Sandbox not found |

---

### POST /api/sandboxes/{id}/launch

Promotes a Sandbox to a SideHustle. Inherits the sandbox's `title` and `description`. Automatically creates an empty BMC and Team. Sets `sandbox_id` on the resulting SideHustle.

| Field | Value |
|---|---|
| Method | `POST` |
| URL | `/api/sandboxes/{id}/launch` |
| Body | None |

**Response 201 — Created**

```json
{
  "id": 5,
  "student_id": 42,
  "sandbox_id": 1,
  "title": "Food Tech Lab",
  "description": "Experimenting with artisanal food products.",
  "status": "IN_THE_LAB",
  "has_open_positions": false,
  "bmc": { "id": 5, "side_hustle_id": 5, "key_partners": null, "..." : "..." },
  "team": { "id": 5, "side_hustle_id": 5 },
  "positions": []
}
```

| Code | Meaning |
|---|---|
| `201` | SideHustle created from sandbox |
| `401` | Missing or invalid bearer token |
| `404` | Sandbox not found |

---

## SideHustle Module

A SideHustle is a student's entrepreneurial venture. It has an associated Business Model Canvas, a Team, and open Positions.

**Status values:** `IN_THE_LAB` (default) · `LIVE_VENTURE`

---

### GET /api/launchpad/summary

Returns aggregate counts for the authenticated student's LaunchPad home (Screen 200). Only returns data owned by the currently authenticated user.

| Field | Value |
|---|---|
| Method | `GET` |
| URL | `/api/launchpad/summary` |
| Body | None |

**Response 200 — OK**

```json
{
  "sandbox_count": 2,
  "in_the_lab_count": 1,
  "live_venture_count": 1,
  "side_hustles": [
    {
      "id": 5,
      "student_id": 42,
      "title": "Compound Butter Co.",
      "status": "IN_THE_LAB",
      "has_open_positions": true,
      "positions": [
        { "id": 3, "title": "Marketing Lead", "status": "OPEN" }
      ]
    }
  ]
}
```

| Code | Meaning |
|---|---|
| `200` | Success |
| `401` | Missing or invalid bearer token |

---

### GET /api/sidehustles

Lists SideHustles. Optionally filter by `student_id`.

| Field | Value |
|---|---|
| Method | `GET` |
| URL | `/api/sidehustles` |
| Body | None |

**Query Parameters**

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `student_id` | integer | No | Filter to a specific student |

**Response 200 — OK** — returns array of SideHustles with nested `bmc`, `team`, `positions`.

| Code | Meaning |
|---|---|
| `200` | Success |
| `401` | Missing or invalid bearer token |

---

### POST /api/sidehustles

Creates a new SideHustle. Automatically creates an empty BMC and Team.

| Field | Value |
|---|---|
| Method | `POST` |
| URL | `/api/sidehustles` |
| Content-Type | `application/json` |

**Request Body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `student_id` | integer | Yes | Must exist in `users` table |
| `title` | string | Yes | Name of the venture (max 255) |
| `description` | string | No | Optional description |

```json
{
  "student_id": 42,
  "title": "Compound Butter Co.",
  "description": "Artisanal compound butters for home chefs."
}
```

**Response 201 — Created**

```json
{
  "id": 5,
  "student_id": 42,
  "sandbox_id": null,
  "title": "Compound Butter Co.",
  "description": "Artisanal compound butters for home chefs.",
  "status": "IN_THE_LAB",
  "has_open_positions": false,
  "bmc": { "id": 5, "side_hustle_id": 5, "key_partners": null },
  "team": { "id": 5, "side_hustle_id": 5, "members": [] },
  "positions": []
}
```

| Code | Meaning |
|---|---|
| `201` | SideHustle created |
| `401` | Missing or invalid bearer token |
| `422` | Validation error |

---

### GET /api/sidehustles/{id}

Returns a single SideHustle with nested BMC, team (with members), and positions.

| Field | Value |
|---|---|
| Method | `GET` |
| URL | `/api/sidehustles/{id}` |

**Response 200 — OK** — full SideHustle object with all relations.

| Code | Meaning |
|---|---|
| `200` | Success |
| `401` | Missing or invalid bearer token |
| `404` | Not found |

---

### PUT /api/sidehustles/{id}

Updates a SideHustle's title, description, and/or status.

| Field | Value |
|---|---|
| Method | `PUT` |
| URL | `/api/sidehustles/{id}` |
| Content-Type | `application/json` |

**Request Body** (all fields optional)

| Field | Type | Notes |
|---|---|---|
| `title` | string | Max 255 characters |
| `description` | string | Nullable |
| `status` | string | `IN_THE_LAB` or `LIVE_VENTURE` |

```json
{
  "status": "LIVE_VENTURE"
}
```

**Response 200 — OK** — returns updated SideHustle with all relations.

| Code | Meaning |
|---|---|
| `200` | Updated |
| `401` | Missing or invalid bearer token |
| `404` | Not found |
| `422` | Invalid `status` value |

---

### DELETE /api/sidehustles/{id}

Deletes a SideHustle. Cascades to BMC, Team, TeamMembers, and Positions.

| Field | Value |
|---|---|
| Method | `DELETE` |
| URL | `/api/sidehustles/{id}` |

**Response 200 — OK**

```json
{ "message": "SideHustle deleted successfully" }
```

| Code | Meaning |
|---|---|
| `200` | Deleted |
| `401` | Missing or invalid bearer token |
| `404` | Not found |

---

## Business Model Canvas Module

Each SideHustle has exactly one BMC with 9 editable text sections. The BMC is created automatically (empty) when the SideHustle is created.

---

### GET /api/sidehustles/{id}/bmc

Returns the BMC for a SideHustle.

| Field | Value |
|---|---|
| Method | `GET` |
| URL | `/api/sidehustles/{id}/bmc` |
| Body | None |

**Response 200 — OK**

```json
{
  "id": 5,
  "side_hustle_id": 5,
  "key_partners": "Local farms",
  "key_activities": "Production, packaging",
  "key_resources": null,
  "value_propositions": "Premium butters",
  "customer_relationships": null,
  "channels": null,
  "customer_segments": "Home cooks",
  "cost_structure": null,
  "revenue_streams": "Direct sales",
  "created_at": "2026-03-13T09:00:00.000000Z",
  "updated_at": "2026-03-13T09:00:00.000000Z"
}
```

| Code | Meaning |
|---|---|
| `200` | Success |
| `401` | Missing or invalid bearer token |
| `404` | SideHustle not found |

---

### PUT /api/sidehustles/{id}/bmc

Updates one or more BMC sections. Only the fields provided are updated; omitted fields remain unchanged.

| Field | Value |
|---|---|
| Method | `PUT` |
| URL | `/api/sidehustles/{id}/bmc` |
| Content-Type | `application/json` |

**Request Body** (all fields optional)

| Field | Type | Notes |
|---|---|---|
| `key_partners` | string | Nullable |
| `key_activities` | string | Nullable |
| `key_resources` | string | Nullable |
| `value_propositions` | string | Nullable |
| `customer_relationships` | string | Nullable |
| `channels` | string | Nullable |
| `customer_segments` | string | Nullable |
| `cost_structure` | string | Nullable |
| `revenue_streams` | string | Nullable |

```json
{
  "key_partners": "Local farms, specialty grocers",
  "value_propositions": "Premium artisanal butters for home chefs"
}
```

**Response 200 — OK** — returns the updated BMC object.

| Code | Meaning |
|---|---|
| `200` | Updated |
| `401` | Missing or invalid bearer token |
| `404` | SideHustle not found |

---

## Team Module

Each SideHustle has exactly one Team. The Team is created automatically when the SideHustle is created.

---

### GET /api/teams/{sideHustleId}

Returns the Team and its members for a given SideHustle.

| Field | Value |
|---|---|
| Method | `GET` |
| URL | `/api/teams/{sideHustleId}` |
| Body | None |

**Response 200 — OK**

```json
{
  "id": 5,
  "side_hustle_id": 5,
  "created_at": "2026-03-13T09:00:00.000000Z",
  "updated_at": "2026-03-13T09:00:00.000000Z",
  "members": [
    {
      "id": 1,
      "team_id": 5,
      "student_id": 42,
      "role": "Founder",
      "joined_at": null
    }
  ]
}
```

| Code | Meaning |
|---|---|
| `200` | Success |
| `401` | Missing or invalid bearer token |
| `404` | Team not found for this SideHustle |

---

### POST /api/teams/{teamId}/members

Adds a member to a team.

| Field | Value |
|---|---|
| Method | `POST` |
| URL | `/api/teams/{teamId}/members` |
| Content-Type | `application/json` |

**Request Body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `student_id` | integer | Yes | User ID of the student to add |
| `role` | string | Yes | Role within the team |

```json
{
  "student_id": 55,
  "role": "Designer"
}
```

**Response 201 — Created**

```json
{
  "id": 2,
  "team_id": 5,
  "student_id": 55,
  "role": "Designer",
  "joined_at": null,
  "created_at": "2026-03-13T10:00:00.000000Z",
  "updated_at": "2026-03-13T10:00:00.000000Z"
}
```

| Code | Meaning |
|---|---|
| `201` | Member added |
| `401` | Missing or invalid bearer token |
| `404` | Team not found |
| `422` | Missing `student_id` or `role` |

---

### DELETE /api/teams/{teamId}/members/{memberId}

Removes a member from a team.

| Field | Value |
|---|---|
| Method | `DELETE` |
| URL | `/api/teams/{teamId}/members/{memberId}` |
| Body | None |

**Response 200 — OK**

```json
{ "message": "Team member removed" }
```

| Code | Meaning |
|---|---|
| `200` | Removed |
| `401` | Missing or invalid bearer token |
| `404` | Member not found in this team |

---

## Position Module

Positions represent open roles within a SideHustle. When an OPEN position exists, the SideHustle's `has_open_positions` flag is automatically set to `true`. The flag is re-evaluated on every create, update, and delete operation.

**Status values:** `OPEN` (default) · `FILLED` · `CLOSED`

> **Q3 dependency:** The ConnectHub Classifieds service reads directly from the `positions` table to verify ownership when creating a classified ad. `side_hustle_id` and `status` on this table are load-bearing for Q3.

---

### GET /api/positions/{sideHustleId}

Lists all positions for a SideHustle.

| Field | Value |
|---|---|
| Method | `GET` |
| URL | `/api/positions/{sideHustleId}` |
| Body | None |

**Response 200 — OK**

```json
[
  {
    "id": 3,
    "side_hustle_id": 5,
    "title": "Marketing Lead",
    "description": "Help grow our brand.",
    "status": "OPEN",
    "created_at": "2026-03-13T09:00:00.000000Z",
    "updated_at": "2026-03-13T09:00:00.000000Z"
  }
]
```

| Code | Meaning |
|---|---|
| `200` | Success |
| `401` | Missing or invalid bearer token |

---

### POST /api/positions

Creates a new position. Status defaults to `OPEN`. Updates `side_hustles.has_open_positions` to `true`.

| Field | Value |
|---|---|
| Method | `POST` |
| URL | `/api/positions` |
| Content-Type | `application/json` |

**Request Body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `side_hustle_id` | integer | Yes | Must exist in `side_hustles` table |
| `title` | string | Yes | Name of the role |
| `description` | string | No | Full description |
| `status` | string | No | Defaults to `OPEN`. One of: `OPEN`, `FILLED`, `CLOSED` |

```json
{
  "side_hustle_id": 5,
  "title": "Marketing Lead",
  "description": "Help grow our brand on social media and at local events."
}
```

**Response 201 — Created**

```json
{
  "id": 3,
  "side_hustle_id": 5,
  "title": "Marketing Lead",
  "description": "Help grow our brand on social media and at local events.",
  "status": "OPEN",
  "created_at": "2026-03-13T09:00:00.000000Z",
  "updated_at": "2026-03-13T09:00:00.000000Z"
}
```

| Code | Meaning |
|---|---|
| `201` | Position created |
| `401` | Missing or invalid bearer token |
| `422` | Missing `side_hustle_id` or `title`, invalid `status`, or `side_hustle_id` not found |

---

### PUT /api/positions/{id}

Updates a position. Re-evaluates `has_open_positions` on the parent SideHustle after the update.

| Field | Value |
|---|---|
| Method | `PUT` |
| URL | `/api/positions/{id}` |
| Content-Type | `application/json` |

**Request Body** (all fields optional)

| Field | Type | Notes |
|---|---|---|
| `title` | string | New title |
| `description` | string | New description (nullable) |
| `status` | string | `OPEN`, `FILLED`, or `CLOSED` |

```json
{
  "status": "FILLED"
}
```

**Response 200 — OK** — returns updated position.

| Code | Meaning |
|---|---|
| `200` | Updated |
| `401` | Missing or invalid bearer token |
| `404` | Position not found |
| `422` | Invalid `status` value |

---

### DELETE /api/positions/{id}

Deletes a position and re-evaluates `has_open_positions` on the parent SideHustle.

| Field | Value |
|---|---|
| Method | `DELETE` |
| URL | `/api/positions/{id}` |
| Body | None |

**Response 200 — OK**

```json
{ "message": "Position deleted" }
```

| Code | Meaning |
|---|---|
| `200` | Deleted |
| `401` | Missing or invalid bearer token |
| `404` | Position not found |

---

## Cross-Service Dependencies

### Auth Service (Q1) → LaunchPad (Q2)

Every LaunchPad route requires a valid Sanctum token. The `auth:sanctum` middleware enforces session validation on all endpoints. The token is obtained via the Auth service:

```
POST /login
Content-Type: application/json

{ "email": "student@hatchloom.dev", "password": "password" }
```

### LaunchPad (Q2) → ConnectHub (Q3) — Position Status Interface

ConnectHub Classifieds reads directly from the `positions` and `side_hustles` tables (same database, no API call). When `POST /api/classifieds` is called by Q3:

1. `position_id` is looked up in `positions`
2. `side_hustles.student_id` is compared against the authenticated user's ID
3. A mismatch returns `403 Forbidden`

This means the `positions` and `side_hustles` tables must be correctly populated with valid `student_id` foreign keys for Q3 to function.
