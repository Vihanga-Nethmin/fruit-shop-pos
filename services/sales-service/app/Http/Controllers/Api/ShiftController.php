<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shift;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    // අලුත් ෂිෆ්ට් එකක් පටන් ගන්න
    public function open(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'opening_float' => 'required|numeric|min:0',
        ]);

        // මේ කැෂියර්ට දැනටමත් open ෂිෆ්ට් එකක් තියෙනවද බලන්න
        $existingShift = Shift::where('user_id', $request->user_id)
                               ->where('status', 'open')
                               ->first();

        if ($existingShift) {
            return response()->json(['message' => 'You already have an open shift.'], 400);
        }

        $shift = Shift::create([
            'user_id' => $request->user_id,
            'opening_float' => $request->opening_float,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        return response()->json([
            'message' => 'Shift opened successfully!',
            'shift' => $shift
        ], 201);
    }

    // ෂිෆ්ට් එක අවසන් කරන්න
    public function close(Request $request, $id)
    {
        $request->validate([
            'closing_cash' => 'required|numeric|min:0',
        ]);

        $shift = Shift::find($id);

        if (!$shift || $shift->status === 'closed') {
            return response()->json(['message' => 'Shift not found or already closed.'], 404);
        }

        // බලාපොරොත්තු වන මුදල් ගණනය කරන්න (Opening Float + Cash Sales)
        $cashSales = Sale::where('user_id', $shift->user_id)
                         ->where('status', 'completed')
                         ->whereBetween('created_at', [$shift->opened_at, now()])
                         ->whereHas('payments', function($q) {
                             $q->where('method', 'cash');
                         })
                         ->sum('total_amount');

        $expectedCash = $shift->opening_float + $cashSales;
        $variance = $request->closing_cash - $expectedCash;

        $shift->update([
            'closing_cash' => $request->closing_cash,
            'expected_cash' => $expectedCash,
            'variance' => $variance,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Shift closed successfully!',
            'shift' => $shift
        ], 200);
    }

    // හැම ෂිෆ්ට් එකක්ම බලන්න
    public function index()
    {
        $shifts = Shift::latest()->get();
        return response()->json([
            'message' => 'Shifts retrieved successfully',
            'data' => $shifts
        ], 200);
    }
}