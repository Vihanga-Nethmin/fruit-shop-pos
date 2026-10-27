<?php

namespace App\Exceptions;

use RuntimeException;

class StockLockedException extends RuntimeException
{
    public function render($request)
    {
        return response()->json([
            'error' => [
                'code'    => 'STOCK_COUNT_LOCKED',
                'message' => 'This stock is locked by a stock count in progress.',
            ],
        ], 409);
    }
}