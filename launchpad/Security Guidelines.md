# Hatchloom Backend — Security Guidelines

**Role B (Karl) | February 2026 | Confidential**

This platform serves minors (ages 12–17). Security is not optional. These guidelines apply to all teams. If you're unsure about something, ask in `#bugs-and-blockers` or message Karl directly.

---

## 1. Parameterized Queries — MANDATORY

**NEVER** build SQL queries with string concatenation or f-strings. This is the #1 cause of SQL injection.

```python
# ❌ NEVER DO THIS — SQL injection vulnerability
query = f"SELECT * FROM users WHERE id = {user_id}"
query = "SELECT * FROM users WHERE id = " + user_id

# ✅ ALWAYS DO THIS — parameterized query
cursor.execute("SELECT * FROM users WHERE id = %s", (user_id,))
```

If using an ORM (Prisma, SQLAlchemy), use the ORM's built-in query methods — don't write raw SQL unless absolutely necessary. If you must write raw SQL, parameterize it.

---

## 2. Authentication & Sessions

Supabase Auth handles password hashing, session tokens, and login. You do NOT build this yourself. Your responsibilities:

- Validate the Supabase session token on **every** API request
- Use the Supabase client library to verify tokens — don't roll your own JWT validation
- Never expose Supabase `service_role` key to the client — it bypasses Row Level Security
- The `anon` key is safe for client-side use. The `service_role` key is server-side only.
- Session timeout: **30 minutes idle** (configure in Supabase → Auth → Settings)
- Never return raw auth tokens or user metadata in your API responses

---

## 3. School Data Isolation — CRITICAL

Each school's data is isolated. A student in School A must **never** see School B's data.

- Every query that touches student data **must** include `school_id` in the WHERE clause
- **Never trust the client** for `school_id` — derive it from the authenticated user's session
- Test this: log in as a student in School A, try to access School B data via the API. It must fail.

```python
# ❌ BAD — trusts client-provided school_id
school_id = request.body.get("school_id")

# ✅ GOOD — derives school_id from authenticated user
school_id = current_user.school_id
```

---

## 4. Input Validation

Validate **all** user input on the server side. Client-side validation is a UX feature, not a security feature.

- **Strings:** max length, no script tags, sanitize HTML
- **Integers:** check type, check range
- **Enums:** validate against allowed values (don't trust the client to send 'student' — verify it's a valid role)
- **JSON fields** (content_json, response_json): validate structure before storing
- **File uploads:** validate file type, enforce size limits, scan with Cloudflare Images AI

```python
# ❌ BAD — stores whatever the client sends
data = request.json
db.insert("submissions", data)

# ✅ GOOD — validate and extract only expected fields
problem_statement = request.json.get("problem_statement", "")
if len(problem_statement) > 5000:
    return error(400, "Problem statement too long")
```

---

## 5. API Authorization

Every endpoint must check:

1. **Is the user authenticated?** (valid session token)
2. **Does their role allow this action?** (role-based access control)
3. **Do they own this resource?** (a student can only see their own submissions)

```python
# Example middleware pattern
def require_role(*allowed_roles):
    def decorator(func):
        def wrapper(request):
            user = get_authenticated_user(request)
            if not user:
                return error(401, "Not authenticated")
            if user.role not in allowed_roles:
                return error(403, "Forbidden")
            return func(request, user)
        return wrapper
    return decorator

# Usage
@require_role('student', 'teacher')
def get_submissions(request, user):
    # student sees own submissions, teacher sees their school's
    ...
```

---

## 6. Sensitive Data in API Responses

**Never return more data than the client needs.**

```python
# ❌ BAD — leaks password hash, internal IDs
return jsonify(user.__dict__)

# ✅ GOOD — explicit response shape
return jsonify({
    "id": user.id,
    "username": user.username,
    "display_name": user.display_name,
    "role": user.role
})
```

Fields that must **never** appear in API responses:
- `password_hash`
- Other users' `email` (unless explicitly required)
- Internal `school_id` of other schools
- `session.token` of other users
- Audit log entries (admin-only)

---

## 7. CORS

Only allow requests from our frontend origin. Do not use wildcard (`*`) in production.

```python
# ❌ BAD
CORS(app, origins="*")

# ✅ GOOD
CORS(app, origins=["https://app.hatchloom.com", "http://localhost:3000"])
```

---

## 8. Environment Variables & Secrets

- **NEVER** commit secrets to Git (API keys, DB passwords, tokens)
- Use `.env` files locally (already in `.gitignore`)
- Use Doppler or equivalent in production
- If you accidentally commit a secret, notify Karl immediately — the key must be rotated

---

## 9. Error Handling

Don't leak internal details in error responses.

```python
# ❌ BAD — leaks stack trace and DB details
return error(500, str(exception))

# ✅ GOOD — generic message, log details server-side
logger.error(f"DB error: {exception}")
return error(500, "Internal server error")
```

---

## 10. Compliance Reminders

We are building for **minors (ages 12–17)**. This means:

- **COPPA** (under-13): requires verifiable parental consent before data collection
- **FERPA**: student education records are protected, schools control access
- **PIPEDA** (Canada): consent required, breach notification within 72 hours
- **Audit logging**: every data access must be logged (7-year retention)

When in doubt, ask: *"Would I be comfortable explaining this to a parent?"*

---

## Quick Reference

| Do | Don't |
|----|-------|
| Parameterized queries | String concatenation in SQL |
| Let Supabase Auth handle passwords | Roll your own auth |
| Validate all input server-side | Trust client input |
| Check school_id from session | Accept school_id from client |
| Return only needed fields | Return full database rows |
| Log errors server-side | Expose stack traces to client |
| Use .env for secrets | Commit secrets to Git |
