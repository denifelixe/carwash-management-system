<?php

namespace Database\Factories;

use App\Models\DailyBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyBalance>
 */
class DailyBalanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cashIncome = fake()->numberBetween(100000, 1000000);
        $cashExpense = fake()->numberBetween(0, $cashIncome);
        $nonCashIncome = fake()->numberBetween(100000, 1000000);
        $nonCashExpense = fake()->numberBetween(0, $nonCashIncome);

        return [
            'date' => fake()->unique()->date(),
            'cash_income' => $cashIncome,
            'cash_expense' => $cashExpense,
            'cash_balance' => $cashIncome - $cashExpense,
            'non_cash_income' => $nonCashIncome,
            'non_cash_expense' => $nonCashExpense,
            'non_cash_balance' => $nonCashIncome - $nonCashExpense,
        ];
    }
}
