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
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    // Get of users.
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'sometimes|integer|min:1',
            'count' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'fails' => $validator->errors(),
            ], 422);
        }

        $count = $request->input('count', 5);
        $page = $request->input('page', 1);

        $users = User::orderBy('id')->paginate($count, ['*'], 'page', $page);

        if ($users->isEmpty() && $users->total() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Page not found',
            ], 404);
        }

        return response()->json([
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
        ]);
    }

    // Get user details by ID.
    public function show($id)
    {
        if (!ctype_digit($id)) {
            return response()->json([
                'success' => false,
                'message' => 'The user with the requested id does not exist.',
                'fails' => [
                    'userId' => [
                        'The user ID must be an integer.'
                    ]
                ]
            ], 400);
        }
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }


    // Create user.
    public function store(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token || !Cache::pull('register_token_' . $token)) {
            return response()->json([
                'success' => false,
                'message' => 'The token expired.'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|min:2|max:255',
            'surname'     => 'required|string|min:2|max:255',
            'email'       => 'required|email|unique:users,email',
            'phone'       => 'required|string|unique:users,phone',
            'password'    => 'required|string|min:6',
            'position_id' => 'required|integer|exists:positions,id',
            'photo'       => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'name.min'         => 'The name must be at least 2 characters.',
            'email.email'      => 'The email must be a valid email address.',
            'phone.required'   => 'The phone field is required.',
            'position_id.integer' => 'The position id must be an integer.',
            'photo.max'        => 'The photo may not be greater than 5 Mbytes.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'fails'   => $validator->errors(),
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

            $response = Http::withBasicAuth('api', env('TINIFY_API_KEY'))
                ->attach('file', fopen($localPath, 'r'), $filename)
                ->post('https://api.tinify.com/shrink');

            if (!$response->ok() || !isset($response['output']['url'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image optimization failed'
                ], 500);
            }

            $optimized = Http::get($response['output']['url']);
            Storage::disk('public')->put("photos/{$filename}", $optimized->body());
            unlink($localPath);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Image processing failed',
                'error'   => $e->getMessage(),
            ], 500);
        }

        // Create user.
        $user = User::create([
            'name'        => $request->name,
            'surname'     => $request->surname,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'password'    => Hash::make($request->password),
            'position_id' => $request->position_id,
            'photo'       => "photos/{$filename}",
        ]);

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'message' => 'New user successfully registered'
        ], 201);
    }

    // Update user.
    public function update(Request $request, $id)
    {
        $token = $request->bearerToken();

        if (!$token || !Cache::pull('register_token_' . $token)) {
            return response()->json([
                'success' => false,
                'message' => 'The token expired.'
            ], 401);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|min:2|max:255',
            'surname'     => 'required|string|min:2|max:255',
            'email'       => 'required|email|unique:users,email,' . $id,
            'phone'       => 'required|string|unique:users,phone,' . $id,
            'password'    => 'nullable|string|min:6',
            'position_id' => 'required|integer|exists:positions,id',
            'photo'       => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'name.min'         => 'The name must be at least 2 characters.',
            'email.email'      => 'The email must be a valid email address.',
            'phone.required'   => 'The phone field is required.',
            'position_id.integer' => 'The position id must be an integer.',
            'photo.max'        => 'The photo may not be greater than 5 Mbytes.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'fails'   => $validator->errors(),
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
                    return response()->json([
                        'success' => false,
                        'message' => 'Image optimization failed'
                    ], 500);
                }

                $optimized = Http::get($response['output']['url']);
                Storage::disk('public')->put("photos/{$filename}", $optimized->body());
                unlink($localPath);

                // Видаляємо старе фото
                if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                    Storage::disk('public')->delete($user->photo);
                }

                $user->photo = "photos/{$filename}";
            }

            // Update fields.
            $user->name        = $request->name;
            $user->surname     = $request->surname;
            $user->email       = $request->email;
            $user->phone       = $request->phone;
            $user->position_id = $request->position_id;
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
                'success' => false,
                'message' => 'User update failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
