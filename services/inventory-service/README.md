# Catalogue & Inventory Service

Part of the Fruit Shop POS monorepo (Vue.js + Laravel + MySQL, microservices).

| | |
|---|---|
| Local port | `8002` (`php artisan serve --port=8002`) |
| Database | `inventory_db` (MySQL) - owned only by this service |

## Owns (FR-04 to FR-14)
Products, categories, suppliers, locations, purchase orders, goods receipts, batches, stock positions, reservations, movements, quality assessments, wastage, supplier returns, stock counts.
Stock is changed **only** by this service; Sales never writes inventory tables.

**Tables:** `products`, `price_history`, `categories`, `suppliers`, `locations`, `purchase_orders`, `goods_receipts`, `batches`, `stock_positions`, `stock_movements`, `reservations`, `quality_assessments`, `wastage_records`, `supplier_returns`, `stock_counts`, `idempotency_keys`, `outbox`

## Public API (via gateway)
`/products`, `/categories`, `/locations`, `/suppliers`, `/purchase-orders`, `/goods-receipts`, `/inventory/*`, `/batches`, `/wastage-records`, `/supplier-returns`, `/stock-counts`
(cost fields stripped from CA responses)

## Internal API (`routes/internal.php`)
Reservations: create, by-request, commit, release. Sale-return-receipts.

## Events
Publishes `inventory.movement_posted` (outbox).

## Rules to remember
FEFO allocation, decimal-safe quantities (KG 3 dp, EACH whole), immutable ledger, Idempotency-Key on stock-changing POSTs, ETag / If-Match on mutable documents, reservation expiry (default 5 min).

## Setup

```bash
cd services/inventory-service
composer install
cp .env.example .env
php artisan key:generate
# create the database inventory_db in MySQL, then:
php artisan migrate
php artisan serve --port=8002
```

Public routes: `routes/api.php` (prefix `/api/v1`).
