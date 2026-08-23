# ADR-001: Modular Monolith Architecture Pattern

## Status
**Accepted**

## Context
Hospital Information Systems encompass diverse domains: Patient Registration, Clinical Charting, Inpatient Bed Management, Pharmacy, Laboratory, Inventory, Billing, and Insurance Claims. 

Two architectural paradigms were considered for the greenfield AfyaNova V3 build:
1. **Microservices Architecture**: Separate network services for Patient, Billing, Inventory, Pharmacy, Lab, etc.
2. **Modular Monolith**: A single deployable codebase structured into strictly decoupled, bounded domain modules.

## Decision
We chose the **Modular Monolith** architecture pattern built on Laravel, PostgreSQL, and Redis.

### Rationale:
1. **ACID Transactional Integrity**: Critical hospital operations (e.g., dispensing medication while checking stock, posting financial billing ledgers, and recording clinical orders) require immediate relational consistency. Distributed sagas and 2-phase commits in microservices introduce significant failure modes, split-brain states, and developer friction.
2. **Operational Simplicity & Deployment Velocity**: A single deployable unit eliminates the need for service meshes, complex distributed tracing, network latency overhead, and multi-repository version synchronization.
3. **High Cohesion & Low Coupling**: Domains maintain clear internal boundaries (`app/Domains/Clinical`, `app/Domains/Billing`, `app/Domains/Inventory`) communicating exclusively via explicit PHP interfaces, DTOs, and Laravel events.
4. **Future Extraction Path**: If a specific domain (e.g., High-Volume Laboratory Analyzer Gateway or FHIR Analytics) requires independent scaling in the future, its bounded context makes extraction into a microservice straightforward.

## Consequences
### Positive:
- Instant ACID transactions across domain boundaries.
- Simplified local development, testing, and CI/CD pipelines.
- Zero network latency for inter-domain queries.
- Drastically reduced infrastructure costs for cloud/on-prem deployments.

### Negative:
- Requires strict automated architectural linting to prevent developers from taking shortcuts (e.g., cross-joining tables across domain boundaries directly).
