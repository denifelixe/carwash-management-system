<?php

namespace App\Support\Admin;

use App\Models\CashEntry;
use App\Models\OrderTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use stdClass;

class FinanceReportQueries
{
    public const PER_PAGE = 25;

    public static function direction(string $direction): string
    {
        return in_array($direction, ['in', 'out'], true) ? $direction : 'all';
    }

    /** @return array{moneyIn: int, moneyOut: int, net: int, transactions: int} */
    public static function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = self::query($from, $to)
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END) AS money_in")
            ->selectRaw("SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END) AS money_out")
            ->selectRaw('COUNT(*) AS transactions')
            ->first();

        return [
            'moneyIn' => (int) $totals->money_in,
            'moneyOut' => (int) $totals->money_out,
            'net' => (int) $totals->money_in - (int) $totals->money_out,
            'transactions' => (int) $totals->transactions,
        ];
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int|null>} */
    public static function log(CarbonImmutable $from, CarbonImmutable $to, string $direction = 'all', int $page = 1): array
    {
        $paginator = self::orderedQuery($from, $to, $direction)
            ->paginate(perPage: self::PER_PAGE, page: max(1, $page));
        $paginator->setCollection(self::present($paginator->getCollection()));

        return Paginated::fromPaginator($paginator, fn (array $row): array => $row);
    }

    /** @return LazyCollection<int, array<string, mixed>> */
    public static function rows(CarbonImmutable $from, CarbonImmutable $to, string $direction = 'all'): LazyCollection
    {
        return new LazyCollection(function () use ($from, $to, $direction) {
            foreach (self::orderedQuery($from, $to, $direction)->lazy(500)->chunk(500) as $chunk) {
                yield from self::present(collect($chunk->all()));
            }
        });
    }

    private static function orderedQuery(CarbonImmutable $from, CarbonImmutable $to, string $direction): Builder
    {
        $query = self::query($from, $to);

        if (self::direction($direction) !== 'all') {
            $query->where('direction', self::direction($direction));
        }

        return $query->orderByDesc('ledger_date')->orderByDesc('occurred_at')->orderByDesc('source')->orderByDesc('id');
    }

    /** Union only identifiers and totals; hydrate just the requested page or export chunk. */
    private static function query(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $payments = OrderTransaction::query()
            ->selectRaw("id, 'pos' AS source, 'in' AS direction, amount, DATE(paid_at) AS ledger_date, paid_at AS occurred_at")
            ->where('paid_at', '>=', $from->startOfDay())
            ->where('paid_at', '<', $to->startOfDay()->addDay())
            ->where('amount', '>', 0)
            ->toBase();
        $entries = CashEntry::query()
            ->selectRaw("id, 'manual' AS source, direction, amount, DATE(entry_date) AS ledger_date, occurred_at")
            ->where('entry_date', '>=', $from->toDateString())
            ->where('entry_date', '<', $to->addDay()->toDateString())
            ->toBase();

        return DB::query()->fromSub($payments->unionAll($entries), 'ledger');
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return Collection<int, non-empty-array<string, mixed>>
     */
    private static function present(Collection $rows): Collection
    {
        $payments = OrderTransaction::query()
            ->with(['order.serviceVariations:id,service_id', 'recordedBy:id,name', 'updatedBy:id,name'])
            ->whereIn('id', $rows->where('source', 'pos')->pluck('id'))->get()->keyBy('id');
        $entries = CashEntry::query()
            ->with(['recordedBy:id,name', 'updatedBy:id,name', 'attachments'])
            ->whereIn('id', $rows->where('source', 'manual')->pluck('id'))->get()->keyBy('id');

        return $rows->map(function (stdClass $row) use ($payments, $entries): array {
            $entry = $row->source === 'pos'
                ? FinancePresenter::posMoneyIn($payments->get($row->id))
                : FinancePresenter::cashEntry($entries->get($row->id));

            return [...$entry, 'direction' => $row->direction];
        });
    }
}
