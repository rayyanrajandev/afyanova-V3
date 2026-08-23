# AfyaNova V3 — Database Architecture & PostgreSQL Design

## 1. Database Philosophy & PostgreSQL Standards

AfyaNova V3 utilizes **PostgreSQL 18+** as its primary relational datastore. The database is designed for strict ACID compliance, data integrity, auditability, and multi-tenant performance.

### Core Database Rules:
1. **Time-Ordered Primary Keys**: All primary keys use **UUIDv7** or **ULIDs** (`uuid` column type in PostgreSQL). This prevents enumeration attacks while maintaining B-Tree index locality and sequential insertion performance.
2. **Explicit Foreign Keys & Referential Integrity**: Every relationship is enforced by relational foreign key constraints with appropriate `ON DELETE RESTRICT` or `ON DELETE CASCADE` rules.
3. **PostgreSQL Check Constraints**: Critical domain invariants (e.g., non-negative prices, balanced debits/credits, valid status strings) are enforced directly at the database engine level via `CHECK` constraints.
4. **No Uncontrolled EAV**: Core clinical, financial, and inventory data is modeled in structured, normalized relational tables. PostgreSQL `jsonb` columns are reserved exclusively for dynamic custom form templates and third-party payload caching.
5. **Immutable Ledgers**: Financial entries and stock movements are append-only. Updates and deletes on ledger tables are blocked via database trigger rules.

---

## 2. Primary Key Strategy: UUIDv7 / ULID

Traditional auto-incrementing integer IDs expose healthcare facilities to insecure direct object reference (IDOR) attacks and record count enumeration. Random UUIDv4 causes severe B-tree index fragmentation.

AfyaNova V3 uses **UUIDv7** (RFC 9562), which combines a 48-bit millisecond UNIX timestamp with 74 bits of cryptographically strong randomness:

```sql
-- Example UUIDv7 generated column or default in PostgreSQL 18+
CREATE TABLE patients (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(), -- or uuidv7 function
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE RESTRICT,
    facility_id UUID NOT NULL REFERENCES facilities(id) ON DELETE RESTRICT,
    primary_mrn VARCHAR(32) NOT NULL,
    ...
);
```

---

## 3. Multi-Tenancy & Indexing Strategy

### 3.1. Tenant Scoping Pattern
Every tenant-owned table contains a `tenant_id UUID NOT NULL` foreign key.

### 3.2. Compound Indexing Standard
Because queries in a multi-tenant application always filter by `tenant_id` (and frequently `facility_id`), compound B-tree indexes always lead with `tenant_id`:

```sql
-- Standard compound index pattern
CREATE INDEX idx_patients_tenant_mrn ON patients (tenant_id, primary_mrn);
CREATE INDEX idx_encounters_tenant_patient_status ON encounters (tenant_id, patient_id, status);
CREATE INDEX idx_charges_tenant_facility_encounter ON charges (tenant_id, facility_id, encounter_id);
CREATE INDEX idx_stock_batches_tenant_product_expiry ON stock_batches (tenant_id, product_item_id, expiry_date);
```

### 3.3. Partial Indexes for Performance
Partial indexes are used extensively for active or unhandled operational states:

```sql
-- Fast lookup of active appointments
CREATE INDEX idx_active_appointments ON appointments (tenant_id, facility_id, scheduled_start) 
WHERE status IN ('Scheduled', 'Confirmed', 'Arrived');

-- Fast queue management lookup
CREATE INDEX idx_unserved_queue_tickets ON queue_tickets (tenant_id, facility_id, department_id, priority, issued_at) 
WHERE status IN ('Waiting', 'Called');
```

---

## 4. PostgreSQL Row-Level Security (RLS) Architecture

In addition to Laravel Eloquent global scopes, AfyaNova V3 enables **PostgreSQL Row-Level Security (RLS)** as a defense-in-depth security boundary against accidental tenant data leaks.

### How RLS Works in AfyaNova V3:
1. When a database connection is checked out from the pool, the application sets the active tenant context via a session variable:
   ```sql
   SET LOCAL app.current_tenant_id = '018f3a5b-9871-7000-8000-000000000001';
   ```
2. PostgreSQL RLS policies enforce isolation transparently:
   ```sql
   ALTER TABLE patients ENABLE ROW LEVEL SECURITY;
   ALTER TABLE encounters ENABLE ROW LEVEL SECURITY;
   ALTER TABLE invoices ENABLE ROW LEVEL SECURITY;

   CREATE POLICY tenant_isolation_policy ON patients
       FOR ALL
       USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID);
   ```

---

## 5. Financial & Stock Ledger Schemas

