# NIXI Auction Portal - Technical Handoff Documentation

## 1) Purpose and Scope
This document is a technical handoff for the NIXI Auction Portal so another team can understand, operate, and extend the system safely.

It covers:
- architecture and module boundaries
- user and admin flows (end-to-end)
- verification and security controls (OTP, blacklist, session auth, payments)
- integrations (PayU, SMS, SMTP, PAN verification)
- route inventory
- database schema inventory (migrated + legacy/externally provisioned tables)
- operational runbook and caveats

---

## 2) Tech Stack and Runtime
- Framework: Laravel (PHP)
- Rendering: server-side Blade templates
- Auth pattern: custom session-based auth and role checks (middleware aliases), not standard guard-only flow in controllers
- DB access style: mostly `DB::table` / query builder (limited Eloquent model-centric pattern)
- Async/background: one artisan command for payment-default promotion (`emd:process-defaults`)

Key files:
- `bootstrap/app.php`
- `routes/web.php`
- `routes/console.php`

---

## 3) System Architecture (High Level)
### 3.1 Layers
- **Presentation layer**: Blade views under `resources/views`
  - `portal/*` -> public/registration/login
  - `user/*` -> bidder area
  - `admin/*` -> admin console
  - `invoices/*`, `payments/*` -> document/payment UX
- **Application layer**: controllers under `app/Http/Controllers`
- **Domain/service layer**: cross-cutting logic under `app/Services`
- **Persistence layer**: MySQL tables (mixed source: migrations + legacy tables)

### 3.2 Core services
- `App\Services\PayuService` -> payment hash/request/response handling
- `App\Services\BulkSmsService` -> mobile OTP SMS (MyToday BulkPush)
- `App\Services\BlacklistService` -> blocklist and identity/fingerprint checks
- `App\Services\EmdAuctionService` -> top bidder default and promotion logic
- `App\Services\AppSettingsService` -> configurable settings access

---

## 4) Access Control and Roles
### 4.1 Roles used
- `user` -> bidder/customer
- `admin` -> platform operator

### 4.2 Middleware boundaries
- `session.auth` protects user routes
- `session.admin` protects admin routes

Primary route grouping is in `routes/web.php`.

### 4.3 Session model
Controllers read/write session keys directly (example: user id, role, OTP keys, UI section state for profile flows).

---

## 5) Full Business Flows

## 5.1 Registration Flow
Controller: `app/Http/Controllers/RegistrationController.php`

Steps:
1. User opens `/register`.
2. Email OTP generation and verification.
3. Mobile OTP generation and verification.
4. PAN verification through external IdFy API (task create + status poll).
5. Registration payload stored as pending data.
6. Registration payment initiated (PayU).
7. On successful callback:
   - user and registration records are finalized
   - payment status persisted
   - account may enforce password reset flow depending on flags

Validation and controls:
- duplicate email/mobile checks
- identity blacklist checks (email/mobile/pan/device/ip)
- OTP TTL checks (10 minutes)

---

## 5.2 Authentication and Session Login
Controller: `app/Http/Controllers/AuthController.php`

Behavior:
- validates user credentials
- checks blocked states/blacklist constraints
- supports legacy plaintext password migration path to hashed password
- regenerates session on login
- logout invalidates and regenerates CSRF/session state

---

## 5.3 Forgot Password
Controller: `app/Http/Controllers/PasswordController.php`

Flow:
1. user requests OTP on `/forgot-password`
2. OTP stored in session with timestamp
3. OTP verification
4. reset token and password update flow

Security:
- OTP expiry validation (10 min)
- tokenized password reset page

---

## 5.4 User Auction Journey
Controller: `app/Http/Controllers/UserAuctionController.php`

User capabilities:
- list auctions (`/user/auctions`) by state/view filters
- view auction details and bid history
- join auction (`/join`)
- watch/unwatch auction
- place bids
- view my bids

Bid controls include:
- auction state validation
- participant validation
- rate/attempt control patterns
- anti-sniping (auto extend near close conditions)
- bid logging

---

## 5.5 Auction Status Lifecycle
Mostly coordinated by:
- `UserAuctionController` status update helpers executed during request cycles
- admin auction actions in `AdminController`

Lifecycle terms in implementation:
- `upcoming`
- `active`
- `closed`
- `cancelled` (and `auction_outcome`)

Outcome and payment windows:
- winner assignment and rank/state fields on auction
- payment window expiration processing (see console command section)

