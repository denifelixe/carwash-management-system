<?php

namespace App\Http\Controllers\Demo;

use App\Support\AppSettings;
use Illuminate\Http\Request;
use Inertia\Response;

class AppSettingController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'admin/master/AppSettings', [
            'settings' => [
                'appName' => AppSettings::appName(),
                'appPhotoUrl' => AppSettings::appPhotoUrl(),
                'hasAppPhoto' => AppSettings::get(AppSettings::APP_PHOTO) !== null,
                'faviconUrl' => AppSettings::faviconUrl(),
                'favicon16Url' => AppSettings::favicon16Url(),
                'favicon32Url' => AppSettings::favicon32Url(),
                'appleTouchIconUrl' => AppSettings::appleTouchIconUrl(),
                'androidChrome192Url' => AppSettings::androidChrome192Url(),
                'androidChrome512Url' => AppSettings::androidChrome512Url(),
                'siteWebmanifestUrl' => AppSettings::siteWebmanifestUrl(),
                'whatsapp' => AppSettings::whatsapp(),
                'instagram' => AppSettings::instagram(),
                'metaTitle' => AppSettings::metaTitle(),
                'metaDescription' => AppSettings::metaDescription(),
                'metaImageUrl' => AppSettings::metaImageUrl() ?? '/og-image.png',
                'hasMetaImage' => AppSettings::get(AppSettings::META_IMAGE) !== null,
            ],
            'capabilities' => ['update' => false],
        ]);
    }
}
