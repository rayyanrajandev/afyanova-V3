<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

use App\Http\Controllers\DashboardController;

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

use App\Domains\Clinical\Http\Controllers\EncounterController;

Route::get('/workspace/clinical', [EncounterController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('workspace.clinical');

Route::get('/workspace/foundation', function () {
    return Inertia::render('Workspace/DesignFoundationDemo');
})->middleware(['auth', 'verified'])->name('workspace.foundation');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

use App\Domains\Patient\Http\Controllers\PatientController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
    Route::get('/patients/create', [PatientController::class, 'create'])->name('patients.create');
    Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
});

use App\Domains\Clinical\Http\Controllers\ClinicalCareExtensionsController;
use App\Domains\Clinical\Http\Controllers\ClinicalChartingController;
use App\Domains\Clinical\Http\Controllers\ProblemListController;
use App\Http\Controllers\Clinical\BreakGlassController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/encounters', [EncounterController::class, 'store'])->name('encounters.store');
    Route::get('/encounters/{encounter}/workspace', [EncounterController::class, 'workspace'])->name('encounters.workspace');
    Route::post('/encounters/{encounter}/complete', [EncounterController::class, 'complete'])->name('encounters.complete');

    Route::post('/encounters/{encounter}/vitals', [ClinicalChartingController::class, 'storeVitals'])->name('clinical.vitals.store');
    Route::post('/encounters/{encounter}/notes', [ClinicalChartingController::class, 'storeNote'])->name('clinical.notes.store');
    Route::post('/clinical-notes/{note}/sign', [ClinicalChartingController::class, 'signNote'])->name('clinical.notes.sign');
    Route::post('/clinical-notes/{note}/amend', [ClinicalChartingController::class, 'amendNote'])->name('clinical.notes.amend');

    Route::post('/break-glass', [BreakGlassController::class, 'store'])->name('clinical.break-glass.store');
    Route::delete('/break-glass', [BreakGlassController::class, 'destroy'])->name('clinical.break-glass.destroy');

    Route::post('/encounters/{encounter}/consent', [ClinicalCareExtensionsController::class, 'storeConsent'])->name('clinical.consent.store');
    Route::post('/encounters/{encounter}/referral', [ClinicalCareExtensionsController::class, 'storeReferral'])->name('clinical.referral.store');
    Route::post('/encounters/{encounter}/immunization', [ClinicalCareExtensionsController::class, 'storeImmunization'])->name('clinical.immunization.store');
    Route::post('/encounters/{encounter}/anc-visit', [ClinicalCareExtensionsController::class, 'storeAncVisit'])->name('clinical.anc.store');
    Route::post('/encounters/{encounter}/partograph', [ClinicalCareExtensionsController::class, 'storePartograph'])->name('clinical.partograph.store');

    Route::post('/patients/{patient}/problems', [ProblemListController::class, 'store'])->name('clinical.problems.store');
    Route::post('/problems/{problem}/resolve', [ProblemListController::class, 'resolve'])->name('clinical.problems.resolve');
});

use App\Domains\Scheduling\Http\Controllers\AppointmentController;
use App\Domains\Scheduling\Http\Controllers\QueueController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::post('/appointments/{appointment}/check-in', [AppointmentController::class, 'checkIn'])->name('appointments.check-in');

    Route::get('/queue', [QueueController::class, 'index'])->name('queue.index');
    Route::post('/queue/{ticket}/call', [QueueController::class, 'call'])->name('queue.call');
    Route::post('/queue/{ticket}/transfer', [QueueController::class, 'transfer'])->name('queue.transfer');
});

use App\Domains\Pharmacy\Http\Controllers\DispensingController;
use App\Domains\Pharmacy\Http\Controllers\InventoryBatchController;
use App\Domains\Pharmacy\Http\Controllers\MedicationReconciliationController;
use App\Domains\Pharmacy\Http\Controllers\PrescriptionController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/encounters/{encounter}/prescriptions', [PrescriptionController::class, 'store'])->name('prescriptions.store');

    Route::get('/pharmacy', [DispensingController::class, 'index'])->name('pharmacy.queue');
    Route::post('/pharmacy/{prescription}/verify', [DispensingController::class, 'verify'])->name('pharmacy.verify');
    Route::post('/pharmacy/{prescription}/dispense', [DispensingController::class, 'dispense'])->name('pharmacy.dispense');

    Route::get('/pharmacy/inventory', [InventoryBatchController::class, 'index'])->name('pharmacy.inventory');
    Route::post('/pharmacy/batches', [InventoryBatchController::class, 'store'])->name('pharmacy.batches.store');
    Route::post('/pharmacy/batches/{batch}/adjust', [InventoryBatchController::class, 'adjust'])->name('pharmacy.batches.adjust');

    Route::post('/patients/{patient}/medication-reconciliation', [MedicationReconciliationController::class, 'store'])->name('pharmacy.reconciliation.store');
});

