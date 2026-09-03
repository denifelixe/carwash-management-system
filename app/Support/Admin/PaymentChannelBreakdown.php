<?php

namespace App\Support\Admin;

use Illuminate\Support\Str;

class PaymentChannelBreakdown
{
    /** @param list<array<string, mixed>> $channels */
    public static function tenderedTotal(array $channels): int
    {
        return (int) collect($channels)->sum(
            fn (array $channel): int => max((int) ($channel['amount'] ?? 0), 0),
        );
    }

    /** @param list<array<string, mixed>> $channels */
    public static function cashTendered(array $channels): int
    {
        return (int) collect($channels)
            ->filter(fn (array $channel): bool => self::baseMethod($channel) === 'Tunai')
            ->sum(fn (array $channel): int => max((int) ($channel['amount'] ?? 0), 0));
    }

    /** @param list<array<string, mixed>> $channels */
    public static function change(array $channels, int $bookedAmount): int
    {
        return max(self::tenderedTotal($channels) - max($bookedAmount, 0), 0);
    }

    /**
     * Return the part of each tender that belongs to revenue. Change is taken
     * from cash first. The final fallback only repairs legacy rows that could
     * record non-cash overpayment before that input became invalid.
     *
     * @param  array<int, array<string, mixed>>  $channels
     * @return list<array{label: string, amount: int, reference?: string}>
     */
    public static function financial(array $channels, int $bookedAmount): array
    {
        $financial = array_values(array_map(
            fn (array $channel): array => self::normalizedChannel($channel),
            $channels,
        ));
        $remainingChange = self::change($financial, $bookedAmount);

        for ($index = count($financial) - 1; $index >= 0 && $remainingChange > 0; $index--) {
            if (self::baseMethod($financial[$index]) !== 'Tunai') {
                continue;
            }

            $deduction = min($financial[$index]['amount'], $remainingChange);
            $financial[$index]['amount'] -= $deduction;
            $remainingChange -= $deduction;
        }

        for ($index = count($financial) - 1; $index >= 0 && $remainingChange > 0; $index--) {
            $deduction = min($financial[$index]['amount'], $remainingChange);
            $financial[$index]['amount'] -= $deduction;
            $remainingChange -= $deduction;
        }

        $positiveChannels = [];

        foreach ($financial as $channel) {
            if ($channel['amount'] > 0) {
                $positiveChannels[] = self::normalizedChannel($channel);
            }
        }

        return $positiveChannels;
    }

    /** @param array<string, mixed> $channel */
    private static function baseMethod(array $channel): string
    {
        return Str::before((string) ($channel['label'] ?? $channel['method'] ?? ''), ' · ');
    }

    /**
     * @param  array<string, mixed>  $channel
     * @return array{label: string, amount: int, reference?: string}
     */
    private static function normalizedChannel(array $channel): array
    {
        $label = (string) ($channel['label'] ?? $channel['method'] ?? '');
        $amount = max((int) ($channel['amount'] ?? 0), 0);
        $reference = trim((string) ($channel['reference'] ?? ''));

        return $reference === ''
            ? ['label' => $label, 'amount' => $amount]
            : ['label' => $label, 'amount' => $amount, 'reference' => $reference];
    }
}
