# API Gateway

Part of the Fruit Shop POS monorepo (Vue.js + Laravel + MySQL, microservices).

| | |
|---|---|
| Local port | `8000` (`php artisan serve --port=8000`) |
| Database | none (stateless) - owned only by this service |

## Responsibilities
Single public entry point for the Vue SPA. Token validation, tenant injection, role guard (SA/OM/SC/CA), rate limiting, correlation ID, routing to services. Never exposes `/internal/v1/*`.

## Routing table
| Public path (`/api/v1`) | Routed to |
|---|---|
| `/auth/*`, `/users`, `/subscription`, `/settings`, `/admin/*` | identity-service |
| `/products`, `/categories`, `/locations`, `/suppliers`, `/purchase-orders`, `/goods-receipts`, `/inventory/*`, `/batches`, `/wastage-records`, `/supplier-returns`, `/stock-counts` | inventory-service |
| `/sales`, `/sale-returns`, `/customers`, `/shifts` | sales-service |
| `/dashboard`, `/reports/*`, `/exports`, `/audit-events` | reporting-service |
| `/internal/v1/*` | blocked |

Middleware lives in `app/Http/Middleware/`.

## Setup

```bash
cd gateway
composer install
cp .env.example .env
php artisan key:generate


php artisan serve --port=8000
```

Public routes: `routes/api.php` (prefix `/api/v1`).
