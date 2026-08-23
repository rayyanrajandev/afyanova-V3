# ADR-008: Extensible Insurance Provider Adapter Pattern

## Status
**Accepted**

## Context
Private hospitals in Tanzania interface with diverse insurance schemes: NHIF (public national scheme) and multiple private underwriters (Jubilee, Strategis, AAR, Assemble, Sanlam, Resolution). Each insurer has distinct authorization protocols, claim submission payload formats, co-pay requirements, and benefit limit structures.

Hardcoding any single provider directly into the billing engine leads to architectural rot and makes regional expansion difficult.

## Decision
AfyaNova V3 defines an **Insurance Provider Adapter Pattern** (`InsuranceProviderAdapterInterface`).
1. The core `Insurance` domain manages standard concepts: `PatientPolicy`, `Coverage`, `PreAuthorization`, `Claim`, and `RemittanceAdvice`.
2. Dedicated adapter classes (`NhifAdapter`, `JubileeAdapter`, `StrategisAdapter`, `GenericPortalCsvAdapter`) implement the adapter interface to translate generic claim operations into provider-specific API calls or batch file exports.

## Consequences
### Positive:
- New insurance providers can be integrated by adding a new adapter driver without modifying core billing or clinical code.
- Clean separation between internal claim lifecycles and external insurer protocols.
- Simplified unit testing with mock adapters.

### Negative:
- Requires maintaining adapter compatibility against third-party API changes.
