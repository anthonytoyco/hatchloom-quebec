  
# HATCHLOOM Work Pack

Auth \+ LaunchPad \+ ConnectHub Backend

Team Quebec

Members: Andrew, Anthony, Daniel, Ronald

Riipen Supervisor: Karl (Database Design Lead)

Hatchloom Inc.

## SCOPE OVERVIEW

Team Quebec owns three critical backend domains: Authentication and User Profiles, the LaunchPad pillar (Sandbox and SideHustle), and the ConnectHub pillar (feed, classifieds, messaging data model). Your team is smaller (3 people), but your scope includes the auth service which is foundational — no other service can function without user identity.

Your Riipen supervisor is Karl, who is the Database Design and AI Outline Document lead. Karl is also responsible for setting up the GitHub repository, Discord channels, and shared file exchange directories for all teams. Work closely with Karl on database schema design since your services have the most complex relational models (teams, positions, projects, BMC).

## Your Domain in Hatchloom

* Auth & User Profile Service — Registration, login, session management, profile CRUD, role-based identity (student, teacher, parent, school admin)

* LaunchPad Service — Sandbox CRUD, SideHustle CRUD, BMC (Business Model Canvas) tool data model, team panel, open positions

* ConnectHub Service — Feed/sharing actions, Classifieds (post creation, OPEN position listings), basic messaging data model

## Golden Path Coverage

Your services power these demo moments:

| Moment | Screen | Demo | What Your Service Provides |
| :---- | :---- | :---- | :---- |
| All | All | A \+ B | Auth: user identity for every API call across the entire platform |
| 7A — ConnectHub | 100 | A | Feed/sharing action, Classifieds post creation, OPEN position state |
| 8A — LaunchPad | 200 → 240 | A | LaunchPad overview, SideHustle with compound butter project, 1 open position, BMC tool editable |

## 2\. TIMELINE & DELIVERABLE SCHEDULE**

### Part 1 — CSSD2203 (\~10 days from Feb 20\)

Design documentation \+ initial implementation. Exact submission date to be confirmed with Professor Marius.

#### Days 1–3: Design & Architecture

* Sequence diagrams for your use cases (user registration and login, create SideHustle, post classified, share in feed)

* Module decomposition: define your service’s modules, interfaces, and operations

* Class diagrams for your domain objects (User, Session, Sandbox, SideHustle, BMC, Team, Position, ClassifiedPost, FeedItem)

* Identify design patterns you will use or plan to use (minimum 6 across all deliverables by D3)

#### Days 4–8: Initial Implementation

* Auth service: register user, login, validate session, get profile, update profile

* LaunchPad service: create sandbox, create SideHustle, get SideHustle, update BMC, manage team, manage positions

* ConnectHub service: create feed post, get feed, create classified post, list classifieds, mark position OPEN/FILLED

* Database tables for users, sessions, sandboxes, side hustles, BMC data, teams, positions, classifieds, feed posts

#### Days 8–10: Test Cases & Documentation

* Write 10–12 test cases covering your use cases (see Section 5 for format)

* Complete the SDD sections: sequence diagrams, module table, class table, design patterns, Gantt chart

* Ensure code is commented and each module has an associated main class for standalone execution

### Part 2 — CSSD2211 (\~2 weeks after Part 1\)

Containerization, API documentation, CI/CD.

* Create Docker images for your services (auth, LaunchPad, ConnectHub — can be one or separate containers)

* Write API documentation: every endpoint, HTTP method, input/output format, example requests and responses

* Write unit tests for each API endpoint

* Build a CI/CD pipeline that runs tests and builds Docker images on push

* Prepare Docker Compose or Kubernetes manifest for your services

| HATCHLOOM NOTE Auth is the single most critical service for the demo. If login does not work, nothing works. Prioritize a working auth flow (even simplified) over feature completeness in LaunchPad or ConnectHub. |
| :---- |

# **3\. INDIVIDUAL SUB-PACKS**

Each team member must be individually responsible for a defined scope. Assign these sub-packs among yourselves. Each sub-pack produces artifacts that satisfy both course deliverables and Hatchloom requirements.

## **Sub-Pack Q1: Auth & User Profile Service**

**Scope**

* User data model: User (polymorphic: student, teacher, parent, school admin, platform admin, entrepreneur), UserProfile, Session

* Registration: create user with username, password, role, school association

* Login: authenticate user, create session, return session token

* Session management: validate session token on every API call, session expiry

* Profile CRUD: get profile, update profile, list profiles (admin only)

* Role-based access: return user role and permissions with session validation

* Parent-child linking: associate parent account with student account

**CSSD2203 Deliverables**

* Sequence diagram: user registers, logs in, and is directed to dashboard

* Sequence diagram: session validation on an API call (middleware pattern)

* Class diagram for User, UserProfile, Session, Role

* Design pattern identification: potentially Singleton (session manager), Strategy (role-based permissions)

* 4 test cases (e.g., register user, login with valid/invalid credentials, session validation, role check)

**CSSD2211 Deliverables**

* Docker image for Auth service

* API docs for all auth and profile endpoints

* Unit tests for registration, login, session management, and role-based access

## **Sub-Pack Q2: LaunchPad Service**

**Scope**

* Sandbox data model: Sandbox (workspace for experimentation), SandboxTool (simplified tools within workspace)

