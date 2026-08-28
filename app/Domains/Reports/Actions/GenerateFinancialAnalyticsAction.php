<?php

namespace App\Domains\Reports\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceLineItem;
use App\Domains\Insurance\Models\InsuranceClaim;
use Illuminate\Support\Carbon;

class GenerateFinancialAnalyticsAction
{
    public function execute(?string $tenantId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        // No Tenant::first() fallback: silently generating this report
        // against an arbitrary other tenant when neither the caller nor
        // the acting user supplies one is a landmine, not a safety net.
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;

        $invoiceQuery = Invoice::with(['lineItems', 'patient', 'encounter'])
            ->where('tenant_id', $tenantId);

        $lineItemQuery = InvoiceLineItem::where('tenant_id', $tenantId);
        $claimQuery = InsuranceClaim::with('policy.provider')->where('tenant_id', $tenantId);

        if ($startDate) {
            $invoiceQuery->whereDate('created_at', '>=', Carbon::parse($startDate));
            $lineItemQuery->whereDate('created_at', '>=', Carbon::parse($startDate));
            $claimQuery->whereDate('created_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $invoiceQuery->whereDate('created_at', '<=', Carbon::parse($endDate));
            $lineItemQuery->whereDate('created_at', '<=', Carbon::parse($endDate));
            $claimQuery->whereDate('created_at', '<=', Carbon::parse($endDate));
        }

        $invoices = $invoiceQuery->get();
        $lineItems = $lineItemQuery->get();
        $claims = $claimQuery->get();

        $totalBilled = (float) $invoices->sum('total_amount');
        $totalCollected = (float) $invoices->sum('paid_amount');
        $totalOutstanding = max(0, $totalBilled - $totalCollected);
        $collectionRate = $totalBilled > 0 ? round(($totalCollected / $totalBilled) * 100, 1) : 100;

        // 1. Departmental Revenue Breakdown (Cost Centers)
        $costCenterGroups = [
            'Consultation' => 0.0,
            'Pharmacy' => 0.0,
            'Laboratory' => 0.0,
            'Procedures' => 0.0,
            'Inpatient' => 0.0,
            'Other' => 0.0,
        ];

        foreach ($lineItems as $item) {
            $cat = strtolower($item->category ?? '');
            $amount = (float) $item->total_price;

            if (str_contains($cat, 'consult')) {
                $costCenterGroups['Consultation'] += $amount;
            } elseif (str_contains($cat, 'pharm') || str_contains($cat, 'drug') || str_contains($cat, 'med')) {
                $costCenterGroups['Pharmacy'] += $amount;
            } elseif (str_contains($cat, 'lab')) {
                $costCenterGroups['Laboratory'] += $amount;
            } elseif (str_contains($cat, 'proc') || str_contains($cat, 'surg') || str_contains($cat, 'dress')) {
                $costCenterGroups['Procedures'] += $amount;
            } elseif (str_contains($cat, 'inpatient') || str_contains($cat, 'bed') || str_contains($cat, 'ward')) {
                $costCenterGroups['Inpatient'] += $amount;
            } else {
                $costCenterGroups['Other'] += $amount;
            }
        }

        $totalLineItemRev = array_sum($costCenterGroups);
        $departmentalRevenue = [];
        foreach ($costCenterGroups as $department => $amount) {
            $pct = $totalLineItemRev > 0 ? round(($amount / $totalLineItemRev) * 100, 1) : 0;
            $departmentalRevenue[] = [
                'department' => $department,
                'revenue_tzs' => $amount,
                'percentage' => $pct,
            ];
        }

        // 2. Payer Mix Breakdown (Cash vs. Insurance)
        $insuranceClaimedTotal = (float) $claims->sum(fn ($c) => $c->total_claimed_amount ?? $c->claimed_amount ?? 0);
        $insuranceApprovedTotal = (float) $claims->sum('approved_amount');
        $cashDirectTotal = max(0, $totalCollected);

        $payerGroups = $claims->groupBy(function ($c) {
            return $c->policy?->provider?->name ?: ($c->payer_name ?: 'National Health Insurance (NHIF)');
        });
        $insurancePayers = [];

        foreach ($payerGroups as $payerName => $pClaims) {
            $claimed = (float) $pClaims->sum(fn ($c) => $c->total_claimed_amount ?? $c->claimed_amount ?? 0);
            $approved = (float) $pClaims->sum('approved_amount');
            $reimbursementRate = $claimed > 0 ? round(($approved / $claimed) * 100, 1) : 0;

            $insurancePayers[] = [
                'payer_name' => $payerName ?: 'National Health Insurance (NHIF)',
                'total_claims' => $pClaims->count(),
                'claimed_amount' => $claimed,
                'approved_amount' => $approved,
                'reimbursement_rate' => $reimbursementRate,
                'status_summary' => [
                    'submitted' => $pClaims->where('status', 'Submitted')->count(),
                    'approved' => $pClaims->where('status', 'Approved')->count(),
                    'remitted' => $pClaims->where('status', 'Remitted')->count(),
                ],
            ];
        }

        return [
            'summary' => [
                'total_billed_tzs' => $totalBilled,
                'total_collected_tzs' => $totalCollected,
                'total_outstanding_tzs' => $totalOutstanding,
                'collection_rate_percent' => $collectionRate,
                'total_invoices' => $invoices->count(),
                'avg_revenue_per_invoice' => $invoices->count() > 0 ? round($totalBilled / $invoices->count(), 2) : 0,
            ],
            'departmental_revenue' => $departmentalRevenue,
            'payer_mix' => [
                'cash_and_mobile' => [
                    'revenue_tzs' => $cashDirectTotal,
                    'share_percent' => ($totalCollected + $insuranceClaimedTotal) > 0
                        ? round(($cashDirectTotal / ($totalCollected + $insuranceClaimedTotal)) * 100, 1)
                        : 100,
                ],
                'insurance_claims' => [
                    'total_claimed_tzs' => $insuranceClaimedTotal,
                    'total_approved_tzs' => $insuranceApprovedTotal,
                    'claims_count' => $claims->count(),
                    'payers' => $insurancePayers,
                ],
            ],
        ];
    }
}
