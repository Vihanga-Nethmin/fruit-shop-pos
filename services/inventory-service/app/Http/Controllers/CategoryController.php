<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('categories', 'name')->where('tenant_id', app('tenant_id')),
            ],
        ]);

        $category = Category::create($data);

        return response()->json(['data' => $category], 201);
    }

    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id); 

        $data = $request->validate([
            'name' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('categories', 'name')
                    ->where('tenant_id', app('tenant_id'))
                    ->ignore($category->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->update($data);
        $category->increment('version');

        return response()->json(['data' => $category->fresh()]);
    }
}