---

## 5.6 Payment Flows
Controllers:
- `app/Http/Controllers/PaymentController.php`
- `app/Http/Controllers/Api/V1/PaymentApiController.php`

Integrated gateway:
- PayU

Flow types:
- registration payment
- auction payment (winner)
- auction participation payment
- wallet topup routes exist but are currently disabled in `routes/web.php`

Callback routes:
- `/payu/auction/success`, `/payu/auction/failure`
- `/payu/registration/success`, `/payu/registration/failure`

Security:
- hash validation on callback
- duplicate callback resilience/idempotent style checks
- CSRF exemptions for gateway callbacks configured in bootstrap

---

## 5.7 Profile Verification Flows (User)
Controller: `UserAuctionController`
View: `resources/views/user/profile.blade.php`

### Supported profile updates
- password update via OTP to registered email
- email update via OTP to new email
- mobile update via OTP to new mobile (SMS)

### OTP storage pattern
Session keys (examples):
- `profile_pwd_otp_{userId}`
- `profile_email_otp_{md5(newEmail)}`
- `profile_mobile_otp_{md5(newMobile)}`

TTL: 10 minutes

### UX continuity behavior
- active section is stored in `profile_ui_section`
- section remains expanded while verification is in progress
- submitted form values are preserved using `withInput()` + pending session values
- section state is cleared on successful completion

---

## 5.8 Notifications, Messaging, and Support
Controllers:
- `AdminNotificationController`
- `UserAuctionController`
- `Api/V1/SupportApiController`

Capabilities:
- admin sends messages to filtered users
- per-recipient thread and replies
- user compose/reply flow
- support tickets list/create via API and admin UI integrations

Messaging tables:
- `admin_messages`
- `admin_message_recipients`
- `admin_message_replies`

---

## 5.9 Invoice Generation
Controller: `app/Http/Controllers/InvoiceController.php`

Capabilities:
- registration invoice PDF
- auction invoice PDF
- admin view/download for a user

Storage side effect:
- generated files saved under `storage/app/invoices/<registration_ref>/...`

---

## 6) Integrations (Technical Contracts)

## 6.1 SMS OTP (MyToday BulkPush)
Service: `app/Services/BulkSmsService.php`
Config: `config/sms.php`

Endpoint:
- default `https://bulkpush.mytoday.com/BulkSms/SingleMsgApi`

Payload fields used:
- `feedid`
- `username`
- `password`
- `To` (now formatted with `91` prefix for Indian MSISDN)
- `Text`
- `templateid`
- `senderid`
- optional `entityid`
- optional `async`

Behavior:
- if disabled (`SMS_BULK_ENABLED=false`), delivery is bypassed and dev paths may expose OTP in debug mode messages
- logs request outcomes and gateway body snippets in `storage/logs/laravel.log`

Operational note:
- gateway HTTP 200 is not always delivery success; use provider TID in response to trace DLT/carrier delivery state

---

## 6.2 Email (SMTP)
Used in registration, profile, winner notifications, and admin messaging.

Configuration pattern:
- active SMTP settings can be loaded dynamically from DB `email_settings` and applied at runtime.

---

## 6.3 PayU Payment Gateway
Service: `app/Services/PayuService.php`

Includes:
- request payload construction
- hash signing
- callback hash verification
- transaction status updates and reference mapping

---

## 6.4 PAN Verification (IdFy)
Controller: `RegistrationController`

Pattern:
- create async PAN verification task
- poll status endpoint
- interpret response to gate registration progression

---

## 7) API and Route Inventory
Primary route file: `routes/web.php`

## 7.1 Public routes
- `/`
- `/login`, `/logout`
- `/forgot-password/*`
- `/register/*`
- `/payments/registration/*`
- `/payu/*` callback endpoints

## 7.2 User routes (`session.auth`)
- auctions index/detail/join/watch/bid/status
- bids history
- profile + OTP update endpoints (password/email/mobile)
- notifications and compose/reply
- invoices
- auction payment and participation payment initiation
- API v1 under `/api/v1/*` for auctions, bids, notifications, payments, support tickets

## 7.3 Admin routes (`session.admin`)
- dashboard and operations
- bids overview
- notifications and threaded replies
- auction CRUD/lifecycle actions
- completed auctions
- user management/blocking
- settings and uploads
- support tickets
- blacklist and audit logs
- admin invoice endpoints

---

