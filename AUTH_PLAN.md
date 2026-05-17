# AUTH_PLAN.md — atoms-maintenance

## Authentication Strategy

### Two-Phase Approach

| Phase | Mode | Auth Provider | Token Validation |
|-------|------|---------------|------------------|
| **Phase 1 (Dev)** | `DEV_MOCK_AUTH=true` | Local mock middleware | Accept `mock-token-{user_id}`, resolve user from seeded `local_users` table |
| **Phase 2 (Prod)** | `DEV_MOCK_AUTH=false` | atoms-rostering SSO | Validate Sanctum token against rostering's auth endpoint or shared token store |

---

## Phase 1: Mock Authentication (Development)

### How It Works

1. **Frontend** sends `Authorization: Bearer mock-token-{user_id}` with every API request.
2. **Backend** `MockAuthMiddleware` intercepts the request:
   - Checks `DEV_MOCK_AUTH` env var. If `false`, passes through to real Sanctum auth.
   - If `true`, extracts `user_id` from the token pattern.
   - Looks up user in `local_users` table.
   - Sets the authenticated user on the request (`Auth::setUser()`).
3. **All downstream controllers** see a normal authenticated user and RBAC works normally.

### Backend `.env` Configuration
```env
DEV_MOCK_AUTH=true
```

### Frontend `.env` Configuration
```env
VITE_DEV_MOCK_AUTH=true
```

### Frontend Behavior When `VITE_DEV_MOCK_AUTH=true`
1. Login page shows a **role selector** instead of email/password.
2. Selecting a role injects the corresponding mock user + mock token into `AuthContext`.
3. All API calls include the mock token in the `Authorization` header.
4. No real HTTP call is made to `/auth/login`.

### Mock Users (Seeded)

| ID | Name | Email | Role |
|----|------|-------|------|
| 1 | Dudik Fahrudin | dudik@airnav.co.id | Manager Teknik |
| 2 | Moch. Ichsan | ichsan@airnav.co.id | Supervisor CNSD |
| 3 | Fajar Kusuma W | fajar@airnav.co.id | Supervisor TFP |
| 4 | Khoirul M.A | khoirul@airnav.co.id | Teknisi CNSD |
| 5 | Iqbal Mustika | iqbal@airnav.co.id | Teknisi TFP |
| 6 | Argo Pragolo | argo@airnav.co.id | Teknisi CNSD |
| 7 | Admin System | admin@airnav.co.id | Admin |

---

## Phase 2: SSO Integration (Production)

### Options Under Consideration

| Option | How It Works | Pros | Cons |
|--------|-------------|------|------|
| **A. Shared Sanctum Token** | Both apps validate against the same `personal_access_tokens` table in rostering's DB | Simple, native Laravel | Tight DB coupling |
| **B. Token Proxy** | Maintenance backend calls rostering's `/auth/verify-token` on every request | Loose coupling | Latency per request |
| **C. JWT Shared Secret** | Rostering issues JWTs with a shared secret; maintenance validates locally | Stateless, fast | Requires JWT setup |

### Recommended: Option B (Token Proxy) with Caching

1. Frontend authenticates against rostering's `/auth/login` and receives a Sanctum token.
2. Maintenance backend receives the same token in `Authorization: Bearer {token}`.
3. Maintenance middleware calls rostering's `GET /auth/me` with the token to validate + get user data.
4. Response is cached locally (TTL: 5 minutes) to reduce round-trips.
5. User data is upserted into `local_users` for FK references.

### Needs Verification
- Whether rostering will expose a dedicated `/auth/verify-token` endpoint for cross-service use.
- Whether a shared PostgreSQL database with read-only access for maintenance is acceptable.
- OAuth2 / OpenID Connect viability within AirNav's infrastructure.

---

## Role-Based Access Control (RBAC)

### Roles for ATOMS-Maintenance

| Role | Code | Permissions |
|------|------|-------------|
| **Admin** | `Admin` | Full system access, user management, all divisions |
| **Manager Teknik** | `Manager Teknik` | View/approve all, manage both divisions |
| **Supervisor CNSD** | `Supervisor CNSD` | Create WOs, manage CNSD inspections, submit reports |
| **Supervisor TFP** | `Supervisor TFP` | Create WOs, manage TFP inspections, submit reports |
| **Teknisi CNSD** | `Teknisi CNSD` | Execute WOs, fill CNSD forms |
| **Teknisi TFP** | `Teknisi TFP` | Execute WOs, fill TFP forms |

### Role Differences from Rostering

| Rostering Roles | Maintenance Roles | Notes |
|-----------------|-------------------|-------|
| Admin | Admin | Same |
| Cns | Teknisi CNSD, Supervisor CNSD | Split into supervisor + technician |
| Support | Teknisi TFP, Supervisor TFP | Split into supervisor + technician |
| Manager Teknik | Manager Teknik | Same |
| General Manager | — | Not used in maintenance (may map to Manager Teknik) |

### RBAC Implementation

```php
// Middleware: CheckRole
Route::middleware('role:Manager Teknik,Supervisor CNSD')
    ->group(function () {
        // CNSD management routes
    });

// In controller:
$this->authorize('update', $workOrder); // Laravel Policy
```

- Use **middleware** for route-level role checks.
- Use **Policies** for resource-level authorization (e.g., "can this user update this specific work order?").
- Use **Gates** for feature-level checks (e.g., "can this user access TFP module?").

---

## Security Considerations

1. **Passwords:** Not stored in maintenance DB. Authentication is delegated to rostering.
2. **Token Storage:** Frontend stores token in `sessionStorage` (bukan localStorage). Key: `auth_token`. Session berakhir saat tab browser ditutup — sesuai dengan delegated auth pattern.
3. **CORS:** Configure Laravel CORS to allow requests from `localhost:5173` (Vite dev server) and production domain.
4. **HTTPS:** Enforce in production.
5. **Rate Limiting:** Apply to auth endpoints (already built into Laravel).
6. **Input Sanitization:** All inputs validated through Form Request classes.

---

## Observed from atoms-rostering

- Uses Laravel Sanctum with `HasApiTokens` trait.
- Auth endpoints: `/login`, `/verify-token`, `/set-password`, `/forgot-password`, `/logout`, `/me`, `/change-password`.
- Role middleware: `middleware('role:Admin,Manager Teknik')`.
- Password hashing via `'password' => 'hashed'` cast.
- User model includes `is_active`, `last_login` tracking.

## Do Not Copy Directly

- Do not copy `AuthController.php` from rostering — maintenance uses a proxy/mock pattern, not direct auth.
- Do not implement password management in maintenance — that's rostering's responsibility.
- Do not store passwords in maintenance DB.
