<?php

namespace App\Domains\Scheduling\Enums;

/**
 * QueueTicket.status is a plain varchar(20) column (migration default:
 * 'Waiting') with no DB-level check constraint — this enum is the only
 * thing that now enforces which values are valid, in place of a comment on
 * the migration and everyone's memory of it.
 *
 * Values below are the ones actually written anywhere in the codebase,
 * confirmed by grep before adding this cast — with one exception:
 * Abandoned was documented in the migration comment from day one but never
 * had a write path. Kept as a defined case (the intent is real, just
 * unimplemented) rather than silently dropped, since dropping it would
 * make it impossible to add that write path later without editing this
 * enum anyway — no different from adding any other new case.
 *
 * This replaces a real, live inconsistency: nearly every read site in the
 * app defensively checked for both 'In Progress' and 'In_Progress' even
 * though the underscore form was never once written to this table (it's
 * the actual value used by the unrelated StocktakeSession.status column,
 * apparently copied from there at some point). With this enum, that
 * variant can no longer be expressed at all.
 */
enum QueueTicketStatus: string
{
    case Waiting = 'Waiting';
    case InProgress = 'In Progress';
    case Completed = 'Completed';
    case Abandoned = 'Abandoned';
}
