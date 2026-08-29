<?php

namespace App\Support\Admin;

use Closure;
use Illuminate\Pagination\LengthAwarePaginator;

class Paginated
{
    /**
     * @template TModel
     * @template TRow
     *
     * @param  LengthAwarePaginator<int, TModel>  $page
     * @param  Closure(TModel): TRow  $map
     * @return array{data: list<TRow>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int, from: int|null, to: int|null}}
     */
    public static function fromPaginator(LengthAwarePaginator $page, Closure $map): array
    {
        return [
            'data' => $page->getCollection()->map($map)->values()->all(),
            'meta' => self::meta(
                $page->currentPage(),
                $page->lastPage(),
                $page->perPage(),
                $page->total(),
                $page->firstItem(),
                $page->lastItem(),
            ),
        ];
    }

    /**
     * @template TRow
     *
     * @param  list<TRow>  $rows
     * @return array{data: list<TRow>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int, from: int|null, to: int|null}}
     */
    public static function fromArray(array $rows, int $page, int $perPage): array
    {
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $page), $lastPage);
        $offset = ($currentPage - 1) * $perPage;
        $data = array_values(array_slice($rows, $offset, $perPage));

        return [
            'data' => $data,
            'meta' => self::meta(
                $currentPage,
                $lastPage,
                $perPage,
                $total,
                $total === 0 ? null : $offset + 1,
                $total === 0 ? null : $offset + count($data),
            ),
        ];
    }

    /**
     * @return array{currentPage: int, lastPage: int, perPage: int, total: int, from: int|null, to: int|null}
     */
    private static function meta(
        int $currentPage,
        int $lastPage,
        int $perPage,
        int $total,
        ?int $from,
        ?int $to,
    ): array {
        return compact('currentPage', 'lastPage', 'perPage', 'total', 'from', 'to');
    }
}
