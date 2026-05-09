# INSTRUCTION.md — atoms-maintenance backend

## Target Tech Stack
- **Framework:** PHP 8.x (Laravel Framework)
- **Database:** PostgreSQL
- **Auth (Dev):** Mock middleware (`DEV_MOCK_AUTH=true`)
- **Auth (Prod):** SSO proxy to atoms-rostering

---

## Core Instructions

### 1. Before Any Task
- Read `AGENTS.md` — project rules, architecture, and strict guidelines.
- Read `BACKEND_CONTEXT.md` — vision, responsibilities, boundaries, and integration points.
- Check `API_PLAN.md` and `DATABASE_PLAN.md` for the relevant module.

### 2. Architecture Rules
- **atoms-rostering is the source of truth** for login, accounts, employee data, and shift/roster schedules.
- **atoms-maintenance handles maintenance-specific features only:** Work Orders, CNSD, TFP, Ground Check, Grounding, Reporting, Logbook, Dashboard.
- Build `backend_atoms-maintenance` as a **standalone Laravel API** that serves the React frontend.
- Frontend and backend are **separate projects** — never merge them.

### 3. Reference Only
- `atoms-rostering/backend_atoms` is a **reference for data patterns** (User model, ShiftAssignment notes mapping, role constants, Sanctum setup).
- **Never modify** any file in `atoms-rostering/`.
- **Never copy** code directly without adapting it for maintenance-specific needs.

### 4. Database Security
- Use `.env` variables for all PostgreSQL connections (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
- **Hardcoding credentials is prohibited.**
- Only commit `.env.example` — never `.env`.
- Local dev password (`1234` or similar) goes in `.env` only.

### 5. Mock Authentication
- Set `DEV_MOCK_AUTH=true` in backend `.env` to enable mock auth middleware.
- Set `VITE_DEV_MOCK_AUTH=true` in frontend `.env` to enable mock login UI.
- Mock users are seeded in `local_users` table, matching `frontend_atoms-maintenance/src/data/mockData.ts`.
- In mock mode, the backend accepts `Authorization: Bearer mock-token-{user_id}` and resolves the user from the seeded table.

### 6. API Design
- All routes prefixed with `/api/v1/`.
- Standard JSON response: `{ success, message, data, errors }`.
- Use Form Request classes for validation.
- Use middleware for role-based access control.

### 7. Development Workflow
- Follow the phased plan in `DEVELOPMENT_WORKFLOW.md`.
- Make incremental changes — one module at a time.
- Validate before committing: `php artisan test`, no hardcoded secrets, documentation updated.
- Follow Conventional Commits (see `git-auto-ship` skill).

### 8. Document Changes
- Update `API_PLAN.md` when adding/changing endpoints.
- Update `DATABASE_PLAN.md` when adding/changing tables.
- Update `AUTH_PLAN.md` when changing auth strategy.

---

## Quick Reference

| I need to... | Read this first |
|-------------|-----------------|
| Understand the project | `AGENTS.md`, `BACKEND_CONTEXT.md` |
| Design a database table | `DATABASE_PLAN.md` |
| Design an API endpoint | `API_PLAN.md` |
| Implement authentication | `AUTH_PLAN.md` |
| Understand rostering data | `ROSTERING_REFERENCE.md` |
| Know what to build next | `DEVELOPMENT_WORKFLOW.md` |
| Understand the product | `PRODUCT_BACKEND.md` |

---

## Environment Variables Reference

### Backend `.env.example`
```env
APP_NAME=ATOMS-Maintenance
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=atoms_maintenance
DB_USERNAME=postgres
DB_PASSWORD=

DEV_MOCK_AUTH=true

SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DOMAIN=localhost
```

### Frontend `.env`
```env
VITE_API_URL=http://localhost:8000/api
VITE_DEV_MOCK_AUTH=true
VITE_REVERB_APP_KEY=atoms-maintenance-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
```
