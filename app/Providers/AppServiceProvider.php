<?php

namespace App\Providers;

use App\Core\Context\BreakGlassContext;
use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Audit\Listeners\EstablishTenantContextOnLogin;
use App\Domains\Audit\Listeners\LogFailedLogin;
use App\Domains\Audit\Listeners\LogSuccessfulLogin;
use App\Domains\Audit\Listeners\LogSuccessfulLogout;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Policies\InvoicePolicy;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Clinical\Policies\EncounterPolicy;
use App\Domains\Clinical\Policies\LabOrderItemPolicy;
use App\Domains\Inpatient\Models\Admission;
use App\Domains\Inpatient\Models\Bed;
use App\Domains\Inpatient\Policies\AdmissionPolicy;
use App\Domains\Inpatient\Policies\BedPolicy;
use App\Domains\Insurance\Models\InsuranceClaim;
use App\Domains\Insurance\Policies\InsuranceClaimPolicy;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Policies\InventoryPolicy;
use App\Domains\Patient\Models\Patient;
use App\Domains\Patient\Policies\PatientPolicy;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\Prescription;
use App\Domains\Pharmacy\Policies\InventoryBatchPolicy;
use App\Domains\Pharmacy\Policies\PrescriptionPolicy;
use App\Domains\Procedure\Models\ProcedureOrder;
use App\Domains\Procedure\Models\SurgicalBooking;
use App\Domains\Procedure\Models\WhoSurgicalChecklist;
use App\Domains\Procedure\Policies\ProcedureOrderPolicy;
use App\Domains\Procedure\Policies\SurgicalBookingPolicy;
use App\Domains\Radiology\Models\RadiologyOrder;
use App\Domains\Radiology\Policies\RadiologyPolicy;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use App\Domains\Scheduling\Policies\AppointmentPolicy;
use App\Domains\Scheduling\Policies\QueueTicketPolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Explicit model => policy map. Several policies here (InventoryPolicy
     * chief among them) don't follow Laravel's `{Model}Policy` naming
     * convention closely enough to auto-discover reliably, so every mapping
     * is registered explicitly rather than leaving some to guesswork.
     */
    protected array $policies = [
        Invoice::class => InvoicePolicy::class,
        Encounter::class => EncounterPolicy::class,
        Prescription::class => PrescriptionPolicy::class,
        InsuranceClaim::class => InsuranceClaimPolicy::class,
        InventoryLocation::class => InventoryPolicy::class,
        Patient::class => PatientPolicy::class,
        LabOrderItem::class => LabOrderItemPolicy::class,
        Admission::class => AdmissionPolicy::class,
        Bed::class => BedPolicy::class,
        InventoryBatch::class => InventoryBatchPolicy::class,
        ProcedureOrder::class => ProcedureOrderPolicy::class,
        SurgicalBooking::class => SurgicalBookingPolicy::class,
        WhoSurgicalChecklist::class => SurgicalBookingPolicy::class,
        Appointment::class => AppointmentPolicy::class,
        QueueTicket::class => QueueTicketPolicy::class,
        RadiologyOrder::class => RadiologyPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext;
        });

        $this->app->singleton(FacilityContext::class, function () {
            return new FacilityContext;
        });

        $this->app->singleton(BreakGlassContext::class, function () {
            return new BreakGlassContext;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Order matters: tenant context must be established before the
        // audit write runs, or that write itself has no tenant context.
        Event::listen(Login::class, EstablishTenantContextOnLogin::class);
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
        Event::listen(Failed::class, LogFailedLogin::class);

        Vite::prefetch(concurrency: 3);

        $domainPaths = [
            'Tenancy', 'Identity', 'Audit', 'Patient',
            'Clinical', 'Scheduling', 'Pharmacy', 'Billing',
            'Inpatient', 'Laboratory', 'Insurance', 'Procedure',
            'Reports', 'Inventory', 'Radiology',
        ];

        foreach ($domainPaths as $domain) {
            $path = app_path("Domains/{$domain}/Database/Migrations");
            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }
}
