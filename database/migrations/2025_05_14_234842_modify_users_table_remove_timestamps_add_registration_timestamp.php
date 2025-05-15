<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropTimestamps();

            $table->unsignedBigInteger('registration_timestamp')->nullable()->after('position_id');
        });

    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamps();

            $table->dropColumn('registration_timestamp');
        });
    }
};
