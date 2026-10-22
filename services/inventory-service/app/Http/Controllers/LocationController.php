<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Location::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30',
                Rule::unique('locations', 'code')->where('tenant_id', app('tenant_id'))],
            'name' => ['required', 'string', 'max:100'],
        ]);

        return response()->json(['data' => Location::create($data)], 201);
    }

    public function update(Request $request, string $id)
    {
        $location = Location::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);


        $location->update($data);
        $location->increment('version');

        return response()->json(['data' => $location->fresh()]);
    }
}