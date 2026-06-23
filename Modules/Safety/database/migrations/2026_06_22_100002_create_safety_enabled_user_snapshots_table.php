<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_enabled_user_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->integer('total_enabled_users')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_enabled_user_snapshots');
    }
};
