<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;

class PositionController extends Controller
{
    // Get all positions.
    public function index()
    {
        return response()->json([
            'success' => true,
            'positions' => Position::all(['id', 'name']),
        ]);
    }
}

