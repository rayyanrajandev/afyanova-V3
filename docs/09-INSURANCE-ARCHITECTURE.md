# AfyaNova V3 — Insurance & Claims Architecture

## 1. Architectural Strategy: Provider Adapter Pattern

AfyaNova V3 does not hardcode insurance logic to any single insurer. Instead, it defines an extensible **Insurance Provider Adapter Pattern**. This allows seamless integration with both Tanzanian national schemes (NHIF) and private insurers (Jubilee, Strategis, AAR, Assemble, Sanlam), while remaining ready for international regional expansion.

```
┌────────────────────────────────────────────────────────┐
│               AFYANOVA INSURANCE DOMAIN                │
│   [PatientPolicy, Coverage, PreAuth, Claim, Remittance] │
└───────────────────────────┬────────────────────────────┘
                            │ Calls Contract Interface
┌───────────────────────────▼────────────────────────────┐
│          InsuranceProviderAdapterInterface             │
│  - verifyEligibility(PatientPolicy $policy): Response  │
│  - requestPreAuthorization(PreAuthDTO $dto): Response  │
│  - submitClaimBatch(ClaimBatch $batch): BatchResult    │
│  - pollClaimStatus(Claim $claim): ClaimStatusResult    │
│  - parseRemittanceAdvice(string $rawPayload): RemitDTO │
└───────────────────────────┬────────────────────────────┘
                            │
         ┌──────────────────┼──────────────────┐
         ▼                  ▼                  ▼
┌─────────────────┐┌─────────────────┐┌─────────────────┐
│   NhifAdapter   ││ JubileeAdapter  ││  GenericPortal  │
│ - NHIF API /    ││ - REST API /    ││   CsvExport     │
│   Card Protocol ││   JSON Claims   ││ - Manual EDI    │
└─────────────────┘└─────────────────┘└─────────────────┘
```

---

## 2. Tanzanian Insurance Ecosystem Overview

1. **NHIF (National Health Insurance Fund)**:
   - Primary public insurer covering civil servants, private employees, and community schemes.
   - Requires card number / biometric validation, pre-authorization for specialized diagnostics/surgeries, and standard electronic claim submission format.
2. **Private Health Insurers**:
   - Major underwriters: **Jubilee**, **Strategis**, **AAR Insurance**, **Assemble Insurance**, **Sanlam**, **Britam**.
   - Features: Specific corporate benefit limits, annual dental/optical caps, tiered inpatient bed entitlement (e.g. TZS 150,000/day max for executive ward).
3. **Corporate Self-Funded Schemes & TPAs**:
   - Companies maintaining direct employee credit lines or managed by Third-Party Administrators.

---

## 3. End-to-End Insurance Claim Lifecycle

```
[1. Patient Check-In]
        │
        ▼
[2. Eligibility & Biometric Check] ──► Query NHIF/Insurer API (Verify active status & coverage)
        │
        ▼
[3. Pre-Authorization Request] ──────► Required for High-Cost Tests / Procedures (MRI, CT, Surgeries)
        │                              Receives PreAuth Code from Insurer
        ▼
[4. Clinical Consultation & Care] ───► Orders, Pharmacy, Procedures documented
        │
        ▼
[5. Encounter Invoicing & Splitting]─► Generates Invoice (Patient Co-Pay + Insurer Claim Amount)
        │
        ▼
[6. Claim Generation & Scrubber] ────► Validates ICD-10 diagnosis codes match ordered procedures
        │                              Checks mandatory fields (Doctor Reg No, Member Card No)
        ▼
[7. Batch Submission] ───────────────► Submitted via API or Secure Electronic File Transfer
        │
        ▼
[8. Adjudication & Response] ────────► Status: Approved, Partially Approved, Rejected
        │
        ▼
[9. Remittance & Reconciliation] ────► Insurer sends bulk payment advice.
                                       System reconciles payments against individual claims.
                                       Rejections are routed to Insurance Desk for dispute/appeal.
```

---

## 4. Claim Scrubber & Pre-Submission Validation Rules

To minimize claim rejection rates from insurance audits, AfyaNova V3 includes an automated **Claim Scrubber** that validates each claim before submission:
1. **Diagnosis Coding Completeness**: Every claim item must have an associated valid ICD-10 primary diagnosis.
2. **Pre-Authorization Enforcement**: If a service code requires prior approval (e.g. MRI, Endoscopy, Dialysis), the claim must contain an approved, non-expired `auth_code`.
3. **Benefit Limit Check**: Checks that the claimed amount does not exceed the member's remaining annual sub-limit for that benefit category.
4. **Physician License Validation**: Ensures the ordering clinician's medical practitioner registration number is attached.

---

## 5. Remittance Processing & Dispute Management

When an insurer remits payment:
- **Full Approval**: The Accounts Receivable balance for the insurer is credited, closing the claim.
- **Partial Approval / Short Payment**: 
  - Approved items are credited on the ledger.
  - Disallowed line items are moved to `ClaimDispute` status with the insurer's rejection reason code (e.g. *Code 42: Not Covered Under Plan*).
  - The billing officer can choose to:
    1. **Appeal / Resubmit**: Submit clinical justification or corrected documentation.
    2. **Transfer to Patient**: Convert the disallowed amount into a patient-due invoice.
    3. **Authorize Write-Off**: Post a bad-debt expense write-off to the financial ledger (requires supervisory permission).
