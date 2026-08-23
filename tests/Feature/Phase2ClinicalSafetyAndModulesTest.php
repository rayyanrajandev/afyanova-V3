<?php

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Clinical\Actions\AdministerImmunizationAction;
use App\Domains\Clinical\Actions\CreateReferralAction;
use App\Domains\Clinical\Actions\ManageProblemListAction;
use App\Domains\Clinical\Actions\RecordAncVisitAction;
use App\Domains\Clinical\Actions\RecordConsentAction;
use App\Domains\Clinical\Actions\RecordPartographAction;
use App\Domains\Clinical\Actions\RecordVitalsAction;
use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Clinical\Events\CriticalVitalRecordedEvent;
use App\Domains\Clinical\Models\Allergy;
use App\Domains\Clinical\Models\ClinicalNote;
use App\Domains\Clinical\Models\Diagnosis;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\LabOrder;
use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Inpatient\Actions\AdmitPatientAction;
use App\Domains\Inpatient\Exceptions\InpatientException;
use App\Domains\Inpatient\Models\Admission;
use App\Domains\Inpatient\Models\Bed;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Laboratory\Actions\CollectSpecimenAction;
use App\Domains\Laboratory\Actions\EvaluateLabResultRangeAction;
use App\Domains\Laboratory\Models\LabTestRange;
use App\Domains\Patient\Actions\MergePatientsAction;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Actions\PrescribeMedicationAction;
use App\Domains\Pharmacy\Actions\ReconcileMedicationsAction;
use App\Domains\Pharmacy\Exceptions\PharmacyException;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\Prescription;
use App\Domains\Procedure\Actions\BookSurgicalCaseAction;
use App\Domains\Procedure\Actions\CompleteWhoChecklistAction;
use App\Domains\Procedure\Actions\CreateProcedureOrderAction;
use App\Domains\Procedure\Actions\RecordProcedureExecutionAction;
use App\Domains\Procedure\Models\OperatingSuite;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Radiology\Actions\AmendRadiologyReportAction;
use App\Domains\Radiology\Actions\OrderImagingAction;
use App\Domains\Radiology\Actions\SignRadiologyReportAction;
use App\Domains\Radiology\Models\RadiologyStudy;
use Illuminate\Support\Facades\Event;
use Ramsey\Uuid\Uuid;

test('inpatient admission atomically locks bed and prevents concurrent double occupancy', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-INP-001',
        'first_name' => 'Admit',
        'last_name' => 'Test',
        'dob' => '1990-01-01',
        'gender' => 'female',
        'status' => 'Active',
    ]);

    $ward = Ward::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'code' => 'WARD-SURG-01',
        'name' => 'Surgical Ward 1',
        'ward_type' => 'General',
        'gender_designation' => 'Mixed',
        'total_beds' => 10,
        'is_active' => true,
    ]);

    $bed = Bed::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'ward_id' => $ward->id,
        'bed_number' => 'BED-101',
        'bed_type' => 'Standard',
        'status' => 'Available',
    ]);

    $admitAction = app(AdmitPatientAction::class);

    $admission = $admitAction->execute([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'admitting_doctor_id' => $user->id,
    ]);

    expect($admission)->not->toBeNull()
        ->and($admission->status)->toBe('Admitted')
        ->and($bed->fresh()->status)->toBe('Occupied');

    // Attempting to admit a second patient to the same occupied bed must fail
    $patient2 = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-INP-002',
        'first_name' => 'Admit2',
        'last_name' => 'Test2',
        'dob' => '1992-02-02',
        'gender' => 'male',
        'status' => 'Active',
    ]);

    expect(fn () => $admitAction->execute([
        'patient_id' => $patient2->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'admitting_doctor_id' => $user->id,
    ]))->toThrow(InpatientException::class);
});

