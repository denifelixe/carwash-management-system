<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTimezoneRequest;
use App\Models\Admin;
use App\Support\Admin\AdminShell;
use App\Support\AppSettings;
use App\Support\Timezones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The outlet's clock. Every datetime the app stores is written in the zone set
 * here, so the modules downstream never convert anything — see AppSettings.
 */
class TimezoneController extends Controller
{
    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.master_timezone.read');

        /** @var Admin $authenticatedAdmin */
        $authenticatedAdmin = $request->user('admin');

        return Inertia::render('admin/master/Timezone', [
            ...$adminShell->props($authenticatedAdmin, 'Timezone', 'master_timezone'),
            'timezone' => AppSettings::timezone(),
            'timezones' => Timezones::options(),
            'capabilities' => [
                'update' => Gate::allows('admin.master_timezone.update'),
            ],
        ]);
    }

    public function update(UpdateTimezoneRequest $request): RedirectResponse
    {
        /** @var Admin $authenticatedAdmin */
        $authenticatedAdmin = $request->user('admin');
        $timezone = (string) $request->validated('timezone');

        AppSettings::put(AppSettings::TIMEZONE, $timezone, $authenticatedAdmin->id);
        /* Applied at once so this very response already reads the new clock. */
        AppSettings::applyTimezone();

        return to_route('admin.master.timezone.index')
            ->with('success', 'Zona waktu diubah ke '.Timezones::code($timezone).'.');
    }
}
