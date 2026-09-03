<?php

namespace App\Http\Controllers\Admin\Master;

use App\Actions\Admin\UpdateReceiptSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateReceiptSettingRequest;
use App\Models\Admin;
use App\Support\Admin\AdminShell;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.master_receipt.read');

        /** @var Admin $authenticatedAdmin */
        $authenticatedAdmin = $request->user('admin');

        return Inertia::render('admin/master/Receipt', [
            ...$adminShell->props($authenticatedAdmin, 'Struk', 'master_receipt'),
            'settings' => [
                'receiptBusinessName' => AppSettings::receiptBusinessName(),
                'receiptFooterNote' => AppSettings::receiptFooterNote(),
                'receiptShowLogo' => AppSettings::receiptShowsLogo(),
                'receiptShowQr' => AppSettings::receiptShowsQr(),
                'receiptPhotoUrl' => AppSettings::receiptPhotoUrl(),
                'hasOwnReceiptPhoto' => AppSettings::hasOwnReceiptPhoto(),
                'receiptLogoWidth' => AppSettings::receiptLogoWidth(),
                'receiptLogoWidthMin' => AppSettings::RECEIPT_LOGO_WIDTH_MIN,
                'receiptLogoWidthMax' => AppSettings::RECEIPT_LOGO_WIDTH_MAX,
                'appPhotoUrl' => AppSettings::appPhotoUrl(),
            ],
            'capabilities' => [
                'update' => Gate::allows('admin.master_receipt.update'),
            ],
        ]);
    }

    public function update(
        UpdateReceiptSettingRequest $request,
        UpdateReceiptSettings $updateReceiptSettings,
    ): RedirectResponse {
        /** @var Admin $authenticatedAdmin */
        $authenticatedAdmin = $request->user('admin');

        $updateReceiptSettings->handle($request->receipt(), $authenticatedAdmin);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pengaturan struk berhasil diperbarui.',
        ]);

        return to_route('admin.master.receipt.index');
    }
}
