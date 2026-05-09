# ROSTERING_REFERENCE.md — atoms-maintenance

## Purpose

This document records what was **observed** from `atoms-rostering/backend_atoms` and `atoms-rostering/frontend_atoms` during workspace analysis. It serves as a reference for designing atoms-maintenance's backend. **Do not modify atoms-rostering files.**

---

## ⚠️ Reference Rules

1. **Read-only reference.** Never write, edit, or commit to `atoms-rostering/`.
2. **Adapt, don't copy.** Use rostering patterns as inspiration, but build maintenance-specific implementations.
3. **Source of truth.** Rostering owns: user accounts, employee master data, shift schedules, roster management, leave/shift requests.
4. **Maintenance does NOT replicate** roster management, shift swapping, leave requests, or employee CRUD.

---

## Observed: Backend Architecture

### Framework & Stack
- **Framework:** Laravel 12.0 (PHP 8.2+)
- **Database:** SQLite in dev (`.env.example`), flexible for production
- **Auth:** Laravel Sanctum (`HasApiTokens` trait)
- **Package Manager:** Composer (PHP), npm (Node/Vite for assets)

### Application Structure
```
backend_atoms/
├── app/
│   ├── Console/          # Artisan commands
│   ├── Helpers/           # Utility helpers
│   ├── Http/
│   │   ├── Controllers/Api/  # API controllers
│   │   ├── Middleware/       # Role-check, auth middleware
│   │   └── Requests/        # Form Request validation
│   ├── Jobs/              # Queue jobs
│   ├── Mail/              # Email templates
│   ├── Models/            # Eloquent models (14 models)
│   ├── Observers/         # Model observers
│   ├── Providers/         # Service providers
│   ├── Services/          # Business logic services
│   └── Traits/            # Shared traits (HasAuditFields)
├── config/                # sanctum.php, auth.php, etc.
├── database/
│   ├── migrations/        # 45 migration files
│   ├── seeders/
│   └── factories/
├── routes/api.php         # ~200 lines of API routes
└── tests/
```

---

## Observed: Data Models

### User Model (`app/Models/User.php`)
```php
// Fillable: name, email, role, grade, password, is_active, last_login
// Casts: is_active (bool), password (hashed), last_login (datetime)
// Traits: HasFactory, Notifiable, SoftDeletes, HasAuditFields, HasApiTokens
// Roles: Admin, Cns, Support, Manager Teknik, General Manager
// Relations: hasOne(Employee), hasMany(AccountToken, Notification, ActivityLog)
```

### Employee Model (`app/Models/Employee.php`)
```php
// Fillable: user_id, employee_type, group_number, is_active, is_fixed_manager
// Types: admin, cns, support, manager_teknik, general_manager
// Casts: is_active (bool), is_fixed_manager (bool)
// Traits: HasFactory, SoftDeletes, HasAuditFields
// Relations: belongsTo(User), hasMany(ShiftAssignment, ManagerDuty, ShiftRequest)
```

### Shift Model (`app/Models/Shift.php`)
```php
// Fillable: name, start_time, end_time
// Values: pagi (07:00-13:00), siang (13:00-19:00), malam (19:00-07:00)
// Traits: HasFactory, SoftDeletes, HasAuditFields
// Relations: hasMany(ShiftAssignment, ShiftRequest)
```

### ShiftAssignment Model (`app/Models/ShiftAssignment.php`)
```php
// Fillable: roster_day_id, shift_id, employee_id, notes, span_days
// Notes mapping: P=pagi, S=siang, M=malam (working)
// Non-working: L, L1, L2, CT, CS, DL, TB, OFF, LIBUR, CUTI
// Key methods: isDayOff(), isWorkingShift(), resolveShiftIdFromNotes()
// Relations: belongsTo(RosterDay, Shift, Employee)
```

