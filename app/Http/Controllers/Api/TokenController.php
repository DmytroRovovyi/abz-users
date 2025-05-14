<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TokenController extends Controller
{
    // Get token.
    public function getToken()
    {
        $token = Str::random(60);

        Cache::put('register_token_' . $token, true, now()->addMinutes(40));

        return response()->json([
            'success' => true,
            'token' => $token,
        ]);
    }
}