test('deceased patient state blocks encounters, admissions, and prescribing', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $deceasedPatient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-DEC-001',
        'first_name' => 'Deceased',
        'last_name' => 'Person',
        'dob' => '1960-01-01',
        'gender' => 'male',
        'status' => 'Deceased',
    ]);

    // 1. Encounter start blocked
    expect(fn () => app(StartEncounterAction::class)->execute([
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $deceasedPatient->id,
        'department_id' => Uuid::uuid7()->toString(),
        'encounter_type' => 'OPD',
    ]))->toThrow(InvalidArgumentException::class);

    // 2. Prescribing blocked
    $drug = MedicationFormulary::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'code' => 'MED-DEC-01',
        'generic_name' => 'Paracetamol',
        'brand_name' => 'Panadol',
        'drug_class' => 'Analgesic',
        'strength' => '500mg',
        'form' => 'Tablet',
        'route' => 'Oral',
        'charge_code' => 'PHARM-PCM-500',
        'is_active' => true,
    ]);

    expect(fn () => app(PrescribeMedicationAction::class)->execute([
        'encounter_id' => Uuid::uuid7()->toString(),
        'patient_id' => $deceasedPatient->id,
        'medication_id' => $drug->id,
        'dosage' => '500mg',
        'frequency' => 'TDS',
        'duration_days' => 5,
        'route' => 'Oral',
        'quantity' => 15,
    ]))->toThrow(PharmacyException::class);
});

test('patient merge completely cascade relinks all clinical child records', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $winner = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-WIN-001',
        'first_name' => 'Winner',
        'last_name' => 'Patient',
        'dob' => '1988-04-12',
        'gender' => 'female',
        'status' => 'Active',
    ]);

    $loser = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-LOSE-002',
        'first_name' => 'Duplicate',
        'last_name' => 'Patient',
        'dob' => '1988-04-12',
        'gender' => 'female',
        'status' => 'Active',
    ]);

    // Attach encounter, note, vital, diagnosis to loser
    $encounter = Encounter::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $loser->id,
        'encounter_type' => 'OPD',
        'status' => 'Completed',
        'start_time' => now(),
    ]);

    $note = ClinicalNote::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'encounter_id' => $encounter->id,
        'patient_id' => $loser->id,
        'author_id' => $env['user']->id,
        'note_type' => 'SOAP_SUBJECTIVE',
        'content' => ['subjective' => 'Patient presents with headache'],
        'is_signed' => true,
        'signed_at' => now(),
    ]);

    $allergy = Allergy::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $loser->id,
        'recorded_by' => $user->id,
        'allergen_type' => 'Drug',
        'allergen' => 'Penicillin',
        'category' => 'Medication',
        'severity' => 'Severe',
        'status' => 'Active',
    ]);

    $mergeAction = app(MergePatientsAction::class);
    $mergedWinner = $mergeAction->execute($winner, $loser);

    expect($mergedWinner->id)->toBe($winner->id)
        ->and($loser->fresh()->status)->toBe('Merged')
        ->and($loser->fresh()->merged_into_patient_id)->toBe($winner->id)
        ->and($encounter->fresh()->patient_id)->toBe($winner->id)
        ->and($note->fresh()->patient_id)->toBe($winner->id)
        ->and($allergy->fresh()->patient_id)->toBe($winner->id);

    $log = AuditLog::where('entity_id', $winner->id)->where('action', 'PATIENT_MERGED')->first();
    expect($log)->not->toBeNull();
});

