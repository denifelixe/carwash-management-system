<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->string('scope', 16);
            $table->string('period', 10);
            $table->unsignedInteger('last_number');
            $table->primary(['scope', 'period']);
        });

        try {
            DB::transaction(function (): void {
                $orders = DB::table('orders')->orderBy('created_at')->orderBy('id')->get(['id', 'created_at', 'source']);
                $payments = DB::table('order_transactions')->orderBy('order_id')->orderBy('created_at')->orderBy('id')->get(['id', 'order_id']);
                $cashEntries = DB::table('cash_entries')->orderBy('entry_date')->orderBy('occurred_at')->orderBy('id')->get(['id', 'category', 'entry_date']);
                $orderNumbers = [];
                $orderCounts = [];
                $paymentCounts = [];
                $cashCounts = [];
                $cashNumbers = [];

                foreach ($orders as $order) {
                    if ($order->created_at === null) {
                        throw new RuntimeException("Order {$order->id} tidak memiliki created_at.");
                    }

                    $period = substr($order->created_at, 0, 7);
                    $sequence = $orderCounts[$period] = ($orderCounts[$period] ?? 0) + 1;

                    if ($sequence > 99999999) {
                        throw new RuntimeException("Urutan order {$period} melebihi delapan digit.");
                    }

                    $orderNumbers[$order->id] = str_pad((string) $sequence, 8, '0', STR_PAD_LEFT)
                        .'/ORD/'.($order->source === 'booking' ? 'BK/' : '')
                        .substr($period, 5, 2).substr($period, 2, 2);
                }

                foreach ($cashEntries as $entry) {
                    if ($entry->entry_date === null) {
                        throw new RuntimeException("Transaksi kas {$entry->id} tidak memiliki tanggal.");
                    }

                    $date = substr($entry->entry_date, 0, 10);
                    $sequence = $cashCounts[$date] = ($cashCounts[$date] ?? 0) + 1;

                    if ($sequence > 9999) {
                        throw new RuntimeException("Urutan transaksi kas {$date} melebihi empat digit.");
                    }

                    $words = preg_split('/[^A-Z0-9]+/', strtoupper($entry->category), flags: PREG_SPLIT_NO_EMPTY);
                    $code = implode('', array_map(fn (string $word): string => substr($word, 0, 1), $words ?: []));
                    $cashNumbers[$entry->id] = 'TRX-'.$code.'-'.substr($date, 2, 2).substr($date, 5, 2).substr($date, 8, 2)
                        .'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                }

                foreach ($orders as $order) {
                    DB::table('orders')->where('id', $order->id)->update([
                        'number' => '__order_migration_'.$order->id,
                        'invoice_number' => '__invoice_migration_'.$order->id,
                    ]);
                }

                foreach ($payments as $payment) {
                    DB::table('order_transactions')->where('id', $payment->id)->update(['reference' => '__payment_migration_'.$payment->id]);
                }

                foreach ($cashEntries as $entry) {
                    DB::table('cash_entries')->where('id', $entry->id)->update(['reference' => '__cash_migration_'.$entry->id]);
                }

                foreach ($orders as $order) {
                    DB::table('orders')->where('id', $order->id)->update([
                        'number' => $orderNumbers[$order->id],
                        'invoice_number' => $orderNumbers[$order->id],
                    ]);
                }

                foreach ($payments as $payment) {
                    $index = $paymentCounts[$payment->order_id] = ($paymentCounts[$payment->order_id] ?? 0) + 1;
                    DB::table('order_transactions')->where('id', $payment->id)->update([
                        'reference' => $orderNumbers[$payment->order_id].'/TRX'.$index,
                    ]);
                }

                foreach ($cashEntries as $entry) {
                    DB::table('cash_entries')->where('id', $entry->id)->update(['reference' => $cashNumbers[$entry->id]]);
                }

                foreach ($orderCounts as $period => $lastNumber) {
                    DB::table('number_sequences')->insert(['scope' => 'order', 'period' => $period, 'last_number' => $lastNumber]);
                }

                foreach ($cashCounts as $period => $lastNumber) {
                    DB::table('number_sequences')->insert(['scope' => 'cash', 'period' => $period, 'last_number' => $lastNumber]);
                }
            });
        } catch (Throwable $exception) {
            Schema::dropIfExists('number_sequences');

            throw $exception;
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Nomor lama tidak dapat dipulihkan otomatis; gunakan backup database.');
    }
};
