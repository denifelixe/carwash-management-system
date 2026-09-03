<?php

namespace App\Http\Controllers\Demo;

use App\Support\AppSettings;
use Illuminate\Http\Request;
use Inertia\Response;

class ReceiptController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'admin/master/Receipt', [
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
            'capabilities' => ['update' => false],
        ]);
    }
}
