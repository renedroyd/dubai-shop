<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('storefront_locale', 5)->nullable()->after('is_admin');
            $table->string('admin_locale', 5)->nullable()->after('storefront_locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['storefront_locale', 'admin_locale']);
        });
    }
};
