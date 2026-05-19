# BACKEND_CONTEXT.md — atoms-maintenance

## Vision

The ATOMS-Maintenance backend is a **standalone Laravel API** that powers the maintenance operations management system for AirNav Indonesia Surabaya. It handles maintenance-specific business logic — Work Orders, equipment inspections (CNSD/TFP), ground checks, grounding inspections, reporting, and logbook management.

**It does NOT own user accounts, employee master data, or shift/roster schedules.** Those belong to `atoms-rostering`.

---

## Two-System Architecture

### atoms-rostering (Source of Truth)
- **Owns:** User accounts, login/authentication, employee records, shift definitions, roster schedules, leave requests, shift swaps.
- **Tech:** Laravel + PostgreSQL (`atoms_rostering`), Sanctum auth.
- **Status:** Production-ready, actively maintained. Running on port 8001 (local dev).

### atoms-maintenance (This Project)
- **Owns:** Work Orders, CNSD inspections (EQ-1), TFP performance checks (AOB Ground), Ground Check, Grounding inspections, Maintenance Reports, Logbooks, Dashboard aggregation.
- **Tech:** PHP 8.x (Laravel) + PostgreSQL.
- **Status:** Phase 3 Scaffolded (Work Orders). Phase 4 Prepared (CNSD). Laravel base installed, `local_users` seeded, MockAuth active. **Successfully integrated and tested with `frontend_atoms-maintenance`**. Frontend UI has been updated to use standardized Work Order statuses (`completed`, `on_hold`, `ongoing`) and features a Print PDF layout. Backend development should align with these UI expectations. Ready to build features.

### Integration Points
| Data | Source | How Maintenance Accesses It |
|------|--------|-----------------------------|
| User login & tokens | atoms-rostering | ✅ SSO via token proxy — `GET http://localhost:8001/api/auth/me` |
| Employee profiles | atoms-rostering | ✅ Via `local_users` cache (manual seed; real sync pending) |
| Current shift & personnel | atoms-rostering | ✅ `GET /api/v1/personnel/shift-today` → `RosteringIntegrationService` |
| Shift definitions (pagi/siang/malam) | atoms-rostering | ✅ Read via `rostering` DB connection |
| Work Orders | atoms-maintenance | ✅ Local DB (owned) — Complete & Verified |
| Inspections (CNSD/TFP) | atoms-maintenance | ⬜ Local DB (owned) — Phase 4+ |
| Reports & Logbooks | atoms-maintenance | ⬜ Local DB (owned) — Phase 5+ |

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

---

## Current Work Order API and Signature State (2026-05-12)

This section reflects the implemented backend state after the Work Order signature pass.

### Implemented API Endpoints

All endpoints are under `/api/v1` and return the standard response wrapper:
`{ success, message, data, errors }`.

| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| POST | `/auth/login` | Public | Mock login in development mode. |
| GET | `/auth/me` | Bearer/mockauth | Return current authenticated local user. |
| POST | `/auth/logout` | Bearer/mockauth | Logout current mock session. |
| GET | `/work-orders` | Bearer/mockauth | Paginated/filterable Work Order list. |
| POST | `/work-orders` | Bearer/mockauth + role | Create Work Order. Status is derived, not accepted from client. |
| GET | `/work-orders/{id}` | Bearer/mockauth | Work Order detail. |
| PUT | `/work-orders/{id}` | Bearer/mockauth + policy | Update editable Work Order fields. |
| DELETE | `/work-orders/{id}` | Bearer/mockauth + Admin/Manager Teknik | Soft-delete Work Order. |
| POST | `/work-orders/{id}/sign` | Bearer/mockauth + policy | Save immutable base64 PNG signature for `mt`, `supervisor`, or `technician`. |
| GET | `/work-orders/{id}/print` | Bearer/mockauth + policy | Return full print data, including required/pending signatures. |
| GET | `/personnel` | Bearer/mockauth | Return active `local_users` for dropdowns. |
| GET | `/notifications` | Bearer/mockauth | Return notifications. |
| PUT | `/notifications/{id}/read` | Bearer/mockauth | Mark one notification as read. |
| PUT | `/notifications/read-all` | Bearer/mockauth | Mark all notifications as read. |

### Work Order Signature Implementation

- Shared trait: `app/Traits/HasSignature.php`.
- Work Order model uses `HasSignature` and `SoftDeletes`.
- Signature columns live on `work_orders`: `mt_name`, `mt_signature`, `mt_signed_by`, `mt_signed_at`, `supervisor_name`, `supervisor_signature`, `supervisor_signed_by`, `supervisor_signed_at`, `technician_name`, `technician_signature`, `technician_signed_by`, `technician_signed_at`.
- Signatures are base64 PNG data URLs stored directly in database long text fields. No file upload or filesystem storage is used.
- Signatures are immutable. A role cannot overwrite an existing signature.
- Required Work Order signatures are `mt`, `supervisor`, and `technician` when `has_supervisor = true`; otherwise only `mt` and `technician`.
- Work Order statuses are authoritative as `ongoing`, `on_hold`, and `completed`.
- Status is derived from signatures and shift timing; clients must not manually set status.

