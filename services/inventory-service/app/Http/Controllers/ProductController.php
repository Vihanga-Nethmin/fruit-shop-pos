<?php

namespace App\Http\Controllers;

use App\Models\PriceHistory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    private const TEMP_USER = '00000000-0000-0000-0000-000000000000'; // TEMP: gateway token eken replace

    public function index()
    {
        return response()->json([
            'data' => Product::orderBy('name')->paginate(25),
        ]);
    }

    public function show(string $id)
    {
        return response()->json(['data' => Product::findOrFail($id)]);
    }

    public function store(Request $request)
    {
        $tenant = app('tenant_id');

        $data = $request->validate([
            'sku'  => ['required', 'string', 'max:50',
                Rule::unique('products', 'sku')->where('tenant_id', $tenant)],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'uuid',
                Rule::exists('categories', 'id')->where('tenant_id', $tenant)],
            'variety' => ['nullable', 'string', 'max:100'],
            'grade'   => ['nullable', 'string', 'max:20'],
            'base_unit' => ['required', Rule::in(['KG', 'EACH'])],
            'sale_unit' => ['required', 'string', 'max:20'],
            'selling_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'reorder_target' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'plu' => ['nullable', 'string', 'max:30',
                Rule::unique('products', 'plu')->where('tenant_id', $tenant)],
            'description' => ['nullable', 'string'],
            'origin' => ['nullable', 'string', 'max:100'],
            'default_supplier_id' => ['nullable', 'uuid',
                Rule::exists('suppliers', 'id')->where('tenant_id', $tenant)],
            'storage_guidance' => ['nullable', 'string'],
            'reorder_lead_time_days' => ['nullable', 'integer', 'min:0'],
            'shelf_life_days' => ['nullable', 'integer', 'min:0'],
        ]);

        // EACH nam thresholds whole numbers witharai
        if ($data['base_unit'] === 'EACH') {
            foreach (['low_stock_threshold', 'reorder_target'] as $f) {
                if (isset($data[$f]) && floor($data[$f]) != $data[$f]) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors'  => [$f => ['EACH quantities must be whole numbers.']],
                    ], 422);
                }
            }
        }

        $data['batch_tracked'] = true; // SRS: all fruits batch tracked

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create($data);

            PriceHistory::create([
                'product_id'   => $product->id,
                'old_price'    => null,
                'new_price'    => $product->selling_price,
                'effective_at' => now(),
                'changed_by'   => $request->header('X-User-Id', self::TEMP_USER),
                'reason'       => 'Initial price',
            ]);

            return $product;
        });

        return response()->json(['data' => $product->fresh()], 201);
    }

    public function update(Request $request, string $id)
    {
        $tenant  = app('tenant_id');
        $product = Product::findOrFail($id);

        // base_unit meke naha: unit wenas karanna bari (SRS FR-04)
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'category_id' => ['sometimes', 'uuid',
                Rule::exists('categories', 'id')->where('tenant_id', $tenant)],
            'variety' => ['nullable', 'string', 'max:100'],
            'grade'   => ['nullable', 'string', 'max:20'],
            'sale_unit' => ['sometimes', 'string', 'max:20'],
            'selling_price' => ['sometimes', 'numeric', 'min:0', 'decimal:0,2'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'low_stock_threshold' => ['sometimes', 'numeric', 'min:0', 'decimal:0,3'],
            'reorder_target' => ['sometimes', 'numeric', 'min:0', 'decimal:0,3'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => ['required_with:selling_price', 'string', 'max:255'],
        ]);

        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        DB::transaction(function () use ($product, $data, $reason, $request) {
            $oldPrice = (string) $product->selling_price;
            $product->update($data);

            if (isset($data['selling_price'])) {
                $newPrice = number_format((float) $data['selling_price'], 2, '.', '');
                if ($newPrice !== $oldPrice) {
                    PriceHistory::create([
                        'product_id'   => $product->id,
                        'old_price'    => $oldPrice,
                        'new_price'    => $newPrice,
                        'effective_at' => now(),
                        'changed_by'   => $request->header('X-User-Id', self::TEMP_USER),
                        'reason'       => $reason,
                    ]);
                }
            }

            $product->increment('version');
        });

        return response()->json(['data' => $product->fresh()]);
    }
}