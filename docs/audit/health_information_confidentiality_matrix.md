# AfyaNova v3 — Health Information Confidentiality & Role-Based Access Control (RBAC) Specification

> **Document Classification:** Healthcare Compliance & System Security Architecture  
> **Jurisdiction:** United Republic of Tanzania  
> **System:** AfyaNova v3 (Multi-Tenant Hospital Management & Electronic Health Record System)  
> **Governing Standards:** Personal Data Protection Act, 2022 (PDPA); Medical Council of Tanganyika (MCT); Pharmacy Act, 2011; Tanzania Nursing and Midwifery Council (TNMC); Ministry of Health (MoH) Digital Health Guidelines.

---

## 1. Executive Summary & Legal Framework

In Tanzania, digital healthcare systems must strictly uphold patient data privacy and confidential medical records as **Sensitive Personal Data (*Taarifa Binafsi Nyeti*)**. In AfyaNova v3, access to information is governed by the **Principle of Least Privilege**, strict **Segregation of Duties**, and **Role-Based Access Control (RBAC)**.

### Governing Tanzanian Legislation & Standards
1. **Personal Data Protection Act, 2022 (Act No. 11 of 2022)** & **Regulations, 2023:** Mandates purpose limitation, lawful processing, strict data minimization, and accountability through audit logs for all sensitive health and biometric data.
2. **Medical Practitioners and Dentists Act (Cap. 152) & MCT Code of Medical Ethics:** Establishes statutory doctor-patient confidentiality and ethical non-disclosure of clinical information without patient consent or court order.
3. **Pharmacy Act, 2011 & Pharmacy Council Guidelines:** Regulates confidentiality around electronic prescriptions, drug dispensing history, and the Dangerous Drugs Act (DDA) narcotic registers.
4. **Tanzania Nursing and Midwifery Council (TNMC) Guidelines:** Enforces patient confidentiality at triage desks, bedside Medication Administration Records (MAR), and maternity/labour wards.
5. **Ministry of Health (MoH) Tanzania National Digital Health Strategy:** Mandates role separation between administrative, clinical, diagnostic, and financial domains in hospital information systems.

---

## 2. Core Architectural Confidentiality Principles

* **Administrative vs. Clinical Segregation:** Registration and reception staff capture identity and queue patients without visibility into clinical notes, diagnoses, or lab results.
* **Financial vs. Diagnostic Separation:** Cashiers and billing staff process financial ledger entries using standardized Charge Master codes without access to private physician clinical narratives.
* **Supply Chain Isolation:** Inventory and warehouse officers manage SKU counts and batch logistics with zero access to individual patient identities or medical charts.
* **Clinical Immutability:** Finalized clinical notes, verified lab reports, and signed radiology results cannot be edited or deleted in place. Corrections require timestamped legal addenda (*amendments*) preserving the complete historical record.
* **Auditing & Break-Glass Protocol:** Every view, export, and record creation is logged immutably in `audit_logs`. In life-threatening emergencies, authorized clinicians can invoke an emergency **Break-Glass** override, which triggers real-time security alerts and compulsory clinical justification logging.

---

## 3. Comprehensive Role-by-Role Confidentiality Matrix