### Local Users Cache

Table: `local_users`.

Structure:
- `id`
- `rostering_user_id`
- `name`
- `email`
- `role`
- `division`
- `is_active`
- `synced_at`
- timestamps
- `deleted_at`

This table is the maintenance-side cache for authenticated users and signer references. It is not the source of truth for rostering data.

### Known Remaining Backend Gaps

- ~~A named read-only rostering DB connection is not configured yet.~~ **RESOLVED (2026-05-15):** Koneksi `rostering` sudah dikonfigurasi di `config/database.php` dan env vars `ROSTERING_DB_*` sudah ditambahkan. `RosteringIntegrationService` sudah dibuat di `app/Services/RosteringIntegrationService.php`. Endpoint `GET /api/v1/personnel/shift-today` sudah tersedia.
- ~~Work Order `isShiftEnded()` di model `WorkOrder` masih menggunakan hardcoded fallback times.~~ **RESOLVED (2026-05-15):** `WorkOrder::isShiftEnded()` sekarang menggunakan `RosteringIntegrationService::isShiftEnded()` dengan fallback ke hardcoded jika rostering tidak tersedia. `WorkOrderService::createWorkOrder()` sekarang auto-resolve MT dan supervisor dari rostering jika roster dipublish.
- ~~CNSD is scaffold-only.~~ **RESOLVED (2026-05-17):** Form EQ-1 (Kesiapan Peralatan CNSD) shipped sebagai pilot. Schema, service, controller, dan routes lengkap. Lihat `cnsd-readiness-rules.md` di `.agents/instructions/`.
- TFP, Ground Check, Grounding, Logbook, dan Reporting backend modules belum diimplementasi.
- CNSD print backend belum dibuat — print direncanakan frontend-only.
- Future modules harus reuse `HasSignature` trait, bukan signature logic per-modul baru.

---

## Work Order List — Filter API

**Endpoint:** `GET /api/v1/work-orders`

Semua parameter opsional. Filter kosong/tidak dikirim diabaikan.

| Parameter | Tipe | Deskripsi |
|---|---|---|
| `search` | string | ILIKE match pada `wo_number` dan `description` |
| `shift_date` | `YYYY-MM-DD` | Cocok persis dengan `shift_date` |
| `year` | string 4-digit | `EXTRACT(YEAR FROM shift_date)` |
| `division` | `CNSD` atau `TFP` | Cocok persis |
| `shift_type` | `pagi`, `siang`, `malam` | Cocok persis |
| `status` | `ongoing`, `on_hold`, `completed` | Cocok persis |
| `wo_type` | `shift`, `personal` | Cocok persis |
| `sort_by` | string | Kolom sortir |
| `sort_dir` | `asc`, `desc` | Arah sortir |
| `per_page` | int (max 100) | Jumlah per halaman, default 15 |

**Endpoint tambahan:** `GET /api/v1/work-orders/years`

Mengembalikan array tahun tersedia dari `shift_date`, descending. Selalu menyertakan tahun berjalan. Dipakai oleh dropdown tahun di frontend.

## Work Order Nomor Format

Format: `WO-{DIVISI}-{YYYYMMDD}-{SEQ}`
Contoh: `WO-CNSD-20260516-001`, `WO-TFP-20260516-001`

- Format lama (`WO-{DIV}-{DD}-{MM}-{YYYY}-{SEQ}`) tetap bisa ditampilkan dan dicari — search di backend memakai ILIKE.
- Nomor urut (3 digit, zero-padded) reset per tanggal + divisi.
- Format baru sortable secara string tanpa konversi.

## Work Order List UI

- Search bar + 5 filter (tanggal, tahun, divisi, shift, status) dalam filter bar satu card.
- Debounce search 350ms supaya tidak membombardir API saat ketik.
- **Active filter chips**: tiap filter aktif muncul sebagai chip biru yang bisa di-close satu per satu.
- **Reset Filter** tombol muncul di sebelah search bar saat ada filter aktif. Reset menghapus semua filter sekaligus.
- **Result count** ditampilkan di kanan bawah filter bar.
- **Empty state DB kosong**: ikon + pesan "Belum ada Work Order" + tombol Buat Work Order.
- **Empty state filter**: ikon + pesan "Tidak ada Work Order yang sesuai filter" + link Reset.
- Responsif: grid 2 kolom di mobile, 5 kolom di desktop.
- Fetch ulang setiap kali filter berubah; create/edit modal onClose juga fetch ulang (filter tetap aktif).

## Database Default — Empty by Design

`DatabaseSeeder::run()` kosong. `MockUserSeeder` dan `WorkOrderSeeder` deprecated, tidak dipanggil. Setelah `migrate:fresh --seed`: 0 work_orders, 0 local_users.

Semua endpoint yang membaca personel dari rostering wajib menerima **date + shift_type** secara eksplisit dari client.

`GET /api/v1/personnel/shift-today?date=YYYY-MM-DD&shift_type=pagi|siang|malam`

Backend timezone = `UTC`. Auto-detect "shift sekarang" via `Carbon::now()` di backend salah untuk operasional WIB. Frontend mengirim shift hasil hitung dari client clock.

