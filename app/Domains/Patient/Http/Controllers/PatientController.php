<?php

namespace App\Domains\Patient\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Patient\Actions\RegisterPatientAction;
use App\Domains\Patient\Actions\SearchPatientsAction;
use App\Domains\Patient\Http\Requests\StorePatientRequest;
use App\Domains\Patient\Models\Patient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspaceAccess;

    public function index(Request $request, SearchPatientsAction $searchAction, AuthorizationService $authService): Response
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, ['patient.registry.view']);

        $search = $request->query('search', '');
        $patients = $searchAction->execute($search);

        return Inertia::render('Domains/Patient/Search', [
            'patients' => $patients,
            'filters' => $request->only(['search']),
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

        return redirect()->route('patients.show', $patient->id)
            ->with('success', 'Patient registered successfully.');
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
            'radiology' => 'radiology.order.view',
            'billing' => 'billing.invoice.view',
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

        return Inertia::render('Domains/Patient/Profile', [
            'can' => $can,
            'patient' => $patient,
        ]);
    }
}
