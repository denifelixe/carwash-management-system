<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Member;
use App\Support\Admin\AdminShell;
use App\Support\Demo\DateFilter;
use App\Support\Demo\Reports;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AdminShell $adminShell): Response
    {
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin, 403);

        $date = DateFilter::fromRequest($request) ?: Reports::todayDate();
        $activeMembers = Member::query()->where('is_active', true)->count();
        $totalMembers = Member::query()->count();

        return Inertia::render('admin/Dashboard', [
            ...$adminShell->props($admin, 'Dashboard', 'dashboard'),
            'filterUrl' => route('admin.dashboard', absolute: false),
            'filters' => DateFilter::meta($date),
            'stats' => $this->stats($activeMembers, $totalMembers),
            'shifts' => [],
            'orderSummary' => [
                'total' => 0,
                'served' => 0,
                'awaitingBooking' => 0,
            ],
            'cashSummary' => [
                'openingBalance' => 0,
                'todayIn' => 0,
                'todayOut' => 0,
                'remainingBalance' => 0,
                'closingBalance' => 0,
                'pendingPayments' => 0,
            ],
        ]);
    }

    /**
     * @return list<array{label: string, value: string, caption: string, delta: float, trend: string, icon: string}>
     */
    private function stats(int $activeMembers, int $totalMembers): array
    {
        return [
            ['label' => 'Pendapatan Hari Ini', 'value' => 'Rp 0', 'caption' => 'dari 0 transaksi keuangan', 'delta' => 0.0, 'trend' => 'flat', 'icon' => 'wallet'],
            ['label' => 'Kendaraan Dilayani', 'value' => '0', 'caption' => 'dari 0 order kendaraan', 'delta' => 0.0, 'trend' => 'flat', 'icon' => 'car'],
            ['label' => 'Member Aktif', 'value' => number_format($activeMembers, 0, ',', '.'), 'caption' => 'dari '.number_format($totalMembers, 0, ',', '.').' member terdaftar', 'delta' => 0.0, 'trend' => 'flat', 'icon' => 'users'],
            ['label' => 'Stempel Ditukar', 'value' => '0', 'caption' => '0 reward diklaim', 'delta' => 0.0, 'trend' => 'flat', 'icon' => 'gift'],
        ];
    }
}
