<?php

declare(strict_types=1);

namespace App\Traits\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait MatchesProviderIdentifiers
{
    /** @param list<string> $ids */
    private function providerIdentifiers(string $table, string $column, array $ids): Builder
    {
        // Inherit the identity column's collation for both existing and newly submitted aliases.
        $input = DB::table($table)->select($column . ' as lookup_id')->selectRaw('0 as position')->whereRaw('1 = 0');

        foreach ($ids as $position => $id) {
            $input->unionAll(DB::query()->selectRaw('? as lookup_id, ? as position', [$id, $position]));
        }

        return DB::query()->fromSub($input, 'submitted')->select('lookup_id')
            ->selectRaw('first_value(lookup_id) over (partition by lookup_id order by position) as canonical_id');
    }
}
