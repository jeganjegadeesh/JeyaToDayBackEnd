# AJ Project — Laravel Backend

REST API backend for the AJ Retail Ice Cream Distribution Management System.
Built for **Laravel 11**, PHP 8.2+, MySQL, with Sanctum token auth for the Flutter app.

## What's included

- `app/Models` — Company, User, Product, Retailer, GiveStock(+Items), ReturnStock(+Items),
  CashPayment, Bill(+Items), RawMaterial, Expense, RetailerLoan
- `app/Traits/HasAudit.php` — shared logic for `company_id` auto-scoping, `created_by` /
  `updated_by` stamping, and the `is_deleted` soft-delete flag (per the DB conventions in the spec)
- `app/Services/BillCalculationService.php` — implements the exact bill formula from section 7
  (Given − Returned → × Rate → Subtotal → − Commission → − Cash Paid → Grand Total)
- `app/Services/CashReportService.php` — implements the Cash Report formula from the additional
  requirements doc
- `app/Http/Controllers/Api/*` — one controller per module/screen in the spec
- `app/Http/Middleware/EnsureRole.php` — role gate (`admin`, `manager`, `retailer`)
- `routes/api.php` — all endpoints, grouped by role
- `database/migrations/*` — full schema, including `company_id` on every master/transaction table
  per the multi-company requirement

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# edit .env with your MySQL credentials, then:
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

This seeds one company ("AJ Ice Creams") and one admin login:

```
Phone: 9999999999
Password: 123456
```

## Auth flow

1. `POST /api/login` with `{ phone_number, password }` → returns `{ token, user }`
2. Send `Authorization: Bearer {token}` on every subsequent request.
3. Every new user/retailer created via the API gets the default password `123456`
   (per spec section 2.2) — send them a reset via `POST /api/users/{id}/reset-password`.

## Role model

| Role | Access |
|---|---|
| `admin` | everything, including delete/restore |
| `manager` | same screens as admin, **no delete** |
| `retailer` | read-only `/api/my/*` endpoints, scoped to their own retailer record |

## Key business rules encoded

- **Soft delete only**: `DELETE` endpoints never remove rows — they flip `is_deleted = 1`.
  Only `admin` role can call them (see `routes/api.php` — the delete routes live in the
  `role:admin` group only).
- **Multi-company**: every master/transaction model auto-filters by the logged-in user's
  `company_id` via a global Eloquent scope in `HasAudit`. Company details (logo, GST, address,
  phone) should be pulled into PDFs/prints from `$user->company`.
- **Bill generation is idempotent per transaction**: `give_stocks`, `return_stocks`, and
  `cash_payments` carry an `is_billed` flag. Generating a bill only picks up rows where
  `is_billed = 0`, then marks them billed — so the next bill for that retailer only covers new
  transactions, matching the "Multiple Bills on Same Day" and "Weekly/Monthly Billing" scenarios
  in the spec.
- **Cash Report**: `Opening Balance + Cash Payments − Raw Material Expenses − Retailer Loans =
  Current Balance`, filterable by today/yesterday/this week/this month/custom range.

## Suggested next steps (not included, flagged as future work in the spec)

- PDF bill/invoice generation (barryvdh/laravel-dompdf is already in `composer.json` — wire up a
  `BillPdfController` using a Blade view for the print layout in section 3.8)
- Loan repayment tracking (placeholder `repaid_amount` column already exists on `retailer_loans`)
- Push notifications for stock/bill events
