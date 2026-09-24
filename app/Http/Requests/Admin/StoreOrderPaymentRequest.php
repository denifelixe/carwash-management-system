<?php

namespace App\Http\Requests\Admin;

use App\Models\AdminShift;
use App\Models\Member;
use App\Models\Order;
use App\Models\Reward;
use App\Support\Admin\MemberStamps;
use App\Support\Admin\OrderQueries;
use App\Support\Admin\PaymentChannelBreakdown;
use App\Support\Admin\RewardRedemptionRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreOrderPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.pos.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'intent' => ['required', Rule::in(['settlement', 'partial'])],
            /* The cashier's own discount. A reward's discount is worked out here, never posted. */
            'discount' => ['required', 'integer', 'min:0'],
            'reward_id' => ['nullable', 'integer', Rule::exists(Reward::class, 'id')],
            'amount' => ['required', 'integer', 'min:0'],
            'channels' => ['present', 'array', 'max:'.count(OrderQueries::PAYMENT_METHODS)],
            'channels.*.method' => ['required', 'distinct', Rule::in(OrderQueries::PAYMENT_METHODS)],
            'channels.*.amount' => ['required', 'integer', 'min:1'],
            'channels.*.provider' => ['nullable', 'string', 'max:60'],
            'channels.*.reference' => ['nullable', 'string', 'max:60'],
            /* Printed on this payment's slip under LUNAS. */
            'note' => ['nullable', 'string', 'max:255'],
            'transaction_shift_id' => [
                'nullable',
                'integer',
                Rule::exists(AdminShift::class, 'id')->where('is_active', true),
            ],
        ];
    }

    /**
     * The bill is the only thing that decides how much may be taken, so the
     * amounts are checked against the order rather than trusted from the form.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $order = $this->order();
            $due = max((int) $order->total - (int) $order->paid_amount, 0);
            $discount = $this->integer('discount');
            $amount = $this->integer('amount');
            $channels = $this->channelInput();
            $tendered = PaymentChannelBreakdown::tenderedTotal($channels);

            foreach ($channels as $index => $channel) {
                if ($channel['method'] === 'Debit' && $channel['provider'] === '') {
                    $validator->errors()->add(
                        "channels.{$index}.provider",
                        'Bank wajib dipilih untuk pembayaran debit.',
                    );
                }
            }

            if ($order->status === 'selesai') {
                $validator->errors()->add('amount', 'Order ini sudah lunas dan tidak bisa dibayar lagi.');

                return;
            }

            if ($order->status === 'batal') {
                $validator->errors()->add('amount', 'Order yang dibatalkan tidak bisa dibayar.');

                return;
            }

            $reward = $this->reward();
            $rewardDiscount = 0;

            if ($reward instanceof Reward) {
                $order->loadMissing('serviceVariations');
                $balance = $order->member instanceof Member ? MemberStamps::balance($order->member) : 0;
                $refusal = RewardRedemptionRules::refusal($reward, $order, $balance);

                if ($refusal !== null) {
                    $validator->errors()->add('reward_id', $refusal);

                    return;
                }

                $rewardDiscount = min(RewardRedemptionRules::discountFor($reward, $order), $due);
            }

            $due -= $rewardDiscount;

            if ($discount > $due) {
                $validator->errors()->add('discount', 'Diskon melebihi sisa tagihan order.');
            }

            if ($amount > $due - min($discount, $due)) {
                $validator->errors()->add('amount', 'Pembayaran melebihi sisa tagihan order.');
            }

            if ($amount === 0 && $discount === 0 && $rewardDiscount === 0) {
                $validator->errors()->add('amount', 'Masukkan jumlah pembayaran.');
            }

            if ($tendered < $amount) {
                $validator->errors()->add('channels', 'Uang yang diterima kurang dari jumlah pembayaran.');
            }

            if (PaymentChannelBreakdown::change($channels, $amount)
                > PaymentChannelBreakdown::cashTendered($channels)) {
                $validator->errors()->add('channels', 'Kembalian hanya dapat diberikan dari pembayaran tunai.');
            }

            if ($amount > 0 && $tendered === 0) {
                $validator->errors()->add('channels', 'Pilih minimal satu metode pembayaran.');
            }
        }];
    }

    /**
     * The payment channels as submitted, normalised so an omitted provider or
     * reference reads as an empty string rather than a missing key.
     *
     * @return list<array{method: string, amount: int, provider: string, reference: string}>
     */
    public function channelInput(): array
    {
        /** @var array<int, array<string, mixed>> $channels */
        $channels = $this->input('channels', []);

        return array_values(array_map(
            fn (array $channel): array => [
                'method' => (string) ($channel['method'] ?? ''),
                'amount' => (int) ($channel['amount'] ?? 0),
                'provider' => trim((string) ($channel['provider'] ?? '')),
                'reference' => trim((string) ($channel['reference'] ?? '')),
            ],
            $channels,
        ));
    }

    public function order(): Order
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $order;
    }

    public function reward(): ?Reward
    {
        $rewardId = $this->integer('reward_id');

        return $rewardId > 0 ? Reward::query()->with('serviceVariations:id,service_id')->find($rewardId) : null;
    }

    /**
     * @return array{intent: string, discount: int, reward_id: int|null, amount: int, channels: list<array{method: string, amount: int, provider: string, reference: string}>, transaction_shift_id: int|null, note: string|null}
     */
    public function payment(): array
    {
        return [
            'intent' => (string) $this->validated('intent'),
            'discount' => $this->integer('discount'),
            'reward_id' => $this->integer('reward_id') ?: null,
            'amount' => $this->integer('amount'),
            'channels' => $this->channelInput(),
            'transaction_shift_id' => $this->integer('transaction_shift_id') ?: null,
            'note' => Str::squish((string) $this->validated('note')) ?: null,
        ];
    }
}