Response:
- `manager` (object|null) — Manager Teknik untuk date+shift tersebut. Diquery dari `shift_assignments` dengan `employees.employee_type = 'Manager Teknik'`. (Tabel `manager_duties` di rostering belum dipakai / kosong.)
- `supervisor` (object|null) — alias backward-compat: CNSD diutamakan, fallback TFP.
- `supervisor_cnsd` (object|null) — CNS dengan `grade >= 13` pada shift ini.
- `supervisor_tfp` (object|null) — Support dengan `grade >= 13` pada shift ini.
- `personnel` (Collection) — semua CNS + Support yang bertugas pada date+shift tersebut. **Tidak termasuk MT** (filter `employee_type IN ('CNS','Support')`).
- `has_supervisor` (bool), `roster_available` (bool), `shift_times` (object|null).

`RosteringIntegrationService` punya:
- `getShiftManager($shift, $date)` — query shift_assignments untuk MT (employee_type='Manager Teknik').
- `getShiftSupervisorByDivision($shift, $date, 'CNS' | 'Support')`.
- `getShiftSupervisor($shift, $date)` — convenience yang prefer CNSD lalu fallback TFP.
- `getShiftPersonnel($shift, $date)` — semua CNS + Support (tidak termasuk MT).
- `getShiftContext($shift, $date)` — agregat lengkap untuk endpoint `shift-today`.

## User Identifier Mapping

Frontend selalu mengirim **rostering_user_id** (sumber kebenaran) untuk semua field user di payload Work Order create. Backend menerjemahkannya ke **local_users.id** sebelum simpan, lewat `App\Services\LocalUserResolver`.

Resolver behavior:
- Cari row `local_users` dengan `rostering_user_id` cocok. Jika ada, return.
- Jika tidak ada, query rostering DB read-only untuk users + employees, lalu `LocalUser::updateOrCreate()` dengan role + division yang dipetakan otomatis.
- Aman dipanggil berkali-kali (idempotent).

`WorkOrderService::mapPayloadRosteringIdsToLocal()` memanggil resolver untuk: `manager_id`, `supervisor_id`, `assigned_technician_id`, `personnel[].user_id`. Mapping dijalankan **sebelum** auto-resolve dari roster, supaya tidak terjadi double-translation.

FormRequest `WorkOrderCreateRequest` **tidak** memakai rule `exists:local_users,id` di field user-id karena resolver akan create on-the-fly. `personnel` array juga `sometimes` (tidak required) — backend `autoFillShiftPersonnelFromRostering()` mengisi otomatis dari rostering shift personnel saat array kosong dan `wo_type='shift'`.

## Database Default — Empty by Design

`DatabaseSeeder::run()` sengaja kosong. `migrate:fresh --seed` menghasilkan **0 work_orders, 0 local_users**. Pengisian terjadi via:

1. **SSO login** — `RosteringAuthService::buildTransientUser()` upsert ke `local_users` setiap kali user login.
2. **Work Order create** — `LocalUserResolver::ensureLocalUser()` lazy-create personnel yang direferensikan.
3. **`php artisan local-users:sync`** — bulk pull semua active users dari rostering. Mendukung `--dry-run` (preview) dan `--prune-stale` (deactivate users yang sudah tidak ada di rostering).
4. **`php artisan local-users:cleanup`** — deteksi dan hapus duplikat `local_users` yang `rostering_user_id`-nya tidak cocok dengan rostering live. Aman: tidak menghapus row yang punya FK references (work_orders, signatures, personnel).

`MockUserSeeder` dan `WorkOrderSeeder` **deprecated** dan **tidak dipanggil** dari `DatabaseSeeder`. Jika dipanggil manual via `db:seed --class=...`, mereka mengeluarkan warning. Jangan kembalikan ke pipeline default.

## Signature Authorization

Sign endpoint `POST /api/v1/work-orders/{id}/sign` enforce nama+role:
- `WorkOrderService::assertSignerCanSignRole()` cek role (LocalUser->isManager / isSupervisor / isTeknisi).
- Lalu cek nama: `namesMatch($workOrder->{role}_name, $signer->name)` dengan tolerant compare (trim + collapse whitespace + case-insensitive).
- Untuk technician di shift WO, fallback diperbolehkan: nama signer cocok dengan salah satu nama di `personnel[]`.
- Jika nama / role tidak cocok → throw `App\Exceptions\SignerNotAuthorizedException` → controller map ke HTTP 403.
- Re-sign signature yang sudah ada → throw `RuntimeException` → 409 (immutable rule).


## CNSD Equipment Readiness — Form EQ-1 (Phase 4 pilot)

Status: ✅ Live (2026-05-17)

### Tabel
- `cnsd_readiness_records` — header per form. Unique partial index per
  (form_type, facility, date, shift_type) ignoring soft-deleted rows.
- `cnsd_readiness_technicians` — snapshot teknisi CNSD per record. Per-row
  immutable signature.
- `cnsd_readiness_items` — item form di-generate dari template EQ-1 di
  `app/Services/Cnsd/CnsdEq1Template.php` (5 section, 36 item baseline).

