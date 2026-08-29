<?php

namespace App\Http\Controllers\Demo;

use App\Support\Demo\Catalog;
use App\Support\Demo\Customers;
use App\Support\Demo\Operations;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Scheduled booking orders (BR-08).
 */
class BookingController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'demo/admin/Bookings', [
            'bookings' => Operations::scheduledBookings(),
            'today' => now()->toDateString(),
            'services' => Catalog::services(),
            'customers' => Customers::all(),
        ]);
    }
}
