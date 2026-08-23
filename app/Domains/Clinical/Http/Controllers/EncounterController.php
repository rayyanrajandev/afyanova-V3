<?php

namespace App\Domains\Clinical\Http\Controllers;

use App\Domains\Clinical\Actions\StartEncounterAction;
use App\Domains\Clinical\Models\ClinicalVital;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\LabOrder;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class EncounterController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        // 1. Fetch only Active (non-closed) Encounters
        $encounters = Encounter::with(['patient.allergies', 'provider', 'vitals', 'notes', 'diagnoses', 'prescriptions.medication', 'labOrders.items.labTest', 'labOrders.items.performedBy', 'procedureOrders.catalog', 'procedureOrders.latestExecution'])
            ->where('status', '!=', 'Closed')
            ->latest('created_at')
            ->take(50)
            ->get();

        $activePatientIds = $encounters->pluck('patient_id')->filter()->unique()->toArray();

        // 2. Waiting Patients: Registered patients who do NOT currently have an open/active consultation
        $waitingPatients = Patient::with(['allergies', 'latestVital', 'vitals'])
            ->whereNotIn('id', $activePatientIds)
            ->latest()
            ->take(50)
            ->get();

        // 3. All registered patients for MPI selection / manual encounter start
        $allPatients = Patient::with(['allergies', 'latestVital', 'vitals'])
            ->latest()
            ->take(50)
            ->get();

        $formularies = MedicationFormulary::where('is_active', true)->get();
        $labTests = LabTest::where('is_active', true)->get();
        $procedureCatalogs = ProcedureCatalog::where('is_active', true)->get();
        $recentLabOrders = LabOrder::with(['items.labTest', 'patient', 'orderingProvider', 'encounter'])
            ->latest()
            ->take(30)
            ->get();

        return Inertia::render('Workspace/ClinicalWorkspace', [
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

        // Auto-link any existing recent unattached triage vitals recorded for this patient within last 24h
        ClinicalVital::where('patient_id', $validated['patient_id'])
            ->whereNull('encounter_id')
            ->where('created_at', '>=', now()->subDay())
            ->update(['encounter_id' => $encounter->id]);

        return redirect()->route('encounters.workspace', $encounter->id)
            ->with('success', 'Encounter started successfully.');
    }

    public function workspace(Encounter $encounter): Response
    {
        if ($encounter->status === 'Triage') {
            $encounter->update([
                'status' => 'In Progress',
                'provider_id' => $encounter->provider_id ?? auth()->id(),
            ]);
        }

        $encounter->load(['patient.allergies', 'patient.latestVital', 'provider', 'vitals', 'notes', 'diagnoses', 'prescriptions.medication', 'labOrders.items.labTest', 'labOrders.items.performedBy', 'procedureOrders.catalog', 'procedureOrders.latestExecution']);

        $encounters = Encounter::with(['patient.allergies', 'provider', 'vitals', 'notes', 'diagnoses', 'prescriptions.medication', 'labOrders.items.labTest', 'labOrders.items.performedBy', 'procedureOrders.catalog', 'procedureOrders.latestExecution'])
            ->where('status', '!=', 'Closed')
            ->latest('created_at')
            ->take(50)
            ->get();

        $activePatientIds = $encounters->pluck('patient_id')->filter()->unique()->toArray();

        $waitingPatients = Patient::with(['allergies', 'latestVital', 'vitals'])
            ->whereNotIn('id', $activePatientIds)
            ->latest()
            ->take(50)
            ->get();

        $allPatients = Patient::with(['allergies', 'latestVital', 'vitals'])
            ->latest()
            ->take(50)
            ->get();

        $formularies = MedicationFormulary::where('is_active', true)->get();
        $labTests = LabTest::where('is_active', true)->get();
        $procedureCatalogs = ProcedureCatalog::where('is_active', true)->get();
        $recentLabOrders = LabOrder::with(['items.labTest', 'patient', 'orderingProvider', 'encounter'])
            ->latest()
            ->take(30)
            ->get();

        return Inertia::render('Workspace/ClinicalWorkspace', [
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

        $encounter->update([
            'status' => 'Closed',
            'end_time' => now(),
        ]);

        return redirect()->route('workspace.clinical')
            ->with('success', 'Consultation encounter completed and closed successfully.');
    }
}
