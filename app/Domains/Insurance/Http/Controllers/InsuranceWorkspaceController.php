<?php

namespace App\Domains\Insurance\Http\Controllers;

use App\Domains\Clinical\Models\Encounter;
use App\Domains\Insurance\Actions\AdjudicateClaimAction;
use App\Domains\Insurance\Actions\GenerateClaimFromEncounterAction;
use App\Domains\Insurance\Actions\RequestPreAuthAction;
use App\Domains\Insurance\Actions\SubmitClaimBatchAction;
use App\Domains\Insurance\Actions\VerifyPolicyEligibilityAction;
use App\Domains\Insurance\Models\InsuranceClaim;
use App\Domains\Insurance\Models\InsuranceProvider;
use App\Domains\Insurance\Models\PatientPolicy;
use App\Domains\Insurance\Models\PreAuthorization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class InsuranceWorkspaceController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $claimsQueue = InsuranceClaim::with(['patient', 'policy.provider', 'policy.scheme', 'encounter.diagnoses', 'items'])
            ->whereIn('status', ['Draft', 'Vetted'])
            ->orderBy('created_at', 'desc')
            ->get();

        $submittedClaims = InsuranceClaim::with(['patient', 'policy.provider', 'policy.scheme', 'encounter.diagnoses', 'items'])
            ->whereIn('status', ['Submitted', 'Approved', 'Queried', 'Partially_Paid', 'Rejected', 'Paid'])
            ->orderBy('updated_at', 'desc')
            ->limit(100)
            ->get();

        $preAuthorizations = PreAuthorization::with(['patient', 'policy.provider', 'encounter'])
            ->orderBy('created_at', 'desc')
            ->get();

        $patientPolicies = PatientPolicy::with(['patient', 'provider', 'scheme'])
            ->orderBy('created_at', 'desc')
            ->get();

        $providers = InsuranceProvider::with(['schemes', 'tariffs'])
            ->where('is_active', true)
            ->get();

        $encountersForClaiming = Encounter::with(['patient', 'provider', 'diagnoses', 'invoices'])
            ->whereDoesntHave('claims')
            ->whereHas('patient.policies')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $metrics = [
            'total_claims' => InsuranceClaim::count(),
            'pending_submission' => $claimsQueue->count(),
            'awaiting_remittance' => InsuranceClaim::where('status', 'Submitted')->count(),
            'total_claimed_value' => floatval(InsuranceClaim::sum('total_claimed_amount')),
            'queried_or_disputed' => InsuranceClaim::whereIn('status', ['Queried', 'Rejected'])->count(),
        ];

        return Inertia::render('Workspace/InsuranceWorkspace', [
            'claimsQueue' => $claimsQueue,
            'submittedClaims' => $submittedClaims,
            'preAuthorizations' => $preAuthorizations,
            'patientPolicies' => $patientPolicies,
            'providers' => $providers,
            'encountersForClaiming' => $encountersForClaiming,
            'metrics' => $metrics,
        ]);
    }

    public function generateClaim(Request $request, GenerateClaimFromEncounterAction $action)
    {
        $validated = $request->validate([
            'encounter_id' => 'required|string|exists:encounters,id',
            'patient_policy_id' => 'nullable|string|exists:patient_policies,id',
        ]);

        try {
            $encounter = Encounter::findOrFail((string) $validated['encounter_id']);
            $policy = ! empty($validated['patient_policy_id']) ? PatientPolicy::find((string) $validated['patient_policy_id']) : null;

            $claim = $action->execute($encounter, $policy);

            $msg = $claim->scrubber_passed
                ? 'Claim generated and passed scrubber checks successfully.'
                : 'Claim generated in draft status. Review scrubber audit warnings.';

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->withErrors(['claim_generation' => $e->getMessage()]);
        }
    }

    public function verifyPolicy(PatientPolicy $policy, VerifyPolicyEligibilityAction $action)
    {
        try {
            $action->execute($policy, true);

            return back()->with('success', 'Policy card and biometric eligibility verified successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['verify_policy' => $e->getMessage()]);
        }
    }

    public function storePreAuth(Request $request, RequestPreAuthAction $action)
    {
        $validated = $request->validate([
            'patient_policy_id' => 'required|string|exists:patient_policies,id',
            'encounter_id' => 'nullable|string|exists:encounters,id',
            'procedure_description' => 'required|string|max:255',
            'requested_amount' => 'required|numeric|min:0',
            'approved_amount' => 'nullable|numeric|min:0',
            'auth_code' => 'nullable|string|max:50',
            'expires_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $action->execute($validated);

            return back()->with('success', 'Pre-authorization approval issued and tracked.');
        } catch (\Exception $e) {
            return back()->withErrors(['pre_auth' => $e->getMessage()]);
        }
    }

    public function adjudicate(Request $request, InsuranceClaim $claim, AdjudicateClaimAction $action)
    {
        $this->authorize('adjudicate', $claim);

        $validated = $request->validate([
            'status' => 'required|string|in:Approved,Partially_Paid,Queried,Rejected',
            'approved_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $action->execute(
                $claim,
                $validated['status'],
                isset($validated['approved_amount']) ? floatval($validated['approved_amount']) : null,
                $validated['notes'] ?? null
            );

            return back()->with('success', 'Remittance adjudication recorded successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['adjudicate' => $e->getMessage()]);
        }
    }

    public function batchSubmit(Request $request, SubmitClaimBatchAction $action)
    {
        $validated = $request->validate([
            'claim_ids' => 'required|array|min:1',
            'claim_ids.*' => 'required|string|exists:insurance_claims,id',
        ]);

        try {
            $batchNo = $action->execute($validated['claim_ids']);

            return back()->with('success', "Batch {$batchNo} submitted to insurance claims clearinghouse.");
        } catch (\Exception $e) {
            return back()->withErrors(['batch_submit' => $e->getMessage()]);
        }
    }
}
