# AfyaNova V3

> Modern, Secure, Multi-Tenant Hospital Information System (HIS / EHR / ERP) engineered for Tanzanian and African Healthcare Facilities.

---

## 1. Overview

**AfyaNova V3** is a greenfield hospital information and electronic health record system. It is designed to empower private and faith-based healthcare facilities across Tanzania and the broader African continent with intuitive clinical workflows, bulletproof financial double-entry accounting, perpetual inventory tracking, and seamless insurance/mobile money integrations.

Built as a **Modular Monolith** using **Laravel (PHP 8.5+), PostgreSQL 18+, Redis, Vue 3, Inertia.js, and Tailwind CSS / shadcn-vue**, AfyaNova V3 prioritizes simplicity, maintainability, and domain integrity.

---

## 2. Core Architectural Highlights

- **Modular Monolith Architecture**: Domain-Driven Design (DDD) with strictly bounded modules (`Clinical`, `Pharmacy`, `Laboratory`, `Billing`, `Inventory`, `Insurance`, `Identity`).
- **PostgreSQL Row-Level Security (RLS)**: Multi-tenant data isolation enforced directly at the database engine level.
- **Double-Entry Financial Ledger**: Immutable debit/credit accounting ensuring zero revenue leakage.
- **Perpetual Inventory & FEFO**: Batch and expiry tracking with strict zero-negative-stock invariants.
- **Clinical Immutability**: Full legal medical record veracity via auditable amendments and SOAP notes.
- **Tanzania-First Integration**: Native adapters for NHIF, private health insurers (Jubilee, Strategis, AAR), and Mobile Money (Vodacom M-Pesa STK push, Airtel Money, Tigo Pesa).
- **FHIR R4 Facade**: Interoperability with international health standards and national registries without internal schema compromises.

---

## 3. Technology Stack

- **Backend**: PHP 8.5+, Laravel 12+, PostgreSQL 18+, Redis 7+
- **Frontend**: Vue 3, TypeScript, Inertia.js, Tailwind CSS 4+, shadcn-vue (Radix primitives)
- **Quality Gates**: Pest PHP, PHPStan (Level 8+), Pint, Vitest, Playwright, GitHub Actions CI
- **Infrastructure**: Docker, Nginx, Redis Queue Workers, S3 / MinIO Encrypted Storage

---

## 4. Documentation Index

Comprehensive architectural specifications and decision records are located in `docs/`:

- [00. Vision & Core Principles](docs/00-VISION.md)
- [01. Reference System Analysis (Bahmni, OpenMRS, OpenEMR, GNU Health)](docs/01-REFERENCE-SYSTEM-ANALYSIS.md)
- [02. Domain Model Specification](docs/02-DOMAIN-MODEL.md)
- [03. System Architecture & Modular Monolith Design](docs/03-SYSTEM-ARCHITECTURE.md)
- [04. Database Architecture & PostgreSQL Standards](docs/04-DATABASE-ARCHITECTURE.md)
- [05. Role-Based Access Control (RBAC) Architecture](docs/05-RBAC-ARCHITECTURE.md)
- [06. Multi-Tenancy & Data Isolation](docs/06-MULTI-TENANCY-ARCHITECTURE.md)
- [07. Clinical Domain Architecture](docs/07-CLINICAL-ARCHITECTURE.md)
- [08. Billing & Financial Ledger Architecture](docs/08-BILLING-ARCHITECTURE.md)
- [09. Insurance & Claims Architecture](docs/09-INSURANCE-ARCHITECTURE.md)
- [10. Inventory & Procurement Architecture](docs/10-INVENTORY-ARCHITECTURE.md)
- [11. Integration & Interoperability Architecture](docs/11-INTEGRATION-ARCHITECTURE.md)
- [12. Security & Data Protection Architecture](docs/12-SECURITY-ARCHITECTURE.md)
- [13. Audit & Compliance Architecture](docs/13-AUDIT-ARCHITECTURE.md)
- [14. FHIR R4 Interoperability Architecture](docs/14-FHIR-ARCHITECTURE.md)
- [15. Comprehensive Testing Strategy](docs/15-TESTING-STRATEGY.md)
- [Architecture Decision Records (ADRs)](docs/decisions/)

---

## 5. Development Rules & Philosophy

See [ARCHITECTURE_RULES.md](ARCHITECTURE_RULES.md) for strict development invariants and guidelines.
See [ROADMAP.md](ROADMAP.md) for implementation phases.
