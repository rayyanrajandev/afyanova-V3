<?php

namespace App\Domains\Billing\Policies;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;

class InvoicePolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->auth->hasPermission($user, 'billing.invoice.view', $invoice->facility_id);
    }

    public function create(User $user, ?string $facilityId = null): bool
    {
        return $this->auth->hasPermission($user, 'billing.invoice.create', $facilityId);
    }

    public function collectPayment(User $user, Invoice $invoice): bool
    {
        return $this->auth->hasPermission($user, 'billing.payment.collect', $invoice->facility_id);
    }

    public function approveDiscount(User $user, Invoice $invoice): bool
    {
        return $this->auth->hasPermission($user, 'billing.discount.approve', $invoice->facility_id);
    }

    public function refund(User $user, Invoice $invoice): bool
    {
        return $this->auth->hasPermission($user, 'billing.refund.issue', $invoice->facility_id);
    }

    public function addItem(User $user, Invoice $invoice): bool
    {
        return $this->auth->hasPermission($user, 'billing.invoice.create', $invoice->facility_id);
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $this->auth->hasPermission($user, 'billing.invoice.create', $invoice->facility_id);
    }

    public function adjust(User $user, Invoice $invoice): bool
    {
        return $this->auth->hasPermission($user, 'billing.discount.approve', $invoice->facility_id);
    }
}
