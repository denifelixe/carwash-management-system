<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\Admin\AdminShell;
use App\Support\Admin\DashboardStats;
use App\Support\Admin\FinanceQueries;
use App\Support\Admin\OrderQueries;
use App\Support\Demo\DateFilter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operational dashboard for Owner and Manager (BR-12).
 *
 * Nothing is counted here: the day is read through the same queries the finance
 * and order modules use, so the dashboard restates their figures rather than
 * arriving at its own.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, AdminShell $adminShell): Response
    {
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin, 403);

        $today = CarbonImmutable::now()->startOfDay();
        $date = DateFilter::resolve($request->query('date')) ?: $today->toDateString();
        ['moneyIn' => $moneyIn, 'moneyOut' => $moneyOut] = FinanceQueries::ledgerForDate($date);

        return Inertia::render('admin/Dashboard', [
            ...$adminShell->props($admin, 'Dashboard', 'dashboard'),
            'filterUrl' => route('admin.dashboard', absolute: false),
            'filters' => OrderQueries::filters($date, $today),
            'stats' => DashboardStats::forDate($date, $moneyIn),
            'shifts' => FinanceQueries::shiftSummary($moneyIn, $moneyOut, $date, withUnassigned: true),
            'orderSummary' => OrderQueries::summaryForDate($date),
            'cashSummary' => FinanceQueries::cashSummary($moneyIn, $moneyOut),
        ]);
    }
}
