# ROSTERING_INTEGRATION.md — atoms-maintenance

> Integration guide for connecting atoms-maintenance to atoms-rostering.
> Generated: 2026-05-14 | Last updated: 2026-05-15 — SSO integration implemented.
> ⚠️ atoms-rostering is READ-ONLY from maintenance's perspective. Never write to its DB.

---

## Section 1 — Rostering Service Overview

### Base URL
| Environment | URL |
|-------------|-----|
| Local dev   | `http://localhost:8001` |
| Production  | TBD (same host, different port or subdomain) |

All API endpoints are prefixed with `/api`. Example: `http://localhost:8001/api/auth/login`

### Auth Mechanism
atoms-rostering uses **Laravel Sanctum** token-based authentication.

**Login flow:**
1. `POST /api/auth/login` with `{ email, password }`
2. Response contains `access_token` (plain text Sanctum token)
3. All subsequent requests use: `Authorization: Bearer {access_token}`

**No API versioning** — routes are flat under `/api/` (not `/api/v1/`).

### Token Payload Structure
The login response returns:
```json
{
  "access_token": "1|3ZXDOGsHiHnRgx49b3...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Administrator",
    "email": "admin@airnav.com",
    "role": "Admin",
    "grade": null,
    "is_active": true,
    "last_login": "2026-05-14T...",
    "employee": {
      "id": 1,
      "user_id": 1,
      "employee_type": "Administrator",
      "group_number": null,
      "is_active": true,
      "is_fixed_manager": false
    }
  }
}
```

**Token format:** `{id}|{random_string}` — stored hashed in `personal_access_tokens` table.

**User roles (rostering):**
- `Admin` — system administrator
- `Cns` — CNS technician (maps to `employee_type: CNS`)
- `Support` — TFP/support technician (maps to `employee_type: Support`)
- `Manager Teknik` — maintenance manager (maps to `employee_type: Manager Teknik`)
- `General Manager` — general manager

### Token Expiry
No explicit expiry — Sanctum tokens are long-lived until revoked via logout. The `personal_access_tokens.expires_at` column exists but is not populated on login.

### Seeded Credentials (Local Dev)
| Email | Password | Role |
|-------|----------|------|
| `admin@airnav.com` | `password` | Admin |
| `user1@airnav.com` | `password` | General Manager |
| `user2@airnav.com` | `password` | Manager Teknik (Dudik Fahrudin Sukarno) |
| `user3@airnav.com` | `password` | Manager Teknik (Andi Wibowo) |
| `user7@airnav.com` | `password` | Cns (Aditya Huzairi P — SVP CNS) |
| `user8@airnav.com` | `password` | Cns (Moch. Ichsan — SPV CNS) |
| `user38@airnav.com` | `password` | Support (Fajar Kusuma W — SPV TFP) |

---

## Section 2 — Read-Only DB Connection Setup

atoms-maintenance should connect to `atoms_rostering` as a **second, read-only database connection** for direct SQL queries when the API is not suitable (e.g., bulk lookups, shift-end time resolution).

### Step 1 — Add to atoms-maintenance `.env`

```dotenv
# Rostering DB (READ-ONLY — never write to this)
ROSTERING_DB_HOST=localhost
ROSTERING_DB_PORT=5432
ROSTERING_DB_DATABASE=atoms_rostering
ROSTERING_DB_USERNAME=postgres
ROSTERING_DB_PASSWORD=1234
```

### Step 2 — Add to `config/database.php`

In the `connections` array, add:

```php
'rostering' => [
    'driver'   => 'pgsql',
    'host'     => env('ROSTERING_DB_HOST', '127.0.0.1'),
    'port'     => env('ROSTERING_DB_PORT', '5432'),
    'database' => env('ROSTERING_DB_DATABASE', 'atoms_rostering'),
    'username' => env('ROSTERING_DB_USERNAME', 'postgres'),
    'password' => env('ROSTERING_DB_PASSWORD', ''),
    'charset'  => 'utf8',
    'prefix'   => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode'  => 'prefer',
    // Read-only: never call DB::connection('rostering')->statement() for writes
],
```

