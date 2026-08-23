# AfyaNova V3 — Clinical Procedures & Surgical Services Architecture

## 1. Architectural Strategy: Tier-Scalable Procedure Delivery

In the Tanzanian and regional East African healthcare ecosystem, clinical procedures span a spectrum ranging from primary outpatient dressing rooms in rural dispensaries to multi-suite major surgical theatres in tertiary referral hospitals.

AfyaNova V3 defines a **Tier-Scalable Procedure Architecture** (`App\Domains\Procedure`) that operates across two primary facility modes:

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                     AFYANOVA PROCEDURES & SURGICAL DOMAIN                               │
│              [ProcedureCatalog, ProcedureOrder, ProcedureExecution, ConsumableUsed]     │
└────────────────────────────────────────────┬────────────────────────────────────────────┘
                                             │
               ┌─────────────────────────────┴─────────────────────────────┐
               ▼                                                           ▼
┌──────────────────────────────────────────────┐  ┌──────────────────────────────────────────────┐
│          TIER 1: DISPENSARY / OPD            │  │           TIER 2: THEATRE / HOSPITAL         │
│      Dressing & Minor Procedure Desk         │  │         Operating Theatre & Surgical Suite   │
├──────────────────────────────────────────────┤  ├──────────────────────────────────────────────┤
│ • Facility: Dispensary / Clinic / OPD        │  │ • Facility: Health Centre / Hospital         │
│ • Roles: Nurse, Clinical Officer (CO), MO    │  │ • Roles: Surgeon, Anesthetist, Scrub Nurse   │
│ • Procedures: Wound dressing, suturing, I&D, │  │ • Procedures: Major & Minor Surgeries,        │
│   catheterization, cannulation, injections,  │    C-Sections, Laparotomy, Orthopedic Surgeries │
│   foreign body removal, circumcision (VMMC)  │  │ • Workflows: Suite/Table Scheduling,         │
│ • Workflows: Dressing Queue, Wound Telemetry,│    WHO Checklist (Time-Out), Anesthesia Protocol│
│   Consumables Stock Deduction, 1-Click Bill  │    Intra-op Blood Loss, PACU Aldrete Scoring    │
└──────────────────────────────────────────────┘  └──────────────────────────────────────────────┘
```

---

## 2. Tanzanian Health Facility Tier Mapping

| Health Facility Tier | Physical Procedure Infrastructure | Key Clinical Cadres | Typical Procedure Scope |
| :--- | :--- | :--- | :--- |
| **Dispensary (*Zahanati*)** | Chumba cha Sindano na Vidonda (Dressing / Injection Room) | Clinical Officer (CO), Assistant Clinical Officer (ACO), Enrolled Nurse | Wound dressing, minor laceration suturing, Incision & Drainage (I&D) of superficial abscesses, IV cannulation, catheterization, male circumcision (VMMC), foreign body extraction. |
| **Health Centre (*Kituo cha Afya*)** | Minor Procedure Room + Emergency Obstetric Minor Theatre | Medical Officer (MO), Assistant Medical Officer (AMO), Clinical Officer, Nurse-Midwife | All Dispensary procedures + Emergency Caesarean Sections, uterine evacuation (MVA), tubal ligation, appendectomy, hernia repair. |
| **District / Regional Hospital (*Hospitali ya Wilaya / Mkoa*)** | Dedicated OPD Dressing Room + Multi-Suite Major Operating Theatres + PACU | Specialist Surgeons, Medical Officers, Anesthesiologists, Anesthetist Technicians, Scrub/Circulating Nurses | Full elective and emergency surgeries (General, Orthopedic, OB/GYN, ENT, Ophthalmology) with formal anesthesia monitoring and recovery telemetry. |

---

## 3. End-to-End Procedure Lifecycles

### 3.1. Tier 1 Lifecycle: Dressing Room & Minor Procedures (Dispensary / OPD)

```
[1. Clinical Consultation]
        │ Doctor / Clinical Officer diagnoses condition (e.g. Abscess, Infected Wound, Laceration)
        │ Places Procedure Order: "Incision & Drainage" or "Wound Dressing with Povidone-Iodine"
        ▼
[2. Dressing Room Queue]
        │ Routes to Dressing & Minor Procedure Room Desk
        │ Patient called into Chumba cha Vidonda
        ▼
[3. Procedure Execution & Consumable Consumption]
        │ Performed by: Nurse or Clinical Officer
        │ Local Anesthesia: Lignocaine 2% (if needed)
        │ Documents: Wound size, pus drainage, type of dressing/suture
        │ Consumables Logged: 2x Sterile Gauze, 1x Pair Surgical Gloves, 10ml Betadine, 1x Suture 2-0
        ▼
[4. Automatic Inventory Stock Deduction]
        │ Generates immutable StockMovement (Dispense/Consumption) from Dispensary/OPD Stock
        ▼
[5. Billing & NHIF Synchronization]
        │ Procedure tariff and billable materials added to Cash POS Invoice or NHIF Claim Form
        ▼
[6. Follow-up & Discharge]
        │ Return date scheduled (e.g., "Return in 3 days for dressing change and wound review")