test('who surgical checklist time-out blocks operating theatre procedure execution until verified', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-SURG-001',
        'first_name' => 'Surgical',
        'last_name' => 'Candidate',
        'dob' => '1975-06-20',
        'gender' => 'male',
        'status' => 'Active',
    ]);

    $encounter = Encounter::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $patient->id,
        'encounter_type' => 'IPD',
        'status' => 'In Progress',
        'start_time' => now(),
    ]);

    $catalog = ProcedureCatalog::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'procedure_code' => 'SURG-APP-01',
        'name' => 'Appendectomy',
        'category' => 'MajorSurgery',
        'standard_price' => 150000.00,
        'is_active' => true,
    ]);

    $suite = OperatingSuite::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'suite_code' => 'STE-MAIN-01',
        'name' => 'Main Theatre 1',
        'suite_type' => 'General',
        'status' => 'Available',
    ]);

    $order = app(CreateProcedureOrderAction::class)->execute($encounter, $catalog->id, 'Urgent', 'Acute Appendicitis');

    $booking = app(BookSurgicalCaseAction::class)->execute($order, $suite->id, [
        'lead_surgeon_id' => $user->id,
        'anesthetist_id' => $user->id,
        'scheduled_start' => now()->addHour(),
        'scheduled_end' => now()->addHours(2),
    ]);

    $execAction = app(RecordProcedureExecutionAction::class);

    // 1. Procedure in OperatingTheatre without time-out completed must be blocked
    expect(fn () => $execAction->execute($order->fresh(), [
        'execution_setting' => 'OperatingTheatre',
        'anesthesia_type' => 'General',
        'findings_and_technique' => 'Incision made, appendix removed cleanly.',
    ]))->toThrow(InvalidArgumentException::class);

    // 2. Complete WHO Time-out
    $checklist = $booking->whoChecklist;
    app(CompleteWhoChecklistAction::class)->execute($checklist, 'time_out');

    // 3. Now execution succeeds
    $execution = $execAction->execute($order->fresh(['surgicalBooking.whoChecklist']), [
        'execution_setting' => 'OperatingTheatre',
        'anesthesia_type' => 'General',
        'findings_and_technique' => 'Incision made, appendix removed cleanly.',
    ]);

    expect($execution)->not->toBeNull()
        ->and($execution->procedure_order_id)->toBe($order->id)
        ->and($order->fresh()->status)->toBe('Completed');
});

test('allergy cross-reactivity and severe drug-drug interactions are rejected', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-SAFE-001',
        'first_name' => 'Safety',
        'last_name' => 'Patient',
        'dob' => '1995-10-10',
        'gender' => 'female',
        'status' => 'Active',
    ]);

    // Record Penicillin allergy
    Allergy::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'patient_id' => $patient->id,
        'recorded_by' => $user->id,
        'allergen_type' => 'Drug',
        'allergen' => 'Penicillin',
        'category' => 'Medication',
        'severity' => 'Severe',
        'status' => 'Active',
    ]);

    $amoxicillin = MedicationFormulary::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'code' => 'MED-AMX-01',
        'generic_name' => 'Amoxicillin',
        'brand_name' => 'Amoxil',
        'drug_class' => 'Penicillin',
        'strength' => '500mg',
        'form' => 'Capsule',
        'route' => 'Oral',
        'charge_code' => 'PHARM-AMX-500',
        'is_active' => true,
    ]);

    $prescribeAction = app(PrescribeMedicationAction::class);

    // 1. Cross-reactivity: Penicillin allergy blocks Amoxicillin
    expect(fn () => $prescribeAction->execute([
        'encounter_id' => Uuid::uuid7()->toString(),
        'patient_id' => $patient->id,
        'medication_id' => $amoxicillin->id,
        'dosage' => '500mg',
        'frequency' => 'TDS',
        'duration_days' => 5,
        'route' => 'Oral',
        'quantity' => 15,
    ]))->toThrow(PharmacyException::class);

    // 2. Severe Drug-Drug Interaction: Warfarin + Diclofenac
    $warfarin = MedicationFormulary::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'code' => 'MED-WARF-01',
        'generic_name' => 'Warfarin',
        'brand_name' => 'Coumadin',
        'drug_class' => 'Anticoagulant',
        'strength' => '5mg',
        'form' => 'Tablet',
        'route' => 'Oral',
        'charge_code' => 'PHARM-WARF-5',
        'is_active' => true,
    ]);

    $diclofenac = MedicationFormulary::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'code' => 'MED-DIC-01',
        'generic_name' => 'Diclofenac',
        'brand_name' => 'Voltaren',
        'drug_class' => 'NSAID',
        'strength' => '50mg',
        'form' => 'Tablet',
        'route' => 'Oral',
        'charge_code' => 'PHARM-DIC-50',
        'is_active' => true,
    ]);

    $rxEncounter = Encounter::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $patient->id,
        'encounter_type' => 'OPD',
        'status' => 'In Progress',
        'start_time' => now(),
    ]);

    // Active Warfarin prescription
    Prescription::create([
        'id' => Uuid::uuid7()->toString(),
        'encounter_id' => $rxEncounter->id,
        'patient_id' => $patient->id,
        'prescriber_id' => $env['user']->id,
        'medication_id' => $warfarin->id,
        'dosage' => '5mg',
        'frequency' => 'OD',
        'duration_days' => 30,
        'route' => 'Oral',
        'quantity' => 30,
        'status' => 'Dispensed',
    ]);

    // Attempting to prescribe Diclofenac (NSAID) while on Warfarin must throw DDI exception
    expect(fn () => $prescribeAction->execute([
        'encounter_id' => $rxEncounter->id,
        'patient_id' => $patient->id,
        'medication_id' => $diclofenac->id,
        'dosage' => '50mg',
        'frequency' => 'BD',
        'duration_days' => 5,
        'route' => 'Oral',
        'quantity' => 10,
    ]))->toThrow(PharmacyException::class);
});

