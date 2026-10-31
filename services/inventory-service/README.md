# Catalogue & Inventory Service

Part of the Fruit Shop POS monorepo (Vue.js + Laravel + MySQL, microservices).

|            |                                                     |
| ---------- | --------------------------------------------------- |
| Local port | `8002` (`php artisan serve --port=8002`)            |
| Database   | `inventory_db` (MySQL) - owned only by this service |
| Stack      | Laravel 12, PHP 8.2+, InnoDB transactions           |

## Owns (FR-04 to FR-14)

Products, categories, suppliers, locations, purchase orders, goods receipts, batches, stock positions, reservations, movements, quality assessments, wastage, supplier returns, stock counts, customer return receipts, alerts, inventory reports, audit records and the event outbox.
Stock is changed **only** by this service; Sales never writes inventory tables. Other services refer to these records by opaque UUIDs (no cross-service joins).

**Tables:** `products`, `price_history`, `categories`, `suppliers`, `locations`, `purchase_orders`, `purchase_order_lines`, `goods_receipts`, `goods_receipt_lines`, `batches`, `stock_positions`, `stock_movements`, `reservations`, `reservation_lines`, `quality_assessments`, `sell_by_reviews`, `wastage_records`, `supplier_returns`, `sale_return_receipts`, `sale_return_receipt_lines`, `stock_counts`, `stock_count_lines`, `audit_events`, `idempotency_keys`, `outbox`

## Public API (prefix `/api/v1`, via gateway)

| Area                 | Endpoints                                                                                                                                                                                                                                                                                      |
| -------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Master data          | `GET/POST /categories`, `PATCH /categories/{id}`; `GET/POST /suppliers`, `GET/PATCH /suppliers/{id}`; `GET/POST /locations`, `PATCH /locations/{id}`; `GET/POST /products`, `GET/PATCH /products/{id}`                                                                                         |
| Stock                | `GET /inventory/positions`, `/inventory/availability`, `/inventory/movements`; `POST /inventory/opening-balances`, `/inventory/transfers`                                                                                                                                                      |
| Purchasing           | `GET/POST /purchase-orders`, `GET /purchase-orders/{id}`, `POST /purchase-orders/{id}/approve`, `/cancel`                                                                                                                                                                                      |
| Receiving            | `GET/POST /goods-receipts`, `GET /goods-receipts/{id}`, `POST /goods-receipts/{id}/post`                                                                                                                                                                                                       |
| Batches              | `GET /batches`, `GET /batches/{id}`, `POST /batches/{id}/assessments`, `GET/POST /batches/{id}/sell-by-review`                                                                                                                                                                                 |
| Wastage              | `GET/POST /wastage-records`, `GET /wastage-records/{id}`, `POST /wastage-records/{id}/approve`, `/reject`, `/cancel`                                                                                                                                                                           |
| Supplier returns     | `GET/POST /supplier-returns`, `GET /supplier-returns/{id}`, `POST /supplier-returns/{id}/approve`, `/reject`, `/cancel`                                                                                                                                                                        |
| Stock counts         | `GET/POST /stock-counts`, `GET /stock-counts/{id}`, `POST /stock-counts/{id}/entries`, `/submit`, `/approve`, `/cancel`                                                                                                                                                                        |
| Alerts               | `GET /inventory/alerts` (`near_days`, `review_days`, `type`)                                                                                                                                                                                                                                   |
| Reports (owner only) | `GET /reports/stock` (`group_by=product\|batch\|location`), `/reports/ageing`, `/reports/wastage`, `/reports/count-variance`, `/reports/supplier-receipts`, `/reports/reorder-suggestions`; add `format=csv` to export. Period reports take `from` and `to` (YYYY-MM-DD, default last 30 days) |
| Audit (owner only)   | `GET /audit-events` (filters, `format=csv`)                                                                                                                                                                                                                                                    |

## Endpoints used by the Sales service

Reservations: `POST /inventory/reservations`, `GET /inventory/reservations/by-request/{requestId}`, `POST /inventory/reservations/{id}/commit`, `/release`.
Customer returns: `POST /inventory/sale-return-receipts`, `GET /inventory/sale-return-receipts/by-request/{requestId}`, `POST /inventory/sale-return-receipt-lines/{lineId}/release` (owner).

