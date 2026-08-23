# ADR-002: Multi-Tenancy via Shared Database & PostgreSQL Row-Level Security (RLS)

## Status
**Accepted**

## Context
AfyaNova V3 is designed as a SaaS platform serving multiple independent healthcare organizations, hospital groups, and clinics. We required a multi-tenancy strategy that provides uncompromising data security, instant tenant onboarding, low infrastructure overhead, and simple database migration maintenance.

## Decision
We chose a **Shared Database with PostgreSQL Row-Level Security (RLS) and Application-Level Global Scoping (Defense-in-Depth)**.

### Rationale:
1. **Security at the Engine Level**: PostgreSQL RLS ensures that even if application-level code contains a bug or omits a where clause, the database engine enforces tenant filtering before returning rows.
2. **Operational Efficiency**: Avoids the resource exhaustion and connection pooling overhead of running thousands of distinct PostgreSQL databases or schemas.
3. **Instant Tenant Provisioning**: Creating a new tenant requires inserting a row into the `tenants` table rather than executing long-running schema migration scripts.
4. **Unified Migration Pipeline**: Migrations run once against a single public schema, eliminating partial migration failure states across tenant pools.

## Consequences
### Positive:
- Military-grade data isolation enforced by PostgreSQL kernel.
- Minimal RAM and CPU overhead per tenant.
- Simplified schema migrations and unified database connection pooling.

### Negative:
- Database connection lifecycle must consistently set `SET LOCAL app.current_tenant_id` on every checkout.
- Direct database management tools (e.g. DBeaver, pgAdmin) require setting the session variable to view tenant-specific records.
