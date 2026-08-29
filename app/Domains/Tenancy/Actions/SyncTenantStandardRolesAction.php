<?php

namespace App\Domains\Tenancy\Actions;

use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

class SyncTenantStandardRolesAction
{
    public function execute(?string $targetTenantId = null): int
    {
        $tenantsQuery = Tenant::query();
        if ($targetTenantId) {
            $tenantsQuery->where('id', $targetTenantId);
        }
        $tenants = $tenantsQuery->get();

        $allPermsMap = Permission::pluck('id', 'slug')->toArray();
        $tenantPermsMap = Permission::where('domain', '!=', 'Platform')->pluck('id', 'slug')->toArray();

        $standardRoles = [
            'tenant-admin' => [
                'name' => 'Tenant Administrator',
                'desc' => 'Executive administration, user provisioning, facility scoping, and audit oversight.',
                'perms' => array_keys($tenantPermsMap),
            ],
            'doctor' => [
                'name' => 'Medical Officer / Clinician',
                'desc' => 'Clinical diagnosis, SOAP charting, lab ordering, Rx prescriptions, and theatre surgery.',
                'perms' => [
                    'clinical.encounter.create', 'clinical.encounter.view', 'clinical.encounter.update', 'clinical.encounter.close',
                    'clinical.encounter.override', 'clinical.break_glass',
                    'clinical.notes.sign', 'clinical.notes.create', 'clinical.vitals.record', 'clinical.diagnosis.manage',
                    'clinical.consent.record', 'clinical.referral.create', 'clinical.immunization.administer',
                    'clinical.anc.record', 'clinical.partograph.record', 'clinical.problem-list.manage',
                    'clinical.allergy.record', 'clinical.allergy.verify',
                    'pharmacy.prescription.create', 'pharmacy.prescription.view',
                    'lab.order.create', 'lab.order.view', 'procedure.order.create', 'procedure.order.execute', 'procedure.order.view',
                    'procedure.theatre.book', 'procedure.theatre.checklist', 'procedure.theatre.pacu',
                    'radiology.order.create', 'radiology.order.view',
                    'inpatient.admission.create', 'inpatient.admission.discharge', 'inpatient.admission.transfer', 'inpatient.bed.transfer',
                    'inpatient.ward.view',
                    'inventory.dda.record',
                    'patient.registry.view',
                    'reports.clinical.view',
                    'scheduling.queue.call', 'scheduling.queue.transfer', 'scheduling.queue.view', 'scheduling.appointment.view',
                ],
            ],
            'nurse' => [
                'name' => 'Nurse / Triage Officer',
                'desc' => 'Vital signs, queue triage, dressing desk procedures, MAR medication administration, and ward notes.',
                'perms' => [
                    'clinical.vitals.record', 'clinical.encounter.view', 'clinical.notes.create',
                    'clinical.immunization.administer', 'clinical.anc.record', 'clinical.partograph.record',
                    'clinical.allergy.record',
                    'procedure.execute.dressing', 'procedure.theatre.checklist', 'procedure.theatre.pacu', 'procedure.order.view',
                    'inpatient.ward.view', 'inpatient.bed.transfer', 'inpatient.bed.manage',
                    'inpatient.admission.transfer', 'inpatient.mar.administer', 'inventory.dda.record',
                    'inventory.dda.view', 'inventory.stock.view',
                    'inventory.requisition.create', 'inventory.requisition.confirm',
                    'scheduling.queue.call', 'scheduling.queue.transfer', 'scheduling.queue.view', 'scheduling.appointment.view',
                    'patient.registry.view',
                ],
            ],
            'lab-technologist' => [
                'name' => 'Laboratory Scientist / Technologist',
                'desc' => 'Specimen collection, bench testing, analyzer result entry, and laboratory verification.',
                'perms' => ['lab.specimen.collect', 'lab.result.record', 'lab.result.verify', 'lab.order.view', 'lab.catalog.manage', 'patient.registry.view'],
            ],
            'radiologist' => [
                'name' => 'Radiologist',
                'desc' => 'Diagnostic imaging report authoring, sign-off, and clinical amendment.',
                'perms' => ['radiology.order.view', 'radiology.study.acquire', 'radiology.report.sign', 'radiology.report.amend', 'patient.registry.view'],
            ],
            'pharmacist' => [
                'name' => 'Pharmacist',
                'desc' => 'Prescription safety review, drug-allergy vetting, clinical dispensing, and medication reconciliation.',
                'perms' => [
                    'pharmacy.prescription.view', 'pharmacy.prescription.verify', 'pharmacy.dispense.execute',
                    'pharmacy.medication-reconciliation.record', 'pharmacy.inventory.adjust', 'pharmacy.inventory.receive', 'pharmacy.inventory.view',
                    'inventory.location.view', 'inventory.stock.view', 'inventory.catalog.view', 'inventory.dda.view',
                    'inpatient.ward.view', 'clinical.allergy.verify',
                    'patient.registry.view',
                ],
            ],
            'cashier' => [
                'name' => 'Cashier / Billing Officer',
                'desc' => 'Patient invoice generation, cash/M-Pesa collection, and shift reconciliation.',
                'perms' => ['billing.invoice.create', 'billing.invoice.view', 'billing.payment.collect', 'billing.shift.reconcile', 'billing.shift.open', 'billing.shift.close', 'patient.registry.view'],
            ],
            'insurance-manager' => [
                'name' => 'Billing & Insurance Manager',
                'desc' => 'Insurance tariffs, NHIF claims batching, pre-auth approvals, discount authorization, and refunds.',
                'perms' => [
                    'insurance.claim.create', 'insurance.claim.submit', 'insurance.claim.vet', 'insurance.claim.adjudicate', 'insurance.claim.view',
                    'insurance.policy.verify', 'insurance.preauth.create',
                    'insurance.tariff.manage', 'billing.discount.approve', 'billing.refund.issue', 'reports.financial.view',
                    'billing.invoice.view',
                    'patient.registry.view',
                ],
            ],
            'inventory-officer' => [
                'name' => 'Inventory & Store Officer',
                'desc' => 'Purchase orders, GRN batch inward posting, stock transfers handshake, and physical stocktaking.',
                'perms' => [
                    'inventory.location.view', 'inventory.transfer.dispatch', 'inventory.transfer.confirm',
                    'inventory.po.create', 'inventory.po.approve', 'inventory.grn.receive',
                    'inventory.stocktake.create', 'inventory.stocktake.approve', 'reports.pharmacoeconomic.view',
                    'inventory.stock.view', 'inventory.catalog.view', 'inventory.requisition.view', 'inventory.transfer.view',
                    'inventory.po.view', 'inventory.predictive.view', 'inventory.grn.view', 'inventory.dda.view',
                    'inventory.gas.view', 'inventory.stocktake.view',
                    'inventory.catalog.manage', 'inventory.requisition.approve', 'inventory.requisition.issue',
                    'inventory.predictive.generate',
                ],
            ],
            'receptionist' => [
                'name' => 'Receptionist / Registration Clerk',
                'desc' => 'Universal patient registration, MPI search, appointment booking, and triage ticketing.',
                'perms' => [
                    'patient.registry.create', 'patient.registry.view',
                    'scheduling.appointment.create', 'scheduling.appointment.checkin', 'scheduling.appointment.view',
                    'scheduling.queue.call', 'scheduling.queue.transfer', 'scheduling.queue.view',
                ],
            ],
            'auditor' => [
                'name' => 'Medical Auditor / Compliance',
                'desc' => 'Read-only inspection of clinical records, financial ledgers, and security audit trails.',
                'perms' => ['clinical.encounter.view', 'billing.invoice.view', 'insurance.claim.view', 'reports.clinical.view', 'reports.financial.view', 'reports.analytics.view', 'audit.log.view', 'patient.registry.view', 'inpatient.ward.view', 'pharmacy.prescription.view', 'inventory.dda.view'],
            ],
        ];

        $previousTenantId = null;
        if (DB::getDriverName() === 'pgsql') {
            $previousTenantId = DB::scalar("SELECT current_setting('app.current_tenant_id', true)");
        }

        $totalRolesSynced = 0;

        try {
            foreach ($tenants as $tenant) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('SELECT set_config(?, ?, false)', ['app.current_tenant_id', $tenant->id]);
                }

                foreach ($standardRoles as $rSlug => $rData) {
                    $role = Role::firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'slug' => $rSlug,
                        ],
                        [
                            'name' => $rData['name'],
                            'description' => $rData['desc'],
                            'is_system' => true,
                        ]
                    );

                    if ($role->permissions()->count() === 0) {
                        $permIdsToAttach = [];
                        foreach ($rData['perms'] as $pSlug) {
                            if (isset($allPermsMap[$pSlug])) {
                                $permIdsToAttach[] = $allPermsMap[$pSlug];
                            }
                        }
                        $role->permissions()->sync($permIdsToAttach);
                    }

                    $totalRolesSynced++;
                }
            }
        } finally {
            if (DB::getDriverName() === 'pgsql' && $previousTenantId !== null) {
                DB::statement('SELECT set_config(?, ?, false)', ['app.current_tenant_id', $previousTenantId]);
            }
        }

        return $totalRolesSynced;
    }
}
