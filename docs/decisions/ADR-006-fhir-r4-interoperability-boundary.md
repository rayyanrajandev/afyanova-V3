# ADR-006: FHIR R4 Interoperability Facade Boundary

## Status
**Accepted**

## Context
Interoperability with national health registries (e.g., Tanzanian MoH HIE), external diagnostic labs, and mobile health apps requires HL7 FHIR R4 standard compliance. However, adopting FHIR directly as the internal database schema creates significant performance overhead, impedance mismatch with billing/ERP domains, and unnecessary structural complexity.

## Decision
AfyaNova V3 adopts a **FHIR R4 Facade Layer** pattern.
1. The internal database schema is designed around optimized DDD domain models (PostgreSQL relational tables + JSONB extensions).
2. Dedicated FHIR mappers and controllers (`app/Domains/Integration/Fhir/`) transform internal entities into standard FHIR R4 resources on dedicated `/fhir/r4/` API endpoints.

## Consequences
### Positive:
- High internal transactional performance and schema clarity.
- Complete compliance with international FHIR R4 specifications.
- Freedom to optimize internal models without breaking external API contracts.

### Negative:
- Mapping logic must be maintained and verified via automated schema tests whenever internal domain models evolve.
