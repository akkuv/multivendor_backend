<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Shared `created_days` filter so a dashboard stat card and the list it links to
 * always describe the same set of rows.
 *
 * `created_days=30` means "created within the last 30 days, ending today", which
 * matches the dashboard period helper: `today()->subDays($days - 1)->startOfDay()`
 * through `today()->endOfDay()`.
 */
trait FiltersCreatedRange
{
    protected const MAX_CREATED_DAYS = 365;

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyCreatedDays(Builder $query, Request $request, string $column = 'created_at'): Builder
    {
        $days = min($request->integer('created_days', 0), self::MAX_CREATED_DAYS);

        if ($days < 1) {
            return $query;
        }

        $start = Carbon::today()->subDays($days - 1)->startOfDay();

        return $query->whereBetween($column, [$start, Carbon::today()->endOfDay()]);
    }

    /**
     * An explicit single-day range, used when drilling into a single point on a
     * dashboard chart. Takes precedence over `created_days` when both are present.
     */
    protected function applyCreatedRange(Builder $query, Request $request, string $column = 'created_at'): Builder
    {
        $from = $request->date('created_from');
        $to = $request->date('created_to');

        if ($from === null && $to === null) {
            return $this->applyCreatedDays($query, $request, $column);
        }

        // A lone bound means a single day, so mirror it rather than running the
        // range up to today. Carbon mutates in place, so clone before shifting:
        // otherwise both ends of the range share one instance and collapse.
        $start = ($from ?? $to)->clone()->startOfDay();
        $end = ($to ?? $from)->clone()->endOfDay();

        return $query->whereBetween($column, [$start, $end]);
    }
}
