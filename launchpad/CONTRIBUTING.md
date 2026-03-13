# Contributing to Hatchloom Backend

## Team Structure

### Riipen Leads
- **Role A** — Architecture & System Design → Supervises Team Papa
- **Role B (Karl)** — Database, Security & Demo Tooling → Supervises Team Quebec
- **Role C** — Golden Path Demo → Supervises Team Romeo

### York U Teams
- **Team Papa** (Salam, Alice, Efua, Mahdis) → Explore pillar backend
- **Team Quebec** (Jahin, Jusafa, Faruk) → Auth + LaunchPad + ConnectHub backend
- **Team Romeo** (Anthony, Andrew, Jefferson, Daniel) → Dashboards & reporting backend

## Discord Channels

| Channel | Purpose | Members |
|---------|---------|---------|
| `#general` | All-team announcements and coordination | Everyone |
| `#architecture-decisions` | Role A posts decisions, all discuss | Everyone |
| `#team-papa` | Papa coordination | Papa + Role A |
| `#team-romeo` | Romeo coordination | Romeo + Role C |
| `#team-quebec` | Quebec coordination | Quebec + Role B (Karl) |
| `#riipen-leads` | Riipen IC private coordination | Role A, B, C only |
| `#bugs-and-blockers` | Post blockers for rapid response | Everyone |

## Branch Naming

```
feature/<team>/<short-description>
fix/<short-description>
```

Examples:
- `feature/papa/course-api`
- `feature/quebec/auth-login`
- `feature/romeo/student-dashboard`
- `fix/nav-link-filenames`

## Commit Messages

Keep them clear and concise:
```
[team] short description

[papa] add course CRUD endpoints
[quebec] implement session token validation
[romeo] wire student home dashboard API
[roleb] add seed script for demo data
```

## Pull Request Process

1. Create a feature branch off `main`
2. Make your changes
3. Test locally
4. Open a PR to `main` with:
   - What you built
   - How to test it
   - Any dependencies on other teams
5. Tag your team lead for review
6. Address feedback, then merge

## Code Review Assignments

| Your Team | Your Reviewer |
|-----------|--------------|
| Team Papa | Role A |
| Team Quebec | Role B (Karl) |
| Team Romeo | Role C |

## File Ownership

Stay in your lane. If you need to change something outside your directory, coordinate with that team's lead first.

| You Own | You Don't Touch Without Asking |
|---------|-------------------------------|
| Your `/services/<x>/` directory | Other teams' service directories |
| Shared `/docs/` (your own docs) | `/database/` (Role B owns schema) |
| | `/gateway/` (Role A owns gateway) |

## Getting Help

1. Check the docs in `/docs`
2. Ask in your team's Discord channel
3. Ask in `#bugs-and-blockers`
4. Message your team lead directly
5. If truly blocked, message Eva — don't wait for Friday
