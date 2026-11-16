<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_installation_identity', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->uuid('installation_id')->unique();
            $table->timestamps();
        });

        DB::table('integration_installation_identity')->insert([
            'id' => 1,
            'installation_id' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_installation_identity');
    }
};
