# DEVELOPMENT_WORKFLOW.md — atoms-maintenance

## Overview

This document defines the phased development workflow for the ATOMS-Maintenance backend. Development is incremental — each phase builds on the previous one, and no phase should be started until the previous phase is validated.

---

## Development Phases

### Phase 0: Documentation & Planning ✅ (Current)
- [x] Analyze `frontend_atoms-maintenance` types and mock data
- [x] Analyze `atoms-rostering/backend_atoms` as reference
- [x] Create documentation: AGENTS.md, BACKEND_CONTEXT.md, DATABASE_PLAN.md, API_PLAN.md, AUTH_PLAN.md, ROSTERING_REFERENCE.md, PRODUCT_BACKEND.md, INSTRUCTION.md
- [x] Define mock auth strategy
- [x] Create .kiro skill files

### Phase 1: Laravel Scaffold ✅ (Complete)
- [x] Initialize Laravel project (`composer create-project laravel/laravel .`)
- [x] Configure PostgreSQL connection in `.env` / `.env.example`
- [x] Set up CORS for React frontend (`localhost:5173`)
- [x] Create base API route file with `/api/v1/` prefix
- [x] Create standard JSON response trait/helper
- [x] Configure `.gitignore` (exclude `.env`, `vendor/`, `storage/`, `node_modules/`)
- [x] Verify: `php artisan serve` starts without errors

### Phase 2: Mock Authentication ✅ (Complete)
- [x] Create `local_users` migration
- [x] Create `LocalUser` model with roles
- [x] Create `MockUserSeeder` with 7 mock users (matching frontend mockData.ts)
- [x] Create `MockAuthMiddleware` (reads `DEV_MOCK_AUTH` env var)
- [x] Create `CheckRole` middleware
- [x] Create auth routes: `POST /login` (mock), `GET /me`, `POST /logout`
- [x] Create `AuthController` with mock logic
- [x] Verify: Frontend can authenticate with mock token and access protected routes

### Phase 3: Work Orders ✅ (Complete)
- [x] Create `work_orders` migration + model
- [x] Create `work_order_personnel` migration + model
- [x] Create `work_order_outputs` migration + model
- [x] Create `WorkOrderService` with state machine logic
- [x] Create `WorkOrderController` (CRUD + status transitions skeleton)
- [x] Create Form Request classes for validation
- [x] Create `WorkOrderPolicy` for authorization
- [x] Seed sample work orders
- [x] Verify: Frontend Work Order page works against real API (Note: Frontend expects `completed`, `on_hold`, `ongoing` statuses)

### Phase 4: CNSD Inspections 🏗️ (Prep)
- [x] Create `cnsd_categories` migration + model + seeder (skeleton)
- [x] Create `cnsd_meter_readings` migration + model (skeleton)
- [x] Create `cnsd_sections` + `cnsd_section_rows` migrations + models (skeleton)
- [x] Create `CnsdController` and `CnsdService` (skeleton)
- [ ] Create Form Request classes
- [ ] Verify: Frontend CNSD EQ-1 form submits to real API

### Phase 5: TFP Performance Checks
- [ ] Create `tfp_categories` migration + model + seeder
- [ ] Create `tfp_performance_checks` migration + model
- [ ] Create `tfp_measurements` + `tfp_facilities` migrations + models
- [ ] Create `TfpController` and `TfpService`
- [ ] Create Form Request classes
- [ ] Verify: Frontend TFP AOB form submits to real API

### Phase 6: Ground Check & Grounding
- [ ] Create `ground_check_readings` migration + model
- [ ] Create `grounding_reports` + child tables migrations + models
- [ ] Create controllers and services
- [ ] Verify: Frontend Ground Check and Grounding pages work

### Phase 7: Reporting & Logbook
- [ ] Create `maintenance_reports` migration + model
- [ ] Create `logbooks` migration + model
- [ ] Implement approval workflow for reports
- [ ] Implement file upload for logbooks
- [ ] Verify: Frontend Reporting and Logbook pages work

### Phase 8: Dashboard
- [ ] Create `trouble_equipment` + `dashboard_checklist` tables
- [ ] Create `DashboardController` with aggregation queries
- [ ] Verify: Frontend Dashboard shows real aggregated data