### Endpoints (semua di bawah `/api/v1/cnsd/readiness`)

| Method | URI | Roles |
|---|---|---|
| GET    | `/` | semua authenticated |
| GET    | `/years` | semua authenticated |
| GET    | `/template` | semua authenticated |
| POST   | `/` | Admin / Manager Teknik / Supervisor CNSD / Teknisi CNSD |
| GET    | `/{id}` | semua authenticated |
| PUT    | `/{id}` | semua authenticated (hanya update item values) |
| POST   | `/{id}/sign` | sesuai role + nama signer match |
| DELETE | `/{id}` | Admin / Manager Teknik |

### Signature Authorization

`CnsdReadinessService::signRecord()` mengikuti pola Work Order:
- Role check (`Manager Teknik` / `Supervisor CNSD` / `Teknisi CNSD`).
- Nama signer harus cocok dengan `manager_name` / `supervisor_name` /
  `cnsd_readiness_technicians.technician_name` (tolerant compare via
  `WorkOrderService::namesMatch()`).
- Untuk teknisi, optional `technician_row_id` di payload mengarah ke row spesifik.
  Backend juga bisa resolve via `signer.id` atau name match.
- Signature **immutable**, **tidak boleh diwakilkan**.

### Format Form Number

`{FORM_TYPE}-{FACILITY}-YYYYMMDD-SEQ`
Contoh: `EQ-1-CNSD-20260517-001`

### Roster Personnel

`CnsdReadinessService::resolveRosterContext()`:
- Manager Teknik dari `getShiftManager(date, shift)`.
- Supervisor CNSD dari `getShiftSupervisorByDivision(date, shift, 'CNS')`.
- Teknisi CNSD dari `getShiftPersonnel(date, shift)` filter `employee_type = 'CNS'`.
- Personel TFP/Support tidak diikutkan ke EQ-1.
- Jika tidak ada teknisi CNSD di shift → create return **422** dengan pesan jelas.
- Manager / supervisor nullable — jika tidak ada di roster, kolom tetap null
  dan tanda tangan untuk role tersebut tidak diwajibkan.


## CNSD Readiness — Phase 4.1 Update (2026-05-17)

Print view, notifications, and signer badge added. See session-handoff for details.

- **Print:** Frontend-only (`CnsdReadinessPrintView.tsx`), route `/cnsd/readiness/:id/print`.
- **Notifications:** `CnsdReadinessCreatedNotification` + `CnsdReadinessCompletedNotification`.
  `NotificationService::notifyReadinessCreated()` / `notifyReadinessCompleted()` added.
  Both fire via `database` channel (in-app bell).
- **Signer badge:** Client-side name match in `CnsdReadinessListPage`, badge "Perlu TTD".
- **Commits:**
  - Backend: `fde9929` (main) — `feat(cnsd): add EQ-1 readiness module with notifications, print support, and signer status`
  - Frontend: `83d1758` (main) — `feat(cnsd): add EQ-1 print view, signer badge, and CNSD readiness module`


## CNSD Radar Meter Reading — Phase 4 Module 2 (2026-05-18)

Status: ✅ Live (second CNSD module after EQ-1).

### Tabel
- `cnsd_radar_meter_records` — header, satu record per (form_type, facility, date, shift_type).
  Field Radar-spesifik: `merk` (default `ELDIS`), `type` (default `MSSR-1 / RL2000`), `serial_number`.
- `cnsd_radar_meter_technicians` — snapshot teknisi CNSD per record. Per-row immutable signature.
- `cnsd_radar_meter_items` — item form di-generate dari template (48 items: section A + C).

### Endpoints (semua di bawah `/api/v1/cnsd/radar-meter`)

| Method | URI | Roles |
|---|---|---|
| GET    | `/` | semua authenticated |
| GET    | `/years` | semua authenticated |
| GET    | `/template` | semua authenticated |
| POST   | `/` | Admin / MT / Sup CNSD / Teknisi CNSD |
| GET    | `/{id}` | semua authenticated |
| PUT    | `/{id}` | semua authenticated (hanya update item values) |
| POST   | `/{id}/sign` | sesuai role + nama signer match |
| DELETE | `/{id}` | Admin / MT |

### Signature Authorization
Identik dengan EQ-1: name-match, immutable, no delegation. Implementasi di
`CnsdRadarMeterService::signRecord()`.

### Format Form Number
`RADAR-YYMMDD-SEQ`
Contoh: `RADAR-260518-001`

### Roster Personnel
Sama dengan EQ-1: hanya `employee_type = 'CNS'`. Tidak ambil TFP. Jika tidak ada
teknisi CNSD pada shift → 422.

### EQ-1 Tetap Berjalan
Modul Radar hidup di tabel & namespace terpisah (`cnsd_radar_meter_*` vs
`cnsd_readiness_*`). Tidak ada perubahan ke EQ-1; kedua modul independen.


## TFP Performance Check AOB Lantai Ground — Phase 5 Module 1 (2026-05-19)

Status: ✅ Live (first TFP module).

