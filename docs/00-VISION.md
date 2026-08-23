# AfyaNova V3 — Project Vision & Architectural Principles

## 1. Executive Summary

**AfyaNova V3** is a next-generation Hospital Information System (HIS / EHR / ERP) engineered specifically for private and faith-based healthcare facilities in Tanzania, with an architectural foundation designed for seamless pan-African expansion. 

Built as a **Modular Monolith** using **Laravel (PHP 8.5+), PostgreSQL 18+, Redis, Vue 3, Inertia.js, and Tailwind CSS / shadcn-vue**, AfyaNova V3 delivers modern clinical, financial, administrative, and inventory capabilities without the operational overhead of microservices.

AfyaNova V3 is a **clean-slate greenfield project**. It does not inherit, copy, or adapt any legacy codebase, schema, or technical debt. It adheres strictly to modern Domain-Driven Design (DDD) principles, resilient financial ledger standards, clinical immutability invariants, and robust multi-tenant data isolation.

---

## 2. Core Mission & Objectives

1. **Clinical Excellence & Patient Safety**:
   - Provide intuitive, sub-second clinical charting, order entry, and medication dispensing workflows.
   - Enforce clinical data immutability: medical records are amended with full audit trails, never overwritten or silently deleted.
   - Support structured, codified clinical observations alongside narrative notes without resorting to brittle, unindexable EAV (Entity-Attribute-Value) anti-patterns.

2. **Bulletproof Financial & Inventory Integrity**:
   - Implement immutable double-entry ledger mechanics for both financial billing and inventory stock movements.
   - Disallow frontend calculations as the source of truth; enforce all pricing, discounts, tax, and insurance co-pay invariants server-side within ACID transactions.
   - Maintain full traceability of medication batches, expiry dates, stock locations, and dispensing audit trails (FEFO / FIFO).

3. **Multi-Tenancy & Enterprise Isolation**:
   - Deliver true multi-tenant SaaS capabilities supporting multi-facility health systems, clinics, diagnostic centers, and regional referral hospitals.
   - Enforce rigorous tenant and facility boundaries using PostgreSQL Row-Level Security (RLS) combined with application-level global scopes and middleware defense-in-depth.

4. **Tanzanian & Regional Context by Design**:
   - Native integration with Tanzanian insurance schemes (e.g., NHIF, Jubilee, Strategis, AAR, Assemble, Resolution) and mobile money gateways (M-Pesa, Airtel Money, Tigo Pesa).
   - Multilingual support (Swahili / English) with locale-specific date, currency (TZS, KES, USD), and clinical terminology.
   - Optimized for low-bandwidth, intermittent connectivity environments with resilient local caching and keyboard-first UI efficiency.

5. **Interoperability & Standards Compliance**:
   - FHIR R4 facade architecture for international data exchange, laboratory integrations, diagnostic equipment, and national reporting without forcing the internal domain model into unnatural FHIR compromises.
   - DICOM/PACS viewer integration hooks and ASTM/HL7 lab analyzer interfacing.

6. **Developer & AI Maintainability**:
   - Strict modular monolith domain boundaries preventing circular dependencies and domain leakage.
   - Type safety, comprehensive test coverage (Unit, Feature, Invariant, and Integration tests), and explicit business rules designed for continuous evolution by engineering teams and AI coding agents.

---

## 3. Guiding Architectural Principles

### Principle 1: Simplicity Over Architectural Fashion
> *Prefer the simplest architecture that correctly satisfies the requirements.*

Avoid premature distributed systems. Microservices introduce network latency, distributed transaction complexity (Sagas, 2PC), deployment fragility, and operational expense. A well-modularized monolith within a single deployable unit provides superior velocity, transaction safety, and refactorability.

### Principle 2: Strict Domain-Driven Bounded Contexts
Every domain has explicit responsibilities, entities, value objects, domain services, events, and API contracts. Direct database cross-joins across distinct domain boundaries are prohibited; inter-domain communication occurs via defined public domain interfaces, DTOs, or decoupled domain events.

### Principle 3: Financial & Physical Reality Invariance
- **Financial Invariant**: Money does not appear or disappear. Every invoice line item originates from an authorized charge, and every payment/credit is recorded on an immutable ledger.
- **Physical Invariant**: Inventory items cannot be negative in the real world. Every change in stock balance must be accompanied by a balanced stock movement transaction (receipt, issue, transfer, dispense, adjustment).

### Principle 4: Clinical Immutability & Revisions
Clinical documentation represents a legal and medical record at a point in time. Records are never updated in-place once signed/finalized. Corrections are recorded as explicit amendments, errata, or addenda referencing the original record, preserving complete historical veracity.

### Principle 5: Defense-in-Depth Security & Multi-Tenancy
Security is never delegated to the frontend. Every request is authenticated, authorized against granular tenant/facility/department permissions, and scoped at the database query level. Client input is treated as untrusted.

---

## 4. Target Market & Deployment Profiles

| Deployment Profile | Target Facilities | Key Requirements |
| :--- | :--- | :--- |
| **SaaS Multi-Tenant Cloud** | Independent clinics, polyclinics, specialized practices | Instant provisioning, automated backups, tenant isolation, pay-per-use billing. |
| **Enterprise Health Network** | Multi-branch hospital groups (e.g., Dar es Salaam, Arusha, Mwanza) | Centralized patient registry, cross-facility referrals, consolidated inventory & finance, local branch resilience. |
| **Hybrid On-Premises** | Regional private hospitals with local infrastructure requirements | Local server deployment, automated cloud sync for offsite backup, telemetry, and external claim processing. |
