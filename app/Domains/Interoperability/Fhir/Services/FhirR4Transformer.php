<?php

namespace App\Domains\Interoperability\Fhir\Services;

use App\Domains\Clinical\Models\Diagnosis;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\VitalSign;
use App\Domains\Patient\Models\Patient;
use Illuminate\Support\Carbon;

class FhirR4Transformer
{
    /**
     * Convert Patient model to FHIR R4 Patient Resource
     */
    public function patientToFhir(Patient $patient): array
    {
        return [
            'resourceType' => 'Patient',
            'id' => $patient->id,
            'meta' => [
                'versionId' => '1',
                'lastUpdated' => $patient->updated_at ? Carbon::parse($patient->updated_at)->toIso8601String() : Carbon::now()->toIso8601String(),
                'profile' => ['http://hl7.org/fhir/StructureDefinition/Patient'],
            ],
            'identifier' => array_filter([
                [
                    'use' => 'usual',
                    'type' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                                'code' => 'MR',
                                'display' => 'Medical Record Number',
                            ]
                        ]
                    ],
                    'system' => 'urn:afyanova:mrn',
                    'value' => $patient->medical_record_number ?? $patient->mrn,
                ],
                $patient->national_id ? [
                    'use' => 'official',
                    'type' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                                'code' => 'NNTZ',
                                'display' => 'National ID / NIDA',
                            ]
                        ]
                    ],
                    'system' => 'urn:tz:nida',
                    'value' => $patient->national_id,
                ] : null,
            ]),
            'active' => true,
            'name' => [
                [
                    'use' => 'official',
                    'family' => $patient->last_name,
                    'given' => array_filter([$patient->first_name, $patient->middle_name]),
                    'text' => trim("{$patient->first_name} {$patient->middle_name} {$patient->last_name}"),
                ]
            ],
            'telecom' => array_filter([
                $patient->phone_number ? [
                    'system' => 'phone',
                    'value' => $patient->phone_number,
                    'use' => 'mobile',
                ] : null,
                $patient->email ? [
                    'system' => 'email',
                    'value' => $patient->email,
                    'use' => 'home',
                ] : null,
            ]),
            'gender' => strtolower($patient->gender) === 'female' ? 'female' : 'male',
            'birthDate' => $patient->date_of_birth ? Carbon::parse($patient->date_of_birth)->toDateString() : null,
            'address' => [
                [
                    'use' => 'home',
                    'line' => array_filter([$patient->street, $patient->ward]),
                    'city' => $patient->district ?? 'Dar es Salaam',
                    'state' => $patient->region ?? 'Dar es Salaam',
                    'country' => 'TZA',
                ]
            ],
        ];
    }

    /**
     * Convert Encounter model to FHIR R4 Encounter Resource
     */
    public function encounterToFhir(Encounter $encounter): array
    {
        return [
            'resourceType' => 'Encounter',
            'id' => $encounter->id,
            'status' => strtolower($encounter->status ?? 'finished'),
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => $encounter->encounter_type === 'Inpatient' ? 'IMP' : 'AMB',
                'display' => $encounter->encounter_type === 'Inpatient' ? 'inpatient encounter' : 'ambulatory',
            ],
            'subject' => [
                'reference' => "Patient/{$encounter->patient_id}",
                'display' => $encounter->patient ? "{$encounter->patient->first_name} {$encounter->patient->last_name}" : null,
            ],
            'period' => [
                'start' => $encounter->created_at ? Carbon::parse($encounter->created_at)->toIso8601String() : null,
                'end' => $encounter->completed_at ? Carbon::parse($encounter->completed_at)->toIso8601String() : null,
            ],
            'reasonCode' => array_filter([
                $encounter->chief_complaint ? [
                    'text' => $encounter->chief_complaint,
                ] : null,
            ]),
        ];
    }

    /**
     * Convert VitalSign to FHIR R4 Observation Resources
     */
    public function vitalsToFhir(VitalSign $vitals): array
    {
        $observations = [];

        if ($vitals->systolic_bp && $vitals->diastolic_bp) {
            $observations[] = [
                'resourceType' => 'Observation',
                'id' => $vitals->id . '_bp',
                'status' => 'final',
                'category' => [
                    [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                                'code' => 'vital-signs',
                                'display' => 'Vital Signs',
                            ]
                        ]
                    ]
                ],
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '85354-9',
                            'display' => 'Blood pressure panel with all children optional',
                        ]
                    ]
                ],
                'subject' => [
                    'reference' => "Patient/{$vitals->patient_id}",
                ],
                'component' => [
                    [
                        'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8480-6', 'display' => 'Systolic blood pressure']]],
                        'valueQuantity' => ['value' => floatval($vitals->systolic_bp), 'unit' => 'mmHg', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]'],
                    ],
                    [
                        'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8462-4', 'display' => 'Diastolic blood pressure']]],
                        'valueQuantity' => ['value' => floatval($vitals->diastolic_bp), 'unit' => 'mmHg', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]'],
                    ],
                ]
            ];
        }

        if ($vitals->heart_rate || $vitals->pulse_rate) {
            $hr = $vitals->heart_rate ?: $vitals->pulse_rate;
            $observations[] = [
                'resourceType' => 'Observation',
                'id' => $vitals->id . '_hr',
                'status' => 'final',
                'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8867-4', 'display' => 'Heart rate']]],
                'subject' => ['reference' => "Patient/{$vitals->patient_id}"],
                'valueQuantity' => ['value' => floatval($hr), 'unit' => 'beats/minute', 'system' => 'http://unitsofmeasure.org', 'code' => '/min'],
            ];
        }

        if ($vitals->temperature) {
            $observations[] = [
                'resourceType' => 'Observation',
                'id' => $vitals->id . '_temp',
                'status' => 'final',
                'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8310-5', 'display' => 'Body temperature']]],
                'subject' => ['reference' => "Patient/{$vitals->patient_id}"],
                'valueQuantity' => ['value' => floatval($vitals->temperature), 'unit' => 'degrees C', 'system' => 'http://unitsofmeasure.org', 'code' => 'Cel'],
            ];
        }

        return $observations;
    }

    /**
     * Convert Diagnosis model to FHIR R4 Condition Resource
     */
    public function diagnosisToFhir(Diagnosis $diagnosis): array
    {
        $code = $diagnosis->icd_10_code ?: ($diagnosis->icd10_code ?: 'R69');
        $desc = $diagnosis->description ?: 'Unspecified condition';

        return [
            'resourceType' => 'Condition',
            'id' => $diagnosis->id,
            'clinicalStatus' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                        'code' => 'active',
                        'display' => 'Active',
                    ]
                ]
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://hl7.org/fhir/sid/icd-10',
                        'code' => $code,
                        'display' => $desc,
                    ]
                ],
                'text' => $desc,
            ],
            'subject' => [
                'reference' => "Patient/{$diagnosis->patient_id}",
            ],
            'encounter' => $diagnosis->encounter_id ? [
                'reference' => "Encounter/{$diagnosis->encounter_id}",
            ] : null,
            'recordedDate' => $diagnosis->created_at ? Carbon::parse($diagnosis->created_at)->toDateString() : null,
        ];
    }

    /**
     * Create FHIR R4 Bundle
     */
    public function createBundle(array $resources, string $bundleType = 'collection'): array
    {
        $entries = array_map(function ($resource) {
            return [
                'fullUrl' => "urn:uuid:{$resource['id']}",
                'resource' => $resource,
            ];
        }, $resources);

        return [
            'resourceType' => 'Bundle',
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => $bundleType,
            'timestamp' => Carbon::now()->toIso8601String(),
            'total' => count($entries),
            'entry' => $entries,
        ];
    }
}