### Step 3 — Using the Connection

```php
use Illuminate\Support\Facades\DB;

// Get all active employees from rostering
$employees = DB::connection('rostering')
    ->table('employees')
    ->join('users', 'users.id', '=', 'employees.user_id')
    ->where('employees.is_active', true)
    ->whereNull('employees.deleted_at')
    ->whereNull('users.deleted_at')
    ->select('users.id as user_id', 'users.name', 'users.role', 'employees.employee_type', 'employees.group_number')
    ->get();

// Get shift assignments for today
$today = now()->toDateString();
$assignments = DB::connection('rostering')
    ->table('shift_assignments as sa')
    ->join('roster_days as rd', 'rd.id', '=', 'sa.roster_day_id')
    ->join('roster_periods as rp', 'rp.id', '=', 'rd.roster_period_id')
    ->join('employees as e', 'e.id', '=', 'sa.employee_id')
    ->join('users as u', 'u.id', '=', 'e.user_id')
    ->join('shifts as s', 's.id', '=', 'sa.shift_id')
    ->where('rd.work_date', $today)
    ->where('rp.status', 'published')
    ->whereNull('sa.deleted_at')
    ->select('u.id as user_id', 'u.name', 'e.employee_type', 's.name as shift_name', 's.start_time', 's.end_time', 'sa.notes')
    ->get();
```

**Important:** Always add `->whereNull('deleted_at')` — all rostering tables use soft deletes.

---

## Section 3 — Relevant API Endpoints

All endpoints require `Authorization: Bearer {token}` unless marked Public.
Base URL: `http://localhost:8001`

### Authentication

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/auth/login` | Public | Login, returns Sanctum token + user+employee data |
| GET | `/api/auth/me` | Bearer | Get current user with employee relationship |
| POST | `/api/auth/logout` | Bearer | Revoke current token |

**Login request:**
```json
POST /api/auth/login
{ "email": "user2@airnav.com", "password": "password" }
```

**Login response (key fields):**
```json
{
  "access_token": "2|abc123...",
  "token_type": "Bearer",
  "user": {
    "id": 2, "name": "Dudik Fahrudin Sukarno",
    "role": "Manager Teknik",
    "employee": { "id": 2, "employee_type": "Manager Teknik", "group_number": 1 }
  }
}
```

---

### Users & Employees

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/users` | Bearer | List all users with employee data (paginated, filterable) |

**Query params:** `search`, `role`, `employee_type`, `is_active`, `per_page`

**Response structure:**
```json
{
  "data": [{
    "id": 2, "name": "Dudik Fahrudin Sukarno",
    "email": "user2@airnav.com", "role": "Manager Teknik",
    "employee": { "id": 2, "employee_type": "Manager Teknik", "group_number": 1 }
  }],
  "total": 55
}
```

**When to call:** When atoms-maintenance needs to populate personnel dropdowns or sync `local_users` cache.

---

