<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('patients:clean', function () {
    $this->info('Cleaning all patient records and associated clinical, billing, and queue data...');

    $tables = [
        'who_surgical_checklists',
        'pacu_recovery_records',
        'surgical_bookings',
        'procedure_consumables_used',
        'procedure_executions',
        'procedure_orders',
        'radiology_reports',
        'radiology_studies',
        'radiology_orders',
        'lab_specimens',
        'lab_order_items',
        'lab_orders',
        'medication_administration_records',
        'bed_transfers',
        'admissions',
        'dispense_event_batches',
        'dispense_events',
        'medication_reconciliations',
        'prescriptions',
        'claim_remittance_items',
        'claim_remittances',
        'insurance_claim_items',
        'insurance_claims',
        'pre_authorizations',
        'patient_policies',
        'invoice_adjustment_notes',
        'invoice_line_items',
        'patient_deposit_allocations',
        'patient_deposits',
        'payments',
        'invoices',
        'queue_tickets',
        'appointments',
        'partograph_entries',
        'anc_encounters',
        'patient_immunizations',
        'clinical_referrals',
        'clinical_consents',
        'allergies',
        'patient_problems',
        'diagnoses',
        'clinical_notes',
        'clinical_vitals',
        'encounters',
        'patient_identifiers',
        'patient_contacts',
        'emergency_contacts',
        'patient_relationships',
        'patients',
    ];

    if (DB::getDriverName() === 'pgsql') {
        DB::statement('TRUNCATE TABLE '.implode(', ', $tables).' CASCADE;');
    } else {
        DB::statement('PRAGMA foreign_keys = OFF;');
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('PRAGMA foreign_keys = ON;');
    }

    // Reset beds to Available state
    DB::table('beds')->update(['status' => 'Available']);

    $this->info('✓ All patient records and associated clinical/billing records wiped successfully.');
    $this->info('✓ All inpatient beds reset to Available.');
    $this->info('✓ Staff logins, roles, lab tests, formularies, and catalogs remain ready.');
})->purpose('Clean all patient records and associated clinical, billing, and queue transactions');

Artisan::command('afyanova:clean-patients', function () {
    $this->call('patients:clean');
})->purpose('Alias for patients:clean — wipe patient and transactional records');

Schedule::command('backup:run')->daily()->at('02:00')->onOneServer();
Schedule::command('backup:clean')->daily()->at('01:30')->onOneServer();
Schedule::command('inpatient:generate-daily-bed-charges')->dailyAt('23:59')->onOneServer();

