# SKILL: Backend Architecture — atoms-maintenance

Always follow these rules when working on the ATOMS-Maintenance backend.

---

## Mandatory First Steps

1. **Read AGENTS.md and BACKEND_CONTEXT.md** before starting any backend task.
2. **Read DATABASE_PLAN.md and API_PLAN.md** for the relevant module.
3. **Check frontend types** at `frontend_atoms-maintenance/src/types/index.ts` to understand expected data shapes.

---

## Architecture Rules

1. **Two-system model.** atoms-rostering is the source of truth for login, accounts, and employee/shift data. atoms-maintenance handles maintenance-specific features only.
2. **Never modify atoms-rostering.** Do not write, edit, or commit any files inside `atoms-rostering/`.
3. **Reference only.** Use `atoms-rostering/backend_atoms` as a reference for Laravel patterns (Sanctum, User/Employee models, ShiftAssignment notes mapping). Do not copy code directly.
4. **Standalone API.** Build `backend_atoms-maintenance` as a separate Laravel API. No HTML views, no monolith.
5. **Tech stack:** PHP 8.x (Laravel) + PostgreSQL. No Node.js backend.

---

## Implementation Rules

1. **Incremental changes.** One module per task. Follow `DEVELOPMENT_WORKFLOW.md` phases.
2. **Controller → Service pattern.** Thin controllers, business logic in `app/Services/`.
3. **Form Request validation.** All endpoints use dedicated Form Request classes.
4. **Standard JSON responses.** `{ success, message, data, errors }` wrapper on all endpoints.
5. **API versioning.** All routes under `/api/v1/`.
6. **Soft deletes.** Use SoftDeletes trait on all models.
7. **Audit fields.** Include `created_by`, `created_at`, `updated_at` on all tables.

---

## Security Rules

1. **Never hardcode secrets.** Use `.env` variables for DB credentials, JWT secrets, API keys.
2. **Only commit `.env.example`.** Never commit `.env` or any file containing real credentials.
3. **RBAC middleware.** Every protected route must have role middleware.
4. **Validate all inputs.** No endpoint should accept unvalidated data.

---

## Documentation Rules

1. **Update `API_PLAN.md`** whenever API routes change.
2. **Update `DATABASE_PLAN.md`** whenever schema changes.
3. **Update `AUTH_PLAN.md`** whenever auth strategy changes.
4. **Never create endpoints without documenting them.**

---

## Commit Rules

1. **Validate before commit.** `php artisan test` must pass.
2. **Follow Conventional Commits** format (see `git-auto-ship` skill).
3. **Safe push.** Follow the git-auto-ship workflow. Never force push.
4. **No unrelated changes.** Stage only relevant files.

---

## Mock Auth (Dev)

1. **`DEV_MOCK_AUTH=true`** enables mock auth middleware.
2. Mock middleware accepts `Bearer mock-token-{user_id}` and resolves user from `local_users`.
3. All downstream RBAC works normally against the mock user.
4. Mock users match `frontend_atoms-maintenance/src/data/mockData.ts`.
