<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Support\Admin\TransactionShiftResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Routes whose success also plays a voice clip: the cashier and field staff
     * act on these at the counter, often without watching the screen. Each
     * value names a recording in public/notification-sounds/ (without .mp3).
     *
     * @var array<string, string>
     */
    private const SOUND_ROUTES = [
        'admin.orders.store' => 'order-telah-berhasil-dibuat',
        'admin.orders.status.update' => 'status-order-telah-berhasil-diperbarui',
        'admin.pos.payments.store' => 'pembayaran-berhasil',
    ];

    /**
     * Controllers report outcomes with redirect()->with('success', ...), which
     * the frontend never reads; it only listens for the Inertia `toast` flash.
     * Turning the fresh session message into that flash on the way out makes
     * every one of those redirects show its notification.
     */
    protected function reflash(Request $request): void
    {
        if ($request->hasSession()) {
            $session = $request->session();
            $message = $session->get('success');

            if (
                in_array('success', $session->get('_flash.new', []), true)
                && is_string($message)
                && ! array_key_exists('toast', Inertia::getFlashed($request))
            ) {
                Inertia::flash('toast', [
                    'type' => 'success',
                    'message' => $message,
                    'sound' => self::SOUND_ROUTES[$request->route()?->getName() ?? ''] ?? null,
                ]);
            }
        }

        parent::reflash($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $admin = $request->user('admin');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'loginShift' => $admin instanceof Admin && $request->routeIs('admin.*')
                ? app(TransactionShiftResolver::class)->loginPresentation($admin)
                : null,
            'auth' => [
                'admin' => $admin instanceof Admin
                    ? [...$admin->toArray(), 'avatar' => $admin->profilePhotoUrl()]
                    : null,
                'member' => $request->user('member'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
