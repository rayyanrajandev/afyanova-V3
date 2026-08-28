<?php

namespace App\Domains\Inpatient\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Inpatient\Actions\AdministerMedicationAction;
use App\Domains\Inpatient\Actions\AdmitPatientAction;
use App\Domains\Inpatient\Actions\DischargePatientAction;
use App\Domains\Inpatient\Actions\GenerateDailyBedChargesAction;
use App\Domains\Inpatient\Actions\TransferBedAction;
use App\Domains\Inpatient\Exceptions\InpatientException;
use App\Domains\Inpatient\Models\Admission;
use App\Domains\Inpatient\Models\Bed;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Patient\Models\Patient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class InpatientWorkspaceController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspaceAccess;

    public function index(Request $request, AuthorizationService $authService): Response
    {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, ['inpatient.ward.view']);

        $can = $this->buildSectionCanMap($request->user(), $authService, [
            'admit' => 'inpatient.admission.create',
            'transfer' => 'inpatient.admission.transfer',
            'discharge' => 'inpatient.admission.discharge',
            'administerMar' => 'inpatient.mar.administer',
            'updateBedStatus' => 'inpatient.bed.manage',
            'generateBedCharges' => 'inpatient.ward.manage',
        ]);

        $wards = Ward::with([
            'beds' => function ($q) {
                $q->with('currentAdmission.patient');
            },
            'activeAdmissions.patient',
        ])
            ->where('is_active', true)
            ->get();

        $beds = Bed::with(['ward', 'currentAdmission.patient'])
            ->orderBy('bed_number', 'asc')
            ->get();

        $activeAdmissions = Admission::with([
            'patient.allergies',
            'patient.latestVital',
            'ward',
            'bed',
            'admittingDoctor',
            'encounter.vitals',
            'transfers.fromWard',
            'transfers.toWard',
            'transfers.fromBed',
            'transfers.toBed',
            'transfers.performer',
            'medicationAdministrationRecords.administeredByUser',
            'medicationAdministrationRecords.witnessUser',
            'medicationAdministrationRecords.location',
            'medicationAdministrationRecords.itemMaster',
        ])
            ->where('status', 'Admitted')
            ->latest('admitted_at')
            ->get();

        $dischargedAdmissions = Admission::with([
            'patient',
            'ward',
            'bed',
            'admittingDoctor',
            'dischargedBy',
        ])
            ->where('status', 'Discharged')
            ->latest('discharged_at')
            ->take(25)
            ->get();

        // Active admitted patient IDs
        $admittedPatientIds = $activeAdmissions->pluck('patient_id')->filter()->unique()->toArray();
        $availablePatients = Patient::whereNotIn('id', $admittedPatientIds)
            ->with(['allergies', 'latestVital'])
            ->latest('created_at')
            ->take(50)
            ->get();

        $doctors = User::where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->where('slug', 'doctor'))
            ->get();
        $nurses = User::where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->where('slug', 'nurse'))
            ->get();

        // Ward cabinets & pharmacy locations
        $wardCabinets = InventoryLocation::where('is_active', true)
            ->orderBy('name')
            ->get();

        $itemMasters = ItemMaster::where('is_active', true)
            ->whereIn('category', ['Pharmaceutical', 'Surgical_Consumable', 'IPC_Chemical', 'Medical_Gas'])
            ->orderBy('name')
            ->get();

        // Available ward stock balances (earliest-expiring batch first)
        $wardStockBalances = InventoryStockBalance::with(['medication', 'location', 'batch'])
            ->where('quantity_on_hand', '>', 0)
            ->get()
            ->sortBy(fn ($balance) => $balance->batch?->expiry_date)
            ->values();

        return Inertia::render('Workspace/InpatientWorkspace', [
            'can' => $can,
            'wards' => $wards,
            'beds' => $beds,
            'activeAdmissions' => $activeAdmissions,
            'dischargedAdmissions' => $dischargedAdmissions,
            'availablePatients' => $availablePatients,
            'doctors' => $doctors,
            'nurses' => $nurses,
            'wardCabinets' => $wardCabinets,
            'itemMasters' => $itemMasters,
            'wardStockBalances' => $wardStockBalances,
        ]);
    }

    public function admit(Request $request, AdmitPatientAction $action)
    {
        $this->authorize('admit', Admission::class);

        $validated = $request->validate([
            'patient_id' => 'required|string',
            'bed_id' => 'required|string',
            'ward_id' => 'nullable|string',
            'admitting_doctor_id' => 'nullable|string',
            'admission_reason' => 'required|string|max:1000',
            'provisional_diagnosis' => 'nullable|string|max:255',
        ]);

        try {
            $admission = $action->execute($validated);

            return back()->with('success', "Patient admitted successfully (Admission #{$admission->admission_number}).");
        } catch (InpatientException $e) {
            return back()->withErrors(['admit' => $e->getMessage()]);
        }
    }

    public function transfer(Request $request, Admission $admission, TransferBedAction $action)
    {
        $this->authorize('transfer', $admission);

        $validated = $request->validate([
            'to_bed_id' => 'required|string',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $action->execute($admission, $validated);

            return back()->with('success', 'Patient transferred to new bed successfully.');
        } catch (InpatientException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }
    }

    public function discharge(Request $request, Admission $admission, DischargePatientAction $action)
    {
        $this->authorize('discharge', $admission);

        $validated = $request->validate([
            'discharge_disposition' => 'required|string',
            'discharge_summary' => 'required|string|max:2000',
        ]);

        try {
            $action->execute($admission, $validated);

            return back()->with('success', 'Patient discharged successfully. Bed released for sanitation.');
        } catch (InpatientException $e) {
            return back()->withErrors(['discharge' => $e->getMessage()]);
        }
    }

    public function administerMar(Request $request, Admission $admission, AdministerMedicationAction $action)
    {
        $this->authorize('administerMar', $admission);

        $validated = $request->validate([
            'item_master_id' => 'nullable|string',
            'item_name' => 'required|string|max:255',
            'location_id' => 'nullable|string',
            'batch_number' => 'nullable|string',
            'dose_quantity' => 'required|numeric|min:0.01',
            'dose_unit' => 'nullable|string|max:50',
            'route' => 'required|string|max:50',
            'frequency' => 'nullable|string|max:50',
            'is_dda_narcotic' => 'nullable|boolean',
            'witness_by' => 'nullable|string',
            'witness_pin_verified' => 'nullable|boolean',
            'status' => 'required|in:Administered,Refused,Held,Missed',
            'charge_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['tenant_id'] = auth()->user()->tenant_id;
        $validated['admission_id'] = $admission->id;
        $validated['administered_by'] = auth()->id();

        try {
            $action->execute($validated);

            return back()->with('success', 'e-MAR dose recorded and ward stock deducted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['mar' => $e->getMessage()]);
        }
    }

    public function updateBedStatus(Request $request, Bed $bed)
    {
        $this->authorize('updateStatus', $bed);

        $validated = $request->validate([
            'status' => 'required|in:Available,Cleaning,Maintenance,Reserved',
        ]);

        if ($bed->status === 'Occupied' && $validated['status'] !== 'Occupied') {
            return back()->withErrors(['bed' => 'Cannot change status of an occupied bed directly. Discharge or transfer the patient first.']);
        }

        $bed->update(['status' => $validated['status']]);

        return back()->with('success', "Bed {$bed->bed_number} status updated to {$validated['status']}.");
    }

    public function generateBedCharges(Request $request, GenerateDailyBedChargesAction $action, AuthorizationService $authService)
    {
        abort_unless($authService->hasPermission($request->user(), 'inpatient.ward.manage') || $authService->isTenantAdmin($request->user()), 403);

        $validated = $request->validate([
            'date' => 'nullable|date',
        ]);

        try {
            $result = $action->execute($validated['date'] ?? null);

            return back()->with('success', "Midnight bed billing executed: {$result['billed_count']} admission(s) charged TZS ".number_format($result['total_amount'], 2)." ({$result['skipped_count']} already billed/skipped).");
        } catch (\Throwable $e) {
            return back()->withErrors(['bed_charges' => $e->getMessage()]);
        }
    }

    public function storeWard(Request $request, AuthorizationService $authService)
    {
        abort_unless($authService->hasPermission($request->user(), 'inpatient.ward.manage') || $authService->isTenantAdmin($request->user()), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50',
            'ward_type' => 'required|string|max:50',
            'gender_restriction' => 'required|in:None,Male_Only,Female_Only,Pediatric',
            'floor_location' => 'nullable|string|max:100',
            'daily_base_rate' => 'required|numeric|min:0',
        ]);

        $validated['tenant_id'] = $request->user()->tenant_id;
        $validated['facility_id'] = $request->user()->facility_id ?? Facility::first()?->id;
        $validated['is_active'] = true;

        $ward = Ward::create($validated);

        return back()->with('success', "Ward {$ward->name} registered successfully.");
    }

    public function updateWard(Request $request, Ward $ward, AuthorizationService $authService)
    {
        abort_unless($authService->hasPermission($request->user(), 'inpatient.ward.manage') || $authService->isTenantAdmin($request->user()), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50',
            'ward_type' => 'required|string|max:50',
            'gender_restriction' => 'required|in:None,Male_Only,Female_Only,Pediatric',
            'floor_location' => 'nullable|string|max:100',
            'daily_base_rate' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $ward->update($validated);

        return back()->with('success', "Ward {$ward->name} updated successfully.");
    }

    public function storeBed(Request $request, AuthorizationService $authService)
    {
        abort_unless($authService->hasPermission($request->user(), 'inpatient.bed.manage') || $authService->isTenantAdmin($request->user()), 403);

        $validated = $request->validate([
            'ward_id' => 'required|uuid|exists:wards,id',
            'bed_number' => 'required|string|max:50',
            'bed_type' => 'required|string|max:50',
            'daily_rate_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $validated['tenant_id'] = $request->user()->tenant_id;
        $validated['facility_id'] = $request->user()->facility_id ?? Facility::first()?->id;
        $validated['status'] = 'Available';
        $validated['daily_rate_amount'] = $validated['daily_rate_amount'] ?? 0;

        $bed = Bed::create($validated);

        return back()->with('success', "Bed {$bed->bed_number} added to ward successfully.");
    }
}
