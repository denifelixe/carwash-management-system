<?php

namespace App\Http\Controllers\Carwash;

use App\Support\Carwash\Catalog;
use App\Support\Carwash\Customers;
use App\Support\Carwash\Operations;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Scheduled booking orders (BR-08).
 */
class BookingController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'carwash/admin/Bookings', [
            'bookings' => Operations::scheduledBookings(),
            'today' => now('Asia/Jakarta')->toDateString(),
            'services' => Catalog::services(),
            'customers' => Customers::all(),
        ]);
    }
}
