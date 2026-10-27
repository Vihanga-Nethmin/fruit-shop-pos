<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\RetailPayment;
use App\Models\Customer;
use App\Models\OutboxEvent; // අලුතෙන් Import කරන්න
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleController extends Controller
{
    // අලුත් බිලක් හදන function එක
    public function store(Request $request)
    {
        // 1. දත්ත නිවැරදිද කියලා බලන්න (Validation)
        $request->validate([
            'tenant_id' => 'required|uuid',
            'user_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|uuid',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'payment_method' => 'required|string|in:cash,card',
            'payment_amount' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'request_id' => 'required|string|unique:sales,request_id'
        ]);

        try {
            DB::beginTransaction();

            // 2. මුළු මුදල ගණනය කරන්න
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += ($item['quantity'] * $item['unit_price']);
            }

            $finalAmount = $totalAmount - ($request->discount_amount ?? 0);

            // 3. ගැනුම්කරුගේ විස්තර තියෙනවා නම් ඒක Save කරන්න
            $customerId = null;
            if ($request->customer_name || $request->customer_phone) {
                $customer = Customer::create([
                    'tenant_id' => $request->tenant_id,
                    'name' => $request->customer_name ?? 'Walk-in Customer',
                    'phone' => $request->customer_phone,
                ]);
                $customerId = $customer->id;
            }

            // 4. අලුත් බිල (Sale) එක හදන්න
            $sale = Sale::create([
                'tenant_id' => $request->tenant_id,
                'user_id' => $request->user_id,
                'customer_id' => $customerId,
                'sale_number' => 'SALE-' . strtoupper(Str::random(8)),
                'status' => 'completed',
                'total_amount' => $finalAmount,
                'tax_amount' => 0,
                'discount_amount' => $request->discount_amount ?? 0,
                'request_id' => $request->request_id,
            ]);

            // 5. බිලේ තියෙන භාණ්ඩ ටික (Sale Lines) save කරන්න
            foreach ($request->items as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                
                SaleLine::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $lineTotal,
                ]);
            }

            // 6. ගෙවීම (Payment) save කරන්න
            RetailPayment::create([
                'sale_id' => $sale->id,
                'method' => $request->payment_method,
                'amount' => $request->payment_amount,
                'status' => 'confirmed',
            ]);

            DB::commit();

            // 7. Event එක සඳහා Sale එකේ lines ටික load කරගන්න
            $sale->load('lines');

            // 8. අලුතෙන් - Event එක Database එකේ Save කරන්න (Outbox Pattern)
            OutboxEvent::create([
                'event_type' => 'sale.completed',
                'payload' => json_encode([ // json_encode අනිවාර්යයි
                    'sale_id' => $sale->id,
                    'tenant_id' => $sale->tenant_id,
                    'user_id' => $sale->user_id,
                    'total_amount' => $sale->total_amount,
                    'items' => $sale->lines->map(function($line) {
                        return [
                            'product_id' => $line->product_id,
                            'quantity' => $line->quantity
                        ];
                    })->toArray(),
                    'timestamp' => now()->toIso8601String()
                ])
            ]);

            // සාර්ථක පණිවිඩයක් යවන්න
            return response()->json([
                'message' => 'Sale created successfully!',
                'sale' => $sale->load('lines', 'payments', 'customer')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create sale', 'details' => $e->getMessage()], 500);
        }
    }

    // හැම බිලක්ම බලන්න (Get All Sales)
    public function index()
    {
        $sales = Sale::with(['lines', 'payments', 'customer'])->latest()->get();

        return response()->json([
            'message' => 'Sales retrieved successfully',
            'data' => $sales
        ], 200);
    }

    // එක බිලක විස්තර විතරක් බලන්න (Get Single Sale)
    public function show($id)
    {
        $sale = Sale::with(['lines', 'payments', 'customer'])->find($id);

        if (!$sale) {
            return response()->json([
                'message' => 'Sale not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Sale retrieved successfully',
            'data' => $sale
        ], 200);
    }
}