### Other Models
- **RosterPeriod** — Monthly roster container with publish/unpublish workflow
- **RosterDay** — Individual days within a roster period
- **ManagerDuty** — Manager assignments for roster periods
- **ShiftRequest** — Shift swap requests with multi-step approval
- **LeaveRequest** — Leave requests with approval workflow
- **Notification** — System notifications with categories and scheduling
- **ActivityLog** — Audit trail for user actions
- **AccountToken** — Password setup/reset tokens
- **RosterTask** — Tasks assigned within roster context

---

## Observed: Key Services

### ShiftResolverService
```php
// Central service for resolving notes → shift_id
// Maps: P→pagi, S→siang, M→malam, PAGI→pagi, SIANG→siang, MALAM→malam
// Non-working detection: L*, CT, CS, DL, TB, OFF, LIBUR, CUTI
// Returns null for non-working shifts
// Used by ShiftAssignment model and controllers
```

### NotificationService
- Sends notifications to users
- Supports scheduled notifications

### GoogleSheetsService
- Imports roster data from Google Sheets (not relevant for maintenance)

### GeminiService
- AI integration (not relevant for maintenance)

---

## Observed: API Routes (Summary)

### Auth (`/auth`)
- `POST /login` — Login with email/password
- `POST /verify-token` — Verify a setup token
- `POST /set-password` — First-time password setup
- `GET /me` — Get authenticated user (Sanctum protected)
- `POST /logout` — Revoke token
- `POST /change-password` — Change password

### Admin (`/admin`)
- CRUD for users (Admin role only)
- Generate activation tokens
- Send activation codes

### Rosters (`/rosters`, `/roster`)
- CRUD for roster periods
- Day-level shift assignments
- Quick update, batch update
- Manager assignment/removal
- Group formation (CNS/Support)
- Publish/unpublish workflow

### Shift Requests (`/shift-requests`)
- Create, approve (by target & manager), reject, cancel
- Available partners lookup
- Manager assignment for shifts

### Leave Requests (`/leave-requests`)
- Create, view, approve/reject (manager only)
- Document generation

### Notifications (`/notifications`)
- CRUD, mark read, star, scheduled notifications
- Debug/test endpoints (dev only)

### Employee Schedule (`/employee`)
- Personal schedule view

---

## Observed: Frontend Architecture (frontend_atoms)

### Stack
- React + TypeScript + Vite + Tailwind CSS

### Structure
```
src/
├── components/      # UI components
├── contexts/        # React contexts
├── hooks/           # Custom hooks
├── lib/             # Utilities
├── modules/         # Feature modules
├── pages/           # Page components
├── services/        # API service layer
├── styles/          # Additional styles
├── types/           # TypeScript types
└── utils/           # Helper utilities
```

---

## What Maintenance Should Adopt

| Pattern | From Rostering | For Maintenance |
|---------|---------------|-----------------|
| User/Employee split | `User` (auth) + `Employee` (operational) | Use `local_users` as cached user profiles |
| Sanctum auth | Token-based stateless auth | Same approach for SSO proxy |
| Role middleware | `middleware('role:Admin,Manager Teknik')` | Same pattern with maintenance-specific roles |
| Controller/Service pattern | Controllers delegate to Services | Adopt for Work Orders, Inspections |
| HasAuditFields trait | Tracks `created_by`, `updated_by` | Implement for all maintenance models |
| SoftDeletes | Used on all models | Adopt for all maintenance entities |
| Form Request validation | Validation in dedicated Request classes | Adopt for all API endpoints |
| Shift time definitions | pagi/siang/malam with start/end times | Reference for shift-contextualized operations |

## What Maintenance Should NOT Adopt

| Feature | Reason |
|---------|--------|
| Roster building/management | Not maintenance's responsibility |
| Shift swap/leave requests | Stays in rostering |
| Google Sheets integration | Rostering-specific |
| Gemini AI service | Rostering-specific |
| Complex batch import routes | Not needed for maintenance |
| Email notification system | May be handled differently |
| Scheduled notifications | Evaluate later |