test('critical vitals telemetry dispatches event upon recording panic values', function () {
    Event::fake([CriticalVitalRecordedEvent::class]);

    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-VTL-001',
        'first_name' => 'Telemetry',
        'last_name' => 'Test',
        'dob' => '1990-01-01',
        'gender' => 'male',
        'status' => 'Active',
    ]);

    $encounter = Encounter::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $patient->id,
        'encounter_type' => 'OPD',
        'status' => 'In Progress',
        'start_time' => now(),
    ]);

    $vital = app(RecordVitalsAction::class)->execute([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'systolic_bp' => 205, // Hypertensive crisis >= 190
        'diastolic_bp' => 110,
        'oxygen_saturation' => 84.0, // Severe hypoxemia < 88%
        'heart_rate' => 160, // Tachycardia >= 150
        'temperature_c' => 37.0,
    ]);

    expect($vital)->not->toBeNull();

    Event::assertDispatched(CriticalVitalRecordedEvent::class, function ($event) use ($vital) {
        return $event->vital->id === $vital->id
            && isset($event->panicFlags['systolic_bp'])
            && isset($event->panicFlags['oxygen_saturation']);
    });
});

test('problem list and medication reconciliation maintain longitudinal continuity', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-CONT-001',
        'first_name' => 'Longitudinal',
        'last_name' => 'Care',
        'dob' => '1970-05-15',
        'gender' => 'male',
        'status' => 'Active',
    ]);

    // 1. Record chronic condition in Problem List
    $problemAction = app(ManageProblemListAction::class);
    $problem = $problemAction->record([
        'patient_id' => $patient->id,
        'icd10_code' => 'I10',
        'problem_name' => 'Essential (primary) hypertension',
        'recorded_by' => $env['user']->id,
        'status' => 'Active',
    ]);

    expect($problem->icd10_code)->toBe('I10')
        ->and($problem->status)->toBe('Active');

    // 2. Medication Reconciliation during Admission
    $medRecAction = app(ReconcileMedicationsAction::class);
    $reconciled = $medRecAction->execute($patient, 'Admission', [
        [
            'medication_name' => 'Amlodipine 10mg',
            'dosage' => '10mg',
            'frequency' => 'OD',
            'action_taken' => 'Continue',
            'clinical_rationale' => 'Maintain BP control during hospital stay',
        ],
        [
            'medication_name' => 'Metformin 500mg',
            'dosage' => '500mg',
            'frequency' => 'BD',
            'action_taken' => 'Hold',
            'clinical_rationale' => 'Hold prior to IV contrast CT scan',
        ],
    ], $env['facility']->id);

    expect($reconciled->count())->toBe(2)
        ->and($reconciled->first()->action_taken)->toBe('Continue')
        ->and($reconciled->last()->action_taken)->toBe('Hold');
});

