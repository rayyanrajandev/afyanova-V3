<?php

namespace App\Domains\Clinical\Http\Controllers;

use App\Domains\Clinical\Services\ClinicalDecisionSupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CdssController extends Controller
{
    public function __construct(
        protected ClinicalDecisionSupportService $cdssService
    ) {}

    /**
     * Real-time prescription safety evaluation
     */
    public function evaluatePrescription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'items' => 'required|array',
            'items.*.medication_id' => 'required|string',
            'items.*.dosage' => 'nullable|string',
            'items.*.frequency' => 'nullable|string',
            'existing_prescriptions' => 'nullable|array',
        ]);

        $evaluation = $this->cdssService->evaluatePrescription(
            patientId: $validated['patient_id'],
            items: $validated['items'],
            existingPrescriptions: $validated['existing_prescriptions'] ?? []
        );

        return response()->json($evaluation);
    }

    /**
     * Real-time MEWS physiological deterioration scoring
     */
    public function calculateMews(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'systolic_bp' => 'nullable|numeric',
            'heart_rate' => 'nullable|numeric',
            'respiratory_rate' => 'nullable|numeric',
            'temperature_c' => 'nullable|numeric',
            'oxygen_saturation' => 'nullable|numeric',
            'avpu' => 'nullable|string|in:A,V,P,U,a,v,p,u',
            'patient_age' => 'nullable|integer',
        ]);

        $mews = $this->cdssService->calculateMews(
            vitals: $validated,
            patientAge: $validated['patient_age'] ?? null
        );

        return response()->json($mews);
    }
}
