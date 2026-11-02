<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(ClassicDubaiOperationalSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            User::factory()->create([
                'name' => 'Test Administrator',
                'email' => 'admin@example.test',
                'is_admin' => true,
            ]);
        }

        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');

        if (filled($adminEmail) && filled($adminPassword)) {
            User::query()->updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => env('ADMIN_NAME', 'Store Administrator'),
                    'password' => $adminPassword,
                    'is_admin' => true,
                ],
            );
        }
    }
}
