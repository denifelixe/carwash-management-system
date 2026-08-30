<?php

namespace App\Http\Controllers\Demo;

use App\Support\AppSettings;
use App\Support\Timezones;
use Illuminate\Http\Request;
use Inertia\Response;

class TimezoneController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'admin/master/Timezone', [
            'timezone' => AppSettings::timezone(),
            'timezones' => Timezones::options(),
            'capabilities' => ['update' => false],
        ]);
    }
}
