<?php

namespace App\Services;

use App\Models\StockMovement;
use App\Models\StockPosition;
use DomainException;
use Illuminate\Support\Facades\DB;

class StockService
{
    
    public function post(array $m): StockMovement
    {
        return DB::transaction(function () use ($m) {
            $position = StockPosition::where('batch_id', $m['batch_id'])
                ->where('location_id', $m['location_id'])
                ->lockForUpdate()
                ->first();

            if (!$position) {
                $position = StockPosition::create([
                    'product_id'   => $m['product_id'],
                    'batch_id'     => $m['batch_id'],
                    'location_id'  => $m['location_id'],
                    'physical_qty' => 0,
                    'reserved_qty' => 0,
                ]);
            }

            $qty = (string) $m['qty'];

          
            $affected = DB::update(
                'UPDATE stock_positions
                    SET physical_qty = physical_qty + ?, version = version + 1, updated_at = ?
                  WHERE id = ? AND tenant_id = ?
                    AND physical_qty + ? >= 0
                    AND physical_qty + ? >= reserved_qty',
                [$qty, now()->toDateTimeString(), $position->id, app('tenant_id'), $qty, $qty]
            );

            if ($affected === 0) {
                throw new DomainException('INSUFFICIENT_STOCK');
            }

            return StockMovement::create([
                'product_id'     => $m['product_id'],
                'batch_id'       => $m['batch_id'],
                'location_id'    => $m['location_id'],
                'signed_qty'     => $qty,
                'type'           => $m['type'],
                'source_type'    => $m['source_type'] ?? null,
                'source_id'      => $m['source_id'] ?? null,
                'source_line_id' => $m['source_line_id'] ?? null,
                'reason'         => $m['reason'] ?? null,
                'actor_id'       => $m['actor_id'],
                'approved_by'    => $m['approved_by'] ?? null,
                'unit_cost'      => $m['unit_cost'],
                'occurred_at'    => $m['occurred_at'] ?? now(),
            ]);
        });
    }
}