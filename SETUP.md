# WorkshopX Admin (Web) — Setup Guide

This PHP admin web app shares your real Supabase backend (confirmed against
your Schema Visualizer, Jan 2026) — the same one your FlutterFlow app uses.

## 1. What's included

```
workshopx-admin/
├── config.php              ← EDIT: paste your Supabase URL + anon key
├── index.php                 (redirects to login/dashboard)
├── login.php                  Admin/Staff login (Supabase Auth + profiles)
├── logout.php
├── dashboard.php              Summary cards + recent orders (with vehicle/customer names)
├── customers.php              Customer records (add/search)
├── customer_view.php          Single customer + their vehicles
├── service_orders.php         Service order oversight (update status)
├── inventory.php              Spare parts stock (add/adjust quantity)
├── invoices.php                Invoices & payments (mark paid, revenue total)
├── surveys.php                 Equipment surveys (see note below — table doesn't exist yet)
├── users.php                   Role management via `profiles` table (admin only)
├── includes/
│   ├── Supabase.php            REST/Auth API client (cURL, no packages needed)
│   └── auth.php                Session guard / role checks
├── partials/  (header.php, sidebar.php, footer.php)
└── assets/    (css/style.css — teal WorkshopX branding, js/app.js)
```

## 2. Configure your Supabase connection

Open `config.php` and fill in:

```php
define('SUPABASE_URL', 'https://ihpwrboxokirlbxhfnbx.supabase.co'); // your project ref
define('SUPABASE_ANON_KEY', 'YOUR_SUPABASE_ANON_PUBLIC_KEY');
```

Find the anon key in **Supabase Dashboard → Project Settings → API**. It's
the same value FlutterFlow uses.

## 3. Your confirmed schema → what's wired up

| Table | Used by | Key columns used |
|---|---|---|
| `customers` | customers.php, customer_view.php | `name`, `phone`, `email`, `address`, `created_at` |
| `vehicles` | customer_view.php, dashboard.php, service_orders.php | `customer_id`, `plate_no`, `make`, `model`, `year`, `colour`, `mileage` |
| `service_orders` | service_orders.php, dashboard.php | `vehicle_id`, `mechanic_id`, `staff_id`, `status`, `created_at`, `due_date` |
| `inventory` | inventory.php, dashboard.php | `part_name`, `part_code`, `quantity`, `unit_price`, `low_stock_alert` |
| `invoices` | invoices.php, dashboard.php | `order_id`, `total_amount`, `status`, `issued_at` |
| `profiles` | login.php, users.php | `id` (= `auth.users.id`), `full_name`, `role`, `phone` |

Everything above matches your real schema exactly — no guessing needed
anymore.

## 4. Tables NOT yet wired into any page

These exist in your database but aren't used by this admin app yet. Let me
know if you'd like any of them built out:

- **`equipment_surveys`** — doesn't exist in your schema at all. The
  Equipment Surveys page (`surveys.php`) will show a friendly "not connected"
  notice until you create this table. Once created, the page works with no
  code changes needed (as long as column names roughly match).
- **`service_order_items`** — line items (parts/labour) per service order.
  Could power an itemized invoice breakdown on `service_orders.php`.
- **`repair_progress`** — mechanic progress logs with photos/notes per order.
  Could power a "repair timeline" view on a service order detail page.
- **`spare_parts`** — a simpler, older-looking parts table (id/name/price/stock)
  that seems to duplicate `inventory`. Worth checking with your supervisor
  whether this is legacy from an earlier version — this admin app uses
  `inventory` since it has richer fields (`part_code`, `low_stock_alert`).

## 5. A couple of things simplified on purpose

- **Mechanic name on Service Orders:** the table shows a shortened
  `mechanic_id` instead of a name, because joining to `profiles` for the
  mechanic's `full_name` requires knowing your exact foreign-key constraint
  name (Supabase's embed syntax needs `mechanic:profiles!<fk_name>(full_name)`).
  Open your Supabase Dashboard → Database → service_orders table → check the
  constraint name on `mechanic_id`, then I can wire this in for you.
- **No email column on User & Role Management:** `profiles` doesn't store
  email (it lives in Supabase's own `auth.users`, which needs the secret
  service-role key to query — never something to expose in browser-facing
  code). The page shows `full_name`, `phone`, and `role` instead.

## 6. Row Level Security (RLS)

The web app authenticates via Supabase Auth and sends the user's JWT with
every request, so your existing RLS policies apply automatically — no extra
backend work needed, as long as your `admin`/`staff` roles have RLS policies
allowing SELECT/INSERT/UPDATE on these tables.

## 7. Run it locally in VS Code

**Option A — PHP built-in server (fastest):**
```bash
cd workshopx-admin
php -S localhost:8000
```
Then open `http://localhost:8000`.

**Option B — XAMPP/Laragon:** copy the folder into `htdocs`/`www` and visit
`http://localhost/workshopx-admin`.

**Requirements:** PHP 7.4+ with the `curl` extension (on by default in
XAMPP/Laragon and `php -S`).

## 8. Login

Log in with an existing Supabase Auth user whose `profiles.role` is `admin`
or `staff`. Mechanic accounts are blocked from the web portal by design
(they work through the mobile app per your proposal's role split).

## 9. Natural next steps

- Add pagination for large tables (currently loads all rows — fine for a
  capstone demo, worth revisiting for a real deployment).
- Add an "Edit" flow for customers/vehicles (currently add + view only).
- Wire in `service_order_items` for an itemized invoice view.
- Add a "Create Staff Account" flow using the Supabase service-role key on
  the server side only (deliberately left out of this build for security).
