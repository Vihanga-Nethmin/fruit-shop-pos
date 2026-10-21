<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Supplier::orderBy('name')->get()]);
    }

    public function show(string $id)
    {
        return response()->json(['data' => Supplier::findOrFail($id)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_code' => ['required', 'string', 'max:30',
                Rule::unique('suppliers', 'supplier_code')->where('tenant_id', app('tenant_id'))],
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json(['data' => Supplier::create($data)], 201);
    }

    public function update(Request $request, string $id)
    {
        $supplier = Supplier::findOrFail($id);

        // supplier_code meke naha: wenas karanna denne nae
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $supplier->update($data);
        $supplier->increment('version');

        return response()->json(['data' => $supplier->fresh()]);
    }
}