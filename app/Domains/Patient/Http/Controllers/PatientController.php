<?php

namespace App\Domains\Patient\Http\Controllers;

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
    use AuthorizesRequests;

    public function index(Request $request, SearchPatientsAction $searchAction): Response
    {
        $search = $request->query('search', '');
        $patients = $searchAction->execute($search);

        return Inertia::render('Domains/Patient/Search', [
            'patients' => $patients,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Domains/Patient/Register');
    }

    public function store(StorePatientRequest $request, RegisterPatientAction $registerAction)
    {
        $this->authorize('create', Patient::class);

        $patient = $registerAction->execute($request->validated());

        return redirect()->route('patients.show', $patient->id)
            ->with('success', 'Patient registered successfully.');
    }

    public function show(Patient $patient): Response
    {
        $patient->load([
            'identifiers',
            'contacts',
            'emergencyContacts',
            'allergies',
            'problems',
            'medicationReconciliations',
            'referrals.toFacility',
            'radiologyOrders.reports',
            'radiologyOrders.studies',
            'encounters' => function ($q) {
                $q->with([
                    'provider',
                    'vitals',
                    'notes',
                    'diagnoses',
                    'prescriptions.medication',
                    'invoices.lineItems',
                ])->latest('start_time');
            },
            'appointments.provider',
            'invoices.lineItems',
        ]);

        return Inertia::render('Domains/Patient/Profile', [
            'patient' => $patient,
        ]);
    }
}
