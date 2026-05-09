# PRODUCT_BACKEND.md — ATOMS-Maintenance

## Backend Product Purpose

The ATOMS-Maintenance backend is an operational API platform serving the maintenance management system for AirNav Indonesia Surabaya. It handles **maintenance-specific** workflows only — Work Orders, equipment inspections, ground checks, grounding inspections, reporting, and logbook management.

**It is NOT responsible for:** user account management, employee master data, shift/roster scheduling, leave requests, or shift swaps. Those belong to `atoms-rostering`.

---

## Core Modules

### 1. Dashboard API
- **Purpose:** Aggregate and serve real-time operational metrics to the frontend dashboard.
- **Data Sources:** Work orders (open/in-progress count), trouble equipment, shift checklist status, current shift info.
- **Shift Data:** Read from rostering (cached) — current shift, personnel on duty.
- **Key Metrics:** Open WOs, pending reports, abnormal equipment count, checklist completion %.

### 2. Work Order Lifecycle
- **Purpose:** Track maintenance tasks from creation through completion.
- **State Machine:** `open` → `in_progress` → `pending` → `closed`
- **Types:** `shift` (all shift personnel) and `personal` (specific technician).
- **Capture:** Start/end times, completion status, obstacles (kendala), suggestions (usulan), manager feedback.
- **Personnel:** Snapshot of assigned personnel with role labels and signatures.

### 3. CNSD Inspections (EQ-1)
- **Purpose:** Digitize the daily equipment readiness checklist (EQ-1 form) for CNSD division.
- **Structure:** Category → Sections → Rows. Each row captures equipment name, status (Normal/Tidak Normal), and dynamic fields (server_aktif, freq, etc.).
- **Categories:** 13 equipment categories (VCCS, Radar, MSSR, ATC System, etc.). Only `CNSD-001` active in MVP.

### 4. TFP Performance Checks (AOB Ground)
- **Purpose:** Digitize the performance check forms for TFP supporting facilities.
- **Structure:** Category → Measurements (panel readings) + Facilities (condition checks).
- **Categories:** 8 equipment categories. Only `TFP-001 (AOB Lantai Ground)` active in MVP.

### 5. Ground Check
- **Purpose:** Track meter readings for navigation and communication equipment.
- **Data:** Equipment name, category (Navigation/Communication), frequency, status, checker, timestamp.

### 6. Grounding Inspections
- **Purpose:** Record grounding system inspections per PUIL 2011/SNI/IEC standards.
- **Structure:** Report → Visual inspection items (6 standard checks) + Measurement items (resistance readings).
- **Compliance:** Standards include ≤ 1 Ω for ground resistance, continuity testing.

### 7. Maintenance Reporting
- **Purpose:** Monthly and periodic operational reports with approval workflow.
- **Types:** kondisi_fasilitas, evaluasi_kinerja, laporan_kerusakan, riwayat_pemeliharaan.
- **Workflow:** `draft` → `pending_manager` → `final` or `rejected`.
- **Approval:** Manager reviews and approves/rejects with reason.

### 8. Logbook Archives
- **Purpose:** Monthly logbook PDF upload and retrieval per division.
- **Data:** Division (CNSD/TFP), month, year, file metadata, uploader info.

---

## Division-Based Organization

All maintenance activities are organized by division:

| Division | Full Name | Equipment Examples |
|----------|-----------|-------------------|
| **CNSD** | Communications, Navigation, Surveillance & Data | VCCS, CDU, MSSR, DVOR, Localizer, Glide Path, ATIS |
| **TFP** | Teknik Fasilitas Penunjang (Supporting Technical Facilities) | UPS, AC, Panel COS, Panel ATS, Genset |

---

## Shift Context

All maintenance activities occur within a shift context:

| Shift | Time | Notes Code |
|-------|------|------------|
| Pagi | 07:00 – 13:00 | P |
| Siang | 13:00 – 19:00 | S |
| Malam | 19:00 – 07:00 | M |

Work orders, inspections, and ground checks are always tied to a specific shift and date.

---

## Expected Backend Capabilities

1. **RESTful API** — All endpoints follow REST conventions with JSON responses.
2. **RBAC** — Role-based access control with 6 roles (Admin, Manager, 2 Supervisors, 2 Teknisi).
3. **Validation** — All inputs validated via Laravel Form Request classes.
4. **Audit Trail** — `created_by`, `created_at`, `updated_at` on every record.
5. **Pagination** — Standard cursor/offset pagination for list endpoints.
6. **File Upload** — Logbook PDFs stored via Laravel filesystem (local/S3).
7. **Soft Deletes** — Records are never truly deleted; soft-deleted with `deleted_at`.

---

## Observed from atoms-rostering
- Provides extensive Notification, Shift Request, and complex Roster building features.
- Focus is on "who is working when" — operational readiness.

## Proposed for atoms-maintenance
- Focus on "what maintenance was done and by whom" — operational recording.
- Extend scope to Work Orders and Daily Inspections, which are core to maintenance.

## Needs Verification
- How much of the Shift Request logic (leave/swapping) is needed for V1.
- Whether maintenance needs its own notification system or piggybacks on rostering's.

## Do Not Copy Directly
- Avoid copying test scripts, unused services, or roster management features from rostering.