| System Domain / Record Type | Receptionist | Nurse | Doctor / Clinician | Lab Technologist | Radiologist | Pharmacist | Cashier | Insurance Manager | Inventory Officer | Medical Auditor | Tenant Admin |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Patient Demographics (NIDA, MRN, Name, Contacts)** | **Full** | **Full** | **Full** | **Full** | **Full** | **Full** | **Full** | **Full** | *No Access* | Read-Only | Read-Only |
| **Clinical SOAP Notes & Progress Records** | *No Access* | Triage/Nursing Notes | **Full (Author/Sign)** | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | Read-Only | *No Access* |
| **ICD-10 Diagnoses & Active Problem Lists** | *No Access* | Read-Only | **Full (Manage)** | *No Access* | *No Access* | Read-Only | *No Access* | Read-Only (Claims) | *No Access* | Read-Only | *No Access* |
| **Patient Drug Allergies & Adverse Reactions** | *No Access* | **Full (Record/View)** | **Full (Record/View)** | *No Access* | Read-Only | **Full (Verify)** | *No Access* | *No Access* | *No Access* | Read-Only | *No Access* |
| **Triage Vitals (BP, Temp, SpO2, BMI)** | *No Access* | **Full (Record/View)** | **Full (Record/View)** | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | Read-Only | *No Access* |
| **Lab Orders & Specimen Barcodes** | *No Access* | Read-Only | **Full (Order/View)** | **Full (Collect/Process)** | *No Access* | *No Access* | *No Access* | Item Code Only | *No Access* | Read-Only | *No Access* |
| **Lab Results & Critical Value Alerts** | *No Access* | Read-Only | **Full (Review/Act)** | **Full (Enter/Verify)** | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | Read-Only | *No Access* |
| **Radiology Imaging Orders & PACS Studies** | *No Access* | Read-Only | **Full (Order/View)** | *No Access* | **Full (Acquire/View)** | *No Access* | *No Access* | Item Code Only | *No Access* | Read-Only | *No Access* |
| **Radiology Diagnostic Reports** | *No Access* | Read-Only | **Full (Review/Act)** | *No Access* | **Full (Author/Sign)** | *No Access* | *No Access* | *No Access* | *No Access* | Read-Only | *No Access* |
| **Prescription Authoring & e-Prescribing** | *No Access* | Read-Only | **Full (Prescribe)** | *No Access* | *No Access* | Read-Only | *No Access* | Item Code Only | *No Access* | Read-Only | *No Access* |
| **Prescription Verification & Dispensing** | *No Access* | *No Access* | Read-Only | *No Access* | *No Access* | **Full (Dispense)** | *No Access* | Item Code Only | *No Access* | Read-Only | *No Access* |
| **e-MAR (Medication Administration Record)** | *No Access* | **Full (Administer)** | **Full (Review)** | *No Access* | *No Access* | Read-Only | *No Access* | *No Access* | *No Access* | Read-Only | *No Access* |
| **DDA Narcotic Register (Controlled Drugs)** | *No Access* | Ward DDA Log | Prescribe Only | *No Access* | *No Access* | Pharmacy DDA Log | *No Access* | *No Access* | View Balances | Read-Only | *No Access* |
| **RCH Records (ANC Visits & Partographs)** | *No Access* | **Full (Record/View)** | **Full (Manage)** | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | Read-Only | *No Access* |
| **Operating Theatre, WHO Checklist & PACU** | *No Access* | Checklist / PACU | **Full (Book/Operate)** | *No Access* | *No Access* | *No Access* | *No Access* | Item Code Only | Theatre Requisition | Read-Only | *No Access* |
| **Inpatient Wards, Bed Status & Admissions** | *No Access* | **Full (Manage Beds)** | **Full (Admit/Discharge)** | *No Access* | *No Access* | Ward Refill | *No Access* | *No Access* | *No Access* | Read-Only | Config Only |
| **Appointment Scheduling & Queue Routing** | **Full** | Queue Call/Transfer | Queue Call/Transfer | Queue Call | Queue Call | Queue Call | Queue Call | *No Access* | *No Access* | Read-Only | Config Only |
| **Invoices, Bill Generation & POS Payments** | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | **Full (Collect)** | Adjust / Refund | *No Access* | Read-Only | *No Access* |
| **Cashier Till Shifts & Float Reconciliation** | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | **Full (Shift Op/Close)** | Supervise / Audit | *No Access* | Read-Only | *No Access* |
| **Insurance Policies, Pre-Auth & Claims** | *No Access* | *No Access* | Pre-Auth Request | *No Access* | *No Access* | *No Access* | *No Access* | **Full (Batch/Adjudicate)** | *No Access* | Read-Only | *No Access* |
| **Central Warehouse, POs & GRN Receipts** | *No Access* | Dept Requisition | Dept Requisition | Lab Requisition | Rad Requisition | Store Requisition | *No Access* | *No Access* | **Full (Procure/Store)** | Read-Only | Config Only |
| **Security Audit Logs (`audit_logs`)** | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | **Full (Read-Only)** | **Full (Read-Only)** |
| **User Provisioning & Role Assignments** | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | *No Access* | **Full (Admin)** |

