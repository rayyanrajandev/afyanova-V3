# AfyaNova V3 — Domain Model Specification

This document defines the Domain-Driven Design (DDD) model for **AfyaNova V3**. It details each core bounded context, its responsibilities, aggregates, entities, value objects, domain invariants, state machines, events, and transactional boundaries.

---

## 1. Bounded Context Map & Dependency Graph

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                             Identity & Tenancy                              │
│         [Tenant, Facility, Department, User, Role, Permission, Audit]       │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │ (scopes all domains)
        ┌──────────────────────────────┼──────────────────────────────┐
        ▼                              ▼                              ▼
┌──────────────────┐          ┌──────────────────┐          ┌──────────────────┐
│     Patient      │          │    Inventory     │          │  Charge Catalog  │
│ [Patient, ID,    │          │ [Product, Batch, │          │ [Service, Fee,   │
│  Demographics]   │          │  Stock, Movement]│          │  Tariff, Scheme] │
└────────┬─────────┘          └────────┬─────────┘          └────────┬─────────┘
         │                             │                             │
         ▼                             │                             │
┌──────────────────┐                   │                             │
│ Clinical / Enc.  │                   │                             │
│ [Encounter, Obs, │                   │                             │
│  Orders, Notes]  │                   │                             │
└────┬────┬───┬────┘                   │                             │
     │    │   │                        │                             │
     ▼    │   ▼                        ▼                             ▼
┌─────────┤ ┌──────────────┐     ┌──────────────┐          ┌──────────────────┐
│  Lab /  │ │  Inpatient   │     │   Pharmacy   │          │     Billing      │
│ Rad     │ │ [Admission,  │     │ [Prescript., │─────────►│ [Invoice, Charge,│
│ [Order, │ │  Bed, Ward]  │     │  Dispensing] │          │  Payment, Ledger]│
│ Result] │ └──────────────┘     └──────────────┘          └────────┬─────────┘
└─────────┘                                                         │
                                                                    ▼
                                                           ┌──────────────────┐
                                                           │    Insurance     │
                                                           │ [Policy, Claim,  │
                                                           │  Pre-Auth, Remit]│
                                                           └──────────────────┘
