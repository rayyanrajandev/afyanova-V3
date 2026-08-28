<?php

namespace App\Domains\Patient\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Patient\Actions\MergePatientsAction;
use App\Domains\Patient\Actions\RegisterPatientAction;
use App\Domains\Patient\Actions\SearchPatientsAction;
use App\Domains\Patient\Http\Requests\StorePatientRequest;
use App\Domains\Patient\Models\Patient;
use App\Domains\Procedure\Models\ProcedureCatalog;
use App\Domains\Scheduling\Enums\QueueTicketStatus;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class PatientController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspaceAccess;

    public function index(Request $request, SearchPatientsAction $searchAction, AuthorizationService $authService): Response
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, ['patient.registry.view']);

        $can = $this->buildSectionCanMap($request->user(), $authService, [
            'registerPatient' => 'patient.registry.create',
            'mergePatient' => 'patient.registry.create',
            'queue' => 'scheduling.queue.view',
            'appointments' => 'scheduling.appointment.view',
            'clinical' => 'clinical.encounter.view',
            'billing' => 'billing.invoice.view',
            'breakGlass' => 'clinical.break_glass',
        ]);

        $search = $request->query('search', '');
        $patients = $searchAction->execute($search, 50, $can['billing']);
        $selectedId = $request->query('selected');

        $metrics = [
            'total_patients' => Patient::count(),
            'lobby_waiting' => QueueTicket::whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])->whereDate('created_at', today())->count(),
            'today_appointments' => Appointment::whereDate('scheduled_time', today())->where('status', 'Scheduled')->count(),
        ];

        $labTests = LabTest::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'test_code', 'name', 'price', 'category', 'specimen_type']);

        $procedureCatalogs = ProcedureCatalog::where('is_active', true)
            ->where('tier_level', 'Tier1_Minor')
            ->orderBy('name')
            ->get(['id', 'procedure_code', 'name', 'category', 'tier_level', 'standard_price']);

        $activeTickets = QueueTicket::whereIn('status', [QueueTicketStatus::Waiting, QueueTicketStatus::InProgress])
            ->whereDate('created_at', today())
            ->get(['id', 'ticket_number', 'patient_id', 'current_service_point', 'status']);

        return Inertia::render('Domains/Patient/Search', [
            'can' => $can,
            'patients' => $patients,
            'metrics' => $metrics,
            'filters' => $request->only(['search']),
            'selectedId' => $selectedId,
            'labTests' => $labTests,
            'procedureCatalogs' => $procedureCatalogs,
            'activeTickets' => $activeTickets,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Patient::class);

        return Inertia::render('Domains/Patient/Register');
    }

    public function store(StorePatientRequest $request, RegisterPatientAction $registerAction)
    {
        $this->authorize('create', Patient::class);

        $patient = $registerAction->execute($request->validated());

        return redirect()->route('patients.index', ['selected' => $patient->id])
            ->with('success', "Patient {$patient->first_name} {$patient->last_name} ({$patient->primary_mrn}) registered successfully.");
    }

    public function merge(Request $request, MergePatientsAction $mergeAction, AuthorizationService $authService): RedirectResponse
    {
        abort_unless(
            $authService->hasPermission($request->user(), 'patient.registry.create') || $authService->isTenantAdmin($request->user()),
            403,
            'You are not authorized to merge patient records.'
        );

        $validated = $request->validate([
            'winner_id' => 'required|uuid|exists:patients,id',
            'loser_id' => 'required|uuid|different:winner_id|exists:patients,id',
            'justification_reason' => 'required|string|min:10|max:500',
        ]);

        $winner = Patient::with(['identifiers', 'contacts', 'allergies'])->findOrFail($validated['winner_id']);
        $loser = Patient::with(['identifiers', 'contacts', 'allergies'])->findOrFail($validated['loser_id']);

        try {
            $mergeAction->execute($winner, $loser);

            return redirect()->route('patients.show', $winner->id)
                ->with('success', "Patient record {$loser->primary_mrn} ({$loser->first_name} {$loser->last_name}) was successfully merged into {$winner->primary_mrn}.");
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['merge' => $e->getMessage()]);
        }
    }

    public function show(Patient $patient, AuthorizationService $authService): Response
    {
        $this->authorize('view', $patient);

        // Core demographic/clinical chart data (identifiers, allergies,
        // problems, encounters' own vitals/notes/diagnoses/prescriptions) is
        // available to anyone who passed the view check above — the page
        // itself is already gated on patient.registry.view. Radiology and
        // billing are the two genuinely separate view permissions in the
        // catalog, so only those two are conditionally eager-loaded.
        $can = $this->buildSectionCanMap(auth()->user(), $authService, [
            'clinical' => 'clinical.encounter.view',
            'radiology' => 'radiology.order.view',
            'billing' => 'billing.invoice.view',
            'mergePatient' => 'patient.registry.create',
            'storeProblem' => 'clinical.problem-list.manage',
            'storeReconciliation' => 'pharmacy.medication-reconciliation.record',
            'recordAllergy' => 'clinical.allergy.record',
            'amendAllergy' => 'clinical.allergy.verify',
        ]);

        $encounterRelations = ['provider', 'vitals', 'notes', 'diagnoses', 'prescriptions.medication'];
        if ($can['billing']) {
            $encounterRelations[] = 'invoices.lineItems';
        }

        $relations = [
            'identifiers',
            'contacts',
            'emergencyContacts',
            'allergies',
            'problems.recordedBy',
            'medicationReconciliations.reconciler',
            'referrals.toFacility',
            'appointments.provider',
        ];

        if ($can['radiology']) {
            $relations[] = 'radiologyOrders.reports';
            $relations[] = 'radiologyOrders.studies';
        }

        if ($can['billing']) {
            $relations[] = 'invoices.lineItems';
        }

        $relations['encounters'] = function ($q) use ($encounterRelations) {
            $q->with($encounterRelations)->latest('start_time');
        };

        $patient->load($relations);

        $availablePatients = $can['mergePatient']
            ? Patient::where('id', '!=', $patient->id)
                ->where('status', 'Active')
                ->select(['id', 'primary_mrn', 'first_name', 'last_name', 'dob', 'gender', 'blood_group'])
                ->limit(100)
                ->get()
            : collect();

        return Inertia::render('Domains/Patient/Profile', [
            'can' => $can,
            'patient' => $patient,
            'availablePatients' => $availablePatients,
        ]);
    }
}
