<?php

namespace App\Domains\Tenancy\Actions;

use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncMasterDictionaryAction
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Broadcast standardized master data (ICD-10, NEMLIT, LOINC) across tenants
     */
    public function execute(string $dictionaryType, ?string $targetTenantId = null): array
    {
        $tenantsQuery = Tenant::query();
        if ($targetTenantId) {
            $tenantsQuery->where('id', $targetTenantId);
        }
        $tenants = $tenantsQuery->get();

        $syncedCount = 0;
        $previousTenantId = null;
        if (DB::getDriverName() === 'pgsql') {
            $previousTenantId = DB::scalar("SELECT current_setting('app.current_tenant_id', true)");
        }

        try {
            foreach ($tenants as $tenant) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('SELECT set_config(?, ?, false)', ['app.current_tenant_id', $tenant->id]);
                }

                if ($dictionaryType === 'nemlit' || $dictionaryType === 'all') {
                    // Tanzania National Essential Medicines List (NEMLIT) master items
                    $standardMedicines = [
                        ['name' => 'Amoxicillin 500mg Caps', 'generic_name' => 'Amoxicillin', 'category' => 'Antibiotic', 'form' => 'Capsule', 'strength' => '500mg'],
                        ['name' => 'Paracetamol 500mg Tabs', 'generic_name' => 'Paracetamol', 'category' => 'Analgesic', 'form' => 'Tablet', 'strength' => '500mg'],
                        ['name' => 'Artemether / Lumefantrine (ALu) 20/120mg', 'generic_name' => 'Artemether + Lumefantrine', 'category' => 'Antimalarial', 'form' => 'Tablet', 'strength' => '20/120mg'],
                        ['name' => 'Metformin 500mg Tabs', 'generic_name' => 'Metformin HCl', 'category' => 'Antidiabetic', 'form' => 'Tablet', 'strength' => '500mg'],
                        ['name' => 'Amlodipine 5mg Tabs', 'generic_name' => 'Amlodipine Besylate', 'category' => 'Antihypertensive', 'form' => 'Tablet', 'strength' => '5mg'],
                        ['name' => 'Ceftriaxone 1g Inj', 'generic_name' => 'Ceftriaxone Sodium', 'category' => 'Antibiotic', 'form' => 'Injection', 'strength' => '1g'],
                        ['name' => 'Oral Rehydration Salts (ORS) Sachet', 'generic_name' => 'ORS Formula', 'category' => 'Electrolytes', 'form' => 'Powder', 'strength' => 'Standard'],
                        ['name' => 'Zinc Sulfate 20mg Dispersible', 'generic_name' => 'Zinc Sulfate', 'category' => 'Supplements', 'form' => 'Dispersible Tablet', 'strength' => '20mg'],
                    ];

                    foreach ($standardMedicines as $med) {
                        MedicationFormulary::firstOrCreate(
                            [
                                'tenant_id' => $tenant->id,
                                'name' => $med['name'],
                            ],
                            [
                                'generic_name' => $med['generic_name'],
                                'drug_category' => $med['category'],
                                'dosage_form' => $med['form'],
                                'strength' => $med['strength'],
                                'is_controlled' => false,
                                'is_active' => true,
                            ]
                        );
                        $syncedCount++;
                    }
                }

                if ($dictionaryType === 'loinc' || $dictionaryType === 'all') {
                    // Standard MoH Clinical Laboratory Diagnostic Test Catalog
                    $standardLabTests = [
                        ['name' => 'Malaria Rapid Diagnostic Test (mRDT)', 'category' => 'Parasitology', 'code' => 'LAB-MRDT', 'turnaround' => 15],
                        ['name' => 'Full Blood Picture (FBP / CBC)', 'category' => 'Hematology', 'code' => 'LAB-FBP', 'turnaround' => 30],
                        ['name' => 'Urinalysis Multi-Stix & Microscopy', 'category' => 'Biochemistry', 'code' => 'LAB-URI', 'turnaround' => 20],
                        ['name' => 'Blood Grouping & Crossmatch (ABO/Rh)', 'category' => 'Immunology', 'code' => 'LAB-ABO', 'turnaround' => 25],
                        ['name' => 'Random Blood Glucose (RBS)', 'category' => 'Biochemistry', 'code' => 'LAB-RBS', 'turnaround' => 10],
                        ['name' => 'Serum Creatinine & eGFR', 'category' => 'Renal Profile', 'code' => 'LAB-CREAT', 'turnaround' => 45],
                    ];

                    foreach ($standardLabTests as $test) {
                        LabTest::firstOrCreate(
                            [
                                'tenant_id' => $tenant->id,
                                'test_code' => $test['code'],
                            ],
                            [
                                'name' => $test['name'],
                                'category' => $test['category'],
                                'standard_turnaround_time_minutes' => $test['turnaround'],
                                'is_active' => true,
                            ]
                        );
                        $syncedCount++;
                    }
                }
            }
        } finally {
            if (DB::getDriverName() === 'pgsql' && $previousTenantId !== null) {
                DB::statement('SELECT set_config(?, ?, false)', ['app.current_tenant_id', $previousTenantId]);
            }
        }

        $this->auditLogger->log([
            'tenant_id' => $targetTenantId ?? $tenants->first()?->id ?? (string) Str::uuid(),
            'facility_id' => null,
            'user_id' => null,
            'event_category' => 'PLATFORM_SUPERADMIN',
            'action' => 'MASTER_DICTIONARY_SYNC',
            'entity_type' => Tenant::class,
            'entity_id' => $targetTenantId ?? (string) Str::uuid(),
            'before_state' => null,
            'after_state' => json_encode([
                'dictionary_type' => $dictionaryType,
                'tenants_synced' => $tenants->count(),
                'records_processed' => $syncedCount,
            ]),
            'justification_reason' => 'Superadmin automated master dictionary broadcast to hospital databases.',
        ]);

        return [
            'success' => true,
            'dictionary_type' => $dictionaryType,
            'tenants_synced' => $tenants->count(),
            'records_synced' => $syncedCount,
        ];
    }
}
