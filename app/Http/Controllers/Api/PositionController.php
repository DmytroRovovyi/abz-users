<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;

class PositionController extends Controller
{
    // Get all positions.
    public function index()
    {
        try {
            $positions = Position::all(['id', 'name']);

            if ($positions->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Positions not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'positions' => $positions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Positions not found',
            ], 422);
        }
    }

}

