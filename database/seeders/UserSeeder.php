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
        $photoSourcePath = storage_path('app/tmp');
        $photoFiles = collect(File::files($photoSourcePath))
            ->filter(fn($file) => in_array($file->getExtension(), ['jpg', 'jpeg']))
            ->values();

        if ($photoFiles->isEmpty()) {
            $this->command->error('No image files found in storage/app/tmp');
            return;
        }

        for ($i = 0; $i < 45; $i++) {
            // Обираємо фото по черзі, або повторюємо з початку
            $photoFile = $photoFiles[$i % $photoFiles->count()];

            $photoName = Str::uuid() . '.jpg';
            $photoStoragePath = 'photos/' . $photoName;

            Storage::disk('public')->put(
                $photoStoragePath,
                File::get($photoFile)
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

        $this->command->info('45 users with photos have been seeded.');
    }
}