## 8) Database Schema Documentation

Important: this project has a **mixed schema source**:
- A) tables created/altered in `database/migrations`
- B) legacy/core business tables referenced in code but not defined in visible migrations

The second category must be exported from production/staging DB (`SHOW CREATE TABLE`) for complete onboarding.

## 8.1 Tables from migrations (authoritative in repo)

### 8.1.1 Framework tables
- `users` (base: id, name, email unique, email_verified_at, password, remember_token, timestamps)
- `password_reset_tokens` (email PK, token, created_at)
- `sessions` (session payload store with `user_id`, ip, user_agent)
- `cache`, `cache_locks`
- `jobs`, `job_batches`, `failed_jobs`

### 8.1.2 User and auction extensions
- `users` extra columns via migrations:
  - `mobile`
  - `wallet_balance` decimal(14,2)
  - `is_blocked` bool
  - `default_count` unsigned int
  - `emd_multiplier` decimal(6,2)
  - `must_reset_password` bool
  - `last_login_at` timestamp
  - `last_login_ip` varchar(45)

- `auctions` extra columns via migrations:
  - `emd_amount` decimal(14,2)
  - `payment_window_expires_at` timestamp
  - `winner_rank` tinyint unsigned
  - `top_bidders_json` json
  - `auction_outcome` varchar(20)
  - `cancelled_at` timestamp

### 8.1.3 Financial and participation tables
- `transactions`
  - key: id
  - fields: `user_id`, `type`, `amount`, `reference_type`, `reference_id`, `status`, `remarks`, timestamps
  - indexes: (`user_id`,`created_at`), (`reference_type`,`reference_id`)

- `wallet_topups`
  - key: id
  - fields: `user_id`, `transaction_id` unique, `amount`, `status`, `gateway_transaction_id`, `gateway_response`, timestamps
  - indexes: (`user_id`,`status`)

- `auction_participants`
  - key: id
  - fields: `auction_id`, `user_id`, `emd_locked`, `locked_emd_amount`, `status`, `joined_at`, timestamps
  - unique: (`auction_id`,`user_id`)
  - index: (`auction_id`,`status`)

### 8.1.4 Engagement/support/audit tables
- `watchlists`
  - unique (`user_id`,`auction_id`)
  - index (`auction_id`)

- `support_tickets`
  - `user_id`, `subject`, `message`, `status`, `priority`, `category`, `attachment_path`, `admin_reply`, `resolved_at`, timestamps
  - indexes (`user_id`,`created_at`), (`status`,`priority`)

- `blacklisted_users`
  - identity vectors: `user_id`, `email`, `mobile`, `pan_card_number`, `device_fingerprint`, `ip_address`
  - state: `reason`, `blacklisted_at`, `expires_at`, `is_active`, timestamps
  - indexes on each identity + active state

- `bid_logs`
  - `auction_id`, `user_id`, `amount`, `event_type`, ip/fingerprint, `meta`, `created_at`
  - indexes by auction/time, user/time, event_type

- `notification_logs`
  - `user_id`, `channel`, `type`, `status`, subject/message, `meta`, `sent_at`, timestamps
  - indexes by user/time, channel/status, type

- `audit_logs`
  - actor, role, action, entity, ip/fingerprint, `old_values`, `new_values`, `meta`, `created_at`
  - indexes by actor/time, entity, action

- `user_identity_change_logs`
  - `user_id`, `field_name`, `old_value`, `new_value`, `ip_address`, `created_at`
  - indexes (`user_id`,`field_name`), `created_at`

### 8.1.5 Admin messaging tables
- `admin_messages`
  - `subject`, `message`, `attachment_path`, `created_by`, timestamps

- `admin_message_recipients`
  - `message_id`, `user_id`, `email_sent_at`, `is_read`, `last_read_at`, timestamps
  - unique (`message_id`,`user_id`)
  - index (`user_id`,`is_read`)

- `admin_message_replies`
  - `message_id`, `sender_role`, `sender_user_id`, `message`, `attachment_path`, timestamps
  - index (`message_id`,`created_at`)

## 8.2 Legacy/core tables referenced in code (not defined in visible migrations)
These tables are critical and must be captured from DB DDL:
- `auctions` (core base columns not in repo migrations)
- `bids`
- `registration`
- `payment_transactions`
- `registration_payments`
- `pending_registrations`
- `settings`
- `notifications` (if used in deployed schema)
- `email_settings`

