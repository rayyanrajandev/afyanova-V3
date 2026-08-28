<?php

namespace App\Domains\Clinical\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Clinical\Models\ClinicalVital;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\LabOrder;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class EncounterController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspaceAccess;

    public function index(Request $request, AuthorizationService $authService): Response
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, ['clinical.encounter.view']);

        // completeEncounter reflects only the base clinical.encounter.update
        // permission — EncounterPolicy::update also requires the caller be
        // the encounter's own provider (or hold clinical.encounter.override),
        // a per-row check the Vue side already applies on top of this flag.
        $can = $this->buildSectionCanMap($request->user(), $authService, [
            'startEncounter' => 'clinical.encounter.create',
            'completeEncounter' => 'clinical.encounter.update',
            'orderProcedure' => 'procedure.order.create',
            'createNote' => 'clinical.notes.create',
            'recordDiagnosis' => 'clinical.diagnosis.manage',
            'signNote' => 'clinical.notes.sign',
            'recordVitals' => 'clinical.vitals.record',
            'prescribe' => 'pharmacy.prescription.create',
            'orderLabs' => 'lab.order.create',
            'orderImaging' => 'radiology.order.create',
            'recordConsent' => 'clinical.consent.record',
            'createReferral' => 'clinical.referral.create',
            'recordAncVisit' => 'clinical.anc.record',
            'recordPartograph' => 'clinical.partograph.record',
            'administerImmunization' => 'clinical.immunization.administer',
        ]);

        // 1. Fetch only Active (non-closed) OPD Consultations
        $encounters = Encounter::with([
            'patient.allergies',
            'patient.policies.insuranceCompany',
            'provider',
            'invoices.lineItems',
            'vitals',
            'notes',
            'diagnoses',
            'prescriptions.medication',
            'labOrders.items.labTest',
            'labOrders.items.performedBy',
            'procedureOrders.catalog',
            'procedureOrders.latestExecution',
            'consents.clinician',
            'referrals.toFacility',
            'immunizations',
            'ancEncounters',
            'partographEntries',
            'radiologyOrders.reports',
            'radiologyOrders.studies',
        ])
            ->where('status', '!=', 'Closed')
            ->whereIn('encounter_type', ['OPD', 'OPD Consultation', 'Emergency', 'Consultation', 'Treatment_Followup'])
            ->latest('created_at')
            ->take(50)
            ->get();

        $activePatientIds = $encounters->pluck('patient_id')->filter()->unique()->toArray();

        // 2. Waiting Patients: Patients holding an active Doctor/Triage Queue Ticket
        $activeQueuePatientIds = QueueTicket::whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])
            ->whereIn('current_service_point', ['Triage', 'Doctor'])
            ->where(function ($q) {
                $q->whereNull('encounter_id')
                    ->orWhereHas('encounter', function ($encQuery) {
                        $encQuery->where('status', '!=', 'Closed')
                            ->whereIn('encounter_type', ['OPD', 'OPD Consultation', 'Emergency', 'Consultation', 'Treatment_Followup']);
                    });
            })
            ->pluck('patient_id')
            ->filter()
            ->unique()
            ->toArray();

        $waitingPatients = Patient::with(['allergies', 'latestVital', 'vitals'])
            ->whereIn('id', $activeQueuePatientIds)
            ->latest()
            ->take(50)
            ->get();

        // 3. All registered patients for MPI selection / manual encounter start
        $allPatients = Patient::with(['allergies', 'latestVital', 'vitals'])
            ->latest()
            ->take(50)
            ->get();

        $formularies = MedicationFormulary::where('is_active', true)->pharmaceuticalOnly()->get();
        $labTests = LabTest::where('is_active', true)->get();
        $procedureCatalogs = ProcedureCatalog::where('is_active', true)->get();
        $recentLabOrders = LabOrder::with(['items.labTest', 'patient', 'orderingProvider', 'encounter'])
            ->latest()
            ->take(30)
            ->get();

        return Inertia::render('Workspace/ClinicalWorkspace', [
            'can' => $can,
            'encounters' => $encounters,
            'patients' => $waitingPatients,
            'allPatients' => $allPatients,
            'formularies' => $formularies,
            'labTests' => $labTests,
            'procedureCatalogs' => $procedureCatalogs,
            'recentLabOrders' => $recentLabOrders,
            'encounter' => null,
        ]);
    }

    public function store(Request $request, StartEncounterAction $action)
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id ?? Tenant::first()?->id;
        $facilityId = $request->facility_id ?? $user->facility_id ?? Facility::where('tenant_id', $tenantId)->first()?->id ?? Facility::first()?->id;
        $departmentId = $request->department_id ?? $user->department_id ?? null;

        $this->authorize('create', [Encounter::class, $facilityId]);

        $validated = $request->validate([
            'patient_id' => 'required|string',
            'facility_id' => 'nullable|string',
            'department_id' => 'nullable|string',
            'provider_id' => 'nullable|string',
            'encounter_type' => 'nullable|string',
            'reason_for_visit' => 'nullable|string',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['facility_id'] = $validated['facility_id'] ?? $facilityId;
        $validated['department_id'] = $validated['department_id'] ?? $departmentId;
        $validated['provider_id'] = $validated['provider_id'] ?? $user->id;
        $validated['encounter_type'] = $validated['encounter_type'] ?? 'OPD Consultation';
        $validated['reason_for_visit'] = $validated['reason_for_visit'] ?? 'General Consultation';

        $encounter = $action->execute($validated);

        // Update any checked-in appointments to In Progress
        Appointment::where('patient_id', $validated['patient_id'])
            ->where('status', 'Checked-In')
            ->update(['status' => 'In Progress']);

        // Auto-link any existing recent unattached triage vitals recorded for this patient within last 24h
        ClinicalVital::where('patient_id', $validated['patient_id'])
            ->whereNull('encounter_id')
            ->where('created_at', '>=', now()->subDay())
            ->update(['encounter_id' => $encounter->id]);

        return redirect()->route('encounters.workspace', $encounter->id)
            ->with('success', 'Encounter started successfully.');
    }

    public function workspace(Encounter $encounter, AuthorizationService $authService): Response
    {
        $this->authorize('view', $encounter);

        $can = $this->buildSectionCanMap(auth()->user(), $authService, [
            'startEncounter' => 'clinical.encounter.create',
            'completeEncounter' => 'clinical.encounter.update',
            'orderProcedure' => 'procedure.order.create',
            'createNote' => 'clinical.notes.create',
            'recordDiagnosis' => 'clinical.diagnosis.manage',
            'signNote' => 'clinical.notes.sign',
            'recordVitals' => 'clinical.vitals.record',
            'prescribe' => 'pharmacy.prescription.create',
            'orderLabs' => 'lab.order.create',
            'orderImaging' => 'radiology.order.create',
            'recordConsent' => 'clinical.consent.record',
            'createReferral' => 'clinical.referral.create',
            'recordAncVisit' => 'clinical.anc.record',
            'recordPartograph' => 'clinical.partograph.record',
            'administerImmunization' => 'clinical.immunization.administer',
        ]);

        if ($encounter->status === 'Triage') {
            $encounter->update([
                'status' => 'In Progress',
                'provider_id' => $encounter->provider_id ?? auth()->id(),
            ]);
        }

        $encounter->load([
            'patient.allergies',
            'patient.policies.insuranceCompany',
            'patient.latestVital',
            'provider',
            'invoices.lineItems',
            'vitals',
            'notes',
            'diagnoses',
            'prescriptions.medication',
            'labOrders.items.labTest',
            'labOrders.items.performedBy',
            'procedureOrders.catalog',
            'procedureOrders.latestExecution',
            'consents.clinician',
            'referrals.toFacility',
            'immunizations',
            'ancEncounters',
            'partographEntries',
            'radiologyOrders.reports',
            'radiologyOrders.studies',
        ]);

        $encounters = Encounter::with([
            'patient.allergies',
            'patient.policies.insuranceCompany',
            'provider',
            'invoices.lineItems',
            'vitals',
            'notes',
            'diagnoses',
            'prescriptions.medication',
            'labOrders.items.labTest',
            'labOrders.items.performedBy',
            'procedureOrders.catalog',
            'procedureOrders.latestExecution',
            'consents.clinician',
            'referrals.toFacility',
            'immunizations',
            'ancEncounters',
            'partographEntries',
            'radiologyOrders.reports',
            'radiologyOrders.studies',
        ])
            ->where('status', '!=', 'Closed')
            ->whereIn('encounter_type', ['OPD', 'OPD Consultation', 'Emergency', 'Consultation', 'Treatment_Followup'])
            ->latest('created_at')
            ->take(50)
            ->get();

        $activePatientIds = $encounters->pluck('patient_id')->filter()->unique()->toArray();

        // Waiting Patients: Patients holding an active Doctor/Triage Queue Ticket
        $activeQueuePatientIds = QueueTicket::whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])
            ->whereIn('current_service_point', ['Triage', 'Doctor'])
            ->where(function ($q) {
                $q->whereNull('encounter_id')
                    ->orWhereHas('encounter', function ($encQuery) {
                        $encQuery->where('status', '!=', 'Closed')
                            ->whereIn('encounter_type', ['OPD', 'OPD Consultation', 'Emergency', 'Consultation', 'Treatment_Followup']);
                    });
            })
            ->pluck('patient_id')
            ->filter()
            ->unique()
            ->toArray();

        $waitingPatients = Patient::with(['allergies', 'latestVital', 'vitals'])
            ->whereIn('id', $activeQueuePatientIds)
            ->latest()
            ->take(50)
            ->get();

        $allPatients = Patient::with(['allergies', 'latestVital', 'vitals'])
            ->latest()
            ->take(50)
            ->get();

        $formularies = MedicationFormulary::where('is_active', true)->pharmaceuticalOnly()->get();
        $labTests = LabTest::where('is_active', true)->get();
        $procedureCatalogs = ProcedureCatalog::where('is_active', true)->get();
        $recentLabOrders = LabOrder::with(['items.labTest', 'patient', 'orderingProvider', 'encounter'])
            ->latest()
            ->take(30)
            ->get();

        return Inertia::render('Workspace/ClinicalWorkspace', [
            'can' => $can,
            'encounter' => $encounter,
            'encounters' => $encounters,
            'patients' => $waitingPatients,
            'allPatients' => $allPatients,
            'formularies' => $formularies,
            'labTests' => $labTests,
            'procedureCatalogs' => $procedureCatalogs,
            'recentLabOrders' => $recentLabOrders,
        ]);
    }

    public function complete(Encounter $encounter)
    {
        $this->authorize('update', $encounter);

        $now = now();
        $encounter->update([
            'status' => 'Closed',
            'end_time' => $now,
        ]);

        // Complete linked queue tickets so patient leaves the waiting queue
        QueueTicket::where('encounter_id', $encounter->id)
            ->whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])
            ->update([
                'status' => QueueTicketStatus::Completed,
                'completed_at' => $now,
            ]);

        // Complete linked appointments so Reception dashboard reflects completed visit
        Appointment::where('patient_id', $encounter->patient_id)
            ->whereIn('status', ['Scheduled', 'Checked-In', 'In Progress'])
            ->update([
                'status' => 'Completed',
            ]);

        return redirect()->route('workspace.clinical')
            ->with('success', 'Consultation encounter completed and closed successfully.');
    }
}
