<?php

use App\Domains\Billing\Http\Controllers\MpesaPaymentController;
use App\Domains\Interoperability\Fhir\Controllers\FhirResourceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (Interoperability, Webhooks, Integrations)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // M-Pesa Daraja / Vodacom Webhooks
    Route::prefix('payments/mpesa')->group(function () {
        Route::post('/callback', [MpesaPaymentController::class, 'handleCallback'])->name('api.mpesa.callback');
        Route::post('/validation', [MpesaPaymentController::class, 'handleC2bValidation'])->name('api.mpesa.validation');
        Route::post('/confirmation', [MpesaPaymentController::class, 'handleC2bConfirmation'])->name('api.mpesa.confirmation');
    });

    // HL7 FHIR R4 Interoperability Endpoints
    Route::prefix('fhir/r4')->group(function () {
        Route::get('/Patient', [FhirResourceController::class, 'searchPatients'])->name('api.fhir.patients.search');
        Route::get('/Patient/{id}', [FhirResourceController::class, 'getPatient'])->name('api.fhir.patients.get');
        Route::get('/Patient/{id}/$everything', [FhirResourceController::class, 'getPatientEverything'])->name('api.fhir.patients.everything');
        Route::get('/Encounter/{id}', [FhirResourceController::class, 'getEncounter'])->name('api.fhir.encounters.get');
    });
});
