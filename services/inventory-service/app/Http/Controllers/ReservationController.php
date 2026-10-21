<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationLine;
use App\Models\Product;
use App\Services\Availability;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    private const EXPIRY_MINUTES = 5;

    // Quantities integer thousandths walin (float error nathuwa)
    private function milli($v): int
    {
        return (int) round(((float) $v) * 1000);
    }

    private function fromMilli(int $m): string
    {
        return number_format($m / 1000, 3, '.', '');
    }

    private function present(string $id)
    {
        return Reservation::with('lines')->findOrFail($id);
    }

    // FEFO order: use-by, nathnam sell-by, ita passe receipt date. Dates nathi ewa passe (FIFO).
    private function candidates(string $productId, ?string $locationId): array
    {
        $sql = 'SELECT sp.id, sp.batch_id, sp.location_id,
                       sp.physical_qty - sp.reserved_qty AS avail,
                       b.unit_cost
                  FROM stock_positions sp
                  JOIN batches b ON b.id = sp.batch_id
                 WHERE sp.tenant_id = ?
                   AND sp.product_id = ?
                   AND sp.physical_qty - sp.reserved_qty > 0
                   AND ' . Availability::sellableSql('b') . "
                   AND b.ripeness = 'READY'";

        $bindings = array_merge([app('tenant_id'), $productId], Availability::bindings());

        if ($locationId) {
            $sql .= ' AND sp.location_id = ?';
            $bindings[] = $locationId;
        }

        $sql .= ' ORDER BY (COALESCE(b.use_by_date, b.sell_by_date) IS NULL),
                           COALESCE(b.use_by_date, b.sell_by_date),
                           b.received_at, sp.id
                  FOR UPDATE';

        return DB::select($sql, $bindings);
    }

    public function store(Request $request)
    {
        $tenant = app('tenant_id');

        $data = $request->validate([
            'request_id'  => ['required', 'string', 'max:100'],
            'location_id' => ['nullable', 'uuid',
                Rule::exists('locations', 'id')->where('tenant_id', $tenant)],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid',
                Rule::exists('products', 'id')->where('tenant_id', $tenant)->where('is_active', true)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
        ]);

        // Idempotency: same request_id nam kalin hadapu reservation eka denawa
        $existing = Reservation::where('request_id', $data['request_id'])->first();
        if ($existing) {
            return response()->json(['data' => $this->present($existing->id)], 200);
        }

        // EACH products walata whole number witharai
        foreach ($data['lines'] as $i => $line) {
            $product = Product::findOrFail($line['product_id']);
            if ($product->base_unit === 'EACH' && $this->milli($line['quantity']) % 1000 !== 0) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors'  => ["lines.$i.quantity" => ['EACH quantities must be whole numbers.']],
                ], 422);
            }
        }

        $shortfall = null;

        try {
            $id = DB::transaction(function () use ($data, $tenant, &$shortfall) {
                $reservation = Reservation::create([
                    'request_id' => $data['request_id'],
                    'status'     => 'ACTIVE',
                    'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
                ]);

                foreach ($data['lines'] as $line) {
                    $requested = $this->milli($line['quantity']);
                    $need      = $requested;
                    $rows      = $this->candidates($line['product_id'], $data['location_id'] ?? null);

                    $available = 0;
                    foreach ($rows as $r) {
                        $available += $this->milli($r->avail);
                    }

                    foreach ($rows as $r) {
                        if ($need <= 0) {
                            break;
                        }

                        $take = min($this->milli($r->avail), $need);
                        $qty  = $this->fromMilli($take);

                        // Guard: available athulata witharai reserve wenawa
                        $affected = DB::update(
                            'UPDATE stock_positions
                                SET reserved_qty = reserved_qty + ?, version = version + 1, updated_at = ?
                              WHERE id = ? AND tenant_id = ?
                                AND physical_qty - reserved_qty >= ?',
                            [$qty, now()->toDateTimeString(), $r->id, $tenant, $qty]
                        );

                        if ($affected === 0) {
                            continue;
                        }

                        ReservationLine::create([
                            'reservation_id' => $reservation->id,
                            'product_id'     => $line['product_id'],
                            'batch_id'       => $r->batch_id,
                            'location_id'    => $r->location_id,
                            'qty'            => $qty,
                            'unit_cost'      => $r->unit_cost,
                        ]);

                        $need -= $take;
                    }

                    if ($need > 0) {
                        $shortfall = [
                            'product_id' => $line['product_id'],
                            'requested'  => $this->fromMilli($requested),
                            'available'  => $this->fromMilli($available),
                        ];
                        throw new DomainException('INSUFFICIENT_STOCK'); // rollback: mukuth hold wenne nae
                    }
                }

                return $reservation->id;
            });
        } catch (DomainException $e) {
            return response()->json([
                'error' => [
                    'code'    => 'INSUFFICIENT_STOCK',
                    'message' => 'Not enough sellable stock.',
                    'details' => $shortfall,
                ],
            ], 409);
        } catch (UniqueConstraintViolationException $e) {
            // Same request_id ekata parallel requests 2k: eka win, anith eka eka thamai return karanne
            $existing = Reservation::where('request_id', $data['request_id'])->firstOrFail();
            return response()->json(['data' => $this->present($existing->id)], 200);
        }

        return response()->json(['data' => $this->present($id)], 201);
    }

    // Timeout unama Sales eka outcome eka balanna
    public function showByRequest(string $requestId)
    {
        $reservation = Reservation::where('request_id', $requestId)->firstOrFail();

        return response()->json(['data' => $this->present($reservation->id)]);
    }
}