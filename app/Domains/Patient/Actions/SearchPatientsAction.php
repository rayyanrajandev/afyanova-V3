<?php

namespace App\Domains\Patient\Actions;

use App\Domains\Patient\Models\Patient;
use App\Domains\Patient\Models\PatientIdentifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SearchPatientsAction
{
    public function execute(string $searchTerm = '', int $limit = 50): Collection
    {
        $query = Patient::query()
            ->with([
                'identifiers',
                'contacts',
                'emergencyContacts',
                'allergies',
                'encounters' => function ($q) {
                    $q->with([
                        'provider',
                        'vitals',
                        'notes',
                        'diagnoses',
                        'prescriptions.medication',
                        'invoices.lineItems',
                    ])->latest('start_time');
                },
                'appointments.provider',
                'invoices.lineItems',
            ])
            ->where('status', '!=', 'Merged');

        // When search is empty, return latest registered patients
        if (empty(trim($searchTerm))) {
            return $query->latest('created_at')->limit($limit)->get();
        }

        $likeOperator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($searchTerm, $likeOperator) {
            // Exact or partial MRN Match
            $q->where('primary_mrn', $searchTerm)
                ->orWhere('primary_mrn', $likeOperator, "%{$searchTerm}%")
              // Or partial name match
                ->orWhere('first_name', $likeOperator, "%{$searchTerm}%")
                ->orWhere('last_name', $likeOperator, "%{$searchTerm}%")
              // Or search by external identifiers (NIDA, NHIF) — exact
              // match only. identifier_value is encrypted at rest with a
              // random IV per save, so it can never be searched (exact or
              // partial) directly at the database level; the lookup hash
              // is a deterministic HMAC that only supports exact-match.
                ->orWhereHas('identifiers', function (Builder $sub) use ($searchTerm) {
                    $sub->where('identifier_lookup_hash', PatientIdentifier::lookupHash($searchTerm));
                })
              // Or search by phone number
                ->orWhereHas('contacts', function (Builder $sub) use ($searchTerm, $likeOperator) {
                    $sub->where('value', $likeOperator, "%{$searchTerm}%");
                });
        })
            ->limit($limit)
            ->get();
    }
}
