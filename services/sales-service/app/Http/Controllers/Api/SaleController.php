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
            'request_id' => 'required|string|unique:sales,request_id' // දෙපාරක් ඔබන එක නවත්තන්න
        ]);

        try {
            // Database Transaction එකක් පටන් ගන්නවා (මොකක් හරි අවුලක් ආවොත් ඔක්කොම cancel වෙන්න)
            DB::beginTransaction();

            // 2. මුළු මුදල ගණනය කරන්න
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += ($item['quantity'] * $item['unit_price']);
            }

            // 3. අලුත් බිල (Sale) එක හදන්න
            $sale = Sale::create([
                'tenant_id' => $request->tenant_id,
                'user_id' => $request->user_id,
                'sale_number' => 'SALE-' . strtoupper(Str::random(8)), // අහඹු බිල්පත් අංකයක්
                'status' => 'completed', // දැනට අපි හිතමු ගෙවීම් ඉවරයි කියලා
                'total_amount' => $totalAmount,
                'tax_amount' => 0,
                'discount_amount' => 0,
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

            // ඔක්කොම හරි ගියා නම් Database එකට save කරන්න
            DB::commit();

            // සාර්ථක පණිවිඩයක් යවන්න
            return response()->json([
                'message' => 'Sale created successfully!',
                'sale' => $sale->load('lines', 'payments')
            ], 201);

        } catch (\Exception $e) {
            // මොකක් හරි අවුලක් ආවොත් ඔක්කොම ආපහු හරවන්න
            DB::rollBack();
            return response()->json(['error' => 'Failed to create sale', 'details' => $e->getMessage()], 500);
        }
    }
        // හැම බිලක්ම බලන්න (Get All Sales)
    public function index()
    {
        // හැම බිලක්ම, ඒවාට අදාළ භාණ්ඩ (lines) සහ ගෙවීම් (payments) එක්කම ගන්න
        $sales = Sale::with(['lines', 'payments'])->latest()->get();

        return response()->json([
            'message' => 'Sales retrieved successfully',
            'data' => $sales
        ], 200);
    }
        // එක බිලක විස්තර විතරක් බලන්න (Get Single Sale)
    public function show($id)
    {
        // අදාළ ID එකට අයිති බිල, ඒකේ භාණ්ඩ සහ ගෙවීම් එක්කම හොයන්න
        $sale = Sale::with(['lines', 'payments'])->find($id);

        // එහෙම බිලක් නැත්නම් 404 Error එකක් එවන්න
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