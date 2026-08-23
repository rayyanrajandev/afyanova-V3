<?php

namespace App\Core\Traits;

use App\Domains\Clinical\Exceptions\ClinicalImmutabilityException;
use Illuminate\Database\Eloquent\Model;

/**
 * Blocks in-place mutation of a clinical record once it's finalized, at the
 * model layer rather than inside a single Action class — so no other code
 * path (a controller, a raw Eloquent call, a future Action) can silently
 * overwrite a record that medical/legal record-keeping requires be amended
 * instead. Host models implement isFinalized(); the sanctioned Amend action
 * for that model is the only caller expected to reach for
 * withFinalizedMutation() (used to flip is_deprecated on the original row).
 */
trait ImmutableWhenFinalized
{
    protected static bool $allowFinalizedMutation = false;

    protected static function bootImmutableWhenFinalized(): void
    {
        static::updating(function (Model $model) {
            if (static::$allowFinalizedMutation) {
                return;
            }

            if ($model->isFinalized()) {
                throw ClinicalImmutabilityException::finalizedRecordCannotBeEdited(
                    class_basename($model),
                    $model->id
                );
            }
        });
    }

    public static function withFinalizedMutation(callable $callback): mixed
    {
        static::$allowFinalizedMutation = true;

        try {
            return $callback();
        } finally {
            static::$allowFinalizedMutation = false;
        }
    }

    abstract protected function isFinalized(): bool;
}
