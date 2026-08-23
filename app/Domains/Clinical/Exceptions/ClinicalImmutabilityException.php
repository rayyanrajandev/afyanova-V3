<?php

namespace App\Domains\Clinical\Exceptions;

use Exception;

class ClinicalImmutabilityException extends Exception
{
    public static function signedNoteCannotBeEdited(string $noteId): self
    {
        return new self("Clinical note {$noteId} is already signed and cannot be edited. You must create an amendment/addendum instead.");
    }

    public static function deprecatedRecordCannotBeEdited(string $entity, string $id): self
    {
        return new self("The {$entity} record {$id} is deprecated and cannot be modified.");
    }

    public static function finalizedRecordCannotBeEdited(string $entity, string $id): self
    {
        return new self("The {$entity} record {$id} is finalized and cannot be edited directly. Create an amendment instead.");
    }
}
