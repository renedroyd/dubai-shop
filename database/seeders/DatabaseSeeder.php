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
        $this->call([
            ClassicDubaiOperationalSeeder::class,
            NotificationTemplateSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            User::factory()->create([
                'name' => 'Test Administrator',
                'email' => 'admin@shop.integraltec.cu',
                'is_admin' => true,
                'password' => bcrypt('Versat*19'),
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
