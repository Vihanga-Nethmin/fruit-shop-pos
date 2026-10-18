# Reporting Service

Part of the Fruit Shop POS monorepo (Vue.js + Laravel + MySQL, microservices).

| | |
|---|---|
| Local port | `8004` (`php artisan serve --port=8004`) |
| Database | `reporting_db` (MySQL) - owned only by this service |

## Owns (FR-22, FR-23, FR-25 audit)
Read-only projections built from events, dashboard, reports, CSV export jobs, append-only audit events.

**Tables:** `sales_projection`, `stock_projection`, `margin_projection`, `wastage_projection`, `export_jobs`, `audit_events`, `processed_events`

## Public API (via gateway)
`/dashboard`, `/reports/*`, `/exports`, `/audit-events`

## Events consumed
`sale.completed`, `sale_return.posted`, `inventory.movement_posted` (deduplicate by `event_id`, store in `processed_events`).

## Setup

```bash
cd services/reporting-service
composer install
cp .env.example .env
php artisan key:generate
# create the database reporting_db in MySQL, then:
php artisan migrate
php artisan serve --port=8004
```

Public routes: `routes/api.php` (prefix `/api/v1`).
