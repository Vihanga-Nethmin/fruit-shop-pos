# API contracts

Agree the APIs here **before** coding. One file per service (OpenAPI or Markdown):

- `identity-service.md`
- `inventory-service.md`
- `sales-service.md`
- `reporting-service.md`
- `events.md` (sale.completed, sale_return.posted, inventory.movement_posted)

Rules: separate databases, services talk only through APIs and events, cross-service references are UUIDs only.