use App\Domains\Billing\Http\Controllers\BillingController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/billing/desk', [BillingController::class, 'index'])->name('billing.desk');
    Route::post('/encounters/{encounter}/invoice', [BillingController::class, 'generate'])->name('billing.generate');
    Route::post('/billing/invoices/{invoice}/pay', [BillingController::class, 'pay'])->name('billing.pay');
    Route::post('/billing/invoices/{invoice}/refund', [BillingController::class, 'refund'])->name('billing.refund');
    Route::post('/billing/invoices/{invoice}/items', [BillingController::class, 'addItem'])->name('billing.items.store');
    Route::post('/billing/invoices/{invoice}/issue', [BillingController::class, 'issue'])->name('billing.invoices.issue');
    Route::post('/billing/invoices/{invoice}/adjust', [BillingController::class, 'adjustInvoice'])->name('billing.invoices.adjust');
    Route::post('/billing/shifts/open', [BillingController::class, 'openShift'])->name('billing.shifts.open');
    Route::post('/billing/shifts/{shift}/close', [BillingController::class, 'closeShift'])->name('billing.shifts.close');
});

use App\Domains\Clinical\Http\Controllers\LabOrderController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/encounters/{encounter}/lab-orders', [LabOrderController::class, 'store'])->name('lab-orders.store');
});

use App\Domains\Inpatient\Http\Controllers\InpatientWorkspaceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/inpatient', [InpatientWorkspaceController::class, 'index'])->name('inpatient.workspace');
    Route::post('/inpatient/admissions', [InpatientWorkspaceController::class, 'admit'])->name('inpatient.admissions.store');
    Route::post('/inpatient/admissions/{admission}/transfer', [InpatientWorkspaceController::class, 'transfer'])->name('inpatient.admissions.transfer');
    Route::post('/inpatient/admissions/{admission}/discharge', [InpatientWorkspaceController::class, 'discharge'])->name('inpatient.admissions.discharge');
    Route::post('/inpatient/admissions/{admission}/mar', [InpatientWorkspaceController::class, 'administerMar'])->name('inpatient.admissions.mar.store');
    Route::post('/inpatient/beds/{bed}/status', [InpatientWorkspaceController::class, 'updateBedStatus'])->name('inpatient.beds.status');
});

use App\Domains\Laboratory\Http\Controllers\LaboratoryWorkspaceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/laboratory', [LaboratoryWorkspaceController::class, 'index'])->name('laboratory.workspace');
    Route::post('/laboratory/items/{item}/collect', [LaboratoryWorkspaceController::class, 'collectSample'])->name('laboratory.items.collect');
    Route::post('/laboratory/items/{item}/results', [LaboratoryWorkspaceController::class, 'saveResults'])->name('laboratory.items.results');
    Route::post('/laboratory/items/{item}/verify', [LaboratoryWorkspaceController::class, 'verifyResults'])->name('laboratory.items.verify');
    Route::post('/laboratory/tests', [LaboratoryWorkspaceController::class, 'storeTest'])->name('laboratory.tests.store');
});

use App\Domains\Radiology\Http\Controllers\RadiologyWorkspaceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/encounters/{encounter}/radiology-orders', [RadiologyWorkspaceController::class, 'order'])->name('radiology.orders.store');
    Route::post('/radiology/orders/{order}/report', [RadiologyWorkspaceController::class, 'signReport'])->name('radiology.report.sign');
    Route::post('/radiology/reports/{report}/amend', [RadiologyWorkspaceController::class, 'amendReport'])->name('radiology.report.amend');
});

