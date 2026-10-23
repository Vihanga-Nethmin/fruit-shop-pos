<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\RetailPayment;
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
            'discount_amount' => 'nullable|numeric|min:0', // අලුත් - Discount එක
            'request_id' => 'required|string|unique:sales,request_id'
        ]);

        try {
            // Database Transaction එකක් පටන් ගන්නවා
            DB::beginTransaction();

            // 2. මුළු මුදල ගණනය කරන්න
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += ($item['quantity'] * $item['unit_price']);
            }

            // Discount එක අඩු කරලා අවසාන මුදල ගණනය කරන්න
            $finalAmount = $totalAmount - ($request->discount_amount ?? 0);

            // 3. අලුත් බිල (Sale) එක හදන්න
            $sale = Sale::create([
                'tenant_id' => $request->tenant_id,
                'user_id' => $request->user_id,
                'sale_number' => 'SALE-' . strtoupper(Str::random(8)),
                'status' => 'completed',
                'total_amount' => $finalAmount, // Discount එක අඩු කරපු මුදල
                'tax_amount' => 0,
                'discount_amount' => $request->discount_amount ?? 0,
                'request_id' => $request->request_id,
            ]);

            // 4. බිලේ තියෙන භාණ්ඩ ටික (Sale Lines) save කරන්න
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

            // 5. ගෙවීම (Payment) save කරන්න
            RetailPayment::create([
                'sale_id' => $sale->id,
                'method' => $request->payment_method,
                'amount' => $request->payment_amount,
                'status' => 'confirmed',
            ]);

            DB::commit();

            // සාර්ථක පණිවිඩයක් යවන්න
            return response()->json([
                'message' => 'Sale created successfully!',
                'sale' => $sale->load('lines', 'payments')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create sale', 'details' => $e->getMessage()], 500);
        }
    }

    // හැම බිලක්ම බලන්න (Get All Sales)
    public function index()
    {
        $sales = Sale::with(['lines', 'payments'])->latest()->get();

        return response()->json([
            'message' => 'Sales retrieved successfully',
            'data' => $sales
        ], 200);
    }

    // එක බිලක විස්තර විතරක් බලන්න (Get Single Sale)
    public function show($id)
    {
        $sale = Sale::with(['lines', 'payments'])->find($id);

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