```

---

## 2. Core Domain Specifications

---

### Domain 1: Identity, Tenancy & Facility

#### Purpose & Responsibilities
Manages SaaS tenants (health organizations), physical hospital facilities/branches, administrative departments, user authentication, multi-factor security, and facility-scoped role-based access control (RBAC).

#### Key Entities & Value Objects
- **Tenant** (Aggregate Root): `id`, `name`, `slug`, `domain`, `status`, `plan`, `settings`, `created_at`
- **Facility**: `id`, `tenant_id`, `name`, `code`, `facility_type` (Clinic, Hospital, Diagnostic Center), `license_number`, `hfr_code` (Health Facility Registry TZ), `physical_address`, `contact_email`, `contact_phone`, `is_active`
- **Department**: `id`, `facility_id`, `name`, `code`, `department_type` (OPD, IPD, Laboratory, Pharmacy, Billing, Radiology, Administration)
- **User**: `id`, `tenant_id`, `email`, `phone`, `password_hash`, `first_name`, `last_name`, `professional_registration_no` (e.g. MCT number), `status` (Active, Suspended, Deactivated), `two_factor_enabled`
- **UserFacilityAssignment**: `id`, `user_id`, `facility_id`, `is_default`, `status`
- **Role**: `id`, `tenant_id`, `name`, `slug`, `is_system`, `description`
- **Permission**: `id`, `name`, `slug`, `domain`, `description`
- **RoleAssignment**: `id`, `user_id`, `role_id`, `facility_id` (optional scope), `department_id` (optional scope)

#### Business Rules & Invariants
1. A Tenant is the top-level isolation boundary; cross-tenant data access is impossible at both database and application layers.
2. A User belongs to exactly one Tenant, but can be assigned to multiple Facilities within that Tenant.
3. Every operational request must execute within an explicit **Tenant Context** and **Facility Context**.
4. System roles (e.g., Super Administrator, Tenant Admin) cannot be deleted or have their core permissions revoked.
5. Role assignments can be global to a Tenant or scoped to a specific Facility or Department.

#### Key Domain Events
- `TenantCreated`, `FacilityRegistered`, `UserRegistered`, `UserAssignedToFacility`, `RoleAssigned`, `UserAuthenticationFailed`, `UserPasswordReset`

---

### Domain 2: Patient Identity & Demographics

#### Purpose & Responsibilities
Acts as the single source of truth for patient identification, demographic master records, contact details, emergency contacts, relationships (family/guarantor), duplicate detection, and patient record merging.

#### Key Entities & Value Objects
- **Patient** (Aggregate Root): `id`, `tenant_id`, `primary_mrn` (Medical Record Number), `first_name`, `middle_name`, `last_name`, `dob`, `gender` (Male, Female, Other, Unknown), `blood_group`, `marital_status`, `occupation`, `nationality`, `national_id` (NIDA for Tanzania), `passport_no`, `status` (Active, Deceased, Merged), `merged_into_patient_id`, `created_at`
- **PatientIdentifier**: `id`, `patient_id`, `facility_id` (optional), `type` (MRN, NIDA, NHIF_CARD_NO, PASSPORT, VOTER_ID), `identifier_value`, `is_primary`
- **PatientContact**: `id`, `patient_id`, `contact_type` (Primary Mobile, Alternate Mobile, Email, Physical Address), `value`, `is_verified`
- **EmergencyContact**: `id`, `patient_id`, `name`, `relationship`, `phone_number`, `alternative_phone`
- **PatientRelationship**: `id`, `patient_id`, `related_patient_id`, `relationship_type` (Parent, Child, Spouse, Sibling, Guardian)

#### Business Rules & Invariants
1. `primary_mrn` must be unique per Tenant.
2. If `status == Deceased`, no new appointments, admissions, or encounters can be opened for the patient.
3. Merged patients retain their original records with `status = Merged` and point to the winning `merged_into_patient_id`. Historical clinical records remain intact with immutable audit pointers.
4. Patient names must support local naming conventions (First, Middle/Father, Surname).

#### Key Domain Events
- `PatientRegistered`, `PatientDemographicsUpdated`, `PatientIdentifierAdded`, `PatientMerged`, `PatientMarkedDeceased`

---

### Domain 3: Clinical & Encounters

#### Purpose & Responsibilities
Manages the patient clinical journey: encounters (visits), clinical notes, triage vitals, coded diagnoses, allergies, problem lists, care plans, and clinical orders (lab, radiology, procedures, pharmacy).

#### Key Entities & Value Objects
- **Encounter** (Aggregate Root): `id`, `tenant_id`, `facility_id`, `patient_id`, `provider_id` (Clinician User ID), `encounter_type` (Outpatient, Inpatient, Emergency, Teleconsultation), `class` (AMB, IMP, EMER), `priority` (Routine, Urgent, Emergency), `started_at`, `ended_at`, `status` (Planned, InProgress, OnHold, Completed, Cancelled)
- **VitalSign**: `id`, `encounter_id`, `patient_id`, `measured_by_user_id`, `recorded_at`, `systolic_bp`, `diastolic_bp`, `heart_rate`, `respiratory_rate`, `temperature_celsius`, `spo2_percentage`, `weight_kg`, `height_cm`, `bmi` (computed), `pain_scale` (0-10), `blood_glucose`, `amendment_reason`
- **ClinicalNote**: `id`, `encounter_id`, `patient_id`, `author_id`, `note_type` (Subjective, Objective, Assessment, Plan, Discharge Summary, Operative Note), `content`, `signed_at`, `is_amendment`, `amended_note_id`
- **Diagnosis**: `id`, `encounter_id`, `patient_id`, `diagnosed_by_user_id`, `icd10_code`, `icd10_title`, `diagnosis_type` (Primary, Secondary, Differential, Working), `certainty` (Suspected, Confirmed, Refuted), `recorded_at`
- **Allergy**: `id`, `patient_id`, `causative_agent` (Medication, Food, Environmental), `severity` (Mild, Moderate, Severe, LifeThreatening), `reaction_description`, `status` (Active, Inactive, Resolved)
- **ClinicalOrder** (Base Order): `id`, `encounter_id`, `patient_id`, `orderer_id`, `order_type` (Laboratory, Radiology, Procedure, Pharmacy), `order_number`, `priority` (Routine, Stat, Urgent), `status` (Draft, Placed, InProgress, Completed, Cancelled, Discontinued), `clinical_indication`

#### Business Rules & Invariants
1. Once a `ClinicalNote` or `VitalSign` is signed or recorded, it is **immutable**. Corrections must create an explicit amendment record pointing to the original record with a documented clinical rationale (`amendment_reason`).
2. An Encounter cannot be marked `Completed` while critical clinical orders are in a `Draft` or unhandled state without explicit clinician confirmation.
3. Every order placed within an Encounter triggers a corresponding charge event in the Billing domain (if billable).
4. Known patient allergies must trigger automated clinical safety warnings during order entry and prescription drafting.

#### Key Domain Events
- `EncounterStarted`, `EncounterCompleted`, `VitalsRecorded`, `ClinicalNoteSigned`, `DiagnosisAdded`, `ClinicalOrderPlaced`, `ClinicalOrderCancelled`

---

### Domain 4: Scheduling & Patient Queue

#### Purpose & Responsibilities
Manages provider availability calendars, appointment bookings, queue ticketing, triage prioritization, room assignments, and patient flow across facility service points (Reception $\to$ Triage $\to$ Consultation $\to$ Lab $\to$ Pharmacy $\to$ Cashier).

#### Key Entities & Value Objects
- **Appointment**: `id`, `tenant_id`, `facility_id`, `patient_id`, `practitioner_id`, `department_id`, `scheduled_start`, `scheduled_end`, `appointment_type` (FirstVisit, FollowUp, Routine, Specialized), `status` (Scheduled, Confirmed, Arrived, InConsultation, Completed, NoShow, Cancelled), `cancellation_reason`
- **QueueTicket**: `id`, `tenant_id`, `facility_id`, `department_id`, `service_point` (Triage, Doctor1, LabSampling, Cashier), `patient_id`, `ticket_number` (e.g. `OPD-042`), `priority` (Emergency, Priority, Normal), `status` (Waiting, Called, Serving, Completed, Transferred, Skipped), `issued_at`, `called_at`, `completed_at`
- **PractitionerSchedule**: `id`, `facility_id`, `practitioner_id`, `day_of_week`, `start_time`, `end_time`, `slot_duration_minutes`, `max_patients`

#### Business Rules & Invariants
1. Queue tickets strictly prioritize Emergency priority over Urgent and Routine.
2. Double-booking practitioner slots requires explicit administrative authorization.
3. Queue status transitions must be monotonic (`Waiting` $\to$ `Called` $\to$ `Serving` $\to$ `Completed`).

---

### Domain 5: Inpatient & Bed Management

#### Purpose & Responsibilities
Tracks inpatient admissions, ward and bed capacity, bed transfers, daily bed charges, nursing care plans, medication administration records (MAR), and discharge summaries.

#### Key Entities & Value Objects
- **Ward**: `id`, `tenant_id`, `facility_id`, `name`, `code`, `gender_restriction` (MaleOnly, FemaleOnly, Mixed, Pediatric), `ward_type` (General, Private, ICU, Maternity, Surgical), `is_active`
- **Bed**: `id`, `ward_id`, `bed_number`, `bed_type` (Standard, ICU, Electric, Bassinet), `daily_rate_amount`, `status` (Available, Occupied, Maintenance, Reserved, Cleaning)
- **Admission**: `id`, `encounter_id`, `patient_id`, `admitting_doctor_id`, `admitted_at`, `discharged_at`, `initial_bed_id`, `current_bed_id`, `discharge_disposition` (Home, Transferred, Deceased, AgainstMedicalAdvice), `discharge_condition`
- **BedTransfer**: `id`, `admission_id`, `from_bed_id`, `to_bed_id`, `transferred_at`, `transferred_by_user_id`, `reason`
- **MedicationAdministrationRecord (MAR)**: `id`, `admission_id`, `prescription_item_id`, `scheduled_time`, `administered_time`, `administered_by_user_id`, `dose_given`, `status` (Given, Refused, Held, Missed), `notes`

#### Business Rules & Invariants
1. A Bed cannot be occupied by more than one active Admission simultaneously.
2. Bed transfers immediately update the source bed to `Cleaning` or `Available` and the destination bed to `Occupied`.
3. Daily bed billing charges are calculated automatically via end-of-day scheduled tasks based on active bed occupancy.

---

### Domain 6: Laboratory & Diagnostics

#### Purpose & Responsibilities
Handles laboratory test definitions, panels, sample collection, barcoding, specimen processing, analyzer result ingestion, reference range evaluation, panic/critical value alerting, technician verification, and final report sign-off.

#### Key Entities & Value Objects
- **LabTestCatalog**: `id`, `tenant_id`, `test_code`, `test_name`, `category` (Hematology, Biochemistry, Microbiology, Parasitology, Immunology), `loinc_code`, `specimen_type` (WholeBlood, Serum, Urine, Stool, CSF), `turnaround_time_minutes`, `is_panel`
- **LabTestParameter**: `id`, `lab_test_id`, `parameter_name`, `unit_of_measure`, `data_type` (Numeric, Text, SelectOption), `default_loinc`
- **LabReferenceRange**: `id`, `parameter_id`, `gender` (All, Male, Female), `min_age_days`, `max_age_days`, `range_low`, `range_high`, `critical_low`, `critical_high`, `normal_text_value`
- **LabOrder**: `id`, `clinical_order_id`, `encounter_id`, `patient_id`, `order_number`, `priority`, `status` (Ordered, SampleCollected, Processing, Verified, Rejected, Completed)
- **Specimen**: `id`, `lab_order_id`, `barcode`, `specimen_type`, `collected_at`, `collected_by_user_id`, `received_at_lab`, `condition` (Satisfactory, Hemolyzed, Clotted, InsufficientVolume), `status` (Collected, Received, Rejected, Processed)
- **LabResultItem**: `id`, `lab_order_id`, `parameter_id`, `numeric_value`, `text_value`, `is_abnormal`, `is_critical`, `status` (Preliminary, Final, Amended), `entered_by_user_id`, `verified_by_user_id`, `verified_at`

#### Business Rules & Invariants
1. Results cannot be released or viewed in the patient clinical chart until verified by a certified laboratory technician or pathologist (unless emergency override is flagged).
2. Critical/Panic values (`value < critical_low` or `value > critical_high`) immediately trigger a high-priority clinician notification.
3. Once verified, result modifications must be recorded as an amended result with the original value preserved.

---

### Domain 7: Pharmacy & Medication Management

#### Purpose & Responsibilities
Governs medication formulary, prescription writing, drug interaction/allergy checks, prescription dispensing, dosage calculations, and medication return workflows.

#### Key Entities & Value Objects
- **Prescription** (Aggregate Root): `id`, `clinical_order_id`, `encounter_id`, `patient_id`, `prescriber_id`, `prescription_number`, `status` (Pending, PartiallyDispensed, FullyDispensed, Cancelled), `prescribed_at`
- **PrescriptionItem**: `id`, `prescription_id`, `inventory_item_id`, `drug_name`, `dosage`, `unit`, `route` (Oral, IV, IM, Topical, Inhalation), `frequency` (e.g. `TID`, `BID`, `QID`, `PRN`), `duration_days`, `total_quantity_prescribed`, `dispensed_quantity`, `special_instructions`, `substitution_allowed`
- **DispenseRecord**: `id`, `prescription_id`, `dispensing_pharmacist_id`, `dispensed_at`, `status` (Dispensed, Returned)
- **DispenseItem**: `id`, `dispense_record_id`, `prescription_item_id`, `stock_batch_id`, `quantity_dispensed`, `unit_cost_price`, `unit_selling_price`, `dispense_fee`

#### Business Rules & Invariants
1. Dispensing medications triggers an atomic inventory movement (deduction) from the designated pharmacy stock location.
2. Dispensing must follow FEFO (First-Expired, First-Out) by default; override requires documented reason.
3. Dispensed quantity cannot exceed the prescribed quantity without an explicit supplemental prescription.

---

### Domain 8: Inventory, Materials Management & Procurement

#### Purpose & Responsibilities
Maintains enterprise physical stock tracking across all hospital material categories (Pharmaceuticals, Surgical Consumables, Lab Reagents, IPC Chemicals, Linen, Stationery & MTUHA Registers, Medical Gases, Nutrition Rations, and Fixed Assets), multi-warehouse hierarchy, internal departmental requisitions (Store Indents), packaging/dispensing UOM conversions, supplier procurement (MSD & DLP), batch/lot tracking, cold chain monitoring, DDA narcotics control, CSSD sterile pack lifecycle, and perpetual double-entry inventory ledger movements.

#### Key Entities & Value Objects
- **ItemMaster** (Aggregate Root): `id`, `tenant_id`, `item_code`, `name`, `generic_name`, `category` (Pharmaceutical, SurgicalConsumable, LabReagent, IpcChemical, Linen, StationeryMtuha, MedicalGas, NutritionFood, FixedAsset), `base_dispensing_uom_id`, `reorder_level`, `safety_stock`, `is_billable`, `is_active`
- **UnitOfMeasure & Conversion**: `id`, `name`, `abbreviation` (Box, Pack, Drum, Piece, Litre, Vial, Roll), `from_uom_id`, `to_uom_id`, `conversion_factor`
- **InventoryLocation**: `id`, `facility_id`, `name`, `code`, `location_type` (CentralWarehouse, PharmacyStore, WardCabinet, TheatreStore, LabStore, GeneralStore), `is_dispensing_enabled`, `is_storage_only`, `cost_center_id`
- **DepartmentRequisition (Store Indent)**: `id`, `tenant_id`, `facility_id`, `requisition_number`, `source_location_id` (Central Store), `destination_location_id` (Ward/Unit Sub-Store), `department_id`, `status` (Draft, Submitted, Approved, Dispatched_In_Transit, Received_Confirmed, Disputed), `requested_by`, `approved_by`, `dispatched_by`, `received_by`
- **InventoryBatch**: `id`, `item_id`, `batch_number`, `manufacturing_date`, `expiry_date`, `unit_cost_price`, `unit_selling_price`, `cold_chain_required`, `status` (Active, Quarantined, Expired)
- **InventoryStockBalance**: `id`, `location_id`, `item_id`, `batch_id`, `quantity_on_hand`, `quantity_reserved`, `reorder_level`
- **StockMovement** (Immutable Double-Entry Ledger): `id`, `tenant_id`, `facility_id`, `movement_type` (GoodsReceipt, TransferIn, TransferOut, Dispense, Consumption, ExpensedOverhead, AdjustmentPos, AdjustmentNeg, DiscardExpired, ReturnSupplier), `source_location_id`, `destination_location_id`, `batch_id`, `quantity`, `unit_cost`, `reference_type` (PurchaseOrder, Requisition, DispenseEvent, ProcedureExecution, StocktakeSession), `reference_id`, `performed_by`, `created_at`
- **PurchaseOrder**: `id`, `facility_id`, `supplier_id`, `po_number`, `procurement_stream` (MSD, DLP_PrimeVendor), `order_date`, `status` (Draft, Submitted, Approved, Partially_Received, Completed, Cancelled), `total_amount`
- **GoodsReceiptNote (GRN)**: `id`, `purchase_order_id`, `supplier_id`, `grn_number`, `supplier_invoice_number`, `delivery_note_number`, `received_date`, `total_received_value`, `received_by`, `verified_by`
- **StocktakeSession**: `id`, `facility_id`, `location_id`, `session_number`, `status` (In_Progress, Review_Pending, Approved_Reconciled), `initiated_by`, `approved_by`, `reconciled_at`
- **DdaRegisterLog**: `id`, `tenant_id`, `batch_id`, `encounter_id`, `patient_id`, `prescriber_id`, `administering_nurse_id`, `dose_administered`, `dose_wasted_discarded`, `balance_remaining`, `witness_user_id`

#### Business Rules & Invariants
1. **Zero Negative Stock Invariant**: Physical stock balance at any valid location cannot drop below zero. Attempting to overdraft throws `InsufficientStockException`.
2. **Double-Entry Stock Movement**: Stock quantity on hand is always the exact mathematical sum of all historical immutable `StockMovement` records for that batch and location.
3. **FEFO Enforcement**: Automatic batch allocation prioritizes the earliest expiring batch. Expired batches are locked from clinical dispensing.
4. **Two-Step Requisition Handshake**: Transferring items from Central Store to a Ward Cabinet requires a dispatch (`TRANSFER_OUT`) followed by receiving confirmation (`TRANSFER_IN`) by the ward nurse to guarantee no stock disappearance in transit.
5. **Consumption Accounting Split**: Chargeable items consumed during procedures/encounters are billed to the patient invoice; non-chargeable items (disinfectants, stationery) are expensed directly against the departmental cost center.

---

### Domain 9: Billing, Pricing & Financial Ledger

#### Purpose & Responsibilities
Controls the master charge catalog (tariffs, price lists for cash, corporate, and insurance schemes), automated fee capture from clinical workflows, patient invoicing, payment processing, deposit accounts, refunds, discounts, write-offs, and double-entry financial ledger accounting.

#### Key Entities & Value Objects
- **ChargeMasterItem**: `id`, `tenant_id`, `code`, `name`, `department_id`, `category` (Consultation, LabTest, RadiologyScan, Procedure, BedCharge, NursingFee), `base_cash_price`, `is_taxable`, `tax_rate_percentage`, `is_active`
- **PriceListTariff**: `id`, `tenant_id`, `name`, `tariff_type` (Cash, InsuranceScheme, CorporateContract), `insurance_provider_id` (optional), `currency` (TZS, USD)
- **TariffItemPrice**: `id`, `tariff_id`, `charge_master_item_id`, `price_amount`, `co_pay_fixed`, `co_pay_percentage`, `pre_auth_required`
- **Charge**: `id`, `tenant_id`, `facility_id`, `encounter_id`, `patient_id`, `charge_master_item_id`, `quantity`, `unit_price`, `gross_amount`, `discount_amount`, `tax_amount`, `net_amount`, `payer_type` (Cash, Insurance, Corporate, Exemption), `status` (Pending, Invoiced, Cancelled), `source_type` (Order, Bed, Consultation), `source_id`
- **Invoice** (Aggregate Root): `id`, `tenant_id`, `facility_id`, `encounter_id`, `patient_id`, `invoice_number`, `invoice_date`, `gross_total`, `discount_total`, `tax_total`, `net_total`, `amount_paid`, `amount_covered_insurance`, `balance_due`, `status` (Draft, Issued, PartiallyPaid, Paid, ClaimSubmitted, Closed, Voided)
- **InvoiceLineItem**: `id`, `invoice_id`, `charge_id`, `item_description`, `quantity`, `unit_price`, `patient_portion`, `insurance_portion`, `discount_amount`, `net_total`
- **Payment**: `id`, `tenant_id`, `facility_id`, `invoice_id`, `payment_number`, `amount`, `currency`, `payment_method` (Cash, M-Pesa, AirtelMoney, TigoPesa, CreditCard, BankTransfer), `provider_transaction_ref`, `cashier_user_id`, `received_at`, `status` (Success, Pending, Failed, Refunded)
- **FinancialLedgerEntry** (Double-Entry Ledger): `id`, `tenant_id`, `facility_id`, `transaction_date`, `account_code`, `debit_amount`, `credit_amount`, `entity_type` (Invoice, Payment, Refund, WriteOff), `entity_id`, `description`

#### Business Rules & Invariants
1. **Financial Balance Invariant**: `Invoice.net_total == Invoice.amount_paid + Invoice.amount_covered_insurance + Invoice.balance_due`.
2. An issued Invoice cannot be edited in place or deleted. Financial corrections require an explicit Credit Note, Debit Note, or authorized Write-off with an audit log.
3. Every payment receipt and invoice issuance creates balancing debits and credits on the `FinancialLedgerEntry` table.

---

### Domain 10: Insurance & Claims Management

#### Purpose & Responsibilities
Manages insurance company profiles, health schemes, member policy cards, eligibility verification, pre-authorization requests, claim batching, electronic claim generation, claims adjudication responses, remittance advice, and short-payment reconciliation.

#### Key Entities & Value Objects
- **InsuranceProvider** (Aggregate Root): `id`, `tenant_id`, `name`, `code` (e.g. `NHIF`, `JUBILEE`, `STRATEGIS`, `AAR`), `portal_url`, `api_adapter_class`, `is_active`
- **InsuranceScheme**: `id`, `insurance_provider_id`, `scheme_name`, `tariff_id`, `coverage_limit_annual`, `requires_biometric_verification`
- **PatientPolicy**: `id`, `patient_id`, `insurance_scheme_id`, `card_number`, `member_number`, `principal_member_name`, `relationship_to_principal`, `valid_from`, `valid_to`, `status` (Active, Expired, Suspended)
- **PreAuthorization**: `id`, `encounter_id`, `patient_policy_id`, `requested_service_id`, `auth_code`, `approved_amount`, `status` (Requested, Approved, Rejected, Expired), `valid_until`
- **Claim** (Aggregate Root): `id`, `tenant_id`, `facility_id`, `invoice_id`, `patient_policy_id`, `claim_number`, `submission_batch_id`, `total_claimed_amount`, `total_approved_amount`, `total_rejected_amount`, `status` (Draft, Submitted, InReview, Approved, PartiallyApproved, Rejected, Remitted), `rejection_reason`
- **ClaimItem**: `id`, `claim_id`, `invoice_line_item_id`, `service_code`, `claimed_amount`, `approved_amount`, `adjudication_status`, `rejection_code`
- **RemittanceAdvice**: `id`, `insurance_provider_id`, `remittance_number`, `payment_reference`, `total_amount_remitted`, `received_date`

#### Business Rules & Invariants
1. Services requiring pre-authorization (e.g., MRI scans, major surgeries, specialized drugs) cannot be processed on an insurance claim without a valid `PreAuthorization` record.
2. Claim status changes (e.g., from `Submitted` to `Approved`) trigger automatic accounts receivable reconciliation entries.
3. The Insurance domain communicates with external insurer APIs exclusively via isolated Adapter drivers.

---

### Domain 11: Audit, Security & Notifications

#### Purpose & Responsibilities
Provides tamper-resistant event logging for compliance and clinical safety, captures all security events, and handles asynchronous messaging (SMS patient reminders, internal staff notifications, panic value alerts).

#### Key Entities & Value Objects
- **AuditLog**: `id`, `tenant_id`, `facility_id`, `user_id`, `event_type` (AUTH, CLINICAL, FINANCIAL, INVENTORY, CONFIG, PATIENT_VIEW), `action` (CREATE, READ, UPDATE, DELETE, SIGN, AMEND, OVERRIDE), `entity_type`, `entity_id`, `before_state` (jsonb), `after_state` (jsonb), `ip_address`, `user_agent`, `created_at`
- **NotificationMessage**: `id`, `tenant_id`, `channel` (SMS, Email, InApp, Push), `recipient_identifier`, `title`, `body`, `status` (Queued, Sent, Delivered, Failed), `sent_at`

---

### Domain 12: Clinical Procedures & Surgical Services

#### Purpose & Responsibilities
Covers the entire spectrum of clinical interventions across Tanzanian facility tiers:
1. **Tier 1 (Dispensary / OPD)**: Dressing & Minor Procedure Room (*Chumba cha Vidonda na Sindano*) managed by Nurses, Clinical Officers, and Medical Officers (wound cleaning/dressing, suturing, abscess I&D, catheterization, cannulation, circumcision, foreign body extraction) with automatic consumable inventory deduction and POS/NHIF billing.
2. **Tier 2 (Hospital / Theatre)**: Operating Theatre (OT) suite and table scheduling, WHO Surgical Safety Checklists (*Sign-In, Time-Out, Sign-Out*), intra-operative surgical and anesthesia logging, and PACU Aldrete recovery telemetry.

#### Key Entities & Value Objects
- **ProcedureCatalog**: `id`, `tenant_id`, `procedure_code`, `name`, `category` (Dressing, MinorSurgery, Injection, MajorSurgery), `tier_level` (Tier1_Minor, Tier2_MajorTheatre), `standard_price`, `requires_consent`, `requires_anesthesia`
- **ProcedureOrder**: `id`, `encounter_id`, `patient_id`, `ordering_provider_id`, `procedure_catalog_id`, `priority` (Routine, Urgent, Emergency), `status` (Ordered, InProgress, Completed, Cancelled)
- **ProcedureExecution**: `id`, `procedure_order_id`, `performed_by_id`, `execution_setting` (DressingRoom, MinorTheatre, MajorTheatre), `anesthesia_type`, `wound_condition`, `findings_and_technique`, `follow_up_date`
- **ProcedureConsumableUsed**: `id`, `procedure_execution_id`, `product_item_id`, `stock_batch_id`, `quantity_used`, `is_billed_to_patient`
- **OperatingSuite**: `id`, `facility_id`, `name`, `suite_code`, `suite_type` (Major, Minor, Obstetric), `status` (Available, Occupied, Cleaning)
- **SurgicalBooking**: `id`, `procedure_order_id`, `operating_suite_id`, `lead_surgeon_id`, `anesthetist_id`, `scheduled_start`, `scheduled_end`, `status`
- **WhoSurgicalChecklist**: `id`, `surgical_booking_id`, `sign_in_completed_at`, `time_out_completed_at`, `sign_out_completed_at`, `sponge_and_needle_count_correct`
- **PacuRecoveryRecord**: `id`, `surgical_booking_id`, `recorded_by_id`, `consciousness_score`, `activity_score`, `respiration_score`, `circulation_score`, `oxygen_saturation_score`, `total_aldrete_score`, `discharge_ready`

#### Business Rules & Invariants
1. Finalizing a procedure with logged consumable materials atomically decrements inventory via immutable `StockMovement` ledger entries.
2. Tier 2 major surgeries cannot proceed without verified WHO Time-Out checklist sign-off.
3. PACU patients cannot be discharged to general inpatient wards until the total Aldrete Recovery Score reaches $\ge 9/10$.

---

## 3. Inter-Domain Communication & Boundaries

To prevent coupling and architectural drift in our modular monolith:
1. **Direct Synchronous Calls**: A domain calls another domain's explicit **Domain Service Interface** or **Query API** returning immutable DTOs (Data Transfer Objects). Direct Eloquent model cross-joins are forbidden across bounded contexts.
2. **Asynchronous Events**: Cross-domain side-effects (e.g., clinical order generating a bill, lab test completing, stock decrementing) use Laravel's typed Event/Listener system executed via Redis queues.

