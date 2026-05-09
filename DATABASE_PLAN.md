# DATABASE_PLAN.md — atoms-maintenance

## Target Database & Tech Stack

- **Framework:** PHP 8.x (Laravel)
- **Engine:** PostgreSQL
- **Environment Variables:** All DB credentials via `.env` (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). Hardcoding is **strictly prohibited**.

---

## Design Principles

1. **Maintenance-only data.** This database stores maintenance-specific entities. User accounts and roster data live in atoms-rostering.
2. **Reference rostering users by ID.** Tables reference `rostering_user_id` (integer FK conceptually pointing to rostering's `users.id`). No local password storage.
3. **Audit everything.** Every table includes `created_by`, `created_at`, `updated_at`. Soft deletes where appropriate.
4. **Normalize.** Avoid JSON blobs for structured data. Use relational tables for repeating sections (e.g., EQ-1 rows).
5. **Match frontend types.** Column names and structures align with `frontend_atoms-maintenance/src/types/index.ts`.

---

## Entity Relationship Diagram (Proposed)

```
┌─────────────────┐     ┌──────────────────────┐     ┌─────────────────────┐
│ local_users     │────▶│ work_orders          │────▶│ work_order_personnel│
│ (synced/cached  │     │                      │     └─────────────────────┘
│  from rostering)│     │ wo_number            │
└────────┬────────┘     │ wo_type (shift/pers) │     ┌─────────────────────┐
         │              │ division (CNSD/TFP)  │────▶│ work_order_outputs  │
         │              │ status (state machine)│     └─────────────────────┘
         │              └──────────────────────┘
         │
         ├──────────────▶ cnsd_meter_readings ──▶ cnsd_sections ──▶ cnsd_section_rows
         │
         ├──────────────▶ tfp_performance_checks ──▶ tfp_measurements
         │                                        ──▶ tfp_facilities
         │
         ├──────────────▶ ground_check_readings
         │
         ├──────────────▶ grounding_reports ──▶ grounding_visual_items
         │                                  ──▶ grounding_measurement_items
         │
         ├──────────────▶ maintenance_reports
         │
         └──────────────▶ logbooks
```

---

## Proposed Tables

### 1. `local_users` — Cached User Profiles

> **Not a source of truth.** Synced/cached from atoms-rostering. Used for FK references and display names.

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | Local auto-increment |
| rostering_user_id | INTEGER UNIQUE | Maps to rostering `users.id` |
| name | VARCHAR(255) | Display name |
| email | VARCHAR(255) | Email address |
| role | VARCHAR(50) | `Admin`, `Manager Teknik`, `Supervisor CNSD`, `Supervisor TFP`, `Teknisi CNSD`, `Teknisi TFP` |
| division | VARCHAR(20) NULL | `CNSD`, `TFP`, `Management`, or NULL |
| is_active | BOOLEAN DEFAULT true | |
| synced_at | TIMESTAMP | Last sync from rostering |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

### 2. `work_orders` — Maintenance Work Orders

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| wo_number | VARCHAR(50) UNIQUE | Format: `WO-{DIV}-{DD}-{MM}-{YYYY}-{SEQ}` |
| wo_type | VARCHAR(20) | `shift` (all personnel) or `personal` (specific technician) |
| division | VARCHAR(10) | `CNSD` or `TFP` |
| shift_type | VARCHAR(10) | `pagi`, `siang`, `malam` |
| shift_date | DATE | |
| description | TEXT | |
| status | VARCHAR(20) | `ongoing` → `on_hold` → `completed` |
| manager_id | INTEGER FK → local_users | |
| supervisor_id | INTEGER FK → local_users | |
| assigned_technician_id | INTEGER FK → local_users NULL | For `personal` WO only |
| manager_name_snapshot | VARCHAR(255) | Frozen name at creation time |
| supervisor_name_snapshot | VARCHAR(255) | Frozen name at creation time |
| start_time | TIME NULL | When work started |
| end_time | TIME NULL | When work ended |
| completion_status | VARCHAR(30) NULL | `selesai`, `belum_selesai_dilanjut`, `tidak_bisa` |
| notes_kendala | TEXT NULL | Issues/obstacles |
| notes_usulan | TEXT NULL | Suggestions |
| notes_pemberi_tugas | TEXT NULL | Manager feedback |
| created_by | INTEGER FK → local_users | |
| closed_at | TIMESTAMP NULL | |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP NULL | Soft delete |

---

### 3. `work_order_personnel` — WO Personnel Assignments

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| work_order_id | INTEGER FK → work_orders | |
| user_id | INTEGER FK → local_users | |
| role_label | VARCHAR(50) | `Teknisi 1`, `Teknisi 2`, etc. |
| signature_url | VARCHAR(255) NULL | |
| created_at | TIMESTAMP | |

---

### 4. `work_order_outputs` — WO Output Types

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| work_order_id | INTEGER FK → work_orders | |
| output_type | VARCHAR(30) | `meter_reading`, `status_peralatan`, `logbook`, `other` |
| output_other | TEXT NULL | Description when type is `other` |

---

### 5. `cnsd_categories` — CNSD Equipment Categories

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| code | VARCHAR(20) UNIQUE | `CNSD-001`, `CNSD-002`, etc. |
| name | VARCHAR(255) | |
| location | VARCHAR(255) | |
| is_active_mvp | BOOLEAN DEFAULT false | |
| sort_order | INTEGER | |

---

### 6. `cnsd_meter_readings` — CNSD EQ-1 Form Submissions

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| category_id | INTEGER FK → cnsd_categories | |
| shift_type | VARCHAR(10) | `pagi`, `siang`, `malam` |
| shift_date | DATE | |
| checked_by | INTEGER FK → local_users | |
| overall_status | VARCHAR(10) | `normal` or `abnormal` |
| notes | TEXT NULL | |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

### 7. `cnsd_sections` — EQ-1 Form Sections

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| meter_reading_id | INTEGER FK → cnsd_meter_readings | |
| section_title | VARCHAR(255) | |
| sort_order | INTEGER | |

---

### 8. `cnsd_section_rows` — EQ-1 Section Rows

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| section_id | INTEGER FK → cnsd_sections | |
| row_no | INTEGER | Display order |
| equipment | VARCHAR(255) | Equipment name |
| status | VARCHAR(20) | `Normal` or `Tidak Normal` |
| keterangan | TEXT | Remarks |
| server_aktif | VARCHAR(50) NULL | Dynamic field |
| dual_state | VARCHAR(50) NULL | Dynamic field |
| type | VARCHAR(50) NULL | Dynamic field |
| dual_status | VARCHAR(50) NULL | Dynamic field |
| freq | VARCHAR(50) NULL | Dynamic field |
| tx_operasi | VARCHAR(50) NULL | Dynamic field |
| channel_aktif | VARCHAR(50) NULL | Dynamic field |
| server_state | VARCHAR(50) NULL | Dynamic field |
| workstation_state | VARCHAR(50) NULL | Dynamic field |

---

### 9. `tfp_categories` — TFP Equipment Categories

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| code | VARCHAR(20) UNIQUE | `TFP-001`, etc. |
| name | VARCHAR(255) | |
| location | VARCHAR(255) | |
| is_active_mvp | BOOLEAN DEFAULT false | |
| sort_order | INTEGER | |

---

### 10. `tfp_performance_checks` — TFP AOB Form Submissions

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| category_id | INTEGER FK → tfp_categories | |
| shift_type | VARCHAR(10) | |
| shift_date | DATE | |
| checked_by | INTEGER FK → local_users | |
| overall_status | VARCHAR(20) | `ok` or `ada_masalah` |
| notes | TEXT NULL | |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

### 11. `tfp_measurements` — AOB Measurement Rows

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| performance_check_id | INTEGER FK → tfp_performance_checks | |
| row_no | INTEGER | |
| label | VARCHAR(255) | Measurement label |
| panel_cos_a03_input | VARCHAR(50) NULL | |
| panel_cos_a03_output | VARCHAR(50) NULL | |
| panel_cos_a03_active | BOOLEAN NULL | |
| panel_ats_a12_input | VARCHAR(50) NULL | |
| panel_ats_a12_output | VARCHAR(50) NULL | |
| panel_ats_a12_active | BOOLEAN NULL | |
| ups_tescom_a_input | VARCHAR(50) NULL | |
| ups_tescom_a_output | VARCHAR(50) NULL | |
| ups_tescom_a_active | BOOLEAN NULL | |
| ups_tescom_b_input | VARCHAR(50) NULL | |
| ups_tescom_b_output | VARCHAR(50) NULL | |
| ups_tescom_b_active | BOOLEAN NULL | |

---

### 12. `tfp_facilities` — Facility Condition Items

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| performance_check_id | INTEGER FK → tfp_performance_checks | |
| name | VARCHAR(255) | Facility name |
| condition | VARCHAR(20) | `Baik` or `Tidak Baik` |
| note_default | TEXT NULL | |
| keterangan | TEXT | Remarks |

---

### 13. `ground_check_readings` — Meter Reading Equipment

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| equipment_name | VARCHAR(255) | |
| category | VARCHAR(20) | `Navigation` or `Communication` |
| frequency | VARCHAR(50) NULL | |
| status | VARCHAR(20) | `Normal` or `Tidak Normal` |
| last_checked | TIMESTAMP | |
| checked_by | INTEGER FK → local_users | |
| shift_date | DATE | |
| shift_type | VARCHAR(10) | |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

### 14. `grounding_reports` — Grounding Inspection Reports

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| kantor_unit_kerja | VARCHAR(255) | e.g., `Cabang Surabaya` |
| nama_peralatan | VARCHAR(255) | Equipment name |
| lokasi_peralatan | VARCHAR(255) | |
| tanggal | DATE | Inspection date |
| dibuat_oleh | INTEGER FK → local_users | |
| disetujui_oleh | INTEGER FK → local_users | |
| lokasi_kerja | VARCHAR(255) | |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

### 15. `grounding_visual_items` — Visual Inspection Checklist

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| grounding_report_id | INTEGER FK → grounding_reports | |
| item_no | INTEGER | |
| name | VARCHAR(255) | e.g., `Terminal Udara` |
| ketersediaan | VARCHAR(20) | `Ada`, `Tidak Ada`, or empty |
| kondisi | VARCHAR(20) | `Baik`, `Tidak Baik`, or empty |
| catatan | TEXT | |

---

### 16. `grounding_measurement_items` — Resistance Measurements

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| grounding_report_id | INTEGER FK → grounding_reports | |
| item_no | INTEGER | |
| name | VARCHAR(255) | |
| standard | VARCHAR(50) | e.g., `≤ 1 Ω` |
| kondisi | VARCHAR(20) | `Baik` or `Tidak Baik` |
| hasil_pengukuran | VARCHAR(100) | Measurement result |

---

### 17. `maintenance_reports` — Monthly/Periodic Reports

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| title | VARCHAR(255) | |
| report_type | VARCHAR(30) | `kondisi_fasilitas`, `evaluasi_kinerja`, `laporan_kerusakan`, `riwayat_pemeliharaan` |
| description | TEXT NULL | |
| related_equipment | VARCHAR(255) NULL | |
| facility | VARCHAR(10) | `CNSD`, `TFP`, or `ALL` |
| content | JSONB NULL | Flexible structured content |
| narrative | TEXT NULL | |
| file_url | VARCHAR(500) NULL | |
| status | VARCHAR(20) | `draft` → `pending_manager` → `final` / `rejected` |
| submitted_at | TIMESTAMP NULL | |
| reviewed_by | INTEGER FK → local_users NULL | |
| reviewed_at | TIMESTAMP NULL | |
| reject_reason | TEXT NULL | |
| period_start | DATE NULL | |
| period_end | DATE NULL | |
| created_by | INTEGER FK → local_users | |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP NULL | |

---

### 18. `logbooks` — Monthly Logbook Archives

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| division | VARCHAR(10) | `CNSD` or `TFP` |
| month | INTEGER | 1–12 |
| year | INTEGER | |
| title | VARCHAR(255) | |
| file_path | VARCHAR(500) | |
| file_name | VARCHAR(255) | |
| file_size | BIGINT | Bytes |
| file_type | VARCHAR(50) | MIME type |
| notes | TEXT NULL | |
| uploaded_by | INTEGER FK → local_users | |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

### 19. `trouble_equipment` — Dashboard Trouble Equipment

| Column | Type | Notes |
|--------|------|-------|
| id | SERIAL PK | |
| equipment_name | VARCHAR(255) | |
| parameter | VARCHAR(255) | Issue description |
| shift | VARCHAR(10) | `pagi`, `siang`, `malam` |
| reported_by | INTEGER FK → local_users | |
| division | VARCHAR(10) | `CNSD` or `TFP` |
| resolved | BOOLEAN DEFAULT false | |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

### 20. `dashboard_checklist` — Shift Checklist Items

| Column | Type | Notes |
|--------|------|-------|
| id | VARCHAR(50) PK | e.g., `cnsd-eq1` |
| label | VARCHAR(255) | |
| division | VARCHAR(10) | `CNSD` or `TFP` |
| is_active | BOOLEAN DEFAULT false | Active in MVP |
| route | VARCHAR(255) NULL | Frontend route |
| sort_order | INTEGER | |

---

## Indexes (Proposed)

- `work_orders`: index on `(division, shift_date)`, `(status)`, `(created_by)`
- `cnsd_meter_readings`: index on `(category_id, shift_date)`
- `tfp_performance_checks`: index on `(category_id, shift_date)`
- `maintenance_reports`: index on `(status)`, `(facility)`, `(created_by)`
- `grounding_reports`: index on `(tanggal)`, `(dibuat_oleh)`
- `ground_check_readings`: index on `(shift_date)`, `(category)`

---

## Notes

### Observed from atoms-rostering
- Rostering uses `User` (auth) → `Employee` (operational) with 1:1 relationship
- Shift definitions: 3 records in `shifts` table (pagi, siang, malam) with start/end times
- ShiftAssignment links employee → roster_day with notes (P/S/M = working, L/CT/OFF = non-working)

### Proposed for atoms-maintenance
- Use `local_users` as a cached mirror of rostering users (synced periodically or on first access)
- All FK references point to `local_users.id`, not directly to rostering's DB
- This allows the maintenance DB to be fully self-contained and portable

### Needs Verification
- Exact fields for CNSD EQ-1 sections vary by equipment category — may need a more flexible schema
- Whether `content` JSONB in `maintenance_reports` is sufficient or needs relational breakdown
- Dashboard checklist items — static config table vs. computed at runtime

### Do Not Copy Directly
- Do not replicate rostering's `roster_periods`, `roster_days`, `shift_assignments`, `shift_requests`, `leave_requests` tables
- Maintenance only needs to *read* shift/roster data from rostering, not store it locally