### Tabel
- `tfp_aob_ground_records` — header, satu record per (form_type, date, shift_type).
  Field TFP-spesifik: `day_name` (hari otomatis dari date), `time_filled` (jam saat create HH:MM).
- `tfp_aob_ground_technicians` — snapshot teknisi TFP per record. Per-row immutable signature.
- `tfp_aob_ground_items` — 21 parameter form di-generate dari template.
  Kolom: `panel_cos_a03_input/output`, `panel_ats_a12_input/output`, `ups_tescom_a_input/output`, `ups_tescom_b_input/output`, `is_disabled_map` (jsonb).
- `tfp_aob_ground_facilities` — 17 fasilitas di-generate dari template.
  Kolom: `facility_name`, `kondisi` (Baik/Normal/Tidak Baik), `keterangan`.

### Endpoints (semua di bawah `/api/v1/tfp/aob-ground`)

| Method | URI | Roles |
|---|---|---|
| GET    | `/` | semua authenticated |
| GET    | `/years` | semua authenticated |
| GET    | `/template` | semua authenticated |
| POST   | `/` | Admin / MT / Sup TFP / Teknisi TFP |
| GET    | `/{id}` | semua authenticated |
| PUT    | `/{id}` | semua authenticated (update items + facilities) |
| POST   | `/{id}/sign` | sesuai role + nama signer match |
| DELETE | `/{id}` | Admin / MT |

### Signature Authorization
Identik dengan CNSD: name-match, immutable, no delegation. Implementasi di
`TfpAobGroundService::signRecord()`. Supervisor check: `isSupervisorTfp() || isSupervisor()`.

### Format Form Number
`TFP-AOBLTGND-YYMMDD-SEQ`
Contoh: `TFP-AOBLTGND-260519-001`

### Roster Personnel
`employee_type = 'Support'` (bukan 'CNS'). Supervisor TFP: `getShiftSupervisorByDivision(..., 'Support')`.
Jika tidak ada teknisi TFP pada shift → 422.

### Template
21 parameter (L1-N, L2-N, L3-N, N-G, L1-L2, L1-L3, L2-L3, L1, L2, L3, N, Frekuensi,
Power Factor, Tegangan Battery, Arus Battery, Kapasitas Battery, Suhu Battery, Mode, Suplai Aktif,
KWH Meter, Suhu Eq. Room). Row "Suhu Ruang ARO" TIDAK ADA (dihapus per permintaan).
17 fasilitas (Catu Daya Listrik, Penerangan, UPS Tescom A/B, AC 01-08, Papan Nama AirNav,
Atap, Plafond, Dinding, Pintu, Door Lock).

### Disabled Cell Enforcement (2026-05-19 refinement)
Backend `updateItems()` membaca `is_disabled_map` per item row dan strip kolom yang
`true` dari payload sebelum `fill()`. Hasil: bahkan jika klien mem-bypass UI dan mengirim
patch ke disabled cell (mis. `panel_cos_a03_input` untuk row Battery), backend tidak akan
menulis ke kolom itu. `is_disabled_map` adalah single source of truth dan di-set saat
record dibuat dari template — tidak boleh diubah lewat update endpoint.

### Time Filled Refresh on Update (2026-05-19 bugfix)
`time_filled` (HH:MM) di-refresh setiap kali user Simpan Perubahan via `updateItems()`
atau `updateFacilities()`. Implementasi: di akhir transaction sebelum return fresh,
`$record->time_filled = now()->format('H:i'); $record->save();`. Setiap save = snapshot
waktu baru. Tanggal dan hari (`date`, `day_name`) TIDAK di-refresh — tetap dari record
header. Cocok dengan paper form yang mencatat "jam saat petugas mengambil reading".

### Timezone Fix (2026-05-19 bugfix)
`config/app.php` diubah dari `'timezone' => 'UTC'` ke `'timezone' => 'Asia/Jakarta'`.
Sebelumnya `now()->format('H:i')` menghasilkan UTC time (7 jam lebih awal dari WIB).
Sekarang menghasilkan WIB time yang benar. Atoms-rostering sudah menggunakan `Asia/Jakarta`.

### Disabled Cell Rules
- Rows 1-12 (voltage/current/frequency): semua 8 kolom enabled
- Row 13 (Power Factor): UPS TESCOM A/B disabled
- Rows 14-17 (Battery): Panel COS A03 + Panel ATS A12 disabled
- Rows 18-19 (Mode, Suplai Aktif): UPS TESCOM A/B disabled
- Rows 20-21 (KWH Meter, Suhu Eq. Room): single value di panel_cos_a03_input, rest disabled

### Dropdown Rules
- Mode: Auto / Manual (Panel COS dan ATS)
- Suplai Aktif: PLN / UPS (Panel COS), PLN 1 / PLN 2 (Panel ATS)
- Kondisi fasilitas: Baik / Normal / Tidak Baik

### CNSD Tetap Berjalan
Modul TFP hidup di tabel & namespace terpisah (`tfp_aob_ground_*`). Tidak ada perubahan ke CNSD.


## CNSD Recorder Meter Reading — Phase 4 Module 3 (2026-05-19)

Status: ✅ Live (third CNSD module after EQ-1 and Radar).

