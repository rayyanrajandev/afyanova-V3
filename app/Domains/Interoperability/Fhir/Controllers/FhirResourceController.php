<?php

namespace App\Domains\Interoperability\Fhir\Controllers;

use App\Domains\Clinical\Models\Diagnosis;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\VitalSign;
use App\Domains\Interoperability\Fhir\Services\FhirR4Transformer;
use App\Domains\Patient\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FhirResourceController extends Controller
{
    public function __construct(
        protected FhirR4Transformer $transformer
    ) {}

    public function getPatient(Request $request, string $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);
        $fhir = $this->transformer->patientToFhir($patient);

        return response()->json($fhir, 200, ['Content-Type' => 'application/fhir+json']);
    }

    public function searchPatients(Request $request): JsonResponse
    {
        $query = Patient::query();

        if ($request->has('name')) {
            $name = $request->get('name');
            $query->where(function ($q) use ($name) {
                $q->where('first_name', 'ilike', "%{$name}%")
                  ->orWhere('last_name', 'ilike', "%{$name}%");
            });
        }

        if ($request->has('identifier')) {
            $idVal = $request->get('identifier');
            $query->where('medical_record_number', $idVal)
                  ->orWhere('national_id', $idVal);
        }

        $patients = $query->limit(50)->get();
        $resources = $patients->map(fn ($p) => $this->transformer->patientToFhir($p))->toArray();
        $bundle = $this->transformer->createBundle($resources, 'searchset');

        return response()->json($bundle, 200, ['Content-Type' => 'application/fhir+json']);
    }

    public function getEncounter(Request $request, string $id): JsonResponse
    {
        $encounter = Encounter::with('patient')->findOrFail($id);
        $fhir = $this->transformer->encounterToFhir($encounter);

        return response()->json($fhir, 200, ['Content-Type' => 'application/fhir+json']);
    }

    public function getPatientEverything(Request $request, string $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);
        $encounters = Encounter::where('patient_id', $id)->get();
        $diagnoses = Diagnosis::where('patient_id', $id)->get();
        $vitals = VitalSign::where('patient_id', $id)->get();

        $resources = [$this->transformer->patientToFhir($patient)];

        foreach ($encounters as $enc) {
            $resources[] = $this->transformer->encounterToFhir($enc);
        }

        foreach ($diagnoses as $diag) {
            $resources[] = $this->transformer->diagnosisToFhir($diag);
        }

        foreach ($vitals as $v) {
            foreach ($this->transformer->vitalsToFhir($v) as $obs) {
                $resources[] = $obs;
            }
        }

        $bundle = $this->transformer->createBundle($resources, 'document');

        return response()->json($bundle, 200, ['Content-Type' => 'application/fhir+json']);
    }
}
