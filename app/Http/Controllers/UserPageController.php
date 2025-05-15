<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UserPageController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->input('page', 1);

        $response = Http::get("https://abz-users.ddev.site/api/v1/users", [
            'page' => $page,
            'count' => 6,
        ]);

        $users = $response->json()['users'] ?? [];

        if ($request->ajax()) {
            return response()->json($users);
        }

        $positions = Http::get('https://abz-users.ddev.site/api/v1/positions')->json()['positions'] ?? [];

        return view('users', compact('users', 'positions'));
    }

    public function store(Request $request)
    {
        $tokenRes = Http::get('https://abz-users.ddev.site/api/v1/token');
        $token = $tokenRes->json()['token'] ?? null;

        if (!$token) {
            return back()->with('error', 'Token not received');
        }

        $photo = $request->file('photo');

        $response = Http::withToken($token)
            ->attach('photo', fopen($photo->getPathname(), 'r'), $photo->getClientOriginalName())
            ->asMultipart()
            ->post('https://abz-users.ddev.site/api/v1/users', [
                ['name' => 'name', 'contents' => $request->name],
                ['name' => 'email', 'contents' => $request->email],
                ['name' => 'phone', 'contents' => $request->phone],
                ['name' => 'position_id', 'contents' => $request->position_id],
            ]);

        return redirect('/')->with('status', $response->json());
    }
}
