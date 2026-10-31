<?php

namespace App\Services;

class Availability
{
    // TEMP: passe tenant timezone setting eken ganna (default Asia/Colombo)
    public static function today(): string
    {
        return now('Asia/Colombo')->toDateString();
    }

    // Batch eka sell karanna puluwan nam true wenna one condition eka ($b = batches alias)
    // Past sell-by batch eka sellable wenne owner approval (sell_by_approved_until) iwara wela nathnam witharai.
    // Past use-by stock kisima welawaka sellable nae.
    // COALESCE: NULL wenne nathi wenna (NOT (...) check walata wadagath)
    public static function sellableSql(string $b = 'b'): string
    {
        return "$b.quality_state = 'SALEABLE'
                AND ($b.use_by_date IS NULL OR $b.use_by_date >= ?)
                AND ($b.sell_by_date IS NULL OR $b.sell_by_date >= ?
                     OR COALESCE($b.sell_by_approved_until >= ?, 0))";
    }

    // sellableSql() ekata adala bindings (placeholders 3k)
    public static function bindings(): array
    {
        $d = self::today();
        return [$d, $d, $d];
    }
}