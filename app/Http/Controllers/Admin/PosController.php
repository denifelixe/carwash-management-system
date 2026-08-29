<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordOrderPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrderPaymentRequest;
use App\Models\Admin;
use App\Models\Member;
use App\Models\Order;
use App\Models\Service;
use App\Support\Admin\AdminShell;
use App\Support\Admin\OrderPresenter;
use App\Support\Admin\OrderQueries;
use App\Support\Demo\DateFilter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Point of sale (BR-06).
 *
 * The cashier settles orders the floor has already handed over rather than
 * composing new ones, so the page is fed the day's orders and only needs the
 * catalog to spell out what each order was billed for. Bookings whose car has
 * not arrived stay listed whichever day is filtered, because a deposit is taken
 * before the visit.
 */
class PosController extends Controller
{
    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.pos.read');

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        $selectedDate = DateFilter::resolve($request->query('date')) ?: $today->toDateString();
        $dailyOrders = OrderQueries::forDate($selectedDate);
        $bookings = OrderQueries::upcomingBookings($today->toDateString());
        $services = OrderQueries::servicesFor($dailyOrders->merge($bookings));

        return Inertia::render('admin/Pos', [
            ...$adminShell->props($admin, 'Kasir POS', 'pos'),
            'orders' => $dailyOrders
                ->where('status', 'pelunasan')
                ->map(fn (Order $order): array => OrderPresenter::order($order))
                ->values()
                ->all(),
            'dailyOrders' => $dailyOrders->map(fn (Order $order): array => OrderPresenter::order($order))->all(),
            'partialPaymentBookings' => $bookings->map(fn (Order $order): array => OrderPresenter::order($order))->all(),
            'filters' => OrderQueries::filters($selectedDate, $today),
            'services' => $services->map(fn (Service $service): array => OrderPresenter::service($service))->all(),
            'customers' => OrderQueries::customers()
                ->map(fn (Member $member): array => OrderPresenter::customer($member))->all(),
            /* Loyalty rewards are not part of the live catalog yet. */
            'rewards' => [],
            'paymentMethods' => OrderQueries::PAYMENT_METHODS,
            'capabilities' => [
                'create' => Gate::allows('admin.pos.create'),
            ],
        ]);
    }

    public function store(StoreOrderPaymentRequest $request, Order $order, RecordOrderPayment $recordPayment): RedirectResponse
    {
        /** @var Admin $cashier */
        $cashier = $request->user('admin');
        $recordPayment->handle($order, $cashier, $request->payment());

        /*
         * The slip is printed from the reloaded order rather than from what was
         * submitted, so the invoice number and transaction reference the write
         * issued are the ones the cashier hands over.
         */
        return back()->with('success', 'Pembayaran berhasil dicatat.');
    }
}
