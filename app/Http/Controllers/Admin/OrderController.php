<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrderRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Admin;
use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\Service;
use App\Support\Admin\AdminShell;
use App\Support\Admin\OrderPresenter;
use App\Support\Admin\OrderQueries;
use App\Support\Demo\DateFilter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /** @var list<string> */
    private const STATUSES = ['booking', 'menunggu', 'proses', 'pelunasan', 'selesai', 'batal'];

    /** @var list<string> */
    private const EDITABLE_STATUSES = ['booking', 'menunggu', 'proses', 'pelunasan', 'batal'];

    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.orders.read');

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $today = CarbonImmutable::now()->startOfDay();
        $selectedDate = DateFilter::resolve($request->query('date')) ?: $today->toDateString();
        $orders = OrderQueries::forDate($selectedDate);
        $services = OrderQueries::servicesFor($orders);

        return Inertia::render('admin/Orders', [
            ...$adminShell->props($admin, 'Order', 'orders'),
            'orders' => $orders->map(fn (Order $order): array => OrderPresenter::order($order))->all(),
            'filters' => OrderQueries::filters($selectedDate, $today),
            'orderStatuses' => self::STATUSES,
            'editableOrderStatuses' => self::EDITABLE_STATUSES,
            'upcoming' => [],
            'services' => $services->map(fn (Service $service): array => OrderPresenter::service($service))->all(),
            'serviceCategories' => $services->where('is_active', true)->pluck('category')->unique()->values()->all(),
            'customers' => OrderQueries::customers()
                ->map(fn (Member $member): array => OrderPresenter::customer($member))->all(),
            'crew' => Admin::query()->visibleInOperations()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Admin $crew): array => [
                    'name' => $crew->name,
                    'role' => 'Crew',
                    'jobs' => 0,
                    'rating' => 0,
                    'initials' => OrderPresenter::initials($crew->name),
                ])->all(),
            'paymentMethods' => OrderQueries::PAYMENT_METHODS,
            'capabilities' => [
                'create' => Gate::allows('admin.orders.create'),
                'update' => Gate::allows('admin.orders.update'),
            ],
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request): void {
            /** @var Collection<int, Service> $services */
            $services = Service::query()->whereKey($data['service_ids'])->where('is_active', true)->lockForUpdate()->get();
            $member = null;
            $vehicle = null;

            if ($data['customer_mode'] === 'existing') {
                $member = Member::query()->whereKey((int) $data['member_id'])->firstOrFail();
                $vehicle = MemberVehicle::query()->whereKey((int) $data['member_vehicle_id'])->firstOrFail();
                $customerName = $member->name;
                $customerPhone = $member->phone ?? '';
                $vehicleName = $vehicle->name;
                $vehiclePlate = $vehicle->plate;
            } else {
                $customerName = Str::squish($data['customer_name'] ?? '');
                $customerPhone = $data['customer_phone'] ?? '';
                $vehicleName = Str::squish($data['vehicle_name'] ?? '');
                $vehiclePlate = $data['vehicle_plate'] ?? '';
            }

            $subtotal = (int) $services->sum('price');

            $order = Order::query()->create([
                'number' => 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'member_id' => $member?->id,
                'member_vehicle_id' => $vehicle?->id,
                'created_by_admin_id' => $request->user('admin')?->getAuthIdentifier(),
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'vehicle_name' => $vehicleName,
                'vehicle_plate' => $vehiclePlate,
                'service_date' => now()->toDateString(),
                /* The outlet's own clock, which is what the column holds. */
                'arrived_at' => now(),
                'source' => 'walk-in',
                'status' => 'menunggu',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'stamps_earned' => $member === null ? 0 : (int) $services->sum('stamps'),
            ]);

            $order->services()->attach($services->mapWithKeys(fn (Service $service): array => [
                $service->id => [
                    'service_name' => $service->name,
                    'unit_price' => $service->price,
                    'stamps' => $service->stamps,
                ],
            ])->all());
        });

        return to_route('admin.orders.index')->with('success', 'Order berhasil disimpan.');
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        abort_if($order->status === 'selesai', 422, 'Order yang sudah selesai tidak dapat diubah.');

        $order->update(['status' => $request->validated('status')]);

        return back()->with('success', 'Status order berhasil diperbarui.');
    }
}