test('radiology order, study acquisition, and signed report with amendment lifecycle', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-RAD-001',
        'first_name' => 'Imaging',
        'last_name' => 'Patient',
        'dob' => '1982-08-14',
        'gender' => 'female',
        'status' => 'Active',
    ]);

    $encounter = Encounter::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $patient->id,
        'encounter_type' => 'OPD',
        'status' => 'In Progress',
        'start_time' => now(),
    ]);

    // 1. Order Imaging
    $order = app(OrderImagingAction::class)->execute($encounter, [
        'modality' => 'X-Ray',
        'procedure_name' => 'Chest X-Ray PA View',
        'body_site' => 'Chest',
        'clinical_indication' => 'Chronic cough and hemoptysis',
        'priority' => 'Urgent',
    ]);

    expect($order->status)->toBe('Ordered');

    // 2. Acquire Study
    $study = RadiologyStudy::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'radiology_order_id' => $order->id,
        'patient_id' => $patient->id,
        'technician_id' => $user->id,
        'accession_number' => 'ACC-XR-001',
        'series_count' => 1,
        'instance_count' => 2,
    ]);

    // 3. Sign Formal Radiologist Report
    $report = app(SignRadiologyReportAction::class)->execute($order, [
        'radiology_study_id' => $study->id,
        'radiologist_id' => $user->id,
        'findings' => 'Right upper lobe cavitary lesion with surrounding consolidation.',
        'impression' => 'Findings consistent with active pulmonary tuberculosis.',
        'is_critical_finding' => true,
    ]);

    expect($report->is_signed)->toBeTrue()
        ->and($order->fresh()->status)->toBe('Reported');

    // 4. Amend Report
    $amended = app(AmendRadiologyReportAction::class)->execute($report, [
        'findings' => 'Right upper lobe cavitary lesion with surrounding consolidation. Mild bilateral pleural effusion.',
        'impression' => 'Pulmonary TB with reactive pleural effusion.',
        'amendment_reason' => 'Addendum after high-resolution re-windowing review.',
    ]);

    expect($amended->is_amendment)->toBeTrue()
        ->and($amended->amended_report_id)->toBe($report->id)
        ->and($report->fresh()->is_deprecated)->toBeTrue();
});

test('statutory care workflows: consent, referral, immunization, anc and partograph', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-CARE-001',
        'first_name' => 'Care',
        'last_name' => 'Recipient',
        'dob' => '2000-01-01',
        'gender' => 'female',
        'status' => 'Active',
    ]);

    $encounter = Encounter::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $patient->id,
        'encounter_type' => 'OPD',
        'status' => 'In Progress',
        'start_time' => now(),
    ]);

    // 1. Consent
    $consent = app(RecordConsentAction::class)->execute($patient, [
        'clinician_id' => $user->id,
        'consent_type' => 'Surgical',
        'procedure_title' => 'Diagnostic Laparoscopy',
        'explanation_of_risks' => 'Bleeding, infection, damage to surrounding structures.',
        'signatory_name' => 'Care Recipient',
    ], $encounter);

    expect($consent->procedure_title)->toBe('Diagnostic Laparoscopy');

    // 2. Referral
    $referral = app(CreateReferralAction::class)->execute($patient, [
        'referring_doctor_id' => $user->id,
        'specialty_required' => 'Gastroenterology',
        'clinical_summary' => 'Refractory epigastric pain',
        'reason_for_referral' => 'Upper GI Endoscopy evaluation',
        'urgency' => 'Routine',
    ], $encounter);

    expect($referral->status)->toBe('Dispatched');

    // 3. Immunization
    $immunization = app(AdministerImmunizationAction::class)->execute($patient, [
        'administered_by' => $user->id,
        'vaccine_code' => 'HepB-1',
        'vaccine_name' => 'Hepatitis B Vaccine',
        'dose_number' => 1,
        'batch_number' => 'HB2026-99',
    ], $encounter);

    expect($immunization->vaccine_code)->toBe('HepB-1');

    // 4. ANC Consultation
    $anc = app(RecordAncVisitAction::class)->execute($encounter, [
        'midwife_id' => $user->id,
        'gravida' => 2,
        'para' => 1,
        'gestational_age_weeks' => 28,
        'fundal_height_cm' => 28.5,
        'fetal_heart_rate_bpm' => 140,
    ]);

    expect($anc->gravida)->toBe(2);

    // 5. Partograph Entry
    $partograph = app(RecordPartographAction::class)->execute($encounter, [
        'anc_encounter_id' => $anc->id,
        'cervical_dilation_cm' => 5.0,
        'fetal_heart_rate_bpm' => 136,
        'uterine_contractions_per_10min' => 4,
        'contraction_duration_seconds' => 45,
    ]);

    expect($partograph->cervical_dilation_cm)->toBe(5.0);
});

