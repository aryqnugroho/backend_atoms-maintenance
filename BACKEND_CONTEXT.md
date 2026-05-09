# BACKEND_CONTEXT.md — atoms-maintenance

## Vision

The ATOMS-Maintenance backend is a **standalone Laravel API** that powers the maintenance operations management system for AirNav Indonesia Surabaya. It handles maintenance-specific business logic — Work Orders, equipment inspections (CNSD/TFP), ground checks, grounding inspections, reporting, and logbook management.

**It does NOT own user accounts, employee master data, or shift/roster schedules.** Those belong to `atoms-rostering`.

---

## Two-System Architecture

### atoms-rostering (Source of Truth)
- **Owns:** User accounts, login/authentication, employee records, shift definitions, roster schedules, leave requests, shift swaps.
- **Tech:** Laravel 12 + SQLite (dev) / PostgreSQL (prod), Sanctum auth.
- **Status:** Production-ready, actively maintained.

### atoms-maintenance (This Project)
- **Owns:** Work Orders, CNSD inspections (EQ-1), TFP performance checks (AOB Ground), Ground Check, Grounding inspections, Maintenance Reports, Logbooks, Dashboard aggregation.
- **Tech:** PHP 8.x (Laravel) + PostgreSQL.
- **Status:** Phase 3 Scaffolded (Work Orders). Phase 4 Prepared (CNSD). Laravel base installed, `local_users` seeded, MockAuth active. **Successfully integrated and tested with `frontend_atoms-maintenance`**. Frontend UI has been updated to use standardized Work Order statuses (`completed`, `on_hold`, `ongoing`) and features a Print PDF layout. Backend development should align with these UI expectations. Ready to build features.

### Integration Points (Future)
| Data | Source | How Maintenance Accesses It |
|------|--------|-----------------------------|
| User login & tokens | atoms-rostering | SSO / shared Sanctum token validation |
| Employee profiles | atoms-rostering | API call or shared read-only DB view |
| Current shift & personnel | atoms-rostering | API call (`GET /roster/today`) |
| Shift definitions (pagi/siang/malam) | atoms-rostering | Cached reference data |
| Work Orders | atoms-maintenance | Local DB (owned) |
| Inspections (CNSD/TFP) | atoms-maintenance | Local DB (owned) |
| Reports & Logbooks | atoms-maintenance | Local DB (owned) |

---

## Relationship with Frontend

- **Separation of Concerns:** The backend is a standalone REST API consumed by the React/Vite frontend. It does not render HTML views.
- **Frontend Modules Requiring Backend Support:**

| Module | Backend Responsibility |
|--------|----------------------|
| **Dashboard** | Aggregated metrics, shift status (from rostering), pending tasks, trouble equipment |
| **Work Order** | CRUD, lifecycle state machine, personnel assignment, completion tracking |
| **CNSD (EQ-1)** | Equipment readiness forms, section-based checklist with multiple measurement rows |
| **TFP (AOB Ground)** | Performance check forms, panel measurements, facility condition checks |
| **Ground Check** | Navigation & communication equipment meter readings |
| **Grounding** | Visual inspection items, resistance measurements per PUIL 2011/SNI/IEC standards |
| **Reporting** | Monthly reports with approval workflow (draft → pending_manager → final / rejected) |
| **Logbook** | Monthly archival PDF uploads per division |
| **Auth/Personnel** | Mock auth in dev; SSO proxy to rostering in production |

---

## Backend Responsibilities

1. **Data Persistence** — PostgreSQL for all maintenance entities.
2. **Authorization** — Role-based access control (RBAC) with middleware. Roles: Admin, Manager Teknik, Supervisor CNSD, Supervisor TFP, Teknisi CNSD, Teknisi TFP.
3. **Business Logic** — Work order state machine, form validation, inspection completeness checks.
4. **Audit Trails** — Who did what and when (created_by, updated_at, timestamps on every record).
5. **Shift Context** — Read shift/personnel info from rostering to contextualize maintenance actions.

---

## Boundaries

| Backend Handles | Frontend Handles | Rostering Handles |
|----------------|-----------------|-------------------|
| Data integrity & persistence | UI rendering & state management | User accounts & passwords |
| API validation & error responses | Form layout & field display | Shift schedules & roster building |
| RBAC enforcement | Route guards & role-based UI | Employee master data |
| Business rule enforcement | Display formatting & filtering | Leave/shift swap requests |
| Audit logging | Notification display | Notification delivery (email) |

---

## What is Known

### Observed from atoms-rostering/backend_atoms
- **Framework:** Laravel 12.0 with PHP 8.2+
- **Authentication:** Laravel Sanctum (token-based)
- **Models:** User (auth) → Employee (operational) with 1:1 relationship
- **Shifts:** Three shifts — pagi (07:00–13:00), siang (13:00–19:00), malam (19:00–07:00)
- **Roles in Rostering:** Admin, Cns, Support, Manager Teknik, General Manager
- **ShiftAssignment:** Links employee → roster_day with `notes` (P/S/M for working, L/CT/CS/DL/TB/OFF for non-working)
- **ShiftResolverService:** Resolves notes → shift_id dynamically

### Confirmed for atoms-maintenance
- **Framework:** PHP 8.x (Laravel Framework)
- **Database:** PostgreSQL
- **Architecture:** Standalone API, separate from frontend
- **Auth Strategy:** Mock auth for dev, SSO integration with rostering for production

### Needs Verification
- Exact SSO integration method (shared Sanctum tokens vs. OAuth2 proxy vs. shared DB)
- Whether maintenance needs its own `users` table or reads from rostering's DB
- Exact table structures for CNSD EQ-1 sections (some fields are dynamic per equipment category)

### Do Not Copy Directly
- Do not copy `backend_atoms` controllers, routes, or migrations verbatim
- Do not include rostering-specific features (roster building, shift swapping, leave management)
- Do not copy unused services (GeminiService, GoogleSheetsService)

---

## Mock Login for Development

### Purpose
Allow frontend and backend development to proceed independently of atoms-rostering.

### Implementation Plan
1. **Backend `.env`:** Add `DEV_MOCK_AUTH=true` flag.
2. **Mock Auth Middleware:** If `DEV_MOCK_AUTH=true`, bypass Sanctum token validation. Resolve user from a `mock_user_id` header or fixed token pattern (`mock-token-{id}`).
3. **Seeder:** Provide a `MockUserSeeder` that creates the 6 role-based users matching `frontend_atoms-maintenance/src/data/mockData.ts`.
4. **Frontend `.env`:** Add `VITE_DEV_MOCK_AUTH=true`. When enabled, AuthContext injects a mock token and user without calling the real login endpoint.
5. **All Maintenance APIs** work normally — they validate the "authenticated" mock user and proceed with RBAC checks.

### Mock Users (from frontend mockData.ts)
| ID | Name | Role | Division |
|----|------|------|----------|
| 1 | Dudik Fahrudin | Manager Teknik | Management |
| 2 | Moch. Ichsan | Supervisor CNSD | CNSD |
| 3 | Fajar Kusuma W | Supervisor TFP | TFP |
| 4 | Khoirul M.A | Teknisi CNSD | CNSD |
| 5 | Iqbal Mustika | Teknisi TFP | TFP |
| 6 | Argo Pragolo | Teknisi CNSD | CNSD |
| 7 | Admin System | Admin | — |
