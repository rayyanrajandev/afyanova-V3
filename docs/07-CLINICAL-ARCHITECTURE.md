# AfyaNova V3 — Clinical Domain Architecture

## 1. Clinical Design Principles

1. **Patient Safety First**: All clinical workflows prioritize patient safety through automated allergy checks, dosage range validations, and panic-value alert notifications.
2. **Clinical Immutability & Legal Record Integrity**: Medical records are legal documents. Once signed, a clinical note, vital sign entry, diagnosis, or lab result cannot be modified or deleted. Corrections are recorded as explicit amendments referencing the original record.
3. **Structured & Codified Data**: Core clinical facts (vitals, diagnoses, allergies, lab values) are stored in normalized, indexable tables with standard coding systems (ICD-10, LOINC) rather than unparsed free text or bloated EAV tables.
4. **Order-Result Decoupling**: Clinical orders (requests) are decoupled from their fulfillment (results/dispensing), supporting independent laboratory, pharmacy, and radiology workflows.

---

## 2. Patient Encounter Lifecycle

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│   1. ARRIVAL    │       │    2. TRIAGE    │       │ 3. CONSULTATION │
│ Reception/Kiosk ├──────►│ Vitals Recorded ├──────►│ History, Exam,  │
│ Patient Check-in│       │ Acuity Scored   │       │ Problem List    │
└─────────────────┘       └─────────────────┘       └────────┬────────┘
                                                             │
                              ┌──────────────────────────────┴──────────────────────────────┐
                              ▼                                                             ▼
                   ┌─────────────────────┐                                       ┌─────────────────────┐
                   │  4A. ORDERS PLACED  │                                       │ 4B. CLINICAL NOTES  │
                   │ Lab, Rad, Rx, Proc  │                                       │ SOAP Note Signed    │
                   └──────────┬──────────┘                                       │ Diagnosis Codified  │
                              │                                                  └──────────┬──────────┘
                              ▼                                                             │
                   ┌─────────────────────┐                                                  │
                   │ 5. EXECUTION/RESULTS│                                                  │
                   │ Lab Specimen/Result │                                                  │
                   │ Pharmacy Dispensing │                                                  │
                   └──────────┬──────────┘                                                  │
                              │                                                             │
                              └──────────────────────────────┬──────────────────────────────┘
                                                             ▼
                                                  ┌─────────────────────┐
                                                  │ 6. ENCOUNTER CLOSE  │
                                                  │ Discharge / Admit / │
                                                  │ Follow-up Scheduled │
                                                  └─────────────────────┘
```

### Encounter States:
- `Planned`: Pre-booked appointment or scheduled admission.
- `Arrived`: Patient has checked in at reception and is waiting for triage/consultation.
- `InProgress`: Active consultation with clinician or active inpatient stay.
- `OnHold`: Paused awaiting laboratory/imaging results.
- `Completed`: Clinician concluded consultation, signed notes, and completed discharge.
- `Cancelled`: Encounter cancelled prior to clinical services being rendered.

---

## 3. Clinical Data Immutability: The Amendment Pattern

When a clinician needs to correct or supplement an already-signed clinical note or recorded vital sign:

```
Original Note (ID: 018f...)
[Note Type: Assessment]
"Patient diagnosed with Type 2 Diabetes."
[Status: Signed by Dr. Mushi at 10:30 AM]
            ▲
            │ (amended_note_id)
Amendment Record (ID: 019a...)
[Note Type: Errata / Amendment]
"Correction: Patient confirmed with Type 1 Diabetes following antibody test results."
[Reason: "Laboratory confirmation of GAD antibodies"]
[Signed by: Dr. Mushi at 02:15 PM]
```

### Database Representation:
```sql
CREATE TABLE clinical_notes (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    facility_id UUID NOT NULL REFERENCES facilities(id),
    encounter_id UUID NOT NULL REFERENCES encounters(id),
    patient_id UUID NOT NULL REFERENCES patients(id),
    author_id UUID NOT NULL REFERENCES users(id),
    note_type VARCHAR(32) NOT NULL, -- 'SOAP_SUBJECTIVE', 'SOAP_OBJECTIVE', 'ASSESSMENT', 'PLAN', 'DISCHARGE_SUMMARY'
    content TEXT NOT NULL,
    signed_at TIMESTAMPTZ NOT NULL,
    is_amendment BOOLEAN NOT NULL DEFAULT FALSE,
    amended_note_id UUID REFERENCES clinical_notes(id),
    amendment_reason TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

---

## 4. Codified Diagnoses & Allergy Registry

### 4.1. Diagnoses Management (ICD-10)
- **Primary Diagnosis**: The main condition responsible for the encounter.
- **Secondary / Comorbidities**: Additional active conditions affecting treatment.
- **Differential / Working**: Suspected conditions under investigation.
- **Certainty Levels**: `Suspected`, `Confirmed`, `Refuted`.

### 4.2. Allergy Registry & Safety Alerts
- All known patient allergies are tracked in a dedicated `allergies` table.
- **Allergy Check Interceptor**: Before any prescription is saved or ordered, the domain checks the prescribed drug's active ingredients against active patient allergies:

```php
namespace App\Domains\Clinical\Services;

class ClinicalSafetyService
{
    public function checkPrescriptionAllergies(Patient $patient, ProductItem $medication): SafetyCheckResult
    {
        $activeAllergies = $patient->activeAllergies;
        foreach ($activeAllergies as $allergy) {
            if ($medication->containsSubstance($allergy->causative_agent)) {
                return SafetyCheckResult::contraindication(
                    "Severe Allergy Warning: Patient has documented {$allergy->severity} allergy to {$allergy->causative_agent}."
                );
            }
        }
        return SafetyCheckResult::safe();
    }
}
```

---

## 5. Clinical Orders & Execution Workflows

Clinical orders represent actionable medical requests:

```
                  ┌────────────────────────┐
                  │     ClinicalOrder      │ (Base Order Aggregate)
                  │ - id, encounter_id     │
                  │ - patient_id, orderer  │
                  │ - status, priority     │
                  └───────────┬────────────┘
                              │
         ┌────────────────────┼────────────────────┐
         ▼                    ▼                    ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│    LabOrder     │  │  Prescription   │  │  RadiologyOrder │
│ - Lab Tests     │  │ - Rx Items      │  │ - Modality      │
│ - Specimens     │  │ - Dosage / FEFO │  │ - Body Region   │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

### Order Priorities:
- `Stat`: Immediate execution required (e.g., Emergency blood gas, ICU medication).
- `Urgent`: Priority processing within 1–2 hours.
- `Routine`: Standard workflow processing.

---

## 6. Panic / Critical Value Alerting System

When a laboratory result or vital sign exceeds critical clinical limits (e.g. Potassium $< 2.5$ or $> 6.5$ mmol/L, Systolic BP $> 200$ mmHg):
1. The result parameter is flagged as `is_critical = true`.
2. A high-priority event `CriticalClinicalValueDetected` is dispatched.
3. An urgent notification is pushed to the attending clinician's mobile device and desktop dashboard with visual and audio alerts.
4. An acknowledgment audit record is required from the clinician upon reviewing the panic value.
