<?php

namespace App\Core\Traits;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;

trait AuthorizesWorkspaceAccess
{
    /**
     * Hard page-level gate: abort 403 unless the user holds at least one of the given slugs.
     */
    protected function authorizeAnyWorkspacePermission(User $user, AuthorizationService $authService, array $slugs, ?string $facilityId = null): void
    {
        abort_unless(
            collect($slugs)->contains(fn (string $slug) => $authService->hasPermission($user, $slug, $facilityId)),
            403
        );
    }

    /**
     * Section-level `can` map: one hasPermission() call per section (never
     * getUserPermissions(), which does not special-case the tenant-admin
     * bypass the way hasPermission() does).
     *
     * @param  array<string, string>  $sectionSlugs  section name => permission slug
     * @return array<string, bool>
     */
    protected function buildSectionCanMap(User $user, AuthorizationService $authService, array $sectionSlugs, ?string $facilityId = null): array
    {
        return collect($sectionSlugs)
            ->map(fn (string $slug) => $authService->hasPermission($user, $slug, $facilityId))
            ->all();
    }
}