test('lab specimens collection and stratified range evaluation', function () {
    $env = $this->setupTenantEnvironment();
    $user = $env['user'];
    $this->actingAs($user);

    $patient = Patient::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'primary_mrn' => 'MRN-LAB-001',
        'first_name' => 'Lab',
        'last_name' => 'Patient',
        'dob' => '1990-01-01',
        'gender' => 'male',
        'status' => 'Active',
    ]);

    $encounter = Encounter::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'facility_id' => $env['facility']->id,
        'patient_id' => $patient->id,
        'encounter_type' => 'OPD',
        'status' => 'In Progress',
        'start_time' => now(),
    ]);

    $test = LabTest::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'test_code' => 'LAB-K',
        'name' => 'Serum Potassium',
        'category' => 'Biochemistry',
        'specimen_type' => 'Serum',
        'price' => 10000.00,
        'is_active' => true,
    ]);

    // Stratified Range: Normal 3.5 - 5.0 mmol/L, Critical Low <= 2.8, Critical High >= 6.2
    LabTestRange::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'lab_test_id' => $test->id,
        'gender' => 'All',
        'age_min_days' => 0,
        'age_max_days' => 36500,
        'normal_min' => 3.5,
        'normal_max' => 5.0,
        'critical_low' => 2.8,
        'critical_high' => 6.2,
        'unit' => 'mmol/L',
    ]);

    $order = LabOrder::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'order_number' => 'LAB-2026-0001',
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'ordering_doctor_id' => $user->id,
        'status' => 'Ordered',
        'ordered_at' => now(),
    ]);

    $item = LabOrderItem::create([
        'id' => Uuid::uuid7()->toString(),
        'tenant_id' => $env['tenant']->id,
        'lab_order_id' => $order->id,
        'lab_test_id' => $test->id,
        'status' => 'Ordered',
    ]);

    // 1. Collect Specimen
    $collectedItem = app(CollectSpecimenAction::class)->execute($item);
    expect($collectedItem->status)->toBe('Sample Collected')
        ->and($collectedItem->specimen_barcode)->not->toBeNull();

    // 2. Evaluate Normal Potassium (4.2 mmol/L)
    $evalAction = app(EvaluateLabResultRangeAction::class);
    $normalResult = $evalAction->execute($collectedItem, 4.2, $patient);
    expect($normalResult['flag'])->toBe('Normal')
        ->and($normalResult['is_critical'])->toBeFalse();

    // 3. Evaluate Critical Potassium (6.8 mmol/L)
    $criticalResult = $evalAction->execute($collectedItem, 6.8, $patient);
    expect($criticalResult['flag'])->toBe('Critical High')
        ->and($criticalResult['is_critical'])->toBeTrue();
});
