<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Exceptions\ChargeNotFoundException;
use App\Domains\Billing\Models\ChargeMasterItem;
use Carbon\Carbon;

/**
 * Single source of truth for what something costs. Replaces the hardcoded
 * unit prices previously scattered across Billing/Pharmacy Actions with an
 * effective-dated lookup, so a historical invoice keeps the price that was
 * actually in force on the day of service even after the catalog changes.
 */
class ChargePriceResolver
{
    public function priceFor(string $code, ?Carbon $at = null): float
    {
        $at ??= Carbon::today();

        $item = ChargeMasterItem::where('code', $code)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $at)
            ->where(function ($query) use ($at) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $at);
            })
            ->orderByDesc('effective_from')
            ->first();

        if (! $item) {
            throw ChargeNotFoundException::forCode($code);
        }

        return (float) $item->unit_price;
    }
}