### Tabel
- `cnsd_recorder_meter_records` — header, satu record per (form_type, facility, date, shift_type).
  Field Recorder-spesifik: `form_code` (default `FORM C-3`), `merk` (default `ATIS - UHER`),
  `type` (default `VC - MDx`), `serial_number` (default `51`).
- `cnsd_recorder_meter_technicians` — snapshot teknisi CNSD per record. Per-row immutable signature.
- `cnsd_recorder_meter_items` — item form di-generate dari template (72 items: section A 69 + section B 3,
  termasuk 15 U/S blocked).

### Endpoints (semua di bawah `/api/v1/cnsd/recorder-meter`)

| Method | URI | Roles |
|---|---|---|
| GET    | `/` | semua authenticated |
| GET    | `/years` | semua authenticated |
| GET    | `/template` | semua authenticated |
| POST   | `/` | Admin / MT / Sup CNSD / Teknisi CNSD |
| GET    | `/{id}` | semua authenticated |
| PUT    | `/{id}` | semua authenticated (hanya update item values, blocked items diabaikan) |
| POST   | `/{id}/sign` | sesuai role + nama signer match |
| DELETE | `/{id}` | Admin / MT |

### Signature Authorization
Identik dengan EQ-1 / Radar: name-match, immutable, no delegation. Implementasi di
`CnsdRecorderMeterService::signRecord()`.

### Format Form Number
`RECORDER-YYMMDD-SEQ`
Contoh: `RECORDER-260517-001`

### U/S Item Protection
- Items dengan `is_blocked = true` (Channel 7, 21–27, 29–35) tidak bisa diisi.
- `CnsdRecorderMeterService::updateItems()` mengabaikan patch yang menargetkan blocked rows.
- Frontend menampilkan red strip + label U/S, semua input disabled.

### Roster Personnel
Sama dengan EQ-1 / Radar: hanya `employee_type = 'CNS'`. Tidak ambil TFP. Jika tidak ada
teknisi CNSD pada shift → 422.

### EQ-1 dan Radar Tetap Berjalan
Modul Recorder hidup di tabel & namespace terpisah (`cnsd_recorder_meter_*` vs
`cnsd_radar_meter_*` vs `cnsd_readiness_*`). Tidak ada perubahan ke modul lain;
ketiga modul independen.


## CNSD AMSC Meter Reading — Phase 4 Module 4 (2026-05-19)

Status: ✅ Live (fourth CNSD module after EQ-1, Radar, and Recorder).

### Tabel
- `cnsd_amsc_meter_records` — header, satu record per (form_type, facility, date, shift_type).
  Field AMSC-spesifik: `merk` (default `ELSA`), `type` (default `1003Qi+`),
  `serial_number` (default `-`).
- `cnsd_amsc_meter_technicians` — snapshot teknisi CNSD per record. Per-row immutable signature.
- `cnsd_amsc_meter_items` — item form di-generate dari template (45 items: 4 sections).
  Kolom: `hasil_a`, `hasil_b` (Front Panel dual A/B), `hasil` (PSU + environment),
  `address`, `status_value`, `cct` (Channel AMSC), `keterangan`.

### Item Template
Sumber: `app/Services/Cnsd/CnsdAmscMeterTemplate.php`.

| Section | Layout | Items |
|---|---|---|
| 1. FRONT PANEL | Dual A/B | 5 items (All Status Indikator, Operation Server, Signal selector, Change Over, Clock Signal) |
| 2. POWER SUPPLY UNIT | Single HASIL | 5 items (+60V, -60V, +12V, +5V, -12V) |
| 3. CHANNEL AMSC | ADDRESS/STATUS/CCT | 32 items (Channel 1-32) |
| 4. LINGKUNGAN KERJA | Single HASIL | 3 items (Suhu, Humidity, Kebersihan) |

Total: **45 items**.

### Endpoints (semua di bawah `/api/v1/cnsd/amsc-meter`)

| Method | URI | Roles |
|---|---|---|
| GET    | `/` | semua authenticated |
| GET    | `/years` | semua authenticated |
| GET    | `/template` | semua authenticated |
| POST   | `/` | Admin / MT / Sup CNSD / Teknisi CNSD |
| GET    | `/{id}` | semua authenticated |
| PUT    | `/{id}` | semua authenticated (hanya update item values) |
| POST   | `/{id}/sign` | sesuai role + nama signer match |
| DELETE | `/{id}` | Admin / MT |

### Signature Authorization
Identik dengan EQ-1 / Radar / Recorder: name-match, immutable, no delegation.
Implementasi di `CnsdAmscMeterService::signRecord()`.

### Format Form Number
`AMSC-YYMMDD-SEQ`
Contoh: `AMSC-260519-001`

### Roster Personnel
Sama dengan EQ-1 / Radar / Recorder: hanya `employee_type = 'CNS'`. Tidak ambil TFP.
Jika tidak ada teknisi CNSD pada shift → 422.

