# SKILL: Frontend Architecture Reference — atoms-maintenance

This skill provides guidance on how the frontend is structured, so backend developers and agents can align API responses with frontend expectations.

---

## Frontend Tech Stack

- **Framework:** React 19 + TypeScript 6
- **Build Tool:** Vite 8
- **Styling:** Tailwind CSS 3.4
- **Routing:** React Router DOM 7
- **State:** React Context (Auth, Notification, Theme)
- **Theme:** Light-mode only
- **Backend:** None yet — all data from `src/data/mockData.ts`

---

## Frontend Pages & Modules

| Module | Route | Status | Backend API Needed |
|--------|-------|--------|-------------------|
| Dashboard | `/` | Implemented | `GET /dashboard/summary`, `/checklist`, `/trouble-equipment`, `/shift-info` |
| Work Orders | `/work-orders`, `/work-orders/:id` | Implemented | Full CRUD + status transitions |
| CNSD EQ-1 | `/cnsd/eq-1` | Implemented | `POST /cnsd/meter-readings` |
| TFP AOB | `/tfp/aob-ground` | Implemented | `POST /tfp/performance-checks` |
| Ground Check | `/ground-check` | Implemented | `GET/POST /ground-checks` |
| Grounding | `/grounding` | Implemented | `GET/POST /grounding-reports` |
| Reporting | `/reporting` | Implemented | CRUD + approval workflow |
| Logbook | `/logbook` | Implemented | Upload + list + download |
| Login | `/login` | Implemented | Mock or SSO |

---

## Frontend Type Definitions

The frontend defines all data shapes in `src/types/index.ts`. Backend API responses **must match these types**.

Key types:
- `User` — id, name, email, role (UserRole), employee_id, is_active
- `WorkOrder` — wo_number, wo_type, division, shift_type, status, personnel[], etc.
- `CnsdMeterReading` — sections[] → rows[] (EQ-1 form structure)
- `TfpPerformanceCheck` — measurements[], facilities[]
- `GroundingReport` — visualItems[], measurementItems[]
- `MaintenanceReport` — report_type, status, approval workflow
- `Logbook` — file metadata with division/month/year
- `ShiftScheduleResponse` — current_shift, personnel[], shift_start, shift_end

---

## Frontend Auth Flow

1. `AuthContext` manages user, token, and isAuthenticated state.
2. Token stored in `localStorage` as `auth_token`.
3. User stored in `localStorage` as `user` (JSON).
4. `authService.ts` calls `POST /auth/login` with email/password.
5. Response expected: `{ access_token, token_type, user }`.
6. `updateUser()` method allows injecting mock users without API call.

### Mock Auth (Dev)
- When `VITE_DEV_MOCK_AUTH=true`, frontend skips real login API.
- Injects mock user + `mock-token-{user_id}` into AuthContext.
- All API calls include `Authorization: Bearer mock-token-{user_id}`.

---

## API Expectations

The frontend uses `VITE_API_URL` (default: `http://localhost:8000/api`) as the base URL.

All API calls expect:
- `Authorization: Bearer {token}` header
- JSON request/response bodies
- Standard pagination: `{ data, current_page, last_page, per_page, total }`

---

## When Building Backend Features

1. **Check `src/types/index.ts`** for the exact TypeScript interface the frontend expects.
2. **Check `src/data/mockData.ts`** for example data structures.
3. **Match field names exactly** — the frontend uses the response directly.
4. **Use snake_case** for API response fields (matching the TypeScript types).
5. **Return nested objects** where the frontend expects them (e.g., `manager: { id, name }`).