### Phase 9: SSO Integration
- [x] Add `rostering` read-only DB connection to `config/database.php` (2026-05-15)
- [x] Add `ROSTERING_DB_*` env vars to `.env` and `.env.example` (2026-05-15)
- [x] Create `RosteringIntegrationService` — wraps all rostering DB queries (2026-05-15)
- [x] Add `GET /api/v1/personnel/shift-today` endpoint — returns real shift context from rostering (2026-05-15)
- [x] Update `WorkOrder::isShiftEnded()` to use `RosteringIntegrationService::isShiftEnded()` (2026-05-15)
- [x] Wire Work Order creation to auto-resolve MT/supervisor from rostering when roster is published (2026-05-15)
- [x] Add `ShiftContextResponse` TypeScript interface to frontend types (2026-05-15)
- [x] Add `workOrderService.getShiftContext()` method to frontend service layer (2026-05-15)
- [x] Update `WorkOrderFormModal` to load real shift context from rostering API with mock fallback (2026-05-15)
- [ ] Implement user sync from rostering to `local_users` (future)
- [ ] Remove mock auth dependency for production (future)
- [ ] Verify: Login via rostering SSO works end-to-end (future)

### Phase 10: Polish & Hardening
- [ ] Write integration tests for critical endpoints
- [ ] Implement audit logging
- [ ] Add rate limiting
- [ ] Security review (CORS, headers, input sanitization)
- [ ] Performance optimization (query indexes, eager loading)
- [ ] Production deployment preparation

---

## Development Environment Setup

### Prerequisites
- PHP 8.x with extensions: `pdo_pgsql`, `openssl`, `mbstring`, `tokenizer`
- Composer 2.x
- PostgreSQL 15+
- Node.js 18+ (for frontend)

### Backend Setup
```bash
# Clone and enter directory
cd backend_atoms-maintenance

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed mock users
php artisan db:seed --class=MockUserSeeder

# Start dev server
php artisan serve
# Backend runs on http://localhost:8000
```

### Frontend Setup (Parallel)
```bash
cd frontend_atoms-maintenance

# Install dependencies
npm install

# Set environment variables
# .env should have:
# VITE_API_URL=http://localhost:8000/api
# VITE_DEV_MOCK_AUTH=true

# Start dev server
npm run dev
# Frontend runs on http://localhost:5173
```

---

## Git Workflow

### Branch Strategy
- `main` — stable, production-ready
- Feature branches for significant work (optional for solo dev)

### Commit Convention
Follow Conventional Commits (see `git-auto-ship` skill):
```
feat(work-orders): implement CRUD endpoints
fix(auth): resolve mock token parsing
docs(api): update API_PLAN with new endpoints
```

### Pre-Commit Checklist
- [ ] `php artisan test` passes
- [ ] No secrets in codebase
- [ ] `.env.example` updated if new env vars
- [ ] API_PLAN.md / DATABASE_PLAN.md updated if schema/routes changed
- [ ] Frontend mock data still compatible with API response shapes

---

## Testing Strategy

### Unit Tests
- Model relationship tests
- Service method tests (state machine transitions, validation logic)

### Integration Tests
- API endpoint tests with mock auth
- Full request → response cycle

### Manual Verification & Integration Testing
- Frontend ↔ Backend integration test for each module
- Role-based access control verification
- **CORS Troubleshooting:** Ensure `config/cors.php` includes the frontend URL (`localhost:5173`) in `allowed_origins` and sets `supports_credentials = true`.
- **Mock Auth Integration:** When `DEV_MOCK_AUTH=true`, verify frontend login returns a valid mock token and `/auth/me` retrieves the correct mocked user profile.

---

## Validation Checklist (Per Phase)

- [ ] Code passes all linting rules
- [ ] No database credentials or secrets hardcoded
- [ ] API endpoints have Form Request validation
- [ ] Migrations are reversible (`down()` method works)
- [ ] Documentation updated to reflect changes
- [ ] Frontend can consume the new API endpoints

---

## Tech Stack Confirmed

- **Backend:** PHP 8.x (Laravel Framework)
- **Database:** PostgreSQL
- **Auth (Dev):** Mock middleware
- **Auth (Prod):** SSO via atoms-rostering
- **API Style:** RESTful JSON API with `/api/v1/` prefix

---

## Do Not Copy Directly
- Do not copy rostering's monolithic structure
- Keep it API-only — no Blade views, no server-rendered HTML
- Do not bundle frontend and backend in one repository
