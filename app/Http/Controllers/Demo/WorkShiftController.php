<?php

namespace App\Http\Controllers\Demo;

use Illuminate\Http\Request;
use Inertia\Response;

class WorkShiftController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'admin/master/WorkShifts', [
            'workShifts' => [
                ['id' => 1, 'key' => 'morning', 'name' => 'Shift Pagi', 'starts_at' => '08:00', 'ends_at' => '16:00', 'is_active' => true, 'admin_count' => 4],
                ['id' => 2, 'key' => 'evening', 'name' => 'Shift Sore', 'starts_at' => '16:00', 'ends_at' => '23:00', 'is_active' => true, 'admin_count' => 4],
            ],
            'capabilities' => ['create' => true, 'update' => true, 'delete' => true],
        ]);
    }
}
