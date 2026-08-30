<?php

namespace App\Http\Controllers\Admin\Master;

use App\Actions\Admin\UpdateAppBranding;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAppSettingRequest;
use App\Models\Admin;
use App\Support\Admin\AdminShell;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AppSettingController extends Controller
{
    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.master_app_settings.read');

        /** @var Admin $authenticatedAdmin */
        $authenticatedAdmin = $request->user('admin');

        return Inertia::render('admin/master/AppSettings', [
            ...$adminShell->props($authenticatedAdmin, 'App Setting', 'master_app_settings'),
            'settings' => [
                'appName' => AppSettings::appName(),
                'appPhotoUrl' => AppSettings::appPhotoUrl(),
                'faviconUrl' => AppSettings::faviconUrl(),
                'whatsapp' => AppSettings::whatsapp(),
                'instagram' => AppSettings::instagram(),
            ],
            'capabilities' => [
                'update' => Gate::allows('admin.master_app_settings.update'),
            ],
        ]);
    }

    public function update(
        UpdateAppSettingRequest $request,
        UpdateAppBranding $updateAppBranding,
    ): RedirectResponse {
        /** @var Admin $authenticatedAdmin */
        $authenticatedAdmin = $request->user('admin');

        $updateAppBranding->handle($request->branding(), $authenticatedAdmin);

        return to_route('admin.master.app-settings.index')
            ->with('success', 'App setting berhasil diperbarui.');
    }
}
