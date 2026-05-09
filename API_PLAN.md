# API_PLAN.md — atoms-maintenance

## Tech Stack

- **Framework:** PHP 8.x (Laravel)
- **Database:** PostgreSQL
- **Auth:** Laravel Sanctum (dev: mock auth middleware)

---

## General Guidelines

- **Versioning:** All endpoints prefixed with `/api/v1/`
- **Response Format:** Standard JSON wrapper for all responses:
  ```json
  {
    "success": true,
    "message": "Work order created",
    "data": { ... },
    "errors": null
  }
  ```
- **Pagination:** Use Laravel's built-in pagination. Query params: `?page=1&per_page=15`
- **Filtering:** Standard query params: `?division=CNSD&status=open&shift_date=2026-04-12`
- **Sorting:** `?sort_by=created_at&sort_dir=desc`
- **Error Responses:** HTTP status codes + error body: `{ "success": false, "message": "...", "errors": { "field": ["..."] } }`

---

## Authentication & Authorization

### Auth Endpoints

| Method | Endpoint | Description | Auth | Dev Mock |
|--------|----------|-------------|------|----------|
| `POST` | `/api/v1/auth/login` | Login (proxies to rostering in prod, mock in dev) | Public | Returns mock token |
| `POST` | `/api/v1/auth/logout` | Logout / revoke token | Bearer | Clears session |
| `GET` | `/api/v1/auth/me` | Get current user profile | Bearer | Returns mock user |

**Integration Status:**
- `POST /api/v1/auth/login` and `GET /api/v1/auth/me` are successfully integrated with `frontend_atoms-maintenance`.
- The frontend correctly receives the mock token and populates the local session.
- **Note:** Work Order UI and Backend have been updated to use standardized statuses (`completed`, `on_hold`, `ongoing`). The Work Order API is now fully integrated.

### Role Middleware

| Role | Access Level |
|------|-------------|
| `Admin` | Full system access |
| `Manager Teknik` | All reports, approve/reject, all divisions |
| `Supervisor CNSD` | CNSD work orders, inspections, reports |
| `Supervisor TFP` | TFP work orders, inspections, reports |
| `Teknisi CNSD` | Create/execute CNSD work orders, fill CNSD forms |
| `Teknisi TFP` | Create/execute TFP work orders, fill TFP forms |

---

## Dashboard Endpoints

| Method | Endpoint | Description | Auth | Roles |
|--------|----------|-------------|------|-------|
| `GET` | `/api/v1/dashboard/summary` | Aggregated metrics (open WOs, pending reports, etc.) | Bearer | All |
| `GET` | `/api/v1/dashboard/checklist` | Shift checklist items for current shift | Bearer | All |
| `GET` | `/api/v1/dashboard/trouble-equipment` | Currently troubled equipment | Bearer | All |
| `GET` | `/api/v1/dashboard/shift-info` | Current shift info & personnel (from rostering cache) | Bearer | All |

---

## Work Order Endpoints

| Method | Endpoint | Description | Auth | Roles |
|--------|----------|-------------|------|-------|
| `GET` | `/api/v1/work-orders` | List work orders (filterable, paginated) | Bearer | All |
| `POST` | `/api/v1/work-orders` | Create new work order | Bearer | Supervisor+, Manager, Admin |
| `GET` | `/api/v1/work-orders/{id}` | Get work order detail | Bearer | All |
| `PUT` | `/api/v1/work-orders/{id}` | Update work order fields | Bearer | Creator, Supervisor+, Manager |
| `PUT` | `/api/v1/work-orders/{id}/status` | Transition work order status | Bearer | Assigned personnel, Supervisor+ |
| `PUT` | `/api/v1/work-orders/{id}/complete` | Submit completion data (times, status, notes) | Bearer | Assigned personnel |
| `PUT` | `/api/v1/work-orders/{id}/feedback` | Add manager/supervisor feedback | Bearer | Manager, Supervisor |
| `DELETE` | `/api/v1/work-orders/{id}` | Soft-delete work order | Bearer | Admin, Manager |

### Work Order Status State Machine

```
ongoing ──► on_hold
   │           │
   └──► completed ◄──┘
```

### Query Params for `GET /work-orders`
- `division` — `CNSD` or `TFP`
- `status` — `ongoing`, `on_hold`, `completed`
- `wo_type` — `shift` or `personal`
- `shift_date` — ISO date
- `shift_type` — `pagi`, `siang`, `malam`
- `page`, `per_page`

---

## CNSD Inspection Endpoints

| Method | Endpoint | Description | Auth | Roles |
|--------|----------|-------------|------|-------|
| `GET` | `/api/v1/cnsd/categories` | List CNSD equipment categories | Bearer | All |
| `GET` | `/api/v1/cnsd/meter-readings` | List CNSD EQ-1 submissions (filtered) | Bearer | All |
| `POST` | `/api/v1/cnsd/meter-readings` | Submit new EQ-1 form | Bearer | Teknisi CNSD, Supervisor CNSD |
| `GET` | `/api/v1/cnsd/meter-readings/{id}` | Get EQ-1 submission detail | Bearer | All |

### Request Body for `POST /cnsd/meter-readings`
```json
{
  "category_id": 1,
  "shift_type": "pagi",
  "shift_date": "2026-04-12",
  "overall_status": "normal",
  "notes": "All equipment normal",
  "sections": [
    {
      "section_title": "VCCS",
      "rows": [
        {
          "no": 1,
          "equipment": "VCCS Server A",
          "status": "Normal",
          "keterangan": "",
          "server_aktif": "A"
        }
      ]
    }
  ]
}
```