These are currently served under `/api/v1`. Planned: move them to `routes/internal.php` behind service identity.

## Events

Publishes `inventory.movement_posted` (outbox). Every stock movement writes one outbox row in the same transaction.
Envelope: `event_id`, `event_type`, `schema_version`, `tenant_id`, `occurred_at`, `correlation_id`, `data`. Delivery is at least once; consumers deduplicate by `event_id`.
`OUTBOX_SINK=log` (default) writes to `storage/logs/outbox-events.log`; `OUTBOX_SINK=http` posts to `REPORTING_EVENTS_URL`.

## Rules to remember

- Physical stock = sum of ledger movements per position. The ledger is immutable; corrections are new movements.
- FEFO allocation (use-by, else sell-by, then received date). A sale can span batches. Ready fruit is the default pool.
- Decimal-safe quantities: KG 3 dp, EACH whole numbers. Costs 6 dp, money 2 dp. Decimals are returned as strings.
- Only accepted receipt quantity enters stock. Unit cost = line cost / accepted quantity.
- Sellable = physical - reserved, for saleable, in-date stock. Past use-by is never sellable. Past sell-by needs an owner review.
- Reservations expire after 5 minutes; commit and expiry are decided by a row lock (only one wins).
- Wastage, supplier returns, count adjustments and quarantine release need the owner; clerks only submit.
- A stock count locks its positions; reserved positions cannot be counted, moved, wasted or returned.
- `Idempotency-Key` on POSTs (optional, `IDEMPOTENCY_REQUIRED=true` makes it mandatory): same key and same request replays the first response, a different request returns 409. Reservations and customer return receipts are also idempotent by `request_id`.
- Writes and CSV exports are written to the append-only audit log.

## Request conventions

- Send `Accept: application/json`. Success: `{"data": ...}`. Domain errors: `{"error": {"code", "message"}}`. Validation: 422 `{"message", "errors"}`.
- Tenant, user and role currently come from temporary headers: `X-Tenant-Id` (required), `X-User-Id`, `X-User-Role` (`OM` owner, `SC` stock clerk). The gateway will replace them with token claims.
- Codes: `INSUFFICIENT_STOCK`, `STOCK_COUNT_LOCKED`, `STOCK_BLOCKED`, `INVALID_DOCUMENT_STATE`, `IDEMPOTENCY_CONFLICT`, `REQUEST_IN_PROGRESS`, `FORBIDDEN`.

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

Public routes: `routes/api.php` (prefix `/api/v1`). Health check: `GET /api/v1/ping`.

| Variable               | Default        | Meaning                            |
| ---------------------- | -------------- | ---------------------------------- |
| `DB_DATABASE`          | `inventory_db` | Service database                   |
| `OUTBOX_SINK`          | `log`          | `log` or `http`                    |
| `REPORTING_EVENTS_URL` | empty          | Needed when the sink is `http`     |
| `IDEMPOTENCY_REQUIRED` | `false`        | Require `Idempotency-Key` on POSTs |

## Scheduled jobs

| Command               | Schedule     | Purpose                          |
| --------------------- | ------------ | -------------------------------- |
| `reservations:expire` | every minute | Frees holds past their expiry    |
| `outbox:publish`      | every minute | Sends pending events             |
| `idempotency:purge`   | daily        | Removes expired idempotency keys |

Locally: `php artisan schedule:work`. Production: a cron entry for `php artisan schedule:run`.

## Tests

Feature tests use the SRS 8.3 worked examples (receiving 18 kg at LKR 400/kg, 16 kg remaining, -0.200 kg count adjustment) and cover FEFO, oversell protection, tenant isolation, roles, idempotency, outbox and audit.
They run on a separate database, `inventory_test_db` (create it once in MySQL); the test base class refuses to run on any other database.

```bash
php artisan test
```

## Planned / not yet done

- Real tenant, user and role from the gateway token (replaces the temporary headers).
- Cost fields hidden from cashier responses (needs the real role).
- ETag / If-Match on mutable documents (a `version` column already exists).
- Endpoint paths to be aligned with REST API Documentation v1.0.
