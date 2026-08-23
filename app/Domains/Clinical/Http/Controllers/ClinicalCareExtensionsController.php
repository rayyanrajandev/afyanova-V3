<?php

namespace App\Domains\Clinical\Http\Controllers;

use App\Domains\Clinical\Actions\AdministerImmunizationAction;
use App\Domains\Clinical\Actions\CreateReferralAction;
use App\Domains\Clinical\Actions\RecordAncVisitAction;
use App\Domains\Clinical\Actions\RecordConsentAction;
use App\Domains\Clinical\Actions\RecordPartographAction;
use App\Domains\Clinical\Models\Encounter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

/**
 * The statutory/longitudinal-care documentation types added alongside the
 * encounters/vitals/notes chart: informed consent, inter-facility
 * referral, immunization, and the maternal ANC/partograph pair. Each is
 * recorded against an active encounter, matching how ClinicalChartingController
 * anchors vitals/notes — the facility_id these all need is read off that
 * encounter (see the individual Actions), never guessed.
 */
class ClinicalCareExtensionsController extends Controller
{
    use AuthorizesRequests;

    public function storeConsent(Request $request, Encounter $encounter, RecordConsentAction $action)
    {
        $this->authorize('recordConsent', $encounter);

        $validated = $request->validate([
            'consent_type' => 'required|string|in:Surgical,Anesthesia,BloodTransfusion,InvasiveProcedure,GeneralTreatment',
            'procedure_title' => 'required|string|max:255',
            'explanation_of_risks' => 'required|string',
            'alternative_treatments' => 'nullable|string',
            'signatory_type' => 'nullable|string|in:Patient,NextOfKin,Guardian,MedicalPowerOfAttorney',
            'signatory_name' => 'required|string|max:150',
            'signature_fingerprint_token' => 'nullable|string',
            'witness_name' => 'nullable|string|max:150',
            'interpreter_used' => 'nullable|boolean',
            'language_used' => 'nullable|string|max:50',
            'procedure_order_id' => 'nullable|uuid',
        ]);

        try {
            $action->execute($encounter->patient, $validated, $encounter);

            return back()->with('success', 'Consent recorded successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['consent' => $e->getMessage()]);
        }
    }

    public function storeReferral(Request $request, Encounter $encounter, CreateReferralAction $action)
    {
        $this->authorize('createReferral', $encounter);

        $validated = $request->validate([
            'to_facility_id' => 'nullable|uuid',
            'external_facility_name' => 'nullable|string|max:255',
            'urgency' => 'nullable|string|in:Routine,Urgent,Emergency',
            'specialty_required' => 'required|string|max:100',
            'clinical_summary' => 'required|string',
            'investigations_performed' => 'nullable|string',
            'treatments_given' => 'nullable|string',
            'reason_for_referral' => 'required|string',
            'transport_mode' => 'nullable|string|max:50',
        ]);

        try {
            $referral = $action->execute($encounter->patient, $validated, $encounter);

            return back()->with('success', "Referral {$referral->referral_number} dispatched successfully.");
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['referral' => $e->getMessage()]);
        }
    }

    public function storeImmunization(Request $request, Encounter $encounter, AdministerImmunizationAction $action)
    {
        $this->authorize('administerImmunization', $encounter);

        $validated = $request->validate([
            'vaccine_code' => 'required|string|max:30',
            'vaccine_name' => 'required|string|max:100',
            'dose_number' => 'nullable|integer|min:1',
            'batch_number' => 'nullable|string|max:50',
            'expiration_date' => 'nullable|date',
            'administration_site' => 'nullable|string|max:50',
            'route' => 'nullable|string|in:Intramuscular,Subcutaneous,Oral,Intradermal',
            'adverse_reaction_notes' => 'nullable|string',
            'next_due_date' => 'nullable|date',
        ]);

        try {
            $action->execute($encounter->patient, $validated, $encounter);

            return back()->with('success', 'Immunization recorded successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['immunization' => $e->getMessage()]);
        }
    }

    public function storeAncVisit(Request $request, Encounter $encounter, RecordAncVisitAction $action)
    {
        $this->authorize('recordAncVisit', $encounter);

        $validated = $request->validate([
            'gravida' => 'nullable|integer|min:1',
            'para' => 'nullable|integer|min:0',
            'last_menstrual_period' => 'nullable|date',
            'estimated_date_of_delivery' => 'nullable|date',
            'gestational_age_weeks' => 'nullable|integer|min:0|max:45',
            'fundal_height_cm' => 'nullable|numeric',
            'fetal_presentation' => 'nullable|string|in:Cephalic,Breech,Transverse',
            'fetal_heart_rate_bpm' => 'nullable|integer',
            'fetal_movement' => 'nullable|string|in:Normal,Reduced,Absent',
            'urinary_protein' => 'nullable|numeric',
            'iptp_malaria_dose' => 'nullable|string|max:20',
            'iron_folate_given' => 'nullable|boolean',
            'high_risk_flag' => 'nullable|boolean',
            'high_risk_reason' => 'nullable|string',
        ]);

        try {
            $action->execute($encounter, $validated);

            return back()->with('success', 'ANC visit recorded successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['anc' => $e->getMessage()]);
        }
    }

    public function storePartograph(Request $request, Encounter $encounter, RecordPartographAction $action)
    {
        $this->authorize('recordPartograph', $encounter);

        $validated = $request->validate([
            'anc_encounter_id' => 'nullable|uuid',
            'cervical_dilation_cm' => 'required|numeric|min:0|max:10',
            'fetal_heart_rate_bpm' => 'required|integer',
            'liquor_status' => 'nullable|string|in:Intact,Clear,Meconium,Blood',
            'fetal_head_descent' => 'nullable|string',
            'uterine_contractions_per_10min' => 'nullable|integer|min:0',
            'contraction_duration_seconds' => 'nullable|integer|min:0',
            'maternal_systolic_bp' => 'nullable|numeric',
            'maternal_diastolic_bp' => 'nullable|numeric',
            'maternal_pulse_bpm' => 'nullable|integer',
            'alert_line_crossed' => 'nullable|boolean',
            'action_line_crossed' => 'nullable|boolean',
            'midwife_remarks' => 'nullable|string',
        ]);

        try {
            $action->execute($encounter, $validated);

            return back()->with('success', 'Partograph entry recorded successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['partograph' => $e->getMessage()]);
        }
    }
}
