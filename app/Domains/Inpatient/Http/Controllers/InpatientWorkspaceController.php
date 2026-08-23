<?php

namespace App\Domains\Inpatient\Http\Controllers;

use App\Domains\Identity\Models\User;
use App\Domains\Inpatient\Actions\AdministerMedicationAction;
use App\Domains\Inpatient\Actions\AdmitPatientAction;
use App\Domains\Inpatient\Actions\DischargePatientAction;
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
    use AuthorizesRequests;

    public function index(): Response
    {
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

        $doctors = User::where('is_active', true)->get();
        $nurses = User::where('is_active', true)->get();

        // Ward cabinets & pharmacy locations
        $wardCabinets = InventoryLocation::where('is_active', true)
            ->orderBy('name')
            ->get();

        $itemMasters = ItemMaster::where('is_active', true)
            ->whereIn('category', ['Pharmaceutical', 'Surgical_Consumable', 'IPC_Chemical', 'Medical_Gas'])
            ->orderBy('name')
            ->get();

        // Available ward stock balances
        $wardStockBalances = InventoryStockBalance::with(['item', 'location'])
            ->where('quantity_on_hand', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->get();

        return Inertia::render('Workspace/InpatientWorkspace', [
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
}
