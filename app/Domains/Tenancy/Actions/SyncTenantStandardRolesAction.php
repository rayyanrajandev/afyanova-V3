<?php

namespace App\Domains\Tenancy\Actions;

use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

class SyncTenantStandardRolesAction
{
    public static array $masterPermissions = [
        // Clinical
        ['name' => 'Create Encounter', 'slug' => 'clinical.encounter.create', 'domain' => 'Clinical'],
        ['name' => 'View Encounter', 'slug' => 'clinical.encounter.view', 'domain' => 'Clinical'],
        ['name' => 'Update Encounter', 'slug' => 'clinical.encounter.update', 'domain' => 'Clinical'],
        ['name' => 'Close Encounter', 'slug' => 'clinical.encounter.close', 'domain' => 'Clinical'],
        ['name' => 'Sign Clinical Notes', 'slug' => 'clinical.notes.sign', 'domain' => 'Clinical'],
        ['name' => 'Record Vital Signs', 'slug' => 'clinical.vitals.record', 'domain' => 'Clinical'],
        ['name' => 'Manage Diagnoses', 'slug' => 'clinical.diagnosis.manage', 'domain' => 'Clinical'],
        ['name' => 'Record Informed Consent', 'slug' => 'clinical.consent.record', 'domain' => 'Clinical'],
        ['name' => 'Create Inter-Facility Referral', 'slug' => 'clinical.referral.create', 'domain' => 'Clinical'],
        ['name' => 'Administer Immunization', 'slug' => 'clinical.immunization.administer', 'domain' => 'Clinical'],
        ['name' => 'Record ANC Visit', 'slug' => 'clinical.anc.record', 'domain' => 'Clinical'],
        ['name' => 'Record Partograph Entry', 'slug' => 'clinical.partograph.record', 'domain' => 'Clinical'],
        ['name' => 'Manage Problem List', 'slug' => 'clinical.problem-list.manage', 'domain' => 'Clinical'],
        ['name' => 'Break-Glass Emergency Access', 'slug' => 'clinical.break_glass', 'domain' => 'Clinical'],
        ['name' => 'Override Another Provider\'s Encounter', 'slug' => 'clinical.encounter.override', 'domain' => 'Clinical'],
        ['name' => 'Add Clinical Note', 'slug' => 'clinical.notes.create', 'domain' => 'Clinical'],
        ['name' => 'Record Patient Allergy', 'slug' => 'clinical.allergy.record', 'domain' => 'Clinical'],
        ['name' => 'Verify/Amend Patient Allergy', 'slug' => 'clinical.allergy.verify', 'domain' => 'Clinical'],

        // Patient Registry
        ['name' => 'Register Patient', 'slug' => 'patient.registry.create', 'domain' => 'Patient'],
        ['name' => 'View Patient Registry', 'slug' => 'patient.registry.view', 'domain' => 'Patient'],

        // Pharmacy
        ['name' => 'Create Prescription', 'slug' => 'pharmacy.prescription.create', 'domain' => 'Pharmacy'],
        ['name' => 'View Prescription', 'slug' => 'pharmacy.prescription.view', 'domain' => 'Pharmacy'],
        ['name' => 'Verify Prescription', 'slug' => 'pharmacy.prescription.verify', 'domain' => 'Pharmacy'],
        ['name' => 'Dispense Medication', 'slug' => 'pharmacy.dispense.execute', 'domain' => 'Pharmacy'],
        ['name' => 'Record Medication Reconciliation', 'slug' => 'pharmacy.medication-reconciliation.record', 'domain' => 'Pharmacy'],
        ['name' => 'Receive Pharmacy Stock Batch', 'slug' => 'pharmacy.inventory.receive', 'domain' => 'Pharmacy'],
        ['name' => 'Adjust Pharmacy Stock Batch', 'slug' => 'pharmacy.inventory.adjust', 'domain' => 'Pharmacy'],
        ['name' => 'View Pharmacy Inventory', 'slug' => 'pharmacy.inventory.view', 'domain' => 'Pharmacy'],

        // Inventory & Warehousing
        ['name' => 'View Inventory Locations', 'slug' => 'inventory.location.view', 'domain' => 'Inventory'],
        ['name' => 'Dispatch Stock Transfer', 'slug' => 'inventory.transfer.dispatch', 'domain' => 'Inventory'],
        ['name' => 'Confirm Stock Transfer', 'slug' => 'inventory.transfer.confirm', 'domain' => 'Inventory'],
        ['name' => 'Create Purchase Order', 'slug' => 'inventory.po.create', 'domain' => 'Inventory'],
        ['name' => 'Approve Purchase Order', 'slug' => 'inventory.po.approve', 'domain' => 'Inventory'],
        ['name' => 'Receive Goods Note (GRN)', 'slug' => 'inventory.grn.receive', 'domain' => 'Inventory'],
        ['name' => 'Initiate Stocktaking', 'slug' => 'inventory.stocktake.create', 'domain' => 'Inventory'],
        ['name' => 'Approve Stocktake Reconciliation', 'slug' => 'inventory.stocktake.approve', 'domain' => 'Inventory'],
        ['name' => 'Record DDA Narcotic Administration', 'slug' => 'inventory.dda.record', 'domain' => 'Inventory'],
        ['name' => 'View Stock Balances', 'slug' => 'inventory.stock.view', 'domain' => 'Inventory'],
        ['name' => 'View Item Catalog', 'slug' => 'inventory.catalog.view', 'domain' => 'Inventory'],
        ['name' => 'View Department Requisitions', 'slug' => 'inventory.requisition.view', 'domain' => 'Inventory'],
        ['name' => 'View Stock Transfers', 'slug' => 'inventory.transfer.view', 'domain' => 'Inventory'],
        ['name' => 'View Purchase Orders', 'slug' => 'inventory.po.view', 'domain' => 'Inventory'],
        ['name' => 'View Predictive Reorders', 'slug' => 'inventory.predictive.view', 'domain' => 'Inventory'],
        ['name' => 'View Goods Receipt Notes', 'slug' => 'inventory.grn.view', 'domain' => 'Inventory'],
        ['name' => 'View DDA Register', 'slug' => 'inventory.dda.view', 'domain' => 'Inventory'],
        ['name' => 'View Medical Gas Cylinders', 'slug' => 'inventory.gas.view', 'domain' => 'Inventory'],
        ['name' => 'View Stocktake Sessions', 'slug' => 'inventory.stocktake.view', 'domain' => 'Inventory'],
        ['name' => 'Manage Item Catalog', 'slug' => 'inventory.catalog.manage', 'domain' => 'Inventory'],
        ['name' => 'Submit Department Requisition', 'slug' => 'inventory.requisition.create', 'domain' => 'Inventory'],
        ['name' => 'Approve Department Requisition', 'slug' => 'inventory.requisition.approve', 'domain' => 'Inventory'],
        ['name' => 'Issue/Dispatch Department Requisition', 'slug' => 'inventory.requisition.issue', 'domain' => 'Inventory'],
        ['name' => 'Confirm Department Requisition Receipt', 'slug' => 'inventory.requisition.confirm', 'domain' => 'Inventory'],
        ['name' => 'Generate Predictive Reorder Purchase Orders', 'slug' => 'inventory.predictive.generate', 'domain' => 'Inventory'],

        // Billing & Financial
        ['name' => 'Create Invoice', 'slug' => 'billing.invoice.create', 'domain' => 'Billing'],
        ['name' => 'View Invoice', 'slug' => 'billing.invoice.view', 'domain' => 'Billing'],
        ['name' => 'Collect Payment', 'slug' => 'billing.payment.collect', 'domain' => 'Billing'],
        ['name' => 'Approve Discount', 'slug' => 'billing.discount.approve', 'domain' => 'Billing'],
        ['name' => 'Reconcile Till Shift', 'slug' => 'billing.shift.reconcile', 'domain' => 'Billing'],
        ['name' => 'Open Cashier Shift', 'slug' => 'billing.shift.open', 'domain' => 'Billing'],
        ['name' => 'Close Cashier Shift', 'slug' => 'billing.shift.close', 'domain' => 'Billing'],
        ['name' => 'Issue Refund', 'slug' => 'billing.refund.issue', 'domain' => 'Billing'],

        // Insurance
        ['name' => 'Create Insurance Claim', 'slug' => 'insurance.claim.create', 'domain' => 'Insurance'],
        ['name' => 'Submit Claims Batch', 'slug' => 'insurance.claim.submit', 'domain' => 'Insurance'],
        ['name' => 'Adjudicate Claim', 'slug' => 'insurance.claim.vet', 'domain' => 'Insurance'],
        ['name' => 'Adjudicate Remittance', 'slug' => 'insurance.claim.adjudicate', 'domain' => 'Insurance'],
        ['name' => 'Manage Insurance Tariffs', 'slug' => 'insurance.tariff.manage', 'domain' => 'Insurance'],
        ['name' => 'View Insurance Claims', 'slug' => 'insurance.claim.view', 'domain' => 'Insurance'],
        ['name' => 'Verify Policy Eligibility', 'slug' => 'insurance.policy.verify', 'domain' => 'Insurance'],
        ['name' => 'Request Pre-Authorization', 'slug' => 'insurance.preauth.create', 'domain' => 'Insurance'],

        // Laboratory
        ['name' => 'Order Lab Test', 'slug' => 'lab.order.create', 'domain' => 'Laboratory'],
        ['name' => 'Collect Specimen', 'slug' => 'lab.specimen.collect', 'domain' => 'Laboratory'],
        ['name' => 'Enter Lab Results', 'slug' => 'lab.result.record', 'domain' => 'Laboratory'],
        ['name' => 'Verify Lab Report', 'slug' => 'lab.result.verify', 'domain' => 'Laboratory'],
        ['name' => 'View Lab Orders & Results', 'slug' => 'lab.order.view', 'domain' => 'Laboratory'],
        ['name' => 'Manage Lab Test Catalog', 'slug' => 'lab.catalog.manage', 'domain' => 'Laboratory'],

        // Radiology
        ['name' => 'Order Diagnostic Imaging', 'slug' => 'radiology.order.create', 'domain' => 'Radiology'],
        ['name' => 'View Radiology Order', 'slug' => 'radiology.order.view', 'domain' => 'Radiology'],
        ['name' => 'Acquire Imaging Study', 'slug' => 'radiology.study.acquire', 'domain' => 'Radiology'],
        ['name' => 'Sign Radiology Report', 'slug' => 'radiology.report.sign', 'domain' => 'Radiology'],
        ['name' => 'Amend Radiology Report', 'slug' => 'radiology.report.amend', 'domain' => 'Radiology'],

        // Procedures & Surgery
        ['name' => 'Order Procedure', 'slug' => 'procedure.order.create', 'domain' => 'Procedure'],
        ['name' => 'Execute Minor Procedure', 'slug' => 'procedure.execute.dressing', 'domain' => 'Procedure'],
        ['name' => 'Book Operating Theatre', 'slug' => 'procedure.theatre.book', 'domain' => 'Procedure'],
        ['name' => 'Sign WHO Checklist', 'slug' => 'procedure.theatre.checklist', 'domain' => 'Procedure'],
        ['name' => 'Record PACU Recovery', 'slug' => 'procedure.theatre.pacu', 'domain' => 'Procedure'],
        ['name' => 'Execute Ordered Procedure', 'slug' => 'procedure.order.execute', 'domain' => 'Procedure'],
        ['name' => 'View Procedure Orders', 'slug' => 'procedure.order.view', 'domain' => 'Procedure'],

        // Inpatient
        ['name' => 'Admit Inpatient', 'slug' => 'inpatient.admission.create', 'domain' => 'Inpatient'],
        ['name' => 'Discharge Inpatient', 'slug' => 'inpatient.admission.discharge', 'domain' => 'Inpatient'],
        ['name' => 'Transfer Inpatient Admission', 'slug' => 'inpatient.admission.transfer', 'domain' => 'Inpatient'],
        ['name' => 'Transfer Inpatient Bed', 'slug' => 'inpatient.bed.transfer', 'domain' => 'Inpatient'],
        ['name' => 'Manage Bed Status', 'slug' => 'inpatient.bed.manage', 'domain' => 'Inpatient'],
        ['name' => 'Administer MAR Medication', 'slug' => 'inpatient.mar.administer', 'domain' => 'Inpatient'],
        ['name' => 'View Ward & Bed Status', 'slug' => 'inpatient.ward.view', 'domain' => 'Inpatient'],

        // Scheduling
        ['name' => 'Create Appointment', 'slug' => 'scheduling.appointment.create', 'domain' => 'Scheduling'],
        ['name' => 'Check In Appointment', 'slug' => 'scheduling.appointment.checkin', 'domain' => 'Scheduling'],
        ['name' => 'Call Queue Ticket', 'slug' => 'scheduling.queue.call', 'domain' => 'Scheduling'],
        ['name' => 'Transfer Queue Ticket', 'slug' => 'scheduling.queue.transfer', 'domain' => 'Scheduling'],
        ['name' => 'View Live Queue', 'slug' => 'scheduling.queue.view', 'domain' => 'Scheduling'],
        ['name' => 'View Appointments', 'slug' => 'scheduling.appointment.view', 'domain' => 'Scheduling'],

        // Reports & BI
        ['name' => 'View Clinical Analytics', 'slug' => 'reports.clinical.view', 'domain' => 'Reports'],
        ['name' => 'View Financial Intelligence', 'slug' => 'reports.financial.view', 'domain' => 'Reports'],
        ['name' => 'View Pharmacoeconomics', 'slug' => 'reports.pharmacoeconomic.view', 'domain' => 'Reports'],
        ['name' => 'View Analytics & Reports', 'slug' => 'reports.analytics.view', 'domain' => 'Reports'],

        // Identity & Audit
        ['name' => 'Manage Staff Accounts', 'slug' => 'identity.user.manage', 'domain' => 'Identity'],
        ['name' => 'Manage Roles & Permissions', 'slug' => 'identity.role.manage', 'domain' => 'Identity'],
        ['name' => 'Assign User Roles', 'slug' => 'identity.roles.assign', 'domain' => 'Identity'],
        ['name' => 'Manage Role Permissions', 'slug' => 'identity.permissions.manage', 'domain' => 'Identity'],
        ['name' => 'View Security Audit Trail', 'slug' => 'audit.log.view', 'domain' => 'Audit'],
        ['name' => 'Superadmin Platform Access', 'slug' => 'platform.superadmin.access', 'domain' => 'Platform'],
    ];

    public static function ensureMasterPermissions(): void
    {
        if (Permission::count() < count(self::$masterPermissions)) {
            foreach (self::$masterPermissions as $p) {
                Permission::firstOrCreate(
                    ['slug' => $p['slug']],
                    ['name' => $p['name'], 'domain' => $p['domain']]
                );
            }
        }
    }

    public function execute(?string $targetTenantId = null): int
    {
        self::ensureMasterPermissions();

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
