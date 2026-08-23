# AfyaNova V3 — FHIR R4 Interoperability Architecture

## 1. Architectural Role of FHIR in AfyaNova V3

**HL7 FHIR (Fast Healthcare Interoperability Resources) R4** is the global gold standard for healthcare data exchange. 

In AfyaNova V3, **FHIR is an interoperability boundary, NOT the internal relational database schema**.

```
┌────────────────────────────────────────────────────────┐
│             AfyaNova Core Domain Model                 │
│    (Optimized for Hospital Operations, ERP, & UI)      │
└───────────────────────────┬────────────────────────────┘
                            │
         ┌──────────────────┴──────────────────┐
         ▼                                     ▼
┌─────────────────────────┐           ┌─────────────────────────┐
│ Application Web APIs /  │           │   FHIR R4 Facade Layer  │
│ Inertia Controllers     │           │ (Mappers & Transformers)│
└─────────────────────────┘           └────────────┬────────────┘
                                                   │
                                                   ▼
                                      ┌─────────────────────────┐
                                      │ External FHIR R4 Clients│
                                      │ (HIE, MoH, SMART Apps)  │
                                      └─────────────────────────┘
```

### Why a FHIR Facade Architecture?
1. **Separation of Concerns**: Internal database schemas must optimize for high-frequency transactional performance, relational joins, complex billing splits, and double-entry ledgers. FHIR resources optimize for document interchange and semantic interoperability.
2. **Evolution & Versioning**: Allows AfyaNova to update its internal business logic without breaking external FHIR API contracts.
3. **No Schema Compromise**: Eliminates the awkward workarounds needed when trying to force hospital billing and stock movements into pure clinical FHIR structures.

---

## 2. Core FHIR R4 Resource Mappings

| FHIR R4 Resource | AfyaNova Internal Entity | Mapping Details & Standard Coding |
| :--- | :--- | :--- |
| **`Patient`** | `Patient` | `id`, `identifier` (MRN, NIDA), `name` (official, given, family), `telecom`, `gender`, `birthDate`, `address`. |
| **`Encounter`** | `Encounter` | `status` (planned, in-progress, finished), `class` (AMB, IMP, EMER), `type`, `subject` (Patient ref), `period` (start, end). |
| **`Observation`** | `VitalSign` / `LabResultItem` | `category` (vital-signs, laboratory), `code` (LOINC), `valueQuantity` (value + unit), `referenceRange` (low, high), `interpretation` (abnormal/panic). |
| **`Condition`** | `Diagnosis` | `clinicalStatus` (active, resolved), `verificationStatus` (confirmed, differential), `code` (ICD-10-WHO), `subject`, `encounter`. |
| **`AllergyIntolerance`** | `Allergy` | `clinicalStatus`, `verificationStatus`, `category` (medication, food), `criticality` (low, high, unable-to-assess), `code`. |
| **`MedicationRequest`** | `PrescriptionItem` | `status` (active, completed), `intent` (order), `medicationCodeableConcept`, `subject`, `dosageInstruction` (timing, route, dose). |
| **`MedicationDispense`** | `DispenseItem` | `status` (completed), `medicationCodeableConcept`, `subject`, `authorizingPrescription`, `quantity`, `whenHandedOver`. |
| **`ServiceRequest`** | `ClinicalOrder` | `status` (active, completed), `intent` (order), `code` (Lab test / Radiology procedure code), `priority` (stat, urgent, routine). |
| **`DiagnosticReport`** | `LabOrder` / `RadiologyOrder` | `status` (preliminary, final), `category` (LAB, RAD), `code`, `subject`, `result` (Observation refs), `conclusion`, `presentedForm` (PDF). |
| **`Organization`** | `Tenant` / `Facility` | `identifier` (HFR code for Tanzania), `name`, `type` (prov, dept), `telecom`, `address`. |
| **`Practitioner`** | `User` | `identifier` (MCT registration number), `name`, `telecom`, `qualification`. |
| **`Location`** | `Ward` / `Bed` / `Department`| `status` (active, suspended), `name`, `mode` (instance), `type` (ward, ro, bd), `physicalType`. |
| **`Coverage`** | `PatientPolicy` | `status` (active), `type` (health insurance), `subscriberId`, `beneficiary`, `payor` (InsuranceProvider ref). |
| **`Claim`** | `Claim` | `status` (active), `type` (institutional, professional), `patient`, `provider`, `priority`, `item` (services, diagnosis codes, amounts). |

---

## 3. FHIR Facade Implementation Pattern

Each supported resource is handled by a dedicated **FHIR Resource Mapper** in `app/Domains/Integration/Fhir/`:

```php
namespace App\Domains\Integration\Fhir\Mappers;

use App\Domains\Clinical\Models\VitalSign;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRCodeableConcept;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRQuantity;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRObservation;

class VitalSignToFhirObservationMapper
{
    public static function toFhir(VitalSign $vital): FHIRObservation
    {
        $obs = new FHIRObservation();
        $obs->setId($vital->id);
        $obs->setStatus('final');
        $obs->addCategory(new FHIRCodeableConcept([
            'coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'vital-signs']]
        ]));
        
        // Map specific components (Systolic, Diastolic, HR, Temp, SpO2)
        // ...
        return $obs;
    }
}
```

---

## 4. Security & SMART on FHIR Roadmap

1. **Authentication**: FHIR endpoints require OAuth2 Bearer Tokens (Laravel Passport / Sanctum).
2. **SMART App Launch Framework**: AfyaNova V3 provides hooks for future SMART on FHIR app launches (allowing third-party clinical decision support plugins, pediatric growth charts, and genomic calculators to launch securely in an iframe inside the clinical chart).
3. **Tenant & Patient Scoping**: FHIR queries enforce standard SMART scopes (e.g. `patient/Observation.read`, `system/Encounter.read`) strictly bounded to the authenticated tenant.