use App\Domains\Insurance\Http\Controllers\InsuranceWorkspaceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/insurance', [InsuranceWorkspaceController::class, 'index'])->name('insurance.workspace');
    Route::post('/insurance/claims/generate', [InsuranceWorkspaceController::class, 'generateClaim'])->name('insurance.claims.generate');
    Route::post('/insurance/claims/batch-submit', [InsuranceWorkspaceController::class, 'batchSubmit'])->name('insurance.claims.batch-submit');
    Route::post('/insurance/claims/{claim}/adjudicate', [InsuranceWorkspaceController::class, 'adjudicate'])->name('insurance.claims.adjudicate');
    Route::post('/insurance/policies/{policy}/verify', [InsuranceWorkspaceController::class, 'verifyPolicy'])->name('insurance.policies.verify');
    Route::post('/insurance/pre-auth', [InsuranceWorkspaceController::class, 'storePreAuth'])->name('insurance.pre-auth.store');
});

use App\Domains\Procedure\Http\Controllers\ProcedureWorkspaceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/procedures', [ProcedureWorkspaceController::class, 'index'])->name('procedures.workspace');
    Route::post('/procedures/orders', [ProcedureWorkspaceController::class, 'orderProcedure'])->name('procedures.orders.store');
    Route::post('/procedures/orders/{order}/execute', [ProcedureWorkspaceController::class, 'executeProcedure'])->name('procedures.orders.execute');
    Route::post('/procedures/orders/{order}/book-surgery', [ProcedureWorkspaceController::class, 'bookSurgery'])->name('procedures.orders.book-surgery');
    Route::post('/procedures/who-checklists/{checklist}', [ProcedureWorkspaceController::class, 'saveWhoChecklist'])->name('procedures.who-checklists.save');
    Route::post('/procedures/bookings/{booking}/pacu', [ProcedureWorkspaceController::class, 'savePacuScore'])->name('procedures.bookings.pacu');
});

use App\Domains\Reports\Http\Controllers\ReportsWorkspaceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/reports', [ReportsWorkspaceController::class, 'index'])->name('reports.workspace');
});

use App\Domains\Inventory\Http\Controllers\InventoryWorkspaceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/inventory', [InventoryWorkspaceController::class, 'index'])->name('inventory.workspace');
    Route::post('/inventory/items', [InventoryWorkspaceController::class, 'storeItem'])->name('inventory.items.store');
    Route::post('/inventory/requisitions', [InventoryWorkspaceController::class, 'storeRequisition'])->name('inventory.requisitions.store');
    Route::post('/inventory/requisitions/{id}/approve', [InventoryWorkspaceController::class, 'approveRequisition'])->name('inventory.requisitions.approve');
    Route::post('/inventory/requisitions/{id}/issue', [InventoryWorkspaceController::class, 'issueRequisition'])->name('inventory.requisitions.issue');
    Route::post('/inventory/requisitions/{id}/confirm', [InventoryWorkspaceController::class, 'confirmRequisition'])->name('inventory.requisitions.confirm');
    Route::post('/inventory/dda-logs', [InventoryWorkspaceController::class, 'storeDdaLog'])->name('inventory.dda-logs.store');
    Route::post('/inventory/transfers', [InventoryWorkspaceController::class, 'storeTransfer'])->name('inventory.transfers.store');
    Route::post('/inventory/transfers/{id}/confirm', [InventoryWorkspaceController::class, 'confirmTransfer'])->name('inventory.transfers.confirm');
    Route::post('/inventory/purchase-orders', [InventoryWorkspaceController::class, 'storePurchaseOrder'])->name('inventory.purchase-orders.store');
    Route::post('/inventory/purchase-orders/{id}/approve', [InventoryWorkspaceController::class, 'approvePurchaseOrder'])->name('inventory.purchase-orders.approve');
    Route::get('/inventory/catalog/search', [InventoryWorkspaceController::class, 'searchCatalog'])->name('inventory.catalog.search');
    Route::post('/inventory/goods-receipt', [InventoryWorkspaceController::class, 'storeGoodsReceipt'])->name('inventory.goods-receipt.store');
    Route::post('/inventory/stocktake', [InventoryWorkspaceController::class, 'storeStocktake'])->name('inventory.stocktake.store');
    Route::post('/inventory/procurement/predictive-reorder', [InventoryWorkspaceController::class, 'generatePredictiveReorder'])->name('inventory.predictive-reorder');
});

use App\Domains\Identity\Http\Controllers\AccessControlWorkspaceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/access-control', [AccessControlWorkspaceController::class, 'index'])->name('access-control.workspace');
    Route::post('/access-control/roles/assign', [AccessControlWorkspaceController::class, 'assignRole'])->name('access-control.roles.assign');
    Route::post('/access-control/roles/{role}/permissions', [AccessControlWorkspaceController::class, 'updatePermissions'])->name('access-control.roles.permissions');
});
