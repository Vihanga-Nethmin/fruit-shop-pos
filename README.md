# Fruit Shop POS & Inventory - Shield Technologies

Vue.js + Laravel + MySQL, microservices architecture (database per service).
Based on SRS v2.0 and Technical Architecture v1.0 (see `docs/`).

## Structure

```
fruit-shop-pos/
|-- frontend/                    Vue.js SPA (talks to gateway only)
|-- gateway/                     Laravel API Gateway                  :8000
|-- services/
|   |-- identity-service/        Tenants, users, subscriptions        :8001  identity_db
|   |-- inventory-service/       Catalogue & inventory                :8002  inventory_db
|   |-- sales-service/           Sales, returns, customers, shifts    :8003  sales_db
|   `-- reporting-service/       Projections, reports, exports, audit :8004  reporting_db
|-- docs/                        SRS, architecture, API contracts
`-- README.md
```

Each Laravel app has its own `README.md` (what it owns, endpoints, events, setup).

## Team split

| Member | Service | Does what |
|---|---|---|
| 1 | Inventory | Products, suppliers, receiving, batches, FEFO, wastage, stock counts |
| 2 | Sales | Checkout, payments, receipts, returns/refunds, shifts |
| 3 | Identity + Reporting | Login, roles, subscriptions, reports, API gateway |

**Rules for all:** agree on the APIs first, keep separate databases, talk to each other only through APIs and events.

## Branches

- `main` - stable, reviewed releases only
- `dev` - integration branch
- one branch per member; merge into `dev` through reviewed pull requests