```

---

### 3.2. Tier 2 Lifecycle: Major Operating Theatre & Surgical Care (Hospital)

```
[1. Surgical Case Decision & Inpatient Booking]
        │ Surgeon / MO books surgery: "Elective Cholecystectomy" or "Emergency C-Section"
        │ Assigns: Operating Room Suite, Table, Date/Time, Surgical Team (Surgeon, Anesthetist, Scrub Nurse)
        ▼
[2. Pre-Operative Assessment & Consents]
        │ Anesthesia pre-op score (ASA Grade I-V), Signed Informed Consent, Blood Cross-Match Verification
        ▼
[3. WHO Surgical Safety Checklist]
        │ 1. Sign-In (Before induction of anesthesia)
        │ 2. Time-Out (Before skin incision — patient ID, procedure, antibiotics, fire risk)
        │ 3. Sign-Out (Before patient leaves theatre — instrument/sponge counts, specimen labeling)
        ▼
[4. Intra-Operative Anesthesia & Surgical Notes]
        │ Anesthesia type (General, Spinal, Epidural, Regional), vitals tracking
        │ Incision type, operative findings, blood loss telemetry, implants used
        ▼
[5. PACU Post-Anesthesia Recovery Telemetry]
        │ Aldrete Recovery Score monitoring (Consciousness, Respiration, Circulation, Activity, O2 Saturation)
        │ Threshold to discharge back to Surgical Inpatient Ward (Score ≥ 9/10)