### Roster — Key Endpoints for Maintenance

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/roster/today` | Bearer | Get shift assignments for a given date+shift |
| GET | `/api/rosters` | Bearer | List roster periods (month/year/status) |
| GET | `/api/rosters/{id}` | Bearer | Full roster with all days, assignments, employees, shifts |
| GET | `/api/rosters/{id}/days/{day_id}` | Bearer | Single day detail with shift_assignments + manager_duties |

#### `GET /api/roster/today` — Primary Integration Endpoint

This is the most important endpoint for atoms-maintenance. It returns who is working on a given date and shift.

**Query parameters:**
- `date` (optional) — `YYYY-MM-DD`, defaults to today
- `shift` (optional) — `07-13`, `13-19`, `19-07`, `pagi`, `siang`, or `malam`; defaults to current time-based shift

**Example request:**
```
GET /api/roster/today?date=2026-05-14&shift=07-13
Authorization: Bearer {token}
```

**Response:**
```json
{
  "date": "2026-05-14",
  "shift_key": "07-13",
  "shift_start": "07:00",
  "shift_end": "13:00",
  "shift_name": "Shift Pagi",
  "shift_periods": [
    { "key": "07-13", "name": "Shift Pagi", "start": "07:00", "end": "13:00" },
    { "key": "13-19", "name": "Shift Siang", "start": "13:00", "end": "19:00" },
    { "key": "19-07", "name": "Shift Malam", "start": "19:00", "end": "07:00" }
  ],
  "assignments": [
    {
      "shift_assignment_id": 1,
      "user_id": 8,
      "user_name": "Moch. Ichsan",
      "role": "Cns",
      "shift_key": "07-13",
      "tasks": [...]
    }
  ],
  "summary": { "total_assignments": 6, "total_tasks": 6, "high_priority": 0 }
}
```

**Note:** `assignments[].user_id` maps to `users.id` in atoms-rostering, which maps to `local_users.rostering_user_id` in atoms-maintenance.

---

### Employee Personal Schedule

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/employee/my-schedule` | Bearer | Current user's schedule for current month |
| GET | `/api/employee/roster/{rosterId}/my-schedule` | Bearer | Schedule for specific roster period |

---

## Section 4 — Data that atoms-maintenance Needs

### How to Get Current Active Shift for a User

**Via API:**
```php
// In atoms-maintenance service
$response = Http::withToken($rosteringToken)
    ->get('http://localhost:8001/api/roster/today', [
        'date' => now()->toDateString(),
    ]);

$data = $response->json();
$shiftKey = $data['shift_key'];       // e.g. "07-13"
$shiftStart = $data['shift_start'];   // e.g. "07:00"
$shiftEnd = $data['shift_end'];       // e.g. "13:00"
```

**Via direct DB (read-only connection):**
```php
$today = now()->toDateString();
$currentHour = (int) now()->format('H');

// Determine shift key from current time
if ($currentHour >= 7 && $currentHour < 13) {
    $shiftName = 'pagi';
} elseif ($currentHour >= 13 && $currentHour < 19) {
    $shiftName = 'siang';
} else {
    $shiftName = 'malam';
}

$shift = DB::connection('rostering')
    ->table('shifts')
    ->where('name', $shiftName)
    ->whereNull('deleted_at')
    ->first();
// $shift->start_time, $shift->end_time
```

---

### How to Get All Technicians in a Shift

```php
// Via direct DB — get all CNS + Support employees working a specific shift on a date
$date = '2026-05-14';
$shiftName = 'pagi'; // or 'siang', 'malam'

$technicians = DB::connection('rostering')
    ->table('shift_assignments as sa')
    ->join('roster_days as rd', 'rd.id', '=', 'sa.roster_day_id')
    ->join('roster_periods as rp', 'rp.id', '=', 'rd.roster_period_id')
    ->join('employees as e', 'e.id', '=', 'sa.employee_id')
    ->join('users as u', 'u.id', '=', 'e.user_id')
    ->join('shifts as s', 's.id', '=', 'sa.shift_id')
    ->where('rd.work_date', $date)
    ->where('rp.status', 'published')
    ->where('s.name', $shiftName)
    ->whereIn('e.employee_type', ['CNS', 'Support'])
    ->whereNull('sa.deleted_at')
    ->whereNull('rd.deleted_at')
    ->select('u.id as user_id', 'u.name', 'e.employee_type', 'e.group_number')
    ->get();
```

---

### How to Get MT (Manager Teknik) for a Shift

```php
// Manager Teknik assigned to a specific shift on a date
$mt = DB::connection('rostering')
    ->table('manager_duties as md')
    ->join('roster_days as rd', 'rd.id', '=', 'md.roster_day_id')
    ->join('roster_periods as rp', 'rp.id', '=', 'rd.roster_period_id')
    ->join('employees as e', 'e.id', '=', 'md.employee_id')
    ->join('users as u', 'u.id', '=', 'e.user_id')
    ->join('shifts as s', 's.id', '=', 'md.shift_id')
    ->where('rd.work_date', $date)
    ->where('rp.status', 'published')
    ->where('md.duty_type', 'Manager Teknik')
    ->where('s.name', $shiftName)
    ->whereNull('md.deleted_at')
    ->whereNull('rd.deleted_at')
    ->select('u.id as user_id', 'u.name', 'e.employee_type')
    ->first();
// Returns null if no MT assigned to this shift
```

