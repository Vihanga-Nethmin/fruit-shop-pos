cd<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\ReturnLine;
use App\Models\RefundRecord;
use App\Models\OutboxEvent; // අලුතෙන් Import කරන්න
use Illuminate\Support\Facades\DB;

class SaleReturnController extends Controller
{
    // භාණ්ඩ ආපහු ගන්නා ක්‍රියාවලිය (Return & Refund)
    public function store(Request $request, $saleId)
    {
        // 1. දත්ත නිවැරදිද කියලා බලන්න (Validation)
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.sale_line_id' => 'required|exists:sale_lines,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.condition' => 'required|string|in:GOOD,SPOILED,DAMAGED', // අලුත් - තත්ත්වය
            'payment_method' => 'required|string|in:cash,card',
            'reason' => 'nullable|string'
        ]);

        // 2. අදාළ බිල තියෙනවද බලන්න
        $sale = Sale::with('lines')->find($saleId);
        if (!$sale) {
            return response()->json(['message' => 'Sale not found'], 404);
        }

        try {
            DB::beginTransaction();

            $totalRefundAmount = 0;
            $returnLinesData = [];

            // 3. ආපහු ගන්නා භාණ්ඩ ගණනය කරන්න සහ සත්‍යාපනය කරන්න
            foreach ($request->items as $item) {
                // අදාළ භාණ්ඩය අර මුල් බිලේ තියෙනවද බලන්න
                $saleLine = $sale->lines->where('id', $item['sale_line_id'])->first();
                
                if (!$saleLine) {
                    throw new \Exception("Sale line not found in this sale.");
                }

                // ඉල්ලන ප්‍රමාණය මුල් බිලේ තියෙන ප්‍රමාණයට වඩා වැඩිද බලන්න
                if ($item['quantity'] > $saleLine->quantity) {
                    throw new \Exception("Return quantity cannot exceed original sale quantity.");
                }

                // එක භාණ්ඩයකට අදාළ මුදල (quantity x unit price)
                $lineRefundAmount = $item['quantity'] * $saleLine->unit_price;
                $totalRefundAmount += $lineRefundAmount;

                $returnLinesData[] = [
                    'sale_line_id' => $item['sale_line_id'],
                    'quantity' => $item['quantity'],
                    'refund_amount' => $lineRefundAmount,
                    'condition' => $item['condition'], // අලුත් - තත්ත්වය
                    'product_id' => $saleLine->product_id, // Event එකට ඕන නිසා මෙතන ගන්නවා
                ];
            }

            // 4. අලුත් Return එකක් හදන්න
            $saleReturn = SaleReturn::create([
                'sale_id' => $sale->id,
                'status' => 'posted',
                'total_refund_amount' => $totalRefundAmount,
                'reason' => $request->reason,
            ]);

            // 5. Return Lines ටික Save කරන්න
            foreach ($returnLinesData as $lineData) {
                $returnData = [
                    'sale_return_id' => $saleReturn->id,
                    'sale_line_id' => $lineData['sale_line_id'],
                    'quantity' => $lineData['quantity'],
                    'refund_amount' => $lineData['refund_amount'],
                    'condition' => $lineData['condition'],
                ];
                ReturnLine::create($returnData);
            }

            // 6. Refund එක Save කරන්න
            RefundRecord::create([
                'sale_return_id' => $saleReturn->id,
                'method' => $request->payment_method,
                'amount' => $totalRefundAmount,
                'confirmed' => true,
            ]);

            DB::commit();

            // 7. අලුතෙන් - Inventory Service එකට Events යවන්න (Outbox Pattern)
            foreach ($returnLinesData as $lineData) {
                $eventType = ($lineData['condition'] === 'GOOD') ? 'sale.return.good' : 'sale.return.spoiled';

                OutboxEvent::create([
                    'event_type' => $eventType,
                    'payload' => json_encode([
                        'return_id' => $saleReturn->id,
                        'sale_id' => $sale->id,
                        'product_id' => $lineData['product_id'],
                        'quantity' => $lineData['quantity'],
                        'condition' => $lineData['condition'],
                        'timestamp' => now()->toIso8601String()
                    ])
                ]);
            }

            return response()->json([
                'message' => 'Sale return and refund processed successfully!',
                'data' => $saleReturn->load('lines', 'refund')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to process return', 'details' => $e->getMessage()], 400);
        }
    }
}