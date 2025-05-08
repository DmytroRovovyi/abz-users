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
    // Display of user.
    public function index()
    {
        return response()->json(User::paginate(6));
    }

    // Edit user.
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
            $response = Http::withBasicAuth('api', env('TINIFY_API_KEY'))
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
}