### Frontend Input Variants
| Section | Input Type | Options |
|---|---|---|
| Front Panel `Normal / Alrm` | dropdown | Normal, Alrm |
| Front Panel `√ / -` | dropdown | √, - |
| Front Panel `OK / Not` | dropdown | OK, Not |
| Power Supply Unit | free text | — |
| Channel AMSC status | dropdown | Normal, U/S, Fault |
| Channel AMSC address/cct | display/free text | — |
| Lingkungan `Max 22°C` | free text | — |
| Lingkungan `√` | dropdown | √, - |

### Channel AMSC Default Values
Channels 1-32 seeded with default address and keterangan from paper form.
Channel 17 and 19 have default status `U/S` (ALTRN CH01, ALTRN CH04).

### EQ-1, Radar, dan Recorder Tetap Berjalan
Modul AMSC hidup di tabel & namespace terpisah (`cnsd_amsc_meter_*`).
Tidak ada perubahan ke modul lain; keempat modul independen.


## CNSD Transmitter Meter Reading — Phase 4 Module 5 (2026-05-19)

Status: ✅ Live (fifth CNSD module after EQ-1, Radar, Recorder, and AMSC).

### Tabel
- `cnsd_transmitter_meter_records` — header, satu record per (form_type, facility, date, shift_type).
  Field Transmitter-spesifik: `form_code` (default `FORM C-1`).
- `cnsd_transmitter_meter_technicians` — snapshot teknisi CNSD per record. Per-row immutable signature.
- `cnsd_transmitter_meter_items` — item form di-generate dari template.
  Kolom: `frequency_label`, `merk`, `tx_label`, `status_value`, `power_output`, `modulasi`,
  `keterangan`, `nominal`, `hasil`, `is_header`, `is_blocked`, `block_reason`.

### Item Template
Sumber: `app/Services/Cnsd/CnsdTransmitterMeterTemplate.php`.

| Section | Layout | Groups | Items |
|---|---|---|---|
| 1. TRANSMITTER / TX RADIO | Frequency/Merk/Status/Power/Modulasi/Keterangan | 9 groups (Ground, ADC, CDU, APP, TMA West, TMA East, ER Makassar, ATIS, Back Up Radio) | ~40 TX items + 9 headers |
| 2. LINGKUNGAN KERJA | NO/Kegiatan/Nominal/Hasil/Keterangan | 1 group | 4 items |

### Status Dropdown Rules
- PAE merk (Primary frequencies): On Air / STBY
- OTE merk (Secondary frequencies): Online / Offline
- CDU (group 3, all PAE): Online / Offline
- TMA West (group 5, all PAE): On Air / STBY
- Back Up Radio (group 9): BLOCKED — status cell disabled, backend rejects update

### Blocked Items (Back Up Radio)
- Group 9 items have `is_blocked = true`.
- Backend `updateItems()` allows `power_output`, `modulasi`, `keterangan` but NOT `status_value` for blocked items.
- Frontend renders status cell as grey/disabled.

### Endpoints (semua di bawah `/api/v1/cnsd/transmitter-meter`)

| Method | URI | Roles |
|---|---|---|
| GET    | `/` | semua authenticated |
| GET    | `/years` | semua authenticated |
| GET    | `/template` | semua authenticated |
| POST   | `/` | Admin / MT / Sup CNSD / Teknisi CNSD |
| GET    | `/{id}` | semua authenticated |
| PUT    | `/{id}` | semua authenticated (update item values, blocked status rejected) |
| POST   | `/{id}/sign` | sesuai role + nama signer match |
| DELETE | `/{id}` | Admin / MT |

### Signature Authorization
Identik dengan EQ-1 / Radar / Recorder / AMSC: name-match, immutable, no delegation.
Implementasi di `CnsdTransmitterMeterService::signRecord()`.

### Format Form Number
`TRANSMITTER-YYMMDD-SEQ`
Contoh: `TRANSMITTER-260519-001`

### Roster Personnel
Sama dengan EQ-1 / Radar / Recorder / AMSC: hanya `employee_type = 'CNS'`.
Tidak ambil TFP. Jika tidak ada teknisi CNSD pada shift → 422.

### Time Filled
`time_filled` di-refresh setiap kali user Simpan Perubahan (pola AMSC/TFP).
`day_name` di-set saat create.

### EQ-1, Radar, Recorder, dan AMSC Tetap Berjalan
Modul Transmitter hidup di tabel & namespace terpisah (`cnsd_transmitter_meter_*`).
Tidak ada perubahan ke modul lain; kelima modul independen.


## Grounding Report — Live (2026-05-19)

Status: ✅ Live (first Grounding module).

### Tabel
- `grounding_report_records` — header per laporan. Multiple records per (date, shift_type) diperbolehkan.
  Field: `report_number`, `date`, `day_name`, `time_filled`, `shift_type`, `work_unit` (default 'Cabang Surabaya'),
  `equipment_name`, `equipment_location`, `status`, manager/supervisor signature columns, soft deletes.
- `grounding_report_technicians` — snapshot teknisi TFP per record. Per-row immutable signature.
- `grounding_report_items` — 9 items dari template (6 VISUAL + 3 PENGUKURAN).
  Kolom: `section_name`, `item_number`, `item_name`, `standard`, `availability`, `condition`, `notes`.

