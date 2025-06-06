<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\JpegEncoder;
use Illuminate\Support\Facades\Cache;
use function Tinify\setKey;
use function Tinify\fromFile;

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

        // Завантажуємо користувачів разом з позицією
        $users = User::with('position')->orderBy('id')->paginate($count, ['*'], 'page', $page);

        // Формуємо масив користувачів із потрібними полями
        $usersArray = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'position' => $user->position?->name ?? null,
                'position_id' => $user->position_id,
                'registration_timestamp' => $user->registration_timestamp,
                'photo' => $user->photo ? asset('storage/' . $user->photo) : null,
            ];
        });

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
            'users' => $usersArray,
        ];

        return response()->json($response);
    }

    // Get user details by ID.
    public function show($id)
    {
        $user = User::with('position')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'position' => $user->position?->name ?? null,
                'position_id' => $user->position_id,
                'registration_timestamp' => time(),
                'photo' => $user->photo ? asset('storage/' . $user->photo) : null,
            ],
        ]);
    }

    // Create user.
    public function store(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token || !Cache::pull('register_token_' . $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        // User data validation.
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|min:2|max:60',
            'email'       => 'required|email|max:255|unique:users,email',
            'phone'       => 'required|string|regex:/^\+380[0-9]{9}$/|unique:users,phone',
            'position_id' => 'required|exists:positions,id',
            'photo'       => 'nullable|image|mimes:jpeg,jpg|max:5120|dimensions:min_width=70,min_height=70',
        ]);

        if ($request->hasFile('photo')) {
            $image = $request->file('photo');
            $filename = Str::uuid() . '.' . $image->getClientOriginalExtension();
        } else {
            $filename = null;
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Image processing.
        if ($request->hasFile('photo')) {
            try {
                $imageFile = $request->file('photo');
                $filename = Str::uuid() . '.jpg';
                $image = Image::read($imageFile)
                    ->cover(70, 70)
                    ->encode(new JpegEncoder(quality: 90));
                $localPath = storage_path("app/tmp/{$filename}");

                if (!file_exists(dirname($localPath))) {
                    mkdir(dirname($localPath), 0755, true);
                }
                $image->save($localPath);
                $mime = mime_content_type($localPath);
                $size = filesize($localPath);
                Log::info('Prepared image info', ['mime' => $mime, 'size' => $size]);

                // --- TinyPNG SDK ---
                setKey(env('TINIFY_API_KEY'));
                $source = fromFile($localPath);
                $optimizedPath = storage_path("app/tmp/optimized_{$filename}");
                $source->toFile($optimizedPath);
                Storage::disk('public')->put("photos/{$filename}", file_get_contents($optimizedPath));
                unlink($localPath);
                unlink($optimizedPath);

            } catch (\Exception $e) {
                Log::error('Image processing or optimization failed', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'error' => 'Image processing failed',
                    'message' => $e->getMessage(),
                ], 500);
            }
        }


        // Create user.
        $user = User::create([
            'name'        => $request->name,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'position_id' => $request->position_id,
            'password'    => Hash::make($request->password),
            'registration_timestamp' => time(),
            'photo'       => "photos/{$filename}",
        ]);

        // Create token.
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
        $token = $request->bearerToken();

        if (!$token || !Cache::pull('register_token_' . $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|required|string|min:2|max:60',
            'email'       => 'sometimes|required|email|max:255|unique:users,email,' . $id,
            'phone'       => 'sometimes|required|string|regex:/^\+380[0-9]{9}$/|unique:users,phone,' . $id,
            'position_id' => 'sometimes|required|exists:positions,id',
            'photo'       => 'nullable|image|mimes:jpeg,jpg|max:5120|dimensions:min_width=70,min_height=70',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            if ($request->hasFile('photo')) {
                $imageFile = $request->file('photo');
                $filename = Str::uuid() . '.jpg';

                $image = Image::read($imageFile)
                    ->cover(70, 70)
                    ->encode(new JpegEncoder(quality: 90));

                $localPath = storage_path("app/tmp/{$filename}");
                if (!file_exists(dirname($localPath))) {
                    mkdir(dirname($localPath), 0755, true);
                }

                $image->save($localPath);

                // Оптимізація через TinyPNG
                setKey(env('TINIFY_API_KEY'));
                $source = fromFile($localPath);
                $optimizedPath = storage_path("app/tmp/optimized_{$filename}");
                $source->toFile($optimizedPath);

                Storage::disk('public')->put("photos/{$filename}", file_get_contents($optimizedPath));
                unlink($localPath);
                unlink($optimizedPath);

                // Видалення старого фото
                if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                    Storage::disk('public')->delete($user->photo);
                }

                $user->photo = "photos/{$filename}";
            }

            foreach (['name', 'email', 'phone', 'position_id', 'password'] as $field) {
                if ($request->filled($field)) {
                    switch ($field) {
                        case 'password':
                            $user->password = Hash::make($request->input($field));
                            break;
                        default:
                            $user->$field = $request->input($field);
                    }
                }
            }

            $user->save();

            return response()->json([
                'success' => true,
                'user'    => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'User update failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
