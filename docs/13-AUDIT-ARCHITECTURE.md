# AfyaNova V3 — Audit & Compliance Architecture

## 1. Audit Principles

1. **Healthcare & Legal Compliance**: Hospital information systems are subject to strict regulatory, medical, and financial audit standards. Audit records must be comprehensive, tamper-evident, and immutable.
2. **Intentional & Structured Logging**: An audit trail must not be an uncontrolled debug log dump. It captures high-value, clinically, financially, and security-significant events with structured context.
3. **The 7 Core Audit Questions**:
   Every audit record must definitively answer:
   - **WHO?** User ID, Full Name, Active Role, IP Address, Client Device.
   - **WHAT?** Action type (`CREATE`, `AMEND`, `SIGN`, `VOID`, `DISPENSE`, `OVERRIDE`) on specific Entity (`Patient`, `ClinicalNote`, `Invoice`, `StockMovement`).
   - **WHEN?** High-precision UTC timestamp (millisecond resolution).
   - **WHERE?** Tenant ID, Facility ID, Department ID, Application Endpoint.
   - **BEFORE?** Complete JSON snapshot of the state prior to mutation.
   - **AFTER?** Complete JSON snapshot of the state following mutation.
   - **WHY?** Clinical or administrative justification for amendments, cancellations, refunds, overrides, or write-offs.

---

## 2. Structured Audit Record Schema

```sql
CREATE TABLE audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    facility_id UUID REFERENCES facilities(id),
    user_id UUID REFERENCES users(id),
    
    event_category VARCHAR(32) NOT NULL, -- 'CLINICAL', 'FINANCIAL', 'INVENTORY', 'AUTH', 'SECURITY', 'PRIVACY'
    action VARCHAR(32) NOT NULL,         -- 'LOGIN', 'LOGOUT', 'CREATE', 'UPDATE', 'AMEND', 'SIGN', 'VOID', 'VIEW_CHART'
    entity_type VARCHAR(64) NOT NULL,    -- 'Encounter', 'ClinicalNote', 'Invoice', 'StockBatch', 'User'
    entity_id UUID NOT NULL,
    
    ip_address INET NOT NULL,
    user_agent TEXT,
    route_name VARCHAR(128),
    
    before_state JSONB,
    after_state JSONB,
    justification_reason TEXT,
    
    hash_signature VARCHAR(64) NOT NULL, -- SHA-256 integrity hash
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Compound indexes for fast compliance audits
CREATE INDEX idx_audit_tenant_entity ON audit_logs (tenant_id, entity_type, entity_id, created_at DESC);
CREATE INDEX idx_audit_tenant_user ON audit_logs (tenant_id, user_id, created_at DESC);
CREATE INDEX idx_audit_tenant_category ON audit_logs (tenant_id, event_category, created_at DESC);
```

---

## 3. Auditable Event Taxonomy

| Category | Trigger Event | Context Captured |
| :--- | :--- | :--- |
| **Clinical** | Clinical note signed / amended | Note type, author license, previous version diff, clinical reason for amendment. |
| **Clinical** | Prescription dispensed | Prescribed vs dispensed quantity, batch number, pharmacist user, override reason (if non-FEFO). |
| **Clinical** | Panic lab result verified / acknowledged | Result value, reference ranges, critical flag, acknowledging clinician ID. |
| **Financial** | Invoice issued / voided | Gross, discount, tax, net totals, line item breakdown, supervisor approval. |
| **Financial** | Payment received / refund issued | Amount, payment method, provider transaction ref, cashier shift ID, refund rationale. |
| **Financial** | Bad-debt write-off / Discount applied | Write-off amount, debtor identity, authorizing manager, policy reason code. |
| **Inventory** | Stock adjustment (gain/loss) | Physical count vs system balance, batch ID, monetary variance, authorizing store officer. |
| **Security** | Authentication attempt (Success / Fail) | Username, IP address, geo-location, failure reason, MFA method used. |
| **Security** | Role / Permission modification | Target user, added/removed permissions, granting admin user. |
| **Privacy** | Sensitive patient chart access | Patient MRN, viewing clinician, break-glass emergency justification (if applicable). |

---

## 4. Tamper Resistance & Immutability Enforcement

To guarantee legal admissibility and prevent audit log tampering:

1. **Database-Level Mutation Block**:
   ```sql
   -- Prevent UPDATE or DELETE on audit logs table
   CREATE RULE no_update_audit AS ON UPDATE TO audit_logs DO INSTEAD NOTHING;
   CREATE RULE no_delete_audit AS ON DELETE TO audit_logs DO INSTEAD NOTHING;
   ```
2. **Cryptographic Chaining (HMAC Hash)**:
   Each audit entry contains a cryptographic SHA-256 signature calculated from the previous entry's hash + current record payload, creating an unbroken audit blockchain.
3. **Automated Cold Storage Archival**:
   Audit logs older than 90 days are automatically archived to immutable, write-once-read-many (WORM) cloud storage (e.g. AWS S3 Glacier Object Lock) for 10-year regulatory retention.
