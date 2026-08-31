<?php

namespace Boi\Backend\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves which catalog rows are required for an application given business type and loan amount.
 *
 * Expects {@see $filesQuery} to target a table with boolean columns:
 * {@code required}, {@code enterprise}, {@code ltd}, {@code above_10m}
 * (typical BOI loan application document catalog). The flags are audience
 * selectors:
 *   - {@code enterprise}: applies to enterprise-type applicants;
 *   - {@code ltd}:        applies to Ltd applicants at or below ₦10m;
 *   - {@code above_10m}:  applies to any applicant above ₦10m.
 * A universal document sets all three; amount-swapped variants (e.g. the
 * Means-of-ID documents) set complementary flags so exactly one variant
 * surfaces per scenario. The {@code required} column is NOT a selector —
 * every surfaced row is mandatory for its scenario, so it is normalized to
 * true in the result. (Filtering on {@code required} before the flags was
 * the bug that hid type-/amount-specific documents entirely.)
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

        if (! $isEnterprise && ! $isLtd) {
            return [];
        }

        $files = $filesQuery
            ->where(function ($query) use ($isEnterprise, $isLtd, $isAbove10m) {
                if ($isEnterprise) {
                    $query->orWhere('enterprise', true);
                }
                if ($isLtd) {
                    // Above ₦10m the amount flag governs (amount-specific docs,
                    // ltd-only docs marked above_10m included); at or below it
                    // the ltd flag does.
                    $query->orWhere($isAbove10m ? 'above_10m' : 'ltd', true);
                }
            })
            ->get(['id', 'name', 'required', 'template', 'enterprise', 'ltd', 'above_10m'])
            ->toArray();

        return array_map(static function (array $file): array {
            $file['required'] = true;

            return $file;
        }, $files);
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
