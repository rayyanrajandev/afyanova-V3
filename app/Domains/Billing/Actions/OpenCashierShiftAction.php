<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\CashierShift;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Facility;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenCashierShiftAction
{
    public function execute(float $openingFloat = 0.00, ?string $notes = null, ?User $user = null): CashierShift
    {
        $cashier = $user ?? auth()->user() ?? User::first();
        if (! $cashier) {
            throw ValidationException::withMessages(['shift' => 'No active user identified to open shift.']);
        }

        // Check if user already has an active open shift
        $activeShift = CashierShift::where('user_id', $cashier->id)
            ->where('status', 'Open')
            ->first();

        if ($activeShift) {
            throw ValidationException::withMessages(['shift' => "You already have an active open shift (#{$activeShift->shift_number})."]);
        }

        return DB::transaction(function () use ($cashier, $openingFloat, $notes) {
            $facility = Facility::first();
            $tenantId = $cashier->tenant_id ?? $facility?->tenant_id;
            $facilityId = $cashier->facility_id ?? $facility?->id;

            $today = now()->format('Ymd');
            $shiftCount = CashierShift::whereDate('created_at', now()->toDateString())->count() + 1;
            $shiftNumber = sprintf('SHIFT-%s-%03d', $today, $shiftCount);

            return CashierShift::create([
                'tenant_id' => $tenantId,
                'facility_id' => $facilityId,
                'user_id' => $cashier->id,
                'shift_number' => $shiftNumber,
                'status' => 'Open',
                'opened_at' => now(),
                'opening_float' => $openingFloat,
                'expected_cash_total' => $openingFloat,
                'notes' => $notes,
            ]);
        });
    }
}
