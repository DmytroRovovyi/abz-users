<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPhotoPath = storage_path('app/tmp/default.jpg');

        if (!File::exists($defaultPhotoPath)) {
            File::copy(
                storage_path('app/tmp/e528c784-439c-4034-81dc-745ec5a2f44e.jpg'),
                $defaultPhotoPath
            );
        }

        for ($i = 1; $i <= 45; $i++) {
            $photoName = Str::uuid() . '.jpg';
            $photoStoragePath = 'photos/' . $photoName;

            Storage::disk('public')->put(
                $photoStoragePath,
                File::get($defaultPhotoPath)
            );

            User::create([
                'name'        => fake()->name(),
                'email'       => fake()->unique()->safeEmail(),
                'phone'       => '+380' . fake()->unique()->numerify('#########'),
                'position_id' => fake()->numberBetween(1, 4),
                'password'    => Hash::make('password123'),
                'photo'       => $photoStoragePath,
            ]);
        }
    }
}