---

## TFP Inspection Endpoints

| Method | Endpoint | Description | Auth | Roles |
|--------|----------|-------------|------|-------|
| `GET` | `/api/v1/tfp/categories` | List TFP equipment categories | Bearer | All |
| `GET` | `/api/v1/tfp/performance-checks` | List TFP AOB submissions | Bearer | All |
| `POST` | `/api/v1/tfp/performance-checks` | Submit new AOB form | Bearer | Teknisi TFP, Supervisor TFP |
| `GET` | `/api/v1/tfp/performance-checks/{id}` | Get AOB submission detail | Bearer | All |

---

## Ground Check Endpoints

| Method | Endpoint | Description | Auth | Roles |
|--------|----------|-------------|------|-------|
| `GET` | `/api/v1/ground-checks` | List ground check readings | Bearer | All |
| `POST` | `/api/v1/ground-checks` | Submit new ground check reading | Bearer | Teknisi CNSD, Supervisor CNSD |
| `GET` | `/api/v1/ground-checks/{id}` | Get ground check detail | Bearer | All |

---

## Grounding Inspection Endpoints

| Method | Endpoint | Description | Auth | Roles |
|--------|----------|-------------|------|-------|
| `GET` | `/api/v1/grounding-reports` | List grounding reports | Bearer | All |
| `POST` | `/api/v1/grounding-reports` | Submit new grounding report | Bearer | All technicians |
| `GET` | `/api/v1/grounding-reports/{id}` | Get grounding report detail | Bearer | All |

### Request Body for `POST /grounding-reports`
```json
{
  "kantor_unit_kerja": "Cabang Surabaya",
  "nama_peralatan": "GEDUNG AOB/ TOWER",
  "lokasi_peralatan": "gedung AOB/ TOWER",
  "tanggal": "2026-04-16",
  "lokasi_kerja": "Surabaya",
  "visual_items": [
    { "no": 1, "name": "Terminal Udara", "ketersediaan": "Ada", "kondisi": "Baik", "catatan": "" }
  ],
  "measurement_items": [
    { "no": 1, "name": "Nilai Tahanan Tahanan Tanah", "standard": "≤ 1 Ω", "kondisi": "Baik", "hasil_pengukuran": "0.5 Ω" }
  ]
}
```

---

## Reporting Endpoints

| Method | Endpoint | Description | Auth | Roles |
|--------|----------|-------------|------|-------|
| `GET` | `/api/v1/reports` | List maintenance reports (filtered) | Bearer | All |
| `POST` | `/api/v1/reports` | Create new report | Bearer | Supervisor+, Manager |
| `GET` | `/api/v1/reports/{id}` | Get report detail | Bearer | All |
| `PUT` | `/api/v1/reports/{id}` | Update report content | Bearer | Creator |
| `PUT` | `/api/v1/reports/{id}/submit` | Submit for manager approval | Bearer | Creator |
| `PUT` | `/api/v1/reports/{id}/approve` | Approve report | Bearer | Manager |
| `PUT` | `/api/v1/reports/{id}/reject` | Reject report with reason | Bearer | Manager |

### Report Status Flow
```
draft ──► pending_manager ──► final
                │
                └──► rejected ──► (edit) ──► pending_manager
```

---

## Logbook Endpoints

| Method | Endpoint | Description | Auth | Roles |
|--------|----------|-------------|------|-------|
| `GET` | `/api/v1/logbooks` | List logbooks (filterable by division, month, year) | Bearer | All |
| `POST` | `/api/v1/logbooks` | Upload new logbook PDF | Bearer | Supervisor+, Manager |
| `GET` | `/api/v1/logbooks/{id}` | Get logbook metadata | Bearer | All |
| `GET` | `/api/v1/logbooks/{id}/download` | Download logbook file | Bearer | All |
| `DELETE` | `/api/v1/logbooks/{id}` | Delete logbook | Bearer | Admin, Manager |

---

## Personnel / Shift Endpoints (Proxy to Rostering)

| Method | Endpoint | Description | Auth | Notes |
|--------|----------|-------------|------|-------|
| `GET` | `/api/v1/personnel` | List active personnel | Bearer | Reads from `local_users` cache |
| `GET` | `/api/v1/personnel/shift-today` | Current shift personnel | Bearer | In dev: returns mock data. In prod: proxies to rostering |

---

## Notifications Endpoints (Future)

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `GET` | `/api/v1/notifications` | List user notifications | Bearer |
| `PUT` | `/api/v1/notifications/{id}/read` | Mark notification as read | Bearer |
| `PUT` | `/api/v1/notifications/read-all` | Mark all as read | Bearer |

---

## Notes

### Observed from atoms-rostering
- Routes grouped by feature (`/auth`, `/admin`, `/rosters`, `/notifications`)
- Heavy use of role middleware: `middleware('role:Admin,Manager Teknik')`
- Controller/Service pattern used (e.g., `ShiftResolverService`)
- Sanctum for token-based auth

### Proposed for atoms-maintenance
- Adopt clear resource-based routing with `/api/v1/` prefix
- Use Form Request classes for validation (not inline `$request->validate()`)
- Controller → Service → Repository pattern for complex logic

### Do Not Copy Directly
- Do not replicate rostering's batch import routes (`/rosters/import`, `/rosters/batch-update`)
- Do not replicate shift request/leave request endpoints — those stay in rostering
