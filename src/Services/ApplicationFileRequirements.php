<?php

namespace Boi\Backend\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves which catalog rows are required for an application given business type and loan amount.
 *
 * Expects {@see $filesQuery} to target a table with boolean columns:
 * {@code required}, {@code enterprise}, {@code ltd}, {@code above_10m}
 * (typical BOI loan application document catalog).
 */
final class ApplicationFileRequirements
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $filesQuery
     * @return list<array<string, mixed>>
     */
    public static function getRequiredFiles(Builder $filesQuery, string $businessType, mixed $loanAmount = null): array
    {
        $amount = self::normalizeLoanAmount($loanAmount);
        $isEnterprise = in_array($businessType, ['sole_proprietorship', 'partnership', 'enterprise', 'cooperative_society'], true);
        $isLtd = $businessType === 'ltd';
        $isAbove10m = $amount !== null && $amount > 10_000_000;

        return $filesQuery
            ->where('required', true)
            ->where(function ($query) use ($isEnterprise, $isLtd, $isAbove10m) {
                if ($isEnterprise) {
                    $query->orWhere('enterprise', true);
                }
                if ($isLtd) {
                    if ($isAbove10m) {
                        $query->orWhere(function ($q) {
                            $q->where('ltd', true);
                            $q->where('above_10m', true);
                        });
                        $query->orWhere(function ($q) {
                            $q->where('ltd', false);
                            $q->where('above_10m', true);
                        });
                    } else {
                        $query->orWhere(function ($q) {
                            $q->where('ltd', true);
                            $q->where('above_10m', true);
                        });
                        $query->orWhere(function ($q) {
                            $q->where('ltd', true);
                            $q->where('above_10m', false);
                        });
                    }
                }
            })
            ->get(['id', 'name', 'required', 'template', 'enterprise', 'ltd', 'above_10m'])
            ->toArray();
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $filesQuery
     * @return list<int|string>
     */
    public static function getRequiredFileIds(Builder $filesQuery, string $businessType, mixed $loanAmount = null): array
    {
        return array_column(self::getRequiredFiles($filesQuery, $businessType, $loanAmount), 'id');
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $filesQuery
     * @return list<string>
     */
    public static function getRequiredFileNames(Builder $filesQuery, string $businessType, mixed $loanAmount = null): array
    {
        return array_column(self::getRequiredFiles($filesQuery, $businessType, $loanAmount), 'name');
    }

    private static function normalizeLoanAmount(mixed $loanAmount): ?float
    {
        if ($loanAmount === null || $loanAmount === '') {
            return null;
        }
        if (is_numeric($loanAmount)) {
            return (float) $loanAmount;
        }

        return null;
    }
}