### Endpoints (semua di bawah `/api/v1/grounding/reports`)

| Method | URI | Roles |
|---|---|---|
| GET    | `/` | semua authenticated |
| GET    | `/years` | semua authenticated |
| GET    | `/template` | semua authenticated |
| POST   | `/` | Admin / MT / Sup TFP / Teknisi TFP |
| GET    | `/{id}` | semua authenticated |
| PUT    | `/{id}` | semua authenticated (update item values) |
| POST   | `/{id}/sign` | sesuai role + nama signer match |
| DELETE | `/{id}` | Admin / MT |

### Signature Authorization
Identik dengan TFP AOB Ground: name-match, immutable, no delegation.
Implementasi di `GroundingReportService::signRecord()`.

### Format Report Number
`GROUNDING-YYMMDD-SEQ`
Contoh: `GROUNDING-260519-001`

### Roster Personnel
`employee_type = 'Support'` (bukan 'CNS'). Supervisor TFP: `getShiftSupervisorByDivision(..., 'Support')`.
Jika tidak ada teknisi TFP pada shift → 422.
Multiple records per shift diperbolehkan (berbeda peralatan).

### Template
6 VISUAL items (Terminal Udara, Konduktor Turun, Modul Penangkal Petir, Sambungan dan Clamp,
Kabel Pembumian, Lightning Counter) + 3 PENGUKURAN items (Nilai Tahanan Tanah ≤1Ω,
Nilai Tahanan Pentanahan ≤1Ω, Uji Kontinuitas -).

### Update Rules
- VISUAL items: availability (Ada/Tidak Ada), condition (Baik/Tidak Baik), notes
- PENGUKURAN items: condition, notes only (availability stays null)
- time_filled refreshed on every updateItems call

### TFP dan CNSD Tetap Berjalan
Modul Grounding hidup di tabel & namespace terpisah (`grounding_report_*`).
Tidak ada perubahan ke modul lain.


## Reporting / Laporan Kerusakan — Phase 6 Module 1 (2026-05-19)

Status: ✅ Live (first Reporting module).

### Karakteristik Unik
Reporting **TIDAK** menggunakan rostering shift personnel. Manager Teknik dan
Pelaksana Perbaikan dipilih manual oleh user. Tidak ada konsep shift.

### Tabel
- `reporting_damage_reports` — header laporan. Status: ongoing | on_hold | completed.
  Manager Teknik signature lives di kolom-kolom record ini.
- `reporting_damage_repairers` — per-row pelaksana + signature immutable.
  Repairers di-pilih manual; bisa campur Teknisi CNSD + Teknisi TFP + Supervisor.

### Endpoints (semua di bawah `/api/v1/reporting`)

| Method | URI | Roles |
|---|---|---|
| GET    | `/damage-reports` | semua authenticated |
| GET    | `/damage-reports/years` | semua authenticated |
| POST   | `/damage-reports` | Admin / MT / Sup CNSD/TFP / Tek CNSD/TFP |
| GET    | `/damage-reports/{id}` | semua authenticated |
| PUT    | `/damage-reports/{id}` | semua authenticated |
| POST   | `/damage-reports/{id}/sign` | sesuai role + nama signer match |
| DELETE | `/damage-reports/{id}` | Admin / MT |
| GET    | `/personnel?scope=manager\|repairer&search=&division=` | semua authenticated |

### Format Nomor Surat
`LTK-YYMMDD-SEQ`
Contoh: `LTK-260519-001`

- Prefix `LTK` (Laporan Teknik Kerusakan)
- Reset sequence per kalender hari
- Counter pakai `withTrashed()`

### Personnel Selector
- Source: `local_users` cache (TIDAK query rostering shift).
- Manager scope: `role = "Manager Teknik"` saja.
- Repairer scope: gabungan Teknisi CNSD/TFP + Supervisor CNSD/TFP.

### Signature Authorization
- Manager: hanya `role = Manager Teknik` + `manager_id == signer.id` atau name match.
- Repairer: role harus Teknisi/Supervisor CNSD/TFP atau Admin. Match by
  `repairer_row_id` (priority) → `person_id == signer.id` → name match.
- Wrong signer → 403. Already signed → 409. Invalid base64 → 422. Immutable.

### Kode Hambatan (9 codes)
AU, PK, TT, SC, TR, ST, PC, AL, TH. Jika `obstacle_code = AL`,
`obstacle_description` (Alasan Lain) **wajib** diisi.

### Validation Highlights
- `report_date`, `location`, `facility`, `equipment_name`, `damage_category`,
  `damage_description`, `manager_id`, `repairers[]` (min 1) wajib.
- `damage_category` ∈ {Ringan, Sedang, Berat}.
- `repair_by_type` ∈ {lokasi, pusat} atau null.
- `obstacle_code` validates against 9 codes; null ok.
- `repairers.*.person_id` tidak boleh duplikat dalam payload.

### Modul Lain Tetap Berjalan
Reporting hidup di tabel & namespace terpisah (`reporting_*`,
`App\Models\Reporting`, `App\Services\Reporting`). Tidak ada perubahan ke
Work Order, CNSD, TFP, Grounding, Ground Check.
