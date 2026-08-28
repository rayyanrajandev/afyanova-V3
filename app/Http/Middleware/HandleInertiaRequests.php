<?php

namespace App\Http\Middleware;

use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Tenancy\Models\Facility;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'tenant' => fn () => $request->user()?->tenant ? [
                    'id' => $request->user()->tenant->id,
                    'name' => $request->user()->tenant->name,
                    'slug' => $request->user()->tenant->slug,
                ] : null,
                'facility' => fn () => $request->user() ? Facility::where('tenant_id', $request->user()->tenant_id)->first() : null,
                'permissions' => fn () => $request->user()
                    ? app(AuthorizationService::class)->getUserPermissions($request->user())
                    : [],
                'is_superadmin' => fn () => $request->user()
                    ? (app(AuthorizationService::class)->isSuperAdmin($request->user()) || app(AuthorizationService::class)->hasPermission($request->user(), 'platform.superadmin.access'))
                    : false,
            ],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'breakGlass' => fn () => $request->session()->has('break_glass.patient_id') ? [
                'patientId' => $request->session()->get('break_glass.patient_id'),
                'expiresAt' => $request->session()->get('break_glass.expires_at'),
            ] : null,
            'impersonation' => fn () => $request->session()->has('impersonation') ? $request->session()->get('impersonation') : null,
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
