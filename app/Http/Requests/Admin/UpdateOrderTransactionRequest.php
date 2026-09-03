<?php

namespace App\Http\Requests\Admin;

use App\Models\OrderTransaction;
use App\Support\Admin\OrderQueries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateOrderTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.finance.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:999999999'],
            'channels' => ['required', 'array', 'min:1', 'max:'.count(OrderQueries::PAYMENT_METHODS)],
            'channels.*.label' => ['required', 'string', 'max:100', 'distinct'],
            'channels.*.amount' => ['required', 'integer', 'min:1', 'max:999999999'],
            'channels.*.provider' => ['nullable', 'string', 'max:60'],
            'channels.*.reference' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $channels = $this->channels();
            $amount = $this->integer('amount');

            foreach ($channels as $index => $channel) {
                $method = Str::before($channel['label'], ' · ');

                if (! in_array($method, OrderQueries::PAYMENT_METHODS, true)) {
                    $validator->errors()->add("channels.{$index}.label", 'Kanal pembayaran tidak valid.');
                }

                if ($method === 'Debit' && $channel['provider'] === '') {
                    $validator->errors()->add(
                        "channels.{$index}.provider",
                        'Bank wajib dipilih untuk pembayaran debit.',
                    );
                }
            }

            if (array_sum(array_column($channels, 'amount')) !== $amount) {
                $validator->errors()->add('channels', 'Total kanal pembayaran harus sama dengan nominal transaksi.');
            }

            $transaction = $this->transaction();
            $order = $transaction->order()->firstOrFail();
            $correctedPaidAmount = (int) $order->transactions()
                ->where('id', '!=', $transaction->id)
                ->sum('amount') + $amount;

            if ($correctedPaidAmount > (int) $order->total) {
                $validator->errors()->add('amount', 'Total pembayaran tidak boleh melebihi total order.');
            }

            if ($order->status === 'selesai' && $correctedPaidAmount !== (int) $order->total) {
                $validator->errors()->add('amount', 'Order selesai harus tetap berstatus lunas setelah koreksi.');
            }
        }];
    }

    /**
     * @return list<array{label: string, amount: int, provider: string, reference: string}>
     */
    public function channels(): array
    {
        /** @var array<int, array<string, mixed>> $channels */
        $channels = $this->input('channels', []);

        return array_values(array_map(
            function (array $channel): array {
                $submittedLabel = trim((string) ($channel['label'] ?? ''));
                $method = Str::before($submittedLabel, ' · ');
                $embeddedProvider = Str::contains($submittedLabel, ' · ')
                    ? Str::after($submittedLabel, ' · ')
                    : '';
                $provider = trim((string) ($channel['provider'] ?? $embeddedProvider));

                return [
                    'label' => $provider === '' ? $method : $method.' · '.$provider,
                    'amount' => (int) ($channel['amount'] ?? 0),
                    'provider' => $provider,
                    'reference' => trim((string) ($channel['reference'] ?? '')),
                ];
            },
            $channels,
        ));
    }

    public function transaction(): OrderTransaction
    {
        /** @var OrderTransaction $transaction */
        $transaction = $this->route('orderTransaction');

        return $transaction;
    }
}
