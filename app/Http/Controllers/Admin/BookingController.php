<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SaveBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBookingRequest;
use App\Http\Requests\Admin\UpdateBookingRequest;
use App\Models\Admin;
use App\Models\Member;
use App\Models\Order;
use App\Models\Service;
use App\Support\Admin\AdminShell;
use App\Support\Admin\OrderPresenter;
use App\Support\Admin\OrderQueries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.bookings.read');

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $bookings = OrderQueries::bookings();
        $services = OrderQueries::servicesFor($bookings);

        return Inertia::render('admin/Bookings', [
            ...$adminShell->props($admin, 'Booking Order', 'bookings'),
            'bookings' => $bookings->map(fn (Order $booking): array => OrderPresenter::booking($booking))->all(),
            'today' => now()->toDateString(),
            'services' => $services->map(fn (Service $service): array => OrderPresenter::service($service))->all(),
            'customers' => OrderQueries::customers()
                ->map(fn (Member $member): array => OrderPresenter::customer($member))->all(),
            'capabilities' => [
                'create' => Gate::allows('admin.bookings.create'),
                'update' => Gate::allows('admin.bookings.update'),
            ],
        ]);
    }

    public function store(StoreBookingRequest $request, SaveBooking $saveBooking): RedirectResponse
    {
        $saveBooking->handle(
            $request->validated(),
            (int) $request->user('admin')?->getAuthIdentifier(),
        );

        return to_route('admin.bookings.index')->with('success', 'Booking berhasil disimpan.');
    }

    public function update(
        UpdateBookingRequest $request,
        Order $order,
        SaveBooking $saveBooking,
    ): RedirectResponse {
        $saveBooking->handle(
            $request->validated(),
            (int) $request->user('admin')?->getAuthIdentifier(),
            $order,
        );

        return to_route('admin.bookings.index')->with('success', 'Booking berhasil diperbarui.');
    }
}