* SideHustle data model: SideHustle, BMC (Business Model Canvas with 9 standard sections), Team, Position (role within SideHustle)

* API endpoints: create sandbox, get sandbox, create SideHustle, get SideHustle, update BMC section, list team members, create position, update position status (OPEN/FILLED)

* LaunchPad Home aggregation: list sandboxes and SideHustles for a student, summary counts (In the Lab count, Live Ventures count)

* Open position indicator: flag SideHustles with open positions

**CSSD2203 Deliverables**

* Sequence diagram: student navigates to LaunchPad, opens SideHustle, edits BMC

* Sequence diagram: student posts an open position from SideHustle

* Class diagram for Sandbox, SideHustle, BMC, Team, Position

* 4 test cases (e.g., create SideHustle, update BMC, create open position, LaunchPad summary counts)

**CSSD2211 Deliverables**

* API docs for all LaunchPad endpoints

* Unit tests for SideHustle CRUD, BMC editing, position management

## **Sub-Pack Q3: ConnectHub Service**

**Scope**

* Feed data model: FeedItem (post type: share, announcement, achievement), FeedAction (like, comment)

* Classifieds data model: ClassifiedPost (linked to SideHustle Position), ClassifiedStatus (OPEN, FILLED, CLOSED)

* Messaging data model: Message (with context\_type \+ context\_id for widget architecture), Thread

* API endpoints: create feed post, get feed for student, create classified post, list classifieds, get classified by ID, update classified status

* Feed sharing: student shares an achievement (e.g., Entrepreneur’s Choice win) to their contacts

**CSSD2203 Deliverables**

* Sequence diagram: student shares win in ConnectHub feed, posts OPEN position in classifieds

* Class diagram for FeedItem, FeedAction, ClassifiedPost, Message, Thread

* Design pattern identification: Observer pattern for feed updates, or Factory for different post types

* 4 test cases (e.g., create feed post, list classifieds, create classified from position, filter classifieds by status)

**CSSD2211 Deliverables**

* API docs for all ConnectHub endpoints

* Unit tests for feed, classifieds CRUD, and status transitions

# **4\. HANDOFF REQUIREMENTS**

When you submit your coursework, your code and documentation become the foundation that the Riipen leads build on. There will be no further support from your team after submission. The following handoff standards are non-negotiable:

* All code in the shared GitHub repository in the directory structure Karl sets up

* README.md in your service directory with: setup instructions, how to run locally, how to run tests, environment variables needed

* API documentation: every endpoint with method, URL, request body, response body, status codes

* Database migration files or schema SQL that can be run to create your tables

* Docker image that builds and runs without manual intervention

* Known issues list: anything incomplete, stubbed, or broken — be honest, this saves the Riipen team hours

| CRITICAL — AUTH PRIORITY The auth service must work before anything else can be tested. If you are behind, deprioritize ConnectHub features and ensure auth \+ LaunchPad basics are solid. A working login flow with session tokens is worth more than a feature-complete classifieds system. |
| :---- |

# **5\. TEST CASE FORMAT (CSSD2203 REQUIREMENT)**

Provide 10–12 test cases across the team using this format (per Professor Marius’s template):

| Field | Description |
| :---- | :---- |
| Test ID | Unique identifier, e.g. TC-Q1-001 |
| Category | Which part of the system is tested, e.g. User authentication |
| Requirements Coverage | Requirement ID tested, e.g. UC1-User-Login |
| Initial Condition | Preconditions, e.g. User account exists in DB |
| Procedure | Step-by-step: 1\. User sends POST /auth/login with username and password 2\. System validates credentials 3\. System returns session token |
| Expected Outcome | 200 response with valid session token, user role, and profile ID |
| Notes | Any additional context, edge cases, or constraints |

# **6\. KEY REFERENCE INFORMATION**

## **BMC (Business Model Canvas) Sections**

The SideHustle BMC tool must support editing these 9 standard sections:

* Key Partners

* Key Activities

* Key Resources

* Value Propositions

* Customer Relationships

* Channels

* Customer Segments

* Cost Structure

* Revenue Streams

Each section is a text field that the student can edit and save. The BMC is stored per-SideHustle.

## **User Roles**

| Role | Can Do | Auth Notes |
| :---- | :---- | :---- |
| Student | Full platform access: courses, LaunchPad, ConnectHub | Primary user. Age 12–17 (minor). |
| Teacher | Moderate, review submissions, monitor progress | Belongs to Hatchloom, not schools. Cross-school access. |
| School Admin | Read-only cohort analytics | Scoped to their school only. |
| Parent | Read-only view of linked child’s work | Must be linked to a student account. |
|  |  |  |
| Platform Admin | System admin, moderation, school onboarding | Hatchloom employee. |
| Entrepreneur | Post challenges, join calls, review submissions | Guest role, limited access. |

## **Screen Numbers You Power**

| Screen \# | Name | Your Responsibility |
| :---- | :---- | :---- |
| All screens | Auth layer | Session validation middleware for every API call platform-wide |
| 100 | ConnectHub Home | API: feed posts, classifieds, sharing actions |
| 200 | LaunchPad Home | API: sandbox/SideHustle listing, summary counts |
| 220 | Individual Sandbox | API: sandbox workspace data, simplified tools |
| 240 | Individual SideHustle | API: SideHustle data, BMC tool, team panel, positions |

