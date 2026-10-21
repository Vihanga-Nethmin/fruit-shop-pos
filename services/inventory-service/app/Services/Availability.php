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
    public static function sellableSql(string $b = 'b'): string
    {
        return "$b.quality_state = 'SALEABLE'
                AND ($b.use_by_date IS NULL OR $b.use_by_date >= ?)
                AND ($b.sell_by_date IS NULL OR $b.sell_by_date >= ?)";
    }

    // sellableSql() ekata adala bindings
    public static function bindings(): array
    {
        $d = self::today();
        return [$d, $d];
    }
}