---

## 4. Role Descriptions & Detailed Boundaries

### 1. Medical Officer / Clinician (`doctor`)
* **Role Description:** Primary diagnosing and treating physician.
* **Permitted Functions:** Complete medical history charting, authoring and signing SOAP notes, managing ICD-10 diagnoses and problem lists, entering allergy records, requesting laboratory and radiology studies, authoring prescriptions, booking operating suites, performing surgical procedures, signing WHO surgical checklists, executing inpatient admissions/discharges, and raising inter-facility referrals.
* **Confidentiality Restrictions:** Bound by statutory MCT medical confidentiality. Cannot view financial cashier till shifts or modify product procurement orders. May only access records of patients under direct care or utilize auditable emergency Break-Glass protocols.

### 2. Nurse / Triage Officer (`nurse`)
* **Role Description:** Triage screening, vital signs assessment, bedside nursing care, and maternity monitoring.
* **Permitted Functions:** Recording patient vitals, creating triage assessments, calling and routing queue tickets, administering prescribed medications on the e-MAR flowsheet, logging ward-level DDA narcotic administrations, documenting wound dressings, managing inpatient bed allocations, and recording ANC/Partograph entries.
* **Confidentiality Restrictions:** Cannot alter physician medical diagnoses, sign doctor clinical SOAP notes, or prescribe medications. Bound by TNMC ethical privacy rules at triage desks and open wards.

### 3. Laboratory Scientist / Technologist (`lab-technologist`)
* **Role Description:** Specimen analysis, diagnostic bench testing, and laboratory verification.
* **Permitted Functions:** Viewing incoming test orders with clinical indications, logging specimen collection and barcode assignment, recording quantitative/qualitative analyzer test results, flagging panic/critical values, verifying and releasing final lab reports, and configuring laboratory test catalogs.
* **Confidentiality Restrictions:** Restricted from viewing unrelated clinical progress notes, psychiatric history, or billing ledgers. Required to uphold high confidentiality over sensitive diagnostic markers (e.g. HIV, Hepatitis, STIs, genetic tests).

### 4. Radiologist (`radiologist`)
* **Role Description:** Medical imaging acquisition, scan interpretation, and diagnostic reporting.
* **Permitted Functions:** Viewing radiology requisitions, recording study acquisitions, interpreting imaging examinations (X-ray, Ultrasound, CT, MRI), authoring and signing official radiology reports, and submitting formal report addenda/amendments.
* **Confidentiality Restrictions:** Access is confined to imaging requisitions and relevant clinical history necessary for imaging interpretation; cannot alter prescriptions, financial records, or general clinical charts.

### 5. Pharmacist (`pharmacist`)
* **Role Description:** Medication safety validation, clinical dispensing, and pharmacy inventory control.
* **Permitted Functions:** Reviewing electronic prescriptions against patient allergy profiles and active medications, performing drug-drug interaction vetting, recording clinical medication reconciliation, executing batch-tracked FEFO dispensing, managing pharmacy store stock, and maintaining statutory DDA narcotic ledgers under the Pharmacy Act, 2011.
* **Confidentiality Restrictions:** Access to clinical information is limited to medication history, allergies, and relevant diagnoses needed to ensure pharmacotherapeutic safety. Cannot view non-medication clinical notes or financial ledger books.

### 6. Receptionist / Registration Clerk (`receptionist`)
* **Role Description:** Universal patient registration, appointment scheduling, and queue coordination.
* **Permitted Functions:** Creating and updating Master Patient Index (MPI) demographics (NIDA number, Full Name, Date of Birth, Gender, Next of Kin, Phone), creating appointment bookings, checking in appointments, and issuing/calling queue tickets.
* **Confidentiality Restrictions:** **Zero clinical record access.** Prohibited from viewing clinical notes, medical diagnoses, laboratory/imaging results, or prescribed medications.