---

### How to Get Supervisor for a Shift (nullable)

In atoms-rostering, "Supervisor" maps to CNS employees with `grade >= 13` (SVP CNS) or `grade = 14` (SPV CNS). There is no separate `supervisor` role — supervisors are CNS employees with higher grade.

```php
// Get supervisor-level CNS for a shift (grade 13 or 14 = SPV/SVP CNS)
$supervisor = DB::connection('rostering')
    ->table('shift_assignments as sa')
    ->join('roster_days as rd', 'rd.id', '=', 'sa.roster_day_id')
    ->join('roster_periods as rp', 'rp.id', '=', 'rd.roster_period_id')
    ->join('employees as e', 'e.id', '=', 'sa.employee_id')
    ->join('users as u', 'u.id', '=', 'e.user_id')
    ->join('shifts as s', 's.id', '=', 'sa.shift_id')
    ->where('rd.work_date', $date)
    ->where('rp.status', 'published')
    ->where('s.name', $shiftName)
    ->where('e.employee_type', 'CNS')
    ->where('u.grade', '>=', 13)  // grade 13 = SVP CNS, grade 14 = SPV CNS
    ->whereNull('sa.deleted_at')
    ->whereNull('rd.deleted_at')
    ->select('u.id as user_id', 'u.name', 'u.grade')
    ->first();
// Returns null if no supervisor-level CNS in this shift (has_supervisor = false)
```

---

### How to Verify if a Shift Has Ended

```php
// Replace the current local fallback in WorkOrderService::isShiftEnded()
// with real shift times from rostering DB

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

public function isShiftEnded(string $shiftName, string $workDate): bool
{
    $shift = DB::connection('rostering')
        ->table('shifts')
        ->where('name', strtolower($shiftName))
        ->whereNull('deleted_at')
        ->first(['name', 'start_time', 'end_time']);

    if (!$shift || !$shift->end_time) {
        // Fallback to hardcoded times if rostering DB unavailable
        $fallback = ['pagi' => '13:00', 'siang' => '19:00', 'malam' => '07:00'];
        $endTimeStr = $fallback[strtolower($shiftName)] ?? '13:00';
    } else {
        $endTimeStr = $shift->end_time; // e.g. "13:00:00"
    }

    $endTime = Carbon::parse($workDate . ' ' . $endTimeStr);

    // Malam shift ends next day
    if (strtolower($shiftName) === 'malam') {
        $endTime->addDay();
    }

    return Carbon::now()->greaterThanOrEqualTo($endTime);
}
```

**Shift end times (confirmed from seeder):**
| Shift | Start | End | Notes |
|-------|-------|-----|-------|
| `pagi` | 07:00 | 13:00 | Same day |
| `siang` | 13:00 | 19:00 | Same day |
| `malam` | 19:00 | 07:00 | Ends next day |

---

## Section 5 — Local Development Setup

### Port Assignments
| Service | Port | URL | Notes |
|---------|------|-----|-------|
| atoms-rostering backend | 8001 | `http://localhost:8001` | Auth source of truth |
| atoms-maintenance backend | 8000 | `http://localhost:8000` | |
| atoms-maintenance frontend | 5173 | `http://localhost:5173` | Vite default — SSO redirect target |
| atoms-rostering frontend | 5174 | `http://localhost:5174` | Run with: `npm run dev -- --port 5174` |

> **Port conflict note:** Both Vite projects default to port 5173. atoms-rostering frontend
> must be started explicitly on port 5174 to avoid conflict when both run simultaneously.
> atoms-maintenance stays on 5173 as it is the SSO redirect target.

