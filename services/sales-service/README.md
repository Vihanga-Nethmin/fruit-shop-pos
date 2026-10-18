# Sales Service

Part of the Fruit Shop POS monorepo (Vue.js + Laravel + MySQL, microservices).

| | |
|---|---|
| Local port | `8003` (`php artisan serve --port=8003`) |
| Database | `sales_db` (MySQL) - owned only by this service |

## Owns (FR-15 to FR-21, FR-24)
Sales, lines, allocations, retail payments, receipts, returns/refunds, customers, shifts, cash movements. Orchestrates checkout (calls Inventory reservation endpoints, never writes stock).

**Tables:** `sales`, `sale_lines`, `allocations`, `retail_payments`, `sale_returns`, `return_lines`, `refund_records`, `customers`, `shifts`, `cash_movements`, `idempotency_keys`, `outbox`

## Public API (via gateway)
`/sales`, `/sale-returns`, `/customers`, `/shifts`

## Calls
identity-service (entitlement), inventory-service (reservations, sale-return-receipts) - internal endpoints only.

## Events
Publishes `sale.completed`, `sale_return.posted` (outbox).

## Setup

```bash
cd services/sales-service
composer install
cp .env.example .env
php artisan key:generate
# create the database sales_db in MySQL, then:
php artisan migrate
php artisan serve --port=8003
```

Public routes: `routes/api.php` (prefix `/api/v1`).
