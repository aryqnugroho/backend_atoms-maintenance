# AGENTS.md — backend_atoms-maintenance

> Agent-agnostic guide for AI coding assistants working on the ATOMS-Maintenance backend.
> **Read this file and BACKEND_CONTEXT.md before every task.**

---

## Project Overview

- **What:** Backend API for ATOMS-Maintenance, an airport equipment maintenance and operations management system for AirNav Indonesia Surabaya.
- **Purpose:** Serve the frontend web app (`frontend_atoms-maintenance`) by managing data persistence, authorization, and business logic for **maintenance-specific features only**.
- **Tech Stack:** PHP 8.x (Laravel Framework) + PostgreSQL.
- **Status:** Phase 3 Scaffolded (Work Orders). Phase 4 Prepared (CNSD). MockAuth active, PostgreSQL configured. **Frontend and Backend successfully integrated and verified.** Ready for Work Orders business logic.

---

## Architecture: Two-System Model

```
┌──────────────────────────┐     ┌──────────────────────────────┐
│  atoms-rostering         │     │  atoms-maintenance           │
│  (Source of Truth)       │     │  (This Project)              │
│                          │     │                              │
│  • User accounts/login   │◄────│  • Consumes auth via SSO     │
│  • Employee master data  │     │  • Work Orders               │
│  • Shift assignments     │     │  • CNSD inspections (EQ-1)   │
│  • Roster schedules      │     │  • TFP perf checks (AOB)     │
│  • Leave/shift requests  │     │  • Ground Check              │
│                          │     │  • Grounding inspections     │
│  backend_atoms (Laravel) │     │  • Reporting & Logbook       │
│  frontend_atoms (React)  │     │  • Dashboard                 │
└──────────────────────────┘     └──────────────────────────────┘
```

### Key Principle
- **atoms-rostering** owns login, accounts, employee records, and shift/roster data.
- **atoms-maintenance** handles **maintenance-specific features only** and reads user/shift data from rostering (via API or shared DB, TBD).
- In development, a **mock login** strategy allows the frontend to work without real SSO.

---

## Strict Rules

1. **Read First:** Always read `AGENTS.md` and `BACKEND_CONTEXT.md` before starting tasks.
2. **Check Frontend Needs:** Before implementing any backend logic, review `frontend_atoms-maintenance/src/types/index.ts` and `src/data/mockData.ts` to understand expected data shapes.
3. **Reference Only:** The `atoms-rostering/backend_atoms` repository is a **reference only** for AirNav data patterns. See `ROSTERING_REFERENCE.md`.
4. **Never Modify atoms-rostering:** Do not write, edit, or commit any files inside `atoms-rostering/`.
5. **Never Hardcode Secrets:** Always use `.env` variables for database connections, JWT secrets, API keys, etc. Only commit `.env.example`.
6. **Incremental Changes:** Make small, focused changes. One feature or module per task.
7. **Document First:** Update `API_PLAN.md` and `DATABASE_PLAN.md` before or alongside implementation.
8. **Validate Before Commit:** Code must pass linting, tests, and build checks. Follow `git-auto-ship` workflow.
9. **Backend is Separate:** The backend must remain completely separate from `frontend_atoms-maintenance`. No monolith.
10. **Validate Inputs:** All API endpoints must validate incoming requests using Form Request classes.

---

## Mock Login Strategy (Dev Environment)

During development, the frontend uses a `DEV_MOCK_AUTH` toggle to bypass real SSO:

| Setting | Behavior |
|---------|----------|
| `DEV_MOCK_AUTH=true` | Backend accepts a mock token, returns hardcoded user data. Frontend skips SSO redirect. |
| `DEV_MOCK_AUTH=false` | Backend validates tokens against atoms-rostering SSO. Production mode. |

### How It Works
1. **Frontend** checks `VITE_DEV_MOCK_AUTH` env var. If `true`, it sends a mock Bearer token (`mock-token-{user_id}`) with API requests.
2. **Backend** middleware checks `DEV_MOCK_AUTH` env var. If `true`, it skips real token validation and resolves a mock user from a seeded user table.
3. All maintenance API calls (Work Orders, CNSD, TFP, etc.) succeed against mock data without requiring a running atoms-rostering instance.
4. Mock users match the roles defined in the frontend: Admin, Manager Teknik, Supervisor CNSD, Supervisor TFP, Teknisi CNSD, Teknisi TFP.

> **See also:** `AUTH_PLAN.md` for full authentication strategy, `INSTRUCTION.md` for setup steps.

---

## Key Directories (Planned)

| Path | Purpose |
|------|---------|
| `app/Models/` | Eloquent models for maintenance entities |
| `app/Http/Controllers/Api/V1/` | Versioned API controllers |
| `app/Http/Middleware/` | Auth, role-check, mock-auth middleware |
| `app/Http/Requests/` | Form Request validation classes |
| `app/Services/` | Business logic services |
| `database/migrations/` | PostgreSQL migration files |
| `database/seeders/` | Seed data for dev/testing |
| `routes/api.php` | API route definitions (prefixed `/api/v1/`) |
| `config/` | Laravel configuration |
| `tests/` | PHPUnit integration/unit tests |

---

## Coding Standards

1. **Controllers:** Thin controllers, delegate logic to Services.
2. **Services:** Business logic in `app/Services/`. One service per domain (WorkOrderService, InspectionService, etc.).
3. **Models:** Use Eloquent with explicit `$fillable`, casts, and relationships.
4. **Validation:** Use Form Request classes for all endpoints.
5. **Responses:** Standard JSON wrapper: `{ success: bool, message: string, data: mixed, errors?: mixed }`.
6. **Naming:** Use snake_case for database columns, camelCase for PHP properties, kebab-case for API routes.
7. **API Versioning:** All routes under `/api/v1/`.

---

## Validation Checklist

Before completing any task:

- [ ] Code passes `php artisan test`
- [ ] No database credentials or secrets hardcoded
- [ ] API endpoints have Form Request validation
- [ ] New endpoints documented in `API_PLAN.md`
- [ ] New tables documented in `DATABASE_PLAN.md`
- [ ] Migration files are reversible (`down()` implemented)
- [ ] `.env.example` updated if new env vars added

---

## For Full Context

| Document | Purpose |
|----------|---------|
| [BACKEND_CONTEXT.md](./BACKEND_CONTEXT.md) | Vision, responsibilities, and boundaries |
| [DATABASE_PLAN.md](./DATABASE_PLAN.md) | Proposed database schema and entities |
| [API_PLAN.md](./API_PLAN.md) | Proposed API endpoints and contracts |
| [AUTH_PLAN.md](./AUTH_PLAN.md) | Authentication and authorization strategy |
| [ROSTERING_REFERENCE.md](./ROSTERING_REFERENCE.md) | What we observed from atoms-rostering |
| [DEVELOPMENT_WORKFLOW.md](./DEVELOPMENT_WORKFLOW.md) | Phased development plan |
| [PRODUCT_BACKEND.md](./PRODUCT_BACKEND.md) | Product purpose and core workflows |
| [INSTRUCTION.md](./INSTRUCTION.md) | Quick-start instructions |