### Startup Order
1. **Start atoms-rostering backend first** (it's the auth source of truth):
   ```bash
   cd atoms-rostering/backend_atoms
   php artisan serve --port=8001
   ```

2. **Start atoms-maintenance backend:**
   ```bash
   cd atoms-maintenance/backend_atoms-maintenance
   php artisan serve --port=8000
   ```

3. **Start atoms-rostering frontend on port 5174** (must be explicit to avoid conflict):
   ```bash
   cd atoms-rostering/frontend_atoms
   npm run dev -- --port 5174
   ```

4. **Start atoms-maintenance frontend** (stays on default port 5173):
   ```bash
   cd atoms-maintenance/frontend_atoms-maintenance
   npm run dev
   ```

### Verify Integration is Working

```bash
# 1. Check rostering is up
curl http://localhost:8001/api/activity-logs
# Expected: 200 OK with JSON

# 2. Login to rostering
curl -X POST http://localhost:8001/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@airnav.com","password":"password"}'
# Expected: { "access_token": "1|...", "user": {...} }

# 3. Test roster/today endpoint
curl http://localhost:8001/api/roster/today \
  -H "Authorization: Bearer {token_from_step_2}"
# Expected: { "date": "...", "shift_key": "...", "assignments": [...] }

# 4. Check maintenance is up
curl http://localhost:8000/api/v1/work-orders \
  -H "Authorization: Bearer mock-token-1"
# Expected: 200 OK with work orders list
```

### Common Errors and Fixes

| Error | Cause | Fix |
|-------|-------|-----|
| `Connection refused on port 8001` | atoms-rostering not started | Run `php artisan serve --port=8001` in `atoms-rostering/backend_atoms` |
| `SQLSTATE: database "atoms_rostering" does not exist` | DB not created | Run `psql -U postgres -c "CREATE DATABASE atoms_rostering;"` |
| `Class not found` after composer install | Autoload not generated | Run `composer dump-autoload` |
| `401 Unauthorized` on rostering API | Token expired or wrong | Re-login via `POST /api/auth/login` |
| `Empty assignments array` from `/api/roster/today` | No published roster for today's date | Roster must be published in atoms-rostering for the current month |
| `pdo_firebird` PHP warning | Missing extension (harmless) | Ignore — does not affect PostgreSQL functionality |

---

## Section 6 — Integration Checklist

### atoms-maintenance Backend Tasks

- [x] Add rostering DB credentials to `atoms-maintenance/.env` (see Section 2) ✅ 2026-05-15
- [x] Add `rostering` connection to `config/database.php` (see Section 2) ✅ 2026-05-15
- [x] `RosteringIntegrationService` dibuat — wraps semua rostering DB queries ✅ 2026-05-15
- [x] Endpoint `GET /api/v1/personnel/shift-today` tersedia ✅ 2026-05-15
- [x] `WorkOrder::isShiftEnded()` sekarang menggunakan `RosteringIntegrationService::isShiftEnded()` dengan fallback ✅ 2026-05-15
- [x] `WorkOrderService::createWorkOrder()` auto-resolve MT & supervisor dari rostering jika roster dipublish ✅ 2026-05-15
- [x] Update Work Order creation to pull real shift personnel from `GET /api/roster/today` ✅ 2026-05-15 (via `/personnel/shift-today`)
- [x] Frontend `types/index.ts` — tambah `ShiftContextResponse`, `RosteringShiftPersonnel`, dll ✅ 2026-05-15
- [x] Frontend `workOrderService.ts` — tambah `getShiftContext()` method ✅ 2026-05-15
- [x] Frontend `WorkOrderFormModal` — load real shift context dari rostering API, fallback ke mock ✅ 2026-05-15
- [ ] Replace `local_users` mock data sync with real sync from `GET /api/users` (rostering)
- [ ] Implement `RosteringService` class in atoms-maintenance to wrap rostering API calls
- [ ] Add `ROSTERING_API_URL` and `ROSTERING_API_TOKEN` to atoms-maintenance `.env` for service-to-service calls
- [ ] Test `POST /api/v1/work-orders/{id}/sign` endpoint with real shift personnel (MT, Supervisor, Technician)
- [ ] Verify `has_supervisor` logic uses real supervisor presence from rostering shift data
- [ ] Add fallback handling when rostering API is unavailable (use local_users cache)
- [ ] Test full Work Order lifecycle with real rostering data (create → sign MT → sign supervisor → sign technician → completed)
- [ ] Document SSO token sharing strategy (shared Sanctum token vs. service account token)

### atoms-rostering Tasks (READ-ONLY — no code changes)
- [x] atoms-rostering is running on port 8001 ✅
- [x] Database `atoms_rostering` created and migrated ✅
- [x] All 55 employees seeded ✅
- [x] Shifts seeded (pagi/siang/malam + others) ✅
- [ ] Publish a roster for the current month (manual step via atoms-rostering frontend or API)

---

## Appendix — Database Tables Reference

Tables in `atoms_rostering` that atoms-maintenance reads:

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `users` | Auth accounts + roles | `id`, `name`, `email`, `role`, `grade`, `is_active` |
| `employees` | Employee operational data | `id`, `user_id`, `employee_type`, `group_number`, `is_fixed_manager` |
| `shifts` | Shift definitions | `id`, `name`, `start_time`, `end_time` |
| `roster_periods` | Monthly rosters | `id`, `month`, `year`, `status` (draft/published) |
| `roster_days` | Individual days | `id`, `roster_period_id`, `work_date` |
| `shift_assignments` | Employee ↔ shift ↔ day | `id`, `roster_day_id`, `shift_id`, `employee_id`, `notes` |
| `manager_duties` | Manager ↔ shift ↔ day | `id`, `roster_day_id`, `employee_id`, `duty_type`, `shift_id` |
| `personal_access_tokens` | Sanctum tokens | `tokenable_id` (= user.id), `token`, `expires_at` |

**All tables use soft deletes** — always filter `WHERE deleted_at IS NULL`.

**`shift_assignments.notes` values:**
- Working: `P` (pagi), `S` (siang), `M` (malam)
- Non-working: `L` (libur), `CT` (cuti tahunan), `CS` (cuti sakit), `DL` (dinas luar), `TB` (tugas belajar), `OFF`

---

## Section 7 — SSO Integration (Implemented 2026-05-15)

### Status: ✅ Implemented

### Approach: Option 2 — Token Proxy via API Call

atoms-maintenance validates Sanctum tokens by calling atoms-rostering's own
`GET /api/auth/me` endpoint. No shared database coupling, no JWT secret required.

### Token Passing Mechanism

```
atoms-rostering frontend (port 5174)
  → User clicks Maintenance button
  → MenuGrid.tsx reads token from sessionStorage
  → window.location.href = 'http://localhost:5173?token={sanctum_token}'

atoms-maintenance frontend (port 5173)
  → AuthContext.initAuth() reads ?token from URL
  → Removes token from URL (history.replaceState)
  → Calls GET /api/v1/auth/verify with token as Bearer header
  → Backend proxies to GET http://localhost:8001/api/auth/me
  → If valid: stores token in sessionStorage, sets user in context
  → If invalid: redirects to http://localhost:5174/login
```

### URL Format for Redirect

```
http://localhost:5173?token={url_encoded_sanctum_token}
```

The token is URL-encoded via `encodeURIComponent()` to handle the `|` character
in Sanctum token format (`{id}|{random_string}`).

### Middleware That Handles Verification

| Middleware | File | When Used |
|-----------|------|-----------|
| `MockAuthMiddleware` | `app/Http/Middleware/MockAuthMiddleware.php` | All protected routes (handles both mock and prod paths) |
| `VerifyRosteringToken` | `app/Http/Middleware/VerifyRosteringToken.php` | Available as `rostering.auth` alias for explicit use |
| `RosteringAuthService` | `app/Services/RosteringAuthService.php` | Called by both middleware — wraps the HTTP call to rostering |

**Route alias:** `mockauth` (unchanged) — now delegates to rostering proxy when `DEV_MOCK_AUTH=false`.

### Token Payload Fields (from rostering /api/auth/me)

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | rostering user ID (= `local_users.rostering_user_id`) |
| `name` | string | Full name |
| `email` | string | Email address |
| `role` | string | Rostering role: `Admin`, `Cns`, `Support`, `Manager Teknik`, `General Manager` |
| `grade` | int\|null | Employee grade (13+ = supervisor level for CNS) |
| `is_active` | bool | Account active flag |
| `employee.id` | int | Employee record ID |
| `employee.employee_type` | string | `CNS`, `Support`, `Manager Teknik`, `Administrator` |
| `employee.group_number` | int\|null | Group/division number |
| `employee.is_fixed_manager` | bool | Fixed manager flag |

### Role Mapping (Rostering → Maintenance)

| Rostering Role | Maintenance Role | Notes |
|---------------|-----------------|-------|
| `Admin` | `Admin` | Direct map |
| `Manager Teknik` | `Manager Teknik` | Direct map |
| `General Manager` | `Manager Teknik` | Closest equivalent |
| `Cns` | `Teknisi CNSD` | Supervisor upgrade via grade check (grade ≥ 13) |
| `Support` | `Teknisi TFP` | Supervisor upgrade via grade check (grade ≥ 13) |

### Token Storage

- **atoms-rostering frontend:** `sessionStorage['auth_token']` (via `authStorage.ts`)
- **atoms-maintenance frontend:** `sessionStorage['auth_token']` (never localStorage)
- Session ends when browser tab is closed — correct for delegated auth

### Known Limitations

1. **No token refresh:** Sanctum tokens in atoms-rostering have no expiry set. If a token is
   revoked at rostering (logout), atoms-maintenance will return 401 on the next request.
   The frontend will then redirect to rostering login.

2. **atoms-rostering must be running:** If rostering backend is down, atoms-maintenance
   returns 401 for all requests. `RosteringAuthService` logs a warning and fails closed.

3. **Mock dev mode:** When `DEV_MOCK_AUTH=true` and `VITE_DEV_MOCK_AUTH=true`, the SSO
   flow is bypassed. Use mock-token-{id} pattern with local_users table.

4. **Supervisor role distinction:** `Cns` employees with `grade >= 13` are supervisors in
   maintenance context. The role mapping in `RosteringAuthService::mapRole()` currently
   maps all `Cns` to `Teknisi CNSD`. Supervisor upgrade logic should be added when
   CNSD supervisor-specific routes are implemented.

### Files Modified/Created for SSO

| File | Change |
|------|--------|
| `app/Services/RosteringAuthService.php` | NEW — HTTP proxy to rostering /api/auth/me |
| `app/Http/Middleware/VerifyRosteringToken.php` | NEW — standalone middleware (alias: `rostering.auth`) |
| `app/Http/Middleware/MockAuthMiddleware.php` | MODIFIED — now handles both mock and prod paths |
| `app/Http/Controllers/Api/V1/AuthController.php` | MODIFIED — added `verify()` public endpoint |
| `routes/api.php` | MODIFIED — added `GET /api/v1/auth/verify` (public) |
| `bootstrap/app.php` | MODIFIED — added `rostering.auth` alias |
| `.env` / `.env.example` | MODIFIED — added `ROSTERING_FRONTEND_URL`, port comments |
| `frontend/.env` | MODIFIED — added `VITE_ROSTERING_FRONTEND_URL=http://localhost:5174` |
| `frontend/src/contexts/AuthContext.tsx` | MODIFIED — full SSO token-from-URL flow |
| `frontend/src/components/layout/ProtectedRoute.tsx` | MODIFIED — production redirect to rostering |
| `frontend/src/pages/auth/LoginPage.tsx` | MODIFIED — production redirect, mock form preserved |
| `frontend/src/services/authService.ts` | MODIFIED — added `verify()`, fixed sessionStorage |
| `frontend/src/services/workOrderService.ts` | MODIFIED — fixed localStorage → sessionStorage |
| `atoms-rostering/frontend_atoms/src/components/feature/home/MenuGrid.tsx` | MODIFIED — Maintenance button SSO redirect |