Action item for handoff:
1. export each table DDL and indexes
2. add ERD and relation map
3. verify defaults and enum/string status values match application expectations

---

## 9) Security and Verification Details

## 9.1 OTP policy
- OTP length: 6 digits
- OTP TTL: 600 seconds
- storage: session keys scoped by user id or hash of target identity
- invalid/expired OTP rejects update/verification actions

## 9.2 Blacklist policy
`BlacklistService` checks by:
- email
- mobile
- PAN
- device fingerprint
- IP address

Applied in:
- login
- registration identity checks
- profile email/mobile updates

## 9.3 Session protections
- login session regeneration
- logout invalidation and token regeneration
- middleware-gated user/admin route groups

## 9.4 Payment callback trust boundary
- callbacks validated by gateway signature/hash
- callback endpoints intentionally CSRF-exempt due to third-party POST origin

## 9.5 Auditability
Operationally relevant logs are in:
- `storage/logs/laravel.log`

Tracked records:
- auth attempts/events in `audit_logs`
- bid lifecycle in `bid_logs`
- identity changes in `user_identity_change_logs`

---

## 10) Operational Runbook

## 10.1 Required env/config areas
- application and DB connection
- SMTP (or email settings table for dynamic runtime config)
- PayU credentials and callback URL alignment
- SMS gateway credentials and switches:
  - `SMS_BULK_ENABLED`
  - `SMS_BULK_URL`
  - `SMS_BULK_FEED_ID`
  - `SMS_BULK_USERNAME`
  - `SMS_BULK_PASSWORD`
  - `SMS_BULK_TEMPLATE_ID`
  - `SMS_BULK_SENDER_ID`
  - optional `SMS_BULK_ENTITY_ID`
  - optional `SMS_BULK_ASYNC`

Post-change command:
- `php artisan config:clear`

## 10.2 Scheduled/background process
Command:
- `php artisan emd:process-defaults`

Purpose:
- promotes H2/H3 bidder when payment window expires for closed auctions pending payment

Recommendation:
- run via scheduler/cron at fixed interval in production

## 10.3 Logging and troubleshooting
- app logs: `storage/logs/laravel.log`
- SMS troubleshooting:
  - look for `BulkSms` log entries
  - inspect XML response and provider TID
  - verify DLT template/entity/sender mapping in provider panel

---

## 11) Known Caveats and Technical Debt
- Some core tables are outside migrations (portability risk).
- Hybrid auth assumptions (custom sessions + framework auth config) can confuse new maintainers.
- OTP may be exposed in debug/non-production paths when SMS is disabled.
- EMD/wallet features are partly present in code but routes are commented/disabled.
- Certain admin date handling patterns normalize to day boundaries; validate business intent before modifying.
- Runtime table creation for email settings exists in admin settings path; migration-first strategy is recommended long term.

---

## 12) Handoff Checklist for New Team
1. Confirm production DDL export for all legacy tables.
2. Produce ER diagram with cardinalities and key constraints.
3. Validate all status values used in code match DB data dictionary.
4. Validate PayU callback hashes in staging with real gateway callbacks.
5. Validate OTP flows in three modes:
   - registration email/mobile
   - profile email/mobile/password
   - forgot password
6. Validate blacklist behavior for each identity dimension.
7. Validate admin notification thread creation per recipient.
8. Validate invoice generation and storage permissions.
9. Wire and monitor `emd:process-defaults` scheduler.
10. Create runbooks for incident response (payment mismatch, OTP delivery failure, callback replay, stuck auction payment).

---

## 13) Quick File Index for Engineers
- Routes: `routes/web.php`
- Console jobs: `routes/console.php`
- User auctions/profile/notifications: `app/Http/Controllers/UserAuctionController.php`
- Registration: `app/Http/Controllers/RegistrationController.php`
- Login/auth: `app/Http/Controllers/AuthController.php`
- Password reset: `app/Http/Controllers/PasswordController.php`
- Admin operations: `app/Http/Controllers/AdminController.php`
- Admin notifications: `app/Http/Controllers/AdminNotificationController.php`
- Payments: `app/Http/Controllers/PaymentController.php`
- API v1: `app/Http/Controllers/Api/V1/*`
- SMS service: `app/Services/BulkSmsService.php`
- Blacklist service: `app/Services/BlacklistService.php`
- PayU service: `app/Services/PayuService.php`
- Migrations: `database/migrations/*`

