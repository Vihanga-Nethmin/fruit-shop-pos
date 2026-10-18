# Identity & Subscription Service

Part of the Fruit Shop POS monorepo (Vue.js + Laravel + MySQL, microservices).

| | |
|---|---|
| Local port | `8001` (`php artisan serve --port=8001`) |
| Database | `identity_db` (MySQL) - owned only by this service |

## Owns (FR-01, FR-02, FR-03, FR-25 settings)
Tenants, users, roles, refresh sessions, plans, subscriptions, subscription payments, shop settings.

**Tables:** `tenants`, `users`, `roles`, `refresh_sessions`, `plans`, `subscriptions`, `subscription_payments`, `settings`

## Public API (via gateway)
`/auth/*`, `/users`, `/subscription`, `/settings`, `/admin/*` (SA only)

## Internal API (`routes/internal.php`)
Entitlement check, consumed by all other services.

## Events
Publishes audit-relevant events (subscription / permission changes) via outbox.

## Setup

```bash
cd services/identity-service
composer install
cp .env.example .env
php artisan key:generate
# create the database identity_db in MySQL, then:
php artisan migrate
php artisan serve --port=8001
```

Public routes: `routes/api.php` (prefix `/api/v1`).
