<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\JpegEncoder;

class UserController extends Controller
{
    // Get of users.
    public function index(Request $request)
    {
        $request->validate([
            'page' => 'integer|min:1',
            'count' => 'integer|min:1|max:100',
        ]);

        $count = $request->input('count', 5);
        $page = $request->input('page', 1);

        $users = User::orderBy('id')->paginate($count, ['*'], 'page', $page);

        $response = [
            'success' => true,
            'page' => $users->currentPage(),
            'total_pages' => $users->lastPage(),
            'total_users' => $users->total(),
            'count' => $users->perPage(),
            'links' => [
                'next_url' => $users->nextPageUrl(),
                'prev_url' => $users->previousPageUrl(),
            ],
            'users' => $users->items(),
        ];

        return response()->json($response);
    }

    // Get user details by ID.
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    // Create user.
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'surname'  => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'phone'    => 'required|string|unique:users',
            'password' => 'required|string|min:6',
            'photo'    => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Image processing.
        try {
            $image = Image::read($request->file('photo'))
                ->cover(70, 70)
                ->encode(new JpegEncoder(quality: 90));
            $filename = Str::uuid() . '.jpg';
            $localPath = storage_path("app/tmp/{$filename}");
            if (!file_exists(dirname($localPath))) {
                mkdir(dirname($localPath), 0755, true);
            }
            $image->save($localPath);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('TINIFY_API_KEY'),
            ])
                ->attach('file', fopen($localPath, 'r'), $filename)
                ->post('https://api.tinify.com/shrink');

            if (!$response->ok() || !isset($response['output']['url'])) {
                return response()->json(['error' => 'Image optimization failed'], 500);
            }

            $optimized = Http::get($response['output']['url']);
            Storage::disk('public')->put("photos/{$filename}", $optimized->body());
            unlink($localPath);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Image processing failed',
                'message' => $e->getMessage(),
            ], 500);
        }

        // Create user.
        $user = User::create([
            'name'     => $request->name,
            'surname'  => $request->surname,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
            'photo'    => "photos/{$filename}",
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    // Update user.
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'surname'  => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $id,
            'phone'    => 'required|string|unique:users,phone,' . $id,
            'password' => 'nullable|string|min:6',
            'photo'    => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            if ($request->hasFile('photo')) {
                $image = Image::read($request->file('photo'))
                    ->cover(70, 70)
                    ->encode(new JpegEncoder(quality: 90));
                $filename = Str::uuid() . '.jpg';
                $localPath = storage_path("app/tmp/{$filename}");
                if (!file_exists(dirname($localPath))) {
                    mkdir(dirname($localPath), 0755, true);
                }
                $image->save($localPath);
                $response = Http::withBasicAuth('api', env('TINIFY_API_KEY'))
                    ->attach('file', fopen($localPath, 'r'), $filename)
                    ->post('https://api.tinify.com/shrink');

                if (!$response->ok() || !isset($response['output']['url'])) {
                    return response()->json(['error' => 'Image optimization failed'], 500);
                }

                $optimized = Http::get($response['output']['url']);
                Storage::disk('public')->put("photos/{$filename}", $optimized->body());
                unlink($localPath);

                // видаляємо старе фото, якщо воно існує
                if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                    Storage::disk('public')->delete($user->photo);
                }

                $user->photo = "photos/{$filename}";
            }

            $user->name     = $request->name;
            $user->surname  = $request->surname;
            $user->email    = $request->email;
            $user->phone    = $request->phone;
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }
            $user->save();

            return response()->json([
                'success' => true,
                'user'    => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'User update failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


}
