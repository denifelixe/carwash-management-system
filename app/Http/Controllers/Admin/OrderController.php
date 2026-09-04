<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CaptureOrderLead;
use App\Actions\Admin\DeleteOrder;
use App\Actions\Admin\UpdateOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrderRequest;
use App\Http\Requests\Admin\UpdateOrderHandlerRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Admin;
use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\ServiceVariation;
use App\Support\Admin\AdminShell;
use App\Support\Admin\LeadQueries;
use App\Support\Admin\OperationalDataWindow;
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
            'services' => $services->map(fn ($service): array => OrderPresenter::service($service))->all(),
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
            /*
             * The walk-in tab searches leads against the server rather than
             * against a preloaded roster: leads grow with every non-member
             * visit, so shipping them all would bloat this page forever.
             */
            'leadOptions' => Inertia::optional(
                fn (): array => LeadQueries::searchOptions($request->string('leadQuery')->toString()),
            ),
            'paymentMethods' => OrderQueries::PAYMENT_METHODS,
            'capabilities' => [
                'create' => Gate::allows('admin.orders.create'),
                'update' => Gate::allows('admin.orders.update'),
                'delete' => Gate::allows('admin.orders.delete'),
            ],
        ]);
    }

    public function store(StoreOrderRequest $request, CaptureOrderLead $captureOrderLead): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $captureOrderLead): void {
            $quantities = collect($data['items'])->mapWithKeys(
                fn (array $item): array => [(int) $item['service_variation_id'] => (int) $item['quantity']],
            );
            /** @var Collection<int, ServiceVariation> $variations */
            $variations = ServiceVariation::query()->with('service')->whereKey($quantities->keys())
                ->where('is_active', true)->whereHas('service', fn ($query) => $query->where('is_active', true))
                ->lockForUpdate()->get();

            abort_if($variations->count() !== $quantities->count(), 422, 'Pilihan layanan tidak lagi tersedia.');
            $member = null;
            $lead = null;
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

                /*
                 * Every non-member visit is filed against the plate, so a walk-in
                 * who keeps coming back builds one lead instead of a new row per
                 * order. The order form's lead picker is only a prefill on top.
                 */
                $lead = $captureOrderLead->handle([
                    'name' => $customerName,
                    'phone' => $customerPhone,
                    'vehicle_name' => $vehicleName,
                    'vehicle_plate' => $vehiclePlate,
                ]);
            }

            $subtotal = (int) $variations->sum(
                fn (ServiceVariation $variation): int => $variation->price * $quantities[$variation->id],
            );

            $order = Order::query()->create([
                'number' => 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'member_id' => $member?->id,
                'member_vehicle_id' => $vehicle?->id,
                'lead_id' => $lead?->id,
                'created_by_admin_id' => $request->user('admin')?->getAuthIdentifier(),
                'handled_by_admin_id' => $data['handled_by_admin_id'],
                'handled_by' => $data['handled_by'],
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
                'stamps_earned' => $member === null ? 0 : (int) $variations->sum(
                    fn (ServiceVariation $variation): int => $variation->service->stamps * $quantities[$variation->id],
                ),
            ]);

            $order->serviceVariations()->attach($variations->mapWithKeys(fn (ServiceVariation $variation): array => [
                $variation->id => [
                    'service_name' => $variation->service->name,
                    'variations' => $variation->variations === null
                        ? null
                        : json_encode($variation->variations, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'unit_price' => $variation->price,
                    'quantity' => $quantities[$variation->id],
                    'total_price' => $variation->price * $quantities[$variation->id],
                    'stamps' => $variation->service->stamps,
                ],
            ])->all());
        });

        return to_route('admin.orders.index')->with('success', 'Order berhasil disimpan.');
    }

    public function updateHandler(UpdateOrderHandlerRequest $request, Order $order): RedirectResponse
    {
        DB::transaction(function () use ($order, $request): void {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            OperationalDataWindow::ensureAllows($order->service_date);
            abort_unless(
                $order->isEditable(),
                422,
                'Order yang sudah memiliki transaksi, lunas, atau selesai tidak dapat diubah.',
            );
            $order->update($request->validated());
        });

        return back()->with('success', 'Handler order berhasil diperbarui.');
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        DB::transaction(function () use ($order, $request): void {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            OperationalDataWindow::ensureAllows($order->service_date);
            abort_unless(
                $order->isEditable(),
                422,
                'Order yang sudah memiliki transaksi, lunas, atau selesai tidak dapat diubah.',
            );
            $order->update(['status' => $request->validated('status')]);
        });

        return back()->with('success', 'Status order berhasil diperbarui.');
    }

    public function update(UpdateOrderRequest $request, Order $order, UpdateOrder $updateOrder): RedirectResponse
    {
        $updateOrder->handle($order, $request->validated());

        return back()->with('success', 'Order berhasil diperbarui.');
    }

    public function destroy(Request $request, Order $order, DeleteOrder $deleteOrder): RedirectResponse
    {
        Gate::authorize('admin.orders.delete');
        /** @var Admin $admin */
        $admin = $request->user('admin');

        $serviceDate = $deleteOrder->handle($order, $admin);

        return to_route('admin.orders.index', ['date' => $serviceDate])
            ->with('success', 'Order berhasil dihapus.');
    }
}