### 7. Cashier / Billing Officer (`cashier`)
* **Role Description:** Point-of-sale payment processing, invoice settlement, and shift reconciliation.
* **Permitted Functions:** Viewing finalized patient billing invoices, collecting payments via Cash, Bank Card, or Mobile Money (M-Pesa, Airtel Money, Tigo Pesa), issuing official receipts, and opening/closing/reconciling cashier till shifts.
* **Confidentiality Restrictions:** **Zero diagnostic access.** Invoices display standardized billing item categories (Consultation, Lab, Pharmacy, Procedure) without displaying confidential clinical narratives, lab findings, or medical diagnoses.

### 8. Billing & Insurance Manager (`insurance-manager`)
* **Role Description:** Insurance scheme administration, claims scrubbing, pre-authorizations, and financial adjustments.
* **Permitted Functions:** Validating patient health insurance policies (NHIF and private insurers), requesting pre-authorizations, verifying ICD-10 diagnostic codes against billed service items, submitting electronic claims batches, reconciling remittances, managing tariff price schedules, approving discounts, and processing refunds.
* **Confidentiality Restrictions:** Limited to clinical information strictly required for claims adjudication (ICD-10 codes, tariff item codes, and proof of service). Prohibited from accessing unredacted narrative SOAP notes.

### 9. Inventory & Store Officer (`inventory-officer`)
* **Role Description:** Central warehouse logistics, supply chain procurement, and stockkeeping.
* **Permitted Functions:** Managing item catalogs, suppliers (e.g. Medical Stores Department — MSD Tanzania), creating and approving Purchase Orders (POs), posting Goods Receipt Notes (GRN), issuing departmental stock transfers, conducting physical stocktakes, and tracking medical gas cylinders.
* **Confidentiality Restrictions:** **Zero patient data access.** Has no visibility into patient names, MRNs, medical records, or individual patient prescriptions.

### 10. Medical Auditor / Compliance Officer (`auditor`)
* **Role Description:** Quality of care assurance, billing compliance, and security oversight.
* **Permitted Functions:** Performing read-only audits on clinical charts, inpatient stays, and discharge summaries; reviewing billing ledgers and insurance claims for fraud detection; inspecting the system security audit trail (`audit_logs`) to review access logs, failed logins, and emergency break-glass activations.
* **Confidentiality Restrictions:** **Strictly read-only.** Cannot create, modify, sign, or delete any record. Bound by non-disclosure agreements and statutory confidentiality under the PDPA 2022.

### 11. Tenant Administrator (`tenant-admin`)
* **Role Description:** System infrastructure, user identity management, and facility topology governance.
* **Permitted Functions:** Provisioning staff user accounts, configuring Multi-Factor Authentication (TOTP MFA), managing RBAC role assignments, creating facilities, clinics, and wards, and reviewing security audit logs.
* **Confidentiality Restrictions:** Administrative segregation of duties prevents tenant administrators from viewing individual patient medical records or authoring clinical notes.

---

## 5. Security Guardrails & Compliance Enforcements

1. **Row-Level Security & Multi-Tenancy:** Data is strictly isolated per healthcare tenant and facility using database-level Row-Level Security (RLS).
2. **Immutable Audit Logging:** Any attempt to read, export, create, or modify a health record generates an immutable audit record containing:
   * Actor User ID, Assigned Role, and Tenant Context
   * Client IP address and User Agent
   * Action Type (`VIEW`, `CREATE`, `UPDATE`, `BREAK_GLASS`)
   * Target Model and Target Record ID
   * Timestamp (UTC / EAT)
3. **Breach Notification & Penalties:** Any unauthorized disclosure, extraction, or snooping into sensitive health data is subject to administrative sanctions and statutory criminal/civil penalties under **Sections 60–65 of the Personal Data Protection Act, 2022**.