```

---

## 4. Domain Entities & Database Schema

### 4.1. Core Procedures Tables (`App\Domains\Procedure`)

```sql
-- 1. Master Procedure Catalog
CREATE TABLE procedure_catalogs (
    id ULID PRIMARY KEY,
    tenant_id VARCHAR NOT NULL,
    procedure_code VARCHAR NOT NULL, -- e.g. PROC-DRS-001, PROC-IND-001, SURG-CS-001
    name VARCHAR NOT NULL,
    category VARCHAR NOT NULL, -- Dressing, MinorSurgery, Injection, MajorSurgery, Orthopedic, OBGYN
    tier_level VARCHAR NOT NULL DEFAULT 'Tier1_Minor', -- Tier1_Minor, Tier2_MajorTheatre
    default_duration_minutes INT NOT NULL DEFAULT 20,
    standard_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    requires_consent BOOLEAN NOT NULL DEFAULT FALSE,
    requires_anesthesia BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 2. Bill of Consumable Materials Template for Procedure
CREATE TABLE procedure_consumable_templates (
    id ULID PRIMARY KEY,
    tenant_id VARCHAR NOT NULL,
    procedure_catalog_id ULID NOT NULL REFERENCES procedure_catalogs(id) ON DELETE CASCADE,
    product_item_id ULID NOT NULL REFERENCES product_items(id) ON DELETE CASCADE,
    default_quantity DECIMAL(8, 2) NOT NULL DEFAULT 1.00,
    is_mandatory BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 3. Procedure Orders (Placed by Clinician)
CREATE TABLE procedure_orders (
    id ULID PRIMARY KEY,
    tenant_id VARCHAR NOT NULL,
    order_number VARCHAR NOT NULL UNIQUE, -- e.g. PR-2026-0001
    encounter_id ULID NOT NULL REFERENCES encounters(id) ON DELETE CASCADE,
    patient_id ULID NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
    ordering_provider_id ULID REFERENCES users(id) ON DELETE SET NULL,
    procedure_catalog_id ULID NOT NULL REFERENCES procedure_catalogs(id) ON DELETE CASCADE,
    priority VARCHAR NOT NULL DEFAULT 'Routine', -- Routine, Urgent, Emergency
    clinical_indication TEXT,
    status VARCHAR NOT NULL DEFAULT 'Ordered', -- Ordered, InProgress, Completed, Cancelled
    ordered_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 4. Procedure Execution Record (Dressing Room or Theatre Notes)
CREATE TABLE procedure_executions (
    id ULID PRIMARY KEY,
    tenant_id VARCHAR NOT NULL,
    procedure_order_id ULID NOT NULL REFERENCES procedure_orders(id) ON DELETE CASCADE,
    performed_by_id ULID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    assistant_id ULID REFERENCES users(id) ON DELETE SET NULL,
    execution_setting VARCHAR NOT NULL DEFAULT 'DressingRoom', -- DressingRoom, MinorTheatre, MajorTheatre
    anesthesia_type VARCHAR DEFAULT 'Local', -- None, Local, Spinal, General, Sedation
    wound_condition VARCHAR, -- Clean, Contaminated, Purulent, Granulating, Epithelializing
    findings_and_technique TEXT NOT NULL,
    post_procedure_instructions TEXT,
    follow_up_date DATE,
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 5. Consumables Actually Used / Stock Deductions
CREATE TABLE procedure_consumables_used (
    id ULID PRIMARY KEY,
    tenant_id VARCHAR NOT NULL,
    procedure_execution_id ULID NOT NULL REFERENCES procedure_executions(id) ON DELETE CASCADE,
    product_item_id ULID NOT NULL REFERENCES product_items(id) ON DELETE RESTRICT,
    stock_batch_id ULID REFERENCES stock_batches(id) ON DELETE SET NULL,
    quantity_used DECIMAL(8, 2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    is_billed_to_patient BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 6. Major Theatre Operating Suites (Tier 2 Only)
CREATE TABLE operating_suites (
    id ULID PRIMARY KEY,
    tenant_id VARCHAR NOT NULL,
    facility_id ULID NOT NULL REFERENCES facilities(id) ON DELETE CASCADE,
    name VARCHAR NOT NULL, -- e.g. Major Theatre 1, Maternity OT, Minor Theatre
    suite_code VARCHAR NOT NULL,
    suite_type VARCHAR NOT NULL DEFAULT 'Major', -- Major, Minor, Obstetric, Endoscopy
    status VARCHAR NOT NULL DEFAULT 'Available', -- Available, Occupied, Cleaning, Maintenance
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 7. Surgical Bookings & Schedules (Tier 2 Only)
CREATE TABLE surgical_bookings (
    id ULID PRIMARY KEY,
    tenant_id VARCHAR NOT NULL,
    booking_number VARCHAR NOT NULL UNIQUE,
    procedure_order_id ULID NOT NULL REFERENCES procedure_orders(id) ON DELETE CASCADE,
    operating_suite_id ULID NOT NULL REFERENCES operating_suites(id) ON DELETE RESTRICT,
    lead_surgeon_id ULID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    anesthetist_id ULID REFERENCES users(id) ON DELETE SET NULL,
    scrub_nurse_id ULID REFERENCES users(id) ON DELETE SET NULL,
    scheduled_start TIMESTAMP NOT NULL,
    scheduled_end TIMESTAMP NOT NULL,
    urgency VARCHAR NOT NULL DEFAULT 'Elective', -- Elective, Urgent, Emergency
    status VARCHAR NOT NULL DEFAULT 'Scheduled', -- Scheduled, InTheatre, PACU, Completed, Cancelled
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 8. WHO Surgical Safety Checklist Audits (Tier 2 Only)
CREATE TABLE who_surgical_checklists (
    id ULID PRIMARY KEY,
    tenant_id VARCHAR NOT NULL,
    surgical_booking_id ULID NOT NULL REFERENCES surgical_bookings(id) ON DELETE CASCADE,
    sign_in_completed_at TIMESTAMP,
    sign_in_verified_by ULID REFERENCES users(id),
    time_out_completed_at TIMESTAMP,
    time_out_verified_by ULID REFERENCES users(id),
    sign_out_completed_at TIMESTAMP,
    sign_out_verified_by ULID REFERENCES users(id),
    sponge_and_needle_count_correct BOOLEAN DEFAULT TRUE,
    specimens_labeled_correctly BOOLEAN DEFAULT TRUE,
    checklist_data JSON, -- Detailed question checklist states
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 9. PACU Post-Anesthesia Aldrete Recovery Telemetry (Tier 2 Only)
CREATE TABLE pacu_recovery_records (
    id ULID PRIMARY KEY,
    tenant_id VARCHAR NOT NULL,
    surgical_booking_id ULID NOT NULL REFERENCES surgical_bookings(id) ON DELETE CASCADE,
    recorded_by_id ULID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    recorded_at TIMESTAMP NOT NULL,
    consciousness_score INT NOT NULL, -- 0 to 2
    activity_score INT NOT NULL,      -- 0 to 2
    respiration_score INT NOT NULL,   -- 0 to 2
    circulation_score INT NOT NULL,   -- 0 to 2
    oxygen_saturation_score INT NOT NULL, -- 0 to 2
    total_aldrete_score INT NOT NULL, -- 0 to 10 (>=9 ready for discharge)
    discharge_ready BOOLEAN NOT NULL DEFAULT FALSE,
    destination_ward_id ULID REFERENCES wards(id) ON DELETE SET NULL,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## 5. Automated Consumables & Stock Integration Invariant

1. **Automatic Double-Entry Stock Movement**:
   When a procedure execution is finalized with logged consumables (`procedure_consumables_used`), the system generates an atomic `StockMovement` entry:
   $$\Delta \text{Stock} = -\text{quantity\_used}$$
   with `movement_type = 'Dispense'`, `reference_type = 'ProcedureExecution'`, referencing the exact batch ID from the local department stock cupboard.
2. **Zero Negative Stock Protection**:
   If stock is insufficient, the system allows clinical override with an inventory variance flag to prevent stopping urgent patient care while alerting the inventory manager.

---

## 6. Financial & Insurance Claims Integration

1. **Cash POS Invoicing**:
   Procedure base fee + billable consumable materials are posted directly to the active `Invoice` under the `Procedure` category.
2. **NHIF & Insurance Claim Itemization**:
   Mapped to standard NHIF procedure code tariffs (e.g. *NHIF Code for Incision & Drainage: TZS 25,000* or *Suturing Laceration: TZS 20,000*) with supporting diagnostic ICD-10 link.
