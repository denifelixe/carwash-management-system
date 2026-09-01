<?php

namespace App\Models;

use Database\Factories\DailyBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One daily snapshot of accumulated cash and non-cash balances.
 *
 * @property int $id
 * @property Carbon $date
 * @property int $cash_income
 * @property int $cash_expense
 * @property int $cash_balance
 * @property int $non_cash_income
 * @property int $non_cash_expense
 * @property int $non_cash_balance
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'date',
    'cash_income',
    'cash_expense',
    'cash_balance',
    'non_cash_income',
    'non_cash_expense',
    'non_cash_balance',
])]
class DailyBalance extends Model
{
    /** @use HasFactory<DailyBalanceFactory> */
    use HasFactory;

    protected $table = 'daily_balance';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'cash_income' => 'integer',
            'cash_expense' => 'integer',
            'cash_balance' => 'integer',
            'non_cash_income' => 'integer',
            'non_cash_expense' => 'integer',
            'non_cash_balance' => 'integer',
        ];
    }
}
