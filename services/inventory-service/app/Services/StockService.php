<?php

namespace App\Services;

use App\Exceptions\StockLockedException;
use App\Models\OutboxEvent;
use App\Models\StockMovement;
use App\Models\StockPosition;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockService
{
    /**
     * Movement ekak post karanawa, position eka saha outbox event eka ekama transaction ekak athule.
     * $m['qty'] signed string (+ wadi, - adu). Float use karanne nae.
     * $m['count_id'] dunnoth (stock count eke adjustment), eke lock eka bypass wenawa.
     */
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

            // Stock count ekakin lock wela nam block (count eka thamai bypass karanne)
            if ($position->locked_by_count_id && $position->locked_by_count_id !== ($m['count_id'] ?? null)) {
                throw new StockLockedException();
            }

            $qty = (string) $m['qty'];

            // Arithmetic eka MySQL eke (DECIMAL). Negative wenna bari, reserved athulata adu wenna bari.
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

            $movement = StockMovement::create([
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

            // Outbox event eka movement ekka ekama transaction ekak athule (movement rollback unoth event eth nae)
            OutboxEvent::create([
                'event_type'     => 'inventory.movement_posted',
                'schema_version' => 1,
                'aggregate_id'   => $movement->id,
                'correlation_id' => request()->header('X-Correlation-Id') ?: (string) Str::uuid(),
                'occurred_at'    => $movement->occurred_at,
                'created_at'     => now(),
                'payload'        => [
                    'movement_id'    => $movement->id,
                    'product_id'     => $movement->product_id,
                    'batch_id'       => $movement->batch_id,
                    'location_id'    => $movement->location_id,
                    'signed_qty'     => (string) $movement->signed_qty,
                    'type'           => $movement->type,
                    'unit_cost'      => (string) $movement->unit_cost,
                    'source_type'    => $movement->source_type,
                    'source_id'      => $movement->source_id,
                    'source_line_id' => $movement->source_line_id,
                ],
            ]);

            return $movement;
        });
    }
}