### 5.1. Financial Double-Entry Ledger Schema
```sql
CREATE TABLE financial_ledger_entries (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    facility_id UUID NOT NULL REFERENCES facilities(id),
    transaction_date TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    account_code VARCHAR(32) NOT NULL, -- e.g. 1010-Cash, 1200-AR-Insurance, 4010-Consultation-Revenue
    debit_amount NUMERIC(15, 2) NOT NULL DEFAULT 0.00,
    credit_amount NUMERIC(15, 2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) NOT NULL DEFAULT 'TZS',
    entity_type VARCHAR(64) NOT NULL, -- 'Invoice', 'Payment', 'Refund', 'WriteOff'
    entity_id UUID NOT NULL,
    description TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    
    CONSTRAINT chk_positive_amounts CHECK (debit_amount >= 0 AND credit_amount >= 0),
    CONSTRAINT chk_either_debit_or_credit CHECK (
        (debit_amount > 0 AND credit_amount = 0) OR 
        (credit_amount > 0 AND debit_amount = 0)
    )
);

CREATE INDEX idx_ledger_tenant_account_date ON financial_ledger_entries (tenant_id, account_code, transaction_date);
CREATE INDEX idx_ledger_entity ON financial_ledger_entries (tenant_id, entity_type, entity_id);
```

### 5.2. Inventory Stock Movement Ledger Schema
```sql
CREATE TABLE stock_movements (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    facility_id UUID NOT NULL REFERENCES facilities(id),
    product_item_id UUID NOT NULL REFERENCES product_items(id),
    batch_id UUID NOT NULL REFERENCES stock_batches(id),
    source_location_id UUID REFERENCES stock_locations(id),
    destination_location_id UUID REFERENCES stock_locations(id),
    movement_type VARCHAR(32) NOT NULL, -- 'GOODS_RECEIPT', 'DISPENSE', 'TRANSFER', 'ADJUSTMENT_POS', 'ADJUSTMENT_NEG'
    quantity NUMERIC(12, 4) NOT NULL,
    unit_cost NUMERIC(15, 2) NOT NULL,
    reference_type VARCHAR(64) NOT NULL, -- 'PurchaseOrder', 'DispenseRecord', 'StockAdjustment'
    reference_id UUID NOT NULL,
    performed_by_user_id UUID NOT NULL REFERENCES users(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT chk_movement_quantity_positive CHECK (quantity > 0),
    CONSTRAINT chk_valid_movement_locations CHECK (
        source_location_id IS NOT NULL OR destination_location_id IS NOT NULL
    )
);

CREATE INDEX idx_stock_movements_tenant_batch ON stock_movements (tenant_id, batch_id, created_at);
CREATE INDEX idx_stock_movements_reference ON stock_movements (tenant_id, reference_type, reference_id);
```

---

## 6. Clinical Tables & Structured Observations

To avoid OpenMRS-style EAV performance bottlenecks, clinical data uses normalized tables for high-frequency clinical metrics:

```sql
-- Normalized Vital Signs Table
CREATE TABLE vital_signs (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    facility_id UUID NOT NULL REFERENCES facilities(id),
    encounter_id UUID NOT NULL REFERENCES encounters(id),
    patient_id UUID NOT NULL REFERENCES patients(id),
    measured_by_user_id UUID NOT NULL REFERENCES users(id),
    recorded_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    
    systolic_bp SMALLINT CHECK (systolic_bp BETWEEN 30 AND 300),
    diastolic_bp SMALLINT CHECK (diastolic_bp BETWEEN 20 AND 200),
    heart_rate SMALLINT CHECK (heart_rate BETWEEN 20 AND 300),
    respiratory_rate SMALLINT CHECK (respiratory_rate BETWEEN 5 AND 100),
    temperature_celsius NUMERIC(4, 1) CHECK (temperature_celsius BETWEEN 25.0 AND 45.0),
    spo2_percentage SMALLINT CHECK (spo2_percentage BETWEEN 0 AND 100),
    weight_kg NUMERIC(5, 2) CHECK (weight_kg BETWEEN 0.2 AND 500.0),
    height_cm NUMERIC(5, 1) CHECK (height_cm BETWEEN 20.0 AND 250.0),
    bmi NUMERIC(4, 1) GENERATED ALWAYS AS (
        CASE WHEN height_cm > 0 THEN ROUND((weight_kg / ((height_cm / 100.0) * (height_cm / 100.0)))::numeric, 1) ELSE NULL END
    ) STORED,
    pain_scale SMALLINT CHECK (pain_scale BETWEEN 0 AND 10),
    blood_glucose_mmol NUMERIC(5, 2),
    
    is_amended BOOLEAN NOT NULL DEFAULT FALSE,
    amended_vital_id UUID REFERENCES vital_signs(id),
    amendment_reason TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_vitals_encounter ON vital_signs (tenant_id, encounter_id, recorded_at);
CREATE INDEX idx_vitals_patient_timeline ON vital_signs (tenant_id, patient_id, recorded_at DESC);
```

---

## 7. Migration & Seeding Conventions

- Migrations are strictly forward-compatible. Destructive migrations (`DROP COLUMN`, `RENAME COLUMN`) in production are split into multi-phase expand-and-contract deployments.
- Seeders provide standardized reference datasets for:
  - ICD-10 Diagnosis Codes & Chapters
  - Standard LOINC Test Parameter Codes
  - Default Hospital Departments & Standard Roles
  - Tanzanian Insurance Provider & Scheme Profiles
