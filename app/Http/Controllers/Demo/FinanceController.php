<?php

namespace App\Http\Controllers\Demo;

use App\Support\Admin\AdminModuleActions;
use App\Support\Demo\Brand;
use App\Support\Demo\DateFilter;
use App\Support\Demo\Finance;
use App\Support\Demo\Operations;
use App\Support\Demo\Reports;
use App\Support\Demo\RoleAccess;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Money in and money out for daily operations (BR-10).
 */
class FinanceController extends AdminController
{
    public function index(Request $request): Response
    {
        // No date in the URL means the day the module is being used on.
        $date = DateFilter::fromRequest($request) ?: Reports::todayDate();
        $role = (string) $request->session()->get(RoleAccess::SESSION_KEY, RoleAccess::DEFAULT_ROLE);
        $canViewNonCashBalance = RoleAccess::allowsAdditionalAction(
            $role,
            'finance',
            AdminModuleActions::VIEW_NON_CASH_BALANCE,
        );
        $dailyBalance = Finance::dailyBalance($date);
        $dailyBalanceHistory = Finance::dailyBalanceHistory($date);

        if (! $canViewNonCashBalance) {
            $dailyBalance['nonCash'] = 0;
            $dailyBalance['previous']['nonCash'] = 0;
            $dailyBalanceHistory = array_map(
                fn (array $balance): array => [
                    ...$balance,
                    'nonCashIncome' => 0,
                    'nonCashExpense' => 0,
                    'nonCashBalance' => 0,
                ],
                $dailyBalanceHistory,
            );
        }

        return $this->page($request, 'admin/Finance', [
            'moneyIn' => DateFilter::apply(Finance::moneyIn(), $date),
            'moneyOut' => DateFilter::apply(Finance::moneyOut(), $date),
            'filters' => DateFilter::meta($date),
            'incomeCategories' => Finance::incomeCategories(),
            'expenseCategories' => Finance::expenseCategories(),
            'cashSummary' => Finance::summary($date),
            'dailyBalance' => $dailyBalance,
            'dailyBalanceHistory' => $dailyBalanceHistory,
            'paymentMethods' => Operations::paymentMethods(),
            'expenseMethods' => Operations::expenseMethods(),
            'shifts' => Brand::shifts(),
            'orders' => Operations::orders(),
            'capabilities' => [
                'create' => true,
                'update' => true,
                'delete' => true,
                'edit_cash_entry_backdate' => RoleAccess::allowsAdditionalAction(
                    $role,
                    'finance',
                    AdminModuleActions::EDIT_CASH_ENTRY_BACKDATE,
                ),
                'view_non_cash_balance' => $canViewNonCashBalance,
            ],
        ]);
    }
}
